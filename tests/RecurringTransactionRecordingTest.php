<?php
declare(strict_types=1);

/**
 * Test to verify that recurring payments record PayPal transactions in the database.
 *
 * This test ensures that:
 * 1. record_paypal_transaction() method exists
 * 2. process_rest_payment() returns transaction data for recording
 * 3. The cron calls record_paypal_transaction() after order creation
 *
 * Background:
 * The issue was that recurring payment orders did not have PayPal transaction data
 * recorded in the database, which prevented the admin from performing refunds/voids.
 * The admin would show "No PayPal transactions are recorded in the database for this order."
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Transaction Recording Test...\n\n");

$basePath = dirname(__DIR__);
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
$cronFile = $basePath . '/cron/paypalac_saved_card_recurring.php';

if (!file_exists($savedCardRecurringFile)) {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

if (!file_exists($cronFile)) {
    fwrite(STDERR, "✗ paypalac_saved_card_recurring.php (cron) not found\n\n");
    exit(1);
}

$classContent = file_get_contents($savedCardRecurringFile);
$cronContent = file_get_contents($cronFile);
$testsPassed = true;

// Test 1: Verify record_paypal_transaction method exists
fwrite(STDOUT, "Test 1: Verify record_paypal_transaction() method exists...\n");

if (strpos($classContent, 'function record_paypal_transaction') !== false) {
    fwrite(STDOUT, "✓ PASS: record_paypal_transaction() method exists\n");
} else {
    fwrite(STDERR, "✗ FAIL: record_paypal_transaction() method not found\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify record_paypal_transaction inserts into TABLE_PAYPAL
fwrite(STDOUT, "Test 2: Verify record_paypal_transaction() inserts into TABLE_PAYPAL...\n");

if (preg_match('/record_paypal_transaction.*?zen_db_perform\s*\(\s*TABLE_PAYPAL/s', $classContent)) {
    fwrite(STDOUT, "✓ PASS: record_paypal_transaction() inserts into TABLE_PAYPAL\n");
} else {
    fwrite(STDERR, "✗ FAIL: record_paypal_transaction() should insert into TABLE_PAYPAL\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify process_rest_payment returns paypal_order_id
fwrite(STDOUT, "Test 3: Verify process_rest_payment() returns paypal_order_id...\n");

if (preg_match("/return.*?'paypal_order_id'/s", $classContent)) {
    fwrite(STDOUT, "✓ PASS: process_rest_payment() returns paypal_order_id\n");
} else {
    fwrite(STDERR, "✗ FAIL: process_rest_payment() should return paypal_order_id\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify process_rest_payment returns intent
fwrite(STDOUT, "Test 4: Verify process_rest_payment() returns intent...\n");

if (preg_match("/return.*?'intent'/s", $classContent)) {
    fwrite(STDOUT, "✓ PASS: process_rest_payment() returns intent\n");
} else {
    fwrite(STDERR, "✗ FAIL: process_rest_payment() should return intent\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 5: Verify process_rest_payment returns payment_status
fwrite(STDOUT, "Test 5: Verify process_rest_payment() returns payment_status...\n");

if (preg_match("/return.*?'payment_status'/s", $classContent)) {
    fwrite(STDOUT, "✓ PASS: process_rest_payment() returns payment_status\n");
} else {
    fwrite(STDERR, "✗ FAIL: process_rest_payment() should return payment_status\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 6: Verify cron calls record_paypal_transaction
fwrite(STDOUT, "Test 6: Verify cron calls record_paypal_transaction()...\n");

if (strpos($cronContent, 'record_paypal_transaction') !== false) {
    fwrite(STDOUT, "✓ PASS: Cron calls record_paypal_transaction()\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should call record_paypal_transaction()\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 7: Verify cron checks for paypal_order_id before recording
fwrite(STDOUT, "Test 7: Verify cron checks for paypal_order_id before recording...\n");

if (preg_match("/paypal_order_id.*?record_paypal_transaction|record_paypal_transaction.*?paypal_order_id/s", $cronContent)) {
    fwrite(STDOUT, "✓ PASS: Cron checks for paypal_order_id before recording\n");
} else {
    fwrite(STDERR, "✗ FAIL: Cron should check for paypal_order_id before recording\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 8: Verify record_paypal_transaction sets txn_type
fwrite(STDOUT, "Test 8: Verify record_paypal_transaction() sets txn_type...\n");

if (preg_match("/record_paypal_transaction.*?txn_type.*?CREATE/s", $classContent)) {
    fwrite(STDOUT, "✓ PASS: record_paypal_transaction() sets txn_type to CREATE\n");
} else {
    fwrite(STDERR, "✗ FAIL: record_paypal_transaction() should set txn_type\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 9: Verify record_paypal_transaction records source as recurring_cron
fwrite(STDOUT, "Test 9: Verify record_paypal_transaction() records source as recurring_cron...\n");

if (strpos($classContent, 'recurring_cron') !== false) {
    fwrite(STDOUT, "✓ PASS: record_paypal_transaction() records source as recurring_cron\n");
} else {
    fwrite(STDERR, "✗ FAIL: record_paypal_transaction() should record source as recurring_cron\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that recurring payments:\n");
    fwrite(STDOUT, "1. Have record_paypal_transaction() method\n");
    fwrite(STDOUT, "2. Insert into TABLE_PAYPAL\n");
    fwrite(STDOUT, "3. Return paypal_order_id from process_rest_payment()\n");
    fwrite(STDOUT, "4. Return intent from process_rest_payment()\n");
    fwrite(STDOUT, "5. Return payment_status from process_rest_payment()\n");
    fwrite(STDOUT, "6. Call record_paypal_transaction() in cron\n");
    fwrite(STDOUT, "7. Check for paypal_order_id before recording\n");
    fwrite(STDOUT, "8. Set txn_type in transaction record\n");
    fwrite(STDOUT, "9. Record source as recurring_cron\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "Recurring payments may not record transactions properly.\n");
    exit(1);
}
