<?php
/**
 * Test that verifies the wallet modules (Google Pay, Apple Pay, Venmo) 
 * have JavaScript that hides their radio buttons and auto-selects when clicked.
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Get the JS files content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js');
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');
$venmoJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.venmo.js');

// Test 1: Google Pay JS contains radio selection function
if (strpos($googlePayJs, 'selectGooglePayRadio') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should contain selectGooglePayRadio function";
} else {
    echo "✓ Google Pay JS contains radio selection function\n";
}

// Test 2: Google Pay JS targets correct radio button
if (strpos($googlePayJs, 'pmt-paypalr_googlepay') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should target pmt-paypalr_googlepay radio";
} else {
    echo "✓ Google Pay JS targets correct radio button\n";
}

// Test 3: Google Pay JS hides radio button
if (strpos($googlePayJs, 'hideModuleRadio') === false || strpos($googlePayJs, 'paypalr-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should hide the radio button";
} else {
    echo "✓ Google Pay JS hides radio button\n";
}

// Test 4: Google Pay JS adds click handler to container
if (strpos($googlePayJs, 'paypalr-googlepay-button') === false || strpos($googlePayJs, 'addEventListener') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should add click handler to container";
} else {
    echo "✓ Google Pay JS adds click handler to container\n";
}

// Test 5: Apple Pay JS contains radio selection function
if (strpos($applePayJs, 'selectApplePayRadio') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should contain selectApplePayRadio function";
} else {
    echo "✓ Apple Pay JS contains radio selection function\n";
}

// Test 6: Apple Pay JS targets correct radio button
if (strpos($applePayJs, 'pmt-paypalr_applepay') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should target pmt-paypalr_applepay radio";
} else {
    echo "✓ Apple Pay JS targets correct radio button\n";
}

// Test 7: Apple Pay JS hides radio button
if (strpos($applePayJs, 'hideModuleRadio') === false || strpos($applePayJs, 'paypalr-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should hide the radio button";
} else {
    echo "✓ Apple Pay JS hides radio button\n";
}

// Test 8: Apple Pay JS adds click handler to container
if (strpos($applePayJs, 'paypalr-applepay-button') === false || strpos($applePayJs, 'addEventListener') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should add click handler to container";
} else {
    echo "✓ Apple Pay JS adds click handler to container\n";
}

// Test 9: Venmo JS contains radio selection function
if (strpos($venmoJs, 'selectVenmoRadio') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should contain selectVenmoRadio function";
} else {
    echo "✓ Venmo JS contains radio selection function\n";
}

// Test 10: Venmo JS targets correct radio button
if (strpos($venmoJs, 'pmt-paypalr_venmo') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should target pmt-paypalr_venmo radio";
} else {
    echo "✓ Venmo JS targets correct radio button\n";
}

// Test 11: Venmo JS hides radio button
if (strpos($venmoJs, 'hideModuleRadio') === false || strpos($venmoJs, 'paypalr-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should hide the radio button";
} else {
    echo "✓ Venmo JS hides radio button\n";
}

// Test 12: Venmo JS adds click handler to container
if (strpos($venmoJs, 'paypalr-venmo-button') === false || strpos($venmoJs, 'addEventListener') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should add click handler to container";
} else {
    echo "✓ Venmo JS adds click handler to container\n";
}

// Test 13: CSS file exists and contains wallet radio hidden class
$cssContent = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/paypalr.css');
if (strpos($cssContent, 'paypalr-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain paypalr-wallet-radio-hidden class";
} else {
    echo "✓ CSS contains wallet radio hidden class\n";
}

// Test 14: Hidden class uses proper CSS to visually hide but keep accessible
if (strpos($cssContent, 'position: absolute') === false || strpos($cssContent, 'clip: rect') === false) {
    $testPassed = false;
    $errors[] = "Hidden class should use proper CSS for visual hiding";
} else {
    echo "✓ Hidden class uses proper CSS for visual hiding\n";
}

// Test 15: Hidden class uses modern clip-path for better browser support
if (strpos($cssContent, 'clip-path: inset') === false) {
    $testPassed = false;
    $errors[] = "Hidden class should use clip-path for modern browsers";
} else {
    echo "✓ Hidden class uses clip-path for modern browsers\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All tests passed! ✓\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
