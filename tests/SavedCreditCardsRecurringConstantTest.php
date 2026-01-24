<?php
declare(strict_types=1);

/**
 * Test to verify that TABLE_SAVED_CREDIT_CARDS_RECURRING constant is loaded from extra_datafiles
 *
 * This test addresses the issue:
 * "PHP Fatal error: Uncaught Error: Undefined constant TABLE_SAVED_CREDIT_CARDS_RECURRING"
 * which occurred in admin/paypalr_saved_card_recurring.php at line 193
 *
 * The fix adds the constant definition to includes/extra_datafiles/ppr_database_tables.php
 * which Zen Cart loads site-wide automatically.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }

    // Load the extra_datafiles that defines table constants (simulating Zen Cart's auto-load behavior)
    require_once DIR_FS_CATALOG . 'includes/extra_datafiles/ppr_database_tables.php';

    $failures = 0;

    // Test 1: Verify the extra_datafiles properly defines TABLE_SAVED_CREDIT_CARDS_RECURRING
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        fwrite(STDERR, "✗ CRITICAL: TABLE_SAVED_CREDIT_CARDS_RECURRING not defined by extra_datafiles\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_SAVED_CREDIT_CARDS_RECURRING is defined by extra_datafiles\n");
    }

    // Test 2: Verify the constant has the correct value
    if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') && TABLE_SAVED_CREDIT_CARDS_RECURRING !== DB_PREFIX . 'saved_credit_cards_recurring') {
        fwrite(STDERR, sprintf(
            "✗ Expected TABLE_SAVED_CREDIT_CARDS_RECURRING to be '%s', got '%s'\n",
            DB_PREFIX . 'saved_credit_cards_recurring',
            TABLE_SAVED_CREDIT_CARDS_RECURRING
        ));
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_SAVED_CREDIT_CARDS_RECURRING has correct value: " . TABLE_SAVED_CREDIT_CARDS_RECURRING . "\n");
    }

    // Test 3: Verify TABLE_SAVED_CREDIT_CARDS is also defined
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        fwrite(STDERR, "✗ CRITICAL: TABLE_SAVED_CREDIT_CARDS not defined by extra_datafiles\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_SAVED_CREDIT_CARDS is defined by extra_datafiles\n");
    }

    // Test 4: Verify TABLE_SAVED_CREDIT_CARDS has the correct value
    if (defined('TABLE_SAVED_CREDIT_CARDS') && TABLE_SAVED_CREDIT_CARDS !== DB_PREFIX . 'saved_credit_cards') {
        fwrite(STDERR, sprintf(
            "✗ Expected TABLE_SAVED_CREDIT_CARDS to be '%s', got '%s'\n",
            DB_PREFIX . 'saved_credit_cards',
            TABLE_SAVED_CREDIT_CARDS
        ));
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_SAVED_CREDIT_CARDS has correct value: " . TABLE_SAVED_CREDIT_CARDS . "\n");
    }

    // Test 5: Verify all saved credit card table constants are defined
    $required_constants = [
        'TABLE_SAVED_CREDIT_CARDS',
        'TABLE_SAVED_CREDIT_CARDS_RECURRING',
    ];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            fwrite(STDERR, "✗ CRITICAL: $const not defined by extra_datafiles\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ $const is defined\n");
        }
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All TABLE_SAVED_CREDIT_CARDS_RECURRING constant tests passed\n");
    exit(0);
}
