<?php
declare(strict_types=1);

/**
 * Test that validates the sync of vault records to saved_credit_cards table
 * when NOTIFY_PAYPALR_VAULT_CARD_SAVED is triggered.
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
    if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
        define('MODULE_PAYMENT_PAYPALR_VERSION', '1.0.0');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_STATUS')) {
        define('MODULE_PAYMENT_PAYPALR_STATUS', 'True');
    }
    if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
        define('TABLE_PAYPAL_SUBSCRIPTIONS', 'paypal_subscriptions');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', 'saved_credit_cards');
    }
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('DIR_WS_CLASSES')) {
        define('DIR_WS_CLASSES', 'includes/classes/');
    }

    // Mock paypalSavedCardRecurring class
    if (!class_exists('paypalSavedCardRecurring')) {
        class paypalSavedCardRecurring {
            public function schedule_payment($amount, $nextBillingDate, $savedCreditCardId, $ordersProductsId, $description, $metadata = []) {
                return 1; // Return mock subscription ID
            }
        }
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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/SubscriptionManager.php';
}

// Mock ObserverManager trait in separate namespace
namespace Zencart\Traits {
    if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
        trait ObserverManager {
            protected function attach($observer, array $events): void {}
        }
    }
}

namespace {
    require_once DIR_FS_CATALOG . 'includes/classes/observers/auto.paypalrestful_recurring.php';

    // Mock database
    class queryFactoryResult
    {
        public array $fields = [];
        public bool $EOF = false;

        public function __construct(array $fields = [], bool $EOF = false)
        {
            $this->fields = $fields;
            $this->EOF = $EOF;
        }

        public function MoveNext(): void
        {
            $this->EOF = true;
        }
    }

    class mockDb
    {
        public array $queries = [];
        public array $mockResults = [];
        private int $lastInsertId = 1;

        public function Execute(string $sql): queryFactoryResult
        {
            $this->queries[] = $sql;
            
            // Check for SELECT query to find existing saved_credit_card
            if (stripos($sql, 'SELECT') !== false && stripos($sql, TABLE_SAVED_CREDIT_CARDS) !== false) {
                // Return empty result for first call (no existing record)
                if (count($this->queries) === 1) {
                    return new queryFactoryResult([], true);
                }
            }
            
            return $this->mockResults[count($this->queries) - 1] ?? new queryFactoryResult([], true);
        }

        public function Insert_ID(): int
        {
            return $this->lastInsertId++;
        }

        public function setMockResult(int $index, array $fields = [], bool $EOF = false): void
        {
            $this->mockResults[$index] = new queryFactoryResult($fields, $EOF);
        }
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input(string $value): string
        {
            return addslashes($value);
        }
    }

    // Mock zco_notifier
    class mockZcoNotifier
    {
        public array $notifications = [];

        public function notify(string $event, $data = null): void
        {
            $this->notifications[] = [
                'event' => $event,
                'data' => $data,
            ];
        }
    }

    use PHPUnit\Framework\TestCase;

    class VaultToSavedCreditCardsSyncTest extends TestCase
    {
        private mockDb $db;
        private mockZcoNotifier $zco_notifier;

        protected function setUp(): void
        {
            $this->db = new mockDb();
            $GLOBALS['db'] = $this->db;
            
            $this->zco_notifier = new mockZcoNotifier();
            $GLOBALS['zco_notifier'] = $this->zco_notifier;
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['db']);
            unset($GLOBALS['zco_notifier']);
        }

        public function testVaultRecordSyncedToSavedCreditCards(): void
        {
            $observer = new zcObserverPaypalrestfulRecurring();

            $vaultRecord = [
                'paypal_vault_id' => 123,
                'customers_id' => 456,
                'orders_id' => 789,
                'vault_id' => '73x436459c971942t',
                'brand' => 'VISA',
                'last_digits' => '8137',
                'card_type' => 'CREDIT',
                'expiry' => '2028-11',
                'cardholder_name' => 'Jeffrey Lew',
            ];

            $class = new stdClass();
            $observer->updateNotifyPaypalrVaultCardSaved($class, 'NOTIFY_PAYPALR_VAULT_CARD_SAVED', $vaultRecord);

            // Verify that INSERT query was executed
            $insertExecuted = false;
            foreach ($this->db->queries as $query) {
                if (stripos($query, 'INSERT INTO') !== false && stripos($query, TABLE_SAVED_CREDIT_CARDS) !== false) {
                    $insertExecuted = true;
                    
                    // Verify the query contains the correct vault_id
                    $this->assertStringContainsString('73x436459c971942t', $query, 'INSERT query should contain vault_id');
                    
                    // Verify the query contains customer ID
                    $this->assertStringContainsString('456', $query, 'INSERT query should contain customers_id');
                    
                    // Verify the query contains card details
                    $this->assertStringContainsString('VISA', $query, 'INSERT query should contain card brand');
                    $this->assertStringContainsString('8137', $query, 'INSERT query should contain last digits');
                    $this->assertStringContainsString('11', $query, 'INSERT query should contain expiry month');
                    $this->assertStringContainsString('2028', $query, 'INSERT query should contain expiry year');
                    $this->assertStringContainsString('Jeffrey Lew', $query, 'INSERT query should contain cardholder name');
                    
                    break;
                }
            }

            $this->assertTrue($insertExecuted, 'INSERT query for saved_credit_cards should have been executed');
        }

        public function testVaultSyncSkipsIfRecordAlreadyExists(): void
        {
            // Set up mock to return existing record
            $this->db->setMockResult(0, ['saved_credit_card_id' => 999], false);

            $observer = new zcObserverPaypalrestfulRecurring();

            $vaultRecord = [
                'paypal_vault_id' => 123,
                'customers_id' => 456,
                'orders_id' => 789,
                'vault_id' => '73x436459c971942t',
                'brand' => 'VISA',
                'last_digits' => '8137',
                'card_type' => 'CREDIT',
                'expiry' => '2028-11',
                'cardholder_name' => 'Jeffrey Lew',
            ];

            $class = new stdClass();
            $observer->updateNotifyPaypalrVaultCardSaved($class, 'NOTIFY_PAYPALR_VAULT_CARD_SAVED', $vaultRecord);

            // Verify that SELECT query was executed but INSERT was NOT
            $selectExecuted = false;
            $insertExecuted = false;
            
            foreach ($this->db->queries as $query) {
                if (stripos($query, 'SELECT') !== false && stripos($query, TABLE_SAVED_CREDIT_CARDS) !== false) {
                    $selectExecuted = true;
                }
                if (stripos($query, 'INSERT INTO') !== false && stripos($query, TABLE_SAVED_CREDIT_CARDS) !== false) {
                    $insertExecuted = true;
                }
            }

            $this->assertTrue($selectExecuted, 'SELECT query should have been executed to check for existing record');
            $this->assertFalse($insertExecuted, 'INSERT query should NOT have been executed when record already exists');
        }

        public function testVaultSyncSkipsIfVaultIdEmpty(): void
        {
            $observer = new zcObserverPaypalrestfulRecurring();

            $vaultRecord = [
                'paypal_vault_id' => 123,
                'customers_id' => 456,
                'orders_id' => 789,
                'vault_id' => '', // Empty vault_id
                'brand' => 'VISA',
                'last_digits' => '8137',
            ];

            $class = new stdClass();
            $observer->updateNotifyPaypalrVaultCardSaved($class, 'NOTIFY_PAYPALR_VAULT_CARD_SAVED', $vaultRecord);

            // Verify that no INSERT query was executed
            foreach ($this->db->queries as $query) {
                $this->assertStringNotContainsString('INSERT INTO', $query, 'No INSERT should occur when vault_id is empty');
            }
        }
    }
}
