<?php
/**
 * Test to verify that HTTP redirect responses from Numinix API include the redirect URL in logs,
 * and that local Zen Cart parameters are not forwarded to Numinix.com.
 *
 * This test validates the fix for the issue where HTTP 302 redirects from Numinix.com
 * didn't show the redirect destination in the debug logs, and where local Zen Cart
 * admin parameters (action, securityToken, proxy_action) were being forwarded to
 * Numinix.com, potentially causing security redirects.
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
        define('IS_ADMIN_FLAG', true);
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace {
    /**
     * Test that verifies the paypalr_proxy_to_numinix function captures redirect URLs
     */
    function testRedirectUrlLogging(): bool
    {
        $passed = true;

        // Read the paypalr_integrated_signup.php file and check for the fix
        $signupFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($signupFile);

        // Check 1: Verify that CURLINFO_REDIRECT_URL is captured
        if (strpos($content, 'CURLINFO_REDIRECT_URL') === false) {
            fwrite(STDERR, "FAIL: The code does not capture CURLINFO_REDIRECT_URL\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ Code captures CURLINFO_REDIRECT_URL from cURL response\n");
        }

        // Check 2: Verify that redirect_url is logged in the non-200 status case
        if (strpos($content, "'redirect_url' =>") !== false) {
            fwrite(STDOUT, "✓ Redirect URL is included in the log output\n");
        } else {
            fwrite(STDERR, "FAIL: Redirect URL is not included in the log output\n");
            $passed = false;
        }

        // Check 3: Verify the redirect URL variable is assigned
        if (preg_match('/\$redirectUrl\s*=\s*curl_getinfo\s*\(\s*\$ch\s*,\s*CURLINFO_REDIRECT_URL\s*\)/', $content)) {
            fwrite(STDOUT, "✓ Redirect URL is properly captured from cURL\n");
        } else {
            fwrite(STDERR, "FAIL: Redirect URL is not properly captured from cURL\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the log context includes redirect_url for non-200 responses
     */
    function testLogContextIncludesRedirectUrl(): bool
    {
        $passed = true;

        $signupFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($signupFile);

        // Find the log call for non-200 status and verify it includes redirect_url
        $pattern = "/paypalr_log_debug\s*\(\s*'Numinix API returned non-200 status'.*?'redirect_url'\s*=>\s*\\\$redirectUrl/s";
        if (preg_match($pattern, $content)) {
            fwrite(STDOUT, "✓ Log call for non-200 status includes redirect_url context\n");
        } else {
            fwrite(STDERR, "FAIL: Log call for non-200 status does not include redirect_url context\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that local Zen Cart admin parameters are not forwarded to Numinix.com
     */
    function testLocalParametersAreRemoved(): bool
    {
        $passed = true;

        $signupFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($signupFile);

        // Check that localOnlyParams array is defined with the expected parameters
        $requiredParams = ['action', 'securityToken', 'proxy_action'];
        
        foreach ($requiredParams as $param) {
            if (strpos($content, "'$param'") !== false && strpos($content, 'localOnlyParams') !== false) {
                fwrite(STDOUT, "✓ Parameter '$param' is in the list of local-only parameters to remove\n");
            } else {
                fwrite(STDERR, "FAIL: Parameter '$param' should be removed before forwarding to Numinix\n");
                $passed = false;
            }
        }

        // Check that unset is called for the local parameters
        if (preg_match('/unset\s*\(\s*\$data\s*\[\s*\$param\s*\]\s*\)/', $content)) {
            fwrite(STDOUT, "✓ Local parameters are unset from the data before forwarding\n");
        } else {
            fwrite(STDERR, "FAIL: Local parameters are not being unset from the forwarded data\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the default Numinix URL points to the standalone API endpoint
     */
    function testDefaultNuminixUrlIsApiEndpoint(): bool
    {
        $passed = true;

        $signupFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($signupFile);

        // Check that the default URL points to the standalone API endpoint
        if (strpos($content, '/api/paypal_onboarding.php') !== false) {
            fwrite(STDOUT, "✓ Default Numinix URL uses standalone API endpoint\n");
        } else {
            fwrite(STDERR, "FAIL: Default Numinix URL should use /api/paypal_onboarding.php\n");
            $passed = false;
        }

        // Check that main_page=paypal_api is no longer hardcoded
        if (strpos($content, "main_page=paypal_api") === false && 
            strpos($content, "'main_page' => 'paypal_api'") === false) {
            fwrite(STDOUT, "✓ Code no longer forces main_page=paypal_api query parameter\n");
        } else {
            fwrite(STDERR, "FAIL: Code should not force main_page=paypal_api for the API endpoint\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the standalone API endpoint file exists
     */
    function testStandaloneApiEndpointExists(): bool
    {
        $passed = true;

        $apiFile = DIR_FS_CATALOG . 'numinix.com/api/paypal_onboarding.php';
        
        if (file_exists($apiFile)) {
            fwrite(STDOUT, "✓ Standalone API endpoint file exists\n");
            
            $content = file_get_contents($apiFile);
            
            // Verify it bootstraps Zen Cart correctly
            if (strpos($content, "require '../includes/configure.php'") !== false) {
                fwrite(STDOUT, "✓ API endpoint includes configure.php\n");
            } else {
                fwrite(STDERR, "FAIL: API endpoint should include configure.php\n");
                $passed = false;
            }
            
            // Verify it sets include_path
            if (strpos($content, 'DIR_FS_CATALOG') !== false && strpos($content, 'include_path') !== false) {
                fwrite(STDOUT, "✓ API endpoint sets include_path with DIR_FS_CATALOG\n");
            } else {
                fwrite(STDERR, "FAIL: API endpoint should set include_path with DIR_FS_CATALOG\n");
                $passed = false;
            }
            
            // Verify it changes directory
            if (strpos($content, 'chdir(DIR_FS_CATALOG)') !== false) {
                fwrite(STDOUT, "✓ API endpoint changes to catalog directory\n");
            } else {
                fwrite(STDERR, "FAIL: API endpoint should chdir to DIR_FS_CATALOG\n");
                $passed = false;
            }
            
            // Verify IS_ADMIN_FLAG is set before application_top
            $isAdminPos = strpos($content, 'IS_ADMIN_FLAG');
            $appTopPos = strpos($content, 'application_top.php');
            if ($isAdminPos !== false && $appTopPos !== false && $isAdminPos < $appTopPos) {
                fwrite(STDOUT, "✓ IS_ADMIN_FLAG is defined before application_top.php\n");
            } else {
                fwrite(STDERR, "FAIL: IS_ADMIN_FLAG should be defined before application_top.php\n");
                $passed = false;
            }
            
            // Verify error handling for missing files
            if (strpos($content, 'missingFiles') !== false && strpos($content, 'http_response_code(500)') !== false) {
                fwrite(STDOUT, "✓ API endpoint has error handling for missing dependencies\n");
            } else {
                fwrite(STDERR, "FAIL: API endpoint should handle missing dependencies gracefully\n");
                $passed = false;
            }
        } else {
            fwrite(STDERR, "FAIL: Standalone API endpoint file does not exist at $apiFile\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying redirect URL is captured from cURL...\n");
    if (testRedirectUrlLogging()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }

    fwrite(STDOUT, "\nTest 2: Verifying log context includes redirect_url...\n");
    if (testLogContextIncludesRedirectUrl()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }

    fwrite(STDOUT, "\nTest 3: Verifying local Zen Cart parameters are removed before forwarding...\n");
    if (testLocalParametersAreRemoved()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }

    fwrite(STDOUT, "\nTest 4: Verifying default Numinix URL uses standalone API endpoint...\n");
    if (testDefaultNuminixUrlIsApiEndpoint()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }

    fwrite(STDOUT, "\nTest 5: Verifying standalone API endpoint exists and is properly configured...\n");
    if (testStandaloneApiEndpointExists()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All proxy tests passed!\n");
        fwrite(STDOUT, "\nFixes applied:\n");
        fwrite(STDOUT, "1. When Numinix.com returns a 302 redirect, the logs now show the redirect URL.\n");
        fwrite(STDOUT, "2. Local Zen Cart parameters (action, securityToken, proxy_action) are now\n");
        fwrite(STDOUT, "   removed before forwarding requests to Numinix.com to prevent security\n");
        fwrite(STDOUT, "   redirects caused by the remote Zen Cart interpreting these parameters.\n");
        fwrite(STDOUT, "3. A new standalone API endpoint (/api/paypal_onboarding.php) is now used\n");
        fwrite(STDOUT, "   on Numinix.com to bypass Zen Cart's page-based action/securityToken handling.\n");
        exit(0);
    }
}
