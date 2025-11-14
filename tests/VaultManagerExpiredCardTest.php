<?php
declare(strict_types=1);

/**
 * Test to verify that expired cards are filtered correctly during checkout
 * but remain visible in the account area for updating.
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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php';

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

    class MockDb
    {
        private array $mockRecords = [];

        public function setMockRecords(array $records): void
        {
            $this->mockRecords = $records;
        }

        public function Execute($query)
        {
            if (stripos($query, 'CREATE TABLE') !== false) {
                return new MockDbRecord([]);
            }
            return new MockDbRecord($this->mockRecords);
        }
    }

    function zen_db_input($value)
    {
        return addslashes((string)$value);
    }

    $db = new MockDb();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['customer_id'] = 123;
}

namespace {
    use PayPalRestful\Common\VaultManager;

    $failures = 0;

    // Get current date for testing
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    $nextYear = $currentYear + 1;
    $lastYear = $currentYear - 1;
    $nextMonth = ($currentMonth % 12) + 1;
    $nextMonthYear = $currentMonth === 12 ? $nextYear : $currentYear;

    // Create mock vault records
    $mockCards = [
        // Valid card - expires next year
        [
            'paypal_vault_id' => '1',
            'customers_id' => '123',
            'orders_id' => '100',
            'vault_id' => 'VAULT-VALID-001',
            'status' => 'ACTIVE',
            'brand' => 'Visa',
            'last_digits' => '1111',
            'card_type' => 'CREDIT',
            'expiry' => sprintf('%04d-%02d', $nextYear, $currentMonth),
            'payer_id' => '',
            'paypal_customer_id' => '',
            'cardholder_name' => 'John Doe',
            'billing_address' => null,
            'card_data' => null,
            'create_time' => null,
            'update_time' => null,
            'date_added' => '2024-01-01 00:00:00',
            'last_modified' => '2024-01-01 00:00:00',
            'last_used' => '2024-01-01 00:00:00',
        ],
        // Expired card - expired last year
        [
            'paypal_vault_id' => '2',
            'customers_id' => '123',
            'orders_id' => '101',
            'vault_id' => 'VAULT-EXPIRED-002',
            'status' => 'ACTIVE',
            'brand' => 'Mastercard',
            'last_digits' => '2222',
            'card_type' => 'CREDIT',
            'expiry' => sprintf('%04d-%02d', $lastYear, $currentMonth),
            'payer_id' => '',
            'paypal_customer_id' => '',
            'cardholder_name' => 'Jane Doe',
            'billing_address' => null,
            'card_data' => null,
            'create_time' => null,
            'update_time' => null,
            'date_added' => '2023-01-01 00:00:00',
            'last_modified' => '2023-01-01 00:00:00',
            'last_used' => '2023-01-01 00:00:00',
        ],
        // Valid card - expires this month or next month
        [
            'paypal_vault_id' => '3',
            'customers_id' => '123',
            'orders_id' => '102',
            'vault_id' => 'VAULT-VALID-003',
            'status' => 'VAULTED',
            'brand' => 'Amex',
            'last_digits' => '3333',
            'card_type' => 'CREDIT',
            'expiry' => sprintf('%04d-%02d', $nextMonthYear, $nextMonth),
            'payer_id' => '',
            'paypal_customer_id' => '',
            'cardholder_name' => 'Bob Smith',
            'billing_address' => null,
            'card_data' => null,
            'create_time' => null,
            'update_time' => null,
            'date_added' => '2024-06-01 00:00:00',
            'last_modified' => '2024-06-01 00:00:00',
            'last_used' => '2024-06-01 00:00:00',
        ],
    ];

    $db->setMockRecords($mockCards);

    // Test 1: activeOnly = true (checkout context) - should exclude expired cards
    $activeCards = VaultManager::getCustomerVaultedCards(123, true);
    
    if (count($activeCards) !== 2) {
        fwrite(STDERR, sprintf(
            "Expected 2 active (non-expired) cards, got %d\n",
            count($activeCards)
        ));
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Checkout context returns only non-expired cards\n");
    }

    // Verify the returned cards are the valid ones (not the expired one)
    $vaultIds = array_column($activeCards, 'vault_id');
    if (!in_array('VAULT-VALID-001', $vaultIds, true)) {
        fwrite(STDERR, "Expected VAULT-VALID-001 in active cards\n");
        $failures++;
    }
    if (!in_array('VAULT-VALID-003', $vaultIds, true)) {
        fwrite(STDERR, "Expected VAULT-VALID-003 in active cards\n");
        $failures++;
    }
    if (in_array('VAULT-EXPIRED-002', $vaultIds, true)) {
        fwrite(STDERR, "Should not include VAULT-EXPIRED-002 in active cards\n");
        $failures++;
    }

    // Test 2: activeOnly = false (account context) - should include all cards
    $allCards = VaultManager::getCustomerVaultedCards(123, false);
    
    if (count($allCards) !== 3) {
        fwrite(STDERR, sprintf(
            "Expected 3 total cards (including expired) for account view, got %d\n",
            count($allCards)
        ));
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Account context returns all cards including expired\n");
    }

    // Verify all cards are present in account view
    $allVaultIds = array_column($allCards, 'vault_id');
    if (!in_array('VAULT-VALID-001', $allVaultIds, true)) {
        fwrite(STDERR, "Expected VAULT-VALID-001 in all cards\n");
        $failures++;
    }
    if (!in_array('VAULT-EXPIRED-002', $allVaultIds, true)) {
        fwrite(STDERR, "Expected VAULT-EXPIRED-002 in all cards\n");
        $failures++;
    }
    if (!in_array('VAULT-VALID-003', $allVaultIds, true)) {
        fwrite(STDERR, "Expected VAULT-VALID-003 in all cards\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\nTotal failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✓ All VaultManager expired card filtering tests passed\n");
    exit(0);
}
