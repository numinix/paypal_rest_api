<?php
declare(strict_types=1);

/**
 * Test to verify that prepare_order() method handles undefined CATEGORY_ID constants gracefully.
 *
 * This test addresses the issue:
 * "PHP Fatal error: Undefined constant 'CATEGORY_ID_PLANS'"
 * which occurred when running the cron job paypal_saved_card_recurring.php
 *
 * The fix checks if constants are defined before using them.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

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
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('DIR_WS_CLASSES')) {
        define('DIR_WS_CLASSES', 'includes/classes/');
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
    }
    if (!defined('TABLE_CUSTOMERS')) {
        define('TABLE_CUSTOMERS', DB_PREFIX . 'customers');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS')) {
        define('TABLE_ORDERS_PRODUCTS', DB_PREFIX . 'orders_products');
    }
    if (!defined('TABLE_PRODUCTS')) {
        define('TABLE_PRODUCTS', DB_PREFIX . 'products');
    }

    fwrite(STDOUT, "=== Undefined Constants Test ===\n");
    fwrite(STDOUT, "Testing that code handles undefined CATEGORY_ID constants...\n\n");

    $failures = 0;

    // Test 1: Verify code doesn't reference undefined constants directly
    fwrite(STDOUT, "Test 1: Checking code uses defined() checks for CATEGORY_ID constants...\n");
    
    $sourceFile = DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';
    if (!file_exists($sourceFile)) {
        fwrite(STDERR, "✗ Source file not found: $sourceFile\n");
        exit(1);
    }
    
    $content = file_get_contents($sourceFile);
    
    // Check that CATEGORY_ID_PLANS is protected by defined()
    if (preg_match('/defined\s*\(\s*[\'"]CATEGORY_ID_PLANS[\'"]\s*\)/', $content)) {
        fwrite(STDOUT, "✓ CATEGORY_ID_PLANS is protected by defined() check\n");
    } else {
        fwrite(STDERR, "✗ CATEGORY_ID_PLANS is not protected by defined() check\n");
        $failures++;
    }
    
    // Check that CATEGORY_ID_CUSTOM_PLANS is protected by defined()
    if (preg_match('/defined\s*\(\s*[\'"]CATEGORY_ID_CUSTOM_PLANS[\'"]\s*\)/', $content)) {
        fwrite(STDOUT, "✓ CATEGORY_ID_CUSTOM_PLANS is protected by defined() check\n");
    } else {
        fwrite(STDERR, "✗ CATEGORY_ID_CUSTOM_PLANS is not protected by defined() check\n");
        $failures++;
    }

    // Test 2: Verify no bare usage of the constants
    fwrite(STDOUT, "\nTest 2: Checking for bare usage of CATEGORY_ID constants...\n");
    
    // Look for patterns like "zen_product_in_category($products_id, CATEGORY_ID_PLANS)"
    // that are NOT preceded by defined() check
    $lines = explode("\n", $content);
    $foundBareUsage = false;
    $lineNum = 0;
    
    foreach ($lines as $line) {
        $lineNum++;
        // Skip lines that have defined() check
        if (strpos($line, 'defined(') !== false) {
            continue;
        }
        
        // Check for bare usage
        if (preg_match('/zen_product_in_category\s*\([^,]+,\s*CATEGORY_ID_(PLANS|CUSTOM_PLANS)\s*\)/', $line)) {
            fwrite(STDERR, "✗ Found bare usage at line $lineNum: " . trim($line) . "\n");
            $foundBareUsage = true;
            $failures++;
        }
    }
    
    if (!$foundBareUsage) {
        fwrite(STDOUT, "✓ No bare usage of CATEGORY_ID constants found\n");
    }

    // Test 3: Verify the fix maintains backward compatibility
    fwrite(STDOUT, "\nTest 3: Verifying backward compatibility logic...\n");
    
    // Check that there's a $isPlansProduct variable being used
    if (preg_match('/\$isPlansProduct\s*=\s*false/', $content)) {
        fwrite(STDOUT, "✓ Uses $isPlansProduct flag for safe checking\n");
    } else {
        fwrite(STDERR, "✗ Doesn't use $isPlansProduct flag\n");
        $failures++;
    }
    
    // Check that the logic still handles store credit properly
    if (preg_match('/if\s*\(\s*!?\s*\$isPlansProduct\s*\)/', $content)) {
        fwrite(STDOUT, "✓ Conditional logic for store credit is present\n");
    } else {
        fwrite(STDERR, "✗ Conditional logic for store credit is missing\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All undefined constants tests passed!\n");
    fwrite(STDOUT, "Code safely handles undefined CATEGORY_ID constants\n");
    exit(0);
}
