<?php
/**
 * Test to verify that ApplePaySession is created synchronously within the user gesture handler
 * to avoid "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler."
 *
 * This test ensures that:
 * 1. ApplePaySession is created in onApplePayButtonClicked (the click handler)
 * 2. session.begin() is called in onApplePayButtonClicked (synchronously)
 * 3. Order creation happens in onvalidatemerchant (asynchronously is OK)
 * 4. The ApplePaySession is created BEFORE any async operations (fetchWalletOrder)
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
$pattern = '/function onApplePayButtonClicked\s*\(\s*\)\s*\{([\s\S]*?)\n    \}/';
if (preg_match($pattern, $applePayJs, $matches)) {
    $clickHandlerBody = $matches[1];
} else {
    $testPassed = false;
    $errors[] = "Could not extract onApplePayButtonClicked function";
    echo "✗ Could not extract onApplePayButtonClicked function\n";
    exit(1);
}

// Test 1: ApplePaySession is created in the click handler (not in a .then() callback)
// We check that 'new ApplePaySession' appears BEFORE any '.then(' in the function
$sessionCreatePos = strpos($clickHandlerBody, 'new ApplePaySession');
$firstThenPos = strpos($clickHandlerBody, '.then(');

if ($sessionCreatePos === false) {
    $testPassed = false;
    $errors[] = "ApplePaySession is not created in the click handler";
    echo "✗ ApplePaySession is not created in the click handler\n";
} elseif ($firstThenPos !== false && $sessionCreatePos > $firstThenPos) {
    $testPassed = false;
    $errors[] = "ApplePaySession is created AFTER a .then() callback (inside async code). It must be created synchronously in the click handler.";
    echo "✗ ApplePaySession is created inside async code (after .then())\n";
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

// Test 3: fetchWalletOrder is NOT called before ApplePaySession creation
// Extract the code before ApplePaySession creation
if ($sessionCreatePos !== false) {
    $codeBeforeSessionCreate = substr($clickHandlerBody, 0, $sessionCreatePos);
    if (strpos($codeBeforeSessionCreate, 'fetchWalletOrder') !== false) {
        $testPassed = false;
        $errors[] = "fetchWalletOrder is called BEFORE ApplePaySession creation. Order should be created in onvalidatemerchant.";
        echo "✗ fetchWalletOrder is called before ApplePaySession creation\n";
    } else {
        echo "✓ fetchWalletOrder is NOT called before ApplePaySession creation\n";
    }
}

// Test 4: fetchWalletOrder is called in onvalidatemerchant handler (async is OK there)
if (strpos($clickHandlerBody, 'onvalidatemerchant') !== false && 
    strpos($clickHandlerBody, 'fetchWalletOrder') !== false) {
    
    // Extract onvalidatemerchant handler
    $validationPattern = '/onvalidatemerchant\s*=\s*function\s*\([^)]*\)\s*\{([\s\S]*?)\n        \};/';
    if (preg_match($validationPattern, $clickHandlerBody, $validationMatches)) {
        $validationBody = $validationMatches[1];
        
        if (strpos($validationBody, 'fetchWalletOrder') !== false) {
            echo "✓ fetchWalletOrder is called inside onvalidatemerchant handler\n";
        } else {
            $testPassed = false;
            $errors[] = "fetchWalletOrder should be called in onvalidatemerchant handler";
            echo "✗ fetchWalletOrder is not in onvalidatemerchant handler\n";
        }
    }
} else {
    $testPassed = false;
    $errors[] = "onvalidatemerchant handler or fetchWalletOrder not found";
    echo "✗ onvalidatemerchant handler or fetchWalletOrder not found\n";
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
// 3. Create ApplePaySession (synchronous)
// 4. Set up handlers (synchronous)
// 5. Call session.begin() (synchronous)
$structurePattern = '/applepay\.config\(\)[\s\S]*?new ApplePaySession[\s\S]*?onvalidatemerchant[\s\S]*?onpaymentauthorized[\s\S]*?oncancel[\s\S]*?session\.begin\(\)/';
if (preg_match($structurePattern, $clickHandlerBody)) {
    echo "✓ Code follows correct synchronous pattern for user gesture compliance\n";
} else {
    $testPassed = false;
    $errors[] = "Code does not follow correct synchronous pattern";
    echo "✗ Code structure does not follow correct pattern\n";
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay session user gesture tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- ApplePaySession is created synchronously in the click handler\n";
    echo "- session.begin() is called synchronously in the click handler\n";
    echo "- Order creation happens asynchronously in onvalidatemerchant (where async is allowed)\n";
    echo "- This prevents 'InvalidAccessError: Must create a new ApplePaySession from a user gesture handler'\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
