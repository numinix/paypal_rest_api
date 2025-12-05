<?php
/**
 * Test to verify that wallet modules do NOT pass the 'intent' parameter to the PayPal SDK URL.
 *
 * The PayPal SDK URL does not accept an 'intent' parameter. The intent (capture/authorize)
 * should be specified when creating the PayPal order, not when loading the SDK.
 *
 * See: https://developer.paypal.com/sdk/js/configuration/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    /**
     * Test that JavaScript files do NOT include 'intent' parameter in the PayPal SDK URL
     */
    function testJavaScriptDoesNotIncludeIntentInSdkUrl(): bool
    {
        $passed = true;
        $jsFiles = [
            'jquery.paypalr.applepay.js',
            'jquery.paypalr.googlepay.js',
            'jquery.paypalr.venmo.js',
        ];

        foreach ($jsFiles as $jsFile) {
            $jsPath = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/' . $jsFile;
            
            if (!file_exists($jsPath)) {
                fwrite(STDERR, "FAIL: $jsFile file not found at $jsPath\n");
                $passed = false;
                continue;
            }
            
            $content = file_get_contents($jsPath);

            // Check that intent is NOT appended to query string for SDK URL
            // The old code had: query += '&intent=' + encodeURIComponent(config.intent)
            // This checks for the specific pattern where intent is added to query via +=
            // The pattern won't match comments or other string occurrences
            if (preg_match("/query\s*\+=\s*['\"]&intent=/", $content)) {
                fwrite(STDERR, "FAIL: $jsFile still appends 'intent' parameter to SDK URL query string\n");
                $passed = false;
            } else {
                fwrite(STDOUT, "✓ $jsFile does NOT append 'intent' parameter to SDK URL\n");
            }

            // Check for the explanatory comment about intent
            if (strpos($content, "intent' parameter is NOT a valid PayPal SDK URL parameter") !== false) {
                fwrite(STDOUT, "✓ $jsFile has comment explaining why intent is not included\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should have comment explaining why intent is not in SDK URL\n");
                $passed = false;
            }

            // Verify that the buildSdkKey function does NOT declare an intent variable
            // We extract the buildSdkKey function body and check for "var intent ="
            // This is more robust than using arbitrary character limits
            $functionPattern = '/function\s+buildSdkKey\s*\([^)]*\)\s*\{([^}]*)\}/s';
            if (preg_match($functionPattern, $content, $matches)) {
                $functionBody = $matches[1];
                if (preg_match('/var\s+intent\s*=/', $functionBody)) {
                    fwrite(STDERR, "FAIL: $jsFile buildSdkKey should not have intent variable\n");
                    $passed = false;
                } else {
                    fwrite(STDOUT, "✓ $jsFile buildSdkKey does not use intent variable\n");
                }
            } else {
                fwrite(STDERR, "FAIL: $jsFile could not find buildSdkKey function\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that the SDK URL is properly constructed without intent
     */
    function testSdkUrlConstruction(): bool
    {
        $passed = true;
        $jsFiles = [
            'jquery.paypalr.applepay.js',
            'jquery.paypalr.googlepay.js',
            'jquery.paypalr.venmo.js',
        ];

        foreach ($jsFiles as $jsFile) {
            $jsPath = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/' . $jsFile;
            $content = file_get_contents($jsPath);

            // Check that the SDK URL includes required parameters
            if (strpos($content, "query = '?client-id='") !== false) {
                fwrite(STDOUT, "✓ $jsFile includes client-id in SDK URL\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should include client-id in SDK URL\n");
                $passed = false;
            }

            // Note: 'venmo' is NOT a valid SDK component. Venmo is a funding source that works
            // through the 'buttons' component using paypal.FUNDING.VENMO
            // For native Google Pay implementation, only 'googlepay' component is needed.
            // For native Apple Pay implementation, only 'applepay' component is needed.
            $hasValidComponents = (
                strpos($content, "&components=buttons,googlepay,applepay") !== false ||
                ($jsFile === 'jquery.paypalr.googlepay.js' && strpos($content, "&components=googlepay") !== false) ||
                ($jsFile === 'jquery.paypalr.applepay.js' && strpos($content, "&components=applepay") !== false)
            );
            if ($hasValidComponents) {
                fwrite(STDOUT, "✓ $jsFile includes valid components in SDK URL (no venmo)\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should include valid components in SDK URL\n");
                $passed = false;
            }

            // Verify 'venmo' is NOT in the components list
            if (strpos($content, "components=") !== false && strpos($content, ",venmo") !== false) {
                fwrite(STDERR, "FAIL: $jsFile should NOT include 'venmo' in components (venmo is not a valid SDK component)\n");
                $passed = false;
            } else {
                fwrite(STDOUT, "✓ $jsFile correctly excludes 'venmo' from SDK components\n");
            }

            if (strpos($content, "&currency=") !== false) {
                fwrite(STDOUT, "✓ $jsFile includes currency in SDK URL\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should include currency in SDK URL\n");
                $passed = false;
            }
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying JavaScript files do NOT include 'intent' in SDK URL...\n");
    if (testJavaScriptDoesNotIncludeIntentInSdkUrl()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying SDK URL is properly constructed...\n");
    if (testSdkUrlConstruction()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All SDK intent parameter tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. The 'intent' parameter is NOT a valid PayPal SDK URL parameter.\n");
        fwrite(STDOUT, "2. Intent (capture/authorize) is specified when creating the PayPal order,\n");
        fwrite(STDOUT, "   not when loading the SDK.\n");
        fwrite(STDOUT, "3. Including 'intent' in the SDK URL causes HTTP 400 errors.\n");
        fwrite(STDOUT, "4. Valid SDK URL parameters include: client-id, components, currency, merchant-id,\n");
        fwrite(STDOUT, "   buyer-country, locale, debug, etc.\n");
        exit(0);
    }
}
