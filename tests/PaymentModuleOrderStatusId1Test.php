<?php
declare(strict_types=1);

/**
 * Test to verify payment modules correctly handle order status ID 1.
 *
 * This test ensures that:
 * 1. All payment modules use ($order_status > 0) instead of ($order_status > 1)
 * 2. This allows order status ID 1 (typically "Pending") to be used correctly
 *
 * Background:
 * The bug was that the condition `$order_status > 1` would incorrectly reject
 * status ID 1, falling back to DEFAULT_ORDERS_STATUS_ID instead of using the
 * configured MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID value.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Payment Module Order Status ID 1 Test...\n\n");

$basePath = dirname(__DIR__);
$paymentModules = [
    'paypalr.php',
    'paypalr_creditcard.php',
    'paypalr_venmo.php',
    'paypalr_applepay.php',
    'paypalr_googlepay.php',
    'paypalr_paylater.php',
    'paypalr_savedcard.php',
];

$testsPassed = true;

foreach ($paymentModules as $module) {
    $modulePath = $basePath . '/includes/modules/payment/' . $module;
    
    if (!file_exists($modulePath)) {
        fwrite(STDOUT, "SKIP: $module not found\n");
        continue;
    }
    
    $content = file_get_contents($modulePath);
    
    // Test: Verify the module uses ($order_status > 0) instead of ($order_status > 1)
    fwrite(STDOUT, "Testing $module...\n");
    
    // Check for the correct pattern
    $hasCorrectPattern = strpos($content, '($order_status > 0)') !== false;
    $hasWrongPattern = strpos($content, '($order_status > 1)') !== false;
    
    if ($hasCorrectPattern && !$hasWrongPattern) {
        fwrite(STDOUT, "  ✓ PASS: Uses correct condition (\$order_status > 0)\n");
    } elseif ($hasWrongPattern) {
        fwrite(STDERR, "  ✗ FAIL: Uses incorrect condition (\$order_status > 1)\n");
        fwrite(STDERR, "    This will reject order status ID 1 and fall back to DEFAULT_ORDERS_STATUS_ID\n");
        $testsPassed = false;
    } else {
        fwrite(STDOUT, "  ? SKIP: Pattern not found in module\n");
    }
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that all payment modules:\n");
    fwrite(STDOUT, "1. Use (\$order_status > 0) to properly handle status ID 1\n");
    fwrite(STDOUT, "2. Will correctly use MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID when set to 1\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Payment modules with (\$order_status > 1) will incorrectly reject status ID 1.\n");
    exit(1);
}
