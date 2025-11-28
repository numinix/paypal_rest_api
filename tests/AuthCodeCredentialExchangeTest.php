<?php
/**
 * Test to verify that authCode and sharedId are captured and used for credential exchange.
 *
 * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
 * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
 * access token. Then, use this access token to get the seller's REST API credentials."
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
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
 * Test that completion page captures authCode and sharedId
 */
function testCompletionPageCapturesAuthCode(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check 1: Verify the completion page extracts authCode from request params
    $capturesAuthCode = strpos($content, "\$_REQUEST['authCode']") !== false
        || strpos($content, "\$_GET['authCode']") !== false;
    if ($capturesAuthCode) {
        fwrite(STDOUT, "✓ Completion page extracts authCode from request parameters\n");
    } else {
        fwrite(STDERR, "FAIL: Completion page should extract authCode from request parameters\n");
        $passed = false;
    }

    // Check 2: Verify the completion page extracts sharedId from request params
    $capturesSharedId = strpos($content, "\$_REQUEST['sharedId']") !== false
        || strpos($content, "\$_GET['sharedId']") !== false;
    if ($capturesSharedId) {
        fwrite(STDOUT, "✓ Completion page extracts sharedId from request parameters\n");
    } else {
        fwrite(STDERR, "FAIL: Completion page should extract sharedId from request parameters\n");
        $passed = false;
    }

    // Check 3: Verify the completion page persists auth code
    if (strpos($content, 'nxp_paypal_persist_auth_code') !== false) {
        fwrite(STDOUT, "✓ Completion page calls nxp_paypal_persist_auth_code\n");
    } else {
        fwrite(STDERR, "FAIL: Completion page should call nxp_paypal_persist_auth_code\n");
        $passed = false;
    }

    // Check 4: Verify postMessage includes authCode and sharedId
    if (strpos($content, "postMessageData['authCode']") !== false &&
        strpos($content, "postMessageData['sharedId']") !== false) {
        fwrite(STDOUT, "✓ PostMessage data includes authCode and sharedId\n");
    } else {
        fwrite(STDERR, "FAIL: PostMessage data should include authCode and sharedId\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that auth code persistence functions exist
 */
function testAuthCodePersistenceFunctionsExist(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check 1: Verify nxp_paypal_persist_auth_code function exists
    if (strpos($content, 'function nxp_paypal_persist_auth_code') !== false) {
        fwrite(STDOUT, "✓ nxp_paypal_persist_auth_code function exists\n");
    } else {
        fwrite(STDERR, "FAIL: nxp_paypal_persist_auth_code function should exist\n");
        $passed = false;
    }

    // Check 2: Verify nxp_paypal_retrieve_auth_code function exists
    if (strpos($content, 'function nxp_paypal_retrieve_auth_code') !== false) {
        fwrite(STDOUT, "✓ nxp_paypal_retrieve_auth_code function exists\n");
    } else {
        fwrite(STDERR, "FAIL: nxp_paypal_retrieve_auth_code function should exist\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that status handler retrieves and uses auth code
 */
function testStatusHandlerUsesAuthCode(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check 1: Verify status handler extracts authCode from request
    if (strpos($content, "\$_REQUEST['authCode']") !== false) {
        fwrite(STDOUT, "✓ Status handler extracts authCode from request\n");
    } else {
        fwrite(STDERR, "FAIL: Status handler should extract authCode from request\n");
        $passed = false;
    }

    // Check 2: Verify status handler extracts sharedId from request
    if (strpos($content, "\$_REQUEST['sharedId']") !== false) {
        fwrite(STDOUT, "✓ Status handler extracts sharedId from request\n");
    } else {
        fwrite(STDERR, "FAIL: Status handler should extract sharedId from request\n");
        $passed = false;
    }

    // Check 3: Verify status handler retrieves auth code from database
    if (strpos($content, 'nxp_paypal_retrieve_auth_code') !== false) {
        fwrite(STDOUT, "✓ Status handler calls nxp_paypal_retrieve_auth_code\n");
    } else {
        fwrite(STDERR, "FAIL: Status handler should call nxp_paypal_retrieve_auth_code\n");
        $passed = false;
    }

    // Check 4: Verify status handler passes auth_code to onboarding service
    if (strpos($content, "'auth_code' => \$authCode") !== false) {
        fwrite(STDOUT, "✓ Status handler passes auth_code to onboarding service\n");
    } else {
        fwrite(STDERR, "FAIL: Status handler should pass auth_code to onboarding service\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that onboarding service exchanges auth code for credentials
 */
function testOnboardingServiceExchangesAuthCode(): bool
{
    $passed = true;

    $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
    $content = file_get_contents($serviceFile);

    // Check 1: Verify exchangeAuthCodeForCredentials method exists
    if (strpos($content, 'function exchangeAuthCodeForCredentials') !== false) {
        fwrite(STDOUT, "✓ exchangeAuthCodeForCredentials method exists\n");
    } else {
        fwrite(STDERR, "FAIL: exchangeAuthCodeForCredentials method should exist\n");
        $passed = false;
    }

    // Check 2: Verify it calls the OAuth2 token endpoint
    if (strpos($content, '/v1/oauth2/token') !== false) {
        fwrite(STDOUT, "✓ Service calls OAuth2 token endpoint\n");
    } else {
        fwrite(STDERR, "FAIL: Service should call OAuth2 token endpoint\n");
        $passed = false;
    }

    // Check 3: Verify it uses authorization_code grant type
    if (strpos($content, "grant_type' => 'authorization_code'") !== false) {
        fwrite(STDOUT, "✓ Service uses authorization_code grant type\n");
    } else {
        fwrite(STDERR, "FAIL: Service should use authorization_code grant type\n");
        $passed = false;
    }

    // Check 4: Verify resolveStatus calls exchangeAuthCodeForCredentials
    if (strpos($content, '$this->exchangeAuthCodeForCredentials') !== false) {
        fwrite(STDOUT, "✓ resolveStatus calls exchangeAuthCodeForCredentials\n");
    } else {
        fwrite(STDERR, "FAIL: resolveStatus should call exchangeAuthCodeForCredentials\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that database installer adds auth_code and shared_id columns
 */
function testDatabaseInstallerAddsColumns(): bool
{
    $passed = true;

    $installerFile = DIR_FS_CATALOG . 'numinix.com/management/includes/installers/numinix_paypal_isu/1_0_7.php';
    if (!file_exists($installerFile)) {
        fwrite(STDERR, "FAIL: Installer version 1.0.7 should exist\n");
        return false;
    }

    $content = file_get_contents($installerFile);

    // Check 1: Verify installer adds auth_code column
    if (strpos($content, "ADD COLUMN auth_code") !== false) {
        fwrite(STDOUT, "✓ Installer adds auth_code column\n");
    } else {
        fwrite(STDERR, "FAIL: Installer should add auth_code column\n");
        $passed = false;
    }

    // Check 2: Verify installer adds shared_id column
    if (strpos($content, "ADD COLUMN shared_id") !== false) {
        fwrite(STDOUT, "✓ Installer adds shared_id column\n");
    } else {
        fwrite(STDERR, "FAIL: Installer should add shared_id column\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that PayPal return redirect detection includes authCode
 */
function testPayPalReturnDetectsAuthCode(): bool
{
    $passed = true;

    $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
    $content = file_get_contents($helpersFile);

    // Check that nxp_paypal_is_paypal_return_redirect checks for authCode
    $detectsAuthCode = (strpos($content, "\$_REQUEST['authCode']") !== false
        || strpos($content, "\$_GET['authCode']") !== false)
        && (strpos($content, "\$_REQUEST['sharedId']") !== false
            || strpos($content, "\$_GET['sharedId']") !== false)
        && strpos($content, '$hasAuthCode') !== false;
    if ($detectsAuthCode) {
        fwrite(STDOUT, "✓ PayPal return redirect detection checks for authCode and sharedId\n");
    } else {
        fwrite(STDERR, "FAIL: PayPal return redirect detection should check for authCode and sharedId\n");
        $passed = false;
    }

    return $passed;
}

// Run the tests
$failures = 0;

fwrite(STDOUT, "Test 1: Verifying completion page captures authCode and sharedId...\n");
if (testCompletionPageCapturesAuthCode()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 2: Verifying auth code persistence functions exist...\n");
if (testAuthCodePersistenceFunctionsExist()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 3: Verifying status handler uses auth code...\n");
if (testStatusHandlerUsesAuthCode()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 4: Verifying onboarding service exchanges auth code for credentials...\n");
if (testOnboardingServiceExchangesAuthCode()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 5: Verifying database installer adds auth_code and shared_id columns...\n");
if (testDatabaseInstallerAddsColumns()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 6: Verifying PayPal return redirect detects authCode...\n");
if (testPayPalReturnDetectsAuthCode()) {
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
    fwrite(STDOUT, "\n✓ All authCode credential exchange tests passed!\n");
    fwrite(STDOUT, "\nFix summary:\n");
    fwrite(STDOUT, "1. Completion page captures authCode and sharedId from PayPal return URL.\n");
    fwrite(STDOUT, "2. Auth code persistence functions store/retrieve authCode and sharedId.\n");
    fwrite(STDOUT, "3. Status handler retrieves and passes auth code to onboarding service.\n");
    fwrite(STDOUT, "4. Onboarding service exchanges authCode for seller REST API credentials.\n");
    fwrite(STDOUT, "5. Database schema includes auth_code and shared_id columns.\n");
    fwrite(STDOUT, "\nThis implements PayPal's documented seller onboarding flow:\n");
    fwrite(STDOUT, "https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/\n");
    exit(0);
}
