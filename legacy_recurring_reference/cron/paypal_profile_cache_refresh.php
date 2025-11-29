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
$paypalLegacyClient = ($paypalSavedCardRecurring->get_paypal_legacy_client());
$PayPalProfileManager = PayPalProfileManager::create($paypalRestClient, $paypalLegacyClient);

$cacheTable = zen_paypal_subscription_cache_table_name();
$ttlSeconds = zen_paypal_subscription_profile_cache_ttl();
$staleThreshold = $ttlSeconds > 0 ? date('Y-m-d H:i:s', time() - $ttlSeconds) : null;

$limit = 100;
$activeStatuses = array('Active', 'Suspended', 'Pending', 'Approved');
$statusList = "('" . implode("','", array_map('zen_db_input', $activeStatuses)) . "')";

$sql = 'SELECT pr.customers_id, pr.profile_id, pr.status, pr.next_payment_date'
    . ' FROM ' . TABLE_PAYPAL_RECURRING . ' pr'
    . ' LEFT JOIN ' . $cacheTable . ' pc ON pc.customers_id = pr.customers_id AND pc.profile_id = pr.profile_id'
    . " WHERE pr.profile_id IS NOT NULL AND pr.profile_id <> ''"
    . '   AND pr.customers_id > 0'
    . '   AND pr.status IS NOT NULL'
    . '   AND pr.status IN ' . $statusList;

if ($staleThreshold !== null) {
    $sql .= "   AND (pc.refreshed_at IS NULL OR pc.refreshed_at < '" . zen_db_input($staleThreshold) . "')";
}

$sql .= ' ORDER BY (pr.next_payment_date IS NULL), pr.next_payment_date ASC, pr.subscription_id DESC';
$sql .= ' LIMIT ' . (int) $limit . ';';

$subscriptionsToRefresh = $db->Execute($sql);

$profiles = array();
while (!$subscriptionsToRefresh->EOF) {
    $profiles[] = array(
        'customers_id' => (int) $subscriptionsToRefresh->fields['customers_id'],
        'profile_id' => $subscriptionsToRefresh->fields['profile_id'],
        'status' => $subscriptionsToRefresh->fields['status'],
        'next_payment_date' => $subscriptionsToRefresh->fields['next_payment_date'],
    );
    $subscriptionsToRefresh->MoveNext();
}

$enqueued = 0;
if (!empty($profiles)) {
    zen_paypal_subscription_refresh_queue_ensure_schema();
    $enqueued = zen_paypal_subscription_refresh_queue_enqueue_many(
        $profiles,
        array(
            'available_at' => date('Y-m-d H:i:s'),
            'context' => array(
                'source' => 'cron.paypal_profile_cache_refresh',
                'reason' => 'stale_cache',
            ),
        )
    );
}

$metrics = zen_paypal_subscription_refresh_queue_metrics();

echo '[' . date('Y-m-d H:i:s') . '] Enqueued ' . (int) $enqueued . ' profile refresh jobs' . "\n";
echo '[' . date('Y-m-d H:i:s') . '] Queue depth: ' . (int) $metrics['pending'] . ' pending, ' . (int) $metrics['locked'] . ' locked, total ' . (int) $metrics['total'] . "\n";
if (!empty($metrics['oldest_available'])) {
    echo '[' . date('Y-m-d H:i:s') . '] Oldest pending available_at: ' . $metrics['oldest_available'] . "\n";
}

require 'includes/application_bottom.php';
