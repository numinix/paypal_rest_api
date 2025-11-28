<?php
/**
 * Test to verify that the completion page sends merchant_id via postMessage to parent window.
 *
 * This test validates the fix for the issue where PayPal credentials couldn't be retrieved
 * automatically because the merchant_id returned by PayPal wasn't being communicated back
 * to the admin panel for use in subsequent status polling.
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
     * Test that completion page extracts merchantId from PayPal return parameters
     */
    function testCompletionPageExtractsMerchantId(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify the completion page extracts merchantIdInPayPal
        if (strpos($content, "merchantIdInPayPal") !== false && 
            preg_match('/\$merchantId\s*=\s*nxp_paypal_filter_string\s*\(/', $content)) {
            fwrite(STDOUT, "✓ Completion page extracts merchantIdInPayPal from GET params\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should extract merchantIdInPayPal from GET params\n");
            $passed = false;
        }

        // Check 2: Verify the completion page also checks merchantId and merchant_id
        if (strpos($content, "'merchantId'") !== false && strpos($content, "'merchant_id'") !== false) {
            fwrite(STDOUT, "✓ Completion page checks multiple merchant ID parameter names\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should check multiple merchant ID parameter names\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that completion page sends postMessage with merchant_id to parent window
     */
    function testCompletionPageSendsPostMessage(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify postMessage is called
        if (strpos($content, 'window.opener.postMessage') !== false) {
            fwrite(STDOUT, "✓ Completion page calls window.opener.postMessage\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should call window.opener.postMessage\n");
            $passed = false;
        }

        // Check 2: Verify the message data includes event type
        if (strpos($content, "paypal_onboarding_complete") !== false) {
            fwrite(STDOUT, "✓ PostMessage includes paypal_onboarding_complete event\n");
        } else {
            fwrite(STDERR, "FAIL: PostMessage should include paypal_onboarding_complete event\n");
            $passed = false;
        }

        // Check 3: Verify merchantId is included in the postMessage data
        if (preg_match("/postMessageData\['merchantId'\]\s*=\s*\\\$merchantId/", $content)) {
            fwrite(STDOUT, "✓ PostMessage data includes merchantId\n");
        } else {
            fwrite(STDERR, "FAIL: PostMessage data should include merchantId\n");
            $passed = false;
        }

        // Check 4: Verify the message is JSON encoded for safe transmission
        if (strpos($content, 'JSON_HEX_TAG') !== false || strpos($content, 'json_encode') !== false) {
            fwrite(STDOUT, "✓ PostMessage data is safely JSON encoded\n");
        } else {
            fwrite(STDERR, "FAIL: PostMessage data should be safely JSON encoded\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that admin JavaScript handles merchant_id from postMessage
     */
    function testAdminJsHandlesMerchantIdFromMessage(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify handlePopupMessage extracts merchantId from payload
        if (strpos($content, 'payload.merchantId') !== false) {
            fwrite(STDOUT, "✓ Admin JS extracts merchantId from postMessage payload\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should extract merchantId from postMessage payload\n");
            $passed = false;
        }

        // Check 2: Verify it also checks merchant_id (snake_case variant)
        if (strpos($content, 'payload.merchant_id') !== false) {
            fwrite(STDOUT, "✓ Admin JS also checks merchant_id variant\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should also check merchant_id variant\n");
            $passed = false;
        }

        // Check 3: Verify state.merchantId is set from the message
        if (preg_match('/state\.merchantId\s*=\s*payload\.merchant/', $content)) {
            fwrite(STDOUT, "✓ Admin JS sets state.merchantId from message\n");
        } else {
            fwrite(STDERR, "FAIL: Admin JS should set state.merchantId from message\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that status polling includes merchant_id in request
     */
    function testStatusPollingIncludesMerchantId(): bool
    {
        $passed = true;

        $adminFile = DIR_FS_CATALOG . 'admin/paypalr_integrated_signup.php';
        $content = file_get_contents($adminFile);

        // Check 1: Verify pollStatus sends merchant_id
        if (preg_match("/merchant_id:\s*state\.merchantId/", $content)) {
            fwrite(STDOUT, "✓ Status polling includes merchant_id from state\n");
        } else {
            fwrite(STDERR, "FAIL: Status polling should include merchant_id from state\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying completion page extracts merchant ID...\n");
    if (testCompletionPageExtractsMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying completion page sends postMessage...\n");
    if (testCompletionPageSendsPostMessage()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying admin JS handles merchant_id from message...\n");
    if (testAdminJsHandlesMerchantIdFromMessage()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying status polling includes merchant_id...\n");
    if (testStatusPollingIncludesMerchantId()) {
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
        fwrite(STDOUT, "\n✓ All merchant ID postMessage tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. Completion page now extracts merchantIdInPayPal from PayPal's return redirect.\n");
        fwrite(STDOUT, "2. Completion page sends the merchant_id via postMessage to the parent window.\n");
        fwrite(STDOUT, "3. Admin JavaScript receives the message and stores merchant_id in state.\n");
        fwrite(STDOUT, "4. Subsequent status polling includes the merchant_id for credential retrieval.\n");
        exit(0);
    }
}
