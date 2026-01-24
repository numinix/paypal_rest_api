<?php
declare(strict_types=1);

/**
 * Test to verify that SavedCreditCardsManager creates the required legacy tables
 *
 * This test addresses the issue:
 * "MySQL error 1146: Table 'saved_credit_cards_recurring' doesn't exist"
 * which occurred when accessing admin/paypalr_saved_card_recurring.php
 *
 * The fix creates legacy table schemas for backward compatibility with older
 * admin pages and payment modules.
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
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input($value) {
            return addslashes($value);
        }
    }

    // Mock database class
    class queryFactoryResult
    {
        public bool $EOF = true;
        public array $fields = [];
        public int $RecordCount = 0;
        
        public function MoveNext(): void
        {
            $this->EOF = true;
        }
    }

    class MockDb
    {
        private array $createdTables = [];
        
        public function Execute($query)
        {
            // Track CREATE TABLE statements
            if (stripos($query, 'CREATE TABLE') !== false) {
                if (stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                    $this->createdTables['saved_credit_cards_recurring'] = true;
                } elseif (stripos($query, TABLE_SAVED_CREDIT_CARDS) !== false) {
                    $this->createdTables['saved_credit_cards'] = true;
                }
            }
            
            // Mock SHOW TABLES response
            if (stripos($query, 'SHOW TABLES') !== false) {
                $result = new queryFactoryResult();
                // Simulate table doesn't exist initially
                $result->EOF = true;
                return $result;
            }
            
            return new queryFactoryResult();
        }
        
        public function hasCreatedTable(string $tableName): bool
        {
            return isset($this->createdTables[$tableName]);
        }
    }

    $db = new MockDb();

    // Load the SavedCreditCardsManager
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/SavedCreditCardsManager.php';

    $failures = 0;

    // Test 1: Verify SavedCreditCardsManager class exists
    if (!class_exists('PayPalRestful\\Common\\SavedCreditCardsManager')) {
        fwrite(STDERR, "✗ SavedCreditCardsManager class not found\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ SavedCreditCardsManager class loaded successfully\n");
    }

    // Test 2: Call ensureSchema() and verify it creates tables
    try {
        \PayPalRestful\Common\SavedCreditCardsManager::ensureSchema();
        fwrite(STDOUT, "✓ SavedCreditCardsManager::ensureSchema() executed without errors\n");
    } catch (\Exception $e) {
        fwrite(STDERR, "✗ ensureSchema() threw exception: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 3: Verify saved_credit_cards table was created
    if (!$db->hasCreatedTable('saved_credit_cards')) {
        fwrite(STDERR, "✗ saved_credit_cards table was not created\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ saved_credit_cards table was created\n");
    }

    // Test 4: Verify saved_credit_cards_recurring table was created
    if (!$db->hasCreatedTable('saved_credit_cards_recurring')) {
        fwrite(STDERR, "✗ saved_credit_cards_recurring table was not created\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ saved_credit_cards_recurring table was created\n");
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All SavedCreditCardsManager tests passed\n");
    exit(0);
}
