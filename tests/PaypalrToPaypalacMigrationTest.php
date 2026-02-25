<?php
declare(strict_types=1);

/**
 * Test to verify that the paypalac module's install() method includes migration logic
 * to copy configuration values from the old paypalr module.
 *
 * This ensures stores upgrading from paypalr to paypalac keep their existing settings
 * (credentials, order statuses, etc.) without having to reconfigure.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    fwrite(STDOUT, "=== PayPal REST → Advanced Checkout Migration Test ===\n");
    fwrite(STDOUT, "Testing that install() migrates old paypalr config values to paypalac keys...\n\n");

    $failures = 0;

    $sourceFile = DIR_FS_CATALOG . 'includes/modules/payment/paypalac.php';
    if (!file_exists($sourceFile)) {
        fwrite(STDERR, "✗ Source file not found: $sourceFile\n");
        exit(1);
    }

    $content = file_get_contents($sourceFile);

    // Test 1: install() method queries for old PAYPALR configuration keys
    fwrite(STDOUT, "Test 1: install() queries for old MODULE_PAYMENT_PAYPALR_% keys...\n");
    if (strpos($content, "MODULE\\_PAYMENT\\_PAYPALR\\_%") !== false || strpos($content, "MODULE_PAYMENT_PAYPALR_%") !== false) {
        fwrite(STDOUT, "✓ install() includes SELECT query for old PAYPALR keys\n");
    } else {
        fwrite(STDERR, "✗ FAILED: install() does not query for old PAYPALR keys\n");
        $failures++;
    }

    // Test 2: install() method performs UPDATE to copy old values to new keys
    fwrite(STDOUT, "\nTest 2: install() updates new PAYPALAC keys with old values...\n");
    if (preg_match("/UPDATE.*configuration.*SET.*configuration_value.*WHERE.*configuration_key/s", $content)) {
        fwrite(STDOUT, "✓ install() includes UPDATE query to copy old values to new keys\n");
    } else {
        fwrite(STDERR, "✗ FAILED: install() does not update new keys with old values\n");
        $failures++;
    }

    // Test 3: install() uses str_replace to convert PAYPALR → PAYPALAC key names
    fwrite(STDOUT, "\nTest 3: install() maps old key names to new key names...\n");
    if (strpos($content, "str_replace('MODULE_PAYMENT_PAYPALR_', 'MODULE_PAYMENT_PAYPALAC_'") !== false) {
        fwrite(STDOUT, "✓ install() uses str_replace to map PAYPALR → PAYPALAC key names\n");
    } else {
        fwrite(STDERR, "✗ FAILED: install() does not map old key names to new key names\n");
        $failures++;
    }

    // Test 4: Migration code comes AFTER the INSERT of new default rows
    fwrite(STDOUT, "\nTest 4: Migration runs after inserting default configuration rows...\n");
    $insertPos = strpos($content, "'MODULE_PAYMENT_PAYPALAC_VERSION'");
    $migratePos = strpos($content, "str_replace('MODULE_PAYMENT_PAYPALR_', 'MODULE_PAYMENT_PAYPALAC_'");
    if ($insertPos !== false && $migratePos !== false && $migratePos > $insertPos) {
        fwrite(STDOUT, "✓ Migration logic is positioned after default row insertion\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Migration logic is not positioned correctly relative to INSERT\n");
        $failures++;
    }

    // Test 5: The old PAYPALR keys are NOT deleted (user must uninstall old module manually)
    fwrite(STDOUT, "\nTest 5: Old PAYPALR keys are not deleted by install()...\n");
    // Extract just the install() method body
    $installMethodStart = strpos($content, 'public function install()');
    $installMethodEnd = false;
    if ($installMethodStart !== false) {
        // Find the end of the install method by counting braces
        $braceCount = 0;
        $startedCounting = false;
        for ($i = $installMethodStart; $i < strlen($content); $i++) {
            if ($content[$i] === '{') {
                $braceCount++;
                $startedCounting = true;
            } elseif ($content[$i] === '}') {
                $braceCount--;
                if ($startedCounting && $braceCount === 0) {
                    $installMethodEnd = $i;
                    break;
                }
            }
        }
    }

    if ($installMethodStart !== false && $installMethodEnd !== false) {
        $installBody = substr($content, $installMethodStart, $installMethodEnd - $installMethodStart);
        if (strpos($installBody, "DELETE FROM") === false || strpos($installBody, "MODULE_PAYMENT_PAYPALR") === false) {
            fwrite(STDOUT, "✓ install() does not DELETE old PAYPALR configuration keys\n");
        } else {
            fwrite(STDERR, "✗ FAILED: install() appears to delete old PAYPALR keys (should not)\n");
            $failures++;
        }
    } else {
        fwrite(STDERR, "✗ FAILED: Could not locate install() method boundaries\n");
        $failures++;
    }

    // Test 6: All new PAYPALAC configuration keys are inserted
    fwrite(STDOUT, "\nTest 6: All expected PAYPALAC configuration keys are present in install()...\n");
    $expectedKeys = [
        'MODULE_PAYMENT_PAYPALAC_VERSION',
        'MODULE_PAYMENT_PAYPALAC_STATUS',
        'MODULE_PAYMENT_PAYPALAC_SERVER',
        'MODULE_PAYMENT_PAYPALAC_CLIENTID_L',
        'MODULE_PAYMENT_PAYPALAC_SECRET_L',
        'MODULE_PAYMENT_PAYPALAC_CLIENTID_S',
        'MODULE_PAYMENT_PAYPALAC_SECRET_S',
        'MODULE_PAYMENT_PAYPALAC_SORT_ORDER',
        'MODULE_PAYMENT_PAYPALAC_ZONE',
        'MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID',
        'MODULE_PAYMENT_PAYPALAC_DEBUGGING',
    ];
    $missingKeys = [];
    foreach ($expectedKeys as $key) {
        if (strpos($content, "'$key'") === false) {
            $missingKeys[] = $key;
        }
    }
    if (empty($missingKeys)) {
        fwrite(STDOUT, "✓ All expected PAYPALAC configuration keys found in install()\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Missing configuration keys: " . implode(', ', $missingKeys) . "\n");
        $failures++;
    }

    // Test 7: Order history backward compatibility - GetPayPalOrderTransactions recognizes both module names
    fwrite(STDOUT, "\nTest 7: Admin query recognizes both paypalr and paypalac module names...\n");
    $txnFile = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Admin/GetPayPalOrderTransactions.php';
    if (file_exists($txnFile)) {
        $txnContent = file_get_contents($txnFile);
        if (strpos($txnContent, "'paypalr'") !== false && strpos($txnContent, "IN (") !== false) {
            fwrite(STDOUT, "✓ GetPayPalOrderTransactions query recognizes both paypalr and paypalac\n");
        } else {
            fwrite(STDERR, "✗ FAILED: GetPayPalOrderTransactions does not recognize legacy paypalr module name\n");
            $failures++;
        }
    } else {
        fwrite(STDERR, "✗ FAILED: GetPayPalOrderTransactions.php not found\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, "❌ $failures test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "✅ All migration tests passed!\n");
    fwrite(STDOUT, "The paypalac module correctly migrates settings from the old paypalr module.\n");
}
