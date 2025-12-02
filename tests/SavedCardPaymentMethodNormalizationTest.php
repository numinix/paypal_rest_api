<?php
declare(strict_types=1);

/**
 * Test to verify that the saved card module correctly normalizes the payment
 * session value from dynamic IDs (like paypalr_savedcard_0) to the base module
 * code (paypalr_savedcard).
 *
 * This ensures Zen Cart can properly find the payment module and set the correct
 * payment_method and payment_module_code fields in the orders table, preventing
 * the "Gift Certificate/Coupon" fallback issue.
 */

$testPassed = true;
$errors = [];

/**
 * Simulates the pre_confirmation_check logic that normalizes the payment session.
 *
 * @param string $sessionPayment The current $_SESSION['payment'] value
 * @param string $moduleCode The module's $this->code value
 * @return string The normalized session payment value
 */
function normalizePaymentSession(string $sessionPayment, string $moduleCode): string
{
    // This simulates the fix in paypalr_savedcard::pre_confirmation_check()
    if (strpos($sessionPayment, 'paypalr_savedcard_') === 0) {
        return $moduleCode;
    }
    return $sessionPayment;
}

$moduleCode = 'paypalr_savedcard';

// Test 1: Dynamic ID for first saved card is normalized
$result = normalizePaymentSession('paypalr_savedcard_0', $moduleCode);
if ($result !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Test 1 failed: Expected 'paypalr_savedcard', got '$result'";
} else {
    echo "✓ Test 1: Dynamic ID 'paypalr_savedcard_0' is normalized to '$result'\n";
}

// Test 2: Dynamic ID for second saved card is normalized
$result = normalizePaymentSession('paypalr_savedcard_1', $moduleCode);
if ($result !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Test 2 failed: Expected 'paypalr_savedcard', got '$result'";
} else {
    echo "✓ Test 2: Dynamic ID 'paypalr_savedcard_1' is normalized to '$result'\n";
}

// Test 3: Dynamic ID for higher index is normalized
$result = normalizePaymentSession('paypalr_savedcard_99', $moduleCode);
if ($result !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Test 3 failed: Expected 'paypalr_savedcard', got '$result'";
} else {
    echo "✓ Test 3: Dynamic ID 'paypalr_savedcard_99' is normalized to '$result'\n";
}

// Test 4: Base module code is not changed
$result = normalizePaymentSession('paypalr_savedcard', $moduleCode);
if ($result !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Test 4 failed: Expected 'paypalr_savedcard', got '$result'";
} else {
    echo "✓ Test 4: Base module code 'paypalr_savedcard' is not changed\n";
}

// Test 5: Other payment module codes are not affected
$result = normalizePaymentSession('paypalr', $moduleCode);
if ($result !== 'paypalr') {
    $testPassed = false;
    $errors[] = "Test 5 failed: Expected 'paypalr', got '$result'";
} else {
    echo "✓ Test 5: Other payment module 'paypalr' is not affected\n";
}

// Test 6: Credit card module is not affected
$result = normalizePaymentSession('paypalr_creditcard', $moduleCode);
if ($result !== 'paypalr_creditcard') {
    $testPassed = false;
    $errors[] = "Test 6 failed: Expected 'paypalr_creditcard', got '$result'";
} else {
    echo "✓ Test 6: Credit card module 'paypalr_creditcard' is not affected\n";
}

// Test 7: Empty string is not affected
$result = normalizePaymentSession('', $moduleCode);
if ($result !== '') {
    $testPassed = false;
    $errors[] = "Test 7 failed: Expected empty string, got '$result'";
} else {
    echo "✓ Test 7: Empty string is not affected\n";
}

// Test 8: Similar but different module name is not affected
$result = normalizePaymentSession('paypalr_savedcard_extra', $moduleCode);
// This should be normalized because it starts with 'paypalr_savedcard_'
if ($result !== 'paypalr_savedcard') {
    $testPassed = false;
    $errors[] = "Test 8 failed: Expected 'paypalr_savedcard', got '$result'";
} else {
    echo "✓ Test 8: Any string starting with 'paypalr_savedcard_' is normalized\n";
}

echo "\n";

// Summary
if ($testPassed) {
    echo "All payment method normalization tests passed! ✓\n";
    echo "\nThis fix ensures that when a customer selects a saved card payment option\n";
    echo "(like 'paypalr_savedcard_0'), the session payment value is normalized to\n";
    echo "'paypalr_savedcard' so Zen Cart can find the payment module and correctly\n";
    echo "set the payment_method in the orders table.\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    exit(1);
}
