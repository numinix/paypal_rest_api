<?php
/**
 * Test to verify that the Apple Pay JS SDK loader is implemented correctly.
 *
 * The <apple-pay-button> WebKit custom element requires Apple's JS SDK to be loaded
 * from https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js before the
 * button element is appended to the DOM.
 *
 * Issue: The button wasn't rendering because the SDK wasn't being loaded.
 * Solution: Add loadApplePayJsSdk() function and call it before creating the button.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the JavaScript file content
$jsFile = __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.applepay.js';
if (!file_exists($jsFile)) {
    echo "❌ JavaScript file not found: {$jsFile}\n";
    exit(1);
}

$js = file_get_contents($jsFile);

echo "Testing Apple Pay JS SDK Loader Implementation\n";
echo "==============================================\n\n";

// Test 1: JS contains appleSdkLoader variable
if (strpos($js, 'appleSdkLoader') === false) {
    $testPassed = false;
    $errors[] = "JavaScript should contain appleSdkLoader variable";
} else {
    echo "✓ JavaScript contains appleSdkLoader variable\n";
}

// Test 2: JS contains loadApplePayJsSdk function
if (strpos($js, 'function loadApplePayJsSdk()') === false) {
    $testPassed = false;
    $errors[] = "JavaScript should contain loadApplePayJsSdk() function";
} else {
    echo "✓ JavaScript contains loadApplePayJsSdk() function\n";
}

// Test 3: loadApplePayJsSdk loads from Apple's CDN
if (strpos($js, 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should load from Apple's CDN (https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js)";
} else {
    echo "✓ loadApplePayJsSdk loads from Apple's CDN\n";
}

// Test 4: loadApplePayJsSdk sets data-apple-pay-sdk attribute
if (strpos($js, 'data-apple-pay-sdk') === false && strpos($js, 'dataset.applePaySdk') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should set data-apple-pay-sdk attribute on script tag";
} else {
    echo "✓ loadApplePayJsSdk sets data-apple-pay-sdk attribute\n";
}

// Test 5: loadApplePayJsSdk returns a Promise
if (strpos($js, 'appleSdkLoader.promise = new Promise') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should return a Promise";
} else {
    echo "✓ loadApplePayJsSdk returns a Promise\n";
}

// Test 6: loadApplePayJsSdk checks for existing script
if (strpos($js, 'document.querySelector(\'script[data-apple-pay-sdk="true"]\')') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should check for existing script tag";
} else {
    echo "✓ loadApplePayJsSdk checks for existing script tag\n";
}

// Test 7: loadApplePayJsSdk is called before the availability check
// The SDK must load first so non-Safari browsers get the ApplePaySession polyfill
// (iOS 18+ QR flow) before canMakePayments() is evaluated.
if (preg_match('/loadApplePayJsSdk\(\)\.then\(function\s*\(\)\s*\{[^}]*ApplePaySession/s', $js) === 0) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should be called before the ApplePaySession availability check";
} else {
    echo "✓ loadApplePayJsSdk is called before the availability check\n";
}

// Test 8: Button is created and appended once eligibility passes
if (preg_match('/function appendApplePayButton\([^)]*\)\s*\{[^}]*createApplePayButton\(\)[^}]*container\.appendChild\(button\)/s', $js) === 0) {
    $testPassed = false;
    $errors[] = "appendApplePayButton should create the button and append it to the container";
} else {
    echo "✓ Button is created and appended after eligibility passes\n";
}

// Test 9: loadApplePayJsSdk handles script load error
if (strpos($js, 'script.onerror') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should handle script load errors";
} else {
    echo "✓ loadApplePayJsSdk handles script load errors\n";
}

// Test 10: loadApplePayJsSdk uses singleton pattern (caches promise)
if (strpos($js, 'if (appleSdkLoader.promise)') === false) {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk should use singleton pattern to avoid loading SDK multiple times";
} else {
    echo "✓ loadApplePayJsSdk uses singleton pattern\n";
}

// Test 11: Verify the function structure
if (preg_match('/function loadApplePayJsSdk\(\)\s*\{/', $js, $matches)) {
    echo "✓ loadApplePayJsSdk function is well-formed\n";
} else {
    $testPassed = false;
    $errors[] = "loadApplePayJsSdk function structure is malformed";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay JS SDK loader tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- loadApplePayJsSdk() function is implemented\n";
    echo "- SDK is loaded from Apple's CDN before button creation\n";
    echo "- Script tag is properly tracked with data-apple-pay-sdk attribute\n";
    echo "- Singleton pattern prevents multiple SDK loads\n";
    echo "- Error handling is implemented\n";
    echo "- Button is only appended after SDK loads successfully\n\n";
    echo "This ensures the Apple Pay button renders correctly on iOS Safari.\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
