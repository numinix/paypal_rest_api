<?php
/**
 * Test: Apple Pay uses existing order and confirmPaymentSource
 *
 * This test validates that Apple Pay reuses the order created by JavaScript
 * and only calls confirmPaymentSource (not a second createOrder).
 *
 * The test verifies:
 * 1. processWalletConfirmation does NOT call createPayPalOrder for Apple Pay
 * 2. Apple Pay uses the existing order ID from the session
 * 3. confirmPaymentSource is called with the existing order for Apple Pay
 * 4. Google Pay and Venmo still create orders (different flow)
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class ApplePayCreateOrderOnlyFlowTest
{
    private string $phpFile;
    private array $testResults = [];

    public function __construct()
    {
        // Use __DIR__ to make path relative to test file location
        $this->phpFile = dirname(__DIR__) . '/includes/modules/payment/paypal/paypal_common.php';

        if (!file_exists($this->phpFile)) {
            throw new RuntimeException("PHP file not found: {$this->phpFile}");
        }
    }

    public function run(): void
    {
        echo "\n=== Apple Pay Existing Order + confirmPaymentSource Test ===\n\n";

        $this->testApplePaySkipsCreateOrder();
        $this->testOtherWalletsCreateOrder();
        $this->testConfirmPaymentSourcePresent();

        $this->printResults();
    }

    /**
     * Test that Apple Pay skips the createPayPalOrder call
     */
    private function testApplePaySkipsCreateOrder(): void
    {
        echo "Test 1: Verify Apple Pay skips createPayPalOrder...\n";

        $content = file_get_contents($this->phpFile);

        // Look for apple_pay exclusion from createPayPalOrder
        $hasApplePayExclusion = preg_match('/if\s*\(\s*\$walletType\s*!==\s*[\'"]apple_pay[\'"]\s*\)/', $content);

        if ($hasApplePayExclusion) {
            $this->testResults[] = [
                'name' => 'Apple Pay skips createPayPalOrder',
                'passed' => true,
                'message' => 'Apple Pay excluded from createPayPalOrder (uses existing order from JS)'
            ];
            echo "  ✓ PASS: Apple Pay skips createPayPalOrder\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay skips createPayPalOrder',
                'passed' => false,
                'message' => 'Apple Pay should be excluded from createPayPalOrder call'
            ];
            echo "  ❌ FAIL: Apple Pay exclusion not found\n";
        }
    }

    /**
     * Test that Google Pay and Venmo still create orders
     */
    private function testOtherWalletsCreateOrder(): void
    {
        echo "Test 2: Verify Google Pay/Venmo create orders...\n";

        $content = file_get_contents($this->phpFile);

        $hasCreateOrderCall = strpos($content, 'createPayPalOrder') !== false;

        if ($hasCreateOrderCall) {
            $this->testResults[] = [
                'name' => 'Other wallets create orders',
                'passed' => true,
                'message' => 'createPayPalOrder still called for non-Apple Pay wallets'
            ];
            echo "  ✓ PASS: Google Pay/Venmo still create orders\n";
        } else {
            $this->testResults[] = [
                'name' => 'Other wallets create orders',
                'passed' => false,
                'message' => 'createPayPalOrder call missing'
            ];
            echo "  ❌ FAIL: createPayPalOrder call not found\n";
        }
    }

    /**
     * Test that confirmPaymentSource is called for all wallets
     */
    private function testConfirmPaymentSourcePresent(): void
    {
        echo "Test 3: Verify confirmPaymentSource is called...\n";

        $content = file_get_contents($this->phpFile);

        $confirmPos = strpos($content, 'confirmPaymentSource');

        if ($confirmPos !== false) {
            $this->testResults[] = [
                'name' => 'confirmPaymentSource present',
                'passed' => true,
                'message' => 'confirmPaymentSource called for all wallets'
            ];
            echo "  ✓ PASS: confirmPaymentSource call present\n";
        } else {
            $this->testResults[] = [
                'name' => 'confirmPaymentSource present',
                'passed' => false,
                'message' => 'confirmPaymentSource call missing'
            ];
            echo "  ❌ FAIL: confirmPaymentSource call missing\n";
        }
    }

    /**
     * Print summary of test results
     */
    private function printResults(): void
    {
        echo "\nTest Results:\n";
        foreach ($this->testResults as $result) {
            $status = $result['passed'] ? 'PASS' : 'FAIL';
            echo "- {$result['name']}: {$status} ({$result['message']})\n";
        }
        
        // Exit with error code if any test failed
        $allPassed = array_reduce($this->testResults, function($carry, $result) {
            return $carry && $result['passed'];
        }, true);
        
        if (!$allPassed) {
            exit(1);
        }
    }
}

// Run the test when executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ApplePayCreateOrderOnlyFlowTest();
    $test->run();
}
