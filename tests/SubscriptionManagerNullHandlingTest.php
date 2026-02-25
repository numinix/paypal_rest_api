<?php
declare(strict_types=1);

/**
 * Test to verify that SubscriptionManager::logSubscription handles orders_products_id = 0 correctly
 *
 * This test ensures that the logSubscription method also handles the case where
 * orders_products_id is 0, setting it to NULL to avoid duplicate key errors.
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
    if (!defined('TABLE_PAYPAL_VAULT')) {
        define('TABLE_PAYPAL_VAULT', DB_PREFIX . 'paypal_vault');
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
        
        public function RecordCount(): int {
            return $this->EOF ? 0 : 1;
        }
    }

    class MockDb
    {
        public $insertId = 1;
        
        public function Execute($query)
        {
            // Return empty result for all queries
            return new queryFactoryResult();
        }
        
        public function Insert_ID() {
            return $this->insertId++;
        }
    }

    $db = new MockDb();

    // Load the SubscriptionManager and VaultManager
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/VaultManager.php';

    $failures = 0;

    // Test 1: logSubscription with orders_products_id = 0
    $testData = [
        'customers_id' => 130825,
        'orders_id' => 26151,
        'orders_products_id' => 0,  // This should be converted to NULL
        'products_id' => 180,
        'products_name' => 'Test Product',
        'products_quantity' => 1,
        'plan_id' => 'PLAN-123',
        'billing_period' => 'MONTH',
        'billing_frequency' => 1,
        'total_billing_cycles' => 12,
        'amount' => 50.00,
        'currency_code' => 'USD',
        'currency_value' => 1.0,
        'status' => 'active',
    ];

    $insertedRecords = [];
    
    $subscriptionId = \PayPalAdvancedCheckout\Common\SubscriptionManager::logSubscription($testData);
    
    if (count($insertedRecords) !== 1) {
        fwrite(STDERR, "✗ Expected one insert, got " . count($insertedRecords) . "\n");
        $failures++;
    } else {
        $insertedData = $insertedRecords[0];
        
        // Verify orders_products_id was set to NULL (not unset) when value is 0
        if (!array_key_exists('orders_products_id', $insertedData)) {
            fwrite(STDERR, "✗ orders_products_id should be present in insert data\n");
            $failures++;
        } else if ($insertedData['orders_products_id'] !== null) {
            fwrite(STDERR, "✗ orders_products_id should be NULL when value is 0, got " . var_export($insertedData['orders_products_id'], true) . "\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ logSubscription sets orders_products_id to NULL when value is 0\n");
        }
        
        // Verify other fields are still present
        $requiredFields = ['customers_id', 'orders_id', 'products_id', 'products_name'];
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

    // Test 2: logSubscription with orders_products_id > 0
    $testData2 = $testData;
    $testData2['orders_products_id'] = 456;
    
    $insertedRecords = [];
    
    $subscriptionId2 = \PayPalAdvancedCheckout\Common\SubscriptionManager::logSubscription($testData2);
    
    if (count($insertedRecords) !== 1) {
        fwrite(STDERR, "✗ Expected one insert for second test\n");
        $failures++;
    } else {
        $insertedData2 = $insertedRecords[0];
        
        if (!array_key_exists('orders_products_id', $insertedData2)) {
            fwrite(STDERR, "✗ orders_products_id should be preserved when value > 0\n");
            $failures++;
        } else if ($insertedData2['orders_products_id'] !== 456) {
            fwrite(STDERR, "✗ orders_products_id should be 456, got " . $insertedData2['orders_products_id'] . "\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ logSubscription preserves orders_products_id when value > 0\n");
        }
    }

    // Test 3: Verify multiple inserts with orders_products_id = 0 would not cause conflicts
    // (simulating what would happen if the database allowed it)
    $insertedRecords = [];
    
    $subscriptionId3 = \PayPalAdvancedCheckout\Common\SubscriptionManager::logSubscription($testData);
    $subscriptionId4 = \PayPalAdvancedCheckout\Common\SubscriptionManager::logSubscription($testData);
    
    if (count($insertedRecords) !== 2) {
        fwrite(STDERR, "✗ Expected two inserts for duplicate test\n");
        $failures++;
    } else {
        $bothNull = (
            $insertedRecords[0]['orders_products_id'] === null &&
            $insertedRecords[1]['orders_products_id'] === null
        );
        
        if (!$bothNull) {
            fwrite(STDERR, "✗ Both inserts should have NULL orders_products_id\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Multiple subscriptions with orders_products_id = 0 both get NULL (no unique constraint conflict)\n");
        }
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n✗ Total failures: $failures\n");
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All SubscriptionManager NULL handling tests passed\n");
    exit(0);
}
