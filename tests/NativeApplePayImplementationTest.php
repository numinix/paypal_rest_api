<?php
/**
 * Test to verify the native Apple Pay implementation follows PayPal's official
 * Apple Pay integration guide.
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/applepay/
 *
 * The native implementation should:
 * 1. Load PayPal SDK with components=applepay
 * 2. Use paypal.Applepay().config() for payment configuration
 * 3. Use ApplePaySession for the Apple Pay payment sheet
 * 4. Use paypal.Applepay().confirmOrder() for order confirmation
 * 5. Check eligibility with paypal.Applepay().isEligible()
 * 6. Handle onvalidatemerchant callback
 * 7. Handle onpaymentauthorized callback
 * 8. Add buyer-country parameter for sandbox mode
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');

echo "Testing Native Apple Pay Implementation\n";
echo "=======================================\n\n";

// Test 1: Apple Pay JS uses paypal.Applepay() API
if (strpos($applePayJs, 'paypal.Applepay()') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use paypal.Applepay() API";
} else {
    echo "✓ Apple Pay JS uses paypal.Applepay() API\n";
}

// Test 2: Apple Pay JS uses applepay.config() for payment configuration
if (strpos($applePayJs, 'applepay.config()') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use applepay.config() for payment configuration";
} else {
    echo "✓ Apple Pay JS uses applepay.config() for payment configuration\n";
}

// Test 3: Apple Pay JS uses ApplePaySession
if (strpos($applePayJs, 'ApplePaySession') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use ApplePaySession";
} else {
    echo "✓ Apple Pay JS uses ApplePaySession\n";
}

// Test 4: Apple Pay JS creates ApplePaySession with new ApplePaySession()
if (strpos($applePayJs, 'new ApplePaySession') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should create ApplePaySession with new ApplePaySession()";
} else {
    echo "✓ Apple Pay JS creates ApplePaySession with new ApplePaySession()\n";
}

// Test 5: Apple Pay JS uses applepay.confirmOrder()
if (strpos($applePayJs, 'applepay.confirmOrder') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use applepay.confirmOrder()";
} else {
    echo "✓ Apple Pay JS uses applepay.confirmOrder()\n";
}

// Test 6: Apple Pay JS uses applepay.isEligible() for eligibility check
if (strpos($applePayJs, 'applepay.isEligible') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use applepay.isEligible() for eligibility check";
} else {
    echo "✓ Apple Pay JS uses applepay.isEligible() for eligibility check\n";
}

// Test 7: Apple Pay JS handles onvalidatemerchant callback
if (strpos($applePayJs, 'onvalidatemerchant') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should handle onvalidatemerchant callback";
} else {
    echo "✓ Apple Pay JS handles onvalidatemerchant callback\n";
}

// Test 8: Apple Pay JS handles onpaymentauthorized callback
if (strpos($applePayJs, 'onpaymentauthorized') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should handle onpaymentauthorized callback";
} else {
    echo "✓ Apple Pay JS handles onpaymentauthorized callback\n";
}

// Test 9: Apple Pay JS adds buyer-country for sandbox mode
if (strpos($applePayJs, "buyer-country=") === false || strpos($applePayJs, 'isSandbox') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should add buyer-country parameter for sandbox mode";
} else {
    echo "✓ Apple Pay JS adds buyer-country parameter for sandbox mode\n";
}

// Test 10: Apple Pay JS loads SDK with components including applepay
// Note: All wallet modules load SDK with all components for compatibility
if (strpos($applePayJs, "&components=buttons,googlepay,applepay") === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should load SDK with components=buttons,googlepay,applepay";
} else {
    echo "✓ Apple Pay JS loads SDK with components=buttons,googlepay,applepay\n";
}

// Test 11: Apple Pay JS uses validateMerchant
if (strpos($applePayJs, 'validateMerchant') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use validateMerchant";
} else {
    echo "✓ Apple Pay JS uses validateMerchant\n";
}

// Test 12: Apple Pay JS uses completeMerchantValidation
if (strpos($applePayJs, 'completeMerchantValidation') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use completeMerchantValidation";
} else {
    echo "✓ Apple Pay JS uses completeMerchantValidation\n";
}

// Test 13: Apple Pay JS does NOT use the old paypal.Buttons approach for Apple Pay
if (strpos($applePayJs, 'paypal.FUNDING.APPLEPAY') !== false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should NOT use the old paypal.FUNDING.APPLEPAY approach";
} else {
    echo "✓ Apple Pay JS does NOT use the old paypal.FUNDING.APPLEPAY approach\n";
}

// Test 14: Apple Pay JS does NOT use paypal.Buttons for Apple Pay
if (preg_match('/paypal\.Buttons\s*\(\s*\{[^}]*fundingSource\s*:\s*paypal\.FUNDING\.APPLEPAY/', $applePayJs)) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should NOT use paypal.Buttons with APPLEPAY funding source";
} else {
    echo "✓ Apple Pay JS does NOT use paypal.Buttons with APPLEPAY funding source\n";
}

// Test 15: Apple Pay JS handles user cancellation (oncancel)
if (strpos($applePayJs, 'session.oncancel') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should handle user cancellation (oncancel)";
} else {
    echo "✓ Apple Pay JS handles user cancellation (oncancel)\n";
}

// Test 16: Apple Pay JS passes orderId to confirmOrder
if (strpos($applePayJs, 'orderId:') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should pass orderId to confirmOrder";
} else {
    echo "✓ Apple Pay JS passes orderId to confirmOrder\n";
}

// Test 17: Apple Pay JS creates native Apple Pay button
if (strpos($applePayJs, 'createApplePayButton') === false && strpos($applePayJs, 'apple-pay-button') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should create native Apple Pay button";
} else {
    echo "✓ Apple Pay JS creates native Apple Pay button\n";
}

// Test 18: Apple Pay JS handles APPROVED status from confirmOrder
if (strpos($applePayJs, 'APPROVED') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should handle APPROVED status from confirmOrder";
} else {
    echo "✓ Apple Pay JS handles APPROVED status from confirmOrder\n";
}

// Test 19: Apple Pay JS uses session.begin() to start payment
if (strpos($applePayJs, 'session.begin()') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use session.begin() to start payment";
} else {
    echo "✓ Apple Pay JS uses session.begin() to start payment\n";
}

// Test 20: Apple Pay JS uses completePayment for ApplePaySession
if (strpos($applePayJs, 'completePayment') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use completePayment for ApplePaySession";
} else {
    echo "✓ Apple Pay JS uses completePayment for ApplePaySession\n";
}

// Test 21: Apple Pay JS checks canMakePayments before rendering
if (strpos($applePayJs, 'canMakePayments') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should check canMakePayments before rendering";
} else {
    echo "✓ Apple Pay JS checks canMakePayments before rendering\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All native Apple Pay implementation tests passed! ✓\n\n";
    echo "Summary of native Apple Pay implementation:\n";
    echo "- Uses PayPal SDK with components=buttons,googlepay,applepay (for wallet module compatibility)\n";
    echo "- Uses paypal.Applepay().config() for payment configuration\n";
    echo "- Uses ApplePaySession for native payment sheet\n";
    echo "- Uses paypal.Applepay().confirmOrder() for order confirmation\n";
    echo "- Checks eligibility with applepay.isEligible()\n";
    echo "- Handles onvalidatemerchant and onpaymentauthorized callbacks\n";
    echo "- Adds buyer-country=US for sandbox mode\n";
    echo "- Properly handles user cancellation and errors\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
