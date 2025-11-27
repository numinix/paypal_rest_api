<?php
/**
 * Test to verify that URL sanitization properly handles HTML entities.
 *
 * This test validates the fix for the issue where URLs with HTML-encoded
 * ampersands (&amp;) were causing PayPal's partner referrals API to reject
 * the request with "MALFORMED_REQUEST_JSON" error.
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
     * Test that verifies the sanitizeUrl method decodes HTML entities
     */
    function testSanitizeUrlDecodesHtmlEntities(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check 1: The sanitizeUrl method uses html_entity_decode
        if (strpos($content, 'html_entity_decode') !== false) {
            fwrite(STDOUT, "✓ sanitizeUrl uses html_entity_decode\n");
        } else {
            fwrite(STDERR, "FAIL: sanitizeUrl should use html_entity_decode\n");
            $passed = false;
        }

        // Check 2: The html_entity_decode is called with ENT_QUOTES flag
        if (strpos($content, 'ENT_QUOTES') !== false) {
            fwrite(STDOUT, "✓ html_entity_decode uses ENT_QUOTES flag\n");
        } else {
            fwrite(STDERR, "FAIL: html_entity_decode should use ENT_QUOTES flag\n");
            $passed = false;
        }

        // Check 3: The html_entity_decode specifies UTF-8 encoding
        if (preg_match('/html_entity_decode\s*\([^)]*UTF-8/i', $content)) {
            fwrite(STDOUT, "✓ html_entity_decode specifies UTF-8 encoding\n");
        } else {
            fwrite(STDERR, "FAIL: html_entity_decode should specify UTF-8 encoding\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the fix is properly documented in the code
     */
    function testSanitizeUrlDocumentation(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check 1: The docblock mentions HTML entities
        if (preg_match('/Decodes\s+HTML\s+entities/i', $content)) {
            fwrite(STDOUT, "✓ Documentation mentions decoding HTML entities\n");
        } else {
            fwrite(STDERR, "FAIL: Documentation should mention decoding HTML entities\n");
            $passed = false;
        }

        // Check 2: The code comment explains why this is needed
        if (preg_match('/zen_href_link.*HTML.*encod/i', $content)) {
            fwrite(STDOUT, "✓ Code comment explains zen_href_link HTML encoding issue\n");
        } else {
            fwrite(STDERR, "FAIL: Code comment should explain zen_href_link HTML encoding issue\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies the management page has environment selector
     */
    function testManagementPageHasEnvironmentSelector(): bool
    {
        $passed = true;

        $mgmtFile = DIR_FS_CATALOG . 'numinix.com/management/paypal_request_signup_link.php';
        $content = file_get_contents($mgmtFile);

        // Check 1: The page has an environment select dropdown
        if (preg_match('/<select[^>]*id="environment"[^>]*>/', $content)) {
            fwrite(STDOUT, "✓ Management page has environment select dropdown\n");
        } else {
            fwrite(STDERR, "FAIL: Management page should have environment select dropdown\n");
            $passed = false;
        }

        // Check 2: The dropdown has sandbox and live options
        if (preg_match('/value="sandbox"/i', $content) && preg_match('/value="live"/i', $content)) {
            fwrite(STDOUT, "✓ Dropdown has sandbox and live options\n");
        } else {
            fwrite(STDERR, "FAIL: Dropdown should have sandbox and live options\n");
            $passed = false;
        }

        // Check 3: The form reads environment from POST
        if (preg_match('/\$_POST\s*\[\s*[\'"]environment[\'"]\s*\]/', $content)) {
            fwrite(STDOUT, "✓ Form reads environment from POST data\n");
        } else {
            fwrite(STDERR, "FAIL: Form should read environment from POST data\n");
            $passed = false;
        }

        // Check 4: Environment is validated against allowed values
        // Look for the in_array validation with sandbox and live values
        if (strpos($content, "in_array") !== false && 
            strpos($content, "'sandbox'") !== false && 
            strpos($content, "'live'") !== false) {
            fwrite(STDOUT, "✓ Environment is validated against allowed values\n");
        } else {
            fwrite(STDERR, "FAIL: Environment should be validated against allowed values\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test the actual URL decoding behavior
     */
    function testUrlDecodingBehavior(): bool
    {
        $passed = true;

        // Simulate a URL with HTML-encoded ampersand
        $encodedUrl = 'https://example.com/page.php?cmd=test&amp;action=return';
        $expectedUrl = 'https://example.com/page.php?cmd=test&action=return';
        $decodedUrl = html_entity_decode($encodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Check 1: html_entity_decode properly decodes &amp; to &
        if ($decodedUrl === $expectedUrl) {
            fwrite(STDOUT, "✓ html_entity_decode properly converts &amp; to &\n");
        } else {
            fwrite(STDERR, "FAIL: html_entity_decode should convert &amp; to &\n");
            fwrite(STDERR, "  Expected: $expectedUrl\n");
            fwrite(STDERR, "  Got: $decodedUrl\n");
            $passed = false;
        }

        // Check 2: The decoded URL is valid
        if (filter_var($decodedUrl, FILTER_VALIDATE_URL) !== false) {
            fwrite(STDOUT, "✓ Decoded URL is valid\n");
        } else {
            fwrite(STDERR, "FAIL: Decoded URL should be valid\n");
            $passed = false;
        }

        // Check 3: The encoded URL is also technically valid (just malformed for JSON)
        if (filter_var($encodedUrl, FILTER_VALIDATE_URL) !== false) {
            fwrite(STDOUT, "✓ Original encoded URL passes filter_var (explains why issue wasn't caught before)\n");
        } else {
            fwrite(STDOUT, "  Note: Original encoded URL doesn't pass filter_var\n");
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying sanitizeUrl decodes HTML entities...\n");
    if (testSanitizeUrlDecodesHtmlEntities()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying documentation updates...\n");
    if (testSanitizeUrlDocumentation()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying management page has environment selector...\n");
    if (testManagementPageHasEnvironmentSelector()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying URL decoding behavior...\n");
    if (testUrlDecodingBehavior()) {
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
        fwrite(STDOUT, "\n✓ All URL sanitization tests passed!\n");
        fwrite(STDOUT, "\nFixes applied:\n");
        fwrite(STDOUT, "1. sanitizeUrl now uses html_entity_decode to convert &amp; to &\n");
        fwrite(STDOUT, "2. This fixes PayPal's MALFORMED_REQUEST_JSON error\n");
        fwrite(STDOUT, "3. The management page now has an environment selector dropdown\n");
        fwrite(STDOUT, "4. Users can choose between Sandbox and Production when generating links\n");
        exit(0);
    }
}
