<?php
/**
 * Test that verifies the saved card options display the actual card brand
 * (VISA, MASTERCARD, AMEX, etc.) instead of generic "Card" or "CREDIT".
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Mock the constant
if (!defined('MODULE_PAYMENT_PAYPALR_SAVED_CARD_GENERIC')) {
    define('MODULE_PAYMENT_PAYPALR_SAVED_CARD_GENERIC', 'Card');
}

// Mock the zen_output_string function
if (!function_exists('zen_output_string')) {
    function zen_output_string($string) {
        return htmlspecialchars($string, ENT_QUOTES);
    }
}

// Simulate the buildSavedCardOptions brand selection logic
function getBrandForCard(array $card): string 
{
    return $card['brand'] ?: ($card['card_type'] ?: (MODULE_PAYMENT_PAYPALR_SAVED_CARD_GENERIC ?? 'Card'));
}

function buildTestCardLabel(array $card): string
{
    $brand = getBrandForCard($card);
    $card_label = $brand . ' ending in ' . $card['last_digits'];
    if (!empty($card['expiry'])) {
        $card_label .= ' (Exp: ' . $card['expiry'] . ')';
    }
    return $card_label;
}

// Test 1: Card with brand should use brand
$card1 = [
    'brand' => 'VISA',
    'card_type' => 'CREDIT',
    'last_digits' => '4242',
    'expiry' => '2025-12',
];
$label1 = buildTestCardLabel($card1);
if (strpos($label1, 'VISA ending in 4242') === false) {
    $testPassed = false;
    $errors[] = "Card with brand 'VISA' should show 'VISA ending in 4242', got: $label1";
} else {
    echo "✓ Card with brand 'VISA' correctly shows 'VISA ending in 4242'\n";
}

// Test 2: Card with empty brand but card_type should use card_type
$card2 = [
    'brand' => '',
    'card_type' => 'MASTERCARD',
    'last_digits' => '5555',
    'expiry' => '2026-03',
];
$label2 = buildTestCardLabel($card2);
if (strpos($label2, 'MASTERCARD ending in 5555') === false) {
    $testPassed = false;
    $errors[] = "Card with card_type 'MASTERCARD' should show 'MASTERCARD ending in 5555', got: $label2";
} else {
    echo "✓ Card with card_type 'MASTERCARD' correctly shows 'MASTERCARD ending in 5555'\n";
}

// Test 3: Card with neither brand nor card_type should use fallback
$card3 = [
    'brand' => '',
    'card_type' => '',
    'last_digits' => '1234',
    'expiry' => '2027-06',
];
$label3 = buildTestCardLabel($card3);
if (strpos($label3, 'Card ending in 1234') === false) {
    $testPassed = false;
    $errors[] = "Card without brand or card_type should show 'Card ending in 1234', got: $label3";
} else {
    echo "✓ Card without brand or card_type correctly falls back to 'Card ending in 1234'\n";
}

// Test 4: AMEX card brand
$card4 = [
    'brand' => 'AMEX',
    'card_type' => '',
    'last_digits' => '3782',
    'expiry' => '2025-09',
];
$label4 = buildTestCardLabel($card4);
if (strpos($label4, 'AMEX ending in 3782') === false) {
    $testPassed = false;
    $errors[] = "Card with brand 'AMEX' should show 'AMEX ending in 3782', got: $label4";
} else {
    echo "✓ Card with brand 'AMEX' correctly shows 'AMEX ending in 3782'\n";
}

// Test 5: DISCOVER card brand
$card5 = [
    'brand' => 'DISCOVER',
    'card_type' => 'CREDIT',
    'last_digits' => '6011',
    'expiry' => '2028-01',
];
$label5 = buildTestCardLabel($card5);
if (strpos($label5, 'DISCOVER ending in 6011') === false) {
    $testPassed = false;
    $errors[] = "Card with brand 'DISCOVER' should show 'DISCOVER ending in 6011', got: $label5";
} else {
    echo "✓ Card with brand 'DISCOVER' correctly shows 'DISCOVER ending in 6011'\n";
}

// Test 6: Verify expiry is included
if (strpos($label5, '(Exp: 2028-01)') === false) {
    $testPassed = false;
    $errors[] = "Card label should include expiry date, got: $label5";
} else {
    echo "✓ Card label correctly includes expiry date\n";
}

// Test 7: Card with no expiry should not have expiry text
$card7 = [
    'brand' => 'VISA',
    'card_type' => '',
    'last_digits' => '9999',
    'expiry' => '',
];
$label7 = buildTestCardLabel($card7);
if (strpos($label7, 'Exp:') !== false) {
    $testPassed = false;
    $errors[] = "Card without expiry should not show expiry text, got: $label7";
} else {
    echo "✓ Card without expiry correctly omits expiry text\n";
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
