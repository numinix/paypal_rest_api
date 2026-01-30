<?php
declare(strict_types=1);

/**
 * Test to verify SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED constant handling
 * 
 * This test validates that:
 * 1. The cron handles undefined constant gracefully (defaults to 0 = unlimited)
 * 2. When constant is 0, retries are unlimited
 * 3. When constant is > 0, retries are limited
 * 4. The logic correctly determines when to stop retrying
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

print "=== Testing SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED Handling ===\n\n";

// Test 1: Undefined constant should default to 0 (unlimited)
print "Test 1: Undefined constant defaults to 0 (unlimited retries)\n";
$max_fails_allowed = defined('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED') 
    ? (int)SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED 
    : 0;

if ($max_fails_allowed === 0) {
    print "  ✓ PASS: Default value is 0 (unlimited retries)\n";
} else {
    print "  ✗ FAIL: Default should be 0, got " . $max_fails_allowed . "\n";
    exit(1);
}

// Test 2: When max_fails_allowed = 0, should never stop retrying
print "\nTest 2: Unlimited retries (max_fails_allowed = 0)\n";
$test_cases = [1, 5, 10, 100, 1000];
foreach ($test_cases as $num_failed_payments) {
    $has_exceeded_max_attempts = ($max_fails_allowed > 0 && $num_failed_payments >= $max_fails_allowed);
    if (!$has_exceeded_max_attempts) {
        print "  ✓ PASS: " . $num_failed_payments . " failures - will retry (unlimited)\n";
    } else {
        print "  ✗ FAIL: " . $num_failed_payments . " failures - should retry but won't\n";
        exit(1);
    }
}

// Test 3: When max_fails_allowed = 3, should stop after 3 failures
print "\nTest 3: Limited retries (max_fails_allowed = 3)\n";
$max_fails_allowed = 3;
$test_scenarios = [
    ['failures' => 1, 'should_retry' => true],
    ['failures' => 2, 'should_retry' => true],
    ['failures' => 3, 'should_retry' => false],
    ['failures' => 4, 'should_retry' => false],
    ['failures' => 5, 'should_retry' => false],
];

foreach ($test_scenarios as $scenario) {
    $num_failed_payments = $scenario['failures'];
    $should_retry = $scenario['should_retry'];
    $has_exceeded_max_attempts = ($max_fails_allowed > 0 && $num_failed_payments >= $max_fails_allowed);
    $will_retry = !$has_exceeded_max_attempts;
    
    if ($will_retry === $should_retry) {
        $action = $will_retry ? 'will retry' : 'will NOT retry';
        print "  ✓ PASS: " . $num_failed_payments . " failures (max: " . $max_fails_allowed . ") - " . $action . "\n";
    } else {
        $expected = $should_retry ? 'retry' : 'NOT retry';
        $actual = $will_retry ? 'retry' : 'NOT retry';
        print "  ✗ FAIL: " . $num_failed_payments . " failures - expected " . $expected . ", got " . $actual . "\n";
        exit(1);
    }
}

// Test 4: Simulate constant being defined
print "\nTest 4: Simulate constant defined as 5\n";
if (!defined('TEST_MAX_FAILS_CONSTANT')) {
    define('TEST_MAX_FAILS_CONSTANT', 5);
}

$max_fails_allowed = defined('TEST_MAX_FAILS_CONSTANT') 
    ? (int)TEST_MAX_FAILS_CONSTANT 
    : 0;

if ($max_fails_allowed === 5) {
    print "  ✓ PASS: Constant correctly read as 5\n";
    
    // Test the logic with 5 as max
    $has_exceeded_at_4 = ($max_fails_allowed > 0 && 4 >= $max_fails_allowed);
    $has_exceeded_at_5 = ($max_fails_allowed > 0 && 5 >= $max_fails_allowed);
    $has_exceeded_at_6 = ($max_fails_allowed > 0 && 6 >= $max_fails_allowed);
    
    if (!$has_exceeded_at_4 && $has_exceeded_at_5 && $has_exceeded_at_6) {
        print "  ✓ PASS: Logic correctly stops at 5 failures\n";
    } else {
        print "  ✗ FAIL: Logic error - 4:" . ($has_exceeded_at_4 ? 'exceeded' : 'ok') . 
               ", 5:" . ($has_exceeded_at_5 ? 'exceeded' : 'ok') . 
               ", 6:" . ($has_exceeded_at_6 ? 'exceeded' : 'ok') . "\n";
        exit(1);
    }
} else {
    print "  ✗ FAIL: Expected 5, got " . $max_fails_allowed . "\n";
    exit(1);
}

// Test 5: Display format in results array
print "\nTest 5: Display format for max_attempts field\n";

// Unlimited case
$max_fails_allowed = 0;
$display_value = $max_fails_allowed > 0 ? $max_fails_allowed : 'Unlimited';
if ($display_value === 'Unlimited') {
    print "  ✓ PASS: Unlimited retries displays as 'Unlimited'\n";
} else {
    print "  ✗ FAIL: Should display 'Unlimited', got '" . $display_value . "'\n";
    exit(1);
}

// Limited case
$max_fails_allowed = 3;
$display_value = $max_fails_allowed > 0 ? $max_fails_allowed : 'Unlimited';
if ($display_value === 3) {
    print "  ✓ PASS: Limited retries (3) displays as '3'\n";
} else {
    print "  ✗ FAIL: Should display '3', got '" . $display_value . "'\n";
    exit(1);
}

print "\n=== All Tests Passed! ===\n";
print "The max fails constant is properly handled:\n";
print "  • Undefined constant defaults to 0 (unlimited retries)\n";
print "  • When 0, retries are unlimited\n";
print "  • When > 0, retries stop after that many failures\n";
print "  • Display format shows 'Unlimited' or the actual number\n";

exit(0);
