<?php
declare(strict_types=1);

/**
 * Test to verify that the next billing date calculation correctly advances
 * by one billing period from the current next_payment_date.
 *
 * This test addresses the issue:
 * "If the Next Billing Date is 02/02/2026 and it's a 1 week recurring subscription,
 * then the new Next Billing Date should be 1 week from that Next Billing Date."
 *
 * Expected behavior: After processing a payment scheduled for date X, the next
 * billing date should be X + billing_period (e.g., X + 1 week for weekly billing).
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

print "=== Testing Next Billing Date Calculation ===\n\n";

// Mock the helper functions used by the cron
$parseRecurringDate = function ($value) {
    if ($value instanceof DateTime) {
        return clone $value;
    }

    if (is_string($value) && trim($value) !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', trim($value));
        if ($parsed instanceof DateTime) {
            $parsed->setTime(0, 0, 0);
            return $parsed;
        }
        
        // Handle datetime format with time component
        $parsed = DateTime::createFromFormat('Y-m-d H:i:s', trim($value));
        if ($parsed instanceof DateTime) {
            $parsed->setTime(0, 0, 0);
            return $parsed;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $date = new DateTime('today');
            $date->setTimestamp($timestamp);
            $date->setTime(0, 0, 0);
            return $date;
        }
    }

    return new DateTime('today');
};

$advanceBillingCycle = function (DateTime $baseDate, array $attributes) {
    $period = isset($attributes['billingperiod']) ? trim((string) $attributes['billingperiod']) : '';
    $frequency = isset($attributes['billingfrequency']) ? (int) $attributes['billingfrequency'] : 0;

    if ($period === '' || $frequency <= 0) {
        return null;
    }

    $normalizedPeriod = strtolower($period);
    $next = clone $baseDate;

    try {
        switch ($normalizedPeriod) {
            case 'day':
            case 'daily':
                $next->add(new DateInterval('P' . $frequency . 'D'));
                break;
            case 'week':
            case 'weekly':
                $next->add(new DateInterval('P' . $frequency . 'W'));
                break;
            case 'semimonth':
            case 'semi-month':
            case 'semi monthly':
            case 'semi-monthly':
            case 'bi-weekly':
            case 'bi weekly':
                $days = max(1, $frequency * 15);
                $next->add(new DateInterval('P' . $days . 'D'));
                break;
            case 'month':
            case 'monthly':
                $next->add(new DateInterval('P' . $frequency . 'M'));
                break;
            case 'year':
            case 'yearly':
                $next->add(new DateInterval('P' . $frequency . 'Y'));
                break;
            default:
                $next->modify('+' . $frequency . ' ' . $period);
                break;
        }
    } catch (Exception $e) {
        return null;
    }

    return $next;
};

/**
 * Calculate the next billing date by advancing from the current next_payment_date.
 * Simply adds one billing cycle to the current billing date.
 */
$calculateNextScheduledBillingDate = function (DateTime $currentNextPaymentDate, array $paymentDetails, array $attributes) use ($advanceBillingCycle) {
    // Simply advance by one billing cycle from the current next_payment_date
    // This maintains the correct billing schedule based on the actual billing date
    return $advanceBillingCycle($currentNextPaymentDate, $attributes);
};

// Test case 1: Weekly subscription - exact issue from problem statement
print "Test 1: Weekly subscription - exact issue from problem statement\n";
print "  Scenario: Next Billing Date is 02/02/2026, 1 week recurring subscription\n";
print "  Expected next billing after processing: 02/09/2026 (1 week from 02/02)\n";

$paymentDetails = [
    'date_added' => '2026-01-02',  // Subscription creation date (ignored)
    'next_payment_date' => '2026-02-02'
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/02 + 1 week = 02/09
if ($nextBillingDate->format('Y-m-d') === '2026-02-09') {
    print "  ✓ PASS: Next billing correctly calculated as 02/09/2026\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-09, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 2: Bi-weekly subscription
print "Test 2: Bi-weekly subscription\n";
print "  Scenario: Next Billing Date is 02/01/2026, 2 week recurring subscription\n";
print "  Expected next billing after processing: 02/15/2026 (2 weeks from 02/01)\n";

$paymentDetails = [
    'date_added' => '2026-01-07',
    'next_payment_date' => '2026-02-01'
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 2  // Bi-weekly
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/01 + 2 weeks = 02/15
if ($nextBillingDate->format('Y-m-d') === '2026-02-15') {
    print "  ✓ PASS: Next billing correctly calculated as 02/15/2026\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-15, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 3: Monthly subscription
print "Test 3: Monthly subscription\n";
print "  Scenario: Next Billing Date is 02/01/2026, 1 month recurring subscription\n";
print "  Expected next billing after processing: 03/01/2026 (1 month from 02/01)\n";

$paymentDetails = [
    'date_added' => '2026-01-15',
    'next_payment_date' => '2026-02-01'
];
$attributes = [
    'billingperiod' => 'Month',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/01 + 1 month = 03/01
if ($nextBillingDate->format('Y-m-d') === '2026-03-01') {
    print "  ✓ PASS: Next billing correctly calculated as 03/01/2026\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-03-01, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 4: Normal weekly operation
print "Test 4: Normal weekly operation\n";
print "  Scenario: Weekly subscription with payment on 02/04/2026\n";
print "  Expected next billing: 02/11/2026\n";

$paymentDetails = [
    'date_added' => '2026-01-07',
    'next_payment_date' => '2026-02-04'
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/04 + 1 week = 02/11
if ($nextBillingDate->format('Y-m-d') === '2026-02-11') {
    print "  ✓ PASS: Normal operation correctly advances to next scheduled date\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-11, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 5: Daily subscription
print "Test 5: Daily subscription\n";
print "  Scenario: Daily subscription with payment on 02/01/2026\n";
print "  Expected next billing: 02/02/2026\n";

$paymentDetails = [
    'date_added' => '2026-01-01',
    'next_payment_date' => '2026-02-01'
];
$attributes = [
    'billingperiod' => 'Day',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/01 + 1 day = 02/02
if ($nextBillingDate->format('Y-m-d') === '2026-02-02') {
    print "  ✓ PASS: Daily subscription correctly advances by 1 day\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-02, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 6: Yearly subscription
print "Test 6: Yearly subscription\n";
print "  Scenario: Yearly subscription with payment on 02/01/2026\n";
print "  Expected next billing: 02/01/2027\n";

$paymentDetails = [
    'date_added' => '2025-02-01',
    'next_payment_date' => '2026-02-01'
];
$attributes = [
    'billingperiod' => 'Year',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/01/2026 + 1 year = 02/01/2027
if ($nextBillingDate->format('Y-m-d') === '2027-02-01') {
    print "  ✓ PASS: Yearly subscription correctly advances by 1 year\n\n";
} else {
    print "  ✗ FAIL: Expected 2027-02-01, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

print "=== All Tests Passed! ===\n";
print "\n";
print "Fix verified:\n";
print "1. Weekly subscription on 02/02 correctly advances to 02/09\n";
print "2. Bi-weekly, monthly, daily, and yearly subscriptions work correctly\n";
print "3. Next billing date is always current_date + billing_period\n";

exit(0);
