<?php
declare(strict_types=1);

/**
 * Test to verify that admin/paypalr_subscriptions.php uses the correct column name 'date_added'
 * instead of 'date' in the saved_credit_cards_recurring table SELECT query.
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'sccr.date' in 'field list'"
 * which occurred when accessing admin/paypalr_subscriptions.php
 *
 * The fix changes the SQL SELECT to use 'sccr.date_added AS date' which matches the actual
 * column name in the saved_credit_cards_recurring table schema.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    fwrite(STDOUT, "=== Admin Subscriptions Date Column Test ===\n");
    fwrite(STDOUT, "Testing that admin/paypalr_subscriptions.php uses correct column name...\n\n");

    // Read the admin file
    $adminFile = dirname(__DIR__) . '/admin/paypalr_subscriptions.php';
    if (!file_exists($adminFile)) {
        fwrite(STDERR, "✗ Admin file not found: $adminFile\n");
        exit(1);
    }

    $content = file_get_contents($adminFile);
    $failures = 0;

    // Test 1: Verify the file doesn't use 'sccr.date,' (without AS)
    fwrite(STDOUT, "Test 1: Checking that 'sccr.date,' (without alias) is not used...\n");
    if (preg_match('/sccr\.date,/', $content)) {
        fwrite(STDERR, "✗ FAILED: Found 'sccr.date,' which references non-existent column\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ PASSED: 'sccr.date,' not found\n");
    }

    // Test 2: Verify the file uses 'sccr.date_added AS date'
    fwrite(STDOUT, "\nTest 2: Checking that 'sccr.date_added AS date' is used...\n");
    if (preg_match('/sccr\.date_added\s+AS\s+date/i', $content)) {
        fwrite(STDOUT, "✓ PASSED: Found 'sccr.date_added AS date' - correct column name with alias\n");
    } else {
        fwrite(STDERR, "✗ FAILED: 'sccr.date_added AS date' not found\n");
        $failures++;
    }

    // Test 3: Verify the SQL query structure is correct
    fwrite(STDOUT, "\nTest 3: Checking SQL query includes all expected columns...\n");
    $expectedPatterns = [
        '/sccr\.saved_credit_card_recurring_id\s+AS\s+paypal_subscription_id/i',
        '/sccr\.products_id/',
        '/sccr\.products_name/',
        '/sccr\.amount/',
        '/sccr\.currency_code/',
        '/sccr\.billing_period/',
        '/sccr\.billing_frequency/',
        '/sccr\.total_billing_cycles/',
        '/sccr\.status/',
        '/sccr\.date_added\s+AS\s+date/i',
        '/sccr\.next_payment_date/',
        '/sccr\.comments/',
        '/sccr\.domain/',
    ];

    $allFound = true;
    foreach ($expectedPatterns as $pattern) {
        if (!preg_match($pattern, $content)) {
            $allFound = false;
            fwrite(STDERR, "✗ Missing expected pattern: $pattern\n");
            $failures++;
        }
    }

    if ($allFound) {
        fwrite(STDOUT, "✓ PASSED: All expected columns found in SQL query\n");
    }

    // Test 4: Verify comment is accurate
    fwrite(STDOUT, "\nTest 4: Checking that comment correctly describes the aliased field...\n");
    if (preg_match('/date.*field.*is aliased from.*date_added/i', $content)) {
        fwrite(STDOUT, "✓ PASSED: Comment correctly describes 'date' field as aliased from 'date_added'\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Comment doesn't correctly describe the aliased field\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All admin subscriptions date column tests passed!\n");
    exit(0);
}
