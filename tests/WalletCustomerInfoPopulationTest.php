<?php
/**
 * Test that verifies wallet payment modules populate customer information from PayPal response.
 * 
 * Bug fix: When using wallet payments (Google Pay, Apple Pay, Venmo, Pay Later) from the cart
 * or product page, customer information was missing from the order because the customer didn't
 * go through the normal checkout flow. This test verifies that the populateOrderCustomerInfo
 * method exists in all wallet modules to extract customer data from PayPal's response.
 */

$testPassed = true;
$errors = [];

// Wallet modules to test
$walletModules = [
    'paypalr_googlepay' => 'google_pay',
    'paypalr_applepay' => 'apple_pay',
    'paypalr_venmo' => 'venmo',
    'paypalr_paylater' => 'paylater',
];

foreach ($walletModules as $moduleName => $paymentSource) {
    $modulePath = __DIR__ . "/../includes/modules/payment/$moduleName.php";
    
    if (!file_exists($modulePath)) {
        $errors[] = "Error: $moduleName.php not found at $modulePath";
        continue;
    }
    
    $moduleContent = file_get_contents($modulePath);
    
    // Test 1: Check that populateOrderCustomerInfo method exists
    if (strpos($moduleContent, 'protected function populateOrderCustomerInfo') === false) {
        $testPassed = false;
        $errors[] = "$moduleName: populateOrderCustomerInfo method not found";
    } else {
        echo "✓ $moduleName has populateOrderCustomerInfo method\n";
    }
    
    // Test 2: Check that it's called in before_process
    if (strpos($moduleContent, '$this->populateOrderCustomerInfo($order, $response)') === false) {
        $testPassed = false;
        $errors[] = "$moduleName: populateOrderCustomerInfo not called in before_process";
    } else {
        echo "✓ $moduleName calls populateOrderCustomerInfo in before_process\n";
    }
    
    // Test 3: Check that it extracts email from payment_source
    $emailPattern = "/\\\$payment_source\['email_address'\]/";
    if (preg_match($emailPattern, $moduleContent) === 0) {
        $testPassed = false;
        $errors[] = "$moduleName: email extraction from payment_source not found";
    } else {
        echo "✓ $moduleName extracts email from payment_source\n";
    }
    
    // Test 4: Check that it updates order->customer['email_address']
    $updatePattern = "/\\\$order->customer\['email_address'\]/";
    if (preg_match($updatePattern, $moduleContent) === 0) {
        $testPassed = false;
        $errors[] = "$moduleName: order customer email update not found";
    } else {
        echo "✓ $moduleName updates order customer email\n";
    }
    
    // Test 5: Check that it uses payer as fallback
    if (strpos($moduleContent, '$payer = $paypalResponse[\'payer\']') === false) {
        $testPassed = false;
        $errors[] = "$moduleName: payer fallback not found";
    } else {
        echo "✓ $moduleName has payer fallback logic\n";
    }
}

// Also check that PayPalCommon::updateOrderHistory has function_exists check
$commonPath = __DIR__ . '/../includes/modules/payment/paypal/paypal_common.php';
if (file_exists($commonPath)) {
    $commonContent = file_get_contents($commonPath);
    
    // Test 6: Check for zen_update_orders_history function_exists check
    if (strpos($commonContent, "function_exists('zen_update_orders_history')") === false) {
        $testPassed = false;
        $errors[] = "PayPalCommon: zen_update_orders_history function_exists check not found";
    } else {
        echo "✓ PayPalCommon has zen_update_orders_history function_exists check\n";
    }
    
    // Test 7: Check for error_log fallback when function doesn't exist
    if (strpos($commonContent, 'zen_update_orders_history not available') === false) {
        $testPassed = false;
        $errors[] = "PayPalCommon: error_log fallback not found";
    } else {
        echo "✓ PayPalCommon has error_log fallback for missing function\n";
    }
} else {
    $testPassed = false;
    $errors[] = "PayPalCommon not found at $commonPath";
}

if (!$testPassed) {
    echo "\n❌ Test FAILED\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

echo "\n✅ All wallet customer info population tests passed!\n";
exit(0);
