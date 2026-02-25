<?php
/**
 * Test to verify that Apple Pay merchant validation happens IMMEDIATELY
 * without creating PayPal orders, to prevent timeout and avoid wasted orders.
 *
 * This test ensures that:
 * 1. onvalidatemerchant calls validateMerchant() immediately
 * 2. Order creation does NOT happen in onvalidatemerchant
 * 3. Order is created in onpaymentauthorized (only when user authorizes payment)
 * 4. This prevents the "payment not completed" / auto-close issue
 * 5. This prevents creating orders when users cancel without authorizing
 *
 * Previous behavior (WRONG - wastes orders):
 * - onvalidatemerchant creates order immediately when modal opens
 * - If user cancels, order is created but never used
 * - Results in abandoned orders in PayPal system
 *
 * New behavior (CORRECT - creates orders only when needed):
 * - onvalidatemerchant calls validateMerchant() immediately (no order creation)
 * - Order creation ONLY happens in onpaymentauthorized when user authorizes
 * - If user cancels, no order is created
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Apple Pay Order Creation Timing Fix\n";
echo "============================================\n\n";

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.applepay.js');

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

// Test 2: Order creation does NOT happen in onvalidatemerchant
echo "\nTest 2: Order creation does NOT happen in onvalidatemerchant\n";

// The pattern we DON'T want: orderPromise = fetchWalletOrder() in onvalidatemerchant
// We want order creation to ONLY happen in onpaymentauthorized

if (preg_match('/orderPromise\s*=\s*fetchWalletOrder\(\)/', $onvalidateBody)) {
    $testPassed = false;
    $errors[] = "Order creation should NOT happen in onvalidatemerchant";
    echo "  ✗ FAIL: Order is created in onvalidatemerchant (wastes orders on cancel)\n";
} else {
    echo "  ✓ Order is NOT created in onvalidatemerchant\n";
}

// Also check that fetchWalletOrder is not called at all in onvalidatemerchant
if (strpos($onvalidateBody, 'fetchWalletOrder') !== false) {
    $testPassed = false;
    $errors[] = "fetchWalletOrder should NOT be called in onvalidatemerchant";
    echo "  ✗ FAIL: fetchWalletOrder is called in onvalidatemerchant\n";
} else {
    echo "  ✓ fetchWalletOrder is NOT called in onvalidatemerchant\n";
}

// Test 3: Order is created in onpaymentauthorized (when user authorizes)
echo "\nTest 3: Order is created in onpaymentauthorized (when user authorizes)\n";

$onpaymentPattern = '/session\.onpaymentauthorized\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (preg_match($onpaymentPattern, $clickHandlerBody, $onpaymentMatches)) {
    $onpaymentBody = $onpaymentMatches[1];
    
    // Check if order is created here (this is where it should happen)
    if (strpos($onpaymentBody, 'fetchWalletOrder') !== false) {
        echo "  ✓ Order is created in onpaymentauthorized\n";
    } else {
        $testPassed = false;
        $errors[] = "Order should be created in onpaymentauthorized";
        echo "  ✗ Order is NOT created in onpaymentauthorized\n";
    }
    
    // Verify orderPromise is assigned here
    if (preg_match('/orderPromise\s*=\s*fetchWalletOrder\(\)/', $onpaymentBody)) {
        echo "  ✓ orderPromise is assigned in onpaymentauthorized\n";
    } else {
        $testPassed = false;
        $errors[] = "orderPromise should be assigned in onpaymentauthorized";
        echo "  ✗ orderPromise is NOT assigned in onpaymentauthorized\n";
    }
    
    // Verify confirmOrder IS called (client-side confirmation pattern)
    if (strpos($onpaymentBody, 'confirmOrder') !== false) {
        echo "  ✓ confirmOrder is called (client-side confirmation as required by PayPal)\n";
    } else {
        $testPassed = false;
        $errors[] = "confirmOrder should be called in onpaymentauthorized (client-side confirmation)";
        echo "  ✗ confirmOrder is NOT called (should use client-side confirmation)\n";
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
    echo "All Apple Pay order creation timing tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Merchant validation happens immediately without creating order\n";
    echo "- Order creation does NOT happen in onvalidatemerchant\n";
    echo "- Order is created in onpaymentauthorized when user authorizes payment\n";
    echo "- This prevents creating wasted orders when users cancel\n";
    echo "\nThis fixes the issue where:\n";
    echo "- User clicks Apple Pay button\n";
    echo "- Modal opens and merchant validation succeeds\n";
    echo "- User cancels without authorizing payment\n";
    echo "- Previously: Order was created and wasted\n";
    echo "- Now: No order is created, saving API calls and avoiding abandoned orders\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
