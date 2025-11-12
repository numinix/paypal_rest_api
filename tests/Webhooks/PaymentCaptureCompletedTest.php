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

    if (!defined('TABLE_PAYPAL')) {
        define('TABLE_PAYPAL', 'paypal');
    }

    if (!function_exists('zen_db_input')) {
        function zen_db_input(string $string): string
        {
            return addslashes($string);
        }
    }

    if (!function_exists('zen_update_orders_history')) {
        function zen_update_orders_history(int $order_id, string $message, string $webhook = '', int $status = -1, int $customer_notified = 0): void
        {
            global $orders_history_updates;
            $orders_history_updates[] = [
                'order_id' => $order_id,
                'message' => $message,
                'webhook' => $webhook,
                'status' => $status,
                'customer_notified' => $customer_notified,
            ];
        }
    }

    class PaymentCaptureTestResult
    {
        public bool $EOF = true;
        public array $fields = [];

        /** @var array<int,array<string,mixed>> */
        private array $rows = [];

        private int $index = 0;

        public function __construct($rows)
        {
            if (!is_array($rows)) {
                $rows = [];
            }

            if ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1)) {
                $rows = [$rows];
            }

            $this->rows = array_values($rows);
            $this->index = 0;
            $this->refresh();
        }

        public function RecordCount(): int
        {
            return count($this->rows);
        }

        public function MoveNext(): void
        {
            if ($this->EOF) {
                return;
            }

            $this->index++;
            $this->refresh();
        }

        private function refresh(): void
        {
            if ($this->index >= count($this->rows)) {
                $this->EOF = true;
                $this->fields = [];
                return;
            }

            $this->EOF = false;
            $this->fields = $this->rows[$this->index];
        }
    }

    class PaymentCaptureTestDb
    {
        /** @var array<string,array<int,array<string,mixed>>> */
        public array $tables = [];

        public function prepare_input(string $string): string
        {
            return addslashes($string);
        }

        public function Execute(string $sql): PaymentCaptureTestResult
        {
            return $this->ExecuteNoCache($sql);
        }

        public function ExecuteNoCache(string $sql): PaymentCaptureTestResult
        {
            $sql = trim($sql);

            // Handle SELECT queries for capture existence check
            // Looking for: SELECT txn_id FROM paypal WHERE txn_id = 'CAPTURE-123' AND order_id = 100 AND txn_type = 'CAPTURE' LIMIT 1
            if (preg_match("/SELECT.*FROM\\s+" . preg_quote(TABLE_PAYPAL, '/') . "\\s+WHERE\\s+txn_id\\s*=\\s*'([^']+)'.*AND\\s+order_id\\s*=\\s*(\\d+).*AND\\s+txn_type\\s*=\\s*'CAPTURE'/is", $sql, $matches)) {
                $txnId = stripslashes($matches[1]);
                $orderId = (int)$matches[2];
                $row = $this->getCaptureByTxnId($txnId, $orderId);
                return new PaymentCaptureTestResult($row === null ? [] : $row);
            }

            // Handle SELECT queries for order lookup by txn_id
            if (preg_match("/SELECT.*FROM\\s+" . preg_quote(TABLE_PAYPAL, '/') . "\\s+WHERE\\s+txn_id\\s*=\\s*'([^']+)'/i", $sql, $matches)) {
                $txnId = stripslashes($matches[1]);
                $row = $this->getTransactionByTxnId($txnId);
                return new PaymentCaptureTestResult($row === null ? [] : $row);
            }

            throw new \RuntimeException('Unhandled SQL: ' . $sql);
        }

        public function getCaptureByTxnId(string $txnId, int $orderId): ?array
        {
            foreach ($this->tables[TABLE_PAYPAL] ?? [] as $row) {
                if (($row['txn_id'] ?? '') === $txnId 
                    && ($row['txn_type'] ?? '') === 'CAPTURE'
                    && ($row['order_id'] ?? 0) === $orderId) {
                    return $row;
                }
            }

            return null;
        }

        public function getTransactionByTxnId(string $txnId): ?array
        {
            foreach ($this->tables[TABLE_PAYPAL] ?? [] as $row) {
                if (($row['txn_id'] ?? '') === $txnId) {
                    return $row;
                }
            }

            return null;
        }

        public function addTransaction(array $transaction): void
        {
            $tableData = &$this->tables[TABLE_PAYPAL];
            if (!is_array($tableData)) {
                $tableData = [];
            }

            $tableData[] = $transaction;
        }
    }
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
    use PHPUnit\Framework\TestCase;

    final class PaymentCaptureCompletedTest extends TestCase
    {
        private \PaymentCaptureTestDb $db;
        private array $orders_history_updates;

        protected function setUp(): void
        {
            parent::setUp();

            date_default_timezone_set('UTC');

            $this->db = new \PaymentCaptureTestDb();
            $this->orders_history_updates = [];

            global $db, $orders_history_updates;
            $db = $this->db;
            $orders_history_updates = &$this->orders_history_updates;
        }

        public function testCheckCaptureExistsReturnsTrueWhenCaptureExists(): void
        {
            // Add an existing capture transaction
            $this->db->addTransaction([
                'txn_id' => 'CAPTURE-123',
                'order_id' => 100,
                'txn_type' => 'CAPTURE',
                'payment_status' => 'COMPLETED',
            ]);

            // Use reflection to test the protected method
            $handler = $this->createPaymentCaptureCompletedHandler();
            $method = new \ReflectionMethod($handler, 'checkCaptureExists');
            $method->setAccessible(true);

            $result = $method->invoke($handler, 'CAPTURE-123', 100);

            $this->assertTrue($result, 'checkCaptureExists should return true when capture exists');
        }

        public function testCheckCaptureExistsReturnsFalseWhenCaptureDoesNotExist(): void
        {
            // Use reflection to test the protected method
            $handler = $this->createPaymentCaptureCompletedHandler();
            $method = new \ReflectionMethod($handler, 'checkCaptureExists');
            $method->setAccessible(true);

            $result = $method->invoke($handler, 'CAPTURE-999', 100);

            $this->assertFalse($result, 'checkCaptureExists should return false when capture does not exist');
        }

        public function testCheckCaptureExistsOnlyMatchesCaptureType(): void
        {
            // Add a transaction that's not a CAPTURE
            $this->db->addTransaction([
                'txn_id' => 'AUTH-123',
                'order_id' => 100,
                'txn_type' => 'AUTHORIZE',
                'payment_status' => 'COMPLETED',
            ]);

            // Use reflection to test the protected method
            $handler = $this->createPaymentCaptureCompletedHandler();
            $method = new \ReflectionMethod($handler, 'checkCaptureExists');
            $method->setAccessible(true);

            $result = $method->invoke($handler, 'AUTH-123', 100);

            $this->assertFalse($result, 'checkCaptureExists should return false for non-CAPTURE transactions');
        }

        private function createPaymentCaptureCompletedHandler(): object
        {
            // Create a minimal mock to instantiate the handler
            $baseDir = dirname(__DIR__, 2);
            require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookObject.php';
            require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookHandlerContract.php';
            require_once $baseDir . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/Events/PaymentCaptureCompleted.php';

            $webhookData = [
                'id' => 'WH-TEST',
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'amount' => [
                        'value' => '10.00',
                        'currency_code' => 'USD',
                    ],
                    'status' => 'COMPLETED',
                    'final_capture' => true,
                ],
                'summary' => 'Payment completed',
            ];

            $webhook = new \PayPalRestful\Webhooks\WebhookObject('POST', [], json_encode($webhookData));
            return new \PayPalRestful\Webhooks\Events\PaymentCaptureCompleted($webhook);
        }
    }
}
