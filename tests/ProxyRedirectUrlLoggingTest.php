<?php
/**
 * Test to verify that HTTP redirect responses from Numinix API include the redirect URL in logs.
 *
 * This test validates the fix for the issue where HTTP 302 redirects from Numinix.com
 * didn't show the redirect destination in the debug logs, making it difficult to diagnose
 * why the API requests were failing.
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

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All proxy redirect URL logging tests passed!\n");
        fwrite(STDOUT, "\nThis fix ensures that when Numinix.com returns a 302 redirect,\n");
        fwrite(STDOUT, "the logs will show where the request was redirected to.\n");
        exit(0);
    }
}
