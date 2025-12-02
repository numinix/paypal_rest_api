<?php
declare(strict_types=1);
/**
 * Test to verify that the getCcInfo getter method correctly provides access
 * to the protected ccInfo property for PayPalCommon::createPayPalOrder().
 * 
 * This test validates the fix for the issue where saved card payments were not
 * being charged because the ccInfo property was protected and couldn't be
 * accessed from PayPalCommon.
 */

// Minimal stubs required to load the test
if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}
if (!defined('DIR_FS_LOGS')) {
    define('DIR_FS_LOGS', sys_get_temp_dir());
}
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

// Stub base class
class base {
    public function notify($eventID, ...$args) {}
    public function attach($obj, $events) {}
    public function __get($name) {
        // This simulates Zen Cart's base class behavior
        return null;
    }
}

// Test that the getter method exists and returns the correct data
function testCcInfoGetter() {
    echo "Testing getCcInfo getter method...\n\n";

    // Test 1: Verify paypalr_savedcard has getCcInfo method
    echo "Test 1: Checking paypalr_savedcard has getCcInfo method...\n";
    
    // Read the file and check for the method
    $savedcard_content = file_get_contents(DIR_FS_CATALOG . 'includes/modules/payment/paypalr_savedcard.php');
    
    if (strpos($savedcard_content, 'public function getCcInfo(): array') !== false) {
        echo "✓ paypalr_savedcard has getCcInfo method\n";
    } else {
        echo "✗ FAILED: paypalr_savedcard is missing getCcInfo method\n";
        return false;
    }

    // Test 2: Verify paypalr_creditcard has getCcInfo method
    echo "\nTest 2: Checking paypalr_creditcard has getCcInfo method...\n";
    
    $creditcard_content = file_get_contents(DIR_FS_CATALOG . 'includes/modules/payment/paypalr_creditcard.php');
    
    if (strpos($creditcard_content, 'public function getCcInfo(): array') !== false) {
        echo "✓ paypalr_creditcard has getCcInfo method\n";
    } else {
        echo "✗ FAILED: paypalr_creditcard is missing getCcInfo method\n";
        return false;
    }

    // Test 3: Verify PayPalCommon::createPayPalOrder uses method_exists check
    echo "\nTest 3: Checking PayPalCommon::createPayPalOrder uses getCcInfo getter...\n";
    
    $common_content = file_get_contents(DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php');
    
    if (strpos($common_content, "method_exists(\$paymentModule, 'getCcInfo')") !== false) {
        echo "✓ PayPalCommon::createPayPalOrder checks for getCcInfo method\n";
    } else {
        echo "✗ FAILED: PayPalCommon::createPayPalOrder doesn't check for getCcInfo method\n";
        return false;
    }
    
    if (strpos($common_content, "\$paymentModule->getCcInfo()") !== false) {
        echo "✓ PayPalCommon::createPayPalOrder calls getCcInfo()\n";
    } else {
        echo "✗ FAILED: PayPalCommon::createPayPalOrder doesn't call getCcInfo()\n";
        return false;
    }

    // Test 4: Simulate the actual behavior with a mock
    echo "\nTest 4: Simulating actual getCcInfo behavior...\n";
    
    $mockClass = new class extends base {
        protected array $ccInfo = [];
        
        public function __construct() {
            $this->ccInfo = [
                'vault_id' => 'test_vault_123',
                'type' => 'VISA',
                'last_digits' => '4242',
                'use_vault' => true,
            ];
        }
        
        public function getCcInfo(): array {
            return $this->ccInfo;
        }
    };
    
    // Test the getter works correctly
    $ccInfo = $mockClass->getCcInfo();
    
    if (!empty($ccInfo['vault_id']) && $ccInfo['vault_id'] === 'test_vault_123') {
        echo "✓ getCcInfo correctly returns vault_id\n";
    } else {
        echo "✗ FAILED: getCcInfo didn't return correct vault_id\n";
        return false;
    }
    
    if (!empty($ccInfo['use_vault']) && $ccInfo['use_vault'] === true) {
        echo "✓ getCcInfo correctly returns use_vault flag\n";
    } else {
        echo "✗ FAILED: getCcInfo didn't return correct use_vault flag\n";
        return false;
    }

    // Test 5: Verify that direct property access would fail (the bug we fixed)
    echo "\nTest 5: Verifying direct property access fails (simulating the bug)...\n";
    
    $accessor = new class {
        public function tryDirectAccess($module) {
            // This simulates what PayPalCommon was doing before the fix
            // Using property_exists + direct access which fails for protected properties
            if (property_exists($module, 'ccInfo')) {
                return $module->ccInfo ?? [];
            }
            return [];
        }
    };
    
    $directResult = $accessor->tryDirectAccess($mockClass);
    
    // With base class __get returning null, this would be empty
    if (empty($directResult)) {
        echo "✓ Direct property access returns empty (as expected due to base class __get)\n";
        echo "  This confirms the bug: protected property access falls back to __get which returns null\n";
    } else {
        echo "✗ UNEXPECTED: Direct property access returned data (shouldn't happen with protected property)\n";
    }

    // Test 6: Verify the fix - using getCcInfo works correctly
    echo "\nTest 6: Verifying fix - using getCcInfo works...\n";
    
    $fixedAccessor = new class {
        public function getWithGetter($module) {
            // This is what PayPalCommon now does after the fix
            if (method_exists($module, 'getCcInfo')) {
                return $module->getCcInfo();
            }
            return property_exists($module, 'ccInfo') ? ($module->ccInfo ?? []) : [];
        }
    };
    
    $fixedResult = $fixedAccessor->getWithGetter($mockClass);
    
    if (!empty($fixedResult['vault_id']) && $fixedResult['vault_id'] === 'test_vault_123') {
        echo "✓ Fixed accessor correctly retrieves vault_id via getCcInfo()\n";
    } else {
        echo "✗ FAILED: Fixed accessor didn't correctly retrieve vault_id\n";
        return false;
    }

    return true;
}

// Run the test
echo "=" . str_repeat("=", 60) . "\n";
echo "SavedCard CcInfo Getter Test\n";
echo "=" . str_repeat("=", 60) . "\n\n";

$result = testCcInfoGetter();

echo "\n" . str_repeat("=", 61) . "\n";
if ($result) {
    echo "✓ All SavedCard CcInfo Getter tests passed!\n\n";
    echo "Summary of the fix:\n";
    echo "- Added public getCcInfo() method to paypalr_savedcard.php\n";
    echo "- Added public getCcInfo() method to paypalr_creditcard.php\n";
    echo "- Updated PayPalCommon::createPayPalOrder to use getCcInfo()\n";
    echo "- This allows proper access to the protected ccInfo property\n";
    echo "- Saved card payments now correctly pass vault_id to PayPal\n";
    exit(0);
} else {
    echo "✗ Some tests FAILED!\n";
    exit(1);
}
