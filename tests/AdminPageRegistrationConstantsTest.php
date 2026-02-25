<?php
declare(strict_types=1);

/**
 * Test to verify that admin page registration constants are loaded admin-wide
 * 
 * This test addresses the issue:
 * "The admin page registration in version 1.3.5 didn't work because the defines 
 * for the language_key values are only loaded on the page itself instead of using 
 * an admin-wide loading file like extra_datafiles or extra_definitions."
 * 
 * The fix splits constants between two admin-wide loading files:
 * - admin/includes/extra_datafiles/paypalac_filenames.php (FILENAME_* constants)
 * - admin/includes/languages/english/extra_definitions/paypalac_admin_names.php (BOX_* constants)
 * 
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', true);
    }

    $failures = 0;

    // Test 1: Verify FILENAME_* constants are defined in extra_datafiles
    require_once DIR_FS_CATALOG . 'admin/includes/extra_datafiles/paypalac_filenames.php';
    
    $filename_constants = [
        'FILENAME_PAYPALAC_SUBSCRIPTIONS' => 'paypalac_subscriptions',
        'FILENAME_PAYPALAC_SAVED_CARD_RECURRING' => 'paypalac_saved_card_recurring',
        'FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT' => 'paypalac_subscriptions_report',
    ];
    
    foreach ($filename_constants as $const => $expectedValue) {
        if (!defined($const)) {
            fwrite(STDERR, "CRITICAL: $const not defined by extra_datafiles\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ $const is defined in extra_datafiles\n");
            
            if (constant($const) !== $expectedValue) {
                fwrite(STDERR, sprintf(
                    "Expected $const to be '%s', got '%s'\n",
                    $expectedValue,
                    constant($const)
                ));
                $failures++;
            } else {
                fwrite(STDOUT, "✓ $const has correct value: " . constant($const) . "\n");
            }
        }
    }

    // Test 2: Verify BOX_* constants are defined in extra_definitions
    require_once DIR_FS_CATALOG . 'admin/includes/languages/english/extra_definitions/paypalac_admin_names.php';
    
    $box_constants = [
        'BOX_PAYPALAC_SUBSCRIPTIONS' => 'Vaulted Subscriptions',
        'BOX_PAYPALAC_SAVED_CARD_RECURRING' => 'Saved Card Subscriptions',
        'BOX_PAYPALAC_SUBSCRIPTIONS_REPORT' => 'Active Subscriptions Report',
    ];
    
    foreach ($box_constants as $const => $expectedValue) {
        if (!defined($const)) {
            fwrite(STDERR, "CRITICAL: $const not defined by extra_definitions\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ $const is defined in extra_definitions\n");
            
            if (constant($const) !== $expectedValue) {
                fwrite(STDERR, sprintf(
                    "Expected $const to be '%s', got '%s'\n",
                    $expectedValue,
                    constant($const)
                ));
                $failures++;
            } else {
                fwrite(STDOUT, "✓ $const has correct value: " . constant($const) . "\n");
            }
        }
    }

    // Test 3: Verify all constants required by the installer are available
    $installer_required_constants = [
        'BOX_PAYPALAC_SUBSCRIPTIONS',
        'FILENAME_PAYPALAC_SUBSCRIPTIONS',
        'BOX_PAYPALAC_SAVED_CARD_RECURRING',
        'FILENAME_PAYPALAC_SAVED_CARD_RECURRING',
        'BOX_PAYPALAC_SUBSCRIPTIONS_REPORT',
        'FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT',
    ];
    
    fwrite(STDOUT, "\nVerifying all installer-required constants are available...\n");
    foreach ($installer_required_constants as $const) {
        if (!defined($const)) {
            fwrite(STDERR, "CRITICAL: Installer requires $const but it's not defined\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Installer constant $const is available\n");
        }
    }

    // Test 4: Verify the old page-specific language file is removed
    $old_language_file = DIR_FS_CATALOG . 'admin/includes/languages/english/paypalac_subscriptions.php';
    if (file_exists($old_language_file)) {
        fwrite(STDERR, "WARNING: Old page-specific language file still exists: $old_language_file\n");
        fwrite(STDERR, "This file should be removed in favor of admin-wide loading via extra_definitions\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Old page-specific language file has been removed\n");
    }

    // Test 5: Verify correct file separation (FILENAME_* in datafiles, BOX_* in definitions)
    fwrite(STDOUT, "\nVerifying correct constant separation...\n");
    
    // Check that extra_datafiles file only has FILENAME_* constants (not BOX_*)
    $datafiles_content = file_get_contents(DIR_FS_CATALOG . 'admin/includes/extra_datafiles/paypalac_filenames.php');
    if (preg_match('/define\s*\(\s*[\'"]BOX_[^\'\"]*[\'\"]\s*,/', $datafiles_content)) {
        fwrite(STDERR, "ERROR: BOX_* constant definitions found in extra_datafiles (should be in extra_definitions)\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ extra_datafiles contains no BOX_* constant definitions (correct)\n");
    }
    
    // Check that extra_definitions file only has BOX_* constants (not FILENAME_*)
    $definitions_content = file_get_contents(DIR_FS_CATALOG . 'admin/includes/languages/english/extra_definitions/paypalac_admin_names.php');
    if (preg_match('/define\s*\(\s*[\'"]FILENAME_[^\'\"]*[\'\"]\s*,/', $definitions_content)) {
        fwrite(STDERR, "ERROR: FILENAME_* constant definitions found in extra_definitions (should be in extra_datafiles)\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ extra_definitions contains no FILENAME_* constant definitions (correct)\n");
    }

    // Final summary
    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n❌ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All admin page registration constant tests passed\n");
    fwrite(STDOUT, "The installer will now have access to all required constants admin-wide.\n");
    exit(0);
}
