<?php
/**
 * Test to verify that merchant integration queries use seller_merchant_id parameter.
 *
 * This test validates the fix for the issue where status polls with a merchant_id
 * were returning "Invalid request" from PayPal's API. The root cause was using
 * 'partner_merchant_id' as the query parameter when filtering by the seller's
 * PayPal merchant ID, when PayPal's Marketplace Merchant Integrations API expects
 * 'seller_merchant_id' for this purpose.
 *
 * PayPal's API parameter names:
 * - partner_merchant_id: The partner's (our) PayPal merchant ID
 * - seller_merchant_id: The seller's (newly onboarded merchant's) PayPal merchant ID
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

    // Service file path constant - centralized for maintainability
    define('ONBOARDING_SERVICE_FILE', DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php');

    /**
     * Helper function to check if a line contains http_build_query with partner_merchant_id.
     * This is used to verify that API query parameters don't use the wrong parameter name.
     *
     * @param string $line Line of code to check
     * @return bool
     */
    function lineContainsPartnerMerchantIdInQuery(string $line): bool
    {
        // Look for http_build_query calls that include partner_merchant_id
        // Skip comment lines
        $trimmedLine = trim($line);
        if (str_starts_with($trimmedLine, '//') || str_starts_with($trimmedLine, '*') || str_starts_with($trimmedLine, '/*')) {
            return false;
        }
        return strpos($line, 'http_build_query') !== false && strpos($line, "'partner_merchant_id'") !== false;
    }

    /**
     * Helper function to find http_build_query blocks and check for parameter usage.
     * Uses a line-based approach instead of complex regex to be more reliable.
     *
     * @param string $content File content
     * @param string $methodName Method name to search within
     * @param string $expectedParam Parameter expected to be used
     * @param string $forbiddenParam Parameter that should NOT be used in queries
     * @return array{found: bool, usesExpected: bool, usesForbidden: bool}
     */
    function checkMethodQueryParameters(string $content, string $methodName, string $expectedParam, string $forbiddenParam): array
    {
        $result = [
            'found' => false,
            'usesExpected' => false,
            'usesForbidden' => false,
        ];

        $lines = explode("\n", $content);
        $inMethod = false;
        $braceCount = 0;
        $inHttpBuildQuery = false;

        foreach ($lines as $line) {
            // Check if we're entering the target method
            if (!$inMethod && strpos($line, "function $methodName") !== false) {
                $inMethod = true;
                $result['found'] = true;
                $braceCount = 0;
            }

            if ($inMethod) {
                // Count braces to track method scope
                $braceCount += substr_count($line, '{');
                $braceCount -= substr_count($line, '}');

                // Check for http_build_query call
                if (strpos($line, 'http_build_query') !== false) {
                    $inHttpBuildQuery = true;
                }

                // When inside http_build_query, check for parameter names
                if ($inHttpBuildQuery) {
                    // Skip comment lines
                    $trimmedLine = trim($line);
                    if (!str_starts_with($trimmedLine, '//') && !str_starts_with($trimmedLine, '*')) {
                        if (strpos($line, "'$expectedParam'") !== false) {
                            $result['usesExpected'] = true;
                        }
                        if (strpos($line, "'$forbiddenParam'") !== false) {
                            $result['usesForbidden'] = true;
                        }
                    }

                    // End of http_build_query array (closing parenthesis or semicolon)
                    if (strpos($line, ']);') !== false || strpos($line, ');') !== false) {
                        $inHttpBuildQuery = false;
                    }
                }

                // Exit when method ends
                if ($braceCount <= 0 && strpos($line, '}') !== false) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Test that fetchMerchantIntegrationByMerchantId uses seller_merchant_id parameter
     */
    function testMerchantIntegrationByMerchantIdUsesSellerMerchantId(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        $result = checkMethodQueryParameters(
            $content,
            'fetchMerchantIntegrationByMerchantId',
            'seller_merchant_id',
            'partner_merchant_id'
        );

        if (!$result['found']) {
            fwrite(STDERR, "FAIL: Could not find fetchMerchantIntegrationByMerchantId method\n");
            return false;
        }

        if ($result['usesExpected']) {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByMerchantId uses 'seller_merchant_id' parameter\n");
        } else {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByMerchantId should use 'seller_merchant_id' parameter\n");
            $passed = false;
        }

        if ($result['usesForbidden']) {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByMerchantId should NOT use 'partner_merchant_id' in API query\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByMerchantId does not use 'partner_merchant_id' in API query\n");
        }

        return $passed;
    }

    /**
     * Test that fetchMerchantIntegrationByTrackingId uses seller_merchant_id parameter
     */
    function testMerchantIntegrationByTrackingIdUsesSellerMerchantId(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        $result = checkMethodQueryParameters(
            $content,
            'fetchMerchantIntegrationByTrackingId',
            'seller_merchant_id',
            'partner_merchant_id'
        );

        if (!$result['found']) {
            fwrite(STDERR, "FAIL: Could not find fetchMerchantIntegrationByTrackingId method\n");
            return false;
        }

        if ($result['usesExpected']) {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByTrackingId uses 'seller_merchant_id' parameter\n");
        } else {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByTrackingId should use 'seller_merchant_id' parameter\n");
            $passed = false;
        }

        if ($result['usesForbidden']) {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByTrackingId should NOT use 'partner_merchant_id' in API query\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByTrackingId does not use 'partner_merchant_id' in API query\n");
        }

        return $passed;
    }

    /**
     * Test that the response handling still correctly checks partner_merchant_id from PayPal response
     */
    function testResponseHandlingUsesPartnerMerchantId(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // The response from PayPal includes partner_merchant_id in the items,
        // so we should still be checking for it when processing the response
        if (strpos($content, "\$item['partner_merchant_id']") !== false) {
            fwrite(STDOUT, "✓ Response handling correctly checks 'partner_merchant_id' from PayPal response\n");
        } else {
            fwrite(STDERR, "FAIL: Response handling should check 'partner_merchant_id' from PayPal response\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the API endpoint is correct
     */
    function testCorrectApiEndpoint(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // Check that the marketplace merchant-integrations endpoint is used
        if (strpos($content, "/v1/customer/partners/marketplace/merchant-integrations") !== false) {
            fwrite(STDOUT, "✓ Uses correct marketplace merchant-integrations endpoint\n");
        } else {
            fwrite(STDERR, "FAIL: Should use /v1/customer/partners/marketplace/merchant-integrations endpoint\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying fetchMerchantIntegrationByMerchantId uses seller_merchant_id...\n");
    if (testMerchantIntegrationByMerchantIdUsesSellerMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying fetchMerchantIntegrationByTrackingId uses seller_merchant_id...\n");
    if (testMerchantIntegrationByTrackingIdUsesSellerMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying response handling uses partner_merchant_id...\n");
    if (testResponseHandlingUsesPartnerMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying correct API endpoint is used...\n");
    if (testCorrectApiEndpoint()) {
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
        fwrite(STDOUT, "\n✓ All seller_merchant_id parameter tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "The PayPal Marketplace Merchant Integrations API expects 'seller_merchant_id'\n");
        fwrite(STDOUT, "as the query parameter when filtering by the seller's PayPal merchant ID.\n");
        fwrite(STDOUT, "Previously, 'partner_merchant_id' was incorrectly used, causing PayPal\n");
        fwrite(STDOUT, "to return 'Invalid request' errors during status polling.\n");
        fwrite(STDOUT, "\nPayPal API parameter naming:\n");
        fwrite(STDOUT, "- partner_merchant_id: The partner's (our) PayPal merchant ID\n");
        fwrite(STDOUT, "- seller_merchant_id: The seller's (newly onboarded merchant's) PayPal merchant ID\n");
        exit(0);
    }
}
