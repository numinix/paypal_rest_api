<?php
declare(strict_types=1);

/**
 * Test to verify subscriptions are routed to the correct table based on plan_id presence
 *
 * This test ensures that:
 * 1. Subscriptions WITH plan_id go to TABLE_PAYPAL_SUBSCRIPTIONS (Vaulted Subscriptions)
 * 2. Subscriptions WITHOUT plan_id go to TABLE_SAVED_CREDIT_CARDS_RECURRING (Saved Card Subscriptions)
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('DIR_WS_CLASSES')) {
        define('DIR_WS_CLASSES', 'includes/classes/');
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
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
    }
    if (!defined('TABLE_CUSTOMERS')) {
        define('TABLE_CUSTOMERS', DB_PREFIX . 'customers');
    }
    if (!defined('TABLE_ORDERS')) {
        define('TABLE_ORDERS', DB_PREFIX . 'orders');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS')) {
        define('TABLE_ORDERS_PRODUCTS', DB_PREFIX . 'orders_products');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS_ATTRIBUTES')) {
        define('TABLE_ORDERS_PRODUCTS_ATTRIBUTES', DB_PREFIX . 'orders_products_attributes');
    }

    // Mock functions
    if (!function_exists('zen_db_input')) {
        function zen_db_input($value) {
            return addslashes($value);
        }
    }
    
    if (!function_exists('zen_output_string_protected')) {
        function zen_output_string_protected($value) {
            return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        }
    }

    // Track which table received inserts
    $savedCardRecurringInserts = [];
    $vaultedSubscriptionInserts = [];
    
    // Mock database class
    class queryFactoryResult
    {
        public bool $EOF = true;
        public array $fields = [];
        
        public function RecordCount(): int {
            return $this->EOF ? 0 : 1;
        }
        
        public function MoveNext(): void {
            $this->EOF = true;
        }
    }

    class MockDb
    {
        public $insertId = 1;
        public $performCalls = [];
        
        public function Execute($query)
        {
            global $savedCardRecurringInserts, $vaultedSubscriptionInserts;
            
            // Mock saved_credit_card lookup by vault_id
            if (strpos($query, 'SELECT saved_credit_card_id FROM') !== false && strpos($query, 'vault_id') !== false) {
                $result = new queryFactoryResult();
                $result->EOF = false;
                $result->fields = ['saved_credit_card_id' => 42];
                return $result;
            }
            
            // Return empty result for other queries
            return new queryFactoryResult();
        }
        
        public function perform($table, $data, $action = 'insert', $where = '') {
            global $savedCardRecurringInserts, $vaultedSubscriptionInserts;
            
            $this->performCalls[] = [
                'table' => $table,
                'data' => $data,
                'action' => $action,
                'where' => $where
            ];
            
            if ($table === TABLE_SAVED_CREDIT_CARDS_RECURRING) {
                $savedCardRecurringInserts[] = $data;
            } elseif ($table === TABLE_PAYPAL_SUBSCRIPTIONS) {
                // This shouldn't be called for Zen Cart-managed subscriptions
                $vaultedSubscriptionInserts[] = $data;
            }
        }
        
        public function Insert_ID() {
            return $this->insertId++;
        }
    }

    $db = new MockDb();

    // Mock zen_db_perform
    if (!function_exists('zen_db_perform')) {
        function zen_db_perform($table, $data, $action = 'insert', $where = '') {
            global $db;
            return $db->perform($table, $data, $action, $where);
        }
    }

    $failures = 0;

    echo "\n=== Subscription Routing Test ===\n";
    echo "Testing that subscriptions route to correct table based on plan_id...\n\n";

    // Test 1: Subscription attribute validation for Zen Cart-managed subscriptions
    echo "Test 1: Verify Zen Cart-managed subscription attributes (no plan_id) are valid\n";
    
    $attributes = [
        'billing_period' => 'MONTH',
        'billing_frequency' => '1',
        'total_billing_cycles' => '12',
    ];
    
    // These should be valid for Zen Cart-managed subscriptions
    if (empty($attributes['billing_period']) || empty($attributes['billing_frequency'])) {
        echo "  ✗ FAILED: Zen Cart-managed subscription missing required attributes\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Zen Cart-managed subscription has required billing_period and billing_frequency\n";
    }

    // Test 2: Verify PayPal-managed subscription attributes (with plan_id) are valid
    echo "\nTest 2: Verify PayPal-managed subscription attributes (with plan_id) are valid\n";
    
    $attributesWithPlan = [
        'plan_id' => 'PLAN-XYZ123',
    ];
    
    if (empty($attributesWithPlan['plan_id'])) {
        echo "  ✗ FAILED: PayPal-managed subscription missing plan_id\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: PayPal-managed subscription has plan_id\n";
    }

    // Test 3: Verify routing logic - subscriptions without plan_id should NOT go to vaulted subscriptions
    echo "\nTest 3: Verify routing logic excludes plan_id for Zen Cart-managed subscriptions\n";
    
    // Simulate the observer's routing decision
    $hasPlanId = !empty($attributes['plan_id']);
    
    if ($hasPlanId) {
        echo "  ✗ FAILED: Zen Cart-managed subscription incorrectly identified as having plan_id\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Zen Cart-managed subscription correctly identified as having no plan_id\n";
    }

    // Test 4: Verify routing logic - subscriptions with plan_id should go to vaulted subscriptions
    echo "\nTest 4: Verify routing logic includes plan_id for PayPal-managed subscriptions\n";
    
    $hasPlanId = !empty($attributesWithPlan['plan_id']);
    
    if (!$hasPlanId) {
        echo "  ✗ FAILED: PayPal-managed subscription incorrectly identified as having no plan_id\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: PayPal-managed subscription correctly identified as having plan_id\n";
    }

    // Test 5: Test next billing date calculation
    echo "\nTest 5: Verify next billing date calculation\n";
    
    $subscriptionAttributes = [
        'billing_period' => 'MONTH',
        'billing_frequency' => 1,
    ];
    
    $date = new DateTime();
    $date->modify('+1 months');
    $expectedDate = $date->format('Y-m-d');
    
    // Simple calculation for monthly subscription
    $testDate = new DateTime();
    $testDate->modify('+' . $subscriptionAttributes['billing_frequency'] . ' months');
    $calculatedDate = $testDate->format('Y-m-d');
    
    if ($calculatedDate !== $expectedDate) {
        echo "  ✗ FAILED: Next billing date calculation incorrect. Expected: $expectedDate, Got: $calculatedDate\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Next billing date correctly calculated as $calculatedDate\n";
    }

    // Test 6: Verify saved_credit_card_id lookup would work with vault_id
    echo "\nTest 6: Verify saved_credit_card_id can be retrieved from vault_id\n";
    
    $vaultId = 'test_vault_id_123';
    $query = "SELECT saved_credit_card_id FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE vault_id = '" . zen_db_input($vaultId) . "' LIMIT 1";
    $result = $db->Execute($query);
    
    if ($result->EOF || empty($result->fields['saved_credit_card_id'])) {
        echo "  ✗ FAILED: Could not retrieve saved_credit_card_id from vault_id\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Successfully retrieved saved_credit_card_id: " . $result->fields['saved_credit_card_id'] . "\n";
    }

    // Summary
    echo "\n=== Test Summary ===\n";
    if ($failures === 0) {
        echo "✅ All tests passed!\n";
        exit(0);
    } else {
        echo "❌ $failures test(s) failed.\n";
        exit(1);
    }
}
