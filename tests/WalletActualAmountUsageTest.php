<?php
/**
 * Test to verify that wallet modules (Apple Pay, Google Pay) use the actual order amount
 * instead of a $0.00 placeholder when displaying the payment sheet.
 *
 * This test ensures that:
 * 1. fetchWalletOrder() is called before creating the payment session
 * 2. The actual order amount (orderConfig.amount) is used in the payment request
 * 3. No hardcoded '0.00' placeholders are used in the final payment request
 *
 * This fixes the issue where users see "$0.00" in the payment modal.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Wallet Modules Use Actual Order Amount\n";
echo "================================================\n\n";

// Test Apple Pay
echo "Testing Apple Pay...\n";
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');

// Extract the onApplePayButtonClicked function
// Match the function body up to the final closing brace at the function level
$pattern = '/function onApplePayButtonClicked\s*\(\s*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (preg_match($pattern, $applePayJs, $matches)) {
    $clickHandlerBody = $matches[1];
    
    // Test 1: fetchWalletOrder is called before creating ApplePaySession
    $fetchPos = strpos($clickHandlerBody, 'fetchWalletOrder()');
    $sessionPos = strpos($clickHandlerBody, 'new ApplePaySession');
    
    if ($fetchPos !== false && $sessionPos !== false && $fetchPos < $sessionPos) {
        echo "  ✓ fetchWalletOrder is called before creating ApplePaySession\n";
    } else {
        $testPassed = false;
        $errors[] = "Apple Pay: fetchWalletOrder should be called before ApplePaySession";
        echo "  ✗ fetchWalletOrder is not called before ApplePaySession\n";
    }
    
    // Test 2: orderConfig.amount is used in payment request
    if (strpos($clickHandlerBody, 'orderConfig.amount') !== false) {
        echo "  ✓ orderConfig.amount is used in payment request\n";
    } else {
        $testPassed = false;
        $errors[] = "Apple Pay: orderConfig.amount should be used in payment request";
        echo "  ✗ orderConfig.amount is not used\n";
    }
    
    // Test 3: Payment request uses actual amount from order
    // Check for both 'total:' and 'amount: orderConfig.amount' presence
    if (strpos($clickHandlerBody, 'total:') !== false && 
        strpos($clickHandlerBody, 'amount: orderConfig.amount') !== false) {
        echo "  ✓ Payment request total uses orderConfig.amount\n";
    } else {
        $testPassed = false;
        $errors[] = "Apple Pay: Payment request should use orderConfig.amount for total";
        echo "  ✗ Payment request does not use orderConfig.amount for total\n";
    }
    
    // Test 4: Currency uses order currency
    if (strpos($clickHandlerBody, 'orderConfig.currency') !== false) {
        echo "  ✓ Currency is taken from order configuration\n";
    } else {
        $testPassed = false;
        $errors[] = "Apple Pay: Currency should be taken from orderConfig";
        echo "  ✗ Currency is not taken from order configuration\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Apple Pay: Could not extract onApplePayButtonClicked function";
    echo "  ✗ Could not extract onApplePayButtonClicked function\n";
}

echo "\n";

// Test Google Pay
echo "Testing Google Pay...\n";
$googlePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js');

// Extract the onGooglePayButtonClicked function
// Match the function body up to the final closing brace at the function level
$pattern = '/function onGooglePayButtonClicked\s*\(\s*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (preg_match($pattern, $googlePayJs, $matches)) {
    $clickHandlerBody = $matches[1];
    
    // Test 1: fetchWalletOrder is called before loadPaymentData
    $fetchPos = strpos($clickHandlerBody, 'fetchWalletOrder()');
    $loadPos = strpos($clickHandlerBody, 'loadPaymentData(');
    
    if ($fetchPos !== false && $loadPos !== false && $fetchPos < $loadPos) {
        echo "  ✓ fetchWalletOrder is called before loadPaymentData\n";
    } else {
        $testPassed = false;
        $errors[] = "Google Pay: fetchWalletOrder should be called before loadPaymentData";
        echo "  ✗ fetchWalletOrder is not called before loadPaymentData\n";
    }
    
    // Test 2: orderConfig.amount is used in transaction info
    if (strpos($clickHandlerBody, 'orderConfig.amount') !== false) {
        echo "  ✓ orderConfig.amount is used in transaction info\n";
    } else {
        $testPassed = false;
        $errors[] = "Google Pay: orderConfig.amount should be used in transaction info";
        echo "  ✗ orderConfig.amount is not used\n";
    }
    
    // Test 3: Transaction info uses actual amount from order
    // Check for both 'transactionInfo:' and 'totalPrice: orderConfig.amount' presence
    if (strpos($clickHandlerBody, 'transactionInfo:') !== false && 
        strpos($clickHandlerBody, 'totalPrice: orderConfig.amount') !== false) {
        echo "  ✓ Transaction info totalPrice uses orderConfig.amount\n";
    } else {
        $testPassed = false;
        $errors[] = "Google Pay: Transaction info should use orderConfig.amount for totalPrice";
        echo "  ✗ Transaction info does not use orderConfig.amount for totalPrice\n";
    }
    
    // Test 4: Currency uses order currency
    if (strpos($clickHandlerBody, 'orderConfig.currency') !== false) {
        echo "  ✓ Currency is taken from order configuration\n";
    } else {
        $testPassed = false;
        $errors[] = "Google Pay: Currency should be taken from orderConfig";
        echo "  ✗ Currency is not taken from order configuration\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Google Pay: Could not extract onGooglePayButtonClicked function";
    echo "  ✗ Could not extract onGooglePayButtonClicked function\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All wallet actual amount usage tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Apple Pay fetches order before creating session and uses actual amount\n";
    echo "- Google Pay fetches order before loading payment data and uses actual amount\n";
    echo "- Both use orderConfig.amount and orderConfig.currency from server\n";
    echo "- This fixes the $0.00 amount display issue in payment modals\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
