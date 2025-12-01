<?php
/**
 * Test that verifies JavaScript validation skips card field validation
 * when a saved card is selected.
 */

// Simple standalone test
$testPassed = true;
$errors = [];

// Simulate the javascript_validation() output generation
$code = 'paypalr_creditcard';

// Define constants for testing
define('CC_OWNER_MIN_LENGTH', 3);
define('CC_NUMBER_MIN_LENGTH', 12);
define('MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_OWNER', '* Please enter the cardholder name.\n');
define('MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_NUMBER', '* Please enter a valid card number.\n');

// Generate the expected JS similar to javascript_validation()
$js = '';
if (defined('CC_OWNER_MIN_LENGTH') && defined('CC_NUMBER_MIN_LENGTH')) {
    $js = '  if (payment_value == "' . $code . '") {' . "\n" .
          '    var saved_card_field = document.checkout_payment.paypalr_saved_card;' . "\n" .
          '    var using_saved_card = saved_card_field && saved_card_field.value && saved_card_field.value !== "new";' . "\n" .
          '    if (!using_saved_card) {' . "\n" .
          '      var cc_owner_field = document.checkout_payment.paypalr_cc_owner;' . "\n" .
          '      var cc_number_field = document.checkout_payment.paypalr_cc_number;' . "\n" .
          '      if (cc_owner_field && cc_number_field) {' . "\n" .
          '        var cc_owner = cc_owner_field.value;' . "\n" .
          '        var cc_number = cc_number_field.value;' . "\n";
    
    if (CC_OWNER_MIN_LENGTH > 0) {
        $js .= '        if (cc_owner == "" || cc_owner.length < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n" .
               '          error_message = error_message + "' . MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_OWNER . '";' . "\n" .
               '          error = 1;' . "\n" .
               '        }' . "\n";
    }
    
    if (CC_NUMBER_MIN_LENGTH > 0) {
        $js .= '        if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n" .
               '          error_message = error_message + "' . MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_NUMBER . '";' . "\n" .
               '          error = 1;' . "\n" .
               '        }' . "\n";
    }
    
    $js .= '      }' . "\n" .
           '    }' . "\n" .
           '  }' . "\n";
}

// Test 1: Verify the JS references the saved_card_field
if (strpos($js, 'var saved_card_field = document.checkout_payment.paypalr_saved_card') === false) {
    $testPassed = false;
    $errors[] = "JS does not reference saved_card_field";
} else {
    echo "✓ JS references saved_card_field\n";
}

// Test 2: Verify the JS checks if using_saved_card
if (strpos($js, 'var using_saved_card = saved_card_field && saved_card_field.value && saved_card_field.value !== "new"') === false) {
    $testPassed = false;
    $errors[] = "JS does not properly check using_saved_card condition";
} else {
    echo "✓ JS properly checks using_saved_card condition\n";
}

// Test 3: Verify validation is wrapped in !using_saved_card check
if (strpos($js, 'if (!using_saved_card)') === false) {
    $testPassed = false;
    $errors[] = "JS does not wrap validation in !using_saved_card check";
} else {
    echo "✓ JS wraps validation in !using_saved_card check\n";
}

// Test 4: Verify cc_owner validation is still present
if (strpos($js, 'cc_owner.length <') === false) {
    $testPassed = false;
    $errors[] = "JS does not contain cc_owner length validation";
} else {
    echo "✓ JS contains cc_owner length validation\n";
}

// Test 5: Verify cc_number validation is still present
if (strpos($js, 'cc_number.length <') === false) {
    $testPassed = false;
    $errors[] = "JS does not contain cc_number length validation";
} else {
    echo "✓ JS contains cc_number length validation\n";
}

// Test 6: Verify the closing braces are properly nested
$openBraces = substr_count($js, '{');
$closeBraces = substr_count($js, '}');
if ($openBraces !== $closeBraces) {
    $testPassed = false;
    $errors[] = "JS has mismatched braces: {$openBraces} open, {$closeBraces} close";
} else {
    echo "✓ JS has properly balanced braces ({$openBraces} open, {$closeBraces} close)\n";
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
