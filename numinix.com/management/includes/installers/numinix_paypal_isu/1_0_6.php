<?php
/**
 * Numinix PayPal ISU Installer - Version 1.0.6
 *
 * Creates the onboarding tracking table for cross-session merchant_id persistence.
 * This table allows the completion page (popup) to store the merchant_id received
 * from PayPal, which can then be retrieved by subsequent status polling requests
 * that come from a different session context.
 *
 * Security features:
 * - Records expire after 1 hour and are automatically cleaned up
 * - Records are deleted immediately after successful credential retrieval
 * - Only tracking_id and merchant_id are stored (minimal data)
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack, $configuration_group_id;

// Define the table name constant if not already defined
if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
    define('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING', DB_PREFIX . 'numinix_paypal_onboarding_tracking');
}

$tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;

// Create the tracking table if it doesn't exist
$createTableSql = "CREATE TABLE IF NOT EXISTS " . $tableName . " (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_id VARCHAR(64) NOT NULL,
    merchant_id VARCHAR(32) NOT NULL,
    environment VARCHAR(10) NOT NULL DEFAULT 'sandbox',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_tracking_id (tracking_id),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->Execute($createTableSql);
    
    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Created PayPal onboarding tracking table for cross-session merchant_id persistence.', 'success');
    }
} catch (Throwable $e) {
    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Failed to create PayPal onboarding tracking table: ' . $e->getMessage(), 'error');
    }
}

// Note: Version number updates are handled automatically by init_numinix_paypal_isu.php
// after each installer file runs. Updating the version here would cause the installer
// to be skipped on subsequent runs even if the table creation above fails.
