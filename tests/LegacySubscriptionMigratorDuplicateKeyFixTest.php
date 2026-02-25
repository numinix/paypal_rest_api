<?php
declare(strict_types=1);

/**
 * Test to verify that LegacySubscriptionMigrator handles orders_products_id = 0 correctly
 *
 * This test addresses the issue:
 * "MySQL error 1062: Duplicate entry '0' for key 'idx_orders_product'"
 * which occurred when migrating legacy subscriptions with orders_products_id = 0
 *
 * The fix unsets orders_products_id when it's 0 before insertion, allowing NULL values
 * which don't violate the UNIQUE constraint.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
        define('TABLE_PAYPAL_SUBSCRIPTIONS', DB_PREFIX . 'paypal_subscriptions');
    }
    if (!defined('TABLE_PAYPAL_RECURRING')) {
        define('TABLE_PAYPAL_RECURRING', DB_PREFIX . 'paypal_recurring');
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input($value) {
            return addslashes($value);
        }
    }

    // Mock zen_db_perform function
    $insertedRecords = [];
    $updatedRecords = [];
    
    if (!function_exists('zen_db_perform')) {
        function zen_db_perform($table, $data, $action = 'insert', $where = '') {
            global $insertedRecords, $updatedRecords;
            
            if ($action === 'insert') {
                $insertedRecords[] = $data;
            } else if ($action === 'update') {
                $updatedRecords[] = ['data' => $data, 'where' => $where];
            }
        }
    }

    // Mock database class
    class queryFactoryResult
    {
        public bool $EOF = true;
        public array $fields = [];
        
        public function MoveNext(): void
        {
            $this->EOF = true;
        }
    }

    class MockDb
    {
        private $existingRecords = [];
        public $insertId = 1;
        
        public function setExistingRecord($conditions, $record) {
            $this->existingRecords[serialize($conditions)] = $record;
        }
        
        public function Execute($query)
        {
            // Check if this is a SELECT query looking for existing records
            if (stripos($query, 'SELECT') === 0) {
                // Check each condition we've set
                foreach ($this->existingRecords as $key => $record) {
                    $conditions = unserialize($key);
                    foreach ($conditions as $field => $value) {
                        if (stripos($query, "$field") !== false && stripos($query, (string)$value) !== false) {
                            $result = new queryFactoryResult();
                            $result->EOF = false;
                            $result->fields = $record;
                            return $result;
                        }
                    }
                }
            } elseif (stripos($query, 'SHOW TABLES') === 0) {
                // Return that table exists
                $result = new queryFactoryResult();
                $result->EOF = false;
                return $result;
            }
            
            // Return empty result
            return new queryFactoryResult();
        }
        
        public function Insert_ID() {
            return $this->insertId++;
        }
    }

    $db = new MockDb();

    // Load the LegacySubscriptionMigrator
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/VaultManager.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/LegacySubscriptionMigrator.php';

    $failures = 0;

    // Test normalizeLegacyRow with orders_products_id = 0
    $testRow = [
        'subscription_id' => 3,
        'customers_id' => 130825,
        'orders_id' => 26151,
        'orders_products_id' => 0,  // This is the problematic value
        'products_id' => 180,
        'products_name' => 'Test Product',
        'amount' => 50.00,
        'currencycode' => 'USD',
        'currency_value' => 1,
        'status' => 'active',
        'profile_id' => 'I-TEST123',
        'billingperiod' => 'Month',
        'billingfrequency' => 1,
        'totalbillingcycles' => 12,
    ];

    // Use reflection to access private method
    $reflectionClass = new ReflectionClass('PayPalAdvancedCheckout\\Common\\LegacySubscriptionMigrator');
    $normalizeMethod = $reflectionClass->getMethod('normalizeLegacyRow');
    $normalizeMethod->setAccessible(true);
    
    $normalized = $normalizeMethod->invoke(null, $testRow);
    
    // Test 1: Verify normalized row has orders_products_id = 0
    if (!isset($normalized['orders_products_id']) || $normalized['orders_products_id'] !== 0) {
        fwrite(STDERR, "✗ normalizeLegacyRow should preserve orders_products_id = 0\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ normalizeLegacyRow preserves orders_products_id = 0\n");
    }

    // Test 2: Call upsertSubscription and verify orders_products_id is unset before insert
    $insertedRecords = [];
    $upsertMethod = $reflectionClass->getMethod('upsertSubscription');
    $upsertMethod->setAccessible(true);
    
    $upsertMethod->invoke(null, $normalized);
    
    if (count($insertedRecords) !== 1) {
        fwrite(STDERR, "✗ Expected one insert, got " . count($insertedRecords) . "\n");
        $failures++;
    } else {
        $insertedData = $insertedRecords[0];
        
        // Test 3: Verify orders_products_id was set to NULL (not unset) when value is 0
        if (!array_key_exists('orders_products_id', $insertedData)) {
            fwrite(STDERR, "✗ orders_products_id should be present in insert data\n");
            $failures++;
        } else if ($insertedData['orders_products_id'] !== null) {
            fwrite(STDERR, "✗ orders_products_id should be NULL when value is 0, got " . var_export($insertedData['orders_products_id'], true) . "\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ orders_products_id is set to NULL when value is 0 (prevents duplicate key error)\n");
        }
        
        // Test 4: Verify other fields are still present
        $requiredFields = ['legacy_subscription_id', 'customers_id', 'orders_id', 'products_id'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $insertedData)) {
                fwrite(STDERR, "✗ Required field $field is missing from insert data\n");
                $failures++;
            }
        }
        if ($failures === 0) {
            fwrite(STDOUT, "✓ Other required fields are preserved in insert data\n");
        }
    }

    // Test 5: Test with orders_products_id > 0 (should be preserved)
    $testRow2 = $testRow;
    $testRow2['orders_products_id'] = 123;
    $testRow2['subscription_id'] = 4;
    
    $normalized2 = $normalizeMethod->invoke(null, $testRow2);
    $insertedRecords = [];
    $upsertMethod->invoke(null, $normalized2);
    
    if (count($insertedRecords) !== 1) {
        fwrite(STDERR, "✗ Expected one insert for second test\n");
        $failures++;
    } else {
        $insertedData2 = $insertedRecords[0];
        
        if (!array_key_exists('orders_products_id', $insertedData2)) {
            fwrite(STDERR, "✗ orders_products_id should be preserved when value > 0\n");
            $failures++;
        } else if ($insertedData2['orders_products_id'] !== 123) {
            fwrite(STDERR, "✗ orders_products_id should be 123, got " . $insertedData2['orders_products_id'] . "\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ orders_products_id is preserved when value > 0\n");
        }
    }

    // Test 6: Test update scenario with existing record
    $db->setExistingRecord(['legacy_subscription_id' => 3], ['paypal_subscription_id' => 100]);
    $updatedRecords = [];
    $insertedRecords = [];
    
    $upsertMethod->invoke(null, $normalized);
    
    if (count($updatedRecords) !== 1) {
        fwrite(STDERR, "✗ Expected one update when record exists, got " . count($updatedRecords) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Existing records are updated, not inserted\n");
    }

    if (count($insertedRecords) !== 0) {
        fwrite(STDERR, "✗ Expected no insert when record exists, got " . count($insertedRecords) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ No duplicate insert when updating existing record\n");
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All LegacySubscriptionMigrator duplicate key fix tests passed\n");
    exit(0);
}
