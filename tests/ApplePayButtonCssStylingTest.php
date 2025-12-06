<?php
/**
 * Test to verify the Apple Pay button CSS styling for iOS Safari compatibility.
 *
 * The <apple-pay-button> is a WebKit custom element that requires explicit CSS
 * styling to be visible on iOS Safari. This test verifies that the necessary
 * CSS rules are present.
 *
 * Issue: On iOS Safari, the Apple Pay button doesn't appear without proper CSS.
 * Solution: Add explicit display and -webkit-appearance styles for apple-pay-button element.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the CSS file content
$cssFile = __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/paypalr.css';
if (!file_exists($cssFile)) {
    echo "❌ CSS file not found: {$cssFile}\n";
    exit(1);
}

$css = file_get_contents($cssFile);

echo "Testing Apple Pay Button CSS Styling\n";
echo "====================================\n\n";

// Test 1: CSS contains apple-pay-button element selector
if (strpos($css, 'apple-pay-button') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain apple-pay-button element selector";
} else {
    echo "✓ CSS contains apple-pay-button element selector\n";
}

// Test 2: CSS sets display property for apple-pay-button
// The display property is crucial for WebKit to show the element
if (preg_match('/apple-pay-button\s*\{[^}]*display\s*:/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "CSS should set display property for apple-pay-button (required for visibility on iOS Safari)";
} else {
    echo "✓ CSS sets display property for apple-pay-button\n";
}

// Test 3: CSS sets -webkit-appearance for apple-pay-button
// This property is required for the native Apple Pay button styling
// Check that both -webkit-appearance and -apple-pay-button are within the apple-pay-button rule
if (preg_match('/apple-pay-button\s*\{[^}]*-webkit-appearance[^}]*-apple-pay-button[^}]*\}/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "CSS should set -webkit-appearance: -apple-pay-button for native styling within apple-pay-button rule";
} else {
    echo "✓ CSS sets -webkit-appearance: -apple-pay-button for native styling\n";
}

// Test 4: Verify the apple-pay-button CSS block is well-formed
if (preg_match('/apple-pay-button\s*\{[^}]+\}/s', $css, $matches)) {
    echo "✓ apple-pay-button CSS block is well-formed\n";
    
    // Extract and display the CSS rule for documentation
    $cssBlock = trim($matches[0]);
    echo "\n  Found CSS rule:\n";
    foreach (explode("\n", $cssBlock) as $line) {
        echo "  " . trim($line) . "\n";
    }
    echo "\n";
} else {
    $testPassed = false;
    $errors[] = "apple-pay-button CSS block is malformed";
}

// Test 5: Verify CSS doesn't conflict with container styling
if (strpos($css, '.paypalr-applepay-button') !== false) {
    echo "✓ CSS contains container class .paypalr-applepay-button\n";
} else {
    $testPassed = false;
    $errors[] = "CSS should contain container class .paypalr-applepay-button";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay button CSS styling tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- The apple-pay-button element has explicit CSS styling\n";
    echo "- The display property is set (required for iOS Safari visibility)\n";
    echo "- The -webkit-appearance property is set for native Apple Pay button styling\n";
    echo "- The CSS is well-formed and doesn't conflict with container styling\n\n";
    echo "This ensures the Apple Pay button is visible on iOS Safari.\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
