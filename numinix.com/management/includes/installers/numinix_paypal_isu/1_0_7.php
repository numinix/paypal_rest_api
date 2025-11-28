<?php
/**
 * Numinix PayPal ISU Installer - Version 1.0.7
 *
 * Adds auth_code and shared_id columns to the onboarding tracking table.
 * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
 * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
 * access token. Then, use this access token to get the seller's REST API credentials."
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * Security features:
 * - Records expire after 1 hour and are automatically cleaned up
 * - Records are deleted immediately after successful credential retrieval
 * - Auth codes are sensitive and should be cleared after exchange
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

// Add auth_code column if it doesn't exist
try {
    // Check if column exists
    $checkColumnSql = "SHOW COLUMNS FROM " . $tableName . " LIKE 'auth_code'";
    $result = $db->Execute($checkColumnSql);
    
    if ($result->EOF) {
        // Column doesn't exist, add it
        $alterSql = "ALTER TABLE " . $tableName . " ADD COLUMN auth_code VARCHAR(512) NULL AFTER merchant_id";
        $db->Execute($alterSql);
        
        if (isset($messageStack) && is_object($messageStack)) {
            $messageStack->add('Added auth_code column to PayPal onboarding tracking table.', 'success');
        }
    }
} catch (Throwable $e) {
    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Failed to add auth_code column: ' . $e->getMessage(), 'error');
    }
}

// Add shared_id column if it doesn't exist
try {
    // Check if column exists
    $checkColumnSql = "SHOW COLUMNS FROM " . $tableName . " LIKE 'shared_id'";
    $result = $db->Execute($checkColumnSql);
    
    if ($result->EOF) {
        // Column doesn't exist, add it
        $alterSql = "ALTER TABLE " . $tableName . " ADD COLUMN shared_id VARCHAR(128) NULL AFTER auth_code";
        $db->Execute($alterSql);
        
        if (isset($messageStack) && is_object($messageStack)) {
            $messageStack->add('Added shared_id column to PayPal onboarding tracking table.', 'success');
        }
    }
} catch (Throwable $e) {
    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Failed to add shared_id column: ' . $e->getMessage(), 'error');
    }
}

// Also make merchant_id nullable since we might have auth_code without merchant_id initially
try {
    $alterSql = "ALTER TABLE " . $tableName . " MODIFY COLUMN merchant_id VARCHAR(32) NULL";
    $db->Execute($alterSql);
} catch (Throwable $e) {
    // This might fail if the column is already nullable, which is fine
}

// Note: Version number updates are handled automatically by init_numinix_paypal_isu.php
// after each installer file runs.
