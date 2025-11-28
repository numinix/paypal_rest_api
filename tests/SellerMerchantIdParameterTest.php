<?php
/**
 * Test to verify that merchant integration queries use the correct PayPal API endpoint.
 *
 * This test validates that the standard Partner Merchant Integrations endpoint is used:
 * GET /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}
 *
 * Previously, the code was using the Marketplace endpoint which caused "Invalid request"
 * errors for non-Marketplace partner accounts.
 *
 * PayPal API endpoints:
 * - Standard Partner: /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}
 * - Marketplace: /v1/customer/partners/marketplace/merchant-integrations (requires special account type)
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
     * Test that fetchMerchantIntegrationByMerchantId uses the standard partner endpoint
     */
    function testMerchantIntegrationByMerchantIdUsesStandardEndpoint(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // Check that the method exists and uses the correct endpoint format
        if (strpos($content, 'function fetchMerchantIntegrationByMerchantId') === false) {
            fwrite(STDERR, "FAIL: Could not find fetchMerchantIntegrationByMerchantId method\n");
            return false;
        }

        // Check for partner client ID in the URL construction
        // Should contain: /v1/customer/partners/' . rawurlencode($partnerClientId) . '/merchant-integrations/' . rawurlencode($merchantId)
        if (strpos($content, "'/v1/customer/partners/' . rawurlencode(\$partnerClientId) . '/merchant-integrations/'") !== false) {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByMerchantId uses standard partner endpoint with partner_client_id in URL path\n");
        } else {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByMerchantId should use standard partner endpoint with partnerClientId in URL path\n");
            $passed = false;
        }

        // Ensure the old marketplace endpoint is not used in this method
        // Look specifically in the method context
        $methodStart = strpos($content, 'function fetchMerchantIntegrationByMerchantId');
        if ($methodStart !== false) {
            // Find the method body (limited scope check)
            $methodBody = substr($content, $methodStart, 2000);
            if (strpos($methodBody, '/marketplace/merchant-integrations') !== false) {
                fwrite(STDERR, "FAIL: fetchMerchantIntegrationByMerchantId should NOT use marketplace endpoint\n");
                $passed = false;
            } else {
                fwrite(STDOUT, "✓ fetchMerchantIntegrationByMerchantId does not use marketplace endpoint\n");
            }
        }

        return $passed;
    }

    /**
     * Test that fetchMerchantIntegrationByTrackingId uses the standard partner endpoint
     */
    function testMerchantIntegrationByTrackingIdUsesStandardEndpoint(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // Check that the method exists and uses the correct endpoint format
        if (strpos($content, 'function fetchMerchantIntegrationByTrackingId') === false) {
            fwrite(STDERR, "FAIL: Could not find fetchMerchantIntegrationByTrackingId method\n");
            return false;
        }

        // Check for partner client ID in the URL construction
        // Should contain: /v1/customer/partners/' . rawurlencode($partnerClientId) . '/merchant-integrations
        if (strpos($content, "'/v1/customer/partners/' . rawurlencode(\$partnerClientId) . '/merchant-integrations'") !== false) {
            fwrite(STDOUT, "✓ fetchMerchantIntegrationByTrackingId uses standard partner endpoint with partner_client_id in URL path\n");
        } else {
            fwrite(STDERR, "FAIL: fetchMerchantIntegrationByTrackingId should use standard partner endpoint with partnerClientId in URL path\n");
            $passed = false;
        }

        // Ensure the old marketplace endpoint is not used in this method
        $methodStart = strpos($content, 'function fetchMerchantIntegrationByTrackingId');
        if ($methodStart !== false) {
            $methodBody = substr($content, $methodStart, 2000);
            if (strpos($methodBody, '/marketplace/merchant-integrations') !== false) {
                fwrite(STDERR, "FAIL: fetchMerchantIntegrationByTrackingId should NOT use marketplace endpoint\n");
                $passed = false;
            } else {
                fwrite(STDOUT, "✓ fetchMerchantIntegrationByTrackingId does not use marketplace endpoint\n");
            }
        }

        return $passed;
    }

    /**
     * Test that fetchMerchantIntegration receives partnerClientId parameter
     */
    function testFetchMerchantIntegrationReceivesPartnerClientId(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // Check that fetchMerchantIntegration has the partnerClientId parameter
        if (preg_match('/function fetchMerchantIntegration\([^)]*\$partnerClientId[^)]*\)/', $content)) {
            fwrite(STDOUT, "✓ fetchMerchantIntegration receives partnerClientId parameter\n");
        } else {
            fwrite(STDERR, "FAIL: fetchMerchantIntegration should receive partnerClientId parameter\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the code normalizes marketplace integration responses correctly
     */
    function testNormalizeMarketplaceIntegrationExists(): bool
    {
        $passed = true;
        $content = file_get_contents(ONBOARDING_SERVICE_FILE);

        // Check that the normalization method exists
        if (strpos($content, 'function normalizeMarketplaceIntegration') !== false) {
            fwrite(STDOUT, "✓ normalizeMarketplaceIntegration method exists for response handling\n");
        } else {
            fwrite(STDERR, "FAIL: normalizeMarketplaceIntegration method should exist\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying fetchMerchantIntegrationByMerchantId uses standard partner endpoint...\n");
    if (testMerchantIntegrationByMerchantIdUsesStandardEndpoint()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying fetchMerchantIntegrationByTrackingId uses standard partner endpoint...\n");
    if (testMerchantIntegrationByTrackingIdUsesStandardEndpoint()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying fetchMerchantIntegration receives partnerClientId...\n");
    if (testFetchMerchantIntegrationReceivesPartnerClientId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying normalizeMarketplaceIntegration method exists...\n");
    if (testNormalizeMarketplaceIntegrationExists()) {
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
        fwrite(STDOUT, "\n✓ All standard partner endpoint tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "The PayPal Partner Merchant Integrations API requires the partner's client ID\n");
        fwrite(STDOUT, "in the URL path for standard partner integrations:\n");
        fwrite(STDOUT, "  GET /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}\n");
        fwrite(STDOUT, "\nThe old /marketplace/ endpoint was only for Marketplace-type partner accounts\n");
        fwrite(STDOUT, "and caused 'Invalid request' errors for standard partner accounts.\n");
        exit(0);
    }
}
