<?php
/**
 * Test: Apple Pay Client-Side Confirm OrderID Save
 *
 * This test validates that when Apple Pay is confirmed client-side using
 * paypal.Applepay().confirmOrder(), the orderID is properly saved to the
 * session so it can be used for the subsequent authorize/capture operation.
 *
 * Background:
 * - After client-side confirmOrder(), JavaScript sends {orderID, wallet, confirmed: true}
 * - The server must recognize this payload and save the orderID to $_SESSION['PayPalAdvancedCheckout']['Order']['id']
 * - The server must NOT require a token in this case (token was already used client-side)
 *
 * The test verifies that:
 * 1. normalizeWalletPayload recognizes confirmed: true payload
 * 2. normalizeWalletPayload does NOT require token when confirmed: true
 * 3. processWalletConfirmation saves the orderID from the payload
 * 4. The saved orderID is available for subsequent payment operations
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class ApplePayClientSideConfirmOrderIdSaveTest
{
    private string $phpFile;
    private array $testResults = [];
    
    public function __construct()
    {
        $this->phpFile = dirname(__DIR__) . '/includes/modules/payment/paypal/paypal_common.php';
        
        if (!file_exists($this->phpFile)) {
            throw new RuntimeException("PHP file not found: {$this->phpFile}");
        }
    }
    
    public function run(): void
    {
        echo "\n=== Apple Pay Client-Side Confirm OrderID Save Test ===\n\n";
        
        $this->testRecognizesConfirmedPayload();
        $this->testSkipsTokenValidationWhenConfirmed();
        $this->testSavesOrderIdToSession();
        $this->testOrderIdAvailableForCapture();
        
        $this->printResults();
    }
    
    /**
     * Test that normalizeWalletPayload recognizes confirmed: true payload
     */
    private function testRecognizesConfirmedPayload(): void
    {
        echo "Test 1: Verify normalizeWalletPayload recognizes confirmed payload...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the check for confirmed: true and orderID in the payload
        $hasConfirmedCheck = preg_match(
            "/if\s*\(\s*isset\s*\(\s*\\\$payload\s*\[\s*['\"]confirmed['\"]\s*\]\s*\)\s*&&\s*\\\$payload\s*\[\s*['\"]confirmed['\"]\s*\]\s*===\s*true/",
            $content
        );
        
        $hasOrderIdCheck = preg_match(
            "/isset\s*\(\s*\\\$payload\s*\[\s*['\"]orderID['\"]\s*\]\s*\)/",
            $content
        );
        
        if ($hasConfirmedCheck && $hasOrderIdCheck) {
            $this->testResults[] = [
                'name' => 'Recognizes confirmed payload',
                'passed' => true,
                'message' => 'Code checks for confirmed: true and orderID in payload'
            ];
            echo "  ✓ PASS: Recognizes confirmed: true payload with orderID\n";
        } else {
            $this->testResults[] = [
                'name' => 'Recognizes confirmed payload',
                'passed' => false,
                'message' => 'Missing checks: confirmed=' . ($hasConfirmedCheck ? 'yes' : 'no') . ', orderID=' . ($hasOrderIdCheck ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: Does not properly recognize confirmed payload\n";
        }
    }
    
    /**
     * Test that token validation is skipped when confirmed: true
     */
    private function testSkipsTokenValidationWhenConfirmed(): void
    {
        echo "Test 2: Verify token validation skipped for confirmed payloads...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // The logic should return the payload early when confirmed: true
        // This means the token validation (which comes after) is never reached
        $pattern = "/if\s*\(\s*isset\s*\(\s*\\\$payload\s*\[\s*['\"]confirmed['\"]\s*\]\s*\)\s*&&[^}]*\)\s*\{[^}]*return\s+\\\$payload;/s";
        
        $hasEarlyReturn = preg_match($pattern, $content);
        
        if ($hasEarlyReturn) {
            $this->testResults[] = [
                'name' => 'Skips token validation when confirmed',
                'passed' => true,
                'message' => 'Code returns early for confirmed payloads, skipping token validation'
            ];
            echo "  ✓ PASS: Token validation skipped for confirmed payloads\n";
        } else {
            $this->testResults[] = [
                'name' => 'Skips token validation when confirmed',
                'passed' => false,
                'message' => 'Code does not return early for confirmed payloads'
            ];
            echo "  ❌ FAIL: Token validation not skipped\n";
        }
    }
    
    /**
     * Test that processWalletConfirmation saves orderID to session
     */
    private function testSavesOrderIdToSession(): void
    {
        echo "Test 3: Verify processWalletConfirmation saves orderID to session...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the code that saves orderID to session
        $savesOrderId = preg_match(
            "/\\\$_SESSION\s*\[\s*['\"]PayPalAdvancedCheckout['\"]\s*\]\s*\[\s*['\"]Order['\"]\s*\]\s*\[\s*['\"]id['\"]\s*\]\s*=\s*\\\$payload\s*\[\s*['\"]orderID['\"]\s*\]/",
            $content
        );
        
        // Also check for the log message
        $hasLogMessage = strpos($content, 'Saved orderID from client-side confirmation') !== false;
        
        if ($savesOrderId && $hasLogMessage) {
            $this->testResults[] = [
                'name' => 'Saves orderID to session',
                'passed' => true,
                'message' => 'Code saves payload[orderID] to $_SESSION[PayPalAdvancedCheckout][Order][id]'
            ];
            echo "  ✓ PASS: OrderID saved to session\n";
        } else {
            $this->testResults[] = [
                'name' => 'Saves orderID to session',
                'passed' => false,
                'message' => 'Missing orderID save: saves=' . ($savesOrderId ? 'yes' : 'no') . ', log=' . ($hasLogMessage ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: OrderID not saved to session\n";
        }
    }
    
    /**
     * Test that the saved orderID is in the expected session location for capture
     */
    private function testOrderIdAvailableForCapture(): void
    {
        echo "Test 4: Verify orderID location is correct for capture operation...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Check that the captureWalletPayment function reads from the same session location
        $captureReadsOrderId = preg_match(
            "/\\\$paypal_order_id\s*=\s*\\\$_SESSION\s*\[\s*['\"]PayPalAdvancedCheckout['\"]\s*\]\s*\[\s*['\"]Order['\"]\s*\]\s*\[\s*['\"]id['\"]\s*\]/",
            $content
        );
        
        if ($captureReadsOrderId) {
            $this->testResults[] = [
                'name' => 'OrderID available for capture',
                'passed' => true,
                'message' => 'captureWalletPayment reads from $_SESSION[PayPalAdvancedCheckout][Order][id]'
            ];
            echo "  ✓ PASS: OrderID available for capture at correct location\n";
        } else {
            $this->testResults[] = [
                'name' => 'OrderID available for capture',
                'passed' => false,
                'message' => 'captureWalletPayment does not read from expected session location'
            ];
            echo "  ❌ FAIL: OrderID location mismatch\n";
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
                echo "✗ {$result['name']}: {$result['message']}\n";
            }
        }
        
        echo "\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        
        if ($failed > 0) {
            echo "\n⚠️  Some tests failed. Please review the implementation.\n";
            exit(1);
        } else {
            echo "\n✓ All tests passed!\n";
        }
    }
}

// Run the test
$test = new ApplePayClientSideConfirmOrderIdSaveTest();
$test->run();
