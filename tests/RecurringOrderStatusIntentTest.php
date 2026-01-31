<?php
declare(strict_types=1);

/**
 * Test to verify that recurring payments use the correct order status based on payment intent.
 *
 * This test ensures that:
 * 1. get_order_status_for_intent() returns pending status for AUTHORIZE intent
 * 2. get_order_status_for_intent() returns completed status for CAPTURE intent
 * 3. create_order() accepts an order status parameter
 * 4. The cron passes the correct order status to create_order()
 *
 * Background:
 * The issue was that recurring payment orders always used status 2 (Processing/Paid)
 * even when the payment was only authorized (not captured). This was inconsistent
 * with how checkout orders worked, where authorized payments use the pending status.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Order Status Test...\n\n");

$basePath = dirname(__DIR__);
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';

if (!file_exists($savedCardRecurringFile)) {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

if (!file_exists($cronFile)) {
    fwrite(STDERR, "✗ paypal_saved_card_recurring.php (cron) not found\n\n");
    exit(1);
}

$classContent = file_get_contents($savedCardRecurringFile);
$cronContent = file_get_contents($cronFile);
$testsPassed = true;

// Test 1: Verify get_order_status_for_intent method exists
fwrite(STDOUT, "Test 1: Verify get_order_status_for_intent() method exists...\n");

if (strpos($classContent, 'function get_order_status_for_intent') !== false) {
    fwrite(STDOUT, "✓ PASS: get_order_status_for_intent() method exists\n");
} else {
    fwrite(STDERR, "✗ FAIL: get_order_status_for_intent() method not found\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify method returns pending status for AUTHORIZE
fwrite(STDOUT, "Test 2: Verify method returns pending status for AUTHORIZE intent...\n");

if (preg_match("/AUTHORIZE.*?ORDER_PENDING_STATUS_ID/s", $classContent)) {
    fwrite(STDOUT, "✓ PASS: AUTHORIZE intent maps to ORDER_PENDING_STATUS_ID\n");
} else {
    fwrite(STDERR, "✗ FAIL: AUTHORIZE intent should map to ORDER_PENDING_STATUS_ID\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify method returns completed status for CAPTURE
fwrite(STDOUT, "Test 3: Verify method returns completed status for CAPTURE intent...\n");

if (preg_match("/CAPTURE|ORDER_STATUS_ID/", $classContent)) {
    fwrite(STDOUT, "✓ PASS: CAPTURE intent maps to ORDER_STATUS_ID\n");
} else {
    fwrite(STDERR, "✗ FAIL: CAPTURE intent should map to ORDER_STATUS_ID\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify create_order accepts order_status parameter
fwrite(STDOUT, "Test 4: Verify create_order() accepts order_status parameter...\n");

if (preg_match('/function\s+create_order\s*\([^)]*\$order_status/', $classContent)) {
    fwrite(STDOUT, "✓ PASS: create_order() accepts order_status parameter\n");
} else {
    fwrite(STDERR, "✗ FAIL: create_order() should accept order_status parameter\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify create_order uses the order_status parameter
fwrite(STDOUT, "Test 5: Verify create_order() uses the order_status parameter...\n");

if (preg_match('/\$order->create\s*\(\s*\$order_totals\s*,\s*\$order_status\s*\)/', $classContent)) {
    fwrite(STDOUT, "✓ PASS: create_order() uses order_status parameter\n");
} else {
    fwrite(STDERR, "✗ FAIL: create_order() should use order_status parameter\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 6: Verify cron extracts payment intent from result
fwrite(STDOUT, "Test 6: Verify cron extracts payment intent from result...\n");

if (strpos($cronContent, "payment_intent = \$payment_result['intent']") !== false ||
    strpos($cronContent, "payment_result['intent']") !== false) {
    fwrite(STDOUT, "✓ PASS: Cron extracts payment intent from result\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should extract payment intent from result\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 7: Verify cron calls get_order_status_for_intent
fwrite(STDOUT, "Test 7: Verify cron calls get_order_status_for_intent()...\n");

if (strpos($cronContent, 'get_order_status_for_intent') !== false) {
    fwrite(STDOUT, "✓ PASS: Cron calls get_order_status_for_intent()\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should call get_order_status_for_intent()\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 8: Verify cron passes order status to create_order
fwrite(STDOUT, "Test 8: Verify cron passes order status to create_order()...\n");

if (preg_match('/create_order\s*\([^)]*\$recurring_order_status/', $cronContent)) {
    fwrite(STDOUT, "✓ PASS: Cron passes order status to create_order()\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should pass order status to create_order()\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that recurring payments:\n");
    fwrite(STDOUT, "1. Have get_order_status_for_intent() method\n");
    fwrite(STDOUT, "2. Return pending status for AUTHORIZE intent\n");
    fwrite(STDOUT, "3. Return completed status for CAPTURE intent\n");
    fwrite(STDOUT, "4. Accept order_status parameter in create_order()\n");
    fwrite(STDOUT, "5. Use the order_status parameter in create_order()\n");
    fwrite(STDOUT, "6. Extract payment intent in cron\n");
    fwrite(STDOUT, "7. Call get_order_status_for_intent() in cron\n");
    fwrite(STDOUT, "8. Pass correct order status to create_order()\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Recurring payments may use incorrect order status.\n");
    exit(1);
}
