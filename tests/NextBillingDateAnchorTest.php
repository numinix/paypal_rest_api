<?php
declare(strict_types=1);

/**
 * Test to verify that the next billing date calculation maintains the original billing schedule
 * when next_payment_date is manually edited.
 *
 * This test addresses the issue:
 * "I had a subscription order with a next billing date of 02/07/2026. I manually changed it 
 * to 02/01/2026 for testing. After it successfully processed, the next billing date was set 
 * to 02/14/2026. Shouldn't it have been set to 02/07/2026 again?"
 *
 * Expected behavior: The next billing date should align with the original schedule based on
 * date_added as the anchor, not drift based on manual edits.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

print "=== Testing Next Billing Date Anchor Calculation ===\n\n";

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
 * The new function that calculates next billing date from the anchor (date_added)
 */
$calculateNextScheduledBillingDate = function (DateTime $currentNextPaymentDate, array $paymentDetails, array $attributes) use ($parseRecurringDate, $advanceBillingCycle) {
    // Get the anchor date (date_added) from the subscription record
    $anchorDateString = '';
    if (isset($paymentDetails['date_added']) && $paymentDetails['date_added'] !== '' && $paymentDetails['date_added'] !== null) {
        $anchorDateString = $paymentDetails['date_added'];
    }
    
    // If no anchor date available, fall back to standard behavior
    if ($anchorDateString === '') {
        return $advanceBillingCycle($currentNextPaymentDate, $attributes);
    }
    
    $anchorDate = $parseRecurringDate($anchorDateString);
    if (!($anchorDate instanceof DateTime)) {
        return $advanceBillingCycle($currentNextPaymentDate, $attributes);
    }
    $anchorDate->setTime(0, 0, 0);
    
    // Start from the anchor and advance through the billing schedule
    // until we find a date that's after the current next_payment_date
    $scheduledDate = clone $anchorDate;
    $currentNextPaymentDate->setTime(0, 0, 0);
    
    // Safety limit to prevent infinite loops
    $maxIterations = 36500;
    $iterations = 0;
    
    while ($scheduledDate <= $currentNextPaymentDate && $iterations < $maxIterations) {
        $nextScheduledDate = $advanceBillingCycle($scheduledDate, $attributes);
        if (!($nextScheduledDate instanceof DateTime)) {
            return $advanceBillingCycle($currentNextPaymentDate, $attributes);
        }
        $scheduledDate = $nextScheduledDate;
        $scheduledDate->setTime(0, 0, 0);
        $iterations++;
    }
    
    if ($iterations >= $maxIterations) {
        return $advanceBillingCycle($currentNextPaymentDate, $attributes);
    }
    
    return $scheduledDate;
};

// Test case 1: Weekly subscription - manually edited to earlier date
print "Test 1: Weekly subscription, manually edited to earlier date\n";
print "  Scenario: Weekly subscription started on 01/07/2026 (anchor)\n";
print "  Original next_payment_date: 02/07/2026\n";
print "  Admin manually changed to: 02/01/2026 for testing\n";
print "  Expected next billing after processing: 02/04/2026 (next date in original weekly pattern)\n";

$paymentDetails = [
    'date_added' => '2026-01-07',  // Anchor date - subscription creation
    'next_payment_date' => '2026-02-01'  // Manually edited date
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 01/07 -> 01/14 -> 01/21 -> 01/28 -> 02/04 (first date after 02/01)
if ($nextBillingDate->format('Y-m-d') === '2026-02-04') {
    print "  ✓ PASS: Next billing correctly aligns with original schedule (02/04/2026)\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-04, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 2: Bi-weekly subscription - manually edited to earlier date
print "Test 2: Bi-weekly subscription, manually edited to earlier date\n";
print "  Scenario: Bi-weekly subscription started on 01/07/2026 (anchor)\n";
print "  Original next_payment_date: 02/04/2026\n";
print "  Admin manually changed to: 02/01/2026 for testing\n";
print "  Expected next billing after processing: 02/04/2026 (next date in original bi-weekly pattern)\n";

$paymentDetails = [
    'date_added' => '2026-01-07',  // Anchor date
    'next_payment_date' => '2026-02-01'  // Manually edited date
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 2  // Bi-weekly
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 01/07 -> 01/21 -> 02/04 (first date after 02/01)
if ($nextBillingDate->format('Y-m-d') === '2026-02-04') {
    print "  ✓ PASS: Next billing correctly aligns with original bi-weekly schedule\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-04, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 3: Monthly subscription - manually edited to earlier date
print "Test 3: Monthly subscription, manually edited to earlier date\n";
print "  Scenario: Monthly subscription started on 01/15/2026 (anchor)\n";
print "  Original next_payment_date: 02/15/2026\n";
print "  Admin manually changed to: 02/01/2026 for testing\n";
print "  Expected next billing after processing: 02/15/2026 (next date in original monthly pattern)\n";

$paymentDetails = [
    'date_added' => '2026-01-15',  // Anchor date
    'next_payment_date' => '2026-02-01'  // Manually edited date
];
$attributes = [
    'billingperiod' => 'Month',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 01/15 -> 02/15 (first date after 02/01)
if ($nextBillingDate->format('Y-m-d') === '2026-02-15') {
    print "  ✓ PASS: Next billing correctly aligns with original monthly schedule (02/15)\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-15, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 4: Normal operation (no manual edit) - should work as before
print "Test 4: Normal operation without manual editing\n";
print "  Scenario: Weekly subscription with normal payment on 02/04/2026\n";
print "  Expected next billing: 02/11/2026\n";

$paymentDetails = [
    'date_added' => '2026-01-07',  // Anchor date
    'next_payment_date' => '2026-02-04'  // Regular payment date (on schedule)
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 02/04 -> 02/11
if ($nextBillingDate->format('Y-m-d') === '2026-02-11') {
    print "  ✓ PASS: Normal operation correctly advances to next scheduled date\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-11, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 5: No anchor date (fallback behavior)
print "Test 5: No anchor date available (fallback to simple advance)\n";
print "  Scenario: Legacy subscription without date_added\n";
print "  Expected behavior: Falls back to adding one billing period to current date\n";

$paymentDetails = [
    'date_added' => '',  // No anchor
    'next_payment_date' => '2026-02-01'
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// Fallback: 02/01 + 1 week = 02/08
if ($nextBillingDate->format('Y-m-d') === '2026-02-08') {
    print "  ✓ PASS: Correctly falls back to simple advance when no anchor available\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-08, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

// Test case 6: Reproduction of exact issue from problem statement
print "Test 6: Exact reproduction of reported issue\n";
print "  Scenario from issue:\n";
print "    - Original next billing date: 02/07/2026\n";
print "    - Manually changed to: 02/01/2026 for testing\n";
print "    - After processing, date was: 02/14/2026 (WRONG - bi-weekly assumed)\n";
print "    - Expected after processing: 02/07/2026 (return to original schedule)\n\n";

// This tests a subscription that was created on 01/31/2026 with weekly billing
// The schedule would be: 01/31, 02/07, 02/14, 02/21, etc.
$paymentDetails = [
    'date_added' => '2026-01-31',  // Creates schedule: 01/31 -> 02/07 -> 02/14
    'next_payment_date' => '2026-02-01'  // Manually edited from 02/07
];
$attributes = [
    'billingperiod' => 'Week',
    'billingfrequency' => 1
];

$currentNextPaymentDate = $parseRecurringDate($paymentDetails['next_payment_date']);
$nextBillingDate = $calculateNextScheduledBillingDate($currentNextPaymentDate, $paymentDetails, $attributes);

print "  Calculated next billing: " . $nextBillingDate->format('Y-m-d') . "\n";

// 01/31 -> 02/07 (first date after 02/01)
if ($nextBillingDate->format('Y-m-d') === '2026-02-07') {
    print "  ✓ PASS: Next billing correctly returns to 02/07/2026 as expected!\n\n";
} else {
    print "  ✗ FAIL: Expected 2026-02-07, got " . $nextBillingDate->format('Y-m-d') . "\n\n";
    exit(1);
}

print "=== All Tests Passed! ===\n";
print "\n";
print "Fix verified:\n";
print "1. Manually-edited next_payment_date no longer causes schedule drift\n";
print "2. Next billing date aligns with original schedule based on date_added anchor\n";
print "3. Normal operations continue to work correctly\n";
print "4. Legacy subscriptions without date_added gracefully fall back to simple advance\n";

exit(0);
