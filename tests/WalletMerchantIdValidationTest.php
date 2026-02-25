<?php
/**
 * Test to verify that wallet modules do not pass invalid merchant IDs to the PayPal SDK.
 *
 * This test validates the fix for the issue where language label constants like
 * "Merchant ID:" were being passed to the PayPal SDK URL, causing 400 errors.
 *
 * The PayPal SDK merchant-id parameter should either:
 * 1. Not be included at all (for Apple Pay/Venmo which don't require it)
 * 2. Be a valid alphanumeric PayPal merchant ID (for Google Pay when configured)
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }

    /**
     * Test that Apple Pay module does not use MODULE_PAYMENT_PAYPALAC_MERCHANT_ID (language label)
     */
    function testApplePayDoesNotUseMerchantIdLanguageLabel(): bool
    {
        $passed = true;
        $applePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalac_applepay.php';
        $content = file_get_contents($applePayFile);

        // Check that MODULE_PAYMENT_PAYPALAC_MERCHANT_ID is NOT used
        // (This is a language constant containing "Merchant ID:", not an actual merchant ID)
        if (strpos($content, "defined('MODULE_PAYMENT_PAYPALAC_MERCHANT_ID')") !== false) {
            fwrite(STDERR, "FAIL: Apple Pay should NOT reference MODULE_PAYMENT_PAYPALAC_MERCHANT_ID (it's a language label)\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ Apple Pay does not reference MODULE_PAYMENT_PAYPALAC_MERCHANT_ID\n");
        }

        return $passed;
    }

    /**
     * Test that Google Pay ajaxGetWalletConfig validates the optional merchant ID
     */
    function testGooglePayValidatesOptionalMerchantIdConstant(): bool
    {
        $passed = true;
        $googlePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalac_googlepay.php';
        $content = file_get_contents($googlePayFile);

        // Find the ajaxGetWalletConfig method
        $methodStart = strpos($content, 'function ajaxGetWalletConfig()');
        if ($methodStart === false) {
            fwrite(STDERR, "FAIL: Could not find ajaxGetWalletConfig method in Google Pay module\n");
            return false;
        }

        // Get the method body (extended scope to capture response fields)
        $methodBody = substr($content, $methodStart);

        $usesHelper = strpos($methodBody, 'getGoogleMerchantIdConfig') !== false;
        $hasValidation = strpos($content, "/^[A-Z0-9]{5,20}$/i") !== false;
        $returnsGoogleMerchantId = strpos($methodBody, "'googleMerchantId' =>") !== false;
        $logsMerchantId = strpos($methodBody, 'Google Merchant ID:') !== false;

        if ($usesHelper) {
            fwrite(STDOUT, "✓ Google Pay ajaxGetWalletConfig loads Google Merchant ID via helper\n");
        } else {
            fwrite(STDERR, "FAIL: Google Pay ajaxGetWalletConfig should retrieve Google Merchant ID via helper\n");
            $passed = false;
        }

        if ($hasValidation) {
            fwrite(STDOUT, "✓ Google Pay ajaxGetWalletConfig validates Google Merchant ID format\n");
        } else {
            fwrite(STDERR, "FAIL: Google Pay ajaxGetWalletConfig should validate Google Merchant ID format (5-20 alphanumerics)\n");
            $passed = false;
        }

        if ($returnsGoogleMerchantId) {
            fwrite(STDOUT, "✓ Google Pay ajaxGetWalletConfig returns googleMerchantId in the response\n");
        } else {
            fwrite(STDERR, "FAIL: Google Pay ajaxGetWalletConfig should include googleMerchantId in the response\n");
            $passed = false;
        }

        if ($logsMerchantId) {
            fwrite(STDOUT, "✓ Google Pay ajaxGetWalletConfig logs the configured Google Merchant ID value\n");
        } else {
            fwrite(STDERR, "FAIL: Google Pay ajaxGetWalletConfig should log the Google Merchant ID value\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that JavaScript files validate merchant ID format before including in SDK URL
     */
    function testJavaScriptValidatesMerchantIdFormat(): bool
    {
        $passed = true;
        $jsFiles = [
            'jquery.paypalac.applepay.js',
            'jquery.paypalac.googlepay.js',
            'jquery.paypalac.venmo.js',
        ];

        foreach ($jsFiles as $jsFile) {
            $jsPath = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/' . $jsFile;
            $content = file_get_contents($jsPath);

            // Check for the merchant ID validation regex
            // The regex should validate that merchant ID is alphanumeric, 5-20 chars
            if (strpos($content, "/^[A-Z0-9]{5,20}$/i.test(") !== false) {
                fwrite(STDOUT, "✓ $jsFile validates merchant ID format before SDK URL inclusion\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should validate merchant ID format before including in SDK URL\n");
                $passed = false;
            }

            // Check that invalid values like "Merchant ID:" won't pass the validation
            // by checking the comment explaining why we validate
            if (strpos($content, 'Do NOT include language label strings like "Merchant ID:"') !== false) {
                fwrite(STDOUT, "✓ $jsFile has comment explaining merchant ID validation\n");
            } else {
                fwrite(STDERR, "FAIL: $jsFile should have comment explaining merchant ID validation\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that Apple Pay returns empty merchant ID (not required for Apple Pay via PayPal)
     */
    function testApplePayReturnsEmptyMerchantId(): bool
    {
        $passed = true;
        $applePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalac_applepay.php';
        $content = file_get_contents($applePayFile);

        // Find the ajaxGetWalletConfig method
        $methodStart = strpos($content, 'function ajaxGetWalletConfig()');
        if ($methodStart === false) {
            fwrite(STDERR, "FAIL: Could not find ajaxGetWalletConfig method in Apple Pay module\n");
            return false;
        }

        // Get the method body
        $methodBody = substr($content, $methodStart, 1500);

        // Check that merchant_id is set to empty string
        if (strpos($methodBody, "\$merchant_id = ''") !== false) {
            fwrite(STDOUT, "✓ Apple Pay ajaxGetWalletConfig sets merchant_id to empty string\n");
        } else {
            fwrite(STDERR, "FAIL: Apple Pay ajaxGetWalletConfig should set merchant_id to empty string\n");
            $passed = false;
        }

        // Check for comment explaining why
        if (strpos($methodBody, 'Apple Pay via PayPal does not require a separate merchant-id') !== false) {
            fwrite(STDOUT, "✓ Apple Pay has comment explaining empty merchant ID\n");
        } else {
            fwrite(STDERR, "FAIL: Apple Pay should have comment explaining why merchant_id is empty\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that Venmo uses optional merchant ID (empty is acceptable)
     */
    function testVenmoUsesOptionalMerchantId(): bool
    {
        $passed = true;
        $venmoFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalac_venmo.php';
        $content = file_get_contents($venmoFile);

        // Find the ajaxGetWalletConfig method
        $methodStart = strpos($content, 'function ajaxGetWalletConfig()');
        if ($methodStart === false) {
            fwrite(STDERR, "FAIL: Could not find ajaxGetWalletConfig method in Venmo module\n");
            return false;
        }

        // Get the method body
        $methodBody = substr($content, $methodStart, 1500);

        // Check that it uses MODULE_PAYMENT_PAYPALAC_VENMO_ACCOUNT_ID, not MODULE_PAYMENT_PAYPALAC_MERCHANT_ID
        if (strpos($methodBody, 'MODULE_PAYMENT_PAYPALAC_VENMO_ACCOUNT_ID') !== false) {
            fwrite(STDOUT, "✓ Venmo uses MODULE_PAYMENT_PAYPALAC_VENMO_ACCOUNT_ID\n");
        } else {
            fwrite(STDERR, "FAIL: Venmo should use MODULE_PAYMENT_PAYPALAC_VENMO_ACCOUNT_ID\n");
            $passed = false;
        }

        // Check that MODULE_PAYMENT_PAYPALAC_MERCHANT_ID is NOT used
        if (strpos($methodBody, "defined('MODULE_PAYMENT_PAYPALAC_MERCHANT_ID')") === false) {
            fwrite(STDOUT, "✓ Venmo does not use MODULE_PAYMENT_PAYPALAC_MERCHANT_ID\n");
        } else {
            fwrite(STDERR, "FAIL: Venmo should NOT use MODULE_PAYMENT_PAYPALAC_MERCHANT_ID\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying Apple Pay does not use language label constant...\n");
    if (testApplePayDoesNotUseMerchantIdLanguageLabel()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying Google Pay validates optional Google Merchant ID configuration...\n");
    if (testGooglePayValidatesOptionalMerchantIdConstant()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying JavaScript files validate merchant ID format...\n");
    if (testJavaScriptValidatesMerchantIdFormat()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying Apple Pay returns empty merchant ID...\n");
    if (testApplePayReturnsEmptyMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying Venmo uses optional merchant ID...\n");
    if (testVenmoUsesOptionalMerchantId()) {
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
        fwrite(STDOUT, "\n✓ All merchant ID validation tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. MODULE_PAYMENT_PAYPALAC_MERCHANT_ID is a LANGUAGE LABEL ('Merchant ID:'),\n");
        fwrite(STDOUT, "   not a configuration value. It should NOT be used as a merchant ID.\n");
        fwrite(STDOUT, "2. Apple Pay via PayPal does not require merchant-id parameter in SDK URL.\n");
        fwrite(STDOUT, "3. Google Pay via PayPal REST uses a validated optional Google Merchant ID configuration when provided.\n");
        fwrite(STDOUT, "4. Venmo uses MODULE_PAYMENT_PAYPALAC_VENMO_ACCOUNT_ID (optional).\n");
        fwrite(STDOUT, "5. JavaScript validates merchant ID format before including in SDK URL.\n");
        exit(0);
    }
}
