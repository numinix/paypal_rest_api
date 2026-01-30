<?php
declare(strict_types=1);

/**
 * Test to verify that paypalSavedCardRecurring has no site-specific customizations.
 *
 * This test ensures the plugin code is clean and doesn't contain:
 * - Hardcoded site-specific constants (CATEGORY_ID_PLANS, etc.)
 * - Direct instantiation of optional classes (storeCredit, etc.)
 * - Hardcoded URLs or email addresses
 *
 * Site-specific customizations should be implemented via observers.
 * See docs/OBSERVER_CUSTOMIZATIONS.md for examples.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    fwrite(STDOUT, "=== Clean Plugin Code Test ===\n");
    fwrite(STDOUT, "Testing that plugin code has no site-specific customizations...\n\n");

    $failures = 0;

    $sourceFile = DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';
    if (!file_exists($sourceFile)) {
        fwrite(STDERR, "✗ Source file not found: $sourceFile\n");
        exit(1);
    }
    
    $content = file_get_contents($sourceFile);
    
    // Test 1: No bare usage of site-specific constants
    fwrite(STDOUT, "Test 1: Checking for bare usage of site-specific constants...\n");
    
    // Look for CATEGORY_ID_PLANS without defined() check
    if (preg_match('/CATEGORY_ID_(PLANS|CUSTOM_PLANS)/', $content, $matches)) {
        // Make sure it's not in a comment
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/CATEGORY_ID_(PLANS|CUSTOM_PLANS)/', $line)) {
                $trimmed = trim($line);
                if (!str_starts_with($trimmed, '//') && !str_starts_with($trimmed, '*')) {
                    fwrite(STDERR, "✗ Found site-specific constant at line " . ($lineNum + 1) . ": " . trim($line) . "\n");
                    $failures++;
                    break;
                }
            }
        }
    }
    
    if ($failures === 0) {
        fwrite(STDOUT, "✓ No site-specific category constants found\n");
    }

    // Test 2: No bare instantiation of optional classes
    fwrite(STDOUT, "\nTest 2: Checking for bare instantiation of optional classes...\n");
    
    // Look for "new storeCredit()" without class_exists check
    if (preg_match('/new\s+storeCredit\s*\(/', $content)) {
        fwrite(STDERR, "✗ Found bare 'new storeCredit()' - should use class_exists() check or observer pattern\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ No bare instantiation of storeCredit class found\n");
    }

    // Test 3: No hardcoded site-specific URLs
    fwrite(STDOUT, "\nTest 3: Checking for hardcoded site-specific URLs...\n");
    
    if (preg_match('/https?:\/\/(www\.)?numinix\.com/i', $content)) {
        fwrite(STDERR, "✗ Found hardcoded numinix.com URL - should be generic\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ No hardcoded site-specific URLs found\n");
    }

    // Test 4: No hardcoded site-specific email addresses
    fwrite(STDOUT, "\nTest 4: Checking for hardcoded site-specific email addresses...\n");
    
    if (preg_match('/support@numinix\.com/i', $content)) {
        fwrite(STDERR, "✗ Found hardcoded site-specific email address\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ No hardcoded site-specific email addresses found\n");
    }

    // Test 5: Verify observer documentation exists
    fwrite(STDOUT, "\nTest 5: Verifying observer documentation exists...\n");
    
    $observerDoc = DIR_FS_CATALOG . 'docs/OBSERVER_CUSTOMIZATIONS.md';
    if (!file_exists($observerDoc)) {
        fwrite(STDERR, "✗ Observer documentation not found: $observerDoc\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Observer documentation exists\n");
        
        // Check that it contains key information
        $docContent = file_get_contents($observerDoc);
        if (strpos($docContent, 'NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS') === false) {
            fwrite(STDERR, "✗ Documentation missing notification point information\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Documentation contains notification point examples\n");
        }
    }
    
    // Test 6: Verify storecredit is initialized safely
    fwrite(STDOUT, "\nTest 6: Verifying storecredit session variable is initialized...\n");
    
    if (preg_match('/\$_SESSION\[.storecredit.\]\s*=\s*0/', $content)) {
        fwrite(STDOUT, "✓ Session storecredit is properly initialized\n");
    } else {
        fwrite(STDERR, "✗ Session storecredit initialization not found\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All clean plugin code tests passed!\n");
    fwrite(STDOUT, "Plugin code is free of site-specific customizations\n");
    fwrite(STDOUT, "See docs/OBSERVER_CUSTOMIZATIONS.md for implementing custom features\n");
    exit(0);
}
