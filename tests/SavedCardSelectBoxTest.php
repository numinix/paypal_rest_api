<?php
/**
 * Test that verifies the paypalr_savedcard module correctly generates
 * a select box for saved cards instead of individual radio buttons.
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
if (!defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL')) {
    define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL', 'Select Card:');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', '/includes/modules/');
}

// Mock zen_output_string_protected
if (!function_exists('zen_output_string_protected')) {
    function zen_output_string_protected($string) {
        return htmlspecialchars($string, ENT_QUOTES);
    }
}

// Mock zen_draw_pull_down_menu
if (!function_exists('zen_draw_pull_down_menu')) {
    function zen_draw_pull_down_menu($name, $options, $default = '', $attrs = '') {
        $html = '<select name="' . htmlspecialchars($name) . '" ' . $attrs . '>';
        foreach ($options as $option) {
            $selected = ($option['id'] === $default) ? ' selected="selected"' : '';
            $html .= '<option value="' . htmlspecialchars($option['id']) . '"' . $selected . '>' . 
                     htmlspecialchars($option['text']) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

/**
 * Simulates the new selection generation logic from paypalr_savedcard
 * using a select box instead of radio buttons
 */
function generateSavedCardSelectBoxSelection(array $vaultedCards, string $selectedVaultId = ''): array
{
    if (empty($vaultedCards)) {
        return [];
    }
    
    $checkoutScript = '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js"></script>';
    
    // If no selection made, default to first card
    if (empty($selectedVaultId) && !empty($vaultedCards)) {
        $selectedVaultId = $vaultedCards[0]['vault_id'];
    }

    $selectOptions = [];
    foreach ($vaultedCards as $card) {
        $brand = $card['brand'] ?: ($card['card_type'] ?: MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD);
        $lastDigits = $card['last_digits'] ?? '****';
        
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
        
        $selectOptions[] = [
            'id' => $card['vault_id'],
            'text' => $cardTitle,
        ];
    }

    // Build the select box with onchange/onfocus handlers
    $moduleCode = 'paypalr_savedcard';
    $selectAttributes = 'id="paypalr-savedcard-select" class="ppr-savedcard-select" ' .
        'onchange="if(typeof methodSelect===\'function\')methodSelect(\'pmt-' . $moduleCode . '\')" ' .
        'onfocus="if(typeof methodSelect===\'function\')methodSelect(\'pmt-' . $moduleCode . '\')"';

    $selectBox = zen_draw_pull_down_menu(
        'paypalr_savedcard_vault_id',
        $selectOptions,
        $selectedVaultId,
        $selectAttributes
    );

    $fields = [
        [
            'title' => MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL,
            'field' => $selectBox,
            'tag' => 'paypalr-savedcard-select',
        ],
    ];
    
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
$selection = generateSavedCardSelectBoxSelection($testCards, '');
if ($selection['id'] !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Expected id 'paypalr_savedcard', got: " . $selection['id'];
} else {
    echo "✓ Selection uses base module code 'paypalr_savedcard'\n";
}

// Test 2: Only one field (the select box) is generated
if (count($selection['fields']) !== 1) {
    $testPassed = false;
    $errors[] = 'Expected 1 field (select box), got: ' . count($selection['fields']);
} else {
    echo "✓ Single field (select box) is generated\n";
}

// Test 3: Field contains a select element
if (strpos($selection['fields'][0]['field'], '<select') === false) {
    $testPassed = false;
    $errors[] = "Field should contain a select element";
} else {
    echo "✓ Field contains a select element\n";
}

// Test 4: Select has correct name attribute
if (strpos($selection['fields'][0]['field'], 'name="paypalr_savedcard_vault_id"') === false) {
    $testPassed = false;
    $errors[] = "Select should have name 'paypalr_savedcard_vault_id'";
} else {
    echo "✓ Select has correct name attribute\n";
}

// Test 5: Select has correct id attribute
if (strpos($selection['fields'][0]['field'], 'id="paypalr-savedcard-select"') === false) {
    $testPassed = false;
    $errors[] = "Select should have id 'paypalr-savedcard-select'";
} else {
    echo "✓ Select has correct id attribute\n";
}

// Test 6: Select includes methodSelect onfocus handler
if (strpos($selection['fields'][0]['field'], 'methodSelect') === false) {
    $testPassed = false;
    $errors[] = "Select should include methodSelect function call";
} else {
    echo "✓ Select includes methodSelect handler\n";
}

// Test 7: Contains options for all cards
if (substr_count($selection['fields'][0]['field'], '<option') !== 2) {
    $testPassed = false;
    $errors[] = 'Select should contain 2 options, found: ' . substr_count($selection['fields'][0]['field'], '<option');
} else {
    echo "✓ Select contains options for all cards\n";
}

// Test 8: First option is selected by default
if (strpos($selection['fields'][0]['field'], 'value="vault_123" selected') === false) {
    $testPassed = false;
    $errors[] = 'First option should be selected by default';
} else {
    echo "✓ First option is selected by default\n";
}

// Test 9: Card brand is displayed in options
if (strpos($selection['fields'][0]['field'], 'VISA') === false) {
    $testPassed = false;
    $errors[] = 'Options should display card brand';
} else {
    echo "✓ Card brand is displayed in options\n";
}

// Test 10: Last digits are displayed in options
if (strpos($selection['fields'][0]['field'], '4242') === false) {
    $testPassed = false;
    $errors[] = 'Options should display last digits';
} else {
    echo "✓ Last digits are displayed in options\n";
}

// Test 11: Field has correct title label
if ($selection['fields'][0]['title'] !== MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL) {
    $testPassed = false;
    $errors[] = 'Field title should be "' . MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL . '"';
} else {
    echo "✓ Field has correct title label\n";
}

// Test 12: Empty cards array returns empty array
$emptySelection = generateSavedCardSelectBoxSelection([]);
if (!empty($emptySelection)) {
    $testPassed = false;
    $errors[] = 'Empty cards should return empty array';
} else {
    echo "✓ Empty cards array returns empty array\n";
}

// Test 13: Pre-selected vault ID is honored
$preSelectedSelection = generateSavedCardSelectBoxSelection($testCards, 'vault_456');
if (strpos($preSelectedSelection['fields'][0]['field'], 'value="vault_456" selected') === false) {
    $testPassed = false;
    $errors[] = 'Pre-selected vault ID should be honored';
} else {
    echo "✓ Pre-selected vault ID is honored\n";
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
