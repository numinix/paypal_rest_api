<?php
/**
 * Test to verify that the order total element selector is configurable for Apple Pay.
 *
 * This test ensures that:
 * 1. The default selector is 'ottotal' (standard Zen Cart element)
 * 2. The selector can be customized via data-total-selector attribute
 * 3. Both getOrderTotalFromPage() and observeOrderTotal() use the configured selector
 * 4. The configuration is properly documented in comments
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Apple Pay Order Total Selector Configuration\n";
echo "=====================================================\n\n";

// Get the Apple Pay JS file content
$applePayJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.applepay.js');

// Test 1: Verify getOrderTotalFromPage function exists
if (strpos($applePayJs, 'function getOrderTotalFromPage') !== false) {
    echo "✓ getOrderTotalFromPage function exists\n";
} else {
    $testPassed = false;
    $errors[] = "getOrderTotalFromPage function not found";
    echo "✗ getOrderTotalFromPage function not found\n";
}

// Test 2: Verify getOrderTotalFromPage uses data-total-selector attribute
if (strpos($applePayJs, 'dataset.totalSelector') !== false) {
    echo "✓ getOrderTotalFromPage checks for data-total-selector attribute\n";
} else {
    $testPassed = false;
    $errors[] = "getOrderTotalFromPage does not check data-total-selector";
    echo "✗ getOrderTotalFromPage does not check data-total-selector\n";
}

// Test 3: Verify default selector is 'ottotal'
$getOrderTotalPattern = '/function getOrderTotalFromPage\s*\(\s*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (preg_match($getOrderTotalPattern, $applePayJs, $matches)) {
    $functionBody = $matches[1];
    
    if (strpos($functionBody, "'ottotal'") !== false || strpos($functionBody, '"ottotal"') !== false) {
        echo "✓ Default selector is 'ottotal'\n";
    } else {
        $testPassed = false;
        $errors[] = "Default selector is not 'ottotal'";
        echo "✗ Default selector is not 'ottotal'\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Could not extract getOrderTotalFromPage function";
    echo "✗ Could not extract getOrderTotalFromPage function\n";
}

// Test 4: Verify observeOrderTotal uses the same configuration
$observeOrderTotalPattern = '/function observeOrderTotal\s*\(\s*\)\s*\{([\s\S]*?)^\s{4}\}/m';
if (preg_match($observeOrderTotalPattern, $applePayJs, $matches)) {
    $functionBody = $matches[1];
    
    if (strpos($functionBody, 'dataset.totalSelector') !== false) {
        echo "✓ observeOrderTotal uses data-total-selector attribute\n";
    } else {
        $testPassed = false;
        $errors[] = "observeOrderTotal does not use data-total-selector";
        echo "✗ observeOrderTotal does not use data-total-selector\n";
    }
    
    if (strpos($functionBody, "'ottotal'") !== false || strpos($functionBody, '"ottotal"') !== false) {
        echo "✓ observeOrderTotal has 'ottotal' as default\n";
    } else {
        $testPassed = false;
        $errors[] = "observeOrderTotal does not have 'ottotal' as default";
        echo "✗ observeOrderTotal does not have 'ottotal' as default\n";
    }
} else {
    $testPassed = false;
    $errors[] = "Could not extract observeOrderTotal function";
    echo "✗ Could not extract observeOrderTotal function\n";
}

// Test 5: Verify both functions use getElementById with the selector
if (preg_match($getOrderTotalPattern, $applePayJs, $getMatches) &&
    preg_match($observeOrderTotalPattern, $applePayJs, $observeMatches)) {
    
    $getTotalBody = $getMatches[1];
    $observeBody = $observeMatches[1];
    
    if (strpos($getTotalBody, 'getElementById(totalSelector)') !== false &&
        strpos($observeBody, 'getElementById(totalSelector)') !== false) {
        echo "✓ Both functions use getElementById with the configured selector\n";
    } else {
        $testPassed = false;
        $errors[] = "Functions do not consistently use getElementById(totalSelector)";
        echo "✗ Functions do not consistently use getElementById(totalSelector)\n";
    }
}

// Test 6: Verify documentation comment exists for observeOrderTotal
if (preg_match('/\/\*\*[\s\S]*?data-total-selector[\s\S]*?\*\/[\s\S]*?function observeOrderTotal/m', $applePayJs)) {
    echo "✓ observeOrderTotal has documentation about data-total-selector\n";
} else {
    $testPassed = false;
    $errors[] = "observeOrderTotal lacks documentation about data-total-selector";
    echo "✗ observeOrderTotal lacks documentation about data-total-selector\n";
}

// Test 7: Verify the container element is 'paypalac-applepay-button'
if (strpos($applePayJs, "'paypalac-applepay-button'") !== false || 
    strpos($applePayJs, '"paypalac-applepay-button"') !== false) {
    echo "✓ Configuration is read from 'paypalac-applepay-button' container\n";
} else {
    $testPassed = false;
    $errors[] = "Configuration container element not found";
    echo "✗ Configuration container element not found\n";
}

// Test 8: Verify currency detection in getOrderTotalFromPage
if (preg_match($getOrderTotalPattern, $applePayJs, $matches)) {
    $functionBody = $matches[1];
    
    // Check for currency detection (EUR, GBP, CAD, AUD, USD)
    $hasCurrencyDetection = strpos($functionBody, 'EUR') !== false &&
                           strpos($functionBody, 'GBP') !== false &&
                           strpos($functionBody, 'USD') !== false;
    
    if ($hasCurrencyDetection) {
        echo "✓ getOrderTotalFromPage includes currency detection\n";
    } else {
        $testPassed = false;
        $errors[] = "getOrderTotalFromPage lacks currency detection";
        echo "✗ getOrderTotalFromPage lacks currency detection\n";
    }
}

// Test 9: Verify numeric amount extraction in getOrderTotalFromPage
if (preg_match($getOrderTotalPattern, $applePayJs, $matches)) {
    $functionBody = $matches[1];
    
    if (strpos($functionBody, 'match') !== false || strpos($functionBody, 'replace') !== false) {
        echo "✓ getOrderTotalFromPage extracts numeric amount from text\n";
    } else {
        $testPassed = false;
        $errors[] = "getOrderTotalFromPage does not extract numeric amount";
        echo "✗ getOrderTotalFromPage does not extract numeric amount\n";
    }
}

// Summary
echo "\n";
if ($testPassed) {
    echo "All Apple Pay order total selector tests passed! ✓\n\n";
    echo "Summary:\n";
    echo "- Default selector is 'ottotal' (standard Zen Cart element)\n";
    echo "- Selector can be customized via data-total-selector attribute\n";
    echo "- Example: <div id=\"paypalac-applepay-button\" data-total-selector=\"custom-total-id\"></div>\n";
    echo "- Both getOrderTotalFromPage() and observeOrderTotal() use the same configuration\n";
    echo "- Amount extraction includes currency detection and numeric parsing\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
