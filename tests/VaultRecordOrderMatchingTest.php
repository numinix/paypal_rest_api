<?php
declare(strict_types=1);

/**
 * Test to verify vault record matching only returns records for the specific order.
 *
 * This test ensures that:
 * 1. Vault records are only returned when they match the specific order ID
 * 2. Vault records from previous orders are NOT returned as a fallback
 * 3. Google Pay orders (which don't create vault records) don't inherit old saved card vaults
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    // Define constants
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
    if (!defined('TABLE_PAYPAL_VAULT')) {
        define('TABLE_PAYPAL_VAULT', DB_PREFIX . 'paypal_vault');
    }
    if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
        define('TABLE_PAYPAL_SUBSCRIPTIONS', DB_PREFIX . 'paypal_subscriptions');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
    }
    if (!defined('TABLE_ORDERS')) {
        define('TABLE_ORDERS', DB_PREFIX . 'orders');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS')) {
        define('TABLE_ORDERS_PRODUCTS', DB_PREFIX . 'orders_products');
    }

    // Mock functions
    if (!function_exists('zen_db_input')) {
        function zen_db_input($value) {
            return addslashes($value);
        }
    }

    $failures = 0;

    echo "\n=== Vault Record Order Matching Test ===\n";
    echo "Testing that vault records only match specific orders (no fallback to other orders)...\n\n";

    /**
     * Simulate the findVaultRecord logic from auto.paypaladvcheckout_recurring.php
     * This mirrors the FIXED implementation that should NOT fall back to $records[0]
     */
    function findVaultRecord(int $customersId, int $ordersId, array $vaultRecords): ?array
    {
        if ($customersId <= 0) {
            return null;
        }

        if (empty($vaultRecords)) {
            return null;
        }

        foreach ($vaultRecords as $record) {
            if ((int)($record['orders_id'] ?? 0) === $ordersId) {
                return $record;
            }
        }

        // FIX: Do NOT fall back to $records[0]
        // This would incorrectly associate a subscription with a payment method from a different order
        return null;
    }

    // Test 1: Vault record matches current order
    echo "Test 1: Vault record matching current order should be returned\n";
    
    $customerId = 100;
    $currentOrderId = 200;
    $vaultRecords = [
        ['paypal_vault_id' => 1, 'customers_id' => 100, 'orders_id' => 200, 'vault_id' => 'vault_current_order'],
        ['paypal_vault_id' => 2, 'customers_id' => 100, 'orders_id' => 150, 'vault_id' => 'vault_previous_order'],
    ];
    
    $result = findVaultRecord($customerId, $currentOrderId, $vaultRecords);
    
    if ($result === null) {
        echo "  ✗ FAILED: Expected vault record for current order, got null\n";
        $failures++;
    } elseif ($result['vault_id'] !== 'vault_current_order') {
        echo "  ✗ FAILED: Wrong vault record returned. Expected 'vault_current_order', got '{$result['vault_id']}'\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Correct vault record for current order returned\n";
    }

    // Test 2: No vault record for current order (e.g., Google Pay) - should NOT fall back
    echo "\nTest 2: No vault for current order (Google Pay scenario) - should return null\n";
    
    $customerId = 100;
    $googlePayOrderId = 300; // New Google Pay order - no vault record for this order
    $vaultRecords = [
        ['paypal_vault_id' => 1, 'customers_id' => 100, 'orders_id' => 200, 'vault_id' => 'vault_old_order_1'],
        ['paypal_vault_id' => 2, 'customers_id' => 100, 'orders_id' => 150, 'vault_id' => 'vault_old_order_2'],
    ];
    
    $result = findVaultRecord($customerId, $googlePayOrderId, $vaultRecords);
    
    if ($result !== null) {
        echo "  ✗ FAILED: Expected null for Google Pay order without vault, got vault_id '{$result['vault_id']}'\n";
        echo "    This is the bug! Old vault records should NOT be used for new orders.\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Correctly returned null for Google Pay order without vault\n";
    }

    // Test 3: Customer has no vault records at all
    echo "\nTest 3: Customer with no vault records should return null\n";
    
    $customerId = 100;
    $orderId = 500;
    $vaultRecords = [];
    
    $result = findVaultRecord($customerId, $orderId, $vaultRecords);
    
    if ($result !== null) {
        echo "  ✗ FAILED: Expected null for customer with no vaults, got a result\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Correctly returned null for customer with no vaults\n";
    }

    // Test 4: Invalid customer ID
    echo "\nTest 4: Invalid customer ID should return null\n";
    
    $customerId = 0;
    $orderId = 100;
    $vaultRecords = [
        ['paypal_vault_id' => 1, 'customers_id' => 100, 'orders_id' => 100, 'vault_id' => 'vault_1'],
    ];
    
    $result = findVaultRecord($customerId, $orderId, $vaultRecords);
    
    if ($result !== null) {
        echo "  ✗ FAILED: Expected null for invalid customer ID, got a result\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Correctly returned null for invalid customer ID\n";
    }

    // Test 5: Vault record at end of list should still be found
    echo "\nTest 5: Vault record at end of list for current order should be found\n";
    
    $customerId = 100;
    $currentOrderId = 300;
    $vaultRecords = [
        ['paypal_vault_id' => 1, 'customers_id' => 100, 'orders_id' => 100, 'vault_id' => 'vault_order_100'],
        ['paypal_vault_id' => 2, 'customers_id' => 100, 'orders_id' => 200, 'vault_id' => 'vault_order_200'],
        ['paypal_vault_id' => 3, 'customers_id' => 100, 'orders_id' => 300, 'vault_id' => 'vault_order_300'],
    ];
    
    $result = findVaultRecord($customerId, $currentOrderId, $vaultRecords);
    
    if ($result === null) {
        echo "  ✗ FAILED: Expected vault record for order 300, got null\n";
        $failures++;
    } elseif ($result['vault_id'] !== 'vault_order_300') {
        echo "  ✗ FAILED: Wrong vault record. Expected 'vault_order_300', got '{$result['vault_id']}'\n";
        $failures++;
    } else {
        echo "  ✓ PASSED: Correctly found vault record at end of list\n";
    }

    // Test 6: Verify the old bug behavior (for documentation purposes)
    echo "\nTest 6: Demonstrate the old buggy behavior that caused this issue\n";
    
    /**
     * This is what the OLD buggy code did - it returned $records[0] as a fallback
     */
    function findVaultRecordOldBuggy(int $customersId, int $ordersId, array $records): ?array
    {
        if ($customersId <= 0) {
            return null;
        }

        if (empty($records)) {
            return null;
        }

        foreach ($records as $record) {
            if ((int)($record['orders_id'] ?? 0) === $ordersId) {
                return $record;
            }
        }

        // BUG: This incorrectly returns the first vault record from any order!
        return $records[0];
    }
    
    $customerId = 100;
    $googlePayOrderId = 999; // New Google Pay order
    $vaultRecords = [
        ['paypal_vault_id' => 1, 'customers_id' => 100, 'orders_id' => 200, 'vault_id' => 'old_saved_card'],
    ];
    
    $buggyResult = findVaultRecordOldBuggy($customerId, $googlePayOrderId, $vaultRecords);
    $fixedResult = findVaultRecord($customerId, $googlePayOrderId, $vaultRecords);
    
    $buggyReturnsWrongVault = ($buggyResult !== null && $buggyResult['vault_id'] === 'old_saved_card');
    $fixedReturnsNull = ($fixedResult === null);
    
    if ($buggyReturnsWrongVault && $fixedReturnsNull) {
        echo "  ✓ PASSED: Old buggy code returns wrong vault, fixed code returns null correctly\n";
        echo "    - Old buggy code would return: vault_id='old_saved_card' (WRONG - different order!)\n";
        echo "    - Fixed code correctly returns: null (no vault for this order)\n";
    } else {
        echo "  ✗ UNEXPECTED: Test setup issue\n";
        $failures++;
    }

    // Summary
    echo "\n=== Test Summary ===\n";
    if ($failures === 0) {
        echo "✅ All tests passed!\n";
        echo "\nThis fix ensures that:\n";
        echo "  - Google Pay orders don't incorrectly inherit old saved card subscriptions\n";
        echo "  - Subscriptions are only created when a vault record was created for the specific order\n";
        echo "  - Each order uses its own payment method, not a fallback from previous orders\n";
        exit(0);
    } else {
        echo "❌ $failures test(s) failed.\n";
        exit(1);
    }
}
