<?php
/**
 * Test: Apple Pay confirmOrder Response Handling
 *
 * This test validates that the Apple Pay module correctly handles the response from
 * PayPal's confirmOrder() SDK method.
 *
 * According to PayPal SDK source code (paypal-applepay-components/src/applepay.js),
 * the confirmOrder method returns Promise<void | PayPalApplePayErrorType>, which means:
 * - On success: resolves with undefined (void)
 * - On failure: throws PayPalApplePayError
 *
 * The test verifies that the JavaScript code:
 * 1. Does NOT check for a status field in the response (which doesn't exist)
 * 2. Treats reaching .then() as success
 * 3. Properly catches and handles errors in .catch()
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class ApplePayConfirmOrderResponseHandlingTest
{
    private string $jsFile = '/home/runner/work/paypal_rest_api/paypal_rest_api/includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
    private array $testResults = [];
    
    public function run(): void
    {
        echo "\n=== Apple Pay confirmOrder Response Handling Test ===\n\n";
        
        $this->testNoStatusCheck();
        $this->testSuccessHandling();
        $this->testErrorLogging();
        
        $this->printResults();
    }
    
    /**
     * Test that the code does NOT check for confirmResult.status
     * (which was the bug - checking for a field that doesn't exist)
     */
    private function testNoStatusCheck(): void
    {
        echo "Test 1: Verify no incorrect status check...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Look for the old incorrect pattern
        $hasOldStatusCheck = preg_match('/confirmResult\.status\s*===\s*PAYPAL_STATUS\.(APPROVED|PAYER_ACTION_REQUIRED)/', $content);
        
        if ($hasOldStatusCheck) {
            $this->testResults[] = [
                'name' => 'No incorrect status check',
                'passed' => false,
                'message' => 'Code still checks for confirmResult.status which does not exist'
            ];
            echo "  ❌ FAIL: Code still has incorrect status check\n";
        } else {
            $this->testResults[] = [
                'name' => 'No incorrect status check',
                'passed' => true,
                'message' => 'Code correctly does not check confirmResult.status'
            ];
            echo "  ✓ PASS: No incorrect status check found\n";
        }
    }
    
    /**
     * Test that success is handled by reaching .then() callback
     */
    private function testSuccessHandling(): void
    {
        echo "Test 2: Verify correct success handling...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the confirmOrder call and its .then() handler
        $pattern = '/applepay\.confirmOrder\(\{.*?\}\)\.then\(function\s*\(confirmResult\)\s*\{(.*?)\}\)\.catch/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $thenBlock = $matches[1];
            
            // Check that the .then() block:
            // 1. Completes payment with SUCCESS
            // 2. Sets payload
            // 3. Does NOT have conditional logic checking status
            
            $hasSuccessCompletion = strpos($thenBlock, 'session.completePayment(ApplePaySession.STATUS_SUCCESS)') !== false;
            $setsPayload = strpos($thenBlock, 'setApplePayPayload(payload)') !== false;
            $hasConditionalStatusCheck = preg_match('/if\s*\(.*?status.*?\)/', $thenBlock);
            
            if ($hasSuccessCompletion && $setsPayload && !$hasConditionalStatusCheck) {
                $this->testResults[] = [
                    'name' => 'Correct success handling',
                    'passed' => true,
                    'message' => 'Success is correctly handled in .then() without status checks'
                ];
                echo "  ✓ PASS: Success handling is correct\n";
            } else {
                $this->testResults[] = [
                    'name' => 'Correct success handling',
                    'passed' => false,
                    'message' => 'Success handling has issues: success=' . ($hasSuccessCompletion ? 'yes' : 'no') . 
                                ', payload=' . ($setsPayload ? 'yes' : 'no') . 
                                ', conditional=' . ($hasConditionalStatusCheck ? 'yes' : 'no')
                ];
                echo "  ❌ FAIL: Success handling has issues\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'Correct success handling',
                'passed' => false,
                'message' => 'Could not find confirmOrder .then() block'
            ];
            echo "  ❌ FAIL: Could not find confirmOrder .then() block\n";
        }
    }
    
    /**
     * Test that error logging includes helpful details
     */
    private function testErrorLogging(): void
    {
        echo "Test 3: Verify detailed error logging...\n";
        
        $content = file_get_contents($this->jsFile);
        
        // Find the .catch() block
        $pattern = '/\.catch\(function\s*\(error\)\s*\{(.*?)\}\);/s';
        
        if (preg_match_all($pattern, $content, $matches)) {
            // Find the catch block after confirmOrder
            $foundDetailedLogging = false;
            
            foreach ($matches[1] as $catchBlock) {
                // Check if this is the confirmOrder catch block (has confirmOrder in error message)
                if (strpos($catchBlock, 'confirmOrder failed') !== false) {
                    // Check for detailed error logging
                    $hasNameLog = strpos($catchBlock, 'error.name') !== false;
                    $hasMessageLog = strpos($catchBlock, 'error.message') !== false;
                    $hasDebugIdLog = strpos($catchBlock, 'error.paypalDebugId') !== false;
                    
                    if ($hasNameLog && $hasMessageLog && $hasDebugIdLog) {
                        $foundDetailedLogging = true;
                        break;
                    }
                }
            }
            
            if ($foundDetailedLogging) {
                $this->testResults[] = [
                    'name' => 'Detailed error logging',
                    'passed' => true,
                    'message' => 'Error logging includes name, message, and paypalDebugId'
                ];
                echo "  ✓ PASS: Detailed error logging is present\n";
            } else {
                $this->testResults[] = [
                    'name' => 'Detailed error logging',
                    'passed' => false,
                    'message' => 'Error logging is missing some details'
                ];
                echo "  ❌ FAIL: Error logging is incomplete\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'Detailed error logging',
                'passed' => false,
                'message' => 'Could not find .catch() block'
            ];
            echo "  ❌ FAIL: Could not find .catch() block\n";
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
$test = new ApplePayConfirmOrderResponseHandlingTest();
$test->run();
