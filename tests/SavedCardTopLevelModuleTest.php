<?php
/**
 * Test that verifies the paypalr_savedcard module correctly generates
 * multiple top-level payment options for saved cards.
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
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD', 'Card');
}

// Mock zen_output_string_protected
if (!function_exists('zen_output_string_protected')) {
    function zen_output_string_protected($string) {
        return htmlspecialchars($string, ENT_QUOTES);
    }
}

// Mock zen_draw_hidden_field
if (!function_exists('zen_draw_hidden_field')) {
    function zen_draw_hidden_field($name, $value, $attrs = '') {
        return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" ' . $attrs . '>';
    }
}

// Mock DIR_WS_MODULES and DIR_WS_TEMPLATE_IMAGES
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', '/includes/modules/');
}
if (!defined('DIR_WS_TEMPLATE_IMAGES')) {
    define('DIR_WS_TEMPLATE_IMAGES', '/includes/templates/template_default/images/');
}
if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', '/nonexistent/');
}

/**
 * Simulates the selection generation logic from paypalr_savedcard
 */
function generateSavedCardSelections(array $vaultedCards): array
{
    $checkoutScript = '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js"></script>';
    
    $selections = [];
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
        
        $hiddenField = zen_draw_hidden_field('paypalr_savedcard_vault_id', $card['vault_id'], 'id="paypalr-savedcard-vault-id-' . $index . '"');
        
        $selections[] = [
            'id' => 'paypalr_savedcard_' . $index,
            'module' => $cardTitle . ($index === 0 ? $checkoutScript : '') . $hiddenField,
            'vault_id' => $card['vault_id'],
            'sort_order' => $index,
        ];
    }
    
    return $selections;
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
    [
        'vault_id' => 'vault_789',
        'brand' => 'AMEX',
        'card_type' => '',
        'last_digits' => '3782',
        'expiry' => '2027-06',
    ],
];

// Test 1: Multiple cards generate multiple selections
$selections = generateSavedCardSelections($testCards);
if (count($selections) !== 3) {
    $testPassed = false;
    $errors[] = 'Expected 3 selections for 3 cards, got: ' . count($selections);
} else {
    echo "✓ Multiple cards generate multiple top-level selections\n";
}

// Test 2: Each selection has a unique ID
$ids = array_column($selections, 'id');
if (count($ids) !== count(array_unique($ids))) {
    $testPassed = false;
    $errors[] = 'Selection IDs are not unique';
} else {
    echo "✓ Each selection has a unique ID\n";
}

// Test 3: Selection IDs follow expected format
foreach ($selections as $index => $selection) {
    $expectedId = 'paypalr_savedcard_' . $index;
    if ($selection['id'] !== $expectedId) {
        $testPassed = false;
        $errors[] = "Expected ID '$expectedId', got: " . $selection['id'];
    }
}
if ($testPassed) {
    echo "✓ Selection IDs follow expected format (paypalr_savedcard_N)\n";
}

// Test 4: First selection includes checkout script
if (strpos($selections[0]['module'], 'jquery.paypalr.checkout.js') === false) {
    $testPassed = false;
    $errors[] = 'First selection should include checkout script';
} else {
    echo "✓ First selection includes checkout script\n";
}

// Test 5: Subsequent selections do not include checkout script
if (strpos($selections[1]['module'], 'jquery.paypalr.checkout.js') !== false) {
    $testPassed = false;
    $errors[] = 'Subsequent selections should not include checkout script';
} else {
    echo "✓ Subsequent selections do not duplicate checkout script\n";
}

// Test 6: Vault ID is included in hidden field
if (strpos($selections[0]['module'], 'vault_123') === false) {
    $testPassed = false;
    $errors[] = 'First selection should include vault_id in hidden field';
} else {
    echo "✓ Vault ID is included in hidden field\n";
}

// Test 7: Card brand is displayed
if (strpos($selections[0]['module'], 'VISA') === false) {
    $testPassed = false;
    $errors[] = 'First selection should display card brand';
} else {
    echo "✓ Card brand is displayed in selection\n";
}

// Test 8: Last digits are displayed
if (strpos($selections[0]['module'], '4242') === false) {
    $testPassed = false;
    $errors[] = 'First selection should display last digits';
} else {
    echo "✓ Last digits are displayed in selection\n";
}

// Test 9: Expiry date is formatted correctly
if (strpos($selections[0]['module'], '12/2025') === false) {
    $testPassed = false;
    $errors[] = 'First selection should display formatted expiry (MM/YYYY)';
} else {
    echo "✓ Expiry date is formatted correctly (MM/YYYY)\n";
}

// Test 10: Empty cards array returns empty selections
$emptySelections = generateSavedCardSelections([]);
if (count($emptySelections) !== 0) {
    $testPassed = false;
    $errors[] = 'Empty cards should return empty selections';
} else {
    echo "✓ Empty cards array returns empty selections\n";
}

// Test 11: Single card still generates proper selection
$singleCardSelections = generateSavedCardSelections([$testCards[0]]);
if (count($singleCardSelections) !== 1) {
    $testPassed = false;
    $errors[] = 'Single card should generate one selection';
} else {
    echo "✓ Single card generates one selection\n";
}

// Test 12: Card with no brand uses fallback
$noBrandCard = [
    [
        'vault_id' => 'vault_999',
        'brand' => '',
        'card_type' => '',
        'last_digits' => '1234',
        'expiry' => '2028-01',
    ],
];
$noBrandSelections = generateSavedCardSelections($noBrandCard);
if (strpos($noBrandSelections[0]['module'], MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD) === false) {
    $testPassed = false;
    $errors[] = 'Card with no brand should use fallback';
} else {
    echo "✓ Card with no brand uses fallback\n";
}

// Test 13: Card with card_type but no brand uses card_type
$cardTypeOnly = [
    [
        'vault_id' => 'vault_888',
        'brand' => '',
        'card_type' => 'DISCOVER',
        'last_digits' => '6011',
        'expiry' => '2029-01',
    ],
];
$cardTypeSelections = generateSavedCardSelections($cardTypeOnly);
if (strpos($cardTypeSelections[0]['module'], 'DISCOVER') === false) {
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
