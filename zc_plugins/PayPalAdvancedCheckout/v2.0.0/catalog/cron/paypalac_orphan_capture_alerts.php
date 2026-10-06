<?php
/**
 * PayPal Advanced Checkout — orphan capture/authorization alert cron.
 *
 * Reports rows in paypal_ac_capture_reservation with orders_id = 0 (PayPal payment
 * reserved, no Zen Cart order). Does not refund, void, or delete rows — ops must
 * check PayPal (and any later money-order orders) before acting.
 *
 * Schedule every 5–15 minutes. Rows newer than 5 minutes are skipped so in-flight
 * checkouts are not reported. Each capture id is emailed at most once per 24 hours
 * until resolved (orders_id set, or a related Zen order id is recorded on the row).
 *
 * Run from any CWD:
 *   php cron/paypalac_orphan_capture_alerts.php
 *   php zc_plugins/PayPalAdvancedCheckout/cron.php paypalac_orphan_capture_alerts
 */

// Dual resolve: catalog-root cron/ shim, or encapsulated catalog/cron via stable dispatcher.
$ppacConfigure = null;
foreach ([
    __DIR__ . '/../includes/configure.php',
    __DIR__ . '/../../../../../includes/configure.php',
] as $ppacConfigureCandidate) {
    if (is_file($ppacConfigureCandidate)) {
        $ppacConfigure = $ppacConfigureCandidate;
        break;
    }
}
if ($ppacConfigure === null) {
    $ppacWalk = __DIR__;
    for ($ppacI = 0; $ppacI < 8; $ppacI++) {
        $ppacConfigureCandidate = $ppacWalk . '/includes/configure.php';
        if (is_file($ppacConfigureCandidate)) {
            $ppacConfigure = $ppacConfigureCandidate;
            break;
        }
        $ppacParent = dirname($ppacWalk);
        if ($ppacParent === $ppacWalk) {
            break;
        }
        $ppacWalk = $ppacParent;
    }
}
if ($ppacConfigure === null) {
    fwrite(STDERR, "PayPal orphan alert cron: includes/configure.php not found from " . __DIR__ . "\n");
    exit(1);
}
require $ppacConfigure;

ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';

// Prefer encapsulated package (overlay modules may be purged); fall back to legacy paths.
if (is_file(DIR_FS_CATALOG . 'ppac_paths.php')) {
    require_once DIR_FS_CATALOG . 'ppac_paths.php';
    ppac_require_catalog_includes_file('modules/payment/paypal/ppacAutoload.php');
    ppac_require_catalog_includes_file('modules/payment/paypal/paypal_common.php');
} elseif (is_file(dirname(__DIR__) . '/includes/modules/payment/paypal/ppacAutoload.php')) {
    require_once dirname(__DIR__) . '/includes/modules/payment/paypal/ppacAutoload.php';
    require_once dirname(__DIR__) . '/includes/modules/payment/paypal/paypal_common.php';
} else {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_common.php';
}

use PayPalAdvancedCheckout\Common\Logger;

$_SESSION['in_cron'] = true;

$run_date = date('Y-m-d');
$timezone = date_default_timezone_get();
$report_id = uniqid();
$generated_at = date('Y-m-d H:i:s');
$min_age_minutes = 5;
$alert_cooldown_hours = 24;
$reported = 0;
$error = '';
$detail_lines = [];
$capture_ids = [];

try {
    $paymentModule = new class {
        public $code = 'paypalac_orphan_cron';
        public $log;
    };
    $paymentModule->log = new Logger();
    if (defined('MODULE_PAYMENT_PAYPALAC_DEBUGGING')
        && strpos((string)MODULE_PAYMENT_PAYPALAC_DEBUGGING, 'Log') !== false
    ) {
        $paymentModule->log->enableDebug();
    }

    $paypalCommon = new PayPalCommon($paymentModule);
    $result = $paypalCommon->alertAgedOrphanCaptureReservations(
        $paymentModule,
        $min_age_minutes,
        $alert_cooldown_hours
    );
    $reported = (int)($result['count'] ?? 0);
    $detail_lines = is_array($result['lines'] ?? null) ? $result['lines'] : [];
    $capture_ids = is_array($result['capture_ids'] ?? null) ? $result['capture_ids'] : [];
} catch (Throwable $e) {
    $error = $e->getMessage();
}

echo "Orphan capture alert completed\n";
echo "Min age (minutes): {$min_age_minutes}\n";
echo "Alert cooldown (hours): {$alert_cooldown_hours}\n";
echo "Orphans reported: {$reported}\n";
if ($error !== '') {
    echo "Error: {$error}\n";
}
if ($detail_lines !== []) {
    echo "\n" . implode("\n", $detail_lines) . "\n";
}

$summary = "Orphan Capture Alert — {$run_date} ({$timezone})\n"
    . "Min age (minutes): {$min_age_minutes}\n"
    . "Alert cooldown (hours): {$alert_cooldown_hours}\n"
    . "Orphans reported: {$reported}\n"
    . ($error !== '' ? "Error: {$error}\n" : '')
    . "\nNo automatic refund. Review PayPal and any follow-up money-order orders before acting.\n"
    . "Clear via Admin → Customers → PayPal Orphan Captures (Refund, Void, or Link order).\n"
    . ($detail_lines !== [] ? "\n" . implode("\n", $detail_lines) . "\n" : '')
    . "\nReport ID: {$report_id}\n"
    . "Generated: {$generated_at}";

$summary_html = '<h1 style="margin: 0 0 16px; font-size: 22px; color: #0f172a;">Orphan Capture Alert &mdash; '
    . htmlspecialchars($run_date, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') . ')</h1>'
    . '<p><strong>Min age (minutes):</strong> ' . (int)$min_age_minutes . '</p>'
    . '<p><strong>Alert cooldown (hours):</strong> ' . (int)$alert_cooldown_hours . '</p>'
    . '<p><strong>Orphans reported:</strong> ' . (int)$reported . '</p>'
    . '<p>No automatic refund. Review PayPal and any follow-up orders before acting. Clear via Admin &rarr; Customers &rarr; PayPal Orphan Captures (Refund, Void, or Link order).</p>'
    . ($error !== '' ? '<p><strong>Error:</strong> ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '')
    . ($detail_lines !== []
        ? '<pre style="font-family: monospace; white-space: pre-wrap;">'
            . htmlspecialchars(implode("\n", $detail_lines), ENT_QUOTES, 'UTF-8')
            . '</pre>'
        : '')
    . '<p><strong>Report ID:</strong> ' . htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Generated:</strong> ' . htmlspecialchars($generated_at, ENT_QUOTES, 'UTF-8') . '</p>';

$notification_email = '';
if (defined('MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL')) {
    $notification_email = trim((string)MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL);
}
if ($notification_email === '' && defined('STORE_OWNER_EMAIL_ADDRESS')) {
    $notification_email = trim((string)STORE_OWNER_EMAIL_ADDRESS);
}

if ($notification_email !== '' && ($reported > 0 || $error !== '')) {
    // zen_mail(): '' = sent, false = aborted, non-empty string = transport error.
    $mail_result = zen_mail(
        $notification_email,
        $notification_email,
        'PayPal Advanced Checkout Orphan Capture Alert Log',
        $summary,
        STORE_NAME,
        EMAIL_FROM,
        ['EMAIL_MESSAGE_HTML' => $summary_html],
        'orphan_capture_alert_log'
    );

    // Cooldown only after a real send — failed/aborted mail must retry next run.
    if ($mail_result === ''
        && $reported > 0
        && $capture_ids !== []
        && isset($paypalCommon)
        && is_object($paypalCommon)
    ) {
        $paypalCommon->markOrphanCaptureReservationsAlerted($capture_ids);
    }
}

require_once 'includes/application_bottom.php';
