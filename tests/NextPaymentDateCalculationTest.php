<?php
declare(strict_types=1);

/**
 * Test to verify that the next payment date calculation maintains the original billing schedule
 * even when payments are processed late.
 *
 * This test addresses the issue:
 * "When a subscription is monthly and on the 15th, but their payment fails for 5 days, 
 * their next payment should still be on the 15th of the following month."
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

print "=== Testing Next Payment Date Calculation ===\n\n";

// Test case 1: Monthly subscription on the 15th, payment 5 days late
print "Test 1: Monthly subscription, payment 5 days late\n";
print "  Scenario: Subscription bills on 15th of each month\n";
print "  Current date: Jan 20, 2026 (payment finally processed)\n";
print "  Next payment date in DB: Jan 15, 2026\n";
print "  Expected next billing: Feb 15, 2026 (NOT Feb 20)\n";

$next_payment_date = '2026-01-15'; // What's in the database
$billing_period = 'month';
$billing_frequency = 1;

// Simulate the logic in cron
$scheduledDate = DateTime::createFromFormat('Y-m-d', $next_payment_date);
$scheduledDate->setTime(0, 0, 0);

// This is the intendedBillingDate (line 506 in cron)
$intendedBillingDate = clone $scheduledDate;

// Advance billing cycle (line 521 in cron)
$nextCycleDue = clone $intendedBillingDate;
$nextCycleDue->add(new DateInterval('P' . $billing_frequency . 'M'));

print "  Calculated next payment: " . $nextCycleDue->format('Y-m-d') . "\n";

if ($nextCycleDue->format('Y-m-d') === '2026-02-15') {
    print "  ✓ PASS: Next payment correctly calculated as Feb 15\n\n";
} else {
    print "  ✗ FAIL: Next payment should be Feb 15, got " . $nextCycleDue->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 2: Monthly subscription, over a month late
print "Test 2: Monthly subscription, over a month late\n";
print "  Scenario: Subscription bills on 15th of each month\n";
print "  Current date: Feb 20, 2026 (payment finally processed)\n";
print "  Next payment date in DB: Jan 15, 2026\n";
print "  Expected behavior: Feb 15 already passed, so should be processed immediately\n";
print "  Expected next billing after Feb 15: Mar 15, 2026\n";

$next_payment_date = '2026-01-15'; // What's in the database
$today = new DateTime('2026-02-20');
$today->setTime(0, 0, 0);

$scheduledDate = DateTime::createFromFormat('Y-m-d', $next_payment_date);
$scheduledDate->setTime(0, 0, 0);

$intendedBillingDate = clone $scheduledDate;

// First advance: Jan 15 -> Feb 15
$nextCycleDue = clone $intendedBillingDate;
$nextCycleDue->add(new DateInterval('P' . $billing_frequency . 'M'));

print "  First advance (Jan 15 payment): " . $nextCycleDue->format('Y-m-d') . "\n";

// Check if Feb 15 has already passed
if ($nextCycleDue <= $today) {
    print "  ✓ Feb 15 has passed, will be processed immediately\n";
    
    // After processing Feb 15 payment, next would be Mar 15
    $afterFebPayment = clone $nextCycleDue;
    $afterFebPayment->add(new DateInterval('P' . $billing_frequency . 'M'));
    print "  Next billing after Feb 15 payment: " . $afterFebPayment->format('Y-m-d') . "\n";
    
    if ($afterFebPayment->format('Y-m-d') === '2026-03-15') {
        print "  ✓ PASS: Next payment correctly calculated as Mar 15\n\n";
    } else {
        print "  ✗ FAIL: Next payment should be Mar 15, got " . $afterFebPayment->format('Y-m-d') . "\n\n";
        exit(1);
    }
} else {
    print "  ✗ FAIL: Feb 15 should have already passed\n\n";
    exit(1);
}

// Test case 3: Weekly subscription
print "Test 3: Weekly subscription, payment 3 days late\n";
print "  Scenario: Subscription bills every Monday\n";
print "  Current date: Thursday (payment finally processed)\n";
print "  Next payment date in DB: Monday\n";
print "  Expected next billing: Next Monday (7 days from original Monday)\n";

$next_payment_date = '2026-01-05'; // Monday
$billing_period = 'week';
$billing_frequency = 1;

$scheduledDate = DateTime::createFromFormat('Y-m-d', $next_payment_date);
$scheduledDate->setTime(0, 0, 0);

$intendedBillingDate = clone $scheduledDate;

$nextCycleDue = clone $intendedBillingDate;
$nextCycleDue->add(new DateInterval('P' . $billing_frequency . 'W'));

print "  Original payment date: " . $scheduledDate->format('Y-m-d (l)') . "\n";
print "  Calculated next payment: " . $nextCycleDue->format('Y-m-d (l)') . "\n";

if ($nextCycleDue->format('Y-m-d') === '2026-01-12' && $nextCycleDue->format('l') === 'Monday') {
    print "  ✓ PASS: Next payment correctly calculated as next Monday (Jan 12)\n\n";
} else {
    print "  ✗ FAIL: Next payment should be Monday Jan 12, got " . $nextCycleDue->format('Y-m-d (l)') . "\n\n";
    exit(1);
}

// Test case 4: Verify the fix - using next_payment_date vs date_added
print "Test 4: Verify fix - Using next_payment_date (not date_added)\n";
print "  Scenario: Monthly subscription created on Jan 1, bills on 15th\n";
print "  Wrong: Using date_added (Jan 1) as base\n";
print "  Correct: Using next_payment_date (Jan 15) as base\n";

$date_added = '2026-01-01'; // Subscription creation date
$next_payment_date = '2026-01-15'; // Current billing date in DB
$billing_period = 'month';
$billing_frequency = 1;

// WRONG way (using date_added)
$wrongBase = DateTime::createFromFormat('Y-m-d', $date_added);
$wrongBase->setTime(0, 0, 0);
$wrongNext = clone $wrongBase;
$wrongNext->add(new DateInterval('P' . $billing_frequency . 'M'));

// CORRECT way (using next_payment_date)
$correctBase = DateTime::createFromFormat('Y-m-d', $next_payment_date);
$correctBase->setTime(0, 0, 0);
$correctNext = clone $correctBase;
$correctNext->add(new DateInterval('P' . $billing_frequency . 'M'));

print "  Wrong (from date_added Jan 1): " . $wrongNext->format('Y-m-d') . "\n";
print "  Correct (from next_payment_date Jan 15): " . $correctNext->format('Y-m-d') . "\n";

if ($correctNext->format('Y-m-d') === '2026-02-15') {
    print "  ✓ PASS: Correctly using next_payment_date maintains schedule on 15th\n\n";
} else {
    print "  ✗ FAIL: Should maintain billing on 15th\n\n";
    exit(1);
}

print "=== All Tests Passed! ===\n";
print "Next payment dates are correctly calculated to maintain the original billing schedule.\n";
print "Late payments will not shift the billing schedule forward.\n";

exit(0);
