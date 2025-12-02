<?php
/**
 * Test that verifies the paypalr_savedcard module correctly generates
 * a single payment selection with radio buttons for each saved card.
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Mock the constants
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE', '%s ending in %s');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_EXPIRY')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_EXPIRY', ' (Exp: %s)');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_SHORT')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_SHORT', 'Pay with Saved Card');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD', 'Card');
}

// Mock zen_output_string_protected
if (!function_exists('zen_output_string_protected')) {
    function zen_output_string_protected($string) {
        return htmlspecialchars($string, ENT_QUOTES);
    }
}

// Mock zen_draw_radio_field
if (!function_exists('zen_draw_radio_field')) {
    function zen_draw_radio_field($name, $value, $checked = false, $attrs = '') {
        $checkedAttr = $checked ? ' checked="checked"' : '';
        return '<input type="radio" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"' . $checkedAttr . ' ' . $attrs . '>';
    }
}

// Mock DIR_WS_MODULES
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', '/includes/modules/');
}

/**
 * Simulates the new selection generation logic from paypalr_savedcard
 * Returns a single selection with radio buttons for each card in fields array
 */
function generateSavedCardSelection(array $vaultedCards): array
{
    if (empty($vaultedCards)) {
        return [];
    }
    
    $checkoutScript = '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js"></script>';
    
    $fields = [];
    foreach ($vaultedCards as $index => $card) {
        $brand = $card['brand'] ?: ($card['card_type'] ?: MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD);
        $lastDigits = $card['last_digits'] ?? '****';
        
        // Build card label
        $cardTitle = sprintf(
            MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE,
            zen_output_string_protected($brand),
            zen_output_string_protected($lastDigits)
        );
        
        if (!empty($card['expiry'])) {
            $cardTitle .= sprintf(
                MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_EXPIRY,
                zen_output_string_protected(formatTestExpiry($card['expiry']))
            );
        }
        
        $vaultId = $card['vault_id'];
        $isChecked = ($index === 0);
        $radioId = 'paypalr-savedcard-' . $index;
        
        $radioInput = zen_draw_radio_field(
            'paypalr_savedcard_vault_id',
            $vaultId,
            $isChecked,
            'id="' . $radioId . '" class="ppr-savedcard-radio"'
        );
        
        $fields[] = [
            'title' => '',
            'field' => '<label class="ppr-savedcard-option" for="' . $radioId . '">' . $radioInput . ' ' . $cardTitle . '</label>',
        ];
    }
    
    return [
        'id' => 'paypalr_savedcard',
        'module' => MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_SHORT . $checkoutScript,
        'fields' => $fields,
    ];
}

function formatTestExpiry(string $expiry): string
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $matches)) {
        return $matches[2] . '/' . $matches[1];
    }
    return $expiry;
}

// Test data - multiple saved cards
$testCards = [
    [
        'vault_id' => 'vault_123',
        'brand' => 'VISA',
        'card_type' => 'CREDIT',
        'last_digits' => '4242',
        'expiry' => '2025-12',
    ],
    [
        'vault_id' => 'vault_456',
        'brand' => 'MASTERCARD',
        'card_type' => 'DEBIT',
        'last_digits' => '5555',
        'expiry' => '2026-03',
    ],
];

// Test 1: Selection uses base module code
$selection = generateSavedCardSelection($testCards);
if ($selection['id'] !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Expected id 'paypalr_savedcard', got: " . $selection['id'];
} else {
    echo "✓ Selection uses base module code 'paypalr_savedcard'\n";
}

// Test 2: Multiple cards generate multiple fields (radio buttons)
if (count($selection['fields']) !== 2) {
    $testPassed = false;
    $errors[] = 'Expected 2 fields for 2 cards, got: ' . count($selection['fields']);
} else {
    echo "✓ Multiple cards generate radio button fields\n";
}

// Test 3: Each field contains a radio button with vault_id as value
foreach ($selection['fields'] as $index => $field) {
    if (strpos($field['field'], 'type="radio"') === false) {
        $testPassed = false;
        $errors[] = "Field $index should contain a radio button";
    }
    if (strpos($field['field'], 'paypalr_savedcard_vault_id') === false) {
        $testPassed = false;
        $errors[] = "Field $index should have name 'paypalr_savedcard_vault_id'";
    }
}
if ($testPassed) {
    echo "✓ Each field contains a radio button with vault_id name\n";
}

// Test 4: First radio button is checked by default
if (strpos($selection['fields'][0]['field'], 'checked="checked"') === false) {
    $testPassed = false;
    $errors[] = 'First radio button should be checked by default';
} else {
    echo "✓ First radio button is checked by default\n";
}

// Test 5: Second radio button is not checked
if (strpos($selection['fields'][1]['field'], 'checked="checked"') !== false) {
    $testPassed = false;
    $errors[] = 'Second radio button should not be checked';
} else {
    echo "✓ Second radio button is not checked\n";
}

// Test 6: Card brand is displayed
if (strpos($selection['fields'][0]['field'], 'VISA') === false) {
    $testPassed = false;
    $errors[] = 'First field should display card brand';
} else {
    echo "✓ Card brand is displayed in field\n";
}

// Test 7: Last digits are displayed
if (strpos($selection['fields'][0]['field'], '4242') === false) {
    $testPassed = false;
    $errors[] = 'First field should display last digits';
} else {
    echo "✓ Last digits are displayed in field\n";
}

// Test 8: Empty cards array returns empty array
$emptySelection = generateSavedCardSelection([]);
if (!empty($emptySelection)) {
    $testPassed = false;
    $errors[] = 'Empty cards should return empty array';
} else {
    echo "✓ Empty cards array returns empty array\n";
}

// Test 9: Single card generates one field
$singleCardSelection = generateSavedCardSelection([$testCards[0]]);
if (count($singleCardSelection['fields']) !== 1) {
    $testPassed = false;
    $errors[] = 'Single card should generate one field';
} else {
    echo "✓ Single card generates one field\n";
}

// Test 10: Card with no brand uses fallback
$noBrandCard = [
    [
        'vault_id' => 'vault_999',
        'brand' => '',
        'card_type' => '',
        'last_digits' => '1234',
        'expiry' => '2028-01',
    ],
];
$noBrandSelection = generateSavedCardSelection($noBrandCard);
if (strpos($noBrandSelection['fields'][0]['field'], MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD) === false) {
    $testPassed = false;
    $errors[] = 'Card with no brand should use fallback';
} else {
    echo "✓ Card with no brand uses fallback\n";
}

// Test 11: Card with card_type but no brand uses card_type
$cardTypeOnly = [
    [
        'vault_id' => 'vault_888',
        'brand' => '',
        'card_type' => 'DISCOVER',
        'last_digits' => '6011',
        'expiry' => '2029-01',
    ],
];
$cardTypeSelection = generateSavedCardSelection($cardTypeOnly);
if (strpos($cardTypeSelection['fields'][0]['field'], 'DISCOVER') === false) {
    $testPassed = false;
    $errors[] = 'Card with card_type but no brand should use card_type';
} else {
    echo "✓ Card with card_type but no brand uses card_type\n";
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
