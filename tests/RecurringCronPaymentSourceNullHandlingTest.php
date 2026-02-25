<?php
declare(strict_types=1);

/**
 * Test to verify that the payment_source null handling fix prevents fatal errors in cron context.
 *
 * This test ensures that:
 * 1. recordPayPalOrderDetails() checks for null/empty payment_source before processing
 * 2. Logger constructor handles cron context with proper filename
 *
 * Background:
 * When the cron processed recurring payments, a PHP Fatal error occurred:
 * "array_key_first(): Argument #1 ($array) must be of type array, null given"
 * This happened because the payment was processed via a different pathway that didn't
 * populate the orderInfo['payment_source'] property.
 *
 * Also, the log filename was incorrectly generated as "paypalac-c-na-nana-*.log" because
 * session customer info wasn't available during cron execution.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Cron Payment Source Null Handling Test...\n\n");

$basePath = dirname(__DIR__);
$paypalCommonFile = $basePath . '/includes/modules/payment/paypal/paypal_common.php';
$loggerFile = $basePath . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';

if (!file_exists($paypalCommonFile)) {
    fwrite(STDERR, "✗ paypal_common.php not found\n\n");
    exit(1);
}

if (!file_exists($loggerFile)) {
    fwrite(STDERR, "✗ Logger.php not found\n\n");
    exit(1);
}

$paypalCommonContent = file_get_contents($paypalCommonFile);
$loggerContent = file_get_contents($loggerFile);
$testsPassed = true;

// Test 1: Verify recordPayPalOrderDetails checks for null/empty payment_source
fwrite(STDOUT, "Test 1: Verify recordPayPalOrderDetails() checks for null/empty payment_source...\n");

if (strpos($paypalCommonContent, "orderInfo['payment_source'] ?? []") !== false) {
    fwrite(STDOUT, "✓ PASS: recordPayPalOrderDetails() uses null coalescing for payment_source\n");
} else {
    fwrite(STDERR, "✗ FAIL: recordPayPalOrderDetails() should use null coalescing for payment_source\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify early return when payment_source is empty
fwrite(STDOUT, "Test 2: Verify early return when payment_source is empty...\n");

if (preg_match('/if\s*\(\s*empty\s*\(\s*\$payment_source_data\s*\).*?\)\s*\{\s*return;/s', $paypalCommonContent)) {
    fwrite(STDOUT, "✓ PASS: recordPayPalOrderDetails() returns early when payment_source is empty\n");
} else {
    fwrite(STDERR, "✗ FAIL: recordPayPalOrderDetails() should return early when payment_source is empty\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify is_array check for payment_source
fwrite(STDOUT, "Test 3: Verify is_array check for payment_source...\n");

if (strpos($paypalCommonContent, '!is_array($payment_source_data)') !== false) {
    fwrite(STDOUT, "✓ PASS: recordPayPalOrderDetails() checks if payment_source is an array\n");
} else {
    fwrite(STDERR, "✗ FAIL: recordPayPalOrderDetails() should check if payment_source is an array\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify Logger handles cron context
fwrite(STDOUT, "Test 4: Verify Logger handles cron context...\n");

if (strpos($loggerContent, "in_cron") !== false) {
    fwrite(STDOUT, "✓ PASS: Logger checks for in_cron session variable\n");
} else {
    fwrite(STDERR, "✗ FAIL: Logger should check for in_cron session variable\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify Logger uses 'cron' suffix for cron context
fwrite(STDOUT, "Test 5: Verify Logger uses 'cron' suffix for cron context...\n");

if (preg_match("/in_cron.*?logfile_suffix\s*=\s*'cron'/s", $loggerContent)) {
    fwrite(STDOUT, "✓ PASS: Logger uses 'cron' suffix for cron context\n");
} else {
    fwrite(STDERR, "✗ FAIL: Logger should use 'cron' suffix for cron context\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 6: Verify cron check comes before customer session check
fwrite(STDOUT, "Test 6: Verify cron check comes before customer session check...\n");

$cronCheckPos = strpos($loggerContent, "in_cron");
$customerCheckPos = strpos($loggerContent, "customer_id");
if ($cronCheckPos !== false && $customerCheckPos !== false && $cronCheckPos < $customerCheckPos) {
    fwrite(STDOUT, "✓ PASS: Cron check is evaluated before customer session check\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron check should be evaluated before customer session check\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that recurring cron payments:\n");
    fwrite(STDOUT, "1. Check for null/empty payment_source before processing\n");
    fwrite(STDOUT, "2. Return early when payment_source is empty\n");
    fwrite(STDOUT, "3. Validate payment_source is an array\n");
    fwrite(STDOUT, "4. Logger detects cron context\n");
    fwrite(STDOUT, "5. Logger uses proper 'cron' suffix for filenames\n");
    fwrite(STDOUT, "6. Cron check is prioritized over customer session check\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Recurring cron payments may still have issues.\n");
    exit(1);
}
