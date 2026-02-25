<?php
/**
 * Remove Expired Cards Cron
 * 
 * Marks expired saved credit cards as deleted in the database.
 * This cleanup script runs independently of subscription processing.
 * 
 * Compatible with all payment modules that use TABLE_SAVED_CREDIT_CARDS:
 * - paypalwpp.php (Website Payments Pro)
 * - paypaldp.php (Direct Payments)
 * - paypalac.php (REST API)
 * - payflow.php (Payflow)
 */

require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';

$cardsUpdated = 0;
$log = [];

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

require_once 'includes/application_bottom.php';
