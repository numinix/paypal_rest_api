<?php
/**
 * Test that verifies the wallet modules (Google Pay, Apple Pay, Venmo) 
 * properly hide their parent container when the payment method is ineligible.
 *
 * When a user or device is ineligible for a payment method (e.g., Venmo unavailable
 * in Canada, Apple Pay unavailable on Windows), the entire payment option should be
 * hidden from the checkout page rather than showing an "unavailable" message.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Get the JS files content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js');
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');
$venmoJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.venmo.js');

// Helper regex pattern for detecting 'unavailable' text that shouldn't appear
$unavailableTextPattern = '/innerHTML\s*=\s*[\'"]<span[^>]*unavailable/i';

echo "Testing ineligible payment method hiding functionality\n";
echo "=======================================================\n\n";

// Test 1: Apple Pay has hidePaymentMethodContainer function
if (strpos($applePayJs, 'function hidePaymentMethodContainer()') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should have hidePaymentMethodContainer function";
} else {
    echo "✓ Apple Pay JS has hidePaymentMethodContainer function\n";
}

// Test 2: Apple Pay uses isEligible check
if (strpos($applePayJs, 'buttonInstance.isEligible') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should check button eligibility with isEligible";
} else {
    echo "✓ Apple Pay JS checks button eligibility with isEligible\n";
}

// Test 3: Apple Pay calls hidePaymentMethodContainer when ineligible
if (strpos($applePayJs, "hidePaymentMethodContainer()") === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should call hidePaymentMethodContainer when ineligible";
} else {
    echo "✓ Apple Pay JS calls hidePaymentMethodContainer when ineligible\n";
}

// Test 4: Apple Pay hidePaymentMethodContainer finds parent container
// Check for the presence of container finding logic (closest selector or parent traversal)
if (strpos($applePayJs, 'container.closest') === false && strpos($applePayJs, 'parentElement') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS hidePaymentMethodContainer should find parent container";
} else {
    echo "✓ Apple Pay JS hidePaymentMethodContainer finds parent container\n";
}

// Test 5: Google Pay has hidePaymentMethodContainer function
if (strpos($googlePayJs, 'function hidePaymentMethodContainer()') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should have hidePaymentMethodContainer function";
} else {
    echo "✓ Google Pay JS has hidePaymentMethodContainer function\n";
}

// Test 6: Google Pay uses isEligible check
if (strpos($googlePayJs, 'buttonInstance.isEligible') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should check button eligibility with isEligible";
} else {
    echo "✓ Google Pay JS checks button eligibility with isEligible\n";
}

// Test 7: Google Pay calls hidePaymentMethodContainer when ineligible
if (strpos($googlePayJs, "hidePaymentMethodContainer()") === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should call hidePaymentMethodContainer when ineligible";
} else {
    echo "✓ Google Pay JS calls hidePaymentMethodContainer when ineligible\n";
}

// Test 8: Google Pay validates merchant ID before rendering
// Check for merchant ID validation logic (either regex validation or checking if merchantId is truthy)
if (strpos($googlePayJs, 'config.merchantId') === false || 
    (strpos($googlePayJs, '.test(config.merchantId)') === false && strpos($googlePayJs, 'test(config.merchantId)') === false)) {
    $testPassed = false;
    $errors[] = "Google Pay JS should validate merchant ID before rendering";
} else {
    echo "✓ Google Pay JS validates merchant ID before rendering\n";
}

// Test 9: Google Pay verifies GOOGLEPAY funding source
if (strpos($googlePayJs, 'paypal.FUNDING.GOOGLEPAY') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should verify GOOGLEPAY funding source";
} else {
    echo "✓ Google Pay JS verifies GOOGLEPAY funding source\n";
}

// Test 10: Venmo has hidePaymentMethodContainer function
if (strpos($venmoJs, 'function hidePaymentMethodContainer()') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should have hidePaymentMethodContainer function";
} else {
    echo "✓ Venmo JS has hidePaymentMethodContainer function\n";
}

// Test 11: Venmo uses isEligible check
if (strpos($venmoJs, 'buttonInstance.isEligible') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should check button eligibility with isEligible";
} else {
    echo "✓ Venmo JS checks button eligibility with isEligible\n";
}

// Test 12: Venmo calls hidePaymentMethodContainer when ineligible
if (strpos($venmoJs, "hidePaymentMethodContainer()") === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should call hidePaymentMethodContainer when ineligible";
} else {
    echo "✓ Venmo JS calls hidePaymentMethodContainer when ineligible\n";
}

// Test 13: All JS files no longer show "unavailable" text when config fails
// They should hide the container instead
$showsUnavailableText = false;
if (preg_match($unavailableTextPattern, $applePayJs)) {
    $showsUnavailableText = true;
    $errors[] = "Apple Pay JS should not show 'unavailable' text; should hide container instead";
}
if (preg_match($unavailableTextPattern, $googlePayJs)) {
    $showsUnavailableText = true;
    $errors[] = "Google Pay JS should not show 'unavailable' text; should hide container instead";
}
if (preg_match($unavailableTextPattern, $venmoJs)) {
    $showsUnavailableText = true;
    $errors[] = "Venmo JS should not show 'unavailable' text; should hide container instead";
}

if (!$showsUnavailableText) {
    echo "✓ All wallet JS files hide container instead of showing 'unavailable' text\n";
} else {
    $testPassed = false;
}

// Test 14: All hidePaymentMethodContainer functions use display:none
if (strpos($applePayJs, "style.display = 'none'") === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS hidePaymentMethodContainer should use display:none";
} else {
    echo "✓ Apple Pay JS hidePaymentMethodContainer uses display:none\n";
}

if (strpos($googlePayJs, "style.display = 'none'") === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS hidePaymentMethodContainer should use display:none";
} else {
    echo "✓ Google Pay JS hidePaymentMethodContainer uses display:none\n";
}

if (strpos($venmoJs, "style.display = 'none'") === false) {
    $testPassed = false;
    $errors[] = "Venmo JS hidePaymentMethodContainer should use display:none";
} else {
    echo "✓ Venmo JS hidePaymentMethodContainer uses display:none\n";
}

// Test 15: All JS files create buttonInstance before checking eligibility
if (strpos($applePayJs, 'var buttonInstance = paypal.Buttons') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should create buttonInstance to check eligibility";
} else {
    echo "✓ Apple Pay JS creates buttonInstance to check eligibility\n";
}

if (strpos($googlePayJs, 'var buttonInstance = paypal.Buttons') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should create buttonInstance to check eligibility";
} else {
    echo "✓ Google Pay JS creates buttonInstance to check eligibility\n";
}

if (strpos($venmoJs, 'var buttonInstance = paypal.Buttons') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should create buttonInstance to check eligibility";
} else {
    echo "✓ Venmo JS creates buttonInstance to check eligibility\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All tests passed! ✓\n\n";
    echo "Summary of functionality:\n";
    echo "- Apple Pay, Google Pay, and Venmo all have hidePaymentMethodContainer() function\n";
    echo "- All wallet modules check eligibility with buttonInstance.isEligible()\n";
    echo "- When ineligible, the parent container is hidden (not just showing 'unavailable' text)\n";
    echo "- Google Pay validates merchant ID configuration before rendering\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
