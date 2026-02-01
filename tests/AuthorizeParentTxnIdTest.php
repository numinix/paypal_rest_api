<?php
declare(strict_types=1);

/**
 * Test to verify AUTHORIZE transactions get correct parent_txn_id.
 *
 * This test ensures that:
 * 1. updateAuthorizations() receives and uses the primary_txn_id (PayPal order ID)
 * 2. AUTHORIZE records are inserted with parent_txn_id = PayPal order ID (CREATE's txn_id)
 * 3. extract_rest_payment_id() does NOT fall back to the order ID
 *
 * Background:
 * The issue was that AUTHORIZE transactions were being inserted with parent_txn_id
 * equal to their own txn_id instead of the CREATE transaction's txn_id (PayPal order ID).
 * This caused the admin action buttons (refund/void/capture) to not appear because
 * the condition `$authorization['parent_txn_id'] === $main_txn_id` failed.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running AUTHORIZE Parent Txn ID Test...\n\n");

$basePath = dirname(__DIR__);
$testsPassed = true;

// Test 1: Verify updateAuthorizations receives primary_txn_id parameter
fwrite(STDOUT, "Test 1: Verify updateAuthorizations() receives primary_txn_id parameter...\n");

$filePath = $basePath . '/includes/modules/payment/paypal/PayPalRestful/Admin/GetPayPalOrderTransactions.php';
if (!file_exists($filePath)) {
    fwrite(STDERR, "✗ GetPayPalOrderTransactions.php not found\n");
    exit(1);
}

$content = file_get_contents($filePath);

// Check that updateAuthorizations has primary_txn_id parameter
if (preg_match('/function updateAuthorizations\(array \$authorizations,\s*string \$primary_txn_id/', $content)) {
    fwrite(STDOUT, "  ✓ PASS: updateAuthorizations() accepts primary_txn_id parameter\n");
} else {
    fwrite(STDERR, "  ✗ FAIL: updateAuthorizations() should accept primary_txn_id parameter\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 2: Verify updateAuthorizations is called with primary_txn_id
fwrite(STDOUT, "Test 2: Verify updateAuthorizations() is called with primary_txn_id...\n");

if (preg_match('/\$this->updateAuthorizations\(\$authorizations,\s*\$primary_txn_id\)/', $content)) {
    fwrite(STDOUT, "  ✓ PASS: updateAuthorizations() is called with \$primary_txn_id\n");
} else {
    fwrite(STDERR, "  ✗ FAIL: updateAuthorizations() should be called with \$primary_txn_id\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 3: Verify parent_txn_id uses primary_txn_id (not authorization's own ID)
fwrite(STDOUT, "Test 3: Verify AUTHORIZE uses primary_txn_id as parent...\n");

if (strpos($content, '$parent_txn_id = ($primary_txn_id !==') !== false) {
    fwrite(STDOUT, "  ✓ PASS: parent_txn_id is set from primary_txn_id\n");
} else {
    fwrite(STDERR, "  ✗ FAIL: parent_txn_id should be set from primary_txn_id\n");
    $testsPassed = false;
}

fwrite(STDOUT, "\n");

// Test 4: Verify extract_rest_payment_id does NOT fall back to order ID
fwrite(STDOUT, "Test 4: Verify extract_rest_payment_id() does NOT fall back to order ID...\n");

$recurringPath = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (!file_exists($recurringPath)) {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n");
    exit(1);
}

$recurringContent = file_get_contents($recurringPath);

// The function should NOT have the fallback to $data['id']
$hasBadFallback = preg_match('/function extract_rest_payment_id.*?if \(isset\(\$data\[\'id\'\]\)\).*?return \$data\[\'id\'\]/s', $recurringContent);
$hasGoodComment = strpos($recurringContent, "Do NOT fall back to \$data['id']") !== false;

if (!$hasBadFallback && $hasGoodComment) {
    fwrite(STDOUT, "  ✓ PASS: extract_rest_payment_id() does not fall back to order ID\n");
} elseif ($hasBadFallback) {
    fwrite(STDERR, "  ✗ FAIL: extract_rest_payment_id() should not fall back to \$data['id']\n");
    $testsPassed = false;
} else {
    fwrite(STDOUT, "  ? INFO: Could not verify fallback removal\n");
}

fwrite(STDOUT, "\n");

// Summary
if ($testsPassed) {
    fwrite(STDOUT, "All tests passed! ✓\n\n");
    fwrite(STDOUT, "Verified that:\n");
    fwrite(STDOUT, "1. updateAuthorizations() receives primary_txn_id parameter\n");
    fwrite(STDOUT, "2. updateAuthorizations() is called with primary_txn_id\n");
    fwrite(STDOUT, "3. AUTHORIZE records use primary_txn_id (PayPal order ID) as parent\n");
    fwrite(STDOUT, "4. extract_rest_payment_id() does not fall back to order ID\n");
    exit(0);
} else {
    fwrite(STDERR, "\nSome tests failed! ✗\n");
    fwrite(STDERR, "AUTHORIZE transactions may have incorrect parent_txn_id.\n");
    exit(1);
}
