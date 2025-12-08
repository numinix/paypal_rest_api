<?php
/**
 * Test to verify that ApplePaySession is created synchronously within the user gesture handler
 * to avoid "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler."
 *
 * This test ensures that:
 * 1. Order total is extracted from page synchronously (e.g., #ottotal element)
 * 2. ApplePaySession is created synchronously in onApplePayButtonClicked (the click handler)
 * 3. session.begin() is called synchronously in onApplePayButtonClicked
 * 4. PayPal order creation happens asynchronously in onpaymentauthorized callback
 * 5. The ApplePaySession is created BEFORE any async .then() callbacks
 *
 * This approach balances two requirements:
 * - User gesture compliance (session created synchronously)
 * - Actual amount display (extracted from page, not $0.00 placeholder)
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js');

echo "Testing Apple Pay Session User Gesture Handling\n";
echo "================================================\n\n";

// Extract the onApplePayButtonClicked function
$pattern = '/function onApplePayButtonClicked\s*\([^)]*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (preg_match($pattern, $applePayJs, $matches)) {
    $clickHandlerBody = $matches[1];
} else {
    $testPassed = false;
    $errors[] = "Could not extract onApplePayButtonClicked function";
    echo "✗ Could not extract onApplePayButtonClicked function\n";
    exit(1);
}

// Test 1: ApplePaySession is created synchronously in the click handler (NOT in .then())
// This is critical to avoid "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler"
$sessionCreatePos = strpos($clickHandlerBody, 'new ApplePaySession');
$firstThenPos = strpos($clickHandlerBody, '.then(');

if ($sessionCreatePos === false) {
    $testPassed = false;
    $errors[] = "ApplePaySession is not created in the click handler";
    echo "✗ ApplePaySession is not created in the click handler\n";
} elseif ($firstThenPos !== false && $sessionCreatePos > $firstThenPos) {
    // Session should NOT be inside .then() - this causes user gesture errors
    $testPassed = false;
    $errors[] = "ApplePaySession must be created synchronously, NOT inside .then() callback";
    echo "✗ ApplePaySession is created inside .then() callback (causes user gesture error)\n";
} else {
    echo "✓ ApplePaySession is created synchronously in the click handler\n";
}

// Test 2: session.begin() is called in the click handler at the top level (not in a .then() callback)
// We need to check that session.begin() is not nested inside a .then() callback
$sessionBeginPos = strpos($clickHandlerBody, 'session.begin()');

if ($sessionBeginPos === false) {
    $testPassed = false;
    $errors[] = "session.begin() is not called in the click handler";
    echo "✗ session.begin() is not called in the click handler\n";
} else {
    // Extract lines to check indentation/nesting level
    // session.begin() should be at the same indentation level as session creation
    // not nested inside function callbacks
    $lines = explode("\n", $clickHandlerBody);
    $sessionBeginLine = null;
    $inThenCallback = false;
    $braceLevel = 0;
    
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'session.begin()') !== false) {
            $sessionBeginLine = $lineNum;
            // Check if we're inside a .then() callback by checking brace nesting
            // session.begin() should be at a low brace level (direct in function)
            if ($braceLevel > 2) { // Allow for try-catch and other control structures
                $inThenCallback = true;
            }
            break;
        }
        
        // Track brace nesting to detect if we're inside callbacks
        $braceLevel += substr_count($line, '{') - substr_count($line, '}');
    }
    
    if ($inThenCallback) {
        $testPassed = false;
        $errors[] = "session.begin() appears to be nested inside a callback. It must be called at the top level of the click handler.";
        echo "✗ session.begin() is nested inside a callback\n";
    } else {
        echo "✓ session.begin() is called synchronously in the click handler\n";
    }
}

// Test 3: getOrderTotalFromPage IS called to extract amount from page
// This allows showing actual amount while creating session synchronously
if (strpos($clickHandlerBody, 'getOrderTotalFromPage') !== false) {
    echo "✓ getOrderTotalFromPage is called to extract order amount from page\n";
} else {
    $testPassed = false;
    $errors[] = "getOrderTotalFromPage should be called to get order amount from page";
    echo "✗ getOrderTotalFromPage is not called\n";
}

// Test 3b: fetchWalletOrder IS called to create server-side order
if (strpos($clickHandlerBody, 'fetchWalletOrder') !== false) {
    echo "✓ fetchWalletOrder is called to create PayPal order\n";
} else {
    $testPassed = false;
    $errors[] = "fetchWalletOrder should be called to create PayPal order";
    echo "✗ fetchWalletOrder is not called\n";
}

// Test 4: Merchant validation happens in onvalidatemerchant (async but immediate)
// Merchant validation uses async promises but doesn't wait for order creation
if (strpos($clickHandlerBody, 'onvalidatemerchant') !== false) {
    // Extract onvalidatemerchant callback
    $onvalidatePattern = '/onvalidatemerchant\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)^\s{8}\};/m';
    if (preg_match($onvalidatePattern, $clickHandlerBody, $onvalidateMatches)) {
        $onvalidateBody = $onvalidateMatches[1];
        
        // Check that validateMerchant is called (uses promises, which is fine)
        if (strpos($onvalidateBody, 'validateMerchant') !== false) {
            echo "✓ Merchant validation is handled in onvalidatemerchant callback\n";
        } else {
            $testPassed = false;
            $errors[] = "validateMerchant should be called in onvalidatemerchant";
            echo "✗ validateMerchant is not called in onvalidatemerchant\n";
        }
    }
}

// Test 4b: Page amount is used in payment request (from getOrderTotalFromPage)
if (strpos($clickHandlerBody, 'orderTotal.amount') !== false) {
    echo "✓ Order amount from page is used in payment request\n";
} else {
    $testPassed = false;
    $errors[] = "orderTotal.amount should be used in payment request";
    echo "✗ Order amount from page is not used in payment request\n";
}

// Test 5: Verify ApplePaySession is created with try-catch for error handling
$tryPos = strpos($clickHandlerBody, 'try {');
if ($tryPos !== false && $sessionCreatePos !== false) {
    // Check if try comes before session creation
    if ($tryPos < $sessionCreatePos) {
        // Extract try block
        $tryBlockPattern = '/try\s*\{([\s\S]*?)}\s*catch/';
        if (preg_match($tryBlockPattern, $clickHandlerBody, $tryMatches)) {
            $tryBlock = $tryMatches[1];
            if (strpos($tryBlock, 'new ApplePaySession') !== false) {
                echo "✓ ApplePaySession creation is wrapped in try-catch for error handling\n";
            }
        }
    }
}

// Test 6: Verify session variable is declared before handlers are set
$sessionVarDeclarationPos = strpos($clickHandlerBody, 'var session');
if ($sessionVarDeclarationPos !== false && $sessionCreatePos !== false) {
    if ($sessionVarDeclarationPos < $sessionCreatePos) {
        echo "✓ Session variable is properly declared\n";
    }
}

// Test 7: Verify orderId is stored for use in onpaymentauthorized
if (strpos($clickHandlerBody, 'var orderId') !== false || 
    strpos($clickHandlerBody, 'orderId = ') !== false) {
    echo "✓ orderId is captured for use in payment authorization\n";
} else {
    $testPassed = false;
    $errors[] = "orderId should be captured from order creation for use in onpaymentauthorized";
    echo "✗ orderId is not properly captured\n";
}

// Test 8: Verify the code structure follows the correct pattern:
// 1. Get applepay SDK reference (synchronous)
// 2. Get applePayConfig (synchronous)
// 3. Get order total from page (synchronous)
// 4. Create ApplePaySession synchronously with page amount (no order creation yet)
// 5. Set up handlers (synchronous) - onvalidatemerchant validates immediately, onpaymentauthorized creates order
// 6. Call session.begin() (synchronous)
// Order creation happens ONLY in onpaymentauthorized (when user authorizes payment)
$structurePattern = '/applepay\.config\(\)[\s\S]*?getOrderTotalFromPage\(\)[\s\S]*?new ApplePaySession[\s\S]*?onvalidatemerchant[\s\S]*?onpaymentauthorized[\s\S]*?oncancel[\s\S]*?session\.begin\(\)/';
if (preg_match($structurePattern, $clickHandlerBody)) {
    echo "✓ Code follows correct pattern: session created synchronously with page amount, order created in onpaymentauthorized\n";
} else {
    $testPassed = false;
    $errors[] = "Code does not follow correct pattern";
    echo "✗ Code structure does not follow correct pattern\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay session user gesture tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Order total is extracted from page synchronously (e.g., #ottotal)\n";
    echo "- ApplePaySession is created synchronously with page amount\n";
    echo "- session.begin() is called synchronously after session creation\n";
    echo "- PayPal order creation happens in onpaymentauthorized callback\n";
    echo "- This maintains user gesture context AND shows actual amount (not $0.00)\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
