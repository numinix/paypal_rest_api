<?php
/**
 * Test to verify that PayPalRestful\Compatibility\Language does not create a global 'language' class alias
 * This addresses the fatal error in Zen Cart 2.1.0 where Product::initLanguages() calls 
 * get_language_list() on the wrong class.
 *
 * Issue: Call to undefined method PayPalRestful\Compatibility\Language::get_language_list()
 * 
 * The PayPalRestful\Compatibility\Language class is a static utility for loading language files
 * and should NOT be aliased as the global 'language' class. Only LanguageShim should create
 * that alias when Zen Cart's own language class is unavailable.
 */
declare(strict_types=1);

$testPassed = true;
$errors = [];

echo "Testing Language Class Alias Behavior\n";
echo "======================================\n\n";

// Test 1: Verify PayPalRestful\Compatibility\Language does not create class alias
echo "Test 1: Loading PayPalRestful\\Compatibility\\Language should not create 'language' alias...\n";

// Make sure 'language' class doesn't exist yet
if (class_exists('language', false)) {
    echo "  ⚠ Warning: 'language' class already exists, skipping this test\n";
} else {
    // Load the Language class
    require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/Compatibility/Language.php';
    
    // Check if it created a global 'language' alias
    if (class_exists('language', false)) {
        $errors[] = "PayPalRestful\\Compatibility\\Language incorrectly created 'language' class alias";
        echo "  ✗ FAILED: 'language' class alias was created (this causes the bug)\n";
        $testPassed = false;
    } else {
        echo "  ✓ No 'language' alias created by PayPalRestful\\Compatibility\\Language\n";
    }
}

// Test 2: Verify LanguageShim DOES create the alias when needed
echo "\nTest 2: Loading LanguageStub.php (contains LanguageShim) should create 'language' alias when not already defined...\n";

if (class_exists('language', false)) {
    echo "  ⚠ Warning: 'language' class already exists, cannot test LanguageShim alias creation\n";
} else {
    // Load LanguageStub.php which contains the LanguageShim class
    require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageStub.php';
    
    // Check if it created the alias
    if (class_exists('language', false)) {
        echo "  ✓ 'language' alias correctly created by LanguageShim\n";
        
        // Test 3: Verify the aliased class has get_language_list method
        echo "\nTest 3: Aliased 'language' class should have get_language_list() method...\n";
        if (method_exists('language', 'get_language_list')) {
            echo "  ✓ 'language' class has get_language_list() method\n";
        } else {
            $errors[] = "Aliased 'language' class missing get_language_list() method";
            echo "  ✗ FAILED: 'language' class missing get_language_list() method\n";
            $testPassed = false;
        }
    } else {
        $errors[] = "LanguageShim did not create 'language' class alias";
        echo "  ✗ FAILED: LanguageShim should create 'language' alias\n";
        $testPassed = false;
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
if ($testPassed) {
    echo "✓ ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "✗ TESTS FAILED\n\n";
    echo "Errors:\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". " . $error . "\n";
    }
    exit(1);
}
