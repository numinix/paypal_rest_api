<?php
/**
 * Test to verify that Helpers::convertPayPalDatePay2Db handles null values correctly.
 * 
 * This test verifies the type signature change that allows null values to be passed
 * to convertPayPalDatePay2Db without causing a TypeError.
 */
declare(strict_types=1);

// Set up minimal constants required by the autoloader
if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}

// Create a minimal PSR-4 autoloader for PayPalAdvancedCheckout namespace
spl_autoload_register(function ($class) {
    $prefix = 'PayPalAdvancedCheckout\\';
    $base_dir = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "\n=== Testing Helpers::convertPayPalDatePay2Db Type Signature ===\n\n";

// Test 1: Verify the method signature accepts nullable string
echo "Test 1: Verify method signature accepts nullable string parameter\n";
$reflectionClass = new ReflectionClass('PayPalAdvancedCheckout\Common\Helpers');
$reflectionMethod = $reflectionClass->getMethod('convertPayPalDatePay2Db');
$parameters = $reflectionMethod->getParameters();

if (count($parameters) !== 1) {
    echo "✗ FAIL: Expected 1 parameter, got " . count($parameters) . "\n";
    exit(1);
}

$param = $parameters[0];
$paramType = $param->getType();

if ($paramType === null) {
    echo "✗ FAIL: Parameter should have a type hint\n";
    exit(1);
}

if (!$paramType->allowsNull()) {
    echo "✗ FAIL: Parameter type should allow null\n";
    exit(1);
}

if ($paramType->getName() !== 'string') {
    echo "✗ FAIL: Parameter type should be string, got " . $paramType->getName() . "\n";
    exit(1);
}

echo "✓ PASS: Method signature is convertPayPalDatePay2Db(?string)\n\n";

// Test 2: Verify the return type allows null
echo "Test 2: Verify return type allows null\n";
$returnType = $reflectionMethod->getReturnType();

if ($returnType === null) {
    echo "✗ FAIL: Method should have a return type hint\n";
    exit(1);
}

if (!$returnType->allowsNull()) {
    echo "✗ FAIL: Return type should allow null\n";
    exit(1);
}

if ($returnType->getName() !== 'string') {
    echo "✗ FAIL: Return type should be string, got " . $returnType->getName() . "\n";
    exit(1);
}

echo "✓ PASS: Return type is ?string\n\n";

// Test 3: Verify the method can be called with null without TypeError
echo "Test 3: Verify method can be called with null without TypeError\n";
try {
    $result = \PayPalAdvancedCheckout\Common\Helpers::convertPayPalDatePay2Db(null);
    echo "✓ PASS: Method accepts null parameter without TypeError\n";
    
    if ($result !== null) {
        echo "✗ FAIL: Expected null return value for null input, got: " . var_export($result, true) . "\n";
        exit(1);
    }
    echo "✓ PASS: Method returns null for null input\n\n";
} catch (TypeError $e) {
    echo "✗ FAIL: TypeError thrown when calling with null: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Verify the method can be called with empty string
echo "Test 4: Verify method handles empty string correctly\n";
try {
    $result = \PayPalAdvancedCheckout\Common\Helpers::convertPayPalDatePay2Db('');
    echo "✓ PASS: Method accepts empty string without error\n";
    
    if ($result !== null) {
        echo "✗ FAIL: Expected null return value for empty string, got: " . var_export($result, true) . "\n";
        exit(1);
    }
    echo "✓ PASS: Method returns null for empty string\n\n";
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Verify the method still works with valid date strings
echo "Test 5: Verify method still works with valid date strings\n";
try {
    $result = \PayPalAdvancedCheckout\Common\Helpers::convertPayPalDatePay2Db('2024-11-21T17:30:45Z');
    
    if ($result === null) {
        echo "✗ FAIL: Expected non-null return value for valid date string\n";
        exit(1);
    }
    
    // Verify result is a valid datetime string
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result)) {
        echo "✗ FAIL: Return value should be in 'Y-m-d H:i:s' format, got: $result\n";
        exit(1);
    }
    
    echo "✓ PASS: Method returns valid datetime string: $result\n\n";
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Test Summary ===\n";
echo "All tests PASSED ✓\n";
echo "\nThe fix successfully allows null values to be passed to convertPayPalDatePay2Db\n";
echo "without causing a TypeError, which resolves the reported issue.\n";
exit(0);
