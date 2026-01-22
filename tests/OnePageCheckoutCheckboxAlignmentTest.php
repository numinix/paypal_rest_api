<?php
/**
 * Test to verify that checkboxes and radio buttons are excluded from height rules
 * in the One Page Checkout CSS.
 *
 * Issue: The CSS rule "#paymentMethodContainer input { height: 50px !important; }"
 * was causing checkboxes (like the PayPal save card option) to display misaligned
 * from their labels.
 *
 * Solution: Exclude checkboxes and radio buttons from the height rule by using
 * :not([type="checkbox"]):not([type="radio"]) selectors.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the CSS file content
$cssFile = __DIR__ . '/../references/one_page_responsive_checkout/catalog/includes/templates/YOUR_TEMPLATE/css/one_page_checkout.css';
if (!file_exists($cssFile)) {
    echo "❌ CSS file not found: {$cssFile}\n";
    exit(1);
}

$css = file_get_contents($cssFile);

echo "Testing One Page Checkout Checkbox Alignment Fix\n";
echo "=================================================\n\n";

// Test 1: #paymentMethodContainer input excludes checkboxes
if (preg_match('/#paymentMethodContainer\s+input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\)/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "#paymentMethodContainer input selector should exclude checkboxes and radio buttons using :not() selectors";
} else {
    echo "✓ #paymentMethodContainer input excludes checkboxes and radio buttons\n";
}

// Test 2: Verify the height: 50px rule is still present for other inputs
if (preg_match('/#paymentMethodContainer\s+input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\)[^}]*height:\s*50px\s*!important/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "#paymentMethodContainer input should still have height: 50px for non-checkbox/radio inputs";
} else {
    echo "✓ #paymentMethodContainer input still has height: 50px for text inputs and selects\n";
}

// Test 3: #hideRegistration .nmx-form input excludes checkboxes
if (preg_match('/#hideRegistration\s+\.nmx-form\s+input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\)/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "#hideRegistration .nmx-form input selector should exclude checkboxes and radio buttons";
} else {
    echo "✓ #hideRegistration .nmx-form input excludes checkboxes and radio buttons\n";
}

// Test 4: #easyLogin .nmx input excludes checkboxes
if (preg_match('/#easyLogin\s+\.nmx\s+input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\)/s', $css) === 0) {
    $testPassed = false;
    $errors[] = "#easyLogin .nmx input selector should exclude checkboxes and radio buttons";
} else {
    echo "✓ #easyLogin .nmx input excludes checkboxes and radio buttons\n";
}

// Test 5: Ensure old problematic selectors are not present
$problematicSelectors = [
    '/#paymentMethodContainer\s+input,\s*\n#paymentMethodContainer\s+select\s*\{[^}]*height:\s*50px\s*!important/',
    '/#hideRegistration\s+\.nmx-form\s+input\s*\{[^}]*height:\s*50px\s*!important/',
    '/#easyLogin\s+\.nmx\s+input,\s*\n\s*#easyLogin\s+\.nmx\s+select\s*\{[^}]*height:\s*50px\s*!important/',
];

$foundProblematicSelectors = false;
foreach ($problematicSelectors as $pattern) {
    if (preg_match($pattern, $css)) {
        $foundProblematicSelectors = true;
        break;
    }
}

if ($foundProblematicSelectors) {
    $testPassed = false;
    $errors[] = "Found old problematic selector without :not() exclusions for checkboxes";
} else {
    echo "✓ No old problematic selectors found (all inputs properly exclude checkboxes)\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All checkbox alignment tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Checkboxes and radio buttons are excluded from height rules\n";
    echo "- Credit card fields and other text inputs still have proper 50px height styling\n";
    echo "- PayPal save card checkbox will now align properly with its label\n";
    echo "- Registration and login form checkboxes are also fixed\n\n";
    echo "This ensures checkboxes and radio buttons maintain their natural size\n";
    echo "while keeping form inputs styled consistently.\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
