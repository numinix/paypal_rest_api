<?php
/**
 * Test to verify that nxp_paypal_current_url includes tracking_id from session.
 *
 * This test validates the fix for the issue where PayPal credentials couldn't be saved
 * because the tracking_id was not included in the return URL. When PayPal redirects back
 * to the completion page, the tracking_id is needed to persist the merchant_id so it can
 * be retrieved by subsequent status polling requests from a different session.
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
     * Test that nxp_paypal_current_url includes tracking_id from session
     */
    function testCurrentUrlIncludesTrackingIdFromSession(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify the function checks for tracking_id in session
        if (strpos($content, "\$_SESSION['nxp_paypal']['tracking_id']") !== false
            && strpos($content, "if (!isset(\$filtered['tracking_id']))") !== false) {
            fwrite(STDOUT, "✓ Function checks for tracking_id in session when not in URL params\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for tracking_id in session\n");
            $passed = false;
        }

        // Check 2: Verify the function includes tracking_id in filtered params
        if (strpos($content, "\$filtered['tracking_id'] = \$sanitized") !== false) {
            fwrite(STDOUT, "✓ Function adds tracking_id from session to filtered params\n");
        } else {
            fwrite(STDERR, "FAIL: Function should add tracking_id from session to filtered params\n");
            $passed = false;
        }

        // Check 3: Verify the session tracking_id is sanitized before use
        if (preg_match('/\$sessionTrackingId\s*=\s*\$_SESSION\[[^\]]+\]\[[^\]]+\].*nxp_paypal_filter_string\(\$sessionTrackingId\)/s', $content)) {
            fwrite(STDOUT, "✓ Session tracking_id is sanitized before use\n");
        } else {
            fwrite(STDERR, "FAIL: Session tracking_id should be sanitized before use\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that nxp_paypal_current_url includes environment from session
     */
    function testCurrentUrlIncludesEnvFromSession(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify the function checks for env in session
        if (strpos($content, "\$_SESSION['nxp_paypal']['env']") !== false
            && strpos($content, "if (!isset(\$filtered['env']))") !== false) {
            fwrite(STDOUT, "✓ Function checks for env in session when not in URL params\n");
        } else {
            fwrite(STDERR, "FAIL: Function should check for env in session\n");
            $passed = false;
        }

        // Check 2: Verify the function validates env against allowed values
        if (strpos($content, "in_array(\$sessionEnv, ['sandbox', 'live'], true)") !== false) {
            fwrite(STDOUT, "✓ Function validates env against allowed values\n");
        } else {
            fwrite(STDERR, "FAIL: Function should validate env against allowed values\n");
            $passed = false;
        }

        // Check 3: Verify the function includes env in filtered params
        if (strpos($content, "\$filtered['env'] = \$sessionEnv") !== false) {
            fwrite(STDOUT, "✓ Function adds env from session to filtered params\n");
        } else {
            fwrite(STDERR, "FAIL: Function should add env from session to filtered params\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that documentation explains the purpose of including tracking_id
     */
    function testDocumentationExplainsTrackingIdPurpose(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify the function docblock mentions tracking_id inclusion
        if (strpos($content, 'tracking_id from the session') !== false
            || strpos($content, 'tracking_id and environment from the session') !== false) {
            fwrite(STDOUT, "✓ Function docblock mentions tracking_id inclusion from session\n");
        } else {
            fwrite(STDERR, "FAIL: Function docblock should mention tracking_id inclusion from session\n");
            $passed = false;
        }

        // Check 2: Verify the code comment explains why tracking_id is needed
        if (strpos($content, 'cross-session') !== false
            && strpos($content, 'merchant_id') !== false
            && strpos($content, 'tracking_id') !== false) {
            fwrite(STDOUT, "✓ Code comments explain the cross-session merchant_id persistence purpose\n");
        } else {
            fwrite(STDERR, "FAIL: Code comments should explain the cross-session persistence purpose\n");
            $passed = false;
        }

        // Check 3: Verify the code comment mentions PayPal redirect
        if (strpos($content, 'PayPal') !== false
            && strpos($content, 'redirect') !== false) {
            fwrite(STDOUT, "✓ Code comments mention PayPal redirect scenario\n");
        } else {
            fwrite(STDERR, "FAIL: Code comments should mention PayPal redirect scenario\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying nxp_paypal_current_url includes tracking_id from session...\n");
    if (testCurrentUrlIncludesTrackingIdFromSession()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying nxp_paypal_current_url includes env from session...\n");
    if (testCurrentUrlIncludesEnvFromSession()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying documentation explains the purpose...\n");
    if (testDocumentationExplainsTrackingIdPurpose()) {
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
        fwrite(STDOUT, "\n✓ All return URL tracking_id tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. nxp_paypal_current_url now includes tracking_id from session in the return URL.\n");
        fwrite(STDOUT, "2. nxp_paypal_current_url now includes environment from session in the return URL.\n");
        fwrite(STDOUT, "3. When PayPal redirects back, the completion page has the tracking_id to persist merchant_id.\n");
        fwrite(STDOUT, "4. This enables cross-session credential retrieval for the onboarding flow.\n");
        exit(0);
    }
}
