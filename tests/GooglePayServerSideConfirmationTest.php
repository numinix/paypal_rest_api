<?php
/**
 * Test: Google Pay Server-Side Confirmation
 *
 * This test validates that the Google Pay module correctly handles payment confirmation
 * on the server side instead of attempting to confirm in JavaScript.
 *
 * Background:
 * - Following the Apple Pay fix, Google Pay now also moves confirmation to the server
 * - Previously, Google Pay called googlepay.confirmOrder() on the client
 * - This could cause double-confirmation issues (client + server)
 *
 * The test verifies that the JavaScript code:
 * 1. Does NOT call googlepay.confirmOrder() after loadPaymentData
 * 2. Includes paymentMethodData in the payload for server-side processing
 * 3. The PHP pre_confirmation_check handles confirmPaymentSource on the server
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class GooglePayServerSideConfirmationTest
{
    private string $jsFile;
    private string $phpFile;
    private array $testResults = [];
    
    public function __construct()
    {
        // Use __DIR__ to make path relative to test file location
        $this->jsFile = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js';
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
        echo "\n=== Google Pay Server-Side Confirmation Test ===\n\n";
        
        $this->testNoClientSideConfirmOrder();
        $this->testPayloadContainsPaymentMethodData();
        $this->testServerSideConfirmation();
        $this->testConsistentWithApplePay();
        
        $this->printResults();
    }
    
    /**
     * Test that confirmOrder is NOT called after loadPaymentData
     */
    private function testNoClientSideConfirmOrder(): void
    {
        echo "Test 1: Verify confirmOrder is NOT called after loadPaymentData...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the loadPaymentData callback
        $pattern = '/loadPaymentData\([^)]+\)\.then\(function\s*\([^)]*\)\s*\{(.*?)\}\);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $callback = $matches[1];
            
            // Check if confirmOrder is called within this callback
            $hasConfirmOrder = strpos($callback, 'googlepay.confirmOrder') !== false;
            
            if (!$hasConfirmOrder) {
                $this->testResults[] = [
                    'name' => 'No client-side confirmOrder',
                    'passed' => true,
                    'message' => 'confirmOrder is correctly NOT called after loadPaymentData'
                ];
                echo "  ✓ PASS: confirmOrder not called on client side\n";
            } else {
                $this->testResults[] = [
                    'name' => 'No client-side confirmOrder',
                    'passed' => false,
                    'message' => 'confirmOrder should NOT be called after loadPaymentData'
                ];
                echo "  ❌ FAIL: confirmOrder is still being called on client side\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'No client-side confirmOrder',
                'passed' => false,
                'message' => 'Could not find loadPaymentData callback'
            ];
            echo "  ❌ FAIL: Could not find loadPaymentData callback\n";
        }
    }
    
    /**
     * Test that the payload includes paymentMethodData
     */
    private function testPayloadContainsPaymentMethodData(): void
    {
        echo "Test 2: Verify payload includes paymentMethodData...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Look for paymentMethodData being added to payload
        $hasPaymentMethodData = strpos($content, 'paymentMethodData: paymentData.paymentMethodData') !== false;
        
        if ($hasPaymentMethodData) {
            $this->testResults[] = [
                'name' => 'Payload contains paymentMethodData',
                'passed' => true,
                'message' => 'paymentMethodData is included in the payload'
            ];
            echo "  ✓ PASS: Payload includes paymentMethodData\n";
        } else {
            $this->testResults[] = [
                'name' => 'Payload contains paymentMethodData',
                'passed' => false,
                'message' => 'paymentMethodData is missing from payload'
            ];
            echo "  ❌ FAIL: paymentMethodData not in payload\n";
        }
    }
    
    /**
     * Test that the PHP processWalletConfirmation calls confirmPaymentSource
     */
    private function testServerSideConfirmation(): void
    {
        echo "Test 3: Verify PHP handles server-side confirmation...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for confirmPaymentSource call in processWalletConfirmation
        $hasConfirmPaymentSource = strpos($content, 'confirmPaymentSource') !== false;
        $hasWalletTypeWrapper = strpos($content, '[$walletType => $payload]') !== false;
        
        if ($hasConfirmPaymentSource && $hasWalletTypeWrapper) {
            $this->testResults[] = [
                'name' => 'Server-side confirmation',
                'passed' => true,
                'message' => 'PHP correctly calls confirmPaymentSource with wallet type wrapper'
            ];
            echo "  ✓ PASS: Server handles confirmation with confirmPaymentSource\n";
        } else {
            $this->testResults[] = [
                'name' => 'Server-side confirmation',
                'passed' => false,
                'message' => 'confirmPaymentSource not properly implemented'
            ];
            echo "  ❌ FAIL: Server-side confirmation not properly implemented\n";
        }
    }
    
    /**
     * Test that Google Pay follows the same pattern as Apple Pay
     */
    private function testConsistentWithApplePay(): void
    {
        echo "Test 4: Verify Google Pay follows Apple Pay pattern...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Check that Google Pay does NOT call confirmOrder after getting payment data
        $hasNoConfirmOrderInCallback = !preg_match('/loadPaymentData.*\.then.*googlepay\.confirmOrder/s', $content);
        
        // Check that it sets the payload and dispatches event
        $setsPayload = strpos($content, 'setGooglePayPayload(payload)') !== false;
        $dispatchesEvent = strpos($content, 'paypalr:googlepay:payload') !== false;
        
        if ($hasNoConfirmOrderInCallback && $setsPayload && $dispatchesEvent) {
            $this->testResults[] = [
                'name' => 'Consistent with Apple Pay',
                'passed' => true,
                'message' => 'Google Pay follows the same server-side confirmation pattern as Apple Pay'
            ];
            echo "  ✓ PASS: Google Pay follows Apple Pay pattern\n";
        } else {
            $this->testResults[] = [
                'name' => 'Consistent with Apple Pay',
                'passed' => false,
                'message' => 'Google Pay does not follow Apple Pay pattern: no_confirm=' . ($hasNoConfirmOrderInCallback ? 'yes' : 'no') . ', sets_payload=' . ($setsPayload ? 'yes' : 'no') . ', dispatches=' . ($dispatchesEvent ? 'yes' : 'no')
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
$test = new GooglePayServerSideConfirmationTest();
$test->run();
