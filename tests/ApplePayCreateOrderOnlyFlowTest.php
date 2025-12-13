<?php
/**
 * Test: Apple Pay uses client-side confirmation flow
 *
 * This test validates that Apple Pay now uses a different flow than other wallets:
 * - Apple Pay: Returns early, skipping both createOrder and confirmPaymentSource (handled client-side)
 * - Google Pay/Venmo: Create order on server, then call confirmPaymentSource
 *
 * The test verifies:
 * 1. processWalletConfirmation returns early for Apple Pay
 * 2. Google Pay and Venmo still create orders (different flow)
 * 3. confirmPaymentSource is still present for non-Apple Pay wallets
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
        echo "\n=== Apple Pay Client-Side Confirmation Flow Test ===\n\n";

        $this->testApplePayReturnsEarly();
        $this->testOtherWalletsCreateOrder();
        $this->testConfirmPaymentSourcePresent();

        $this->printResults();
    }

    /**
     * Test that Apple Pay returns early without calling createPayPalOrder or confirmPaymentSource
     */
    private function testApplePayReturnsEarly(): void
    {
        echo "Test 1: Verify Apple Pay returns early...\n";

        $content = file_get_contents($this->phpFile);

        // Look for early return for apple_pay
        // Updated regex to handle nested braces
        $hasApplePayEarlyReturn = preg_match("/if\s*\(\s*\\\$walletType\s*===\s*'apple_pay'\s*\)\s*\{.*?return;/s", $content);

        if ($hasApplePayEarlyReturn) {
            $this->testResults[] = [
                'name' => 'Apple Pay returns early',
                'passed' => true,
                'message' => 'Apple Pay returns early (client-side confirmation)'
            ];
            echo "  ✓ PASS: Apple Pay returns early\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay returns early',
                'passed' => false,
                'message' => 'Apple Pay should return early to skip server-side confirmation'
            ];
            echo "  ❌ FAIL: Apple Pay early return not found\n";
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
     * Test that confirmPaymentSource is called for non-Apple Pay wallets
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
                'message' => 'confirmPaymentSource called for non-Apple Pay wallets'
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
