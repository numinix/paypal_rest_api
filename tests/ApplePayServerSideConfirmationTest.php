<?php
/**
 * Test: Apple Pay Server-Side Confirmation
 *
 * This test validates that the Apple Pay module correctly handles payment confirmation
 * on the server side instead of attempting to confirm in JavaScript.
 *
 * Background:
 * - The Braintree Apple Pay module (working reference) does NOT call confirmOrder in JavaScript
 * - Braintree tokenizes the payment, completes the session, and submits the form
 * - The server then processes the payment
 *
 * The test verifies that the JavaScript code:
 * 1. Does NOT call applepay.confirmOrder() in the onpaymentauthorized callback
 * 2. Calls session.completePayment(STATUS_SUCCESS) immediately after order creation
 * 3. Includes payment token and contacts in the payload for server-side processing
 * 4. The PHP pre_confirmation_check handles confirmPaymentSource on the server
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class ApplePayServerSideConfirmationTest
{
    private string $jsFile;
    private string $phpFile;
    private array $testResults = [];
    
    public function __construct()
    {
        // Use __DIR__ to make path relative to test file location
        $this->jsFile = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
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
        echo "\n=== Apple Pay Server-Side Confirmation Test ===\n\n";
        
        $this->testNoClientSideConfirmOrder();
        $this->testImmediateSessionCompletion();
        $this->testPayloadContainsToken();
        $this->testPayloadContainsContacts();
        $this->testServerSideConfirmation();
        
        $this->printResults();
    }
    
    /**
     * Test that confirmOrder is NOT called in the onpaymentauthorized callback
     */
    private function testNoClientSideConfirmOrder(): void
    {
        echo "Test 1: Verify confirmOrder is NOT called in onpaymentauthorized...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the onpaymentauthorized callback
        $pattern = '/session\.onpaymentauthorized\s*=\s*function\s*\([^)]*\)\s*\{(.*?)\n\s*\};/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $callback = $matches[1];
            
            // Check if confirmOrder is called within this callback
            $hasConfirmOrder = strpos($callback, 'applepay.confirmOrder') !== false;
            
            if (!$hasConfirmOrder) {
                $this->testResults[] = [
                    'name' => 'No client-side confirmOrder',
                    'passed' => true,
                    'message' => 'confirmOrder is correctly NOT called in onpaymentauthorized'
                ];
                echo "  ✓ PASS: confirmOrder not called on client side\n";
            } else {
                $this->testResults[] = [
                    'name' => 'No client-side confirmOrder',
                    'passed' => false,
                    'message' => 'confirmOrder should NOT be called in onpaymentauthorized callback'
                ];
                echo "  ❌ FAIL: confirmOrder is still being called on client side\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'No client-side confirmOrder',
                'passed' => false,
                'message' => 'Could not find onpaymentauthorized callback'
            ];
            echo "  ❌ FAIL: Could not find onpaymentauthorized callback\n";
        }
    }
    
    /**
     * Test that session.completePayment(SUCCESS) is called immediately after order creation
     */
    private function testImmediateSessionCompletion(): void
    {
        echo "Test 2: Verify session is completed immediately after order creation...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the onpaymentauthorized callback
        $pattern = '/session\.onpaymentauthorized\s*=\s*function\s*\([^)]*\)\s*\{(.*?)\n\s*\};/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $callback = $matches[1];
            
            // Look for the order creation success path
            // After order validation passes, it should complete the session
            $hasOrderValidation = strpos($callback, 'Order validation passed') !== false;
            $hasImmediateCompletion = preg_match('/session\.completePayment\(ApplePaySession\.STATUS_SUCCESS\)/', $callback);
            
            // Make sure completion happens BEFORE any confirmOrder (which should not exist)
            $completionPos = strpos($callback, 'session.completePayment(ApplePaySession.STATUS_SUCCESS)');
            $confirmOrderPos = strpos($callback, 'applepay.confirmOrder');
            
            $completionBeforeConfirm = $confirmOrderPos === false || $completionPos < $confirmOrderPos;
            
            if ($hasOrderValidation && $hasImmediateCompletion && $completionBeforeConfirm) {
                $this->testResults[] = [
                    'name' => 'Immediate session completion',
                    'passed' => true,
                    'message' => 'Session is completed immediately after order creation'
                ];
                echo "  ✓ PASS: Session completed immediately after order creation\n";
            } else {
                $this->testResults[] = [
                    'name' => 'Immediate session completion',
                    'passed' => false,
                    'message' => 'Session completion is not immediate or missing'
                ];
                echo "  ❌ FAIL: Session completion is not immediate\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'Immediate session completion',
                'passed' => false,
                'message' => 'Could not find onpaymentauthorized callback'
            ];
            echo "  ❌ FAIL: Could not find onpaymentauthorized callback\n";
        }
    }
    
    /**
     * Test that the payload includes the payment token
     */
    private function testPayloadContainsToken(): void
    {
        echo "Test 3: Verify payload includes payment token...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Look for token being added to payload
        $hasTokenInPayload = strpos($content, 'token: event.payment.token') !== false;
        
        if ($hasTokenInPayload) {
            $this->testResults[] = [
                'name' => 'Payload contains token',
                'passed' => true,
                'message' => 'Payment token is included in the payload'
            ];
            echo "  ✓ PASS: Payload includes payment token\n";
        } else {
            $this->testResults[] = [
                'name' => 'Payload contains token',
                'passed' => false,
                'message' => 'Payment token is missing from payload'
            ];
            echo "  ❌ FAIL: Payment token not in payload\n";
        }
    }
    
    /**
     * Test that the payload conditionally includes billing and shipping contacts
     */
    private function testPayloadContainsContacts(): void
    {
        echo "Test 4: Verify payload conditionally includes contacts...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Look for contacts being conditionally added to payload
        $hasBillingContact = strpos($content, 'payload.billing_contact = event.payment.billingContact') !== false;
        $hasShippingContact = strpos($content, 'payload.shipping_contact = event.payment.shippingContact') !== false;
        
        // Also check that they are conditional (if statement)
        $hasBillingCondition = strpos($content, 'if (event.payment.billingContact)') !== false;
        $hasShippingCondition = strpos($content, 'if (event.payment.shippingContact)') !== false;
        
        if ($hasBillingContact && $hasShippingContact && $hasBillingCondition && $hasShippingCondition) {
            $this->testResults[] = [
                'name' => 'Payload contains contacts conditionally',
                'passed' => true,
                'message' => 'Billing and shipping contacts are conditionally included in payload'
            ];
            echo "  ✓ PASS: Contacts conditionally included in payload\n";
        } else {
            $this->testResults[] = [
                'name' => 'Payload contains contacts conditionally',
                'passed' => false,
                'message' => 'Contacts are not properly included in payload: billing=' . ($hasBillingContact && $hasBillingCondition ? 'yes' : 'no') . ', shipping=' . ($hasShippingContact && $hasShippingCondition ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: Contacts not properly included in payload\n";
        }
    }
    
    /**
     * Test that the PHP processWalletConfirmation calls confirmPaymentSource
     */
    private function testServerSideConfirmation(): void
    {
        echo "Test 5: Verify PHP handles server-side confirmation...\n";
        
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
$test = new ApplePayServerSideConfirmationTest();
$test->run();
