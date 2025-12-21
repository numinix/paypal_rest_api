<?php
/**
 * Numinix PayPal ISU Installer - Version 1.0.10
 *
 * Adds seller_nonce column to the onboarding tracking table.
 * The seller_nonce (code_verifier in PKCE) is required for exchanging authCode/sharedId
 * for the seller's REST API credentials.
 *
 * Per PayPal ISU documentation, the seller_nonce generated during partner referral creation
 * must be passed as code_verifier when exchanging the authorization code for tokens.
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * Security features:
 * - Records expire after 1 hour and are automatically cleaned up
 * - Records are deleted immediately after successful credential retrieval
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

// Add seller_nonce column if it doesn't exist
try {
    // Check if column exists
    $checkColumnSql = "SHOW COLUMNS FROM " . $tableName . " LIKE 'seller_nonce'";
    $result = $db->Execute($checkColumnSql);
    
    if ($result->EOF) {
        // Column doesn't exist, add it after environment column
        // Check if environment column exists to determine position
        $afterCol = 'environment';
        // Escape column name for safe SQL usage
        $escapedAfterCol = '`' . str_replace('`', '``', $afterCol) . '`';
        $checkAfterSql = "SHOW COLUMNS FROM " . $tableName . " LIKE '" . $afterCol . "'";
        $afterResult = $db->Execute($checkAfterSql);
        
        $alterSql = "ALTER TABLE " . $tableName . " ADD COLUMN `seller_nonce` VARCHAR(128) NULL";
        if (!$afterResult->EOF) {
            $alterSql .= " AFTER " . $escapedAfterCol;
        }
        
        $db->Execute($alterSql);
        
        if (isset($messageStack) && is_object($messageStack)) {
            $messageStack->add('Added seller_nonce column to PayPal onboarding tracking table.', 'success');
        }
    }
} catch (Throwable $e) {
    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Failed to add seller_nonce column: ' . $e->getMessage(), 'error');
    }
}

// Note: Version number updates are handled automatically by init_numinix_paypal_isu.php
// after each installer file runs.
