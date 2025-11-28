<?php
/**
 * Test to verify the default features for Partner Referrals API.
 *
 * This test validates that PARTNER_FEE is not included in the default features
 * as it requires special PayPal partner account configuration that is not
 * universally available (particularly on production accounts).
 *
 * Issue: Partner referral requests fail in production with FEATURES_UNAUTHORIZED
 * error when PARTNER_FEE is included but the REST app doesn't have that feature
 * configured.
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
     * Test that default features do NOT include PARTNER_FEE
     */
    function testDefaultFeaturesExcludePartnerFee(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Find the resolveFeatures method and extract the default array
        // Looking for: return $this->sanitizeStringList($value, ['PAYMENT', 'REFUND']);
        $pattern = '/protected\s+function\s+resolveFeatures\s*\([^)]*\)\s*:\s*array\s*\{[^}]*sanitizeStringList\s*\(\s*\$value\s*,\s*\[([^\]]+)\]\s*\)/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $defaultFeatures = $matches[1];
            
            // Check that PAYMENT is included
            if (strpos($defaultFeatures, "'PAYMENT'") !== false) {
                fwrite(STDOUT, "✓ Default features include PAYMENT\n");
            } else {
                fwrite(STDERR, "FAIL: Default features should include PAYMENT\n");
                $passed = false;
            }
            
            // Check that REFUND is included
            if (strpos($defaultFeatures, "'REFUND'") !== false) {
                fwrite(STDOUT, "✓ Default features include REFUND\n");
            } else {
                fwrite(STDERR, "FAIL: Default features should include REFUND\n");
                $passed = false;
            }
            
            // Check that PARTNER_FEE is NOT included
            if (strpos($defaultFeatures, "PARTNER_FEE") === false) {
                fwrite(STDOUT, "✓ Default features do NOT include PARTNER_FEE (intentional - requires special account config)\n");
            } else {
                fwrite(STDERR, "FAIL: Default features should NOT include PARTNER_FEE (causes FEATURES_UNAUTHORIZED in production)\n");
                $passed = false;
            }
        } else {
            fwrite(STDERR, "FAIL: Could not find resolveFeatures method pattern in SignupLinkService.php\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the docblock documents why PARTNER_FEE is excluded
     */
    function testResolveFeaturesDocumentation(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Find the docblock for resolveFeatures method by looking for the method and extracting preceding comment
        $pattern = '/(\/\*\*[\s\S]*?\*\/)\s*protected\s+function\s+resolveFeatures/';
        
        if (preg_match($pattern, $content, $matches)) {
            $docblock = $matches[1];
            
            // Check that the docblock explains why PARTNER_FEE is excluded
            if (stripos($docblock, 'PARTNER_FEE') !== false && 
                (stripos($docblock, 'excluded') !== false || stripos($docblock, 'intentionally') !== false)) {
                fwrite(STDOUT, "✓ Documentation explains PARTNER_FEE exclusion\n");
            } else {
                fwrite(STDERR, "FAIL: Documentation should explain why PARTNER_FEE is excluded from defaults\n");
                $passed = false;
            }
            
            // Check that the docblock mentions the account configuration requirement
            if (stripos($docblock, 'account') !== false && 
                (stripos($docblock, 'configuration') !== false || stripos($docblock, 'setup') !== false)) {
                fwrite(STDOUT, "✓ Documentation mentions account configuration requirement\n");
            } else {
                fwrite(STDERR, "FAIL: Documentation should mention account configuration requirement\n");
                $passed = false;
            }
            
            // Check that the docblock mentions FEATURES_UNAUTHORIZED or the production issue
            if (stripos($docblock, 'FEATURES_UNAUTHORIZED') !== false || 
                stripos($docblock, 'production') !== false) {
                fwrite(STDOUT, "✓ Documentation mentions the production/authorization issue\n");
            } else {
                fwrite(STDERR, "FAIL: Documentation should mention the FEATURES_UNAUTHORIZED or production issue\n");
                $passed = false;
            }
        } else {
            fwrite(STDERR, "FAIL: Could not find resolveFeatures docblock\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that custom features can still be provided (PARTNER_FEE can be explicitly requested)
     */
    function testCustomFeaturesAreStillSupported(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check that buildPayload still passes features from options
        if (strpos($content, "\$this->resolveFeatures(\$options['features']") !== false) {
            fwrite(STDOUT, "✓ Custom features from options are supported (callers can still request PARTNER_FEE explicitly)\n");
        } else {
            fwrite(STDERR, "FAIL: Should support custom features from options array\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying default features exclude PARTNER_FEE...\n");
    if (testDefaultFeaturesExcludePartnerFee()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying documentation explains the PARTNER_FEE exclusion...\n");
    if (testResolveFeaturesDocumentation()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying custom features are still supported...\n");
    if (testCustomFeaturesAreStillSupported()) {
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
        fwrite(STDOUT, "\n✓ All Partner Referrals features tests passed!\n");
        fwrite(STDOUT, "\nFix applied:\n");
        fwrite(STDOUT, "The default features for Partner Referrals API no longer include\n");
        fwrite(STDOUT, "PARTNER_FEE. This feature requires special PayPal partner account\n");
        fwrite(STDOUT, "configuration that is not universally available, particularly on\n");
        fwrite(STDOUT, "production accounts.\n");
        fwrite(STDOUT, "\nThis resolves the 'FEATURES_UNAUTHORIZED' error that occurred when\n");
        fwrite(STDOUT, "generating signup links in production environments where the REST app\n");
        fwrite(STDOUT, "does not have PARTNER_FEE configured.\n");
        fwrite(STDOUT, "\nNote: Callers can still explicitly request PARTNER_FEE by passing it\n");
        fwrite(STDOUT, "in the 'features' option if their account supports it.\n");
        exit(0);
    }
}
