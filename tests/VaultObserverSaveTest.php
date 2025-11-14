<?php
declare(strict_types=1);

/**
 * Test that validates the vault observer correctly saves vault card data
 * after order creation across all checkout systems.
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
    if (!defined('MODULE_PAYMENT_PAYPALR_STATUS')) {
        define('MODULE_PAYMENT_PAYPALR_STATUS', 'True');
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

    // Mock PSR-4 Autoloader
    class mockPsr4Autoloader
    {
        public function addPrefix(string $prefix, string $path): void
        {
            // Mock implementation - does nothing
        }
    }
    
    if (!isset($GLOBALS['psr4Autoloader'])) {
        $GLOBALS['psr4Autoloader'] = new mockPsr4Autoloader();
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';

    // Mock database class
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

    class queryFactory
    {
        private array $executedQueries = [];
        private array $mockResults = [];

        public function Execute(string $query)
        {
            $this->executedQueries[] = $query;
            
            // Check for order query
            if (strpos($query, 'SELECT customers_id') !== false) {
                return $this->mockResults['order'] ?? new queryFactoryResult(['customers_id' => 1], false);
            }
            
            // Check for vault existence query
            if (strpos($query, 'SELECT paypal_vault_id') !== false) {
                return $this->mockResults['vault_exists'] ?? new queryFactoryResult([], true);
            }
            
            // Check for vault select query
            if (strpos($query, 'SELECT *') !== false && strpos($query, TABLE_PAYPAL_VAULT) !== false) {
                return $this->mockResults['vault_select'] ?? new queryFactoryResult([
                    'paypal_vault_id' => 1,
                    'customers_id' => 1,
                    'orders_id' => 100,
                    'vault_id' => '1234567890',
                    'status' => 'ACTIVE',
                    'brand' => 'VISA',
                    'last_digits' => '1234',
                    'card_type' => 'CREDIT',
                    'expiry' => '2025-12',
                ], false);
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

        public function clearExecutedQueries(): void
        {
            $this->executedQueries = [];
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

    // Mock Zen Cart notifier (used by VaultManager)
    class zco_notifier
    {
        public array $notifications = [];

        public function notify($event, ...$params): void
        {
            $this->notifications[] = [
                'event' => $event,
                'params' => $params,
            ];
        }
    }

    // Initialize global variables
    $GLOBALS['db'] = new queryFactory();
    $GLOBALS['zco_notifier'] = new zco_notifier();
    $GLOBALS['mockDbPerformCalls'] = [];
}

namespace Zencart\Traits {
    trait ObserverManager
    {
        protected function attach($observer, array $events): void
        {
            // Mock implementation
        }
    }
}

namespace {
    require_once DIR_FS_CATALOG . 'includes/classes/observers/auto.paypalrestful_vault.php';

    use PHPUnit\Framework\TestCase;

    class VaultObserverSaveTest extends TestCase
    {
        private zcObserverPaypalrestfulVault $observer;

        protected function setUp(): void
        {
            global $db, $zco_notifier, $mockDbPerformCalls;
            
            // Reset globals
            $db = new queryFactory();
            $zco_notifier = new zco_notifier();
            $mockDbPerformCalls = [];
            
            // Clear session
            $_SESSION = [];
            
            $this->observer = new zcObserverPaypalrestfulVault();
        }

        protected function tearDown(): void
        {
            $_SESSION = [];
        }

        public function testObserverDoesNothingWhenNoOrderId(): void
        {
            global $mockDbPerformCalls;
            
            // No order_number_created in session
            $_SESSION = [];
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            $this->assertEmpty($mockDbPerformCalls, 'No database operations should occur without order ID');
        }

        public function testObserverDoesNothingWhenNoVaultData(): void
        {
            global $mockDbPerformCalls;
            
            // Set order ID but no vault data
            $_SESSION['order_number_created'] = 100;
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            $this->assertEmpty($mockDbPerformCalls, 'No database operations should occur without vault data');
        }

        public function testObserverSavesVaultCardData(): void
        {
            global $mockDbPerformCalls, $db;
            
            // Set up session with order and vault data
            $_SESSION['order_number_created'] = 100;
            $_SESSION['PayPalRestful']['VaultCardData'] = [
                'card_source' => [
                    'vault' => [
                        'id' => '1234567890',
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
                    'expiry' => '2025-12',
                    'name' => 'John Doe',
                    'billing_address' => [
                        'address_line_1' => '123 Main St',
                        'admin_area_2' => 'Anytown',
                        'admin_area_1' => 'CA',
                        'postal_code' => '12345',
                        'country_code' => 'US',
                    ],
                ],
            ];
            
            // Mock database to return customer ID
            $db->setMockResult('order', new queryFactoryResult(['customers_id' => 1], false));
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            // Verify that zen_db_perform was called to insert vault data
            $this->assertNotEmpty($mockDbPerformCalls, 'Database should be called to save vault data');
            
            // Find the insert call for the vault table
            $vaultInsert = null;
            foreach ($mockDbPerformCalls as $call) {
                if ($call['table'] === TABLE_PAYPAL_VAULT && $call['action'] === 'insert') {
                    $vaultInsert = $call;
                    break;
                }
            }
            
            $this->assertNotNull($vaultInsert, 'Vault insert should have been called');
            $this->assertEquals(1, $vaultInsert['data']['customers_id'], 'Customer ID should match');
            $this->assertEquals(100, $vaultInsert['data']['orders_id'], 'Order ID should match');
            $this->assertEquals('1234567890', $vaultInsert['data']['vault_id'], 'Vault ID should match');
            $this->assertEquals('ACTIVE', $vaultInsert['data']['status'], 'Status should match');
            $this->assertEquals('VISA', $vaultInsert['data']['brand'], 'Brand should match');
            $this->assertEquals('1234', $vaultInsert['data']['last_digits'], 'Last digits should match');
            
            // Verify session was cleaned up
            $this->assertArrayNotHasKey('VaultCardData', $_SESSION['PayPalRestful'] ?? [], 'Vault data should be removed from session');
        }

        public function testObserverProcessesOrderOnlyOnce(): void
        {
            global $mockDbPerformCalls, $db;
            
            // Set up session
            $_SESSION['order_number_created'] = 100;
            $_SESSION['PayPalRestful']['VaultCardData'] = [
                'card_source' => [
                    'vault' => [
                        'id' => '1234567890',
                        'status' => 'ACTIVE',
                    ],
                    'brand' => 'VISA',
                    'last_digits' => '1234',
                ],
            ];
            
            $db->setMockResult('order', new queryFactoryResult(['customers_id' => 1], false));
            
            // Call the observer twice
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            $firstCallCount = count($mockDbPerformCalls);
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            // Should not have made additional database calls
            $this->assertEquals($firstCallCount, count($mockDbPerformCalls), 'Observer should process each order only once');
        }

        public function testObserverSendsNotification(): void
        {
            global $zco_notifier, $db;
            
            // Set up session
            $_SESSION['order_number_created'] = 100;
            $_SESSION['PayPalRestful']['VaultCardData'] = [
                'card_source' => [
                    'vault' => [
                        'id' => '1234567890',
                        'status' => 'ACTIVE',
                    ],
                    'brand' => 'VISA',
                    'last_digits' => '1234',
                ],
            ];
            
            $db->setMockResult('order', new queryFactoryResult(['customers_id' => 1], false));
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            // Check that the notification was sent
            $this->assertNotEmpty($zco_notifier->notifications, 'Notification should be sent');
            
            $vaultNotification = null;
            foreach ($zco_notifier->notifications as $notification) {
                if ($notification['event'] === 'NOTIFY_PAYPALR_VAULT_CARD_SAVED') {
                    $vaultNotification = $notification;
                    break;
                }
            }
            
            $this->assertNotNull($vaultNotification, 'NOTIFY_PAYPALR_VAULT_CARD_SAVED notification should be sent');
        }

        public function testObserverHandlesInvalidCustomerId(): void
        {
            global $mockDbPerformCalls, $db;
            
            // Set up session
            $_SESSION['order_number_created'] = 100;
            $_SESSION['PayPalRestful']['VaultCardData'] = [
                'card_source' => [
                    'vault' => [
                        'id' => '1234567890',
                        'status' => 'ACTIVE',
                    ],
                    'brand' => 'VISA',
                    'last_digits' => '1234',
                ],
            ];
            
            // Mock database to return invalid customer ID
            $db->setMockResult('order', new queryFactoryResult(['customers_id' => 0], false));
            
            $this->observer->updateNotifyCheckoutProcessAfterOrderCreateAddProducts(
                $this,
                'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
                []
            );
            
            // Verify that no vault save was attempted
            $this->assertEmpty($mockDbPerformCalls, 'No database operations should occur with invalid customer ID');
        }
    }
}
