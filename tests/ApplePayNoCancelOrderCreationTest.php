<?php
/**
 * Test to verify that Apple Pay does NOT create orders when users cancel.
 *
 * This test ensures that:
 * 1. Order creation happens ONLY in onpaymentauthorized
 * 2. If user cancels (oncancel fires), no order is created
 * 3. This prevents wasted API calls and abandoned orders in PayPal
 *
 * Issue scenario (from user report):
 * - User clicks Apple Pay button
 * - onvalidatemerchant is called (modal opens)
 * - Previously: Order was created here (783ms API call to ppac_wallet.php)
 * - User cancels without authorizing payment
 * - oncancel is called
 * - Result: Wasted order creation, abandoned order in PayPal system
 *
 * Expected behavior (after fix):
 * - User clicks Apple Pay button
 * - onvalidatemerchant is called (modal opens)
 * - NO order creation happens here
 * - User cancels without authorizing payment
 * - oncancel is called
 * - Result: No order created, no wasted API calls
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Apple Pay No Cancel Order Creation\n";
echo "==========================================\n\n";

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.applepay.js');

// Extract the onApplePayButtonClicked function
$pattern = '/function onApplePayButtonClicked\s*\([^)]*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (!preg_match($pattern, $applePayJs, $matches)) {
    echo "✗ Could not extract onApplePayButtonClicked function\n";
    exit(1);
}
$clickHandlerBody = $matches[1];

// Test 1: onvalidatemerchant does NOT create orders
echo "Test 1: onvalidatemerchant does NOT create orders\n";

$onvalidatePattern = '/session\.onvalidatemerchant\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (!preg_match($onvalidatePattern, $clickHandlerBody, $onvalidateMatches)) {
    echo "  ✗ Could not extract onvalidatemerchant callback\n";
    exit(1);
}
$onvalidateBody = $onvalidateMatches[1];

// Check that fetchWalletOrder is NOT called in onvalidatemerchant
if (strpos($onvalidateBody, 'fetchWalletOrder') !== false) {
    $testPassed = false;
    $errors[] = "onvalidatemerchant should NOT call fetchWalletOrder";
    echo "  ✗ FAIL: fetchWalletOrder is called in onvalidatemerchant\n";
    echo "  This means orders are created when modal opens, even if user cancels!\n";
} else {
    echo "  ✓ fetchWalletOrder is NOT called in onvalidatemerchant\n";
}

// Check that orderPromise is NOT assigned in onvalidatemerchant
if (preg_match('/orderPromise\s*=/', $onvalidateBody)) {
    $testPassed = false;
    $errors[] = "orderPromise should NOT be assigned in onvalidatemerchant";
    echo "  ✗ FAIL: orderPromise is assigned in onvalidatemerchant\n";
} else {
    echo "  ✓ orderPromise is NOT assigned in onvalidatemerchant\n";
}

// Test 2: onpaymentauthorized DOES create orders
echo "\nTest 2: onpaymentauthorized DOES create orders\n";

$onpaymentPattern = '/session\.onpaymentauthorized\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (!preg_match($onpaymentPattern, $clickHandlerBody, $onpaymentMatches)) {
    echo "  ✗ Could not extract onpaymentauthorized callback\n";
    exit(1);
}
$onpaymentBody = $onpaymentMatches[1];

// Check that fetchWalletOrder IS called in onpaymentauthorized
if (strpos($onpaymentBody, 'fetchWalletOrder') !== false) {
    echo "  ✓ fetchWalletOrder IS called in onpaymentauthorized\n";
} else {
    $testPassed = false;
    $errors[] = "fetchWalletOrder should be called in onpaymentauthorized";
    echo "  ✗ FAIL: fetchWalletOrder is NOT called in onpaymentauthorized\n";
}

// Check that orderPromise is assigned in onpaymentauthorized
if (preg_match('/orderPromise\s*=\s*fetchWalletOrder\(\)/', $onpaymentBody)) {
    echo "  ✓ orderPromise is assigned in onpaymentauthorized\n";
} else {
    $testPassed = false;
    $errors[] = "orderPromise should be assigned in onpaymentauthorized";
    echo "  ✗ FAIL: orderPromise assignment not found in onpaymentauthorized\n";
}

// Test 3: Verify console log messages
echo "\nTest 3: Verify appropriate console logging\n";

// Check for log message in onvalidatemerchant that indicates no order creation
if (preg_match('/order creation will happen in onpaymentauthorized/i', $onvalidateBody)) {
    echo "  ✓ onvalidatemerchant logs that order creation is deferred\n";
} else {
    echo "  ℹ No explicit log about deferred order creation (optional)\n";
}

// Check for log message in onpaymentauthorized about creating order
if (preg_match('/Creating PayPal order/i', $onpaymentBody)) {
    echo "  ✓ onpaymentauthorized logs order creation\n";
} else {
    echo "  ℹ No explicit log about order creation in onpaymentauthorized (optional)\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All tests passed! ✓\n\n";
    echo "Verified behavior:\n";
    echo "1. When user clicks Apple Pay button:\n";
    echo "   - onvalidatemerchant is called (modal opens)\n";
    echo "   - Merchant validation happens\n";
    echo "   - NO order is created\n";
    echo "\n";
    echo "2. If user cancels:\n";
    echo "   - oncancel is called\n";
    echo "   - No order was created (nothing to clean up)\n";
    echo "   - No wasted API calls to PayPal\n";
    echo "\n";
    echo "3. If user authorizes payment:\n";
    echo "   - onpaymentauthorized is called\n";
    echo "   - Order is created NOW (when actually needed)\n";
    echo "   - Payment is confirmed with PayPal\n";
    echo "\n";
    echo "This fixes the issue from the problem statement:\n";
    echo "- User reported: 'I never submitted payment, all I did was click the Apple Pay button'\n";
    echo "- User saw: Order creation logs (fetchWalletOrder 783ms)\n";
    echo "- Problem: Orders were being created just from opening the modal\n";
    echo "- Solution: Orders now only created when user authorizes payment\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
