<?php
/**
 * Test that verifies all wallet modules (Google Pay, Apple Pay, Venmo) load the PayPal SDK
 * with all required components to ensure compatibility when multiple modules are enabled.
 *
 * The PayPal SDK should be loaded with components=buttons,googlepay,applepay for Google Pay,
 * Apple Pay, and Venmo so that any wallet
 * module can use the SDK features it needs, regardless of which module loads the SDK first.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

$testPassed = true;
$errors = [];

// Get the JS files content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js');
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');
$venmoJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.venmo.js');

echo "Testing Wallet SDK Components Compatibility\n";
echo "============================================\n\n";

// Test 1: Google Pay loads SDK with all components
if (strpos($googlePayJs, '&components=buttons,googlepay,applepay') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should load SDK with components=buttons,googlepay,applepay";
} else {
    echo "✓ Google Pay JS loads SDK with all wallet components\n";
}

// Test 2: Apple Pay loads SDK with all components
if (strpos($applePayJs, '&components=buttons,googlepay,applepay') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should load SDK with components=buttons,googlepay,applepay";
} else {
    echo "✓ Apple Pay JS loads SDK with all wallet components\n";
}

// Test 3: Venmo loads SDK with all components (Venmo is a funding source, not a component)
if (strpos($venmoJs, '&components=buttons,googlepay,applepay') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should load SDK with components=buttons,googlepay,applepay";
} else {
    echo "✓ Venmo JS loads SDK with all wallet components\n";
}

// Test 4: All modules use a shared SDK loader state
$sharedLoaderPattern = '/window\.paypalrSdkLoaderState\s*\|\|/';
if (!preg_match($sharedLoaderPattern, $googlePayJs)) {
    $testPassed = false;
    $errors[] = "Google Pay JS should use shared SDK loader state";
} else {
    echo "✓ Google Pay JS uses shared SDK loader state\n";
}

if (!preg_match($sharedLoaderPattern, $applePayJs)) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should use shared SDK loader state";
} else {
    echo "✓ Apple Pay JS uses shared SDK loader state\n";
}

if (!preg_match($sharedLoaderPattern, $venmoJs)) {
    $testPassed = false;
    $errors[] = "Venmo JS should use shared SDK loader state";
} else {
    echo "✓ Venmo JS uses shared SDK loader state\n";
}

// Test 5: All modules include environment in buildSdkKey
$envInKeyPattern = '/function\s+buildSdkKey[^}]*environment[^}]*return/s';
if (!preg_match($envInKeyPattern, $googlePayJs)) {
    $testPassed = false;
    $errors[] = "Google Pay JS buildSdkKey should include environment";
} else {
    echo "✓ Google Pay JS buildSdkKey includes environment\n";
}

if (!preg_match($envInKeyPattern, $applePayJs)) {
    $testPassed = false;
    $errors[] = "Apple Pay JS buildSdkKey should include environment";
} else {
    echo "✓ Apple Pay JS buildSdkKey includes environment\n";
}

if (!preg_match($envInKeyPattern, $venmoJs)) {
    $testPassed = false;
    $errors[] = "Venmo JS buildSdkKey should include environment";
} else {
    echo "✓ Venmo JS buildSdkKey includes environment\n";
}

// Test 6: Google Pay adds buyer-country for sandbox
if (strpos($googlePayJs, 'buyer-country=US') === false || strpos($googlePayJs, 'isSandbox') === false) {
    $testPassed = false;
    $errors[] = "Google Pay JS should add buyer-country=US for sandbox mode";
} else {
    echo "✓ Google Pay JS adds buyer-country=US for sandbox mode\n";
}

// Test 7: Apple Pay adds buyer-country for sandbox
if (strpos($applePayJs, 'buyer-country=US') === false || strpos($applePayJs, 'isSandbox') === false) {
    $testPassed = false;
    $errors[] = "Apple Pay JS should add buyer-country=US for sandbox mode";
} else {
    echo "✓ Apple Pay JS adds buyer-country=US for sandbox mode\n";
}

// Test 8: Venmo adds buyer-country for sandbox
if (strpos($venmoJs, 'buyer-country=US') === false || strpos($venmoJs, 'isSandbox') === false) {
    $testPassed = false;
    $errors[] = "Venmo JS should add buyer-country=US for sandbox mode";
} else {
    echo "✓ Venmo JS adds buyer-country=US for sandbox mode\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All SDK components compatibility tests passed! ✓\n\n";
    echo "Summary of functionality:\n";
    echo "- Google Pay and Apple Pay load SDK with components=buttons,googlepay,applepay\n";
    echo "- Venmo loads SDK with components=buttons,googlepay,applepay\n";
    echo "- All wallet modules use a shared SDK loader state for deduplication\n";
    echo "- All wallet modules include environment in buildSdkKey for proper caching\n";
    echo "- All wallet modules add buyer-country=US parameter for sandbox mode\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
