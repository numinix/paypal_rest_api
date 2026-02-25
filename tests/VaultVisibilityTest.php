<?php
declare(strict_types=1);

/**
 * Test that validates the vault visibility functionality.
 * 
 * This test verifies that:
 * 1. All cards are vaulted with PayPal
 * 2. Only cards where the user checked "Save for future use" are visible in checkout
 * 3. All cards (visible and hidden) are accessible in the account management page
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
    if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_STATUS', 'True');
    }
    if (!defined('TABLE_ORDERS')) {
        define('TABLE_ORDERS', 'orders');
    }
    if (!defined('TABLE_PAYPAL_VAULT')) {
        define('TABLE_PAYPAL_VAULT', 'paypal_vault');
    }
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', '');
    }

    // Mock PSR-4 Autoloader
    class mockPsr4Autoloader
    {
        public function addPrefix(string $prefix, string $path): void
        {
            // Mock implementation
        }
    }
    
    if (!isset($GLOBALS['psr4Autoloader'])) {
        $GLOBALS['psr4Autoloader'] = new mockPsr4Autoloader();
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/VaultManager.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';

    // Mock database class
    class queryFactoryResult
    {
        public array $fields = [];
        public bool $EOF = false;
        private array $allRows = [];
        private int $currentIndex = 0;

        public function __construct(array $fields = [], bool $EOF = false)
        {
            if ($EOF) {
                $this->fields = [];
                $this->EOF = true;
                $this->allRows = [];
            } else {
                // Check if this is a multi-row result
                if (isset($fields[0]) && is_array($fields[0])) {
                    $this->allRows = $fields;
                    $this->fields = $fields[0] ?? [];
                    $this->EOF = empty($fields);
                } else {
                    $this->allRows = [$fields];
                    $this->fields = $fields;
                }
            }
        }

        public function MoveNext(): void
        {
            $this->currentIndex++;
            if ($this->currentIndex >= count($this->allRows)) {
                $this->EOF = true;
                $this->fields = [];
            } else {
                $this->fields = $this->allRows[$this->currentIndex];
            }
        }
    }

    class queryFactory
    {
        private array $executedQueries = [];
        private array $mockResults = [];
        private array $insertedData = [];

        public function Execute(string $query)
        {
            $this->executedQueries[] = $query;
            
            // Handle CREATE TABLE
            if (stripos($query, 'CREATE TABLE') !== false) {
                return new queryFactoryResult([], false);
            }
            
            // Handle SHOW COLUMNS
            if (stripos($query, 'SHOW COLUMNS') !== false) {
                // Return that visible column exists
                return new queryFactoryResult(['Field' => 'visible'], false);
            }
            
            // Handle INSERT ... ON DUPLICATE KEY UPDATE
            if (stripos($query, 'INSERT INTO') !== false && stripos($query, 'ON DUPLICATE KEY UPDATE') !== false) {
                // Parse the INSERT query to extract field values
                if (preg_match('/INSERT INTO .* \((.*?)\)\s+VALUES\s+\((.*?)\)/is', $query, $matches)) {
                    $columns = array_map('trim', explode(',', $matches[1]));
                    $values = array_map('trim', explode(',', $matches[2]));
                    
                    $data = [];
                    foreach ($columns as $idx => $col) {
                        $col = trim($col, '` ');
                        $val = $values[$idx] ?? '';
                        // Remove quotes from values
                        $val = trim($val, "' ");
                        $data[$col] = $val;
                    }
                    
                    $this->insertedData[] = $data;
                }
                
                // Return success
                return new queryFactoryResult([], false);
            }
            
            // Handle SELECT for vault existence check
            if (stripos($query, 'SELECT paypal_vault_id') !== false && stripos($query, 'LIMIT 1') !== false) {
                return $this->mockResults['vault_exists'] ?? new queryFactoryResult([], true);
            }
            
            // Handle SELECT * queries for vault data
            if (stripos($query, 'SELECT *') !== false && stripos($query, TABLE_PAYPAL_VAULT) !== false) {
                // Check if this is looking for visible cards (activeOnly = true)
                if (stripos($query, 'visible = 1') !== false) {
                    return $this->mockResults['vault_select_visible'] ?? new queryFactoryResult([], true);
                }
                // Otherwise return all cards (activeOnly = false)
                return $this->mockResults['vault_select_all'] ?? new queryFactoryResult([], true);
            }
            
            return new queryFactoryResult([], true);
        }

        public function setMockResult(string $key, queryFactoryResult $result): void
        {
            $this->mockResults[$key] = $result;
        }

        public function getExecutedQueries(): array
        {
            return $this->executedQueries;
        }

        public function getInsertedData(): array
        {
            return $this->insertedData;
        }

        public function clearExecutedQueries(): void
        {
            $this->executedQueries = [];
        }
        
        public function clearInsertedData(): void
        {
            $this->insertedData = [];
        }
    }

    // Mock zen_db_perform function
    function zen_db_perform(string $table, array $data, string $action = 'insert', string $where = ''): void
    {
        global $mockDbPerformCalls;
        $mockDbPerformCalls[] = [
            'table' => $table,
            'data' => $data,
            'action' => $action,
            'where' => $where,
        ];
    }

    // Mock zen_db_input function
    function zen_db_input($value): string
    {
        return addslashes((string)$value);
    }

    // Initialize global variables
    $GLOBALS['db'] = new queryFactory();
    $GLOBALS['mockDbPerformCalls'] = [];
}

namespace {
    use PayPalAdvancedCheckout\Common\VaultManager;

    $failures = 0;

    echo "Test 1: Save card with visible=true (user checked save card checkbox)...\n";
    
    $cardSource = [
        'vault' => [
            'id' => 'VAULT-VISIBLE-123',
            'status' => 'ACTIVE',
            'customer' => [
                'id' => 'CUST123',
                'payer_id' => 'PAYER456',
            ],
            'create_time' => '2025-01-15T10:30:00Z',
            'update_time' => '2025-01-15T10:30:00Z',
        ],
        'brand' => 'VISA',
        'last_digits' => '1234',
        'type' => 'CREDIT',
        'expiry' => '2028-12',
        'name' => 'John Doe',
        'billing_address' => [
            'address_line_1' => '123 Main St',
            'admin_area_2' => 'Anytown',
            'admin_area_1' => 'CA',
            'postal_code' => '12345',
            'country_code' => 'US',
        ],
    ];
    
    VaultManager::saveVaultedCard(1, 100, $cardSource, true);
    
    // Check that visible was set to 1 in the INSERT query
    $found = false;
    $insertedData = $GLOBALS['db']->getInsertedData();
    foreach ($insertedData as $data) {
        if (isset($data['visible'])) {
            if ($data['visible'] === '1') {
                echo "  ✓ Card saved with visible=1 (will be shown in checkout)\n";
                $found = true;
            } else {
                fwrite(STDERR, "  ✗ Expected visible=1, got {$data['visible']}\n");
                $failures++;
            }
            break;
        }
    }
    if (!$found) {
        fwrite(STDERR, "  ✗ visible field was not set in database insert\n");
        $failures++;
    }

    echo "\nTest 2: Save card with visible=false (user did not check save card checkbox)...\n";
    
    $GLOBALS['db']->clearInsertedData();
    
    $cardSource2 = [
        'vault' => [
            'id' => 'VAULT-HIDDEN-456',
            'status' => 'ACTIVE',
            'customer' => [
                'id' => 'CUST123',
                'payer_id' => 'PAYER456',
            ],
            'create_time' => '2025-01-15T10:31:00Z',
            'update_time' => '2025-01-15T10:31:00Z',
        ],
        'brand' => 'MASTERCARD',
        'last_digits' => '5678',
        'type' => 'CREDIT',
        'expiry' => '2027-06',
        'name' => 'Jane Smith',
    ];
    
    VaultManager::saveVaultedCard(1, 101, $cardSource2, false);
    
    // Check that visible was set to 0 in the INSERT query
    $found = false;
    $insertedData = $GLOBALS['db']->getInsertedData();
    foreach ($insertedData as $data) {
        if (isset($data['visible'])) {
            if ($data['visible'] === '0') {
                echo "  ✓ Card saved with visible=0 (will NOT be shown in checkout)\n";
                $found = true;
            } else {
                fwrite(STDERR, "  ✗ Expected visible=0, got {$data['visible']}\n");
                $failures++;
            }
            break;
        }
    }
    if (!$found) {
        fwrite(STDERR, "  ✗ visible field was not set in database insert\n");
        $failures++;
    }

    echo "\nTest 3: getCustomerVaultedCards with activeOnly=true returns only visible cards...\n";
    
    // Mock database to return both visible and hidden cards
    // Use future expiry dates to avoid cards being filtered as expired
    $allCards = [
        [
            'paypal_vault_id' => 1,
            'customers_id' => 1,
            'orders_id' => 100,
            'vault_id' => 'VAULT-VISIBLE-123',
            'status' => 'ACTIVE',
            'brand' => 'VISA',
            'last_digits' => '1234',
            'card_type' => 'CREDIT',
            'expiry' => '2028-12',
            'visible' => 1,
        ],
        [
            'paypal_vault_id' => 2,
            'customers_id' => 1,
            'orders_id' => 101,
            'vault_id' => 'VAULT-HIDDEN-456',
            'status' => 'ACTIVE',
            'brand' => 'MASTERCARD',
            'last_digits' => '5678',
            'card_type' => 'CREDIT',
            'expiry' => '2027-06',
            'visible' => 0,
        ],
    ];
    
    $visibleCards = [
        [
            'paypal_vault_id' => 1,
            'customers_id' => 1,
            'orders_id' => 100,
            'vault_id' => 'VAULT-VISIBLE-123',
            'status' => 'ACTIVE',
            'brand' => 'VISA',
            'last_digits' => '1234',
            'card_type' => 'CREDIT',
            'expiry' => '2028-12',
            'visible' => 1,
        ],
    ];
    
    $GLOBALS['db']->setMockResult('vault_select_visible', new queryFactoryResult($visibleCards, false));
    $GLOBALS['db']->setMockResult('vault_select_all', new queryFactoryResult($allCards, false));
    
    $cards = VaultManager::getCustomerVaultedCards(1, true);
    
    if (count($cards) === 1) {
        echo "  ✓ activeOnly=true returns only 1 visible card\n";
        if ($cards[0]['vault_id'] === 'VAULT-VISIBLE-123') {
            echo "  ✓ Correct visible card returned\n";
        } else {
            fwrite(STDERR, "  ✗ Wrong card returned\n");
            $failures++;
        }
    } else {
        fwrite(STDERR, "  ✗ Expected 1 card, got " . count($cards) . "\n");
        $failures++;
    }

    echo "\nTest 4: getCustomerVaultedCards with activeOnly=false returns all cards...\n";
    
    $cards = VaultManager::getCustomerVaultedCards(1, false);
    
    if (count($cards) === 2) {
        echo "  ✓ activeOnly=false returns all 2 cards (for account management)\n";
    } else {
        fwrite(STDERR, "  ✗ Expected 2 cards, got " . count($cards) . "\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n❌ Vault visibility test failed with $failures error(s).\n");
        exit(1);
    }

    echo "\n✅ All vault visibility tests passed.\n";
    echo "   - All cards are vaulted with PayPal for security\n";
    echo "   - Only cards where user checked 'Save' are visible in checkout\n";
    echo "   - All cards (visible and hidden) are accessible in account management\n";
}
