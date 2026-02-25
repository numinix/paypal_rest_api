<?php
declare(strict_types=1);

/**
 * Test to verify that SavedCreditCardsManager creates billing address and shipping columns
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'billing_name' in 'field list'"
 * which occurred when creating subscriptions with billing address information.
 *
 * The fix adds billing address and shipping columns to saved_credit_cards_recurring
 * table via ensureLegacyColumns() for subscription independence.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
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
        private int $recordCount = 0;
        
        public function MoveNext(): void
        {
            $this->EOF = true;
        }
        
        public function RecordCount(): int
        {
            return $this->recordCount;
        }
        
        public function setRecordCount(int $count): void
        {
            $this->recordCount = $count;
        }
    }

    class MockDb
    {
        private array $createdTables = [];
        private array $addedColumns = [];
        
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
            
            // Track ALTER TABLE ADD statements for billing address and shipping columns
            if (stripos($query, 'ALTER TABLE') !== false && stripos($query, ' ADD ') !== false) {
                if (stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                    // Billing address columns
                    if (stripos($query, 'billing_name') !== false) {
                        $this->addedColumns['billing_name'] = true;
                    }
                    if (stripos($query, 'billing_company') !== false) {
                        $this->addedColumns['billing_company'] = true;
                    }
                    if (stripos($query, 'billing_street_address') !== false) {
                        $this->addedColumns['billing_street_address'] = true;
                    }
                    if (stripos($query, 'billing_suburb') !== false) {
                        $this->addedColumns['billing_suburb'] = true;
                    }
                    if (stripos($query, 'billing_city') !== false) {
                        $this->addedColumns['billing_city'] = true;
                    }
                    if (stripos($query, 'billing_state') !== false) {
                        $this->addedColumns['billing_state'] = true;
                    }
                    if (stripos($query, 'billing_postcode') !== false) {
                        $this->addedColumns['billing_postcode'] = true;
                    }
                    if (stripos($query, 'billing_country_id') !== false) {
                        $this->addedColumns['billing_country_id'] = true;
                    }
                    if (stripos($query, 'billing_country_code') !== false) {
                        $this->addedColumns['billing_country_code'] = true;
                    }
                    
                    // Shipping columns
                    if (stripos($query, 'shipping_method') !== false) {
                        $this->addedColumns['shipping_method'] = true;
                    }
                    if (stripos($query, 'shipping_cost') !== false) {
                        $this->addedColumns['shipping_cost'] = true;
                    }
                }
            }
            
            // Mock SHOW TABLES response
            if (stripos($query, 'SHOW TABLES') !== false) {
                $result = new queryFactoryResult();
                $result->EOF = true;
                return $result;
            }
            
            // Mock SHOW COLUMNS response - columns don't exist initially
            if (stripos($query, 'SHOW COLUMNS') !== false) {
                $result = new queryFactoryResult();
                $result->EOF = true;
                $result->setRecordCount(0);
                return $result;
            }
            
            return new queryFactoryResult();
        }
        
        public function hasCreatedTable(string $tableName): bool
        {
            return isset($this->createdTables[$tableName]);
        }
        
        public function hasAddedColumn(string $columnName): bool
        {
            return isset($this->addedColumns[$columnName]);
        }
    }

    $db = new MockDb();

    // Load the SavedCreditCardsManager
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SavedCreditCardsManager.php';

    $failures = 0;

    // Test 1: Verify SavedCreditCardsManager class exists
    if (!class_exists('PayPalAdvancedCheckout\\Common\\SavedCreditCardsManager')) {
        fwrite(STDERR, "✗ SavedCreditCardsManager class not found\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ SavedCreditCardsManager class loaded successfully\n");
    }

    // Test 2: Call ensureSchema() and verify it creates tables
    try {
        \PayPalAdvancedCheckout\Common\SavedCreditCardsManager::ensureSchema();
        fwrite(STDOUT, "✓ SavedCreditCardsManager::ensureSchema() executed without errors\n");
    } catch (\Exception $e) {
        fwrite(STDERR, "✗ ensureSchema() threw exception: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 3: Verify billing_name column was added
    if (!$db->hasAddedColumn('billing_name')) {
        fwrite(STDERR, "✗ billing_name column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_name column was added via ALTER TABLE\n");
    }

    // Test 4: Verify billing_company column was added
    if (!$db->hasAddedColumn('billing_company')) {
        fwrite(STDERR, "✗ billing_company column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_company column was added via ALTER TABLE\n");
    }

    // Test 5: Verify billing_street_address column was added
    if (!$db->hasAddedColumn('billing_street_address')) {
        fwrite(STDERR, "✗ billing_street_address column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_street_address column was added via ALTER TABLE\n");
    }

    // Test 6: Verify billing_suburb column was added
    if (!$db->hasAddedColumn('billing_suburb')) {
        fwrite(STDERR, "✗ billing_suburb column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_suburb column was added via ALTER TABLE\n");
    }

    // Test 7: Verify billing_city column was added
    if (!$db->hasAddedColumn('billing_city')) {
        fwrite(STDERR, "✗ billing_city column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_city column was added via ALTER TABLE\n");
    }

    // Test 8: Verify billing_state column was added
    if (!$db->hasAddedColumn('billing_state')) {
        fwrite(STDERR, "✗ billing_state column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_state column was added via ALTER TABLE\n");
    }

    // Test 9: Verify billing_postcode column was added
    if (!$db->hasAddedColumn('billing_postcode')) {
        fwrite(STDERR, "✗ billing_postcode column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_postcode column was added via ALTER TABLE\n");
    }

    // Test 10: Verify billing_country_id column was added
    if (!$db->hasAddedColumn('billing_country_id')) {
        fwrite(STDERR, "✗ billing_country_id column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_country_id column was added via ALTER TABLE\n");
    }

    // Test 11: Verify billing_country_code column was added
    if (!$db->hasAddedColumn('billing_country_code')) {
        fwrite(STDERR, "✗ billing_country_code column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ billing_country_code column was added via ALTER TABLE\n");
    }

    // Test 12: Verify shipping_method column was added
    if (!$db->hasAddedColumn('shipping_method')) {
        fwrite(STDERR, "✗ shipping_method column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ shipping_method column was added via ALTER TABLE\n");
    }

    // Test 13: Verify shipping_cost column was added
    if (!$db->hasAddedColumn('shipping_cost')) {
        fwrite(STDERR, "✗ shipping_cost column was not added via ALTER TABLE\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ shipping_cost column was added via ALTER TABLE\n");
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All billing address and shipping column tests passed\n");
    exit(0);
}
