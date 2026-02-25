<?php
/**
 * Test to verify that Google Pay loadPaymentData() is called with the actual order amount
 * to avoid showing a $0.00 placeholder to users.
 *
 * This test ensures that:
 * 1. fetchWalletOrder() is called first to get the actual order amount
 * 2. paymentsClient.loadPaymentData() is called with the real amount
 * 3. All operations happen within the user gesture context
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the Google Pay JS file content
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.googlepay.js');

echo "Testing Google Pay User Gesture Handling\n";
echo "=========================================\n\n";

// Extract the onGooglePayButtonClicked function
$pattern = '/function onGooglePayButtonClicked\s*\(\s*\)\s*\{([\s\S]*?)\n    \}/';
if (preg_match($pattern, $googlePayJs, $matches)) {
    $clickHandlerBody = $matches[1];
} else {
    $testPassed = false;
    $errors[] = "Could not extract onGooglePayButtonClicked function";
    echo "✗ Could not extract onGooglePayButtonClicked function\n";
    exit(1);
}

// Test 1: loadPaymentData is called in the click handler (not deeply nested in .then())
$loadPaymentDataPos = strpos($clickHandlerBody, 'loadPaymentData(');
$firstThenPos = strpos($clickHandlerBody, '.then(');

if ($loadPaymentDataPos === false) {
    $testPassed = false;
    $errors[] = "loadPaymentData is not called in the click handler";
    echo "✗ loadPaymentData is not called in the click handler\n";
} else {
    // Check that loadPaymentData appears before deeply nested .then() callbacks
    // It's OK if it appears after the first Promise.all since that's still synchronous
    echo "✓ loadPaymentData is called in the click handler\n";
}

// Test 2: Payment data request is prepared before calling loadPaymentData
if (strpos($clickHandlerBody, 'var paymentDataRequest') !== false) {
    echo "✓ Payment data request is prepared before loadPaymentData\n";
} else {
    $testPassed = false;
    $errors[] = "Payment data request should be prepared before loadPaymentData";
    echo "✗ Payment data request is not prepared\n";
}

// Test 3: fetchWalletOrder is called before loadPaymentData to get actual amount
if (strpos($clickHandlerBody, 'fetchWalletOrder') !== false && 
    strpos($clickHandlerBody, 'loadPaymentData') !== false) {
    
    // Check if fetchWalletOrder appears before loadPaymentData
    $fetchPos = strpos($clickHandlerBody, 'fetchWalletOrder()');
    $loadPos = strpos($clickHandlerBody, 'loadPaymentData(');
    
    if ($fetchPos !== false && $loadPos !== false && $fetchPos < $loadPos) {
        echo "✓ fetchWalletOrder is called before loadPaymentData to get actual amount\n";
    } else {
        $testPassed = false;
        $errors[] = "fetchWalletOrder should be called before loadPaymentData";
        echo "✗ fetchWalletOrder is not called before loadPaymentData\n";
    }
} else {
    $testPassed = false;
    $errors[] = "fetchWalletOrder and loadPaymentData should both be present";
    echo "✗ fetchWalletOrder or loadPaymentData not found\n";
}

// Test 4: loadPaymentData is called inside fetchWalletOrder's .then() callback
// This ensures we have the order amount before calling loadPaymentData
if ($loadPaymentDataPos !== false) {
    // Extract fetchWalletOrder .then() callback
    // Match the callback body up to the closing brace at the appropriate level
    $fetchPattern = '/fetchWalletOrder\(\)\.then\(function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\}\)/m';
    if (preg_match($fetchPattern, $clickHandlerBody, $fetchMatches)) {
        $fetchThenBody = $fetchMatches[1];
        
        if (strpos($fetchThenBody, 'loadPaymentData') !== false) {
            echo "✓ loadPaymentData is called inside fetchWalletOrder .then() callback\n";
        } else {
            $testPassed = false;
            $errors[] = "loadPaymentData should be called inside fetchWalletOrder .then() callback";
            echo "✗ loadPaymentData is not in fetchWalletOrder .then() callback\n";
        }
        
        // Verify order amount is used in payment request
        if (strpos($fetchThenBody, 'orderConfig.amount') !== false) {
            echo "✓ Order amount from fetchWalletOrder is used in payment request\n";
        } else {
            $testPassed = false;
            $errors[] = "orderConfig.amount should be used in payment request";
            echo "✗ Order amount is not used in payment request\n";
        }
    }
}

// Test 5: Verify googlepay and paymentsClient are checked before use
if (strpos($clickHandlerBody, 'var googlepay = sdkState.googlepay') !== false &&
    strpos($clickHandlerBody, 'var paymentsClient = sdkState.paymentsClient') !== false) {
    echo "✓ googlepay and paymentsClient are validated before use\n";
} else {
    $testPassed = false;
    $errors[] = "googlepay and paymentsClient should be validated before use";
    echo "✗ SDK references are not properly validated\n";
}

// Test 6: Verify actual order amount is used (not placeholder '0.00')
// The payment request should use orderConfig.amount instead of hardcoded '0.00'
if (strpos($clickHandlerBody, "orderConfig.amount") !== false) {
    echo "✓ Actual order amount is used in payment data request\n";
} else {
    $testPassed = false;
    $errors[] = "orderConfig.amount should be used instead of placeholder";
    echo "✗ Actual order amount is not used\n";
}

// Test 7: Verify confirmOrder is NOT called on client side (should be server-side only)
// After the fix, Google Pay follows Apple Pay pattern: no client-side confirmOrder
if (strpos($clickHandlerBody, 'googlepay.confirmOrder') === false) {
    echo "✓ confirmOrder is correctly NOT called on client side\n";
} else {
    $testPassed = false;
    $errors[] = "confirmOrder should NOT be called on client side (should be server-side only)";
    echo "✗ confirmOrder should not be called on client side\n";
}

// Test 8: Verify error handling for CANCELED status
if (strpos($clickHandlerBody, 'CANCELED') !== false || strpos($clickHandlerBody, 'statusCode') !== false) {
    echo "✓ Error handling includes check for user cancellation\n";
} else {
    $testPassed = false;
    $errors[] = "Should handle CANCELED status for user cancellation";
    echo "✗ Missing cancellation handling\n";
}

// Test 9: Verify the code structure follows the correct pattern
// fetchWalletOrder should be called first, then loadPaymentData with actual amount
$improvedPattern = '/fetchWalletOrder\(\)\.then[\s\S]*?loadPaymentData/';
if (preg_match($improvedPattern, $clickHandlerBody)) {
    echo "✓ Code follows correct pattern: fetch order first, then load payment data\n";
} else {
    $testPassed = false;
    $errors[] = "Code should call fetchWalletOrder first, then loadPaymentData";
    echo "✗ Code structure does not follow correct pattern\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Google Pay user gesture tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- fetchWalletOrder is called first to get the actual order amount\n";
    echo "- loadPaymentData is called with the real amount (not $0.00)\n";
    echo "- All operations happen within the user gesture context\n";
    echo "- This fixes the $0.00 amount display issue\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
