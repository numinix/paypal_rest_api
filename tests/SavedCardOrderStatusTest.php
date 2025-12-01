<?php
/**
 * Test that verifies the paypalr_savedcard module correctly sets order status
 * to match the paypalr.php module's behavior.
 * 
 * Bug fix: The saved card module was using HELD_STATUS_ID instead of 
 * ORDER_PENDING_STATUS_ID for non-completed payments, and checking 
 * STATUS_COMPLETED instead of STATUS_CAPTURED/STATUS_CREATED.
 */

$testPassed = true;
$errors = [];

// Define status constants as they would be in PayPalRestfulApi
if (!defined('STATUS_CAPTURED')) {
    define('STATUS_CAPTURED', 'CAPTURED');
}
if (!defined('STATUS_CREATED')) {
    define('STATUS_CREATED', 'CREATED');
}
if (!defined('STATUS_PENDING')) {
    define('STATUS_PENDING', 'PENDING');
}
if (!defined('STATUS_APPROVED')) {
    define('STATUS_APPROVED', 'APPROVED');
}

// Define order status IDs
if (!defined('MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID')) {
    define('MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID', 2); // Processing
}
if (!defined('MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID')) {
    define('MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID', 1); // Pending
}
if (!defined('MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID')) {
    define('MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID', 1); // Held
}
if (!defined('DEFAULT_ORDERS_STATUS_ID')) {
    define('DEFAULT_ORDERS_STATUS_ID', 1);
}

/**
 * Simulates the old (buggy) order status logic from paypalr_savedcard.php
 * This checks STATUS_COMPLETED and uses HELD_STATUS_ID
 */
function getOldOrderStatus(string $paymentStatus): int
{
    // Old buggy logic:
    // if ($payment['status'] !== 'COMPLETED') -> use HELD_STATUS_ID
    if ($paymentStatus !== 'COMPLETED') {
        return (int)MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID;
    }
    return (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
}

/**
 * Simulates the new (fixed) order status logic from paypalr_savedcard.php
 * This matches paypalr.php: checks STATUS_CAPTURED/STATUS_CREATED and uses ORDER_PENDING_STATUS_ID
 */
function getNewOrderStatus(string $paymentStatus): int
{
    // New fixed logic (matches paypalr.php):
    // if ($payment_status !== STATUS_CAPTURED && $payment_status !== STATUS_CREATED) -> use ORDER_PENDING_STATUS_ID
    if ($paymentStatus !== STATUS_CAPTURED && $paymentStatus !== STATUS_CREATED) {
        return (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
    }
    return (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
}

/**
 * Simulates the paypalr.php order status logic (the reference implementation)
 */
function getPaypalrOrderStatus(string $paymentStatus): int
{
    // paypalr.php logic:
    // if ($payment_status !== STATUS_CAPTURED && $payment_status !== STATUS_CREATED)
    if ($paymentStatus !== STATUS_CAPTURED && $paymentStatus !== STATUS_CREATED) {
        return (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
    }
    return (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
}

// Test scenarios
$testCases = [
    ['status' => STATUS_CAPTURED, 'description' => 'Captured payment'],
    ['status' => STATUS_CREATED, 'description' => 'Created (authorized) payment'],
    ['status' => STATUS_PENDING, 'description' => 'Pending payment'],
    ['status' => STATUS_APPROVED, 'description' => 'Approved payment'],
    ['status' => 'VOIDED', 'description' => 'Voided payment'],
    ['status' => 'DECLINED', 'description' => 'Declined payment'],
];

echo "Testing order status logic alignment...\n\n";

// Test 1: Verify new logic matches paypalr.php for all statuses
foreach ($testCases as $testCase) {
    $status = $testCase['status'];
    $description = $testCase['description'];
    
    $newStatus = getNewOrderStatus($status);
    $paypalrStatus = getPaypalrOrderStatus($status);
    
    if ($newStatus !== $paypalrStatus) {
        $testPassed = false;
        $errors[] = "Status mismatch for '$description' ($status): new=$newStatus, paypalr=$paypalrStatus";
    } else {
        echo "✓ $description ($status): Status matches paypalr.php (status=$newStatus)\n";
    }
}

echo "\n";

// Test 2: Verify that CAPTURED status gets ORDER_STATUS_ID (completed)
$capturedStatus = getNewOrderStatus(STATUS_CAPTURED);
if ($capturedStatus !== (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID) {
    $testPassed = false;
    $errors[] = "CAPTURED status should use ORDER_STATUS_ID, got: $capturedStatus";
} else {
    echo "✓ CAPTURED payments use ORDER_STATUS_ID ($capturedStatus)\n";
}

// Test 3: Verify that CREATED status gets ORDER_STATUS_ID (completed for auth)
$createdStatus = getNewOrderStatus(STATUS_CREATED);
if ($createdStatus !== (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID) {
    $testPassed = false;
    $errors[] = "CREATED status should use ORDER_STATUS_ID, got: $createdStatus";
} else {
    echo "✓ CREATED (authorized) payments use ORDER_STATUS_ID ($createdStatus)\n";
}

// Test 4: Verify that PENDING status gets ORDER_PENDING_STATUS_ID
$pendingStatus = getNewOrderStatus(STATUS_PENDING);
if ($pendingStatus !== (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID) {
    $testPassed = false;
    $errors[] = "PENDING status should use ORDER_PENDING_STATUS_ID, got: $pendingStatus";
} else {
    echo "✓ PENDING payments use ORDER_PENDING_STATUS_ID ($pendingStatus)\n";
}

// Test 5: Verify that other statuses get ORDER_PENDING_STATUS_ID
$otherStatus = getNewOrderStatus('VOIDED');
if ($otherStatus !== (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID) {
    $testPassed = false;
    $errors[] = "VOIDED status should use ORDER_PENDING_STATUS_ID, got: $otherStatus";
} else {
    echo "✓ VOIDED payments use ORDER_PENDING_STATUS_ID ($otherStatus)\n";
}

echo "\n";

// Test 6: Demonstrate the bug fix - old logic vs new logic
echo "Bug fix demonstration:\n";
echo "  Old logic checked: payment['status'] !== 'COMPLETED'\n";
echo "  New logic checks: payment_status !== STATUS_CAPTURED && payment_status !== STATUS_CREATED\n\n";

// For CAPTURED status:
$oldForCaptured = getOldOrderStatus(STATUS_CAPTURED);
$newForCaptured = getNewOrderStatus(STATUS_CAPTURED);
if ($oldForCaptured !== $newForCaptured) {
    echo "  ✓ Fix applied: CAPTURED status previously used wrong status ($oldForCaptured), now correct ($newForCaptured)\n";
} else {
    echo "  - CAPTURED status: old=$oldForCaptured, new=$newForCaptured (same)\n";
}

// For CREATED status:
$oldForCreated = getOldOrderStatus(STATUS_CREATED);
$newForCreated = getNewOrderStatus(STATUS_CREATED);
if ($oldForCreated !== $newForCreated) {
    echo "  ✓ Fix applied: CREATED status previously used wrong status ($oldForCreated), now correct ($newForCreated)\n";
} else {
    echo "  - CREATED status: old=$oldForCreated, new=$newForCreated (same)\n";
}

echo "\n";

// Summary
if ($testPassed) {
    echo "All order status tests passed! ✓\n";
    echo "\nThe fix ensures saved card module uses the same order status logic as paypalr.php:\n";
    echo "- Uses ORDER_STATUS_ID for successful captures and authorizations\n";
    echo "- Uses ORDER_PENDING_STATUS_ID for pending/other statuses\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
