<?php
/**
 * Test that verifies all payment modules explicitly set $order->info['order_status']
 * when a payment IS captured (STATUS_CAPTURED / STATUS_COMPLETED).
 *
 * Bug: On Zen Cart zc158a and earlier, the order processing does not pick up
 * $this->order_status from the payment module. The code must explicitly set
 * $order->info['order_status'] for the status to take effect. The modules only
 * did this for non-captured (pending) payments, causing captured payments to
 * fall back to DEFAULT_ORDERS_STATUS_ID instead of the configured PAID status.
 *
 * Fix: Add an else branch that sets $order->info['order_status'] = $this->order_status
 * when the payment is successfully captured.
 */

$testPassed = true;
$errors = [];

// -----
// Wallet-style modules: check for the pattern
//   if ($payment_status !== ...STATUS_CAPTURED) {
//       ...
//   } else {
//       $order->info['order_status'] = $this->order_status;
//   }
//
$walletModules = [
    'paypalac.php' => 'STATUS_CAPTURED',
    'paypalac_venmo.php' => 'STATUS_CAPTURED',
    'paypalac_paylater.php' => 'STATUS_CAPTURED',
    'paypalac_googlepay.php' => 'STATUS_CAPTURED',
    'paypalac_applepay.php' => 'STATUS_CAPTURED',
    'paypalac_savedcard.php' => 'STATUS_CAPTURED',
];

$basePath = dirname(__DIR__) . '/includes/modules/payment/';

echo "Testing that all payment modules explicitly set \$order->info['order_status'] for captured payments...\n\n";

foreach ($walletModules as $file => $statusConst) {
    $filePath = $basePath . $file;
    if (!file_exists($filePath)) {
        $testPassed = false;
        $errors[] = "File not found: $file";
        continue;
    }

    $content = file_get_contents($filePath);

    // Verify the else branch exists with the order_status assignment.
    // Look for the closing brace of the if-block followed by the else branch.
    $pattern = '/\}\s*else\s*\{\s*\$order->info\[.order_status.\]\s*=\s*\$this->order_status;\s*\}/s';

    if (preg_match($pattern, $content)) {
        echo "✓ $file: else branch sets \$order->info['order_status'] for captured payments\n";
    } else {
        $testPassed = false;
        $errors[] = "$file: Missing else branch to set \$order->info['order_status'] when payment is captured";
    }
}

echo "\n";

// -----
// Credit card module: uses $payment['status'] !== STATUS_COMPLETED instead
//
$ccFile = 'paypalac_creditcard.php';
$ccPath = $basePath . $ccFile;
if (!file_exists($ccPath)) {
    $testPassed = false;
    $errors[] = "File not found: $ccFile";
} else {
    $content = file_get_contents($ccPath);

    // Check that the else branch of the STATUS_COMPLETED check assigns order_status
    $pattern = '/\}\s*else\s*\{\s*\$order->info\[.order_status.\]\s*=\s*\$this->order_status;/s';

    if (preg_match($pattern, $content)) {
        echo "✓ $ccFile: else branch sets \$order->info['order_status'] for completed payments\n";
    } else {
        $testPassed = false;
        $errors[] = "$ccFile: Missing \$order->info['order_status'] assignment in else branch for completed payments";
    }
}

echo "\n";

// -----
// Logic test: Simulate the before_process order status assignment
//
echo "Testing order status assignment logic...\n\n";

define('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID', 5);
define('MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID', 3);
define('DEFAULT_ORDERS_STATUS_ID', 1);

/**
 * Simulates before_process() order status assignment with the fix applied.
 *
 * @param int $constructor_order_status  The order_status set in __construct()
 * @param string $payment_status         The derived payment status
 * @param int $initial_order_status      The $order->info['order_status'] before before_process()
 * @return int  The final $order->info['order_status']
 */
function simulateBeforeProcess(int $constructor_order_status, string $payment_status, int $initial_order_status): int
{
    $order_info_order_status = $initial_order_status;
    $this_order_status = $constructor_order_status;

    if ($payment_status !== 'CAPTURED') {
        $this_order_status = (int)MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID;
        $order_info_order_status = $this_order_status;
    } else {
        $order_info_order_status = $this_order_status;
    }

    return $order_info_order_status;
}

// Test: Captured payment should use the configured PAID status, not the default
$result = simulateBeforeProcess(
    (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID,
    'CAPTURED',
    (int)DEFAULT_ORDERS_STATUS_ID
);
if ($result === (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID) {
    echo "✓ Captured payment: order status set to configured PAID status ($result), not default (" . DEFAULT_ORDERS_STATUS_ID . ")\n";
} else {
    $testPassed = false;
    $errors[] = "Captured payment should use ORDER_STATUS_ID (" . MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID . "), got: $result";
}

// Test: Non-captured payment should use the PENDING status
$result = simulateBeforeProcess(
    (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID,
    'APPROVED',
    (int)DEFAULT_ORDERS_STATUS_ID
);
if ($result === (int)MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID) {
    echo "✓ Approved (authorized) payment: order status set to pending ($result)\n";
} else {
    $testPassed = false;
    $errors[] = "Approved payment should use ORDER_PENDING_STATUS_ID (" . MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID . "), got: $result";
}

// Test: Captured payment should NOT fall through to default even when default differs
$result = simulateBeforeProcess(
    (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID,
    'CAPTURED',
    99  // Some arbitrary initial status
);
if ($result === (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID) {
    echo "✓ Captured payment: overrides initial order status (99) with configured PAID status ($result)\n";
} else {
    $testPassed = false;
    $errors[] = "Captured payment should override initial status with ORDER_STATUS_ID (" . MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID . "), got: $result";
}

echo "\n";

// Summary
if ($testPassed) {
    echo "All captured payment order status tests passed! ✓\n";
    echo "\nThe fix ensures that \$order->info['order_status'] is explicitly set in all\n";
    echo "payment modules for both captured and non-captured payments, preventing\n";
    echo "fallback to DEFAULT_ORDERS_STATUS_ID on older Zen Cart versions.\n";
    exit(0);
} else {
    echo "Tests FAILED:\n";
    foreach ($errors as $error) {
        fwrite(STDERR, "  ✗ $error\n");
    }
    exit(1);
}
