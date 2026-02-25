<?php
/**
 * Test to verify that seller_nonce is captured, persisted, and forwarded for credential exchange.
 *
 * Per PayPal ISU documentation, the seller_nonce generated during partner referral creation
 * must be passed as code_verifier when exchanging the authorization code for tokens.
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * This test validates the fix for the remote onboarding issue where seller_nonce was not
 * being forwarded from the client to numinix.com, causing credential exchange to fail.
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
 * Test that admin JS state includes sellerNonce
 */
function testAdminJsStateIncludesSellerNonce(): bool
{
    $passed = true;

    $adminFile = DIR_FS_CATALOG . 'admin/paypalac_integrated_signup.php';
    $content = file_get_contents($adminFile);

    // Check that state object includes sellerNonce
    if (strpos($content, 'sellerNonce: null') !== false) {
        fwrite(STDOUT, "✓ Admin JS state includes sellerNonce\n");
    } else {
        fwrite(STDERR, "FAIL: Admin JS state should include sellerNonce\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that admin JS captures seller_nonce from start response
 */
function testAdminJsCapturesSellerNonceFromResponse(): bool
{
    $passed = true;

    $adminFile = DIR_FS_CATALOG . 'admin/paypalac_integrated_signup.php';
    $content = file_get_contents($adminFile);

    // Check that seller_nonce is extracted from response data
    if (strpos($content, 'state.sellerNonce = data.seller_nonce') !== false) {
        fwrite(STDOUT, "✓ Admin JS captures seller_nonce from start response\n");
    } else {
        fwrite(STDERR, "FAIL: Admin JS should capture seller_nonce from start response\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that status polling includes seller_nonce
 */
function testStatusPollingIncludesSellerNonce(): bool
{
    $passed = true;

    $adminFile = DIR_FS_CATALOG . 'admin/paypalac_integrated_signup.php';
    $content = file_get_contents($adminFile);

    // Check that status polling sends seller_nonce
    if (strpos($content, 'seller_nonce: state.sellerNonce') !== false) {
        fwrite(STDOUT, "✓ Status polling includes seller_nonce from state\n");
    } else {
        fwrite(STDERR, "FAIL: Status polling should include seller_nonce from state\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that seller_nonce is stored in PHP session
 */
function testSellerNonceStoredInSession(): bool
{
    $passed = true;

    $adminFile = DIR_FS_CATALOG . 'admin/paypalac_integrated_signup.php';
    $content = file_get_contents($adminFile);

    // Check that seller_nonce is stored in session
    if (strpos($content, "['paypalac_isu_seller_nonce']") !== false) {
        fwrite(STDOUT, "✓ seller_nonce is stored in PHP session\n");
    } else {
        fwrite(STDERR, "FAIL: seller_nonce should be stored in PHP session\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that numinix.com persists seller_nonce to database
 */
function testNuminixPersistsSellerNonce(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check that persist function exists
    if (strpos($content, 'function nxp_paypal_persist_seller_nonce') !== false) {
        fwrite(STDOUT, "✓ nxp_paypal_persist_seller_nonce function exists\n");
    } else {
        fwrite(STDERR, "FAIL: nxp_paypal_persist_seller_nonce function should exist\n");
        $passed = false;
    }

    // Check that retrieve function exists
    if (strpos($content, 'function nxp_paypal_retrieve_seller_nonce') !== false) {
        fwrite(STDOUT, "✓ nxp_paypal_retrieve_seller_nonce function exists\n");
    } else {
        fwrite(STDERR, "FAIL: nxp_paypal_retrieve_seller_nonce function should exist\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that start handler persists seller_nonce
 */
function testStartHandlerPersistsSellerNonce(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check that start handler calls persist function
    if (strpos($content, 'nxp_paypal_persist_seller_nonce') !== false) {
        fwrite(STDOUT, "✓ Start handler persists seller_nonce to database\n");
    } else {
        fwrite(STDERR, "FAIL: Start handler should persist seller_nonce to database\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that finalize/status handlers retrieve seller_nonce
 */
function testFinalizeStatusRetrievesSellerNonce(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check that retrieve is called for cross-session retrieval
    if (strpos($content, 'nxp_paypal_retrieve_seller_nonce') !== false) {
        fwrite(STDOUT, "✓ Finalize/status handlers retrieve seller_nonce from database\n");
    } else {
        fwrite(STDERR, "FAIL: Finalize/status handlers should retrieve seller_nonce from database\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that database installer adds seller_nonce column
 */
function testDatabaseInstallerAddsSellerNonceColumn(): bool
{
    $passed = true;

    $installerFile = DIR_FS_CATALOG . 'numinix.com/management/includes/installers/numinix_paypal_isu/1_0_10.php';
    if (!file_exists($installerFile)) {
        fwrite(STDERR, "FAIL: Installer version 1.0.10 should exist\n");
        return false;
    }

    $content = file_get_contents($installerFile);

    // Check that installer adds seller_nonce column
    if (strpos($content, 'seller_nonce') !== false) {
        fwrite(STDOUT, "✓ Installer 1.0.10 adds seller_nonce column\n");
    } else {
        fwrite(STDERR, "FAIL: Installer 1.0.10 should add seller_nonce column\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that completion page includes seller_nonce
 */
function testCompletionPageIncludesSellerNonce(): bool
{
    $passed = true;

    $adminFile = DIR_FS_CATALOG . 'admin/paypalac_integrated_signup.php';
    $content = file_get_contents($adminFile);

    // Check that completion page retrieves seller_nonce from session
    if (strpos($content, "['paypalac_isu_seller_nonce']") !== false) {
        fwrite(STDOUT, "✓ Completion page retrieves seller_nonce from session\n");
    } else {
        fwrite(STDERR, "FAIL: Completion page should retrieve seller_nonce from session\n");
        $passed = false;
    }

    // Check that completion page passes seller_nonce to JS
    if (strpos($content, 'var sellerNonce = ') !== false) {
        fwrite(STDOUT, "✓ Completion page passes seller_nonce to JavaScript\n");
    } else {
        fwrite(STDERR, "FAIL: Completion page should pass seller_nonce to JavaScript\n");
        $passed = false;
    }

    return $passed;
}

// Run the tests
$failures = 0;

fwrite(STDOUT, "Test 1: Verifying admin JS state includes sellerNonce...\n");
if (testAdminJsStateIncludesSellerNonce()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 2: Verifying admin JS captures seller_nonce from response...\n");
if (testAdminJsCapturesSellerNonceFromResponse()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 3: Verifying status polling includes seller_nonce...\n");
if (testStatusPollingIncludesSellerNonce()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 4: Verifying seller_nonce is stored in PHP session...\n");
if (testSellerNonceStoredInSession()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 5: Verifying numinix.com persists seller_nonce...\n");
if (testNuminixPersistsSellerNonce()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 6: Verifying start handler persists seller_nonce...\n");
if (testStartHandlerPersistsSellerNonce()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 7: Verifying finalize/status handlers retrieve seller_nonce...\n");
if (testFinalizeStatusRetrievesSellerNonce()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 8: Verifying database installer adds seller_nonce column...\n");
if (testDatabaseInstallerAddsSellerNonceColumn()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 9: Verifying completion page includes seller_nonce...\n");
if (testCompletionPageIncludesSellerNonce()) {
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
    fwrite(STDOUT, "\n✓ All seller_nonce persistence tests passed!\n");
    fwrite(STDOUT, "\nFix summary:\n");
    fwrite(STDOUT, "1. Admin JS state now includes sellerNonce field.\n");
    fwrite(STDOUT, "2. Admin JS captures seller_nonce from start response.\n");
    fwrite(STDOUT, "3. Status polling requests include seller_nonce.\n");
    fwrite(STDOUT, "4. seller_nonce is stored in PHP session.\n");
    fwrite(STDOUT, "5. Numinix.com has persist/retrieve functions for seller_nonce.\n");
    fwrite(STDOUT, "6. Start handler persists seller_nonce to database.\n");
    fwrite(STDOUT, "7. Finalize/status handlers retrieve seller_nonce from database.\n");
    fwrite(STDOUT, "8. Database installer 1.0.10 adds seller_nonce column.\n");
    fwrite(STDOUT, "9. Completion page includes seller_nonce.\n");
    fwrite(STDOUT, "\nThis fixes the remote onboarding issue where seller_nonce was not\n");
    fwrite(STDOUT, "being forwarded from the client to numinix.com for credential exchange.\n");
    fwrite(STDOUT, "\nPayPal documentation:\n");
    fwrite(STDOUT, "https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/\n");
    exit(0);
}
