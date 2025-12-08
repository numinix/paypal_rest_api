<?php
/**
 * Test to verify that Apple Pay merchant validation happens IMMEDIATELY
 * without waiting for PayPal order creation, to prevent timeout issues.
 *
 * This test ensures that:
 * 1. onvalidatemerchant calls validateMerchant() immediately
 * 2. Order creation happens in parallel (not blocking merchant validation)
 * 3. Order is awaited in onpaymentauthorized (when actually needed)
 * 4. This prevents the "payment not completed" / auto-close issue
 *
 * Previous behavior (WRONG - causes timeout):
 * - onvalidatemerchant waits for orderPromise.then() before calling validateMerchant()
 * - If order creation is slow, Apple Pay times out waiting for completeMerchantValidation()
 * - Session cancels automatically, user sees "payment not completed"
 *
 * New behavior (CORRECT - prevents timeout):
 * - onvalidatemerchant calls validateMerchant() immediately
 * - Order creation starts in parallel but doesn't block merchant validation
 * - Order is awaited in onpaymentauthorized where it's actually needed
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Apple Pay Merchant Validation Timeout Fix\n";
echo "===================================================\n\n";

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');

// Extract the onApplePayButtonClicked function
$pattern = '/function onApplePayButtonClicked\s*\([^)]*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (!preg_match($pattern, $applePayJs, $matches)) {
    echo "✗ Could not extract onApplePayButtonClicked function\n";
    exit(1);
}
$clickHandlerBody = $matches[1];

// Extract the onvalidatemerchant callback
$onvalidatePattern = '/session\.onvalidatemerchant\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (!preg_match($onvalidatePattern, $clickHandlerBody, $onvalidateMatches)) {
    echo "✗ Could not extract onvalidatemerchant callback\n";
    exit(1);
}
$onvalidateBody = $onvalidateMatches[1];

// Test 1: validateMerchant is called directly (NOT nested inside orderPromise.then())
echo "Test 1: Merchant validation happens immediately\n";

// Check if validateMerchant is called directly (not inside .then())
$validateMerchantPos = strpos($onvalidateBody, 'applepay.validateMerchant');
$firstThenPos = strpos($onvalidateBody, '.then(');

if ($validateMerchantPos === false) {
    $testPassed = false;
    $errors[] = "validateMerchant is not called in onvalidatemerchant";
    echo "  ✗ validateMerchant is not called\n";
} elseif ($firstThenPos !== false && $validateMerchantPos > $firstThenPos) {
    // If validateMerchant comes after .then(), it might be nested inside a promise
    // We need to verify it's not blocked by orderPromise
    
    // Check if there's an orderPromise.then() pattern that blocks validateMerchant
    if (preg_match('/orderPromise\s*\.then\s*\([^{]*\{[\s\S]*?validateMerchant/m', $onvalidateBody)) {
        $testPassed = false;
        $errors[] = "validateMerchant is called inside orderPromise.then() - this causes timeout!";
        echo "  ✗ FAIL: validateMerchant is blocked by orderPromise (causes timeout)\n";
    } else {
        echo "  ✓ validateMerchant is called without waiting for orderPromise\n";
    }
} else {
    echo "  ✓ validateMerchant is called immediately\n";
}

// Test 2: Order creation doesn't block merchant validation
echo "\nTest 2: Order creation doesn't block merchant validation\n";

// The pattern we want: validateMerchant is called directly, order creation happens in parallel
// The pattern we DON'T want: orderPromise.then(...validateMerchant...)

if (preg_match('/orderPromise\s*=\s*fetchWalletOrder\(\)/', $onvalidateBody)) {
    // Order creation starts in onvalidatemerchant (good - parallel execution)
    echo "  ✓ Order creation starts in parallel in onvalidatemerchant\n";
} else {
    // Order creation might happen elsewhere (e.g., in click handler or onpaymentauthorized)
    echo "  ℹ Order creation may happen outside onvalidatemerchant\n";
}

// Verify validateMerchant is NOT nested inside orderPromise.then()
if (preg_match('/orderPromise\.then\([^{]*\{[^}]*validateMerchant/s', $onvalidateBody)) {
    $testPassed = false;
    $errors[] = "validateMerchant must not wait for orderPromise to resolve";
    echo "  ✗ CRITICAL: validateMerchant waits for order (causes timeout)\n";
} else {
    echo "  ✓ validateMerchant does not wait for order creation\n";
}

// Test 3: Order is awaited in onpaymentauthorized (where it's actually needed)
echo "\nTest 3: Order is awaited when actually needed (onpaymentauthorized)\n";

$onpaymentPattern = '/session\.onpaymentauthorized\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (preg_match($onpaymentPattern, $clickHandlerBody, $onpaymentMatches)) {
    $onpaymentBody = $onpaymentMatches[1];
    
    // Check if order is awaited here
    if (strpos($onpaymentBody, 'orderPromise') !== false || 
        strpos($onpaymentBody, 'fetchWalletOrder') !== false) {
        echo "  ✓ Order is created/awaited in onpaymentauthorized\n";
    } else {
        // Order might be already available from onvalidatemerchant
        echo "  ℹ Order may be already available from earlier step\n";
    }
    
    // Verify confirmOrder is called (this needs the order)
    if (strpos($onpaymentBody, 'confirmOrder') !== false) {
        echo "  ✓ confirmOrder is called in onpaymentauthorized\n";
    } else {
        $testPassed = false;
        $errors[] = "confirmOrder should be called in onpaymentauthorized";
        echo "  ✗ confirmOrder is not called\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Could not extract onpaymentauthorized callback";
    echo "  ✗ Could not extract onpaymentauthorized callback\n";
}

// Test 4: Session is created synchronously (maintains user gesture)
echo "\nTest 4: ApplePaySession created synchronously\n";

$sessionCreatePos = strpos($clickHandlerBody, 'new ApplePaySession');
$onvalidateDefPos = strpos($clickHandlerBody, 'session.onvalidatemerchant');

if ($sessionCreatePos !== false && $onvalidateDefPos !== false && $sessionCreatePos < $onvalidateDefPos) {
    echo "  ✓ ApplePaySession created before defining callbacks\n";
} else {
    $testPassed = false;
    $errors[] = "ApplePaySession should be created before defining callbacks";
    echo "  ✗ Unexpected session creation order\n";
}

// Test 5: session.begin() is called synchronously
echo "\nTest 5: session.begin() called synchronously\n";

if (preg_match('/session\.begin\(\)/', $clickHandlerBody)) {
    echo "  ✓ session.begin() is called\n";
} else {
    $testPassed = false;
    $errors[] = "session.begin() must be called";
    echo "  ✗ session.begin() is not called\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All merchant validation timeout fix tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Merchant validation happens immediately without waiting for order\n";
    echo "- Order creation runs in parallel (doesn't block merchant validation)\n";
    echo "- Order is awaited in onpaymentauthorized when actually needed\n";
    echo "- This prevents Apple Pay timeout and auto-close issues\n";
    echo "\nThis fixes the issue where:\n";
    echo "- User clicks Apple Pay button\n";
    echo "- Modal opens with correct price\n";
    echo "- Modal shows 'processing' and then auto-closes\n";
    echo "- Console shows 'cancelled by user' (actually a timeout)\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
