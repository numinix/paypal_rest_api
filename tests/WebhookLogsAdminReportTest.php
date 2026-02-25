<?php
declare(strict_types=1);

/**
 * Test to verify PayPal Webhook Logs admin report setup
 *
 * This test verifies:
 * 1. FILENAME_PAYPALAC_WEBHOOK_LOGS constant is defined in extra_datafiles
 * 2. BOX_PAYPALAC_WEBHOOK_LOGS constant is defined in extra_definitions
 * 3. TABLE_PAYPAL_WEBHOOKS constant is defined in ppac_database_tables.php
 * 4. The admin report page file exists
 * 5. The version patch in paypalac.php registers the webhook logs admin page
 * 6. The version patch creates the paypal_webhooks table
 * 7. CURRENT_VERSION is bumped to 1.3.11
 * 8. WebhookController logs all webhook outcomes with verification_status
 * 9. v1.3.11 upgrade adds verification_status column
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running PayPal Webhook Logs Admin Report Tests...\n\n";

$basePath = dirname(__DIR__);
$failures = 0;

// ---- Test 1: FILENAME constant in extra_datafiles ----
echo "Test 1: Checking FILENAME_PAYPALAC_WEBHOOK_LOGS in extra_datafiles...\n";
if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', '');
}
require_once $basePath . '/admin/includes/extra_datafiles/paypalac_filenames.php';
if (defined('FILENAME_PAYPALAC_WEBHOOK_LOGS') && constant('FILENAME_PAYPALAC_WEBHOOK_LOGS') === 'paypalac_webhook_logs') {
    echo "✓ FILENAME_PAYPALAC_WEBHOOK_LOGS is defined with correct value\n\n";
} else {
    echo "✗ FILENAME_PAYPALAC_WEBHOOK_LOGS is not correctly defined\n\n";
    $failures++;
}

// ---- Test 2: BOX constant in extra_definitions ----
echo "Test 2: Checking BOX_PAYPALAC_WEBHOOK_LOGS in extra_definitions...\n";
require_once $basePath . '/admin/includes/languages/english/extra_definitions/paypalac_admin_names.php';
if (defined('BOX_PAYPALAC_WEBHOOK_LOGS') && constant('BOX_PAYPALAC_WEBHOOK_LOGS') === 'PayPal Webhook Logs') {
    echo "✓ BOX_PAYPALAC_WEBHOOK_LOGS is defined with correct value\n\n";
} else {
    echo "✗ BOX_PAYPALAC_WEBHOOK_LOGS is not correctly defined\n\n";
    $failures++;
}

// ---- Test 3: TABLE_PAYPAL_WEBHOOKS constant ----
echo "Test 3: Checking TABLE_PAYPAL_WEBHOOKS in ppac_database_tables.php...\n";
require_once $basePath . '/admin/includes/extra_datafiles/ppac_database_tables.php';
if (defined('TABLE_PAYPAL_WEBHOOKS') && constant('TABLE_PAYPAL_WEBHOOKS') === 'paypal_webhooks') {
    echo "✓ TABLE_PAYPAL_WEBHOOKS is defined with correct value\n\n";
} else {
    echo "✗ TABLE_PAYPAL_WEBHOOKS is not correctly defined\n\n";
    $failures++;
}

// ---- Test 4: Admin report page file exists ----
echo "Test 4: Checking admin report page file exists...\n";
$adminPage = $basePath . '/admin/paypalac_webhook_logs.php';
if (file_exists($adminPage)) {
    echo "✓ admin/paypalac_webhook_logs.php exists\n\n";
} else {
    echo "✗ admin/paypalac_webhook_logs.php does not exist\n\n";
    $failures++;
}

// ---- Test 5: Version constant updated to 1.3.11 ----
echo "Test 5: Checking CURRENT_VERSION is 1.3.11...\n";
$paypalacFile = $basePath . '/includes/modules/payment/paypalac.php';
$paypalacContent = file_get_contents($paypalacFile);
if (strpos($paypalacContent, "protected const CURRENT_VERSION = '1.3.11'") !== false) {
    echo "✓ CURRENT_VERSION is set to 1.3.11\n\n";
} else {
    echo "✗ CURRENT_VERSION is not set to 1.3.11\n\n";
    $failures++;
}

// ---- Test 6: v1.3.10 upgrade case exists ----
echo "Test 6: Checking for v1.3.10 upgrade case in tableCheckup()...\n";
if (preg_match("/case version_compare\(MODULE_PAYMENT_PAYPALAC_VERSION, '1\.3\.10', '<'\)/", $paypalacContent)) {
    echo "✓ Version 1.3.10 upgrade case exists\n\n";
} else {
    echo "✗ Version 1.3.10 upgrade case not found\n\n";
    $failures++;
}

// ---- Test 7: Webhook table creation SQL in upgrade ----
echo "Test 7: Checking for paypal_webhooks table creation in upgrade...\n";
if (strpos($paypalacContent, 'CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_WEBHOOKS') !== false) {
    echo "✓ paypal_webhooks table creation SQL found in upgrade\n\n";
} else {
    echo "✗ paypal_webhooks table creation SQL not found in upgrade\n\n";
    $failures++;
}

// ---- Test 8: Admin page registration in upgrade ----
echo "Test 8: Checking for paypalacWebhookLogs admin page registration...\n";
if (strpos($paypalacContent, "zen_page_key_exists('paypalacWebhookLogs')") !== false &&
    strpos($paypalacContent, "'paypalacWebhookLogs'") !== false &&
    strpos($paypalacContent, "'BOX_PAYPALAC_WEBHOOK_LOGS'") !== false &&
    strpos($paypalacContent, "'FILENAME_PAYPALAC_WEBHOOK_LOGS'") !== false) {
    echo "✓ paypalacWebhookLogs admin page registration found\n\n";
} else {
    echo "✗ paypalacWebhookLogs admin page registration not found\n\n";
    $failures++;
}

// ---- Test 9: Admin page registered under 'reports' menu ----
echo "Test 9: Checking admin page is registered under 'reports' menu...\n";
// Look for zen_register_admin_page call with 'reports' as parent
if (preg_match("/zen_register_admin_page\(\s*'paypalacWebhookLogs'.*?'reports'/s", $paypalacContent)) {
    echo "✓ paypalacWebhookLogs is registered under 'reports' menu\n\n";
} else {
    echo "✗ paypalacWebhookLogs is not registered under 'reports' menu\n\n";
    $failures++;
}

// ---- Test 10: Report page contains pagination, search, and clear functionality ----
echo "Test 10: Checking report page has required features...\n";
$reportContent = file_get_contents($adminPage);
$hasSearch = strpos($reportContent, 'search') !== false;
$hasPagination = strpos($reportContent, 'nmx-pagination') !== false || strpos($reportContent, 'nmx-list-pagination') !== false;
$hasClear = strpos($reportContent, 'clear') !== false || strpos($reportContent, 'Clear') !== false;
$hasNuminixStyle = strpos($reportContent, 'numinix_admin.css') !== false;
$hasTable = strpos($reportContent, 'nmx-table') !== false;

if ($hasSearch && $hasPagination && $hasClear && $hasNuminixStyle && $hasTable) {
    echo "✓ Report page has search, pagination, clear, Numinix styling, and table\n\n";
} else {
    $missing = [];
    if (!$hasSearch) $missing[] = 'search';
    if (!$hasPagination) $missing[] = 'pagination';
    if (!$hasClear) $missing[] = 'clear';
    if (!$hasNuminixStyle) $missing[] = 'Numinix styling';
    if (!$hasTable) $missing[] = 'table display';
    echo "✗ Report page is missing: " . implode(', ', $missing) . "\n\n";
    $failures++;
}

// ---- Test 11: Correct file separation (FILENAME in datafiles, BOX in definitions) ----
echo "Test 11: Checking correct constant separation for webhook logs...\n";
$datafilesContent = file_get_contents($basePath . '/admin/includes/extra_datafiles/paypalac_filenames.php');
$definitionsContent = file_get_contents($basePath . '/admin/includes/languages/english/extra_definitions/paypalac_admin_names.php');

$filenameInDatafiles = strpos($datafilesContent, 'FILENAME_PAYPALAC_WEBHOOK_LOGS') !== false;
$boxInDefinitions = strpos($definitionsContent, 'BOX_PAYPALAC_WEBHOOK_LOGS') !== false;
$filenameNotInDefinitions = strpos($definitionsContent, 'FILENAME_PAYPALAC_WEBHOOK_LOGS') === false;
$boxNotInDatafiles = strpos($datafilesContent, 'BOX_PAYPALAC_WEBHOOK_LOGS') === false;

if ($filenameInDatafiles && $boxInDefinitions && $filenameNotInDefinitions && $boxNotInDatafiles) {
    echo "✓ Constants are correctly separated between datafiles and definitions\n\n";
} else {
    echo "✗ Constants are not correctly separated\n\n";
    $failures++;
}

// ---- Test 12: WebhookController saveToDatabase includes verification_status ----
echo "Test 12: Checking WebhookController saves verification_status...\n";
$controllerFile = $basePath . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Webhooks/WebhookController.php';
$controllerContent = file_get_contents($controllerFile);
$hasSaveParam = strpos($controllerContent, "verification_status") !== false;
$hasSaveAllPaths = strpos($controllerContent, "'ignored'") !== false
    && strpos($controllerContent, "'failed'") !== false
    && strpos($controllerContent, "'verified'") !== false
    && strpos($controllerContent, "'skipped'") !== false;

if ($hasSaveParam && $hasSaveAllPaths) {
    echo "✓ WebhookController logs all webhook outcomes with verification_status\n\n";
} else {
    $missing = [];
    if (!$hasSaveParam) $missing[] = 'verification_status parameter';
    if (!$hasSaveAllPaths) $missing[] = 'all status paths (verified/failed/ignored/skipped)';
    echo "✗ WebhookController is missing: " . implode(', ', $missing) . "\n\n";
    $failures++;
}

// ---- Test 13: v1.3.11 upgrade adds verification_status column ----
echo "Test 13: Checking v1.3.11 upgrade adds verification_status column...\n";
if (preg_match("/case version_compare\(MODULE_PAYMENT_PAYPALAC_VERSION, '1\.3\.11', '<'\)/", $paypalacContent)
    && strpos($paypalacContent, "ADD verification_status") !== false) {
    echo "✓ v1.3.11 upgrade adds verification_status column\n\n";
} else {
    echo "✗ v1.3.11 upgrade for verification_status column not found\n\n";
    $failures++;
}

// ---- Test 14: Admin report displays verification status ----
echo "Test 14: Checking admin report shows verification status...\n";
$hasStatusHeading = strpos($reportContent, 'TABLE_HEADING_STATUS') !== false;
$hasStatusLabel = strpos($reportContent, 'TEXT_LABEL_VERIFICATION_STATUS') !== false;
$hasStatusColumn = strpos($reportContent, 'verification_status') !== false;

if ($hasStatusHeading && $hasStatusLabel && $hasStatusColumn) {
    echo "✓ Admin report displays verification status in list and detail views\n\n";
} else {
    $missing = [];
    if (!$hasStatusHeading) $missing[] = 'status table heading';
    if (!$hasStatusLabel) $missing[] = 'status detail label';
    if (!$hasStatusColumn) $missing[] = 'verification_status field';
    echo "✗ Admin report is missing: " . implode(', ', $missing) . "\n\n";
    $failures++;
}

// ---- Final Summary ----
echo "========================================\n";
if ($failures > 0) {
    echo "❌ $failures test(s) failed!\n";
    exit(1);
} else {
    echo "All PayPal Webhook Logs Admin Report tests passed! ✓\n";
}
echo "========================================\n";
