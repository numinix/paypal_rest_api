<?php
declare(strict_types=1);

/**
 * Test to verify that VaultManager handles duplicate key scenarios
 * gracefully using INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * This test ensures that when multiple processes attempt to save the same
 * vault_id simultaneously, the database handles it atomically without
 * triggering duplicate key errors.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }
    if (!defined('TABLE_PAYPAL_VAULT')) {
        define('TABLE_PAYPAL_VAULT', DB_PREFIX . 'paypal_vault');
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/VaultManager.php';

    class MockDbRecord
    {
        public bool $EOF = false;
        public array $fields = [];
        private int $position = 0;
        private array $allRecords = [];

        public function __construct(array $records)
        {
            $this->allRecords = $records;
            $this->position = 0;
            $this->updateCurrent();
        }

        private function updateCurrent(): void
        {
            if ($this->position >= count($this->allRecords)) {
                $this->EOF = true;
                $this->fields = [];
            } else {
                $this->EOF = false;
                $this->fields = $this->allRecords[$this->position];
            }
        }

        public function MoveNext(): void
        {
            $this->position++;
            $this->updateCurrent();
        }
    }

    /**
     * Mock database that handles INSERT ... ON DUPLICATE KEY UPDATE
     */
    class MockDbDuplicateKey
    {
        public array $executeCalls = [];
        public int $insertCallCount = 0;

        public function Execute(string $sql)
        {
            $this->executeCalls[] = $sql;
            
            // Handle CREATE TABLE and SHOW COLUMNS
            if (stripos($sql, 'CREATE TABLE') !== false) {
                return new MockDbRecord([]);
            }
            if (stripos($sql, 'SHOW COLUMNS') !== false) {
                return new MockDbRecord([['Field' => 'visible']]);
            }

            // Handle INSERT ... ON DUPLICATE KEY UPDATE
            if (stripos($sql, 'INSERT INTO') !== false) {
                $this->insertCallCount++;
                // The INSERT ... ON DUPLICATE KEY UPDATE always succeeds
                return new MockDbRecord([]);
            }

            // Handle SELECT * for final fetch
            if (stripos($sql, 'SELECT *') !== false) {
                return new MockDbRecord([
                    [
                        'paypal_vault_id' => 42,
                        'customers_id' => 1,
                        'orders_id' => 100,
                        'vault_id' => 'TEST-VAULT-ID',
                        'status' => 'ACTIVE',
                        'brand' => 'VISA',
                        'last_digits' => '1234',
                        'card_type' => 'CREDIT',
                        'expiry' => '2026-12',
                        'payer_id' => 'PAYER123',
                        'paypal_customer_id' => 'CUST123',
                        'cardholder_name' => 'John Doe',
                        'billing_address' => null,
                        'card_data' => null,
                        'create_time' => '2025-01-15 10:30:00',
                        'update_time' => '2025-01-15 10:30:00',
                        'date_added' => '2025-01-15 10:30:00',
                        'last_modified' => '2025-01-15 10:30:00',
                        'last_used' => '2025-01-15 10:30:00',
                        'visible' => 1,
                    ],
                ]);
            }

            return new MockDbRecord([]);
        }
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input($value): string
        {
            return addslashes((string)$value);
        }
    }

    // Initialize mock database
    $GLOBALS['db'] = new MockDbDuplicateKey();

    use PayPalAdvancedCheckout\Common\VaultManager;

    $failures = 0;

    // Test 1: Verify that duplicate key scenario is handled gracefully using INSERT ... ON DUPLICATE KEY UPDATE
    echo "Test 1: Handle duplicate key using INSERT ... ON DUPLICATE KEY UPDATE...\n";

    $cardSource = [
        'vault' => [
            'id' => 'TEST-VAULT-ID',
            'status' => 'ACTIVE',
            'customer' => [
                'id' => 'CUST123',
                'payer_id' => 'PAYER123',
            ],
            'create_time' => '2025-01-15T10:30:00Z',
            'update_time' => '2025-01-15T10:30:00Z',
        ],
        'brand' => 'VISA',
        'last_digits' => '1234',
        'type' => 'CREDIT',
        'expiry' => '2026-12',
        'name' => 'John Doe',
    ];

    try {
        $result = VaultManager::saveVaultedCard(1, 100, $cardSource, true);
        
        if ($result !== null) {
            echo "  ✓ saveVaultedCard returned successfully\n";
        } else {
            fwrite(STDERR, "  ✗ saveVaultedCard returned null\n");
            $failures++;
        }
    } catch (\Exception $e) {
        fwrite(STDERR, "  ✗ Unexpected exception: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 2: Verify that INSERT ... ON DUPLICATE KEY UPDATE was used
    echo "\nTest 2: Verify INSERT ... ON DUPLICATE KEY UPDATE was executed...\n";

    $executeCalls = $GLOBALS['db']->executeCalls;
    $foundInsertOnDuplicateKey = false;

    foreach ($executeCalls as $sql) {
        if (stripos($sql, 'INSERT INTO') !== false && stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $foundInsertOnDuplicateKey = true;
            // Verify that date_added is NOT in the UPDATE clause
            $parts = explode('ON DUPLICATE KEY UPDATE', $sql);
            if (count($parts) === 2) {
                $updateClause = $parts[1];
                if (stripos($updateClause, 'date_added') !== false) {
                    fwrite(STDERR, "  ✗ date_added should NOT be in the ON DUPLICATE KEY UPDATE clause\n");
                    $failures++;
                } else {
                    echo "  ✓ date_added is correctly excluded from UPDATE clause\n";
                }
            }
            break;
        }
    }

    if ($foundInsertOnDuplicateKey) {
        echo "  ✓ INSERT ... ON DUPLICATE KEY UPDATE was used\n";
    } else {
        fwrite(STDERR, "  ✗ INSERT ... ON DUPLICATE KEY UPDATE was not found in SQL calls\n");
        $failures++;
    }

    // Test 3: Verify the query was only executed once (no race condition retries needed)
    echo "\nTest 3: Verify single INSERT execution (no retries needed)...\n";

    $insertCount = $GLOBALS['db']->insertCallCount;
    if ($insertCount === 1) {
        echo "  ✓ INSERT was executed exactly once (database handles duplicates atomically)\n";
    } else {
        fwrite(STDERR, "  ✗ Expected 1 INSERT, got $insertCount\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n❌ VaultManager duplicate key test failed with $failures error(s).\n");
        exit(1);
    }

    echo "\n✓ All VaultManager duplicate key handling tests passed\n";
}
