<?php
/**
 * Test to verify the Apple Pay button CSS custom properties are defined in CSS,
 * not as inline styles in JavaScript.
 *
 * CSS custom properties (--apple-pay-button-width, --apple-pay-button-height, 
 * --apple-pay-button-border-radius) must be defined in the CSS file for proper
 * rendering on iOS Safari.
 *
 * Issue: Setting these properties as inline styles via JavaScript may not work
 * correctly with WebKit's <apple-pay-button> custom element on iOS Safari.
 *
 * Solution: Define CSS custom properties in paypalr.css, not in JavaScript.
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

// Get the JavaScript file content
$jsFile = __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
if (!file_exists($jsFile)) {
    echo "❌ JavaScript file not found: {$jsFile}\n";
    exit(1);
}

$js = file_get_contents($jsFile);

echo "Testing Apple Pay Button CSS Custom Properties\n";
echo "==============================================\n\n";

// Test 1: CSS contains --apple-pay-button-width
if (strpos($css, '--apple-pay-button-width') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain --apple-pay-button-width custom property";
} else {
    echo "✓ CSS contains --apple-pay-button-width custom property\n";
}

// Test 2: CSS contains --apple-pay-button-height
if (strpos($css, '--apple-pay-button-height') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain --apple-pay-button-height custom property";
} else {
    echo "✓ CSS contains --apple-pay-button-height custom property\n";
}

// Test 3: CSS contains --apple-pay-button-border-radius
if (strpos($css, '--apple-pay-button-border-radius') === false) {
    $testPassed = false;
    $errors[] = "CSS should contain --apple-pay-button-border-radius custom property";
} else {
    echo "✓ CSS contains --apple-pay-button-border-radius custom property\n";
}

// Test 4: Verify CSS custom properties are within the apple-pay-button rule
if (preg_match('/apple-pay-button\s*\{[^}]*--apple-pay-button-width[^}]*\}/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "CSS custom property --apple-pay-button-width should be within apple-pay-button rule";
} else {
    echo "✓ CSS custom property --apple-pay-button-width is within apple-pay-button rule\n";
}

if (preg_match('/apple-pay-button\s*\{[^}]*--apple-pay-button-height[^}]*\}/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "CSS custom property --apple-pay-button-height should be within apple-pay-button rule";
} else {
    echo "✓ CSS custom property --apple-pay-button-height is within apple-pay-button rule\n";
}

if (preg_match('/apple-pay-button\s*\{[^}]*--apple-pay-button-border-radius[^}]*\}/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "CSS custom property --apple-pay-button-border-radius should be within apple-pay-button rule";
} else {
    echo "✓ CSS custom property --apple-pay-button-border-radius is within apple-pay-button rule\n";
}

// Test 5: JavaScript should NOT set CSS custom properties as inline styles
// Check both conditions to ensure inline styles are not used
$hasInlineStyleCssText = (strpos($js, 'button.style.cssText') !== false);
$hasApplePayCustomProp = (strpos($js, '--apple-pay-button') !== false);

if ($hasInlineStyleCssText && $hasApplePayCustomProp) {
    $testPassed = false;
    $errors[] = "JavaScript should NOT set --apple-pay-button-* custom properties as inline styles using button.style.cssText";
} else {
    echo "✓ JavaScript does NOT set CSS custom properties as inline styles\n";
}

// Test 6: JavaScript should NOT use .style to set --apple-pay-button-width
if (preg_match('/\.style[\.\[].*--apple-pay-button-width/', $js)) {
    $testPassed = false;
    $errors[] = "JavaScript should NOT use .style to set --apple-pay-button-width";
} else {
    echo "✓ JavaScript does NOT use .style to set --apple-pay-button-width\n";
}

// Test 7: Verify the complete apple-pay-button CSS block
if (preg_match('/apple-pay-button\s*\{[^}]+\}/s', $css, $matches)) {
    echo "\n  Found complete CSS rule:\n";
    $cssBlock = trim($matches[0]);
    foreach (explode("\n", $cssBlock) as $line) {
        echo "  " . trim($line) . "\n";
    }
    echo "\n";
}

// Summary
if ($testPassed) {
    echo "All Apple Pay button CSS custom properties tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- CSS custom properties are defined in paypalr.css\n";
    echo "- --apple-pay-button-width, --apple-pay-button-height, and --apple-pay-button-border-radius are in the CSS\n";
    echo "- JavaScript does NOT set these properties as inline styles\n";
    echo "- This ensures proper rendering on iOS Safari\n\n";
    echo "This fix ensures the Apple Pay button displays correctly on all iOS devices.\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
