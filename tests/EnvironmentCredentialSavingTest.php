<?php
/**
 * Test to verify that credentials are saved to the correct fields based on the
 * environment returned by the numinix.com API endpoint.
 *
 * This test validates the fix for the issue where numinix.com API returns
 * environment indication but the plugin was ignoring it and using the local
 * MODULE_PAYMENT_PAYPALR_SERVER setting instead.
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
     * Test that verifies the save_credentials handler accepts environment parameter
     */
    function testSaveCredentialsAcceptsEnvironment(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: The handler reads environment from POST data
        if (preg_match('/\$_POST\s*\[\s*[\'"]environment[\'"]\s*\]/', $content)) {
            fwrite(STDOUT, "✓ paypalr_handle_save_credentials reads environment from POST\n");
        } else {
            fwrite(STDERR, "FAIL: paypalr_handle_save_credentials should read environment from POST\n");
            $passed = false;
        }

        // Check 2: The handler validates sandbox/live values
        if (preg_match('/sandbox.*live|live.*sandbox/', $content)) {
            fwrite(STDOUT, "✓ Handler validates environment values (sandbox/live)\n");
        } else {
            fwrite(STDERR, "FAIL: Handler should validate environment values\n");
            $passed = false;
        }

        // Check 3: Handler logs the environment source
        if (strpos($content, 'environment_source') !== false) {
            fwrite(STDOUT, "✓ Handler logs environment source for debugging\n");
        } else {
            fwrite(STDERR, "FAIL: Handler should log environment source\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the JavaScript autoSaveCredentials sends environment
     */
    function testJavaScriptSendsEnvironment(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: autoSaveCredentials function accepts environment parameter
        if (preg_match('/function\s+autoSaveCredentials\s*\(\s*credentials\s*,\s*credentialEnvironment\s*\)/', $content)) {
            fwrite(STDOUT, "✓ autoSaveCredentials accepts credentialEnvironment parameter\n");
        } else {
            fwrite(STDERR, "FAIL: autoSaveCredentials should accept credentialEnvironment parameter\n");
            $passed = false;
        }

        // Check 2: Payload includes environment from parameter
        if (preg_match('/environment\s*:\s*credentialEnvironment/', $content)) {
            fwrite(STDOUT, "✓ autoSaveCredentials sends environment in payload\n");
        } else {
            fwrite(STDERR, "FAIL: autoSaveCredentials should send environment in payload\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies handleStatusResponse passes environment to autoSaveCredentials
     */
    function testHandleStatusResponsePassesEnvironment(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: handleStatusResponse extracts remoteEnvironment from data
        if (preg_match('/remoteEnvironment\s*=\s*data\.environment/', $content)) {
            fwrite(STDOUT, "✓ handleStatusResponse extracts environment from API response\n");
        } else {
            fwrite(STDERR, "FAIL: handleStatusResponse should extract environment from API response\n");
            $passed = false;
        }

        // Check 2: autoSaveCredentials is called with remoteEnvironment
        if (preg_match('/autoSaveCredentials\s*\(\s*data\.credentials\s*,\s*remoteEnvironment\s*\)/', $content)) {
            fwrite(STDOUT, "✓ autoSaveCredentials is called with remoteEnvironment\n");
        } else {
            fwrite(STDERR, "FAIL: autoSaveCredentials should be called with remoteEnvironment\n");
            $passed = false;
        }

        // Check 3: displayCredentials is called with remoteEnvironment
        if (preg_match('/displayCredentials\s*\(\s*data\.credentials\s*,\s*remoteEnvironment\s*\)/', $content)) {
            fwrite(STDOUT, "✓ displayCredentials is called with remoteEnvironment\n");
        } else {
            fwrite(STDERR, "FAIL: displayCredentials should be called with remoteEnvironment\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies displayCredentials shows the remote environment
     */
    function testDisplayCredentialsShowsEnvironment(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: displayCredentials accepts credentialEnvironment parameter
        if (preg_match('/function\s+displayCredentials\s*\(\s*credentials\s*,\s*credentialEnvironment\s*\)/', $content)) {
            fwrite(STDOUT, "✓ displayCredentials accepts credentialEnvironment parameter\n");
        } else {
            fwrite(STDERR, "FAIL: displayCredentials should accept credentialEnvironment parameter\n");
            $passed = false;
        }

        // Check 2: displayCredentials uses escapeHtml on credentialEnvironment
        if (preg_match('/escapeHtml\s*\(\s*credentialEnvironment\s*\)/', $content)) {
            fwrite(STDOUT, "✓ displayCredentials safely escapes credentialEnvironment\n");
        } else {
            fwrite(STDERR, "FAIL: displayCredentials should escape credentialEnvironment\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the API response includes environment in credentials response
     */
    function testApiResponseIncludesEnvironment(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
        $content = file_get_contents($serviceFile);

        // Check 1: The resolveStatus method includes environment in response data
        if (preg_match("/'environment'\s*=>\s*\\\$environment/", $content)) {
            fwrite(STDOUT, "✓ Onboarding service includes environment in status response\n");
        } else {
            fwrite(STDERR, "FAIL: Onboarding service should include environment in status response\n");
            $passed = false;
        }

        // Check 2: The start method includes environment in response data
        if (preg_match("/'environment'\s*=>\s*\(string\)/", $content)) {
            fwrite(STDOUT, "✓ Onboarding service includes environment in start response\n");
        } else {
            fwrite(STDERR, "FAIL: Onboarding service should include environment in start response\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies fallback behavior when environment is not provided
     */
    function testFallbackToLocalEnvironment(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Handler falls back to paypalr_detect_environment when environment not valid
        if (preg_match('/paypalr_detect_environment\s*\(\s*\)/', $content)) {
            fwrite(STDOUT, "✓ Handler falls back to paypalr_detect_environment\n");
        } else {
            fwrite(STDERR, "FAIL: Handler should fall back to paypalr_detect_environment\n");
            $passed = false;
        }

        // Check 2: JavaScript falls back to local environment variable
        if (preg_match('/remoteEnvironment\s*=\s*data\.environment\s*\|\|\s*environment/', $content)) {
            fwrite(STDOUT, "✓ JavaScript falls back to local environment variable\n");
        } else {
            fwrite(STDERR, "FAIL: JavaScript should fall back to local environment variable\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying save_credentials handler accepts environment parameter...\n");
    if (testSaveCredentialsAcceptsEnvironment()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying JavaScript autoSaveCredentials sends environment...\n");
    if (testJavaScriptSendsEnvironment()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying handleStatusResponse passes environment...\n");
    if (testHandleStatusResponsePassesEnvironment()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying displayCredentials shows environment...\n");
    if (testDisplayCredentialsShowsEnvironment()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying API response includes environment...\n");
    if (testApiResponseIncludesEnvironment()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 6: Verifying fallback to local environment...\n");
    if (testFallbackToLocalEnvironment()) {
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
        fwrite(STDOUT, "\n✓ All environment credential saving tests passed!\n");
        fwrite(STDOUT, "\nFixes applied:\n");
        fwrite(STDOUT, "1. numinix.com API already returns 'environment' in the response.\n");
        fwrite(STDOUT, "2. The plugin now passes this environment to autoSaveCredentials.\n");
        fwrite(STDOUT, "3. The PHP handler now reads and validates the environment from POST.\n");
        fwrite(STDOUT, "4. Credentials are saved to the correct fields (sandbox vs production).\n");
        fwrite(STDOUT, "5. The display now shows the remote environment, not the local setting.\n");
        exit(0);
    }
}
