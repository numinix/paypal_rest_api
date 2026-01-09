<?php
require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php';
require_once DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/debug.php';
require_once DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/functions.php';

$_SESSION['in_cron'] = true;

$paypalSavedCardRecurring = new paypalSavedCardRecurring();
$paypalRestClient = $paypalSavedCardRecurring->get_paypal_rest_client();
$paypalLegacyClient = $paypalSavedCardRecurring->get_paypal_legacy_client();
$profileManager = PayPalProfileManager::create($paypalRestClient, $paypalLegacyClient);

zen_paypal_subscription_refresh_queue_ensure_schema();

$maxJobs = 50;
if (isset($argv[1]) && (int) $argv[1] > 0) {
    $maxJobs = (int) $argv[1];
}

$batchSize = min($maxJobs, 10);
$workerId = php_uname('n') . ':' . getmypid();

$metricsBefore = zen_paypal_subscription_refresh_queue_metrics();
echo '[' . date('Y-m-d H:i:s') . '] Worker starting - queue pending ' . (int) $metricsBefore['pending'] . ', locked ' . (int) $metricsBefore['locked'] . ', total ' . (int) $metricsBefore['total'] . "\n";

$processed = 0;
$succeeded = 0;
$skipped = 0;
$failed = 0;

while ($processed < $maxJobs) {
    $jobs = zen_paypal_subscription_refresh_queue_claim($batchSize, array(
        'worker_id' => $workerId,
    ));

    if (empty($jobs)) {
        break;
    }

    foreach ($jobs as $job) {
        if ($processed >= $maxJobs) {
            break;
        }

        $processed++;
        $jobId = isset($job['queue_id']) ? (int) $job['queue_id'] : 0;
        $customerId = isset($job['customers_id']) ? (int) $job['customers_id'] : 0;
        $profileId = isset($job['profile_id']) ? $job['profile_id'] : '';
        $context = array();
        if (isset($job['context']) && is_string($job['context']) && $job['context'] !== '') {
            $decoded = json_decode($job['context'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $start = microtime(true);
        $result = zen_paypal_subscription_refresh_process_job($job, $profileManager, array(
            'saved_card_recurring' => $paypalSavedCardRecurring,
        ));
        $elapsed = microtime(true) - $start;

        if (!is_array($result)) {
            $result = array('success' => false, 'error' => 'unknown_result');
        }

        if (!empty($result['success'])) {
            zen_paypal_subscription_refresh_queue_complete($jobId);
            if (isset($result['status']) && $result['status'] === 'missing_subscription') {
                $skipped++;
                echo '[' . date('Y-m-d H:i:s') . '] Skipped missing subscription ' . $profileId . ' for customer ' . $customerId . ' (job ' . $jobId . ', ' . number_format($elapsed, 3) . "s)\n";
            } else {
                $succeeded++;
                $status = isset($result['status']) ? $result['status'] : 'refreshed';
                echo '[' . date('Y-m-d H:i:s') . '] Refreshed profile ' . $profileId . ' for customer ' . $customerId . ' (job ' . $jobId . ', status: ' . $status . ', ' . number_format($elapsed, 3) . "s)\n";
            }
        } else {
            $failed++;
            $error = isset($result['error']) ? $result['error'] : 'unknown_error';
            $retrySeconds = isset($result['retry_seconds']) ? (int) $result['retry_seconds'] : 300;
            zen_paypal_subscription_refresh_queue_fail($jobId, $error, $retrySeconds);
            echo '[' . date('Y-m-d H:i:s') . '] Failed profile ' . $profileId . ' for customer ' . $customerId . ' (job ' . $jobId . ', error: ' . $error . ', ' . number_format($elapsed, 3) . "s)\n";
        }
    }
}

$metricsAfter = zen_paypal_subscription_refresh_queue_metrics();
echo '[' . date('Y-m-d H:i:s') . '] Worker complete - processed ' . $processed . ', succeeded ' . $succeeded . ', skipped ' . $skipped . ', failed ' . $failed . "\n";
echo '[' . date('Y-m-d H:i:s') . '] Queue pending ' . (int) $metricsAfter['pending'] . ', locked ' . (int) $metricsAfter['locked'] . ', total ' . (int) $metricsAfter['total'] . "\n";
if (!empty($metricsAfter['oldest_available'])) {
    echo '[' . date('Y-m-d H:i:s') . '] Oldest pending available_at: ' . $metricsAfter['oldest_available'] . "\n";
}

require 'includes/application_bottom.php';
