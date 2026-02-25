<?php
/**
 * Test to verify Address::get() and CountryCodes::convertCountryCode() handle null country codes gracefully
 * This addresses the TypeError that occurs during Google Pay wallet initialization when delivery
 * address is not yet populated.
 *
 * Issue: PayPalAdvancedCheckout\Api\Data\CountryCodes::convertCountryCode(): Argument #1 ($country_code) 
 * must be of type string, null given
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Address Null Country Code Handling\n";
echo "===========================================\n\n";

require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/Data/CountryCodes.php';
require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Address.php';

use PayPalAdvancedCheckout\Api\Data\CountryCodes;
use PayPalAdvancedCheckout\Zc2Pp\Address;

// Test 1: convertCountryCode handles null input gracefully
echo "Test 1: convertCountryCode handles null input...\n";
try {
    $result = CountryCodes::convertCountryCode(null);
    if ($result === '') {
        echo "  ✓ convertCountryCode returns empty string for null input\n";
    } else {
        $errors[] = "convertCountryCode should return empty string for null, got: " . var_export($result, true);
        echo "  ✗ FAILED: Expected empty string, got: " . var_export($result, true) . "\n";
        $testPassed = false;
    }
} catch (TypeError $e) {
    $errors[] = "convertCountryCode threw TypeError for null input: " . $e->getMessage();
    echo "  ✗ FAILED: TypeError thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 2: convertCountryCode handles empty string input
echo "Test 2: convertCountryCode handles empty string input...\n";
try {
    $result = CountryCodes::convertCountryCode('');
    if ($result === '') {
        echo "  ✓ convertCountryCode returns empty string for empty string input\n";
    } else {
        $errors[] = "convertCountryCode should return empty string for empty string, got: " . var_export($result, true);
        echo "  ✗ FAILED: Expected empty string, got: " . var_export($result, true) . "\n";
        $testPassed = false;
    }
} catch (TypeError $e) {
    $errors[] = "convertCountryCode threw TypeError for empty string: " . $e->getMessage();
    echo "  ✗ FAILED: TypeError thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 3: convertCountryCode still works with valid country codes
echo "Test 3: convertCountryCode handles valid country code (US)...\n";
try {
    $result = CountryCodes::convertCountryCode('US');
    if ($result === 'US') {
        echo "  ✓ convertCountryCode returns US for valid US code\n";
    } else {
        $errors[] = "convertCountryCode should return 'US' for US input, got: " . var_export($result, true);
        echo "  ✗ FAILED: Expected 'US', got: " . var_export($result, true) . "\n";
        $testPassed = false;
    }
} catch (Exception $e) {
    $errors[] = "convertCountryCode threw exception for valid code: " . $e->getMessage();
    echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 4: convertCountryCode handles the special case of CH -> C2 (Zen Cart uses CH for China)
echo "Test 4: convertCountryCode handles China special case (Zen Cart CH -> PayPal C2)...\n";
try {
    $result = CountryCodes::convertCountryCode('CH');
    if ($result === 'C2') {
        echo "  ✓ convertCountryCode converts CH to C2\n";
    } else {
        $errors[] = "convertCountryCode should convert CH to C2, got: " . var_export($result, true);
        echo "  ✗ FAILED: Expected 'C2', got: " . var_export($result, true) . "\n";
        $testPassed = false;
    }
} catch (Exception $e) {
    $errors[] = "convertCountryCode threw exception for CH: " . $e->getMessage();
    echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 5: Address::get() handles null country code without throwing TypeError
echo "Test 5: Address::get() handles null country code...\n";
try {
    $incomplete_address = [
        'street_address' => '123 Main St',
        'city' => 'Test City',
        'state' => 'Test State',
        'state_code' => 'TS',
        'postcode' => '12345',
        'country' => [
            'iso_code_2' => null,  // This is what happens before user enters address
        ],
    ];

    $result = Address::get($incomplete_address);
    
    if (is_array($result) && isset($result['country_code']) && $result['country_code'] === '') {
        echo "  ✓ Address::get() returns empty country_code for null input\n";
    } else {
        $errors[] = "Address::get() should return array with empty country_code, got: " . var_export($result, true);
        echo "  ✗ FAILED: Expected array with empty country_code\n";
        $testPassed = false;
    }
} catch (TypeError $e) {
    $errors[] = "Address::get() threw TypeError for null country code: " . $e->getMessage();
    echo "  ✗ FAILED: TypeError thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 6: Address::get() handles missing country array without errors
echo "Test 6: Address::get() handles missing country.iso_code_2...\n";
try {
    $address_no_country = [
        'street_address' => '123 Main St',
        'city' => 'Test City',
        'state' => 'Test State',
        'state_code' => 'TS',
        'postcode' => '12345',
        'country' => [],  // Empty country array
    ];

    $result = Address::get($address_no_country);
    
    if (is_array($result) && isset($result['country_code']) && $result['country_code'] === '') {
        echo "  ✓ Address::get() returns empty country_code when iso_code_2 is missing\n";
    } else {
        $errors[] = "Address::get() should return array with empty country_code when iso_code_2 missing";
        echo "  ✗ FAILED: Expected array with empty country_code\n";
        $testPassed = false;
    }
} catch (Exception $e) {
    $errors[] = "Address::get() threw exception for missing iso_code_2: " . $e->getMessage();
    echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Test 7: Address::get() still works properly with complete valid address
echo "Test 7: Address::get() handles complete address correctly...\n";
try {
    $complete_address = [
        'street_address' => '123 Main St',
        'city' => 'New York',
        'state' => 'New York',
        'state_code' => 'NY',
        'postcode' => '10001',
        'country' => [
            'iso_code_2' => 'US',
            'iso_code_3' => 'USA',
            'title' => 'United States',
        ],
    ];

    $result = Address::get($complete_address);
    
    $allCorrect = true;
    if ($result['address_line_1'] !== '123 Main St') {
        echo "  ✗ address_line_1 mismatch\n";
        $allCorrect = false;
    }
    if ($result['admin_area_2'] !== 'New York') {
        echo "  ✗ admin_area_2 mismatch\n";
        $allCorrect = false;
    }
    if ($result['admin_area_1'] !== 'NY') {
        echo "  ✗ admin_area_1 mismatch\n";
        $allCorrect = false;
    }
    if ($result['postal_code'] !== '10001') {
        echo "  ✗ postal_code mismatch\n";
        $allCorrect = false;
    }
    if ($result['country_code'] !== 'US') {
        echo "  ✗ country_code mismatch\n";
        $allCorrect = false;
    }
    
    if ($allCorrect) {
        echo "  ✓ Address::get() correctly processes complete address\n";
    } else {
        $errors[] = "Address::get() did not correctly process complete address";
        $testPassed = false;
    }
} catch (Exception $e) {
    $errors[] = "Address::get() threw exception for complete address: " . $e->getMessage();
    echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    $testPassed = false;
}

// Summary
echo "\n";
echo "===========================================\n";
if ($testPassed) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
