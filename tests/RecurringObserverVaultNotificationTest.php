<?php
declare(strict_types=1);

/**
 * Test that validates the recurring observer listens to vault notifications
 * and activates subscriptions when vault becomes available.
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
    if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
        define('MODULE_PAYMENT_PAYPALAC_VERSION', '1.0.0');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_STATUS', 'True');
    }
    if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
        define('TABLE_PAYPAL_SUBSCRIPTIONS', 'paypal_subscriptions');
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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php';
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
    require_once DIR_FS_CATALOG . 'includes/classes/observers/auto.paypaladvcheckout_recurring.php';

    // Mock database
    class queryFactoryResult
    {
        public array $fields = [];
        public bool $EOF = false;
        private array $records = [];
        private int $currentIndex = 0;

        public function __construct(array $records = [], bool $EOF = false)
        {
            if (empty($records)) {
                $this->EOF = $EOF;
                return;
            }

            if (isset($records['paypal_subscription_id']) || !isset($records[0])) {
                $this->fields = $records;
                $this->EOF = $EOF;
                return;
            }

            $this->records = $records;
            $this->currentIndex = 0;
            $this->fields = $records[0] ?? [];
            $this->EOF = empty($records);
        }

        public function MoveNext(): void
        {
            if (empty($this->records)) {
                $this->EOF = true;
                return;
            }

            $this->currentIndex++;
            if ($this->currentIndex >= count($this->records)) {
                $this->EOF = true;
            } else {
                $this->fields = $this->records[$this->currentIndex];
            }
        }

        public function RecordCount(): int
        {
            if (!empty($this->records)) {
                return count($this->records);
            }
            return $this->EOF ? 0 : 1;
        }
    }

    class queryFactory
    {
        private array $executedQueries = [];
        public array $performedUpdates = [];

        public function Execute(string $query)
        {
            $this->executedQueries[] = $query;
            
            // Mock subscriptions awaiting vault
            if (strpos($query, 'SELECT paypal_subscription_id, status') !== false) {
                return new queryFactoryResult([
                    [
                        'paypal_subscription_id' => 1,
                        'status' => 'awaiting_vault',
                    ],
                ], false);
            }
            
            return new queryFactoryResult([], true);
        }

        public function getExecutedQueries(): array
        {
            return $this->executedQueries;
        }
    }

    function zen_db_input(string $value): string
    {
        return addslashes($value);
    }

    function zen_db_perform(string $table, array $data, string $action = 'insert', string $where = ''): bool
    {
        global $db;
        $db->performedUpdates[] = [
            'table' => $table,
            'data' => $data,
            'action' => $action,
            'where' => $where,
        ];
        return true;
    }

    // Mock notifier
    class base
    {
        public array $notifications = [];

        public function notify($event, $params = [], &$p2 = null, &$p3 = null): void
        {
            $this->notifications[] = [
                'event' => $event,
                'params' => $params,
            ];
        }
    }
}

namespace Tests {
    use PHPUnit\Framework\TestCase;

    class RecurringObserverVaultNotificationTest extends TestCase
    {
        private \queryFactory $db;
        private \base $zco_notifier;

        protected function setUp(): void
        {
            $this->db = new \queryFactory();
            $GLOBALS['db'] = $this->db;
            
            $this->zco_notifier = new \base();
            $GLOBALS['zco_notifier'] = $this->zco_notifier;
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['db']);
            unset($GLOBALS['zco_notifier']);
        }

        public function testObserverActivatesSubscriptionsWhenVaultNotificationReceived(): void
        {
            $observer = new \zcObserverPaypaladvcheckoutRecurring();

            $vaultRecord = [
                'customers_id' => 1,
                'orders_id' => 100,
                'paypal_vault_id' => 5,
                'vault_id' => 'vault_token_12345',
                'status' => 'ACTIVE',
            ];

            $class = new \stdClass();
            $observer->updateNotifyPaypalacVaultCardSaved($class, 'NOTIFY_PAYPALAC_VAULT_CARD_SAVED', $vaultRecord);

            // Verify subscriptions were queried
            $queries = $this->db->getExecutedQueries();
            $this->assertNotEmpty($queries);
            $this->assertStringContainsString(TABLE_PAYPAL_SUBSCRIPTIONS, $queries[0]);

            // Verify subscriptions were updated
            $this->assertCount(1, $this->db->performedUpdates);
            $update = $this->db->performedUpdates[0];
            $this->assertSame('active', $update['data']['status']);
            $this->assertSame(5, $update['data']['paypal_vault_id']);
            $this->assertSame('vault_token_12345', $update['data']['vault_id']);

            // Verify notification was sent
            $notifications = array_filter($this->zco_notifier->notifications, function($n) {
                return $n['event'] === 'NOTIFY_SUBSCRIPTIONS_ACTIVATED';
            });
            $this->assertCount(1, $notifications);
            
            $notification = array_values($notifications)[0];
            $this->assertSame(1, $notification['params']['customers_id']);
            $this->assertSame(100, $notification['params']['orders_id']);
            $this->assertSame('vault_token_12345', $notification['params']['vault_id']);
            $this->assertSame(1, $notification['params']['activated_count']);
        }

        public function testObserverIgnoresInvalidVaultRecord(): void
        {
            $observer = new \zcObserverPaypaladvcheckoutRecurring();

            // Test with empty array
            $class = new \stdClass();
            $observer->updateNotifyPaypalacVaultCardSaved($class, 'NOTIFY_PAYPALAC_VAULT_CARD_SAVED', []);
            $this->assertEmpty($this->db->performedUpdates);

            // Test with missing customers_id
            $observer->updateNotifyPaypalacVaultCardSaved($class, 'NOTIFY_PAYPALAC_VAULT_CARD_SAVED', [
                'orders_id' => 100,
                'paypal_vault_id' => 5,
                'vault_id' => 'vault_token_12345',
            ]);
            $this->assertEmpty($this->db->performedUpdates);

            // Test with missing vault_id
            $observer->updateNotifyPaypalacVaultCardSaved($class, 'NOTIFY_PAYPALAC_VAULT_CARD_SAVED', [
                'customers_id' => 1,
                'orders_id' => 100,
                'paypal_vault_id' => 5,
            ]);
            $this->assertEmpty($this->db->performedUpdates);
        }

        public function testObserverAttachesToVaultNotification(): void
        {
            // Create a mock to verify the attach method is called with correct events
            $observer = $this->getMockBuilder(\zcObserverPaypaladvcheckoutRecurring::class)
                ->onlyMethods(['attach'])
                ->getMock();

            $observer->expects($this->once())
                ->method('attach')
                ->with(
                    $this->identicalTo($observer),
                    $this->callback(function($events) {
                        return in_array('NOTIFY_PAYPALAC_VAULT_CARD_SAVED', $events, true);
                    })
                );

            $observer->__construct();
        }
    }
}
