<?php
/**
 * Test: Google Pay Client-Side Confirmation
 *
 * This test validates that the Google Pay module correctly handles payment confirmation
 * on the client side using googlepay.confirmOrder(), matching the Apple Pay pattern.
 *
 * Background:
 * - Google Pay was failing with INTERNAL_SERVER_ERROR during server-side confirmPaymentSource
 * - Following the Apple Pay fix pattern, Google Pay now uses client-side confirmation
 * - This matches PayPal's recommended Advanced Integration pattern for Google Pay
 *
 * The test verifies that the JavaScript code:
 * 1. DOES call googlepay.confirmOrder() after loadPaymentData
 * 2. Returns a simplified payload with {orderID, confirmed: true, wallet}
 * 3. The PHP pre_confirmation_check skips confirmPaymentSource when confirmed: true
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class GooglePayClientSideConfirmationTest
{
    private string $jsFile;
    private string $phpFile;
    private array $testResults = [];
    
    public function __construct()
    {
        // Use __DIR__ to make path relative to test file location
        $this->jsFile = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/jquery.paypalac.googlepay.js';
        $this->phpFile = dirname(__DIR__) . '/includes/modules/payment/paypal/paypal_common.php';
        
        if (!file_exists($this->jsFile)) {
            throw new RuntimeException("JavaScript file not found: {$this->jsFile}");
        }
        
        if (!file_exists($this->phpFile)) {
            throw new RuntimeException("PHP file not found: {$this->phpFile}");
        }
    }
    
    public function run(): void
    {
        echo "\n=== Google Pay Client-Side Confirmation Test ===\n\n";
        
        $this->testClientSideConfirmOrder();
        $this->testPayloadContainsConfirmed();
        $this->testServerSkipsConfirmation();
        $this->testConsistentWithApplePay();
        
        $this->printResults();
    }
    
    /**
     * Test that confirmOrder IS called after loadPaymentData
     */
    private function testClientSideConfirmOrder(): void
    {
        echo "Test 1: Verify confirmOrder IS called after loadPaymentData...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the loadPaymentData callback
        $pattern = '/loadPaymentData\([^)]+\)\.then\(function\s*\([^)]*\)\s*\{(.*?)\}\);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $callback = $matches[1];
            
            // Check if confirmOrder is called within this callback
            $hasConfirmOrder = strpos($callback, 'googlepay.confirmOrder') !== false;
            
            if ($hasConfirmOrder) {
                $this->testResults[] = [
                    'name' => 'Client-side confirmOrder',
                    'passed' => true,
                    'message' => 'confirmOrder is correctly called after loadPaymentData'
                ];
                echo "  ✓ PASS: confirmOrder called on client side\n";
            } else {
                $this->testResults[] = [
                    'name' => 'Client-side confirmOrder',
                    'passed' => false,
                    'message' => 'confirmOrder SHOULD be called after loadPaymentData'
                ];
                echo "  ❌ FAIL: confirmOrder is NOT being called on client side\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'Client-side confirmOrder',
                'passed' => false,
                'message' => 'Could not find loadPaymentData callback'
            ];
            echo "  ❌ FAIL: Could not find loadPaymentData callback\n";
        }
    }
    
    /**
     * Test that the payload includes confirmed: true
     */
    private function testPayloadContainsConfirmed(): void
    {
        echo "Test 2: Verify payload includes confirmed: true...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Look for confirmed: true being added to payload
        $hasConfirmed = strpos($content, 'confirmed: true') !== false;
        
        if ($hasConfirmed) {
            $this->testResults[] = [
                'name' => 'Payload contains confirmed',
                'passed' => true,
                'message' => 'confirmed: true is included in the payload'
            ];
            echo "  ✓ PASS: Payload includes confirmed: true\n";
        } else {
            $this->testResults[] = [
                'name' => 'Payload contains confirmed',
                'passed' => false,
                'message' => 'confirmed: true is missing from payload'
            ];
            echo "  ❌ FAIL: confirmed: true not in payload\n";
        }
    }
    
    /**
     * Test that the PHP processWalletConfirmation skips confirmPaymentSource when confirmed
     */
    private function testServerSkipsConfirmation(): void
    {
        echo "Test 3: Verify PHP skips server-side confirmation when confirmed...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the logic that checks for confirmed: true and skips confirmPaymentSource
        $hasConfirmedCheck = strpos($content, "isset(\$payload['confirmed']) && \$payload['confirmed'] === true") !== false;
        $hasGooglePayInCondition = preg_match('/if\s*\(\$walletType\s*===\s*[\'"]apple_pay[\'"]\s*\|\|\s*\$walletType\s*===\s*[\'"]google_pay[\'"]\)/', $content);
        
        if ($hasConfirmedCheck && $hasGooglePayInCondition) {
            $this->testResults[] = [
                'name' => 'Server skips confirmation',
                'passed' => true,
                'message' => 'PHP correctly skips confirmPaymentSource when confirmed: true for Google Pay'
            ];
            echo "  ✓ PASS: Server skips confirmPaymentSource when already confirmed\n";
        } else {
            $this->testResults[] = [
                'name' => 'Server skips confirmation',
                'passed' => false,
                'message' => 'Server does not properly check for confirmed: true (has_check=' . ($hasConfirmedCheck ? 'yes' : 'no') . ', has_google_pay=' . ($hasGooglePayInCondition ? 'yes' : 'no') . ')'
            ];
            echo "  ❌ FAIL: Server does not properly skip confirmation\n";
        }
    }
    
    /**
     * Test that Google Pay follows the same pattern as Apple Pay
     */
    private function testConsistentWithApplePay(): void
    {
        echo "Test 4: Verify Google Pay follows Apple Pay pattern...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Check that Google Pay DOES call confirmOrder after getting payment data
        $hasConfirmOrderInCallback = preg_match('/loadPaymentData.*\.then.*googlepay\.confirmOrder/s', $content);
        
        // Check that it sets the payload and dispatches event
        $setsPayload = strpos($content, 'setGooglePayPayload(payload)') !== false;
        $dispatchesEvent = strpos($content, 'paypalac:googlepay:payload') !== false;
        
        if ($hasConfirmOrderInCallback && $setsPayload && $dispatchesEvent) {
            $this->testResults[] = [
                'name' => 'Consistent with Apple Pay',
                'passed' => true,
                'message' => 'Google Pay follows the same client-side confirmation pattern as Apple Pay'
            ];
            echo "  ✓ PASS: Google Pay follows Apple Pay pattern\n";
        } else {
            $this->testResults[] = [
                'name' => 'Consistent with Apple Pay',
                'passed' => false,
                'message' => 'Google Pay does not follow Apple Pay pattern: has_confirm=' . ($hasConfirmOrderInCallback ? 'yes' : 'no') . ', sets_payload=' . ($setsPayload ? 'yes' : 'no') . ', dispatches=' . ($dispatchesEvent ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: Google Pay does not follow Apple Pay pattern\n";
        }
    }
    
    private function printResults(): void
    {
        echo "\n=== Test Results ===\n\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passed++;
                echo "✓ {$result['name']}: {$result['message']}\n";
            } else {
                $failed++;
                echo "❌ {$result['name']}: {$result['message']}\n";
            }
        }
        
        echo "\n";
        echo "Total: " . count($this->testResults) . " tests\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "\n";
        
        if ($failed === 0) {
            echo "🎉 All tests passed!\n\n";
            exit(0);
        } else {
            echo "⚠️  Some tests failed.\n\n";
            exit(1);
        }
    }
}

// Run the test
$test = new GooglePayClientSideConfirmationTest();
$test->run();
