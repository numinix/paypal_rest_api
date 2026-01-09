<?php
/**
 * Test to verify Google Pay button templates don't have duplicate rendering calls.
 * 
 * The issue was that templates were calling window.paypalrGooglePayRender() after
 * the JavaScript file already called renderGooglePayButton(), causing two buttons
 * to be created.
 * 
 * This test ensures:
 * 1. The JavaScript file calls renderGooglePayButton() once at initialization
 * 2. Templates do NOT call window.paypalrGooglePayRender() directly
 * 3. The rendering is handled solely by the JavaScript file
 * 
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

// File paths for testing
const JS_FILE_PATH = '../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js';
const CART_TEMPLATE_PATH = '../includes/templates/template_default/templates/tpl_modules_paypalr_googlepay.php';
const PRODUCT_TEMPLATE_PATH = '../includes/templates/template_default/templates/tpl_modules_paypalr_product_googlepay.php';

$testPassed = true;
$errors = [];

echo "Testing Google Pay Duplicate Button Prevention\n";
echo "==============================================\n\n";

// Helper function to safely read file contents
function safeReadFile(string $relativePath): string {
    $fullPath = __DIR__ . '/' . $relativePath;
    if (!file_exists($fullPath)) {
        throw new RuntimeException("File not found: {$fullPath}");
    }
    // Verify the path is within the expected directory
    $realPath = realpath($fullPath);
    $baseDir = realpath(__DIR__ . '/..');
    if ($realPath === false || !str_starts_with($realPath, $baseDir . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Invalid file path: {$fullPath}");
    }
    return file_get_contents($realPath);
}

// Regex patterns used in tests
$renderCallPattern = '/window\.paypalrGooglePayRender\s*\(/';
$domContentLoadedPattern = '/DOMContentLoaded.*paypalrGooglePayRender/s';

// Test 1: JavaScript file initializes the button
$googlePayJs = safeReadFile(JS_FILE_PATH);

if (strpos($googlePayJs, 'window.paypalrGooglePayRender = renderGooglePayButton;') === false) {
    $testPassed = false;
    $errors[] = "JavaScript should expose paypalrGooglePayRender function";
} else {
    echo "✓ JavaScript exposes paypalrGooglePayRender function\n";
}

if (strpos($googlePayJs, 'renderGooglePayButton();') === false) {
    $testPassed = false;
    $errors[] = "JavaScript should call renderGooglePayButton() at initialization";
} else {
    echo "✓ JavaScript calls renderGooglePayButton() at initialization\n";
}

// Test 2: Shopping cart template should NOT call window.paypalrGooglePayRender()
$cartTemplate = safeReadFile(CART_TEMPLATE_PATH);

// Check for the problematic pattern: calling window.paypalrGooglePayRender() in template
if (preg_match($renderCallPattern, $cartTemplate)) {
    $testPassed = false;
    $errors[] = "Shopping cart template should NOT call window.paypalrGooglePayRender() directly (causes duplicate button)";
} else {
    echo "✓ Shopping cart template does NOT call window.paypalrGooglePayRender() directly\n";
}

// Check for DOMContentLoaded listener in template (another form of duplicate rendering)
if (preg_match($domContentLoadedPattern, $cartTemplate)) {
    $testPassed = false;
    $errors[] = "Shopping cart template should NOT add DOMContentLoaded listener for rendering (causes duplicate button)";
} else {
    echo "✓ Shopping cart template does NOT add DOMContentLoaded listener for rendering\n";
}

// Test 3: Product page template should NOT call window.paypalrGooglePayRender()
$productTemplate = safeReadFile(PRODUCT_TEMPLATE_PATH);

if (preg_match($renderCallPattern, $productTemplate)) {
    $testPassed = false;
    $errors[] = "Product page template should NOT call window.paypalrGooglePayRender() directly (causes duplicate button)";
} else {
    echo "✓ Product page template does NOT call window.paypalrGooglePayRender() directly\n";
}

// Check for DOMContentLoaded listener in product template
if (preg_match($domContentLoadedPattern, $productTemplate)) {
    $testPassed = false;
    $errors[] = "Product page template should NOT add DOMContentLoaded listener for rendering (causes duplicate button)";
} else {
    echo "✓ Product page template does NOT add DOMContentLoaded listener for rendering\n";
}

// Test 4: Templates should load the JavaScript file
if (strpos($cartTemplate, 'jquery.paypalr.googlepay.js') === false) {
    $testPassed = false;
    $errors[] = "Shopping cart template should load jquery.paypalr.googlepay.js";
} else {
    echo "✓ Shopping cart template loads jquery.paypalr.googlepay.js\n";
}

if (strpos($productTemplate, 'jquery.paypalr.googlepay.js') === false) {
    $testPassed = false;
    $errors[] = "Product page template should load jquery.paypalr.googlepay.js";
} else {
    echo "✓ Product page template loads jquery.paypalr.googlepay.js\n";
}

// Test 5: Templates should have the button container div
if (strpos($cartTemplate, 'id="paypalr-googlepay-button"') === false) {
    $testPassed = false;
    $errors[] = "Shopping cart template should have button container div";
} else {
    echo "✓ Shopping cart template has button container div\n";
}

if (strpos($productTemplate, 'id="paypalr-googlepay-button"') === false) {
    $testPassed = false;
    $errors[] = "Product page template should have button container div";
} else {
    echo "✓ Product page template has button container div\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Google Pay duplicate button tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- JavaScript file handles rendering automatically\n";
    echo "- Templates only load the JavaScript file and provide button container\n";
    echo "- No duplicate rendering calls in templates\n";
    echo "- Single Google Pay button will be created\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
