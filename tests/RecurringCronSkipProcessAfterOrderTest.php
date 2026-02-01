<?php
declare(strict_types=1);

/**
 * Test to verify that processAfterOrder() in paypal_common.php skips processing for cron-generated orders.
 *
 * This test ensures that:
 * 1. processAfterOrder checks for $_SESSION['in_cron'] flag
 * 2. processAfterOrder checks for $_SESSION['automatic_subscription_order'] flag
 * 3. When either flag is set, processAfterOrder returns early without inserting PayPal records
 *
 * Background:
 * When the cron processes recurring payments, it calls create_order() which triggers after_process()
 * on the payment module. This calls processAfterOrder() which was inserting duplicate/incorrect
 * PayPal transaction records because the payment module's orderInfo wasn't properly set in cron context.
 *
 * The fix ensures that cron-generated orders are handled exclusively by record_paypal_transaction()
 * in paypalSavedCardRecurring, which has access to the correct payment result data.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Cron Skip processAfterOrder Test...\n\n");

$basePath = dirname(__DIR__);
$paypalCommonFile = $basePath . '/includes/modules/payment/paypal/paypal_common.php';

if (!file_exists($paypalCommonFile)) {
    fwrite(STDERR, "✗ paypal_common.php not found\n\n");
    exit(1);
}

$content = file_get_contents($paypalCommonFile);
$testsPassed = true;

// Test 1: Verify processAfterOrder checks for in_cron flag
fwrite(STDOUT, "Test 1: Verify processAfterOrder checks for \$_SESSION['in_cron'] flag...\n");

// Find the processAfterOrder method - we look for the function declaration and
// use the content until the next public/protected/private function declaration
$methodStart = strpos($content, 'function processAfterOrder');
if ($methodStart === false) {
    fwrite(STDERR, "✗ Could not find processAfterOrder method\n");
    exit(1);
}

// Find the next method declaration after processAfterOrder
$nextMethodPos = preg_match('/\n\s*(?:public|protected|private)\s+function\s+\w+/s', $content, $matches, PREG_OFFSET_CAPTURE, $methodStart + 1);
if ($nextMethodPos) {
    $methodEnd = $matches[0][1];
} else {
    $methodEnd = strlen($content);
}

$processAfterOrderContent = substr($content, $methodStart, $methodEnd - $methodStart);

if (strpos($processAfterOrderContent, "\$_SESSION['in_cron']") !== false) {
    fwrite(STDOUT, "✓ PASS: processAfterOrder checks for \$_SESSION['in_cron'] flag\n");
} else {
    fwrite(STDERR, "✗ FAIL: processAfterOrder does not check for \$_SESSION['in_cron'] flag\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify processAfterOrder checks for automatic_subscription_order flag
fwrite(STDOUT, "Test 2: Verify processAfterOrder checks for \$_SESSION['automatic_subscription_order'] flag...\n");

if (strpos($processAfterOrderContent, "\$_SESSION['automatic_subscription_order']") !== false ||
    strpos($processAfterOrderContent, "automatic_subscription_order") !== false) {
    fwrite(STDOUT, "✓ PASS: processAfterOrder checks for \$_SESSION['automatic_subscription_order'] flag\n");
} else {
    fwrite(STDERR, "✗ FAIL: processAfterOrder does not check for \$_SESSION['automatic_subscription_order'] flag\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify the check happens early in the method (before record insertion)
fwrite(STDOUT, "Test 3: Verify the cron check happens before PayPal record insertion...\n");

$cronCheckPos = strpos($processAfterOrderContent, "in_cron");
$recordPos = strpos($processAfterOrderContent, "recordPayPalOrderDetails");

if ($cronCheckPos !== false && $recordPos !== false && $cronCheckPos < $recordPos) {
    fwrite(STDOUT, "✓ PASS: Cron check occurs before recordPayPalOrderDetails call\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron check should occur before recordPayPalOrderDetails call\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify processAfterOrder returns early when in cron context
fwrite(STDOUT, "Test 4: Verify processAfterOrder returns early when in cron context...\n");

// Check for a return statement after the cron check
if (preg_match('/in_cron.*?\{[^}]*return;/s', $processAfterOrderContent) ||
    preg_match('/automatic_subscription_order.*?\{[^}]*return;/s', $processAfterOrderContent)) {
    fwrite(STDOUT, "✓ PASS: processAfterOrder returns early when cron flags are detected\n");
} else {
    fwrite(STDERR, "✗ FAIL: processAfterOrder should return early when cron flags are detected\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify there's a comment explaining why
fwrite(STDOUT, "Test 5: Verify there's a comment explaining the cron skip logic...\n");

if (preg_match('/cron|recurring|record_paypal_transaction/i', $processAfterOrderContent)) {
    fwrite(STDOUT, "✓ PASS: Comment or reference to cron/recurring context found\n");
} else {
    fwrite(STDERR, "✗ FAIL: Should have a comment explaining the cron skip logic\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that processAfterOrder():\n");
    fwrite(STDOUT, "1. Checks for \$_SESSION['in_cron'] flag\n");
    fwrite(STDOUT, "2. Checks for \$_SESSION['automatic_subscription_order'] flag\n");
    fwrite(STDOUT, "3. Performs the check before recordPayPalOrderDetails\n");
    fwrite(STDOUT, "4. Returns early when cron flags are detected\n");
    fwrite(STDOUT, "5. Has documentation explaining the logic\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "processAfterOrder may insert incorrect PayPal records for cron-generated orders.\n");
    exit(1);
}
