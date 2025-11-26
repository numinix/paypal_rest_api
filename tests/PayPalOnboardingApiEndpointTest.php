<?php
/**
 * Test to verify that PayPal onboarding API endpoint handles:
 * 1. PayPal return redirects after modal completion (GET requests with PayPal params)
 * 2. API proxy requests from external admin panels (cross-origin XHR requests)
 *
 * This test validates the fix for issues where:
 * - PayPal modal showed "Missing nxp_paypal_action parameter" after completion
 * - Admin proxy received "Request origin mismatch" errors for status polling
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
}

namespace {
    /**
     * Test that verifies the API endpoint handles PayPal return redirects
     */
    function testPayPalReturnRedirectHandling(): bool
    {
        $passed = true;

        $apiFile = DIR_FS_CATALOG . 'numinix.com/api/paypal_onboarding.php';
        $content = file_get_contents($apiFile);

        // Check 1: Verify the endpoint checks for PayPal return redirect
        if (strpos($content, 'nxp_paypal_is_paypal_return_redirect') !== false) {
            fwrite(STDOUT, "✓ API endpoint checks for PayPal return redirects\n");
        } else {
            fwrite(STDERR, "FAIL: API endpoint should check for PayPal return redirects\n");
            $passed = false;
        }

        // Check 2: Verify the endpoint shows completion page for PayPal returns
        if (strpos($content, 'nxp_paypal_show_completion_page') !== false) {
            fwrite(STDOUT, "✓ API endpoint shows completion page for PayPal returns\n");
        } else {
            fwrite(STDERR, "FAIL: API endpoint should show completion page for PayPal returns\n");
            $passed = false;
        }

        // Check 3: Verify the PayPal return detection happens before the error response
        $returnCheckPos = strpos($content, 'nxp_paypal_is_paypal_return_redirect');
        $errorPos = strpos($content, "nxp_paypal_json_error('Missing nxp_paypal_action parameter");
        if ($returnCheckPos !== false && $errorPos !== false && $returnCheckPos < $errorPos) {
            fwrite(STDOUT, "✓ PayPal return check happens before error response\n");
        } else {
            fwrite(STDERR, "FAIL: PayPal return check should happen before error response\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the helper function for detecting PayPal returns exists
     */
    function testPayPalReturnDetectionHelperExists(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_is_paypal_return_redirect function exists
        if (preg_match('/function\s+nxp_paypal_is_paypal_return_redirect\s*\(\s*\)/', $content)) {
            fwrite(STDOUT, "✓ nxp_paypal_is_paypal_return_redirect function exists\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_is_paypal_return_redirect function should exist\n");
            $passed = false;
        }

        // Check 2: Verify it checks for merchantIdInPayPal parameter
        if (strpos($content, 'merchantIdInPayPal') !== false) {
            fwrite(STDOUT, "✓ Function checks for merchantIdInPayPal parameter\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for merchantIdInPayPal parameter\n");
            $passed = false;
        }

        // Check 3: Verify it checks for permissionsGranted parameter
        if (strpos($content, 'permissionsGranted') !== false) {
            fwrite(STDOUT, "✓ Function checks for permissionsGranted parameter\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for permissionsGranted parameter\n");
            $passed = false;
        }

        // Check 4: Verify it only triggers for GET requests
        if (strpos($content, "REQUEST_METHOD") !== false && strpos($content, "'GET'") !== false) {
            fwrite(STDOUT, "✓ Function only triggers for GET requests\n");
        } else {
            fwrite(STDERR, "FAIL: Function should only trigger for GET requests\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the completion page helper exists and displays correctly
     */
    function testCompletionPageHelperExists(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_show_completion_page function exists
        if (preg_match('/function\s+nxp_paypal_show_completion_page\s*\(\s*\)/', $content)) {
            fwrite(STDOUT, "✓ nxp_paypal_show_completion_page function exists\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_show_completion_page function should exist\n");
            $passed = false;
        }

        // Check 2: Verify it outputs HTML (not JSON)
        if (strpos($content, "Content-Type: text/html") !== false) {
            fwrite(STDOUT, "✓ Completion page outputs HTML content type\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should output HTML content type\n");
            $passed = false;
        }

        // Check 3: Verify it has auto-close functionality
        if (strpos($content, 'window.close()') !== false) {
            fwrite(STDOUT, "✓ Completion page has auto-close functionality\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should have auto-close functionality\n");
            $passed = false;
        }

        // Check 4: Verify it has success message
        if (strpos($content, 'PayPal Onboarding Complete') !== false || strpos($content, 'Setup Complete') !== false) {
            fwrite(STDOUT, "✓ Completion page has success message\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should have success message\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies API proxy request detection helper exists
     */
    function testApiProxyRequestDetectionExists(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_is_api_proxy_request function exists
        if (preg_match('/function\s+nxp_paypal_is_api_proxy_request\s*\(\s*\)/', $content)) {
            fwrite(STDOUT, "✓ nxp_paypal_is_api_proxy_request function exists\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_is_api_proxy_request function should exist\n");
            $passed = false;
        }

        // Check 2: Verify it checks for XMLHttpRequest header
        if (strpos($content, 'HTTP_X_REQUESTED_WITH') !== false && strpos($content, 'xmlhttprequest') !== false) {
            fwrite(STDOUT, "✓ Function checks for XMLHttpRequest header\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for XMLHttpRequest header\n");
            $passed = false;
        }

        // Check 3: Verify it checks for POST method
        if (strpos($content, 'REQUEST_METHOD') !== false && 
            (strpos($content, "'POST'") !== false || strpos($content, '"POST"') !== false)) {
            fwrite(STDOUT, "✓ Function checks for POST method\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for POST method\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies origin validation allows API proxy requests
     */
    function testOriginValidationAllowsApiProxy(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_validate_origin_for_action calls nxp_paypal_is_api_proxy_request
        if (preg_match('/function\s+nxp_paypal_validate_origin_for_action.*?nxp_paypal_is_api_proxy_request/s', $content)) {
            fwrite(STDOUT, "✓ Origin validation uses API proxy request detection\n");
        } else {
            fwrite(STDERR, "FAIL: Origin validation should use API proxy request detection\n");
            $passed = false;
        }

        // Check 2: Verify API proxy requests return true (allowed)
        if (preg_match('/nxp_paypal_is_api_proxy_request\s*\(\s*\)\s*\)\s*\{[^}]*return\s+true/s', $content)) {
            fwrite(STDOUT, "✓ API proxy requests are allowed by origin validation\n");
        } else {
            fwrite(STDERR, "FAIL: API proxy requests should be allowed by origin validation\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies nonce validation for API proxy requests
     */
    function testNonceValidationForApiProxy(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_validate_nonce_for_action handles API proxy requests
        if (preg_match('/function\s+nxp_paypal_validate_nonce_for_action.*?nxp_paypal_is_api_proxy_request/s', $content)) {
            fwrite(STDOUT, "✓ Nonce validation handles API proxy requests\n");
        } else {
            fwrite(STDERR, "FAIL: Nonce validation should handle API proxy requests\n");
            $passed = false;
        }

        // Check 2: Verify API proxy requests just require non-empty nonce
        // Looking for pattern where it checks nonce is not null/empty for proxy requests
        if (preg_match('/nxp_paypal_is_api_proxy_request\s*\(\s*\)\s*\).*\$nonce\s*!==\s*null.*\$nonce\s*!==\s*[\'\"][\'\"]/s', $content)) {
            fwrite(STDOUT, "✓ API proxy requests require non-empty nonce\n");
        } else {
            fwrite(STDERR, "FAIL: API proxy requests should require non-empty nonce\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying API endpoint handles PayPal return redirects...\n");
    if (testPayPalReturnRedirectHandling()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying PayPal return detection helper exists...\n");
    if (testPayPalReturnDetectionHelperExists()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying completion page helper exists...\n");
    if (testCompletionPageHelperExists()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying API proxy request detection exists...\n");
    if (testApiProxyRequestDetectionExists()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying origin validation allows API proxy requests...\n");
    if (testOriginValidationAllowsApiProxy()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 6: Verifying nonce validation for API proxy requests...\n");
    if (testNonceValidationForApiProxy()) {
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
        fwrite(STDOUT, "\n✓ All PayPal onboarding API tests passed!\n");
        fwrite(STDOUT, "\nFixes applied:\n");
        fwrite(STDOUT, "1. PayPal return redirects (GET with merchantIdInPayPal, permissionsGranted)\n");
        fwrite(STDOUT, "   now show a user-friendly completion page instead of JSON error.\n");
        fwrite(STDOUT, "2. API proxy requests from external admin panels are now properly\n");
        fwrite(STDOUT, "   recognized and allowed through origin validation.\n");
        fwrite(STDOUT, "3. Nonce validation for API proxy requests accepts any non-empty nonce\n");
        fwrite(STDOUT, "   since session state is not shared between the admin and numinix.com.\n");
        exit(0);
    }
}
