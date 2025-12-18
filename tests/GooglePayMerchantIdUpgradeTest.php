<?php
/**
 * Test to verify that the Google Pay module correctly adds the MERCHANT_ID
 * configuration when upgrading to version 1.3.7, even if the version number
 * is already at 1.3.7 but the configuration is missing.
 *
 * This test validates the fix for the issue where users who upgraded to 1.3.7
 * did not see the new Google Merchant ID option in the configuration.
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
     * Test that tableCheckup() adds missing MERCHANT_ID config when version is already 1.3.7
     */
    function testTableCheckupAddsConfigWhenVersionIsCurrent(): bool
    {
        $passed = true;
        $googlePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalr_googlepay.php';
        $content = file_get_contents($googlePayFile);

        // Check that tableCheckup validates config exists when version is current
        $hasVersionCheck = strpos($content, '$version_is_current') !== false;
        $checksConfigExists = strpos($content, "WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID'") !== false;
        $appliesConfigInDefault = strpos($content, "default:") !== false && 
                                   strpos($content, "applyVersionSqlFile('1.3.7_add_googlepay_merchant_id.sql')") !== false;

        if ($hasVersionCheck) {
            fwrite(STDOUT, "✓ tableCheckup checks if version is current\n");
        } else {
            fwrite(STDERR, "FAIL: tableCheckup should check if version is current\n");
            $passed = false;
        }

        if ($checksConfigExists) {
            fwrite(STDOUT, "✓ tableCheckup verifies MERCHANT_ID config exists\n");
        } else {
            fwrite(STDERR, "FAIL: tableCheckup should verify MERCHANT_ID config exists\n");
            $passed = false;
        }

        if ($appliesConfigInDefault) {
            fwrite(STDOUT, "✓ tableCheckup applies missing config in default case\n");
        } else {
            fwrite(STDERR, "FAIL: tableCheckup should apply missing config in default case\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the logic handles both upgrade scenarios correctly
     */
    function testUpgradeLogicHandlesBothScenarios(): bool
    {
        $passed = true;
        $googlePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalr_googlepay.php';
        $content = file_get_contents($googlePayFile);

        // Find the tableCheckup method
        $methodStart = strpos($content, 'protected function tableCheckup()');
        if ($methodStart === false) {
            fwrite(STDERR, "FAIL: Could not find tableCheckup method in Google Pay module\n");
            return false;
        }

        $methodEnd = strpos($content, "\n    }", $methodStart + 100);
        $methodBody = substr($content, $methodStart, $methodEnd - $methodStart);

        // Check for proper fallthrough from version < 1.3.7
        $hasVersionCompare137 = strpos($methodBody, "version_compare(MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION, '1.3.7', '<')") !== false;
        
        // Check that it handles case when version IS 1.3.7 but config is missing
        $hasDefaultCase = strpos($methodBody, 'default:') !== false;
        $usesHelperMethod = strpos($content, 'merchantIdConfigExists()') !== false;
        $defaultCaseHandlesMissingConfig = strpos($methodBody, 'if ($version_is_current && !$this->merchantIdConfigExists())') !== false &&
                                           strpos($methodBody, "applyVersionSqlFile('1.3.7_add_googlepay_merchant_id.sql')") !== false;

        if ($hasVersionCompare137) {
            fwrite(STDOUT, "✓ tableCheckup handles upgrade from version < 1.3.7\n");
        } else {
            fwrite(STDERR, "FAIL: tableCheckup should handle upgrade from version < 1.3.7\n");
            $passed = false;
        }

        if ($hasDefaultCase && $usesHelperMethod && $defaultCaseHandlesMissingConfig) {
            fwrite(STDOUT, "✓ tableCheckup handles missing config when version is already 1.3.7\n");
        } else {
            fwrite(STDERR, "FAIL: tableCheckup should handle missing config when version is already 1.3.7\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that early return only happens when all configs exist
     */
    function testEarlyReturnOnlyWhenConfigsExist(): bool
    {
        $passed = true;
        $googlePayFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalr_googlepay.php';
        $content = file_get_contents($googlePayFile);

        // Find the tableCheckup method
        $methodStart = strpos($content, 'protected function tableCheckup()');
        if ($methodStart === false) {
            fwrite(STDERR, "FAIL: Could not find tableCheckup method\n");
            return false;
        }

        $methodEnd = strpos($content, "\n    }", $methodStart + 100);
        $methodBody = substr($content, $methodStart, $methodEnd - $methodStart);

        // Check that early return is guarded by config existence check
        $usesHelperMethod = strpos($content, 'merchantIdConfigExists()') !== false;
        $earlyReturnUsesHelper = strpos($methodBody, 'if ($version_is_current && $this->merchantIdConfigExists())') !== false;
        $hasEarlyReturn = strpos($methodBody, 'return;') !== false;

        if ($usesHelperMethod && $earlyReturnUsesHelper && $hasEarlyReturn) {
            fwrite(STDOUT, "✓ Early return only happens when MERCHANT_ID config exists (via helper method)\n");
        } else {
            fwrite(STDERR, "FAIL: Early return should be guarded by config existence check via helper method\n");
            $passed = false;
        }

        return $passed;
    }

    // Run tests
    fwrite(STDOUT, "Test 1: Verifying tableCheckup adds missing config when version is current...\n");
    $test1 = testTableCheckupAddsConfigWhenVersionIsCurrent();
    if ($test1) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
    }

    fwrite(STDOUT, "Test 2: Verifying upgrade logic handles both scenarios...\n");
    $test2 = testUpgradeLogicHandlesBothScenarios();
    if ($test2) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
    }

    fwrite(STDOUT, "Test 3: Verifying early return only when configs exist...\n");
    $test3 = testEarlyReturnOnlyWhenConfigsExist();
    if ($test3) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
    }

    if ($test1 && $test2 && $test3) {
        fwrite(STDOUT, "\n✓ All Google Pay merchant ID upgrade tests passed!\n\n");
        fwrite(STDOUT, "Fix summary:\n");
        fwrite(STDOUT, "1. tableCheckup now checks if MERCHANT_ID config exists before early return\n");
        fwrite(STDOUT, "2. Handles upgrade from version < 1.3.7 (applies SQL via version compare)\n");
        fwrite(STDOUT, "3. Handles missing config when version is already 1.3.7 (applies SQL via default case)\n");
        fwrite(STDOUT, "4. Early return only happens when version is current AND all configs exist\n");
        exit(0);
    } else {
        fwrite(STDERR, "\n✗ Some tests failed\n");
        exit(1);
    }
}
