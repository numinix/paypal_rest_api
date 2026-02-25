<?php
/**
 * Test to verify that CSP (Content Security Policy) nonce support is correctly implemented.
 *
 * When a Content Security Policy is in place with script-src directive, scripts need to
 * include a nonce attribute that matches the CSP header to be allowed to execute.
 *
 * This test verifies that:
 * 1. The observer adds nonce attribute to PayPal SDK script tag when CSP_NONCE is set
 * 2. The observer adds nonce attribute to inline script tags when CSP_NONCE is set
 * 3. JavaScript files include getCspNonce() helper function
 * 4. JavaScript files use the nonce when creating script tags dynamically
 *
 * Issue: Content Security Policy violation when loading PayPal SDK
 * Solution: Add CSP nonce support to all script tags (both static and dynamic)
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing CSP Nonce Support Implementation\n";
echo "========================================\n\n";

// Test 1: Observer file contains CSP nonce handling
echo "Test 1: Observer CSP nonce handling\n";
echo "------------------------------------\n";

$observerFile = __DIR__ . '/../includes/classes/observers/auto.paypalrestful.php';
if (!file_exists($observerFile)) {
    echo "❌ Observer file not found: {$observerFile}\n";
    exit(1);
}

$observerContent = file_get_contents($observerFile);

// Check for CSP_NONCE global variable check
if (strpos($observerContent, '$GLOBALS[\'CSP_NONCE\']') === false) {
    $testPassed = false;
    $errors[] = "Observer should check for \$GLOBALS['CSP_NONCE']";
} else {
    echo "✓ Observer checks for \$GLOBALS['CSP_NONCE']\n";
}

// Check for nonce attribute in script params
if (strpos($observerContent, 'nonce="') === false) {
    $testPassed = false;
    $errors[] = "Observer should add nonce attribute to script tags";
} else {
    echo "✓ Observer adds nonce attribute to script tags\n";
}

// Check for htmlspecialchars to sanitize the nonce
if (strpos($observerContent, 'htmlspecialchars($GLOBALS[\'CSP_NONCE\']') === false) {
    $testPassed = false;
    $errors[] = "Observer should sanitize CSP nonce with htmlspecialchars";
} else {
    echo "✓ Observer sanitizes CSP nonce with htmlspecialchars\n";
}

echo "\n";

// Test 2: JavaScript files contain getCspNonce() helper
echo "Test 2: JavaScript getCspNonce() helper\n";
echo "----------------------------------------\n";

$jsFiles = [
    'google-pay' => __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.googlepay.js',
    'apple-pay' => __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.applepay.js',
    'venmo' => __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.venmo.js',
];

foreach ($jsFiles as $name => $jsFile) {
    if (!file_exists($jsFile)) {
        $testPassed = false;
        $errors[] = "JavaScript file not found: {$jsFile}";
        continue;
    }

    $js = file_get_contents($jsFile);

    // Check for getCspNonce function
    if (strpos($js, 'function getCspNonce()') === false) {
        $testPassed = false;
        $errors[] = "{$name} JavaScript should contain getCspNonce() function";
    } else {
        echo "✓ {$name} JavaScript contains getCspNonce() function\n";
    }

    // Check that getCspNonce queries for script[nonce]
    if (strpos($js, 'script[nonce]') === false) {
        $testPassed = false;
        $errors[] = "{$name} JavaScript getCspNonce() should query for script[nonce]";
    } else {
        echo "✓ {$name} JavaScript getCspNonce() queries for script[nonce]\n";
    }

    // Check that nonce is used when creating script elements
    if (strpos($js, "setAttribute('nonce'") === false && strpos($js, 'setAttribute("nonce"') === false) {
        $testPassed = false;
        $errors[] = "{$name} JavaScript should set nonce attribute on created script elements";
    } else {
        echo "✓ {$name} JavaScript sets nonce attribute on created script elements\n";
    }
}

echo "\n";

// Test 3: Verify nonce propagation pattern
echo "Test 3: Nonce propagation pattern\n";
echo "----------------------------------\n";

foreach ($jsFiles as $name => $jsFile) {
    $js = file_get_contents($jsFile);

    // Check for the pattern: var nonce = getCspNonce();
    if (strpos($js, 'var nonce = getCspNonce()') === false) {
        $testPassed = false;
        $errors[] = "{$name} JavaScript should call getCspNonce() before creating scripts";
    } else {
        echo "✓ {$name} JavaScript calls getCspNonce() before creating scripts\n";
    }

    // Check for conditional nonce setting: if (nonce)
    if (strpos($js, 'if (nonce)') === false) {
        $testPassed = false;
        $errors[] = "{$name} JavaScript should conditionally set nonce only when available";
    } else {
        echo "✓ {$name} JavaScript conditionally sets nonce when available\n";
    }
}

echo "\n";

// Test 4: Verify CSP nonce comment documentation
echo "Test 4: Documentation\n";
echo "---------------------\n";

foreach ($jsFiles as $name => $jsFile) {
    $js = file_get_contents($jsFile);

    // Check for CSP-related comment
    if (strpos($js, 'CSP') === false && strpos($js, 'Content Security Policy') === false) {
        // Warning only, not a failure
        echo "⚠ {$name} JavaScript could benefit from CSP-related comments\n";
    } else {
        echo "✓ {$name} JavaScript includes CSP-related documentation\n";
    }
}

echo "\n";

// Print summary
echo "Test Summary\n";
echo "============\n";

if ($testPassed) {
    echo "✅ All tests passed!\n";
    echo "\n";
    echo "CSP nonce support is correctly implemented:\n";
    echo "- Observer checks for \$GLOBALS['CSP_NONCE'] and adds nonce to script tags\n";
    echo "- JavaScript files include getCspNonce() helper function\n";
    echo "- Dynamically created script tags use the nonce attribute\n";
    echo "- Nonce is propagated from existing script tags to new ones\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n\n";
    echo "Errors found:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}
