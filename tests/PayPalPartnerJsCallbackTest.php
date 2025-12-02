<?php
/**
 * Test to verify that PayPal partner.js callback integration is properly implemented.
 *
 * Per PayPal docs: "When you use the mini-browser flow, PayPal's partner.js script
 * calls your callback function with (authCode, sharedId) parameters when the seller
 * completes the sign-up flow."
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
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
     * Test that admin page includes PayPal partner.js script
     */
    function testAdminIncludesPartnerJsScript(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify PayPal partner.js script is included
        if (strpos($content, 'webapps/merchantboarding/js/lib/lightbox/partner.js') !== false) {
            fwrite(STDOUT, "✓ Admin page includes PayPal partner.js script\n");
        } else {
            fwrite(STDERR, "FAIL: Admin page should include PayPal partner.js script\n");
            $passed = false;
        }

        // Check 2: Verify data-paypal-onboard-complete attribute is set
        if (strpos($content, 'data-paypal-onboard-complete') !== false) {
            fwrite(STDOUT, "✓ Admin page has data-paypal-onboard-complete attribute\n");
        } else {
            fwrite(STDERR, "FAIL: Admin page should have data-paypal-onboard-complete attribute\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that global callback function is defined
     */
    function testGlobalCallbackFunctionDefined(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify paypalOnboardedCallback function is defined
        if (strpos($content, 'function paypalOnboardedCallback(authCode, sharedId)') !== false) {
            fwrite(STDOUT, "✓ Global paypalOnboardedCallback function is defined\n");
        } else {
            fwrite(STDERR, "FAIL: Global paypalOnboardedCallback function should be defined\n");
            $passed = false;
        }

        // Check 2: Verify callback dispatches custom event
        if (strpos($content, "new CustomEvent('paypalOnboardingComplete'") !== false) {
            fwrite(STDOUT, "✓ Callback dispatches paypalOnboardingComplete event\n");
        } else {
            fwrite(STDERR, "FAIL: Callback should dispatch paypalOnboardingComplete event\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that admin JS listens for the custom callback event
     */
    function testAdminListensForCallbackEvent(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify event listener for paypalOnboardingComplete
        if (strpos($content, "addEventListener('paypalOnboardingComplete'") !== false) {
            fwrite(STDOUT, "✓ Admin JS listens for paypalOnboardingComplete event\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should listen for paypalOnboardingComplete event\n");
            $passed = false;
        }

        // Check 2: Verify handler extracts authCode from event detail
        if (strpos($content, 'detail.authCode') !== false) {
            fwrite(STDOUT, "✓ Event handler extracts authCode from detail\n");
        } else {
            fwrite(STDERR, "FAIL: Event handler should extract authCode from detail\n");
            $passed = false;
        }

        // Check 3: Verify handler extracts sharedId from event detail
        if (strpos($content, 'detail.sharedId') !== false) {
            fwrite(STDOUT, "✓ Event handler extracts sharedId from detail\n");
        } else {
            fwrite(STDERR, "FAIL: Event handler should extract sharedId from detail\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that mini-browser flow is supported
     */
    function testMiniBrowserFlowSupported(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify useMinibrowerFlow function exists
        if (strpos($content, 'function useMinibrowerFlow') !== false) {
            fwrite(STDOUT, "✓ useMinibrowerFlow function exists\n");
        } else {
            fwrite(STDERR, "FAIL: useMinibrowerFlow function should exist\n");
            $passed = false;
        }

        // Check 2: Verify it checks for PAYPAL.apps.Signup
        if (strpos($content, 'PAYPAL.apps.Signup') !== false) {
            fwrite(STDOUT, "✓ Mini-browser flow checks for PAYPAL.apps.Signup\n");
        } else {
            fwrite(STDERR, "FAIL: Mini-browser flow should check for PAYPAL.apps.Signup\n");
            $passed = false;
        }

        // Check 3: Verify it creates link with data-paypal-button attribute
        if (strpos($content, "data-paypal-button', 'true'") !== false) {
            fwrite(STDOUT, "✓ Mini-browser flow creates link with data-paypal-button attribute\n");
        } else {
            fwrite(STDERR, "FAIL: Mini-browser flow should create link with data-paypal-button attribute\n");
            $passed = false;
        }

        // Check 4: Verify paypal-signup-container exists
        if (strpos($content, 'paypal-signup-container') !== false) {
            fwrite(STDOUT, "✓ PayPal signup container element exists\n");
        } else {
            fwrite(STDERR, "FAIL: PayPal signup container element should exist\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that both popup and mini-browser flows are supported
     */
    function testDualFlowSupport(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify both flows are attempted
        if (strpos($content, 'useMinibrowerFlow(redirectUrl)') !== false &&
            strpos($content, 'openPayPalPopup(redirectUrl)') !== false) {
            fwrite(STDOUT, "✓ Both mini-browser and popup flows are supported\n");
        } else {
            fwrite(STDERR, "FAIL: Both mini-browser and popup flows should be supported\n");
            $passed = false;
        }

        // Check 2: Verify mini-browser is tried first with fallback to popup
        $miniBrowserPos = strpos($content, 'useMinibrowerFlow(redirectUrl)');
        $popupPos = strpos($content, 'openPayPalPopup(redirectUrl)');
        if ($miniBrowserPos !== false && $popupPos !== false && $miniBrowserPos < $popupPos) {
            fwrite(STDOUT, "✓ Mini-browser flow is tried before popup fallback\n");
        } else {
            fwrite(STDERR, "FAIL: Mini-browser flow should be tried before popup fallback\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying admin page includes PayPal partner.js script...\n");
    if (testAdminIncludesPartnerJsScript()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying global callback function is defined...\n");
    if (testGlobalCallbackFunctionDefined()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying admin JS listens for callback event...\n");
    if (testAdminListensForCallbackEvent()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying mini-browser flow is supported...\n");
    if (testMiniBrowserFlowSupported()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying dual flow support (popup and mini-browser)...\n");
    if (testDualFlowSupport()) {
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
        fwrite(STDOUT, "\n✓ All PayPal partner.js callback tests passed!\n");
        fwrite(STDOUT, "\nImplementation summary:\n");
        fwrite(STDOUT, "1. PayPal partner.js script is loaded with data-paypal-onboard-complete attribute.\n");
        fwrite(STDOUT, "2. Global paypalOnboardedCallback function receives authCode and sharedId.\n");
        fwrite(STDOUT, "3. Callback dispatches custom event for the main script to handle.\n");
        fwrite(STDOUT, "4. Mini-browser flow is supported with PAYPAL.apps.Signup integration.\n");
        fwrite(STDOUT, "5. Falls back to popup flow if mini-browser is not available.\n");
        fwrite(STDOUT, "\nThis implements PayPal's documented seller onboarding flow:\n");
        fwrite(STDOUT, "https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/\n");
        exit(0);
    }
}
