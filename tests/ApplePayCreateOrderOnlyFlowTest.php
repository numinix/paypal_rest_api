<?php
/**
 * Test: Apple Pay Create-Order-Only Flow (No confirmPaymentSource)
 *
 * This test validates that Apple Pay payment confirmation now uses a
 * create-order-only flow instead of the confirmPaymentSource endpoint.
 *
 * Background:
 * - PayPal's /v2/checkout/orders/{id}/confirm-payment-source endpoint was
 *   returning INTERNAL_SERVICE_ERROR (500) for Apple Pay
 * - Sending empty payment_source: {} on createOrder caused 422 ORDER_NOT_APPROVED
 *
 * Solution:
 * - Initial order creation (JS): No payment_source sent
 * - After token available: Create fresh order with payment_source.apple_pay.token
 * - Skip confirmPaymentSource entirely for Apple Pay
 * - Go directly to captureOrder
 *
 * The test verifies:
 * 1. processWalletConfirmation detects apple_pay and branches to special flow
 * 2. The special flow clears previous order and creates new one
 * 3. Sets wallet_payment_confirmed flag without calling confirmPaymentSource
 * 4. Returns early (doesn't execute confirmPaymentSource code path)
 * 5. Google Pay and Venmo still use the confirmPaymentSource flow
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
        
        $this->testApplePaySpecialCase();
        $this->testApplePayClearsOrder();
        $this->testApplePayCreatesNewOrder();
        $this->testApplePaySetsConfirmedFlag();
        $this->testApplePaySkipsConfirmPaymentSource();
        $this->testGooglePayStillUsesConfirmFlow();
        
        $this->printResults();
    }
    
    /**
     * Test that processWalletConfirmation has special handling for apple_pay
     */
    private function testApplePaySpecialCase(): void
    {
        echo "Test 1: Verify special apple_pay handling exists...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the apple_pay special case block
        $hasApplePayCheck = preg_match('/if\s*\(\s*\$walletType\s*===\s*[\'"]apple_pay[\'"]\s*\)/', $content);
        
        if ($hasApplePayCheck) {
            $this->testResults[] = [
                'name' => 'Apple Pay special case detection',
                'passed' => true,
                'message' => 'Found apple_pay wallet type check'
            ];
            echo "  ✓ PASS: Apple Pay special case block exists\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay special case detection',
                'passed' => false,
                'message' => 'Could not find apple_pay wallet type check'
            ];
            echo "  ❌ FAIL: No special handling for apple_pay found\n";
        }
    }
    
    /**
     * Test that Apple Pay flow clears previous order
     */
    private function testApplePayClearsOrder(): void
    {
        echo "Test 2: Verify Apple Pay flow clears previous order...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Simple check: look for the unset within a reasonable distance after the apple_pay check
        $applePayPos = strpos($content, "if (\$walletType === 'apple_pay')");
        if ($applePayPos === false) {
            $this->testResults[] = [
                'name' => 'Apple Pay clears previous order',
                'passed' => false,
                'message' => 'Could not find apple_pay check'
            ];
            echo "  ❌ FAIL: Could not find apple_pay check\n";
            return;
        }
        
        // Look for unset within 2000 characters after the apple_pay check
        $searchArea = substr($content, $applePayPos, 2000);
        $clearsOrder = strpos($searchArea, "unset(\$_SESSION['PayPalRestful']['Order'])") !== false;
        
        if ($clearsOrder) {
            $this->testResults[] = [
                'name' => 'Apple Pay clears previous order',
                'passed' => true,
                'message' => 'Order is cleared before creating new one'
            ];
            echo "  ✓ PASS: Previous order is cleared\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay clears previous order',
                'passed' => false,
                'message' => 'Previous order should be cleared'
            ];
            echo "  ❌ FAIL: Previous order not being cleared\n";
        }
    }
    
    /**
     * Test that Apple Pay flow creates a new order
     */
    private function testApplePayCreatesNewOrder(): void
    {
        echo "Test 3: Verify Apple Pay flow creates new order...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Simple check: look for createPayPalOrder within a reasonable distance after the apple_pay check
        $applePayPos = strpos($content, "if (\$walletType === 'apple_pay')");
        if ($applePayPos === false) {
            $this->testResults[] = [
                'name' => 'Apple Pay creates new order',
                'passed' => false,
                'message' => 'Could not find apple_pay check'
            ];
            echo "  ❌ FAIL: Could not find apple_pay check\n";
            return;
        }
        
        // Look for createPayPalOrder within 2000 characters after the apple_pay check
        $searchArea = substr($content, $applePayPos, 2000);
        $createsOrder = strpos($searchArea, 'createPayPalOrder') !== false;
        
        if ($createsOrder) {
            $this->testResults[] = [
                'name' => 'Apple Pay creates new order',
                'passed' => true,
                'message' => 'New order created with token'
            ];
            echo "  ✓ PASS: New order is created\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay creates new order',
                'passed' => false,
                'message' => 'New order should be created'
            ];
            echo "  ❌ FAIL: New order not being created\n";
        }
    }
    
    /**
     * Test that Apple Pay flow sets wallet_payment_confirmed flag
     */
    private function testApplePaySetsConfirmedFlag(): void
    {
        echo "Test 4: Verify Apple Pay flow sets confirmed flag...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Simple check: look for wallet_payment_confirmed within a reasonable distance after the apple_pay check
        $applePayPos = strpos($content, "if (\$walletType === 'apple_pay')");
        if ($applePayPos === false) {
            $this->testResults[] = [
                'name' => 'Apple Pay sets confirmed flag',
                'passed' => false,
                'message' => 'Could not find apple_pay check'
            ];
            echo "  ❌ FAIL: Could not find apple_pay check\n";
            return;
        }
        
        // Look within 2000 characters after the apple_pay check
        $searchArea = substr($content, $applePayPos, 2000);
        $setsFlag = strpos($searchArea, "['wallet_payment_confirmed']") !== false &&
                    strpos($searchArea, "= true") !== false;
        
        if ($setsFlag) {
            $this->testResults[] = [
                'name' => 'Apple Pay sets confirmed flag',
                'passed' => true,
                'message' => 'wallet_payment_confirmed flag is set'
            ];
            echo "  ✓ PASS: Confirmed flag is set\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay sets confirmed flag',
                'passed' => false,
                'message' => 'wallet_payment_confirmed flag should be set'
            ];
            echo "  ❌ FAIL: Confirmed flag not being set\n";
        }
    }
    
    /**
     * Test that Apple Pay flow returns early (skips confirmPaymentSource)
     */
    private function testApplePaySkipsConfirmPaymentSource(): void
    {
        echo "Test 5: Verify Apple Pay flow returns early...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Simple check: look for return statement within a reasonable distance after the apple_pay check
        $applePayPos = strpos($content, "if (\$walletType === 'apple_pay')");
        if ($applePayPos === false) {
            $this->testResults[] = [
                'name' => 'Apple Pay returns early',
                'passed' => false,
                'message' => 'Could not find apple_pay check'
            ];
            echo "  ❌ FAIL: Could not find apple_pay check\n";
            return;
        }
        
        // Look within 2000 characters after the apple_pay check
        $searchArea = substr($content, $applePayPos, 2000);
        $returnsEarly = strpos($searchArea, 'return;') !== false;
        $hasComment = stripos($searchArea, 'skip confirmPaymentSource') !== false;
        
        if ($returnsEarly && $hasComment) {
            $this->testResults[] = [
                'name' => 'Apple Pay returns early',
                'passed' => true,
                'message' => 'Returns early to skip confirmPaymentSource'
            ];
            echo "  ✓ PASS: Returns early, skipping confirmPaymentSource\n";
        } else {
            $this->testResults[] = [
                'name' => 'Apple Pay returns early',
                'passed' => false,
                'message' => 'Should return early to skip confirmPaymentSource'
            ];
            echo "  ❌ FAIL: Not returning early\n";
        }
    }
    
     /**
     * Test that Google Pay still uses confirmPaymentSource flow
     */
    private function testGooglePayStillUsesConfirmFlow(): void
    {
        echo "Test 6: Verify Google Pay still uses confirmPaymentSource...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the Non-Apple Pay section comment
        $hasNonApplePaySection = strpos($content, 'Non-Apple Pay wallets') !== false;
        
        // Find the apple_pay if block position
        $applePayPos = strpos($content, "if (\$walletType === 'apple_pay')");
        if ($applePayPos === false) {
            $this->testResults[] = [
                'name' => 'Non-Apple Pay uses confirmPaymentSource',
                'passed' => false,
                'message' => 'Could not find apple_pay check'
            ];
            echo "  ❌ FAIL: Could not find apple_pay check\n";
            return;
        }
        
        // Look for confirmPaymentSource after the apple_pay block
        $afterApplePay = substr($content, $applePayPos + 1000); // Skip past the apple_pay block
        $callsConfirm = strpos($afterApplePay, 'confirmPaymentSource') !== false;
        
        if ($callsConfirm && $hasNonApplePaySection) {
            $this->testResults[] = [
                'name' => 'Non-Apple Pay uses confirmPaymentSource',
                'passed' => true,
                'message' => 'Google Pay/Venmo still use confirmPaymentSource'
            ];
            echo "  ✓ PASS: Non-Apple Pay wallets still use confirmPaymentSource\n";
        } else {
            $this->testResults[] = [
                'name' => 'Non-Apple Pay uses confirmPaymentSource',
                'passed' => false,
                'message' => 'Non-Apple Pay wallets should still use confirmPaymentSource'
            ];
            echo "  ❌ FAIL: confirmPaymentSource not found for non-Apple Pay\n";
        }
    }
    
    private function printResults(): void
    {
        echo "\n";
        echo "=== Test Summary ===\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
                echo "❌ {$result['name']}: {$result['message']}\n";
            }
        }
        
        echo "\n";
        if ($failed === 0) {
            echo "✓ All tests passed ($passed/$passed)\n";
            exit(0);
        } else {
            echo "❌ Some tests failed ($passed passed, $failed failed)\n";
            exit(1);
        }
    }
}

// Run the test
$test = new ApplePayCreateOrderOnlyFlowTest();
$test->run();
