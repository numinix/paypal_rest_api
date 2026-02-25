<?php
declare(strict_types=1);

/**
 * Test to verify that all paypalac sub-modules' install() methods include migration logic
 * to copy configuration values from the old paypalr sub-modules.
 *
 * This ensures stores upgrading from paypalr_* to paypalac_* keep their existing settings
 * (sort order, zones, statuses, etc.) without having to reconfigure.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    fwrite(STDOUT, "=== Sub-Module PayPalR → PayPalAC Migration Test ===\n");
    fwrite(STDOUT, "Testing that all sub-module install() methods migrate old paypalr config values...\n\n");

    $failures = 0;

    $subModules = [
        'applepay' => 'APPLEPAY',
        'creditcard' => 'CREDITCARD',
        'googlepay' => 'GOOGLEPAY',
        'paylater' => 'PAYLATER',
        'savedcard' => 'SAVEDCARD',
        'venmo' => 'VENMO',
    ];

    foreach ($subModules as $moduleName => $constantPrefix) {
        fwrite(STDOUT, "--- Testing paypalac_$moduleName ---\n");

        $sourceFile = DIR_FS_CATALOG . "includes/modules/payment/paypalac_$moduleName.php";
        if (!file_exists($sourceFile)) {
            fwrite(STDERR, "✗ FAILED: Source file not found: $sourceFile\n");
            $failures++;
            continue;
        }

        $content = file_get_contents($sourceFile);

        // Test 1: install() method queries for old PAYPALR sub-module configuration keys
        $likePattern = "MODULE\\_PAYMENT\\_PAYPALR\\_{$constantPrefix}\\_%";
        if (strpos($content, $likePattern) !== false) {
            fwrite(STDOUT, "✓ install() includes SELECT query for old PAYPALR_{$constantPrefix} keys\n");
        } else {
            fwrite(STDERR, "✗ FAILED: install() does not query for old PAYPALR_{$constantPrefix} keys\n");
            $failures++;
        }

        // Test 2: install() uses str_replace to convert PAYPALR → PAYPALAC key names
        $strReplacePattern = "str_replace('MODULE_PAYMENT_PAYPALR_{$constantPrefix}_', 'MODULE_PAYMENT_PAYPALAC_{$constantPrefix}_'";
        if (strpos($content, $strReplacePattern) !== false) {
            fwrite(STDOUT, "✓ install() uses str_replace to map PAYPALR_{$constantPrefix} → PAYPALAC_{$constantPrefix} key names\n");
        } else {
            fwrite(STDERR, "✗ FAILED: install() does not map old key names to new key names for {$constantPrefix}\n");
            $failures++;
        }

        // Test 3: install() performs UPDATE to copy old values to new keys
        if (preg_match("/UPDATE.*configuration.*SET.*configuration_value.*WHERE.*configuration_key/s", $content)) {
            fwrite(STDOUT, "✓ install() includes UPDATE query to copy old values to new keys\n");
        } else {
            fwrite(STDERR, "✗ FAILED: install() does not update new keys with old values for {$constantPrefix}\n");
            $failures++;
        }

        // Test 4: Migration code comes AFTER the INSERT of new default rows
        $insertPos = strpos($content, "'MODULE_PAYMENT_PAYPALAC_{$constantPrefix}_VERSION'");
        $migratePos = strpos($content, $strReplacePattern);
        if ($insertPos !== false && $migratePos !== false && $migratePos > $insertPos) {
            fwrite(STDOUT, "✓ Migration logic is positioned after default row insertion\n");
        } else {
            fwrite(STDERR, "✗ FAILED: Migration logic is not positioned correctly relative to INSERT for {$constantPrefix}\n");
            $failures++;
        }

        fwrite(STDOUT, "\n");
    }

    // Summary
    fwrite(STDOUT, "=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, "❌ $failures test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "✅ All sub-module migration tests passed!\n");
    fwrite(STDOUT, "All paypalac sub-modules correctly migrate settings from old paypalr sub-modules.\n");
}
