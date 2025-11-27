<?php
/**
 * Test to verify that nxp_paypal_dispatch_event uses the consolidated debug log
 * instead of PHP's error_log which creates separate Zen Cart error log files.
 *
 * This test validates the fix for the issue where ISU modal polling creates
 * multiple Zen Cart error log files due to repeated error_log() calls.
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
     * Extract the body of a function from the file content
     */
    function extractFunctionBody(string $content, string $funcName): ?string
    {
        // Find the function signature
        $pattern = '/function\s+' . preg_quote($funcName, '/') . '\s*\([^)]*\)\s*:\s*void\s*\{/';
        if (!preg_match($pattern, $content, $matches)) {
            return null;
        }
        
        // Find the position of the match
        $startPos = strpos($content, $matches[0]) + strlen($matches[0]);
        
        // Find matching closing brace
        $braceCount = 1;
        $pos = $startPos;
        $length = strlen($content);
        
        while ($pos < $length && $braceCount > 0) {
            if ($content[$pos] === '{') {
                $braceCount++;
            } elseif ($content[$pos] === '}') {
                $braceCount--;
            }
            $pos++;
        }
        
        return substr($content, $startPos, $pos - $startPos - 1);
    }

    /**
     * Test that nxp_paypal_dispatch_event uses nxp_paypal_log_debug instead of error_log
     */
    function testDispatchEventUsesLogDebug(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Find the nxp_paypal_dispatch_event function body
        $functionBody = extractFunctionBody($content, 'nxp_paypal_dispatch_event');
        
        if ($functionBody === null) {
            fwrite(STDERR, "FAIL: Could not find nxp_paypal_dispatch_event function\n");
            return false;
        }

        // Check 1: Verify error_log is NOT used in the function
        if (strpos($functionBody, 'error_log(') !== false) {
            fwrite(STDERR, "FAIL: nxp_paypal_dispatch_event should NOT use error_log()\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ nxp_paypal_dispatch_event does NOT use error_log()\n");
        }

        // Check 2: Verify nxp_paypal_log_debug IS used in the function
        if (strpos($functionBody, 'nxp_paypal_log_debug(') !== false) {
            fwrite(STDOUT, "✓ nxp_paypal_dispatch_event uses nxp_paypal_log_debug()\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_dispatch_event should use nxp_paypal_log_debug()\n");
            $passed = false;
        }

        // Check 3: Verify the log message includes "ISU event"
        if (strpos($functionBody, 'ISU event') !== false) {
            fwrite(STDOUT, "✓ Log message includes ISU event identifier\n");
        } else {
            fwrite(STDERR, "FAIL: Log message should include ISU event identifier\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that nxp_paypal_log_debug writes to consolidated log file
     */
    function testLogDebugWritesToConsolidatedFile(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_resolve_debug_log_file returns the consolidated log path
        if (strpos($content, 'numinix_paypal_api_debug.log') !== false) {
            fwrite(STDOUT, "✓ Debug log file is named numinix_paypal_api_debug.log\n");
        } else {
            fwrite(STDERR, "FAIL: Debug log should be named numinix_paypal_api_debug.log\n");
            $passed = false;
        }

        // Check 2: Verify nxp_paypal_log_debug uses file_put_contents with FILE_APPEND
        if (preg_match('/file_put_contents\s*\([^,]+,\s*[^,]+,\s*FILE_APPEND/', $content)) {
            fwrite(STDOUT, "✓ nxp_paypal_log_debug appends to single log file\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_log_debug should append to log file\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that error_log is no longer used for event logging
     */
    function testNoErrorLogForEvents(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check that there's no "ISU event" string being logged with error_log
        if (preg_match('/error_log\s*\([^)]*ISU\s+event[^)]*\)/', $content)) {
            fwrite(STDERR, "FAIL: Found error_log() call with 'ISU event' - this creates separate Zen Cart logs\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ No error_log() calls for ISU events\n");
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying nxp_paypal_dispatch_event uses nxp_paypal_log_debug...\n");
    if (testDispatchEventUsesLogDebug()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying nxp_paypal_log_debug writes to consolidated log file...\n");
    if (testLogDebugWritesToConsolidatedFile()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying no error_log() calls for ISU events...\n");
    if (testNoErrorLogForEvents()) {
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
        fwrite(STDOUT, "\n✓ All dispatch event logging tests passed!\n");
        fwrite(STDOUT, "\nFix applied:\n");
        fwrite(STDOUT, "- nxp_paypal_dispatch_event() now uses nxp_paypal_log_debug() instead of error_log()\n");
        fwrite(STDOUT, "- ISU events are logged to the consolidated numinix_paypal_api_debug.log file\n");
        fwrite(STDOUT, "- This prevents creation of separate Zen Cart error log files during ISU polling\n");
        exit(0);
    }
}
