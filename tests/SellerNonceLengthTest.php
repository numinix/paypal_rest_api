<?php
/**
 * Test to verify that seller_nonce meets PayPal's minimum length requirement.
 *
 * This test validates the fix for the issue where seller_nonce was too short
 * (43 characters) causing PayPal to reject the partner referral request with:
 * "The length of a field value should not be shorter than 44 characters."
 *
 * The fix increases the random bytes from 32/33 to 34, which produces a
 * 46-character nonce after base64 URL-safe encoding, providing a 2-character
 * safety margin above PayPal's 44-character minimum requirement.
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
     * Test that seller_nonce generation produces strings of at least 44 characters
     */
    function testSellerNonceLength(): bool
    {
        $passed = true;
        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';

        if (!file_exists($serviceFile)) {
            fwrite(STDERR, "FAIL: SignupLinkService.php not found at $serviceFile\n");
            return false;
        }

        // Use reflection to test the protected method
        require_once $serviceFile;
        $service = new NuminixPaypalIsuSignupLinkService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateSellerNonce');
        $method->setAccessible(true);

        // Test multiple nonce generations to ensure consistency
        $minLength = 44;
        $maxLength = 0;
        $totalLength = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $nonce = $method->invoke($service);
            $length = strlen($nonce);
            
            $minLength = min($minLength, $length);
            $maxLength = max($maxLength, $length);
            $totalLength += $length;

            if ($length < 44) {
                fwrite(STDERR, "FAIL: Generated nonce is too short: $length characters (expected at least 44)\n");
                fwrite(STDERR, "  Nonce: $nonce\n");
                $passed = false;
            }
        }

        $avgLength = round($totalLength / $iterations, 1);

        if ($passed) {
            fwrite(STDOUT, "✓ All $iterations generated nonces meet the 44-character minimum\n");
            fwrite(STDOUT, "  Min length: $minLength\n");
            fwrite(STDOUT, "  Max length: $maxLength\n");
            fwrite(STDOUT, "  Avg length: $avgLength\n");
        }

        return $passed;
    }

    /**
     * Test that the code uses 34 bytes for nonce generation
     */
    function testSellerNonceByteCount(): bool
    {
        $passed = true;
        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check that the code uses 34 bytes (not 32 or 33)
        if (preg_match('/random_bytes\(34\)/', $content) && preg_match('/openssl_random_pseudo_bytes\(34\)/', $content)) {
            fwrite(STDOUT, "✓ Code uses 34 bytes for seller_nonce generation\n");
        } else {
            fwrite(STDERR, "FAIL: Code should use 34 bytes for seller_nonce generation\n");
            $passed = false;
        }

        // Check that old byte counts (32 or 33) are not used
        if (preg_match('/random_bytes\(32\)/', $content) || preg_match('/openssl_random_pseudo_bytes\(32\)/', $content)) {
            fwrite(STDERR, "FAIL: Code still uses 32 bytes (produces 43 characters - too short)\n");
            $passed = false;
        }

        if (preg_match('/random_bytes\(33\)/', $content) || preg_match('/openssl_random_pseudo_bytes\(33\)/', $content)) {
            fwrite(STDERR, "FAIL: Code still uses 33 bytes (produces exactly 44 characters - no safety margin)\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the code includes validation for minimum length
     */
    function testSellerNonceValidation(): bool
    {
        $passed = true;
        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check that validation exists to ensure nonce is at least 44 characters
        if (preg_match('/strlen\(\$nonce\)\s*<\s*44/', $content)) {
            fwrite(STDOUT, "✓ Code includes validation for 44-character minimum\n");
        } else {
            fwrite(STDERR, "FAIL: Code should validate that seller_nonce is at least 44 characters\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test the mathematical calculation for base64 encoding
     */
    function testBase64EncodingMath(): bool
    {
        $passed = true;

        fwrite(STDOUT, "\nBase64 encoding length calculations:\n");

        // Test different byte counts
        $testCases = [
            32 => 43,  // Too short
            33 => 44,  // Minimum (no margin)
            34 => 46,  // Safe (2-char margin)
        ];

        foreach ($testCases as $bytes => $expectedLength) {
            // Calculate expected length: ceil(bytes * 4 / 3)
            $calculatedLength = (int)ceil($bytes * 4 / 3);
            
            // Verify with actual encoding
            $actual = strlen(rtrim(base64_encode(random_bytes($bytes)), '='));
            
            $status = ($actual === $expectedLength && $actual === $calculatedLength) ? '✓' : '✗';
            $note = '';
            
            if ($bytes === 32) {
                $note = ' (TOO SHORT)';
            } elseif ($bytes === 33) {
                $note = ' (MINIMUM - NO MARGIN)';
            } elseif ($bytes === 34) {
                $note = ' (SAFE - 2 CHAR MARGIN)';
            }
            
            fwrite(STDOUT, "  $status $bytes bytes → $actual characters$note\n");
            
            if ($actual !== $expectedLength || $actual !== $calculatedLength) {
                $passed = false;
            }
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying seller_nonce meets 44-character minimum...\n");
    if (testSellerNonceLength()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying code uses 34 bytes for generation...\n");
    if (testSellerNonceByteCount()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying code includes length validation...\n");
    if (testSellerNonceValidation()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying base64 encoding math...\n");
    if (testBase64EncodingMath()) {
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
        fwrite(STDOUT, "\n✓ All seller_nonce length tests passed!\n");
        fwrite(STDOUT, "\nFix applied:\n");
        fwrite(STDOUT, "The generateSellerNonce() method now uses 34 bytes instead of 33,\n");
        fwrite(STDOUT, "producing a 46-character nonce that exceeds PayPal's 44-character\n");
        fwrite(STDOUT, "minimum requirement with a 2-character safety margin.\n");
        fwrite(STDOUT, "\nThis resolves the error:\n");
        fwrite(STDOUT, "'The length of a field value should not be shorter than 44 characters.'\n");
        fwrite(STDOUT, "for the /operations/0/api_integration_preference/rest_api_integration/\n");
        fwrite(STDOUT, "first_party_details/seller_nonce field.\n");
        exit(0);
    }
}
