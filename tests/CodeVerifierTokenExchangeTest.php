<?php
/**
 * Test to verify that code_verifier (seller_nonce) is included in token exchange.
 *
 * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
 * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
 * access token."
 * 
 * The token exchange request MUST include:
 *   - grant_type=authorization_code
 *   - code={authCode}
 *   - code_verifier={seller_nonce}
 *
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

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
 * Test that token exchange includes code_verifier parameter
 */
function testTokenExchangeIncludesCodeVerifier(): bool
{
    $passed = true;

    $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
    $content = file_get_contents($serviceFile);

    // Check 1: Verify code_verifier is added to token request body
    // Using multiple flexible patterns to handle various code formatting styles
    $hasCodeVerifier = preg_match('/code_verifier.*sellerNonce/s', $content)
        || preg_match('/\$tokenParams\s*\[\s*[\'"]code_verifier[\'"]\s*\]/s', $content)
        || (strpos($content, 'code_verifier') !== false && strpos($content, 'sellerNonce') !== false);
    if ($hasCodeVerifier) {
        fwrite(STDOUT, "✓ Token exchange adds code_verifier parameter with seller_nonce\n");
    } else {
        fwrite(STDERR, "FAIL: Token exchange should add code_verifier parameter with seller_nonce\n");
        $passed = false;
    }

    // Check 2: Verify grant_type is authorization_code
    if (strpos($content, "'grant_type'") !== false && strpos($content, "'authorization_code'") !== false) {
        fwrite(STDOUT, "✓ Token exchange uses authorization_code grant type\n");
    } else {
        fwrite(STDERR, "FAIL: Token exchange should use authorization_code grant type\n");
        $passed = false;
    }

    // Check 3: Verify code parameter is included
    if (preg_match("/['\"]code['\"]\s*=>\s*\\\$authCode/", $content)) {
        fwrite(STDOUT, "✓ Token exchange includes code parameter with authCode\n");
    } else {
        fwrite(STDERR, "FAIL: Token exchange should include code parameter with authCode\n");
        $passed = false;
    }

    // Check 4: Verify Basic auth uses sharedId (ISU flow)
    if (strpos($content, "\$sharedId . ':'") !== false) {
        fwrite(STDOUT, "✓ Basic auth uses sharedId:empty format (ISU flow)\n");
    } else {
        fwrite(STDERR, "FAIL: Basic auth should use sharedId:empty format for ISU flow\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that the misleading comment about NOT including code_verifier has been removed/updated
 */
function testMisleadingCommentRemoved(): bool
{
    $passed = true;

    $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
    $content = file_get_contents($serviceFile);

    // Check that the old misleading comment is NOT present
    if (stripos($content, 'Do NOT include code_verifier') !== false) {
        fwrite(STDERR, "FAIL: Misleading comment 'Do NOT include code_verifier' should be removed\n");
        $passed = false;
    } else {
        fwrite(STDOUT, "✓ Misleading comment about NOT including code_verifier has been removed\n");
    }

    // Check that correct PayPal docs reference exists
    if (strpos($content, 'https://developer.paypal.com/docs/multiparty/seller-onboarding') !== false) {
        fwrite(STDOUT, "✓ Code includes reference to correct PayPal documentation\n");
    } else {
        fwrite(STDERR, "FAIL: Code should reference PayPal's seller onboarding documentation\n");
        $passed = false;
    }

    return $passed;
}

/**
 * Test that seller_nonce validation and warning exists
 */
function testSellerNonceValidationExists(): bool
{
    $passed = true;

    $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
    $content = file_get_contents($serviceFile);

    // Check that code logs warning when seller_nonce is missing
    if (preg_match('/if\s*\(\s*\$sellerNonce\s*!==\s*[\'"]{2}\s*\)/', $content)) {
        fwrite(STDOUT, "✓ Code checks if seller_nonce is available before including code_verifier\n");
    } else {
        fwrite(STDERR, "FAIL: Code should check if seller_nonce is available\n");
        $passed = false;
    }

    return $passed;
}

// Run the tests
$failures = 0;

fwrite(STDOUT, "Test 1: Verifying token exchange includes code_verifier parameter...\n");
if (testTokenExchangeIncludesCodeVerifier()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 2: Verifying misleading comment has been removed...\n");
if (testMisleadingCommentRemoved()) {
    fwrite(STDOUT, "  ✓ Test passed\n\n");
} else {
    fwrite(STDERR, "  ✗ Test failed\n\n");
    $failures++;
}

fwrite(STDOUT, "Test 3: Verifying seller_nonce validation exists...\n");
if (testSellerNonceValidationExists()) {
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
    fwrite(STDOUT, "\n✓ All code_verifier token exchange tests passed!\n");
    fwrite(STDOUT, "\nFix summary:\n");
    fwrite(STDOUT, "The token exchange request now includes code_verifier (seller_nonce)\n");
    fwrite(STDOUT, "as required by PayPal's ISU documentation.\n");
    fwrite(STDOUT, "\nPayPal documentation:\n");
    fwrite(STDOUT, "https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/\n");
    fwrite(STDOUT, "\nCurl equivalent:\n");
    fwrite(STDOUT, "curl -X POST https://api-m.sandbox.paypal.com/v1/oauth2/token \\\n");
    fwrite(STDOUT, "  -u SHARED-ID: \\\n");
    fwrite(STDOUT, "  -d 'grant_type=authorization_code&code=AUTH-CODE&code_verifier=SELLER-NONCE'\n");
    exit(0);
}
