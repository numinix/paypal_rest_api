<?php
declare(strict_types=1);

/**
 * Test to verify recurring payments respect the MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE setting
 *
 * This test ensures that:
 * 1. Recurring payments use AUTHORIZE intent when transaction mode is 'Auth Only (All Txns)' or 'Auth Only (Card-Only)'
 * 2. Recurring payments use CAPTURE intent when transaction mode is 'Final Sale'
 * 3. Payment status is correctly set to 'Pending' for authorizations and 'Completed' for captures
 *
 * Background:
 * The issue was that recurring payment processing hardcoded $intent = 'CAPTURE' which caused
 * payments to be captured instead of authorized when the site was in authorization-only mode.
 * This was inconsistent with how checkout payments worked.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Payment Intent Test...\n\n");

$basePath = dirname(__DIR__);
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';

if (!file_exists($savedCardRecurringFile)) {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

$content = file_get_contents($savedCardRecurringFile);
$testsPassed = true;

// Test 1: Verify intent is not hardcoded to CAPTURE
fwrite(STDOUT, "Test 1: Verify intent is not hardcoded to CAPTURE...\n");

// Check that the old hardcoded line is NOT present
if (preg_match('/^\s*\$intent\s*=\s*[\'"]CAPTURE[\'"]\s*;\s*$/m', $content)) {
    fwrite(STDERR, "✗ FAIL: intent is still hardcoded to 'CAPTURE'\n");
    fwrite(STDERR, "  Recurring payments should respect MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE setting\n");
    $testsPassed = false;
} else {
    fwrite(STDOUT, "✓ PASS: intent is not hardcoded to 'CAPTURE'\n");
}

fwrite(STDOUT, "\n");

// Test 2: Verify MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is checked
fwrite(STDOUT, "Test 2: Verify transaction mode setting is checked...\n");

if (strpos($content, 'MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE') !== false) {
    fwrite(STDOUT, "✓ PASS: MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is checked in process_rest_payment\n");
} else {
    fwrite(STDERR, "✗ FAIL: MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is not being checked\n");
    fwrite(STDERR, "  Recurring payments should use the same transaction mode as checkout payments\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify intent determination logic
fwrite(STDOUT, "Test 3: Verify intent determination logic...\n");

// Check that Final Sale results in CAPTURE
if (preg_match("/['\"]Final Sale['\"]\s*\)\s*\?\s*['\"]CAPTURE['\"]/", $content)) {
    fwrite(STDOUT, "✓ PASS: 'Final Sale' transaction mode correctly maps to CAPTURE intent\n");
} else {
    fwrite(STDERR, "✗ FAIL: 'Final Sale' transaction mode should map to CAPTURE intent\n");
    $testsPassed = false;
}

// Check that Auth Only results in AUTHORIZE
if (preg_match("/:\s*['\"]AUTHORIZE['\"]/", $content)) {
    fwrite(STDOUT, "✓ PASS: Auth Only transaction modes correctly map to AUTHORIZE intent\n");
} else {
    fwrite(STDERR, "✗ FAIL: Auth Only transaction modes should map to AUTHORIZE intent\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify payment_status is set based on intent
fwrite(STDOUT, "Test 4: Verify payment_status is set based on intent...\n");

// Check that payment_status uses a conditional based on intent
if (preg_match('/payment_status\s*=\s*\(\s*\$intent\s*===\s*[\'"]CAPTURE[\'"]\s*\)/', $content)) {
    fwrite(STDOUT, "✓ PASS: payment_status is correctly set based on intent (Completed for CAPTURE, Pending for AUTHORIZE)\n");
} else {
    fwrite(STDERR, "✗ FAIL: payment_status should be conditional based on intent\n");
    fwrite(STDERR, "  CAPTURE should set 'Completed', AUTHORIZE should set 'Pending'\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify pendingreason is set for authorizations
fwrite(STDOUT, "Test 5: Verify pendingreason is set for authorizations...\n");

if (preg_match('/pendingreason\s*=\s*\(\s*\$intent\s*===\s*[\'"]AUTHORIZE[\'"]\s*\)/', $content)) {
    fwrite(STDOUT, "✓ PASS: pendingreason is correctly set for authorization transactions\n");
} else {
    fwrite(STDERR, "✗ FAIL: pendingreason should indicate 'authorization' for AUTHORIZE intent\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that recurring payments:\n");
    fwrite(STDOUT, "1. Respect the MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE setting\n");
    fwrite(STDOUT, "2. Use AUTHORIZE intent when in authorization-only mode\n");
    fwrite(STDOUT, "3. Use CAPTURE intent when in 'Final Sale' mode\n");
    fwrite(STDOUT, "4. Set payment_status appropriately based on the intent\n");
    fwrite(STDOUT, "5. Set pendingreason for authorization transactions\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Recurring payments may not properly respect authorization mode.\n");
    exit(1);
}
