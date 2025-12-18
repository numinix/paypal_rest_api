<?php
/**
 * Test to verify the native Google Pay implementation follows PayPal's official
 * Google Pay integration guide and uses client-side confirmation pattern.
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/googlepay/
 *
 * The native implementation should:
 * 1. Load PayPal SDK with components=googlepay
 * 2. Load Google Pay JS from pay.google.com/gp/p/js/pay.js
 * 3. Use paypal.Googlepay().config() for payment configuration
 * 4. Use google.payments.api.PaymentsClient for the Google Pay client
 * 5. Use paymentsClient.createButton() for rendering the button
 * 6. Use paymentsClient.loadPaymentData() for payment flow
 * 7. Use client-side confirmOrder (matching Apple Pay pattern)
 * 8. Check eligibility with paypal.Googlepay().isEligible()
 * 9. Add buyer-country parameter for sandbox mode
 * 10. Pass confirmation result to server with {orderID, confirmed: true}
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the Google Pay JS file content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js');

echo "Testing Native Google Pay Implementation\n";
echo "========================================\n\n";

// Test 1: Google Pay JS loads Google Pay JS library
if (strpos($googlePayJs, 'pay.google.com/gp/p/js/pay.js') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should load Google Pay JS library from pay.google.com";
} else {
    echo "✓ Google Pay JS loads Google Pay JS library from pay.google.com\n";
}

// Test 2: Google Pay JS uses paypal.Googlepay() API
if (strpos($googlePayJs, 'paypal.Googlepay()') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use paypal.Googlepay() API";
} else {
    echo "✓ Google Pay JS uses paypal.Googlepay() API\n";
}

// Test 3: Google Pay JS uses googlepay.config() for payment configuration
if (strpos($googlePayJs, 'googlepay.config()') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use googlepay.config() for payment configuration";
} else {
    echo "✓ Google Pay JS uses googlepay.config() for payment configuration\n";
}

// Test 4: Google Pay JS uses google.payments.api.PaymentsClient
if (strpos($googlePayJs, 'google.payments.api.PaymentsClient') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use google.payments.api.PaymentsClient";
} else {
    echo "✓ Google Pay JS uses google.payments.api.PaymentsClient\n";
}

// Test 5: Google Pay JS uses paymentsClient.createButton()
if (strpos($googlePayJs, 'paymentsClient.createButton') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use paymentsClient.createButton()";
} else {
    echo "✓ Google Pay JS uses paymentsClient.createButton()\n";
}

// Test 6: Google Pay JS uses paymentsClient.loadPaymentData()
if (strpos($googlePayJs, 'paymentsClient.loadPaymentData') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use paymentsClient.loadPaymentData()";
} else {
    echo "✓ Google Pay JS uses paymentsClient.loadPaymentData()\n";
}

// Test 7: Google Pay JS DOES use client-side confirmOrder (matching Apple Pay pattern)
// Following the fix for INTERNAL_SERVER_ERROR, confirmOrder should be called on client
if (strpos($googlePayJs, 'googlepay.confirmOrder') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use client-side googlepay.confirmOrder() to match Apple Pay pattern";
} else {
    echo "✓ Google Pay JS uses client-side googlepay.confirmOrder() (matching Apple Pay pattern)\n";
}

// Test 8: Google Pay JS uses googlepay.isEligible() for eligibility check
if (strpos($googlePayJs, 'googlepay.isEligible') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use googlepay.isEligible() for eligibility check";
} else {
    echo "✓ Google Pay JS uses googlepay.isEligible() for eligibility check\n";
}

// Test 9: Google Pay JS adds buyer-country for sandbox mode
if (strpos($googlePayJs, "buyer-country=") === false || strpos($googlePayJs, 'isSandbox') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should add buyer-country parameter for sandbox mode";
} else {
    echo "✓ Google Pay JS adds buyer-country parameter for sandbox mode\n";
}

// Test 10: Google Pay JS loads SDK with all wallet components (buttons,googlepay,applepay)
// This ensures compatibility when multiple wallet modules are enabled simultaneously
if (strpos($googlePayJs, "&components=buttons,googlepay,applepay") === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should load SDK with components=buttons,googlepay,applepay";
} else {
    echo "✓ Google Pay JS loads SDK with components=buttons,googlepay,applepay\n";
}

// Test 11: Google Pay JS uses isReadyToPay check
if (strpos($googlePayJs, 'paymentsClient.isReadyToPay') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use paymentsClient.isReadyToPay()";
} else {
    echo "✓ Google Pay JS uses paymentsClient.isReadyToPay()\n";
}

// Test 12: Google Pay JS uses correct Google Pay environment (TEST vs PRODUCTION)
if (strpos($googlePayJs, "'TEST'") === false || strpos($googlePayJs, "'PRODUCTION'") === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use correct Google Pay environment (TEST vs PRODUCTION)";
} else {
    echo "✓ Google Pay JS uses correct Google Pay environment (TEST vs PRODUCTION)\n";
}

// Test 13: Google Pay JS does NOT use the old paypal.Buttons approach for Google Pay
if (strpos($googlePayJs, 'paypal.FUNDING.GOOGLEPAY') !== false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should NOT use the old paypal.FUNDING.GOOGLEPAY approach";
} else {
    echo "✓ Google Pay JS does NOT use the old paypal.FUNDING.GOOGLEPAY approach\n";
}

// Test 14: Google Pay JS does NOT use paypal.Buttons for Google Pay
if (preg_match('/paypal\.Buttons\s*\(\s*\{[^}]*fundingSource\s*:\s*paypal\.FUNDING\.GOOGLEPAY/', $googlePayJs)) {
    $testPassed = false;
    $errors[] = "Google Pay JS should NOT use paypal.Buttons with GOOGLEPAY funding source";
} else {
    echo "✓ Google Pay JS does NOT use paypal.Buttons with GOOGLEPAY funding source\n";
}

// Test 15: Google Pay JS has proper error handling for user cancellation
if (strpos($googlePayJs, 'CANCELED') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should handle user cancellation (CANCELED status)";
} else {
    echo "✓ Google Pay JS handles user cancellation (CANCELED status)\n";
}

// Test 16: Google Pay JS sets payload with orderId
// Server-side confirmation uses payload with orderId and paymentMethodData
if (strpos($googlePayJs, 'orderID:') === false && strpos($googlePayJs, 'orderId') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should set orderId in payload";
} else {
    echo "✓ Google Pay JS sets orderId in payload\n";
}

// Test 17: Google Pay JS includes loadGooglePayJs function
if (strpos($googlePayJs, 'function loadGooglePayJs') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should have loadGooglePayJs function";
} else {
    echo "✓ Google Pay JS has loadGooglePayJs function\n";
}

// Test 18: Google Pay JS includes paymentMethodData in payload
// Server-side confirmation needs the payment data from Google Pay
if (strpos($googlePayJs, 'paymentMethodData') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should include paymentMethodData in payload for server confirmation";
} else {
    echo "✓ Google Pay JS includes paymentMethodData in payload\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All native Google Pay implementation tests passed! ✓\n\n";
    echo "Summary of native Google Pay implementation:\n";
    echo "- Uses PayPal SDK with components=googlepay\n";
    echo "- Loads Google Pay JS from pay.google.com/gp/p/js/pay.js\n";
    echo "- Uses paypal.Googlepay().config() for payment configuration\n";
    echo "- Uses google.payments.api.PaymentsClient for Google Pay client\n";
    echo "- Uses paymentsClient.createButton() for button rendering\n";
    echo "- Uses paymentsClient.loadPaymentData() for payment flow\n";
    echo "- Uses paypal.Googlepay().confirmOrder() for order confirmation\n";
    echo "- Checks eligibility with googlepay.isEligible()\n";
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
