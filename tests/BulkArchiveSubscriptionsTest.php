<?php
declare(strict_types=1);

/**
 * Test to verify that admin/paypalr_subscriptions.php has bulk archive/unarchive functionality
 * with checkboxes and select all/unselect all capability.
 *
 * This test verifies:
 * 1. Bulk action handlers for archive and unarchive exist
 * 2. Checkbox column in table header for select all functionality
 * 3. Individual checkboxes in subscription rows
 * 4. JavaScript for select all/unselect all functionality
 * 5. Bulk action form with dropdown and apply button
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    fwrite(STDOUT, "=== Bulk Archive Subscriptions Test ===\n");
    fwrite(STDOUT, "Testing bulk archive/unarchive functionality in admin/paypalr_subscriptions.php...\n\n");

    // Read the admin file
    $adminFile = dirname(__DIR__) . '/admin/paypalr_subscriptions.php';
    if (!file_exists($adminFile)) {
        fwrite(STDERR, "✗ Admin file not found: $adminFile\n");
        exit(1);
    }

    $content = file_get_contents($adminFile);
    $failures = 0;

    // Test 1: Verify bulk_archive action handler exists
    fwrite(STDOUT, "Test 1: Checking bulk_archive action handler exists...\n");
    if (preg_match('/if\s*\(\s*\$action\s*===\s*[\'"]bulk_archive[\'"]\s*\)/', $content)) {
        fwrite(STDOUT, "✓ PASSED: bulk_archive action handler found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: bulk_archive action handler not found\n");
        $failures++;
    }

    // Test 2: Verify bulk_unarchive action handler exists
    fwrite(STDOUT, "\nTest 2: Checking bulk_unarchive action handler exists...\n");
    if (preg_match('/if\s*\(\s*\$action\s*===\s*[\'"]bulk_unarchive[\'"]\s*\)/', $content)) {
        fwrite(STDOUT, "✓ PASSED: bulk_unarchive action handler found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: bulk_unarchive action handler not found\n");
        $failures++;
    }

    // Test 3: Verify subscription_ids array is checked in bulk handlers
    fwrite(STDOUT, "\nTest 3: Checking bulk handlers validate subscription_ids...\n");
    if (preg_match('/\$subscriptionIds\s*=\s*\$_POST\[[\'"]subscription_ids[\'"]\]/', $content)) {
        fwrite(STDOUT, "✓ PASSED: subscription_ids POST parameter is accessed\n");
    } else {
        fwrite(STDERR, "✗ FAILED: subscription_ids POST parameter check not found\n");
        $failures++;
    }

    // Test 4: Verify select all checkbox in table header
    fwrite(STDOUT, "\nTest 4: Checking select all checkbox in table header...\n");
    if (preg_match('/<input[^>]+type=["\']checkbox["\'][^>]+id=["\']select-all-subscriptions["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Select all checkbox found in table header\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Select all checkbox not found in table header\n");
        $failures++;
    }

    // Test 5: Verify individual checkboxes in subscription rows
    fwrite(STDOUT, "\nTest 5: Checking individual subscription checkboxes...\n");
    if (preg_match('/<input[^>]+type="checkbox"[^>]+name="subscription_ids\[\]"/', $content) &&
        preg_match('/class="subscription-checkbox"/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Individual subscription checkboxes found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Individual subscription checkboxes not found\n");
        $failures++;
    }

    // Test 6: Verify bulk action form exists
    fwrite(STDOUT, "\nTest 6: Checking bulk action form exists...\n");
    if (preg_match('/id=["\']bulk-actions-form["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Bulk actions form found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Bulk actions form not found\n");
        $failures++;
    }

    // Test 7: Verify bulk action dropdown with archive/unarchive options
    fwrite(STDOUT, "\nTest 7: Checking bulk action dropdown with options...\n");
    if (preg_match('/<select[^>]+id=["\']bulk-action-select["\']/', $content) &&
        preg_match('/<option[^>]+value=["\']bulk_archive["\']/', $content) &&
        preg_match('/<option[^>]+value=["\']bulk_unarchive["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Bulk action dropdown with archive/unarchive options found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Bulk action dropdown with options not found\n");
        $failures++;
    }

    // Test 8: Verify apply bulk action button
    fwrite(STDOUT, "\nTest 8: Checking apply bulk action button...\n");
    if (preg_match('/<button[^>]+id=["\']apply-bulk-action["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Apply bulk action button found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Apply bulk action button not found\n");
        $failures++;
    }

    // Test 9: Verify JavaScript for select all functionality
    fwrite(STDOUT, "\nTest 9: Checking JavaScript select all functionality...\n");
    if (preg_match('/getElementById\(["\']select-all-subscriptions["\']\)/', $content) &&
        preg_match('/\.subscription-checkbox/', $content)) {
        fwrite(STDOUT, "✓ PASSED: JavaScript select all functionality found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: JavaScript select all functionality not found\n");
        $failures++;
    }

    // Test 10: Verify JavaScript for bulk action submission
    fwrite(STDOUT, "\nTest 10: Checking JavaScript bulk action submission...\n");
    if (preg_match('/getElementById\(["\']apply-bulk-action["\']\)/', $content) &&
        preg_match('/addEventListener\(["\']click["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: JavaScript bulk action submission found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: JavaScript bulk action submission not found\n");
        $failures++;
    }

    // Test 11: Verify selected count display
    fwrite(STDOUT, "\nTest 11: Checking selected count display element...\n");
    if (preg_match('/id=["\']selected-count["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Selected count display element found\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Selected count display element not found\n");
        $failures++;
    }

    // Test 12: Verify table header has additional column (checkbox column)
    fwrite(STDOUT, "\nTest 12: Checking table header has checkbox column...\n");
    if (preg_match('/<th[^>]*>.*?<input[^>]+type=["\']checkbox["\'][^>]+id=["\']select-all-subscriptions["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Table header has checkbox column\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Table header checkbox column not found\n");
        $failures++;
    }

    // Test 13: Verify colspan in empty message updated for new column
    fwrite(STDOUT, "\nTest 13: Checking empty table message colspan updated...\n");
    if (preg_match('/<td\s+colspan=["\']9["\']/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Empty table message colspan is 9 (includes checkbox column)\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Empty table message colspan not updated to 9\n");
        $failures++;
    }

    // Test 14: Verify checkbox has stopPropagation to prevent row toggle
    fwrite(STDOUT, "\nTest 14: Checking checkbox cell prevents row toggle on click...\n");
    if (preg_match('/onclick=["\']event\.stopPropagation/', $content)) {
        fwrite(STDOUT, "✓ PASSED: Checkbox cell has stopPropagation to prevent row toggle\n");
    } else {
        fwrite(STDERR, "✗ FAILED: Checkbox cell stopPropagation not found\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All bulk archive subscriptions tests passed!\n");
    exit(0);
}
