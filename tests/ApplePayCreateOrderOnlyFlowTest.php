<?php
/**
 * Test: Apple Pay uses confirmPaymentSource flow
 *
 * This test validates that Apple Pay reuses the standard wallet flow that
 * creates the order once and then calls confirmPaymentSource with the token
 * collected in the browser.
 *
 * The test verifies:
 * 1. processWalletConfirmation no longer shortcuts Apple Pay into a special
 *    createOrder-only path
 * 2. There is no code that clears the session order just for Apple Pay
 * 3. confirmPaymentSource remains in place for wallet flows (including Apple Pay)
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
        echo "\n=== Apple Pay confirmPaymentSource Flow Test ===\n\n";

        $this->testNoApplePayBypass();
        $this->testApplePayDoesNotClearOrder();
        $this->testConfirmPaymentSourcePresent();

        $this->printResults();
    }

    /**
     * Test that processWalletConfirmation does NOT have an Apple Pay early return
     */
    private function testNoApplePayBypass(): void
    {
        echo "Test 1: Verify no Apple Pay bypass block exists...\n";

        $content = file_get_contents($this->phpFile);

        // Look for the old apple_pay special-case block that returned early
        $hasApplePayBypass = preg_match('/apple_pay[^\n]+createOrder-only flow/', $content);

        if ($hasApplePayBypass) {
            $this->testResults[] = [
                'name' => 'Apple Pay bypass removed',
                'passed' => false,
                'message' => 'Found legacy Apple Pay create-order-only flow'
            ];
            echo "  ❌ FAIL: Legacy Apple Pay create-order-only flow still present\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay bypass removed',
                'passed' => true,
                'message' => 'Apple Pay uses standard wallet flow'
            ];
            echo "  ✓ PASS: Apple Pay uses standard wallet flow\n";
        }
    }

    /**
     * Test that Apple Pay flow no longer clears previous order
     */
    private function testApplePayDoesNotClearOrder(): void
    {
        echo "Test 2: Verify Apple Pay flow does not clear previous order...\n";

        $content = file_get_contents($this->phpFile);

        $clearsOrder = false;
        $applePos = strpos($content, 'apple_pay');
        if ($applePos !== false) {
            $searchArea = substr($content, $applePos, 500);
            $clearsOrder = strpos($searchArea, "unset(\$_SESSION['PayPalRestful']['Order']") !== false;
        }

        if ($clearsOrder) {
            $this->testResults[] = [
                'name' => 'Apple Pay does not clear order',
                'passed' => false,
                'message' => 'Session order should not be cleared for Apple Pay'
            ];
            echo "  ❌ FAIL: Found session order clearing logic\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay does not clear order',
                'passed' => true,
                'message' => 'No session order clearing logic detected'
            ];
            echo "  ✓ PASS: Session order not cleared by Apple Pay flow\n";
        }
    }

    /**
     * Test that confirmPaymentSource remains in the wallet flow
     */
    private function testConfirmPaymentSourcePresent(): void
    {
        echo "Test 3: Verify confirmPaymentSource call exists...\n";

        $content = file_get_contents($this->phpFile);

        $confirmPos = strpos($content, 'confirmPaymentSource');

        if ($confirmPos !== false) {
            $this->testResults[] = [
                'name' => 'confirmPaymentSource present',
                'passed' => true,
                'message' => 'confirmPaymentSource call present for wallet flows'
            ];
            echo "  ✓ PASS: confirmPaymentSource call present\n";
        } else {
            $this->testResults[] = [
                'name' => 'confirmPaymentSource present',
                'passed' => false,
                'message' => 'confirmPaymentSource call missing for wallet flows'
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
    }
}

// Run the test when executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ApplePayCreateOrderOnlyFlowTest();
    $test->run();
}
