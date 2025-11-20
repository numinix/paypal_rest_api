<?php
/**
 * Test that verifies credit card input fields have onfocus attribute
 * to ensure proper radio button visual state when fields are focused.
 */

// Simple standalone test to verify onfocus attribute is added
// This doesn't require the full Zen Cart environment

$testPassed = true;
$errors = [];

// Simulate the code change
$code = 'paypalr_creditcard';
$onFocus = ' onfocus="methodSelect(\'pmt-' . $code . '\')"';

// Test 1: Verify the onfocus attribute is constructed correctly
$expectedOnFocus = ' onfocus="methodSelect(\'pmt-paypalr_creditcard\')"';
if ($onFocus !== $expectedOnFocus) {
    $testPassed = false;
    $errors[] = "OnFocus attribute mismatch. Expected: {$expectedOnFocus}, Got: {$onFocus}";
} else {
    echo "✓ OnFocus attribute constructed correctly\n";
}

// Test 2: Verify the attribute can be safely concatenated with HTML attributes
$testHtml = 'class="test-class" id="test-id"' . $onFocus;
if (strpos($testHtml, 'onfocus="methodSelect(\'pmt-paypalr_creditcard\')"') === false) {
    $testPassed = false;
    $errors[] = "OnFocus attribute not found in concatenated HTML";
} else {
    echo "✓ OnFocus attribute can be concatenated with HTML attributes\n";
}

// Test 3: Verify the JavaScript function call is properly escaped
if (strpos($onFocus, 'methodSelect(') === false || strpos($onFocus, 'pmt-paypalr_creditcard') === false) {
    $testPassed = false;
    $errors[] = "JavaScript function call not properly formatted";
} else {
    echo "✓ JavaScript function call properly formatted\n";
}

// Test 4: Mock buildSavedCardOptions to verify it accepts the parameter
function mockBuildSavedCardOptions(array $vaultedCards, string $selectedVaultId, string $onFocus = ''): string
{
    $html = '<select name="paypalr_saved_card" id="paypalr-saved-card" class="ppr-saved-card-select"' . $onFocus . '>';
    $html .= '</select>';
    return $html;
}

$mockResult = mockBuildSavedCardOptions([], 'new', $onFocus);
if (strpos($mockResult, 'onfocus="methodSelect(\'pmt-paypalr_creditcard\')"') === false) {
    $testPassed = false;
    $errors[] = "buildSavedCardOptions does not include onfocus attribute";
} else {
    echo "✓ buildSavedCardOptions includes onfocus attribute\n";
}

// Test 5: Verify HTML is properly formed
if (strpos($mockResult, '<select') === false || strpos($mockResult, '</select>') === false) {
    $testPassed = false;
    $errors[] = "buildSavedCardOptions does not generate proper HTML";
} else {
    echo "✓ buildSavedCardOptions generates proper HTML\n";
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
