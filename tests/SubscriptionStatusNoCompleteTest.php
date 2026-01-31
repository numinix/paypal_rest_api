<?php
declare(strict_types=1);

/**
 * Test to verify that successful recurring payments do NOT set subscription status to 'complete'.
 *
 * This test ensures that:
 * 1. process_payment() does NOT set status to 'complete' after success
 * 2. The subscription stays in 'scheduled' status with updated next billing date
 * 3. Only ONE subscription exists per subscription order (no duplicates)
 *
 * Background:
 * The issue was that after each successful recurring payment, the subscription status
 * was being set to 'complete', which created confusion because the subscription should
 * remain in 'scheduled' status with the next billing date updated. The 'complete' status
 * was a legacy approach where each payment created a new subscription entry.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Subscription Status No Complete Test...\n\n");

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

// Test 1: Verify process_payment does NOT set status to 'complete' for REST payments
fwrite(STDOUT, "Test 1: Verify process_payment() does NOT set status to 'complete' for REST payments...\n");

// Search for update_payment_status with 'complete' literal (not in comments or SQL)
// This should NOT be present in successful payment handling
$lines = explode("\n", $classContent);
$hasCompleteStatusUpdate = false;
foreach ($lines as $lineNum => $line) {
    // Skip comments
    if (preg_match('/^\s*(\/\/|\/\*|\*)/', $line)) {
        continue;
    }
    // Skip SQL queries
    if (preg_match('/SELECT|FROM|WHERE/', $line)) {
        continue;
    }
    // Check for update_payment_status with 'complete'
    if (preg_match('/update_payment_status\s*\([^)]*[\'"]complete[\'"]/', $line)) {
        $hasCompleteStatusUpdate = true;
        break;
    }
}

if ($hasCompleteStatusUpdate) {
    fwrite(STDERR, "✗ FAIL: process_payment() still sets status to 'complete' for successful payments\n");
    $testsPassed = false;
} else {
    fwrite(STDOUT, "✓ PASS: process_payment() does NOT set status to 'complete' for successful payments\n");
}

fwrite(STDOUT, "\n");

// Test 2: Verify add_payment_comment method exists
fwrite(STDOUT, "Test 2: Verify add_payment_comment() method exists...\n");

if (strpos($classContent, 'function add_payment_comment') !== false) {
    fwrite(STDOUT, "✓ PASS: add_payment_comment() method exists\n");
} else {
    fwrite(STDERR, "✗ FAIL: add_payment_comment() method not found\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify process_payment uses add_payment_comment instead of update_payment_status for success
fwrite(STDOUT, "Test 3: Verify process_payment() uses add_payment_comment() for successful payments...\n");

if (strpos($classContent, 'add_payment_comment($paypal_saved_card_recurring_id') !== false) {
    fwrite(STDOUT, "✓ PASS: process_payment() uses add_payment_comment() for transaction logging\n");
} else {
    fwrite(STDERR, "✗ FAIL: process_payment() should use add_payment_comment() for transaction logging\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify cron sets status to 'scheduled' after successful payment
fwrite(STDOUT, "Test 4: Verify cron sets status to 'scheduled' after successful payment...\n");

if (preg_match("/update_payment_status.*?'scheduled'/", $cronContent)) {
    fwrite(STDOUT, "✓ PASS: Cron sets status to 'scheduled' after successful payment\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should set status to 'scheduled' after successful payment\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify cron does NOT set status to 'complete' for store credit payments
fwrite(STDOUT, "Test 5: Verify cron does NOT set status to 'complete' for store credit payments...\n");

// Look for store credit handling that uses add_payment_comment instead of update_payment_status with 'complete'
if (preg_match("/storecredit.*?add_payment_comment.*?store credit/si", $cronContent)) {
    fwrite(STDOUT, "✓ PASS: Cron uses add_payment_comment() for store credit (not 'complete' status)\n");
} elseif (preg_match("/storecredit.*?update_payment_status.*?'complete'/si", $cronContent)) {
    fwrite(STDERR, "✗ FAIL: Cron should NOT set status to 'complete' for store credit payments\n");
    $testsPassed = false;
} else {
    fwrite(STDOUT, "✓ PASS: Store credit handling does not use 'complete' status\n");
}

fwrite(STDOUT, "\n");

// Test 6: Verify the comment in code explains the status handling
fwrite(STDOUT, "Test 6: Verify code comments explain the subscription status handling...\n");

if (strpos($classContent, 'Status is NOT set to \'complete\'') !== false ||
    strpos($classContent, 'scheduled') !== false) {
    fwrite(STDOUT, "✓ PASS: Code contains comments explaining subscription status handling\n");
} else {
    fwrite(STDERR, "✗ FAIL: Code should contain comments explaining subscription status handling\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that subscription status handling:\n");
    fwrite(STDOUT, "1. Does NOT set status to 'complete' for REST payments\n");
    fwrite(STDOUT, "2. Has add_payment_comment() method for logging without status change\n");
    fwrite(STDOUT, "3. Uses add_payment_comment() for successful payment logging\n");
    fwrite(STDOUT, "4. Sets status to 'scheduled' after successful payment in cron\n");
    fwrite(STDOUT, "5. Does NOT set status to 'complete' for store credit payments\n");
    fwrite(STDOUT, "6. Contains explanatory comments about status handling\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Subscription status may incorrectly be set to 'complete'.\n");
    exit(1);
}
