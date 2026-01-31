<?php
declare(strict_types=1);

/**
 * Test to verify that the recurring observer skips subscription creation for cron-generated orders.
 *
 * This test ensures that:
 * 1. The observer checks for $_SESSION['in_cron'] flag
 * 2. The observer checks for $_SESSION['automatic_subscription_order'] flag
 * 3. When either flag is set, the observer skips subscription creation
 *
 * Background:
 * The issue was that when the cron processed recurring payments, it would create new orders
 * via create_order(). This triggered the NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS
 * notification, which caused the observer to create duplicate subscriptions for each recurring payment.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Cron Skip Duplicate Subscription Test...\n\n");

$basePath = dirname(__DIR__);
$observerFile = $basePath . '/includes/classes/observers/auto.paypalrestful_recurring.php';

if (!file_exists($observerFile)) {
    fwrite(STDERR, "✗ auto.paypalrestful_recurring.php not found\n\n");
    exit(1);
}

$content = file_get_contents($observerFile);
$testsPassed = true;

// Test 1: Verify observer checks for in_cron flag
fwrite(STDOUT, "Test 1: Verify observer checks for \$_SESSION['in_cron'] flag...\n");

if (strpos($content, "\$_SESSION['in_cron']") !== false) {
    fwrite(STDOUT, "✓ PASS: Observer checks for \$_SESSION['in_cron'] flag\n");
} else {
    fwrite(STDERR, "✗ FAIL: Observer does not check for \$_SESSION['in_cron'] flag\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify observer checks for automatic_subscription_order flag
fwrite(STDOUT, "Test 2: Verify observer checks for \$_SESSION['automatic_subscription_order'] flag...\n");

if (strpos($content, "\$_SESSION['automatic_subscription_order']") !== false) {
    fwrite(STDOUT, "✓ PASS: Observer checks for \$_SESSION['automatic_subscription_order'] flag\n");
} else {
    fwrite(STDERR, "✗ FAIL: Observer does not check for \$_SESSION['automatic_subscription_order'] flag\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify the check happens early in the method (before order processing)
fwrite(STDOUT, "Test 3: Verify the cron check happens before subscription creation...\n");

// Find the position of the cron check and the subscription creation
$cronCheckPos = strpos($content, "in_cron");
$schedulePaymentPos = strpos($content, "schedule_payment");

if ($cronCheckPos !== false && $schedulePaymentPos !== false && $cronCheckPos < $schedulePaymentPos) {
    fwrite(STDOUT, "✓ PASS: Cron check occurs before subscription creation logic\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron check should occur before subscription creation\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify the observer returns early when in cron context
fwrite(STDOUT, "Test 4: Verify observer returns early when in cron context...\n");

// Check for a return statement after the cron check
if (preg_match('/in_cron.*?automatic_subscription_order.*?\{[^}]*return;/s', $content) ||
    preg_match('/automatic_subscription_order.*?in_cron.*?\{[^}]*return;/s', $content)) {
    fwrite(STDOUT, "✓ PASS: Observer returns early when cron flags are detected\n");
} else {
    fwrite(STDERR, "✗ FAIL: Observer should return early when cron flags are detected\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify the observer logs when skipping
fwrite(STDOUT, "Test 5: Verify observer logs skip reason...\n");

if (preg_match('/Skipping.*?recurring payment.*?cron|cron.*?automatic subscription/i', $content)) {
    fwrite(STDOUT, "✓ PASS: Observer logs when skipping cron-generated orders\n");
} else {
    fwrite(STDERR, "✗ FAIL: Observer should log when skipping cron-generated orders\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that the recurring observer:\n");
    fwrite(STDOUT, "1. Checks for \$_SESSION['in_cron'] flag\n");
    fwrite(STDOUT, "2. Checks for \$_SESSION['automatic_subscription_order'] flag\n");
    fwrite(STDOUT, "3. Performs the check before subscription creation logic\n");
    fwrite(STDOUT, "4. Returns early when cron flags are detected\n");
    fwrite(STDOUT, "5. Logs when skipping cron-generated orders\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "The observer may create duplicate subscriptions for cron-generated orders.\n");
    exit(1);
}
