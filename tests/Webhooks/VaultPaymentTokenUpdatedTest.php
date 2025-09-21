<?php
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__, 2) . '/');
    }

    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }

    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }

    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', '');
    }

    if (!function_exists('zen_db_input')) {
        function zen_db_input(string $string): string
        {
            return addslashes($string);
        }
    }

    if (!function_exists('zen_db_perform')) {
        function zen_db_perform(string $table, array $data, string $action = 'insert', string $parameters = ''): void
        {
            global $db;
            if (method_exists($db, 'perform')) {
                $db->perform($table, $data, $action, $parameters);
            }
        }
    }

    class VaultTestResult
    {
        public bool $EOF;
        public array $fields;

        public function __construct(?array $fields)
        {
            $this->EOF = ($fields === null);
            $this->fields = $fields ?? [];
        }

        public function RecordCount(): int
        {
            return $this->EOF ? 0 : 1;
        }

        public function MoveNext(): void
        {
        }
    }

    class VaultTestDb
    {
        /** @var array<string,array<int,array<string,mixed>>> */
        public array $tables = [];

        public function Execute(string $sql): VaultTestResult
        {
            $sql = trim($sql);
            if (stripos($sql, 'create table') === 0) {
                return new VaultTestResult(null);
            }

            if (preg_match("/FROM\\s+" . preg_quote(TABLE_PAYPAL_VAULT, '/') . "\\s+WHERE\\s+vault_id\s*=\s*'([^']+)'/i", $sql, $matches)) {
                $vaultId = stripslashes($matches[1]);
                $row = $this->getVaultById($vaultId);
                return new VaultTestResult($row);
            }

            throw new \RuntimeException('Unhandled SQL: ' . $sql);
        }

        public function perform(string $table, array $data, string $action, string $parameters): void
        {
            $tableData = &$this->tables[$table];
            if (!is_array($tableData)) {
                $tableData = [];
            }

            if ($action === 'insert') {
                if (!isset($data['paypal_vault_id'])) {
                    $data['paypal_vault_id'] = count($tableData) + 1;
                }
                $tableData[] = $data;
                return;
            }

            if ($action === 'update') {
                if (preg_match('/paypal_vault_id\s*=\s*(\d+)/i', $parameters, $matches)) {
                    $targetId = (int)$matches[1];
                    foreach ($tableData as &$row) {
                        if ((int)($row['paypal_vault_id'] ?? 0) === $targetId) {
                            $row = array_merge($row, $data);
                            return;
                        }
                    }
                }
            }

            throw new \RuntimeException('Unhandled perform action: ' . $action . ' with parameters: ' . $parameters);
        }

        public function getVaultById(string $vaultId): ?array
        {
            foreach ($this->tables[TABLE_PAYPAL_VAULT] ?? [] as $row) {
                if (($row['vault_id'] ?? '') === $vaultId) {
                    return $row;
                }
            }

            return null;
        }
    }

    $baseDir = dirname(__DIR__, 2);
    require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';
    require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php';
    require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookObject.php';
    require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookHandlerContract.php';
    require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/Events/VaultPaymentTokenUpdated.php';
}

namespace PayPalRestful\Common {
    class Logger
    {
        public function __construct(string $name = '')
        {
        }

        public function write(string $message, bool $includeTimestamp = false, string $includeSeparator = ''): void
        {
        }
    }
}

namespace PayPalRestful\Tests\Webhooks {
    use PayPalRestful\Common\VaultManager;
    use PayPalRestful\Webhooks\Events\VaultPaymentTokenUpdated;
    use PayPalRestful\Webhooks\WebhookObject;
    use PHPUnit\Framework\TestCase;

    final class VaultPaymentTokenUpdatedTest extends TestCase
    {
        private \VaultTestDb $db;
        private $notifier;

        protected function setUp(): void
        {
            parent::setUp();

            date_default_timezone_set('UTC');

            $this->db = new \VaultTestDb();
            $this->notifier = new class {
                public array $events = [];

                public function notify(string $event, array $payload): void
                {
                    $this->events[] = [$event, $payload];
                }
            };

            global $db, $zco_notifier;
            $db = $this->db;
            $zco_notifier = $this->notifier;
        }

        public function testWebhookRefreshesStoredExpiry(): void
        {
            $card = [
                'type' => 'CREDIT',
                'brand' => 'VISA',
                'last_digits' => '1111',
                'expiry' => '2025-08',
                'name' => 'Test Buyer',
                'billing_address' => [
                    'address_line_1' => '1 Main St',
                    'admin_area_2' => 'Oldtown',
                    'postal_code' => '94105',
                    'country_code' => 'US',
                ],
                'vault' => [
                    'id' => 'CARD-123456',
                    'status' => 'APPROVED',
                    'create_time' => '2023-01-01T12:00:00Z',
                    'update_time' => '2023-01-01T12:00:00Z',
                    'customer' => [
                        'id' => 'CUS-OLD',
                        'payer_id' => 'PAYEROLD',
                    ],
                ],
            ];

            $stored = VaultManager::saveVaultedCard(10, 20, $card);
            $this->assertNotNull($stored);
            $this->assertSame('2025-08', $stored['expiry']);

            $webhookPayload = [
                'id' => 'WH-TEST',
                'event_type' => 'VAULT.PAYMENT-TOKEN.UPDATED',
                'resource' => [
                    'id' => 'CARD-123456',
                    'status' => 'ACTIVE',
                    'create_time' => '2023-01-01T12:00:00Z',
                    'update_time' => '2024-03-15T09:30:00Z',
                    'customer' => [
                        'id' => 'CUS-NEW',
                        'payer_id' => 'PAYER123',
                    ],
                    'metadata' => [
                        'payer_id' => 'PAYER123',
                    ],
                    'payment_source' => [
                        'card' => [
                            'type' => 'CREDIT',
                            'brand' => 'VISA',
                            'last_digits' => '5678',
                            'expiry' => '2026-12',
                            'name' => 'Test Buyer',
                            'billing_address' => [
                                'address_line_1' => '1 Main St',
                                'admin_area_2' => 'Atlanta',
                                'postal_code' => '30303',
                                'country_code' => 'US',
                            ],
                        ],
                    ],
                ],
            ];

            $webhook = new WebhookObject('POST', [], json_encode($webhookPayload));
            $handler = new VaultPaymentTokenUpdated($webhook);
            $handler->action();

            $this->assertNotEmpty($this->notifier->events);
            [$event, $payload] = $this->notifier->events[0];
            $this->assertSame('NOTIFY_PAYPALR_VAULT_CARD_SAVED', $event);
            $this->assertSame('2026-12', $payload['expiry']);
            $this->assertSame('ACTIVE', $payload['status']);
            $this->assertSame('5678', $payload['last_digits']);
            $this->assertSame('CUS-NEW', $payload['paypal_customer_id']);
            $this->assertSame('PAYER123', $payload['payer_id']);
            $this->assertSame([
                'address_line_1' => '1 Main St',
                'admin_area_2' => 'Atlanta',
                'postal_code' => '30303',
                'country_code' => 'US',
            ], $payload['billing_address']);

            $storedRow = $this->db->getVaultById('CARD-123456');
            $this->assertNotNull($storedRow);
            $this->assertSame('2026-12', $storedRow['expiry']);
            $this->assertSame('ACTIVE', strtoupper($storedRow['status']));
        }
    }
}
