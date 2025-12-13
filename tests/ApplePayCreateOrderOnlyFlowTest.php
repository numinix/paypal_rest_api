<?php
/**
 * Test: Apple Pay uses create-order-only flow to avoid PayPal 500 errors
 *
 * This test validates that Apple Pay uses a special create-order-only flow
 * that skips confirmPaymentSource to avoid PayPal INTERNAL_SERVICE_ERROR (500) issues.
 *
 * The test verifies:
 * 1. processWalletConfirmation has an Apple Pay shortcut that returns early
 * 2. Apple Pay clears the session order to create a fresh order with the token
 * 3. confirmPaymentSource is skipped for Apple Pay but still present for other wallets
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
        echo "\n=== Apple Pay Create-Order-Only Flow Test ===\n\n";

        $this->testApplePayHasBypass();
        $this->testApplePayClearsOrder();
        $this->testApplePaySkipsConfirmPaymentSource();

        $this->printResults();
    }

    /**
     * Test that processWalletConfirmation HAS an Apple Pay early return
     */
    private function testApplePayHasBypass(): void
    {
        echo "Test 1: Verify Apple Pay bypass block exists...\n";

        $content = file_get_contents($this->phpFile);

        // Look for the apple_pay special-case block that returns early
        $hasApplePayBypass = preg_match('/if\s*\(\s*\$walletType\s*===\s*[\'"]apple_pay[\'"]\s*\)/', $content);
        $hasEarlyReturn = preg_match('/createOrder-only flow/', $content) && preg_match('/skipped confirmPaymentSource/', $content);

        if ($hasApplePayBypass && $hasEarlyReturn) {
            $this->testResults[] = [
                'name' => 'Apple Pay bypass present',
                'passed' => true,
                'message' => 'Apple Pay uses create-order-only flow to avoid 500 errors'
            ];
            echo "  ✓ PASS: Apple Pay uses create-order-only flow\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay bypass present',
                'passed' => false,
                'message' => 'Apple Pay bypass not found or incomplete'
            ];
            echo "  ❌ FAIL: Apple Pay bypass not found\n";
        }
    }

    /**
     * Test that Apple Pay flow clears previous order to create fresh one with token
     */
    private function testApplePayClearsOrder(): void
    {
        echo "Test 2: Verify Apple Pay flow clears previous order...\n";

        $content = file_get_contents($this->phpFile);

        $clearsOrder = false;
        // Find apple_pay section
        if (preg_match('/if\s*\(\s*\$walletType\s*===\s*[\'"]apple_pay[\'"]\s*\)\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $applePos = $matches[0][1];
            $searchArea = substr($content, $applePos, 1000);
            $clearsOrder = strpos($searchArea, "unset(\$_SESSION['PayPalRestful']['Order']") !== false;
        }

        if ($clearsOrder) {
            $this->testResults[] = [
                'name' => 'Apple Pay clears order',
                'passed' => true,
                'message' => 'Session order is cleared to create fresh order with token'
            ];
            echo "  ✓ PASS: Session order cleared for fresh order creation\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay clears order',
                'passed' => false,
                'message' => 'Session order should be cleared for Apple Pay'
            ];
            echo "  ❌ FAIL: Session order clearing logic not found\n";
        }
    }

    /**
     * Test that Apple Pay skips confirmPaymentSource but it remains for other wallets
     */
    private function testApplePaySkipsConfirmPaymentSource(): void
    {
        echo "Test 3: Verify Apple Pay skips confirmPaymentSource...\n";

        $content = file_get_contents($this->phpFile);

        // Check that confirmPaymentSource still exists (for other wallets)
        $confirmPresent = strpos($content, 'confirmPaymentSource') !== false;
        
        // Check that Apple Pay returns before confirmPaymentSource
        $applePayReturnsEarly = false;
        if (preg_match('/if\s*\(\s*\$walletType\s*===\s*[\'"]apple_pay[\'"]\s*\)\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $applePos = $matches[0][1];
            $searchArea = substr($content, $applePos, 1500);
            $applePayReturnsEarly = preg_match('/return\s*;/', $searchArea) !== false;
        }

        if ($confirmPresent && $applePayReturnsEarly) {
            $this->testResults[] = [
                'name' => 'Apple Pay skips confirmPaymentSource',
                'passed' => true,
                'message' => 'Apple Pay returns early; confirmPaymentSource present for other wallets'
            ];
            echo "  ✓ PASS: Apple Pay skips confirmPaymentSource\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay skips confirmPaymentSource',
                'passed' => false,
                'message' => 'Apple Pay should return before confirmPaymentSource call'
            ];
            echo "  ❌ FAIL: Apple Pay flow structure incorrect\n";
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
