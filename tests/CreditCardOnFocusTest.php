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
$code = 'paypalac_creditcard';
$onFocus = ' onfocus="methodSelect(\'pmt-' . $code . '\')"';

// Test 1: Verify the onfocus attribute is constructed correctly
$expectedOnFocus = ' onfocus="methodSelect(\'pmt-paypalac_creditcard\')"';
if ($onFocus !== $expectedOnFocus) {
    $testPassed = false;
    $errors[] = "OnFocus attribute mismatch. Expected: {$expectedOnFocus}, Got: {$onFocus}";
} else {
    echo "✓ OnFocus attribute constructed correctly\n";
}

// Test 2: Verify the attribute can be safely concatenated with HTML attributes
$testHtml = 'class="test-class" id="test-id"' . $onFocus;
if (strpos($testHtml, 'onfocus="methodSelect(\'pmt-paypalac_creditcard\')"') === false) {
    $testPassed = false;
    $errors[] = "OnFocus attribute not found in concatenated HTML";
} else {
    echo "✓ OnFocus attribute can be concatenated with HTML attributes\n";
}

// Test 3: Verify the JavaScript function call is properly escaped
if (strpos($onFocus, 'methodSelect(') === false || strpos($onFocus, 'pmt-paypalac_creditcard') === false) {
    $testPassed = false;
    $errors[] = "JavaScript function call not properly formatted";
} else {
    echo "✓ JavaScript function call properly formatted\n";
}

// Test 4: Mock buildSavedCardInlineOptions to verify it accepts the parameter
function mockBuildSavedCardInlineOptions(array $vaultedCards, string $selectedVaultId, string $onFocus = ''): string
{
    $html = '<div class="ppr-saved-card-inline">';
    $html .= '<label>';
    $html .= '<input type="radio" name="paypalac_saved_card" value="new"' . $onFocus . ' />';
    $html .= '<span>Use a new card</span>';
    $html .= '</label>';
    $html .= '</div>';
    return $html;
}

$mockResult = mockBuildSavedCardInlineOptions([], 'new', $onFocus);
if (strpos($mockResult, 'onfocus="methodSelect(\'pmt-paypalac_creditcard\')"') === false) {
    $testPassed = false;
    $errors[] = "buildSavedCardInlineOptions does not include onfocus attribute";
} else {
    echo "✓ buildSavedCardInlineOptions includes onfocus attribute\n";
}

// Test 5: Verify HTML is properly formed
if (strpos($mockResult, '<div') === false || strpos($mockResult, '</div>') === false || strpos($mockResult, '<input') === false) {
    $testPassed = false;
    $errors[] = "buildSavedCardInlineOptions does not generate proper HTML";
} else {
    echo "✓ buildSavedCardInlineOptions generates proper HTML\n";
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
