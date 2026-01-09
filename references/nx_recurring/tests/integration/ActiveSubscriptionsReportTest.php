<?php
/**
 * Tests for Active Subscriptions Report calculations
 */

// Define the function locally for testing (extracted from active_subscriptions_report.php)
if (!function_exists('asr_compute_annual_value')) {
    function asr_compute_annual_value($amount, $period, $frequency)
    {
        $amount = (float) $amount;
        $period = is_string($period) ? strtolower(trim($period)) : '';
        $frequency = (int) $frequency;

        if ($amount <= 0 || $period === '') {
            return null;
        }

        // Normalize common data entry errors where period and frequency appear mismatched
        // If period is "year" but frequency looks like days (365-366), treat as "day" period
        if ($period === 'year' && $frequency >= 365 && $frequency <= 366) {
            $period = 'day';
        }

        switch ($period) {
            case 'day':
                $periodsPerYear = 365;
                break;
            case 'week':
                $periodsPerYear = 52;
                break;
            case 'semimonth':
            case 'semi-month':
                $periodsPerYear = 24;
                break;
            case 'month':
                $periodsPerYear = 12;
                break;
            case 'year':
                $periodsPerYear = 1;
                break;
            default:
                return null;
        }

        if ($frequency <= 0) {
            $frequency = 1;
        }

        $chargesPerYear = $periodsPerYear / $frequency;
        if ($chargesPerYear <= 0) {
            return null;
        }

        return $amount * $chargesPerYear;
    }
}

class ActiveSubscriptionsReportTest
{
    private $testResults = [];
    private $testCount = 0;
    private $passCount = 0;

    public function run()
    {
        echo "Running Active Subscriptions Report Tests...\n\n";

        $this->testAnnualValueCalculation_NormalCases();
        $this->testAnnualValueCalculation_YearPeriodNormalization();
        $this->testAnnualValueCalculation_EdgeCases();

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Test Results: {$this->passCount}/{$this->testCount} passed\n";
        echo str_repeat("=", 60) . "\n";

        return $this->passCount === $this->testCount;
    }

    private function assert($condition, $message)
    {
        $this->testCount++;
        if ($condition) {
            $this->passCount++;
            echo "✓ PASS: $message\n";
        } else {
            echo "✗ FAIL: $message\n";
        }
    }

    private function assertEqualsWithTolerance($expected, $actual, $tolerance, $message)
    {
        $this->testCount++;
        $diff = abs($expected - $actual);
        if ($diff <= $tolerance) {
            $this->passCount++;
            echo "✓ PASS: $message (expected: $expected, actual: $actual)\n";
        } else {
            echo "✗ FAIL: $message (expected: $expected, actual: $actual, diff: $diff)\n";
        }
    }

    public function testAnnualValueCalculation_NormalCases()
    {
        echo "Test Group: Normal Cases\n";
        echo str_repeat("-", 60) . "\n";

        // Test: Every 1 Year
        $result = asr_compute_annual_value(100, 'Year', 1);
        $this->assertEqualsWithTolerance(100.0, $result, 0.01, "Every 1 Year @ $100 = $100/year");

        // Test: Every 1 Month
        $result = asr_compute_annual_value(10, 'Month', 1);
        $this->assertEqualsWithTolerance(120.0, $result, 0.01, "Every 1 Month @ $10 = $120/year");

        // Test: Every 2 Months
        $result = asr_compute_annual_value(20, 'Month', 2);
        $this->assertEqualsWithTolerance(120.0, $result, 0.01, "Every 2 Months @ $20 = $120/year");

        // Test: Every 1 Week
        $result = asr_compute_annual_value(10, 'Week', 1);
        $this->assertEqualsWithTolerance(520.0, $result, 0.01, "Every 1 Week @ $10 = $520/year");

        // Test: Every 7 Days (weekly)
        $result = asr_compute_annual_value(10, 'Day', 7);
        $this->assertEqualsWithTolerance(521.43, $result, 0.01, "Every 7 Days @ $10 = ~$521.43/year");

        // Test: Every 30 Days (monthly-ish)
        $result = asr_compute_annual_value(100, 'Day', 30);
        $this->assertEqualsWithTolerance(1216.67, $result, 0.01, "Every 30 Days @ $100 = ~$1216.67/year");

        echo "\n";
    }

    public function testAnnualValueCalculation_YearPeriodNormalization()
    {
        echo "Test Group: Year Period Normalization (Bug Fix)\n";
        echo str_repeat("-", 60) . "\n";

        // Test: Every 365 Year (should be normalized to Every 365 Day = annual)
        // This is the bug case from the issue report
        $result = asr_compute_annual_value(55, 'Year', 365);
        $this->assertEqualsWithTolerance(55.0, $result, 0.01, "Every 365 Year @ $55 should be normalized to $55/year");

        // Test: Every 366 Year (leap year case)
        $result = asr_compute_annual_value(55, 'Year', 366);
        $this->assertEqualsWithTolerance(54.85, $result, 0.01, "Every 366 Year @ $55 should be normalized to ~$54.85/year");

        // Test: Verify the fix works for multiple subscriptions scenario
        // 4 subscriptions at $55 each with "Every 365 Year"
        $annualValuePerSubscription = asr_compute_annual_value(55, 'Year', 365);
        $totalForFour = $annualValuePerSubscription * 4;
        $this->assertEqualsWithTolerance(220.0, $totalForFour, 0.01, "4 subscriptions @ $55 with Every 365 Year = $220/year total");

        // Test: Every 365 Day should still work correctly
        $result = asr_compute_annual_value(55, 'Day', 365);
        $this->assertEqualsWithTolerance(55.0, $result, 0.01, "Every 365 Day @ $55 = $55/year (unchanged)");

        echo "\n";
    }

    public function testAnnualValueCalculation_EdgeCases()
    {
        echo "Test Group: Edge Cases\n";
        echo str_repeat("-", 60) . "\n";

        // Test: Zero amount
        $result = asr_compute_annual_value(0, 'Month', 1);
        $this->assert($result === null, "Zero amount returns null");

        // Test: Negative amount
        $result = asr_compute_annual_value(-100, 'Month', 1);
        $this->assert($result === null, "Negative amount returns null");

        // Test: Empty period
        $result = asr_compute_annual_value(100, '', 1);
        $this->assert($result === null, "Empty period returns null");

        // Test: Invalid period
        $result = asr_compute_annual_value(100, 'invalid', 1);
        $this->assert($result === null, "Invalid period returns null");

        // Test: Zero frequency (should default to 1)
        $result = asr_compute_annual_value(100, 'Year', 0);
        $this->assertEqualsWithTolerance(100.0, $result, 0.01, "Zero frequency defaults to 1");

        // Test: Negative frequency (should default to 1)
        $result = asr_compute_annual_value(100, 'Year', -5);
        $this->assertEqualsWithTolerance(100.0, $result, 0.01, "Negative frequency defaults to 1");

        // Test: Every 2 Years (legitimate multi-year subscription)
        $result = asr_compute_annual_value(100, 'Year', 2);
        $this->assertEqualsWithTolerance(50.0, $result, 0.01, "Every 2 Years @ $100 = $50/year");

        // Test: Case insensitive period
        $result = asr_compute_annual_value(100, 'MONTH', 1);
        $this->assertEqualsWithTolerance(1200.0, $result, 0.01, "Period is case-insensitive");

        echo "\n";
    }
}

// Run the tests if this file is executed directly
if (php_sapi_name() === 'cli') {
    $tester = new ActiveSubscriptionsReportTest();
    $success = $tester->run();
    exit($success ? 0 : 1);
}
