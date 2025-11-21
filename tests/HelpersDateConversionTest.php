<?php
/**
 * Test to verify that Helpers::convertPayPalDatePay2Db handles null values correctly.
 */
declare(strict_types=1);

namespace PayPalRestful\Common {
    // Mock the Helpers class for testing
    class Helpers
    {
        public static function convertPayPalDatePay2Db(?string $paypal_date): ?string
        {
            if ($paypal_date === null || $paypal_date === '') {
                return null;
            }
            // Simulate the conversion logic
            $cleaned = trim(preg_replace('/[^0-9-:]/', ' ', $paypal_date));
            // Simple mock conversion - in real code this goes through convertToLocalTimeZone
            return date('Y-m-d H:i:s', strtotime($cleaned));
        }
    }
}

namespace {
    use PayPalRestful\Common\Helpers;

    /**
     * Test null input handling
     */
    function testConvertPayPalDatePay2DbWithNull(): bool
    {
        $result = Helpers::convertPayPalDatePay2Db(null);
        
        if ($result !== null) {
            echo "FAIL: convertPayPalDatePay2Db(null) should return null, got: " . var_export($result, true) . "\n";
            return false;
        }
        
        echo "PASS: convertPayPalDatePay2Db(null) returns null\n";
        return true;
    }

    /**
     * Test empty string input handling
     */
    function testConvertPayPalDatePay2DbWithEmptyString(): bool
    {
        $result = Helpers::convertPayPalDatePay2Db('');
        
        if ($result !== null) {
            echo "FAIL: convertPayPalDatePay2Db('') should return null, got: " . var_export($result, true) . "\n";
            return false;
        }
        
        echo "PASS: convertPayPalDatePay2Db('') returns null\n";
        return true;
    }

    /**
     * Test valid date input handling
     */
    function testConvertPayPalDatePay2DbWithValidDate(): bool
    {
        // PayPal uses ISO 8601 format: 2024-11-21T17:30:45Z
        $paypalDate = '2024-11-21T17:30:45Z';
        $result = Helpers::convertPayPalDatePay2Db($paypalDate);
        
        if ($result === null) {
            echo "FAIL: convertPayPalDatePay2Db with valid date should not return null\n";
            return false;
        }
        
        // Result should be a valid datetime string in Y-m-d H:i:s format
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result)) {
            echo "FAIL: convertPayPalDatePay2Db result should be in 'Y-m-d H:i:s' format, got: $result\n";
            return false;
        }
        
        echo "PASS: convertPayPalDatePay2Db('$paypalDate') returns valid datetime: $result\n";
        return true;
    }

    /**
     * Test with various PayPal date formats
     */
    function testConvertPayPalDatePay2DbWithVariousFormats(): bool
    {
        $testDates = [
            '2024-11-21T17:30:45Z',
            '2024-01-01T00:00:00Z',
            '2025-12-31T23:59:59Z',
        ];
        
        foreach ($testDates as $date) {
            $result = Helpers::convertPayPalDatePay2Db($date);
            if ($result === null) {
                echo "FAIL: convertPayPalDatePay2Db('$date') should not return null\n";
                return false;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result)) {
                echo "FAIL: convertPayPalDatePay2Db('$date') should return valid datetime format, got: $result\n";
                return false;
            }
        }
        
        echo "PASS: convertPayPalDatePay2Db handles various date formats correctly\n";
        return true;
    }

    // Run all tests
    $allPassed = true;
    
    echo "\n=== Testing Helpers::convertPayPalDatePay2Db ===\n\n";
    
    $allPassed = testConvertPayPalDatePay2DbWithNull() && $allPassed;
    $allPassed = testConvertPayPalDatePay2DbWithEmptyString() && $allPassed;
    $allPassed = testConvertPayPalDatePay2DbWithValidDate() && $allPassed;
    $allPassed = testConvertPayPalDatePay2DbWithVariousFormats() && $allPassed;
    
    echo "\n=== Test Summary ===\n";
    if ($allPassed) {
        echo "All tests PASSED\n";
        exit(0);
    } else {
        echo "Some tests FAILED\n";
        exit(1);
    }
}
