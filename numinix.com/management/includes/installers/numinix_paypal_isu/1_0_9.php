<?php
/**
 * Numinix PayPal ISU Installer - Version 1.0.9
 *
 * Ensures the seller credential columns exist in the onboarding tracking table.
 * These columns are needed to persist the seller's REST API credentials after
 * the authCode/sharedId token exchange flow completes.
 *
 * This installer re-applies the column additions from 1.0.7 for users who may
 * have upgraded directly to 1.0.8 without running the 1.0.7 installer.
 *
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
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

// List of columns to ensure exist
$columnsToAdd = [
    [
        'name' => 'auth_code',
        'definition' => 'VARCHAR(512) NULL',
        'after' => 'merchant_id',
    ],
    [
        'name' => 'shared_id',
        'definition' => 'VARCHAR(128) NULL',
        'after' => 'auth_code',
    ],
    [
        'name' => 'seller_access_token',
        'definition' => 'TEXT NULL',
        'after' => 'shared_id',
    ],
    [
        'name' => 'seller_access_token_expires_at',
        'definition' => 'DATETIME NULL',
        'after' => 'seller_access_token',
    ],
    [
        'name' => 'seller_client_id',
        'definition' => 'VARCHAR(255) NULL',
        'after' => 'seller_access_token_expires_at',
    ],
    [
        'name' => 'seller_client_secret',
        'definition' => 'TEXT NULL',
        'after' => 'seller_client_id',
    ],
];

foreach ($columnsToAdd as $column) {
    try {
        // Escape column name for safe SQL usage
        $escapedName = '`' . str_replace('`', '``', $column['name']) . '`';
        $escapedAfter = '`' . str_replace('`', '``', $column['after']) . '`';
        
        $checkColumnSql = "SHOW COLUMNS FROM " . $tableName . " LIKE '" . $column['name'] . "'";
        $result = $db->Execute($checkColumnSql);

        if ($result->EOF) {
            // Column doesn't exist, add it
            $alterSql = "ALTER TABLE " . $tableName . " ADD COLUMN " . $escapedName . " " . $column['definition'];
            
            // Only add AFTER clause if the reference column exists
            $checkAfterCol = $db->Execute("SHOW COLUMNS FROM " . $tableName . " LIKE '" . $column['after'] . "'");
            if (!$checkAfterCol->EOF) {
                $alterSql .= " AFTER " . $escapedAfter;
            }
            
            $db->Execute($alterSql);

            if (isset($messageStack) && is_object($messageStack)) {
                $messageStack->add('Added ' . $column['name'] . ' column to PayPal onboarding tracking table.', 'success');
            }
        }
    } catch (Throwable $e) {
        if (isset($messageStack) && is_object($messageStack)) {
            $messageStack->add('Failed to add ' . $column['name'] . ' column: ' . $e->getMessage(), 'error');
        }
    }
}

// Ensure merchant_id is nullable since we might have auth_code without merchant_id initially
try {
    $alterSql = "ALTER TABLE " . $tableName . " MODIFY COLUMN merchant_id VARCHAR(32) NULL";
    $db->Execute($alterSql);
} catch (Throwable $e) {
    // This might fail if the column is already nullable, which is fine
}

// Note: Version number updates are handled automatically by init_numinix_paypal_isu.php
// after each installer file runs.
