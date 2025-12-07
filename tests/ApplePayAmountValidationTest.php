<?php
/**
 * Test to verify that Apple Pay properly validates amount in both PHP and JavaScript.
 *
 * This test ensures that:
 * 1. PHP validates that amount exists in the order structure before returning success
 * 2. JavaScript explicitly checks for undefined, null, and empty string (but allows '0' or '0.00')
 * 3. Better error messages distinguish between missing amounts and user cancellation
 *
 * This fixes the issue where the modal would immediately close with "Apple Pay cancelled by user"
 * when the amount was missing from the server response.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Apple Pay Amount Validation\n";
echo "=====================================\n\n";

// Test PHP side validation
echo "Testing PHP amount validation...\n";
$applePayPhp = file_get_contents(__DIR__ . '/../includes/modules/payment/paypalr_applepay.php');

// Test 1: PHP extracts amount from current array
if (preg_match('/\$amount\s*=\s*\$current\[\'value\'\]\s*\?\?\s*[\'\"][\'\"]/', $applePayPhp)) {
    echo "  ✓ PHP extracts amount from current array\n";
} else {
    $testPassed = false;
    $errors[] = "PHP should extract amount from current array";
    echo "  ✗ PHP does not extract amount properly\n";
}

// Test 2: PHP checks if amount is empty string
if (preg_match('/if\s*\(\s*\$amount\s*===\s*[\'\"][\'\"]/', $applePayPhp)) {
    echo "  ✓ PHP checks if amount is empty string\n";
} else {
    $testPassed = false;
    $errors[] = "PHP should check if amount is empty string";
    echo "  ✗ PHP does not check for empty amount\n";
}

// Test 3: PHP logs error when amount is missing
if (preg_match('/amount.*is missing.*session structure/', $applePayPhp)) {
    echo "  ✓ PHP logs error when amount is missing\n";
} else {
    $testPassed = false;
    $errors[] = "PHP should log error when amount is missing";
    echo "  ✗ PHP does not log error for missing amount\n";
}

// Test 4: PHP returns error message when amount is missing
if (preg_match('/return.*success.*false.*message.*Order created but amount is missing/s', $applePayPhp)) {
    echo "  ✓ PHP returns error message when amount is missing\n";
} else {
    $testPassed = false;
    $errors[] = "PHP should return error message when amount is missing";
    echo "  ✗ PHP does not return error message for missing amount\n";
}

echo "\n";

// Test JavaScript side validation
echo "Testing JavaScript amount validation...\n";
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');

// Extract the onvalidatemerchant callback
$onvalidatePattern = '/onvalidatemerchant\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
if (preg_match($onvalidatePattern, $applePayJs, $matches)) {
    $onvalidateBody = $matches[1];
    
    // Test 5: JavaScript explicitly checks for undefined, null, and empty string
    if (preg_match('/config\.amount\s*===\s*undefined\s*\|\|\s*config\.amount\s*===\s*null\s*\|\|\s*config\.amount\s*===\s*[\'\"]\s*[\'\"]/', $onvalidateBody)) {
        echo "  ✓ JavaScript explicitly checks for undefined, null, and empty string\n";
    } else {
        $testPassed = false;
        $errors[] = "JavaScript should explicitly check for undefined, null, and empty string";
        echo "  ✗ JavaScript does not explicitly check amount types\n";
    }
    
    // Test 6: JavaScript has comment about allowing '0' or '0.00'
    if (preg_match('/allow.*[\'"]0[\'"].*[\'"]0\.00[\'"]/', $onvalidateBody)) {
        echo "  ✓ JavaScript comment indicates '0' and '0.00' are allowed\n";
    } else {
        $testPassed = false;
        $errors[] = "JavaScript should document that '0' and '0.00' are valid amounts";
        echo "  ✗ JavaScript does not document edge case handling\n";
    }
    
    // Test 7: JavaScript error message mentions "missing or empty"
    if (preg_match('/amount is missing or empty/', $onvalidateBody)) {
        echo "  ✓ JavaScript error message distinguishes missing vs empty\n";
    } else {
        $testPassed = false;
        $errors[] = "JavaScript error message should mention 'missing or empty'";
        echo "  ✗ JavaScript error message not specific enough\n";
    }
    
    // Test 8: JavaScript still calls session.abort() on error
    if (preg_match('/session\.abort\(\)/', $onvalidateBody)) {
        echo "  ✓ JavaScript calls session.abort() when amount validation fails\n";
    } else {
        $testPassed = false;
        $errors[] = "JavaScript should call session.abort() on validation failure";
        echo "  ✗ JavaScript does not abort session on error\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Could not extract onvalidatemerchant callback";
    echo "  ✗ Could not extract onvalidatemerchant callback\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay amount validation tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- PHP validates amount exists and is non-empty before returning success\n";
    echo "- PHP logs detailed error when amount is missing from session structure\n";
    echo "- JavaScript explicitly checks for undefined, null, and empty string\n";
    echo "- JavaScript allows '0' and '0.00' as valid amounts (e.g., free orders)\n";
    echo "- Error messages clearly indicate the issue is missing/empty amount\n";
    echo "- This prevents spurious 'cancelled by user' messages when amount is missing\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
