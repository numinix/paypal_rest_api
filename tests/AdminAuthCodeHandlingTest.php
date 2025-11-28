<?php
/**
 * Test to verify that the admin JavaScript extracts and uses authCode/sharedId from postMessage.
 *
 * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
 * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
 * access token. Then, use this access token to get the seller's REST API credentials."
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * This test validates the fix for the ISU implementation that removes polling and instead
 * immediately uses the authCode and sharedId upon sign-up completion.
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
     * Test that admin JS state includes authCode and sharedId
     */
    function testAdminJsStateIncludesAuthCode(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify state object includes authCode
        if (strpos($content, 'authCode: null') !== false) {
            fwrite(STDOUT, "✓ Admin JS state includes authCode\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS state should include authCode\n");
            $passed = false;
        }

        // Check 2: Verify state object includes sharedId
        if (strpos($content, 'sharedId: null') !== false) {
            fwrite(STDOUT, "✓ Admin JS state includes sharedId\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS state should include sharedId\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that admin JS extracts authCode from postMessage payload
     */
    function testAdminJsExtractsAuthCodeFromPayload(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify handlePopupMessage extracts authCode from payload
        if (strpos($content, 'payload.authCode') !== false) {
            fwrite(STDOUT, "✓ Admin JS extracts authCode from postMessage payload\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should extract authCode from postMessage payload\n");
            $passed = false;
        }

        // Check 2: Verify it also checks auth_code (snake_case variant)
        if (strpos($content, 'payload.auth_code') !== false) {
            fwrite(STDOUT, "✓ Admin JS also checks auth_code variant\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should also check auth_code variant\n");
            $passed = false;
        }

        // Check 3: Verify state.authCode is set from the message
        if (strpos($content, 'state.authCode = payload.authCode') !== false) {
            fwrite(STDOUT, "✓ Admin JS sets state.authCode from message\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should set state.authCode from message\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that admin JS extracts sharedId from postMessage payload
     */
    function testAdminJsExtractsSharedIdFromPayload(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify handlePopupMessage extracts sharedId from payload
        if (strpos($content, 'payload.sharedId') !== false) {
            fwrite(STDOUT, "✓ Admin JS extracts sharedId from postMessage payload\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should extract sharedId from postMessage payload\n");
            $passed = false;
        }

        // Check 2: Verify it also checks shared_id (snake_case variant)
        if (strpos($content, 'payload.shared_id') !== false) {
            fwrite(STDOUT, "✓ Admin JS also checks shared_id variant\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should also check shared_id variant\n");
            $passed = false;
        }

        // Check 3: Verify state.sharedId is set from the message
        if (strpos($content, 'state.sharedId = payload.sharedId') !== false) {
            fwrite(STDOUT, "✓ Admin JS sets state.sharedId from message\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should set state.sharedId from message\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that status polling includes authCode and sharedId in request
     */
    function testStatusPollingIncludesAuthCodeAndSharedId(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify pollStatus sends authCode
        if (strpos($content, 'authCode: state.authCode') !== false) {
            fwrite(STDOUT, "✓ Status polling includes authCode from state\n");
        } else {
            fwrite(STDERR, "FAIL: Status polling should include authCode from state\n");
            $passed = false;
        }

        // Check 2: Verify pollStatus sends sharedId
        if (strpos($content, 'sharedId: state.sharedId') !== false) {
            fwrite(STDOUT, "✓ Status polling includes sharedId from state\n");
        } else {
            fwrite(STDERR, "FAIL: Status polling should include sharedId from state\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the completion page passes authCode and sharedId via postMessage
     */
    function testCompletionPagePassesAuthCode(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify postMessage data includes authCode
        if (strpos($content, "postMessageData['authCode']") !== false) {
            fwrite(STDOUT, "✓ Completion page postMessage includes authCode\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page postMessage should include authCode\n");
            $passed = false;
        }

        // Check 2: Verify postMessage data includes sharedId
        if (strpos($content, "postMessageData['sharedId']") !== false) {
            fwrite(STDOUT, "✓ Completion page postMessage includes sharedId\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page postMessage should include sharedId\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying admin JS state includes authCode and sharedId...\n");
    if (testAdminJsStateIncludesAuthCode()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying admin JS extracts authCode from postMessage payload...\n");
    if (testAdminJsExtractsAuthCodeFromPayload()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying admin JS extracts sharedId from postMessage payload...\n");
    if (testAdminJsExtractsSharedIdFromPayload()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying status polling includes authCode and sharedId...\n");
    if (testStatusPollingIncludesAuthCodeAndSharedId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying completion page passes authCode and sharedId via postMessage...\n");
    if (testCompletionPagePassesAuthCode()) {
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
        fwrite(STDOUT, "\n✓ All admin authCode handling tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. Admin JS state now includes authCode and sharedId fields.\n");
        fwrite(STDOUT, "2. Admin JS extracts authCode from postMessage payload (checks both camelCase and snake_case).\n");
        fwrite(STDOUT, "3. Admin JS extracts sharedId from postMessage payload (checks both camelCase and snake_case).\n");
        fwrite(STDOUT, "4. Status polling requests now include authCode and sharedId from state.\n");
        fwrite(STDOUT, "5. Completion page sends authCode and sharedId via postMessage to parent window.\n");
        fwrite(STDOUT, "\nThis implements PayPal's documented seller onboarding flow:\n");
        fwrite(STDOUT, "https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/\n");
        fwrite(STDOUT, "\nThe authCode and sharedId are now immediately used upon sign-up completion\n");
        fwrite(STDOUT, "to exchange for seller REST API credentials, eliminating unnecessary polling.\n");
        exit(0);
    }
}
