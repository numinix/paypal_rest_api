<?php
/**
 * PayPal Advanced Checkout - Remove Expired Cards Cron
 * 
 * Marks expired saved credit cards as deleted in the database.
 * This cleanup script runs independently of subscription processing.
 * 
 * Handles both TABLE_SAVED_CREDIT_CARDS and TABLE_PAYPAL_VAULT entries.
 */

require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';

$cardsUpdated = 0;
$log = [];

$run_date = date('Y-m-d');
$timezone = date_default_timezone_get();
$report_id = uniqid();
$generated_at = date('Y-m-d H:i:s');

// Check if the saved credit cards table exists and has the expected structure
if (defined('TABLE_SAVED_CREDIT_CARDS')) {
    // Mark cards as deleted where expiry date has passed
    // Expiry is stored in MMYY format
    $sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . " 
            SET is_deleted = '1'
            WHERE is_deleted = '0' 
            AND expiry IS NOT NULL
            AND expiry <> ''
            AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) < CURDATE()";
    
    $db->Execute($sql);
    $cardsUpdated = $db->affectedRows();
    
    if ($cardsUpdated > 0) {
        $log[] = "Marked $cardsUpdated expired card(s) as deleted";
    }
}

// Also check PayPal Vault table for REST API cards
if (defined('TABLE_PAYPAL_VAULT')) {
    // Mark vaulted cards as inactive where expiry date has passed
    // Vault expiry may be stored in various formats (MMYY, MM/YY, MM-YY)
    $vaultSql = "UPDATE " . TABLE_PAYPAL_VAULT . " 
                 SET status = 'expired'
                 WHERE status = 'active' 
                 AND expiry IS NOT NULL
                 AND expiry <> ''
                 AND (
                     (LENGTH(expiry) = 4 AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) < CURDATE())
                     OR (LENGTH(expiry) = 5 AND expiry LIKE '%/%' AND LAST_DAY(STR_TO_DATE(expiry, '%m/%y')) < CURDATE())
                     OR (LENGTH(expiry) = 5 AND expiry LIKE '%-%' AND LAST_DAY(STR_TO_DATE(expiry, '%m-%y')) < CURDATE())
                     OR (LENGTH(expiry) = 7 AND expiry LIKE '%/%' AND LAST_DAY(STR_TO_DATE(expiry, '%m/%Y')) < CURDATE())
                     OR (LENGTH(expiry) = 7 AND expiry LIKE '%-%' AND LAST_DAY(STR_TO_DATE(expiry, '%m-%Y')) < CURDATE())
                 )";
    
    $db->Execute($vaultSql);
    $vaultCardsUpdated = $db->affectedRows();
    
    if ($vaultCardsUpdated > 0) {
        $log[] = "Marked $vaultCardsUpdated expired vaulted card(s) as expired";
        $cardsUpdated += $vaultCardsUpdated;
    }
}

// Output results
echo "Expired cards cleanup completed\n";
echo "Total cards processed: $cardsUpdated\n";

if (!empty($log)) {
    echo "\nLog:\n";
    foreach ($log as $entry) {
        echo "- " . $entry . "\n";
    }
}

$summary = "Expired Cards Cleanup — {$run_date} ({$timezone})\n"
    . "Total Cards Processed: {$cardsUpdated}\n\n"
    . "Report ID: {$report_id}\n\n"
    . "Generated: {$generated_at}";

$summary_html = '<h1 style="margin: 0 0 16px; font-size: 22px; color: #0f172a;">Expired Cards Cleanup &mdash; '
    . htmlspecialchars($run_date, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') . ')</h1>'
    . '<p><strong>Total Cards Processed:</strong> ' . (int)$cardsUpdated . '</p>'
    . '<p><strong>Report ID:</strong> ' . htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Generated:</strong> ' . htmlspecialchars($generated_at, ENT_QUOTES, 'UTF-8') . '</p>';

$notification_email = '';
if (defined('MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL')) {
    $notification_email = trim((string)MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL);
}

if ($notification_email !== '') {
    zen_mail(
        $notification_email,
        $notification_email,
        'PayPal Advanced Checkout Expired Cards Cleanup Log',
        $summary,
        STORE_NAME,
        EMAIL_FROM,
        array('EMAIL_MESSAGE_HTML' => $summary_html),
        'expired_cards_cleanup_log'
    );
}

require_once 'includes/application_bottom.php';
