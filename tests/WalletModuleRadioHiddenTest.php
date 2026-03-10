<?php
/**
 * Test that verifies the wallet modules (Google Pay, Apple Pay, Venmo, Pay Later) 
 * have JavaScript that hides their radio buttons and auto-selects when clicked.
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Get the JS files content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.googlepay.js');
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.applepay.js');
$venmoJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.venmo.js');
$payLaterJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.paylater.js');

// Test 1: Google Pay JS contains radio selection function
if (strpos($googlePayJs, 'selectGooglePayRadio') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should contain selectGooglePayRadio function";
} else {
    echo "✓ Google Pay JS contains radio selection function\n";
}

// Test 2: Google Pay JS targets correct radio button
if (strpos($googlePayJs, 'pmt-paypalac_googlepay') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should target pmt-paypalac_googlepay radio";
} else {
    echo "✓ Google Pay JS targets correct radio button\n";
}

// Test 3: Google Pay JS hides module label (radio remains visible)
if (strpos($googlePayJs, 'hideModuleLabel') === false || strpos($googlePayJs, 'paypalac-wallet-label-hidden') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should hide only the module label";
} else {
    echo "✓ Google Pay JS hides module label\n";
}

// Test 4: Google Pay JS adds click handler to container
if (strpos($googlePayJs, 'paypalac-googlepay-button') === false || strpos($googlePayJs, 'addEventListener') === false) {
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
if (strpos($applePayJs, 'pmt-paypalac_applepay') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should target pmt-paypalac_applepay radio";
} else {
    echo "✓ Apple Pay JS targets correct radio button\n";
}

// Test 7: Apple Pay JS hides module label (radio remains visible)
if (strpos($applePayJs, 'hideModuleLabel') === false || strpos($applePayJs, 'paypalac-wallet-label-hidden') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should hide only the module label";
} else {
    echo "✓ Apple Pay JS hides module label\n";
}

// Test 8: Apple Pay JS adds click handler to container
if (strpos($applePayJs, 'paypalac-applepay-button') === false || strpos($applePayJs, 'addEventListener') === false) {
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
if (strpos($venmoJs, 'pmt-paypalac_venmo') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should target pmt-paypalac_venmo radio";
} else {
    echo "✓ Venmo JS targets correct radio button\n";
}

// Test 11: Venmo JS hides radio button
if (strpos($venmoJs, 'hideModuleRadio') === false || strpos($venmoJs, 'paypalac-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should hide the radio button";
} else {
    echo "✓ Venmo JS hides radio button\n";
}

// Test 12: Venmo JS adds click handler to container
if (strpos($venmoJs, 'paypalac-venmo-button') === false || strpos($venmoJs, 'addEventListener') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should add click handler to container";
} else {
    echo "✓ Venmo JS adds click handler to container\n";
}


// Test 13: Pay Later JS contains radio selection function
if (strpos($payLaterJs, 'selectPaylaterRadio') === false) {
    $testPassed = false;
    $errors[] = "Pay Later JS should contain selectPaylaterRadio function";
} else {
    echo "✓ Pay Later JS contains radio selection function\n";
}

// Test 14: Pay Later JS targets correct radio button
if (strpos($payLaterJs, 'pmt-paypalac_paylater') === false) {
    $testPassed = false;
    $errors[] = "Pay Later JS should target pmt-paypalac_paylater radio";
} else {
    echo "✓ Pay Later JS targets correct radio button\n";
}

// Test 15: Pay Later JS hides radio button
if (strpos($payLaterJs, 'hideModuleRadio') === false || strpos($payLaterJs, 'paypalac-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "Pay Later JS should hide the radio button";
} else {
    echo "✓ Pay Later JS hides radio button\n";
}

// Test 16: Pay Later JS hides module label when unavailable
if (strpos($payLaterJs, 'hideModuleLabel') === false || strpos($payLaterJs, 'paypalac-wallet-label-hidden') === false) {
    $testPassed = false;
    $errors[] = "Pay Later JS should hide the module label when unavailable";
} else {
    echo "✓ Pay Later JS hides module label when unavailable\n";
}

// Test 13: CSS file exists and contains wallet radio hidden class
$cssContent = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/paypalac.css');
if (strpos($cssContent, 'paypalac-wallet-radio-hidden') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain paypalac-wallet-radio-hidden class";
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
