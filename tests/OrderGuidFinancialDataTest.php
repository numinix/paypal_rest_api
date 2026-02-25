<?php
/**
 * Test: Order GUID includes financial data and stays within PayPal's character limit
 *
 * This test validates that PayPalCommon::createOrderGuid():
 * 1. Produces a fixed-length UUID-like GUID (36 chars) that fits within
 *    PayPal's PayPal-Request-Id header limit
 * 2. Changes when the order total changes (e.g. store credit applied)
 * 3. Changes when store credit session data changes
 * 4. Changes when wallet payload changes
 * 5. Remains stable when inputs don't change
 *
 * Background:
 * - The GUID is used as the PayPal-Request-Id header for idempotency
 * - PayPal limits this header to ~64 characters
 * - One-page checkout can change order totals (e.g. applying store credit)
 *   without changing cart contents, requiring a new PayPal order
 * - A previous fix tried appending a raw md5 financial hash, exceeding the limit
 * - This test ensures the GUID uses SHA-256 hashing into a UUID format (36 chars)
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

declare(strict_types=1);

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_FS_LOGS')) {
    define('DIR_FS_LOGS', sys_get_temp_dir());
}
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php';

class OrderGuidFinancialDataTest
{
    private array $testResults = [];

    private function makeOrder(float $total = 100.00, float $shipping = 5.00, float $tax = 8.00): object
    {
        return new class($total, $shipping, $tax) {
            public $products;
            public $info;
            public function __construct(float $total, float $shipping, float $tax)
            {
                $this->products = [
                    ['id' => 'SKU1', 'qty' => 1],
                    ['id' => 'SKU2', 'qty' => 2],
                ];
                $this->info = [
                    'total' => $total,
                    'shipping_cost' => $shipping,
                    'tax' => $tax,
                ];
            }
        };
    }

    private function resetSession(): void
    {
        $_SESSION = [
            'customer_id' => 12345,
            'cartID' => 'cart_abc123',
            'PayPalAdvancedCheckout' => [
                'CompletedOrders' => 0,
            ],
        ];
    }

    public function run(): void
    {
        echo "\n=== Order GUID Financial Data Test ===\n\n";

        $this->testGuidLength();
        $this->testGuidStability();
        $this->testGuidChangesWithTotal();
        $this->testGuidChangesWithStoreCredit();
        $this->testGuidChangesWithWalletPayload();
        $this->testGuidUuidFormat();

        $this->printResults();
    }

    private function testGuidLength(): void
    {
        echo "Test 1: GUID is exactly 36 characters (UUID format)\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});
        $order = $this->makeOrder();

        $guid = $common->createOrderGuid($order, 'card');
        $len = strlen($guid);
        $passed = ($len === 36);

        echo "  GUID: $guid\n";
        echo "  Length: $len\n";
        echo ($passed ? "  ✓ GUID is 36 characters\n" : "  ✗ GUID is NOT 36 characters (got $len)\n");

        $this->testResults[] = ['name' => 'GUID is 36 chars', 'passed' => $passed];
        echo "\n";
    }

    private function testGuidStability(): void
    {
        echo "Test 2: GUID is stable when inputs don't change\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});
        $order = $this->makeOrder();

        $guid1 = $common->createOrderGuid($order, 'card');
        $guid2 = $common->createOrderGuid($order, 'card');
        $passed = ($guid1 === $guid2);

        echo ($passed ? "  ✓ Same inputs produce same GUID\n" : "  ✗ Same inputs produce different GUIDs\n");

        $this->testResults[] = ['name' => 'GUID stability', 'passed' => $passed];
        echo "\n";
    }

    private function testGuidChangesWithTotal(): void
    {
        echo "Test 3: GUID changes when order total changes\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});

        $order1 = $this->makeOrder(100.00);
        $guid1 = $common->createOrderGuid($order1, 'card');

        $order2 = $this->makeOrder(90.00);  // e.g. store credit applied, reducing total
        $guid2 = $common->createOrderGuid($order2, 'card');

        $passed = ($guid1 !== $guid2);

        echo "  GUID (total=100): $guid1\n";
        echo "  GUID (total=90):  $guid2\n";
        echo ($passed ? "  ✓ GUID changed when total changed\n" : "  ✗ GUID did NOT change when total changed\n");

        $this->testResults[] = ['name' => 'GUID changes with total', 'passed' => $passed];
        echo "\n";
    }

    private function testGuidChangesWithStoreCredit(): void
    {
        echo "Test 4: GUID changes when store credit session data changes\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});
        $order = $this->makeOrder();

        $guid_no_credit = $common->createOrderGuid($order, 'card');

        $_SESSION['storecredit'] = 10.00;
        $guid_with_credit = $common->createOrderGuid($order, 'card');

        $_SESSION['storecredit'] = 20.00;
        $guid_different_credit = $common->createOrderGuid($order, 'card');

        $passed = ($guid_no_credit !== $guid_with_credit) && ($guid_with_credit !== $guid_different_credit);

        echo "  GUID (no credit):    $guid_no_credit\n";
        echo "  GUID (credit=10):    $guid_with_credit\n";
        echo "  GUID (credit=20):    $guid_different_credit\n";
        echo ($passed ? "  ✓ GUID changed with store credit changes\n" : "  ✗ GUID did NOT change with store credit changes\n");

        $this->testResults[] = ['name' => 'GUID changes with store credit', 'passed' => $passed];
        echo "\n";
    }

    private function testGuidChangesWithWalletPayload(): void
    {
        echo "Test 5: GUID changes when wallet payload changes\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});
        $order = $this->makeOrder();

        $guid_no_payload = $common->createOrderGuid($order, 'apple_pay');

        $_SESSION['PayPalAdvancedCheckout']['WalletPayload']['apple_pay'] = ['token' => 'token-123'];
        $guid_with_payload = $common->createOrderGuid($order, 'apple_pay');

        $_SESSION['PayPalAdvancedCheckout']['WalletPayload']['apple_pay'] = ['token' => 'token-456'];
        $guid_different_payload = $common->createOrderGuid($order, 'apple_pay');

        $passed = ($guid_no_payload !== $guid_with_payload) && ($guid_with_payload !== $guid_different_payload);

        echo "  GUID (no payload):        $guid_no_payload\n";
        echo "  GUID (token=123):         $guid_with_payload\n";
        echo "  GUID (token=456):         $guid_different_payload\n";
        echo ($passed ? "  ✓ GUID changed with wallet payload changes\n" : "  ✗ GUID did NOT change with wallet payload changes\n");

        $this->testResults[] = ['name' => 'GUID changes with wallet payload', 'passed' => $passed];
        echo "\n";
    }

    private function testGuidUuidFormat(): void
    {
        echo "Test 6: GUID matches UUID-like format (8-4-4-4-12)\n";
        $this->resetSession();
        $common = new PayPalCommon(new class {});
        $order = $this->makeOrder();

        $guid = $common->createOrderGuid($order, 'card');
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        $passed = (bool)preg_match($pattern, $guid);

        echo "  GUID: $guid\n";
        echo ($passed ? "  ✓ GUID matches UUID format\n" : "  ✗ GUID does NOT match UUID format\n");

        $this->testResults[] = ['name' => 'GUID UUID format', 'passed' => $passed];
        echo "\n";
    }

    private function printResults(): void
    {
        echo "=== Test Results ===\n";
        $total = count($this->testResults);
        $passed = 0;
        foreach ($this->testResults as $r) {
            $status = $r['passed'] ? '✓ PASS' : '✗ FAIL';
            echo "{$status}: {$r['name']}\n";
            if ($r['passed']) $passed++;
        }
        echo "\nSummary: {$passed}/{$total} tests passed\n";
        if ($passed === $total) {
            echo "\n✓ All tests PASSED!\n";
            exit(0);
        } else {
            echo "\n✗ Some tests FAILED!\n";
            exit(1);
        }
    }
}

// Run the test
if (PHP_SAPI === 'cli') {
    try {
        $test = new OrderGuidFinancialDataTest();
        $test->run();
    } catch (Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}
