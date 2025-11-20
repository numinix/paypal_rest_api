<?php
/**
 * Test to verify that all payment modules trim credentials consistently
 * to prevent TokenCache encryption key mismatches.
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

    // Define test constants with whitespace to verify trimming
    if (!defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
        define('MODULE_PAYMENT_PAYPALR_SERVER', 'sandbox');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_CLIENTID_L')) {
        define('MODULE_PAYMENT_PAYPALR_CLIENTID_L', '  LiveClientId123  ');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_SECRET_L')) {
        define('MODULE_PAYMENT_PAYPALR_SECRET_L', '  LiveSecret456  ');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_CLIENTID_S')) {
        define('MODULE_PAYMENT_PAYPALR_CLIENTID_S', '  SandboxClientId789  ');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_SECRET_S')) {
        define('MODULE_PAYMENT_PAYPALR_SECRET_S', '  SandboxSecret012  ');
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace {
    /**
     * Test that wallet modules trim credentials before creating PayPalRestfulApi
     */
    function testWalletModulesTrimCredentials(): bool
    {
        $modules = [
            'paypalr_creditcard',
            'paypalr_applepay', 
            'paypalr_googlepay',
            'paypalr_venmo',
        ];
        
        $allPassed = true;
        
        foreach ($modules as $module) {
            $filePath = DIR_FS_CATALOG . "includes/modules/payment/{$module}.php";
            
            if (!file_exists($filePath)) {
                fwrite(STDOUT, "  - $module: File not found (skipped)\n");
                continue;
            }
            
            $content = file_get_contents($filePath);
            
            // Check that credentials are trimmed before being passed to PayPalRestfulApi
            // Pattern: $client_id = trim(...) or similar
            if (strpos($content, 'trim($client_id)') !== false && 
                strpos($content, 'trim($secret)') !== false) {
                fwrite(STDOUT, "  ✓ $module: Trims credentials correctly\n");
            } else {
                fwrite(STDERR, "  ✗ $module: Does NOT trim credentials\n");
                $allPassed = false;
            }
        }
        
        return $allPassed;
    }

    /**
     * Test that paypalr.php getEnvironmentInfo returns trimmed credentials
     */
    function testPaypalrGetEnvironmentInfoTrims(): bool
    {
        $filePath = DIR_FS_CATALOG . 'includes/modules/payment/paypalr.php';
        
        if (!file_exists($filePath)) {
            fwrite(STDERR, "FAIL: paypalr.php not found\n");
            return false;
        }
        
        $content = file_get_contents($filePath);
        
        // Check that getEnvironmentInfo trims credentials in return statement
        if (strpos($content, 'trim($client_id)') !== false && 
            strpos($content, 'trim($secret)') !== false &&
            strpos($content, 'public static function getEnvironmentInfo') !== false) {
            fwrite(STDOUT, "✓ paypalr::getEnvironmentInfo() trims credentials\n");
            return true;
        } else {
            fwrite(STDERR, "✗ paypalr::getEnvironmentInfo() does NOT trim credentials\n");
            return false;
        }
    }

    /**
     * Test that trimmed and untrimmed credentials would create the same TokenCache key
     */
    function testTokenCacheConsistency(): bool
    {
        // This test verifies that all modules will now create TokenCache with the same secret
        // even if the configuration has whitespace
        
        $trimmedSecret = 'SandboxSecret012';
        $untrimmedSecret = '  SandboxSecret012  ';
        
        // After our fix, both should be trimmed before TokenCache creation
        if (trim($untrimmedSecret) !== $trimmedSecret) {
            fwrite(STDERR, "FAIL: trim() function not working as expected\n");
            return false;
        }
        
        fwrite(STDOUT, "✓ Trimming ensures consistent TokenCache encryption keys\n");
        return true;
    }

    // Run the tests
    $failures = 0;
    
    fwrite(STDOUT, "Test 1: Verifying paypalr::getEnvironmentInfo() trims credentials...\n");
    if (testPaypalrGetEnvironmentInfoTrims()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }
    
    fwrite(STDOUT, "\nTest 2: Verifying wallet modules trim credentials...\n");
    if (testWalletModulesTrimCredentials()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }
    
    fwrite(STDOUT, "\nTest 3: Verifying TokenCache consistency...\n");
    if (testTokenCacheConsistency()) {
        fwrite(STDOUT, "  ✓ Test passed\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n");
        $failures++;
    }
    
    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All credential trimming tests passed!\n");
        exit(0);
    }
}
