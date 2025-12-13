<?php
/**
 * Test: Apple Pay Server-Side Confirmation (DEPRECATED)
 *
 * This test is now DEPRECATED. The code has been changed to use client-side confirmation.
 * See ApplePayClientSideConfirmationTest.php for the current test coverage.
 *
 * Historical Background (no longer applicable):
 * - The Braintree Apple Pay module (working reference) does NOT call confirmOrder in JavaScript
 * - Braintree tokenizes the payment, completes the session, and submits the form
 * - The server then processes the payment
 *
 * Current Implementation (as of 2025):
 * - PayPal's Apple Pay integration requires client-side confirmOrder() call
 * - Server-side confirm-payment-source causes 500 INTERNAL_SERVICE_ERROR for Apple Pay
 * - The proper flow is: create order → client-side confirmOrder() → server-side authorize/capture
 *
 * This test now validates that we've properly switched to client-side confirmation.
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
        echo "\n=== Apple Pay Server-Side Confirmation Test (DEPRECATED) ===\n";
        echo "⚠️  This test is deprecated. The code now uses client-side confirmOrder().\n";
        echo "See ApplePayClientSideConfirmationTest.php for current test coverage.\n\n";
        
        // Just validate that we've properly switched to client-side confirmation
        $this->testUsesClientSideConfirmation();
        
        $this->printResults();
    }
    
    /**
     * Test that the code now uses client-side confirmOrder (opposite of what this test originally checked)
     */
    private function testUsesClientSideConfirmation(): void
    {
        echo "Historical validation (checking that code has been properly updated):\n\n";
        echo "Test 1: Verify code now uses client-side confirmOrder...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Check if confirmOrder IS now called (opposite of original test)
        $hasConfirmOrder = strpos($content, 'applepay.confirmOrder') !== false;
        
        if ($hasConfirmOrder) {
            $this->testResults[] = [
                'name' => 'Uses client-side confirmOrder',
                'passed' => true,
                'message' => 'Code has been correctly updated to use client-side confirmOrder()'
            ];
            echo "  ✓ PASS: Code now uses client-side confirmOrder() (as required by PayPal)\n";
        } else {
            $this->testResults[] = [
                'name' => 'Uses client-side confirmOrder',
                'passed' => false,
                'message' => 'Code does not use client-side confirmOrder() - this will cause 500 errors'
            ];
            echo "  ❌ FAIL: Code does not use client-side confirmOrder()\n";
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
