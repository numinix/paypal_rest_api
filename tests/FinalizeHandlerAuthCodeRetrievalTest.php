<?php
/**
 * Test to verify that the finalize handler retrieves authCode and sharedId from database.
 *
 * This test validates that when the completion page's finalize request doesn't include
 * authCode/sharedId (because they weren't in the PayPal redirect URL), the finalize
 * handler retrieves them from the database where they were persisted by the main page's
 * status call after receiving them via the PayPal callback.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

// Global setup
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
 * Test that finalize handler retrieves authCode from database
 */
function testFinalizeHandlerRetrievesAuthCode(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Find the finalize handler function
    $finalizeStart = strpos($content, 'function nxp_paypal_handle_finalize');
    if ($finalizeStart === false) {
        fwrite(STDERR, "FAIL: Could not find nxp_paypal_handle_finalize function\n");
        return false;
    }

    // Find the next function after finalize handler (to delimit the search area)
    $finalizeEnd = strpos($content, 'function nxp_paypal_handle_status', $finalizeStart);
    if ($finalizeEnd === false) {
        $finalizeEnd = strlen($content);
    }

    // Extract just the finalize handler code
    $finalizeCode = substr($content, $finalizeStart, $finalizeEnd - $finalizeStart);

    // Check 1: Verify finalize handler calls nxp_paypal_retrieve_auth_code
    if (strpos($finalizeCode, 'nxp_paypal_retrieve_auth_code') !== false) {
        fwrite(STDOUT, "✓ Finalize handler calls nxp_paypal_retrieve_auth_code\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should call nxp_paypal_retrieve_auth_code to retrieve from database\n");
        $passed = false;
    }

    // Check 2: Verify finalize handler retrieves persisted auth data
    if (strpos($finalizeCode, '$persistedAuthData') !== false) {
        fwrite(STDOUT, "✓ Finalize handler uses \$persistedAuthData variable\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should use \$persistedAuthData for retrieved auth code\n");
        $passed = false;
    }

    // Check 3: Verify finalize handler checks if authCode is empty before retrieving
    if (strpos($finalizeCode, 'empty($authCode)') !== false) {
        fwrite(STDOUT, "✓ Finalize handler checks if authCode is empty before database lookup\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should check if authCode is empty before database lookup\n");
        $passed = false;
    }

    // Check 4: Verify finalize handler checks if sharedId is empty before retrieving
    if (strpos($finalizeCode, 'empty($sharedId)') !== false) {
        fwrite(STDOUT, "✓ Finalize handler checks if sharedId is empty before database lookup\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should check if sharedId is empty before database lookup\n");
        $passed = false;
    }

    // Check 5: Verify finalize handler logs the retrieval
    if (strpos($finalizeCode, 'Retrieved authCode and sharedId from database for finalize') !== false) {
        fwrite(STDOUT, "✓ Finalize handler logs auth code retrieval from database\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should log auth code retrieval\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that both status and finalize handlers retrieve auth code from database
 */
function testBothHandlersRetrieveAuthCode(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Count occurrences of nxp_paypal_retrieve_auth_code calls
    $callCount = substr_count($content, 'nxp_paypal_retrieve_auth_code($trackingId)');

    // Should be called at least twice (once in status, once in finalize)
    if ($callCount >= 2) {
        fwrite(STDOUT, "✓ nxp_paypal_retrieve_auth_code is called in both status and finalize handlers ($callCount calls)\n");
    } else {
        fwrite(STDERR, "FAIL: nxp_paypal_retrieve_auth_code should be called in both handlers (found $callCount calls, expected >= 2)\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that finalize handler retrieves seller_nonce from database
 */
function testFinalizeHandlerRetrievesSellerNonce(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Find the finalize handler function
    $finalizeStart = strpos($content, 'function nxp_paypal_handle_finalize');
    if ($finalizeStart === false) {
        fwrite(STDERR, "FAIL: Could not find nxp_paypal_handle_finalize function\n");
        return false;
    }

    // Find the next function
    $finalizeEnd = strpos($content, 'function nxp_paypal_handle_status', $finalizeStart);
    if ($finalizeEnd === false) {
        $finalizeEnd = strlen($content);
    }

    $finalizeCode = substr($content, $finalizeStart, $finalizeEnd - $finalizeStart);

    // Check: Verify finalize handler retrieves seller_nonce
    if (strpos($finalizeCode, 'nxp_paypal_retrieve_seller_nonce') !== false) {
        fwrite(STDOUT, "✓ Finalize handler retrieves seller_nonce from database\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize handler should retrieve seller_nonce from database\n");
        $passed = false;
    }

    return $passed;
}

// Run the tests
$failures = 0;

fwrite(STDOUT, "Test 1: Verifying finalize handler retrieves authCode from database...\n");
if (testFinalizeHandlerRetrievesAuthCode()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 2: Verifying both status and finalize handlers retrieve auth code...\n");
if (testBothHandlersRetrieveAuthCode()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 3: Verifying finalize handler retrieves seller_nonce from database...\n");
if (testFinalizeHandlerRetrievesSellerNonce()) {
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
    fwrite(STDOUT, "\n✓ All finalize handler auth code retrieval tests passed!\n");
    fwrite(STDOUT, "\nFix summary:\n");
    fwrite(STDOUT, "The finalize handler now retrieves authCode, sharedId, and seller_nonce from\n");
    fwrite(STDOUT, "the database when they're not provided in the request. This handles the\n");
    fwrite(STDOUT, "cross-session case where:\n");
    fwrite(STDOUT, "1. Main page receives authCode/sharedId via PayPal callback\n");
    fwrite(STDOUT, "2. Main page's status call persists them to database\n");
    fwrite(STDOUT, "3. Completion page's finalize call retrieves them from database\n");
    fwrite(STDOUT, "4. Finalize exchanges authCode/sharedId for seller REST API credentials\n");
    exit(0);
}
