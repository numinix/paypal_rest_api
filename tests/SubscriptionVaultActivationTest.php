<?php
declare(strict_types=1);

/**
 * Test that validates subscriptions are automatically activated when vault becomes available.
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
    if (!defined('TABLE_ORDERS')) {
        define('TABLE_ORDERS', 'orders');
    }
    if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
        define('TABLE_PAYPAL_SUBSCRIPTIONS', 'paypal_subscriptions');
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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php';

    // Mock database class
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

            // Handle single record
            if (isset($records['paypal_subscription_id']) || !isset($records[0])) {
                $this->fields = $records;
                $this->EOF = $EOF;
                return;
            }

            // Handle multiple records
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
        public ?int $lastInsertId = null;

        public function Execute(string $query)
        {
            $this->executedQueries[] = $query;
            
            // Mock subscriptions awaiting vault
            if (strpos($query, 'SELECT paypal_subscription_id, status') !== false &&
                strpos($query, 'awaiting_vault') !== false) {
                return new queryFactoryResult([
                    [
                        'paypal_subscription_id' => 1,
                        'status' => 'awaiting_vault',
                    ],
                    [
                        'paypal_subscription_id' => 2,
                        'status' => 'pending',
                    ],
                ], false);
            }
            
            return new queryFactoryResult([], true);
        }

        public function Insert_ID(): int
        {
            return $this->lastInsertId ?? 1;
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
}

namespace Tests {
    use PHPUnit\Framework\TestCase;
    use PayPalAdvancedCheckout\Common\SubscriptionManager;

    class SubscriptionVaultActivationTest extends TestCase
    {
        private \queryFactory $db;

        protected function setUp(): void
        {
            $this->db = new \queryFactory();
            $GLOBALS['db'] = $this->db;
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['db']);
        }

        public function testActivateSubscriptionsWithVaultLinksAndActivatesSubscriptions(): void
        {
            $customersId = 1;
            $ordersId = 100;
            $paypalVaultId = 5;
            $vaultId = 'vault_token_12345';

            $activatedCount = SubscriptionManager::activateSubscriptionsWithVault(
                $customersId,
                $ordersId,
                $paypalVaultId,
                $vaultId
            );

            // Should activate 2 subscriptions
            $this->assertSame(2, $activatedCount);

            // Verify updates were performed
            $this->assertCount(2, $this->db->performedUpdates);

            // Verify first update
            $firstUpdate = $this->db->performedUpdates[0];
            $this->assertSame(TABLE_PAYPAL_SUBSCRIPTIONS, $firstUpdate['table']);
            $this->assertSame('update', $firstUpdate['action']);
            $this->assertSame($paypalVaultId, $firstUpdate['data']['paypal_vault_id']);
            $this->assertSame($vaultId, $firstUpdate['data']['vault_id']);
            $this->assertSame('active', $firstUpdate['data']['status']);
            $this->assertArrayHasKey('last_modified', $firstUpdate['data']);

            // Verify second update
            $secondUpdate = $this->db->performedUpdates[1];
            $this->assertSame(TABLE_PAYPAL_SUBSCRIPTIONS, $secondUpdate['table']);
            $this->assertSame('update', $secondUpdate['action']);
            $this->assertSame($paypalVaultId, $secondUpdate['data']['paypal_vault_id']);
            $this->assertSame($vaultId, $secondUpdate['data']['vault_id']);
            $this->assertSame('active', $secondUpdate['data']['status']);
        }

        public function testActivateSubscriptionsWithInvalidParametersReturnsZero(): void
        {
            // Test with zero customer ID
            $result = SubscriptionManager::activateSubscriptionsWithVault(0, 100, 5, 'vault_token');
            $this->assertSame(0, $result);
            $this->assertEmpty($this->db->performedUpdates);

            // Test with zero order ID
            $result = SubscriptionManager::activateSubscriptionsWithVault(1, 0, 5, 'vault_token');
            $this->assertSame(0, $result);
            $this->assertEmpty($this->db->performedUpdates);

            // Test with zero vault ID
            $result = SubscriptionManager::activateSubscriptionsWithVault(1, 100, 0, 'vault_token');
            $this->assertSame(0, $result);
            $this->assertEmpty($this->db->performedUpdates);

            // Test with empty vault token
            $result = SubscriptionManager::activateSubscriptionsWithVault(1, 100, 5, '');
            $this->assertSame(0, $result);
            $this->assertEmpty($this->db->performedUpdates);
        }

        public function testActivateSubscriptionsQueriesCorrectConditions(): void
        {
            $customersId = 1;
            $ordersId = 100;
            $paypalVaultId = 5;
            $vaultId = 'vault_token_12345';

            SubscriptionManager::activateSubscriptionsWithVault(
                $customersId,
                $ordersId,
                $paypalVaultId,
                $vaultId
            );

            $queries = $this->db->getExecutedQueries();
            
            // Find the SELECT query (skip CREATE TABLE and ALTER TABLE queries)
            $selectQuery = '';
            foreach ($queries as $query) {
                if (strpos($query, 'SELECT paypal_subscription_id, status') !== false) {
                    $selectQuery = $query;
                    break;
                }
            }
            
            $this->assertNotEmpty($selectQuery, 'SELECT query should be executed');

            // Verify the query selects correct table
            $this->assertStringContainsString(TABLE_PAYPAL_SUBSCRIPTIONS, $selectQuery);

            // Verify the query filters by customer and order
            $this->assertStringContainsString("customers_id = {$customersId}", $selectQuery);
            $this->assertStringContainsString("orders_id = {$ordersId}", $selectQuery);

            // Verify the query filters by status
            $this->assertStringContainsString("awaiting_vault", $selectQuery);
            $this->assertStringContainsString("pending", $selectQuery);

            // Verify the query filters for subscriptions without vault
            $this->assertStringContainsString("paypal_vault_id = 0", $selectQuery);
        }
    }
}
