<?php
/**
 * Test: Wallet Capture or Authorize Intent
 *
 * This test validates that wallet payment methods (Apple Pay, Google Pay, Venmo)
 * correctly respect the transaction mode setting and call the appropriate
 * PayPal API endpoint (capture vs authorize).
 *
 * Background:
 * - When MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is 'Final Sale', wallets should CAPTURE
 * - When MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is 'Auth Only (All Txns)', wallets should AUTHORIZE
 * - When MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE is 'Auth Only (Card-Only)', wallets should CAPTURE
 *   (because wallets are not cards)
 *
 * The test verifies that:
 * 1. captureWalletPayment accepts transaction_mode and ppr_type parameters
 * 2. The method uses the same logic as processCreditCardPayment for determining capture vs authorize
 * 3. The method calls captureOrder when should_capture is true
 * 4. The method calls authorizeOrder when should_capture is false
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class WalletCaptureOrAuthorizeIntentTest
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
        echo "\n=== Wallet Capture or Authorize Intent Test ===\n\n";
        
        $this->testCaptureWalletPaymentSignature();
        $this->testHasTransactionModeLogic();
        $this->testCallsCaptureOrder();
        $this->testCallsAuthorizeOrder();
        $this->testWalletModulesPassTransactionMode();
        
        $this->printResults();
    }
    
    /**
     * Test that captureWalletPayment has the correct signature with transaction_mode and ppr_type
     */
    private function testCaptureWalletPaymentSignature(): void
    {
        echo "Test 1: Verify captureWalletPayment signature...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the function signature with all required parameters
        $hasCorrectSignature = preg_match(
            '/function\s+captureWalletPayment\s*\([^)]*\$transaction_mode[^)]*\$ppr_type[^)]*\)/',
            $content
        );
        
        if ($hasCorrectSignature) {
            $this->testResults[] = [
                'name' => 'captureWalletPayment signature',
                'passed' => true,
                'message' => 'Function accepts transaction_mode and ppr_type parameters'
            ];
            echo "  ✓ PASS: Function signature includes transaction_mode and ppr_type\n";
        } else {
            $this->testResults[] = [
                'name' => 'captureWalletPayment signature',
                'passed' => false,
                'message' => 'Function signature missing required parameters'
            ];
            echo "  ❌ FAIL: Function signature does not include required parameters\n";
        }
    }
    
    /**
     * Test that the method has logic to determine capture vs authorize based on transaction mode
     */
    private function testHasTransactionModeLogic(): void
    {
        echo "Test 2: Verify transaction mode logic...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the logic that determines should_capture based on transaction_mode
        // Pattern should match: $should_capture = ($transaction_mode === 'Final Sale' || ...)
        // OR using constants: $should_capture = ($transaction_mode === self::TRANSACTION_MODE_FINAL_SALE || ...)
        $hasTransactionModeCheck = preg_match(
            '/\$should_capture\s*=\s*\(\s*\$transaction_mode\s*===\s*(?:[\'"]Final Sale[\'"]|self::TRANSACTION_MODE_FINAL_SALE)/',
            $content
        );
        
        // Also check for the Auth Only (Card-Only) check with ppr_type
        $hasCardOnlyCheck = preg_match(
            '/\$ppr_type\s*!==\s*[\'"]card[\'"]\s*&&\s*\$transaction_mode\s*===\s*(?:[\'"]Auth Only \(Card-Only\)[\'"]|self::TRANSACTION_MODE_AUTH_CARD_ONLY)/',
            $content
        );
        
        if ($hasTransactionModeCheck && $hasCardOnlyCheck) {
            $this->testResults[] = [
                'name' => 'Transaction mode logic',
                'passed' => true,
                'message' => 'Code correctly determines capture vs authorize based on transaction mode'
            ];
            echo "  ✓ PASS: Transaction mode logic implemented\n";
        } else {
            $this->testResults[] = [
                'name' => 'Transaction mode logic',
                'passed' => false,
                'message' => 'Missing logic: mode check=' . ($hasTransactionModeCheck ? 'yes' : 'no') . ', card-only=' . ($hasCardOnlyCheck ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: Transaction mode logic not implemented\n";
        }
    }
    
    /**
     * Test that the method calls captureOrder when should_capture is true
     */
    private function testCallsCaptureOrder(): void
    {
        echo "Test 3: Verify captureOrder is called when should_capture is true...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the captureOrder call within the captureWalletPayment function
        // Should be: if ($should_capture) { ... $ppr->captureOrder(...) }
        $pattern = '/if\s*\(\s*\$should_capture\s*\)\s*\{[^}]*\$ppr\s*->\s*captureOrder\s*\(/s';
        $hasCaptureOrderCall = preg_match($pattern, $content);
        
        if ($hasCaptureOrderCall) {
            $this->testResults[] = [
                'name' => 'Calls captureOrder',
                'passed' => true,
                'message' => 'Code calls captureOrder when should_capture is true'
            ];
            echo "  ✓ PASS: captureOrder called correctly\n";
        } else {
            $this->testResults[] = [
                'name' => 'Calls captureOrder',
                'passed' => false,
                'message' => 'captureOrder not called in the correct condition'
            ];
            echo "  ❌ FAIL: captureOrder not called correctly\n";
        }
    }
    
    /**
     * Test that the method calls authorizeOrder when should_capture is false
     */
    private function testCallsAuthorizeOrder(): void
    {
        echo "Test 4: Verify authorizeOrder is called when should_capture is false...\n";
        
        $content = file_get_contents($this->phpFile);
        
        // Look for the authorizeOrder call in the else block
        // Should be: } else { ... $ppr->authorizeOrder(...) }
        $pattern = '/\}\s*else\s*\{[^}]*\$ppr\s*->\s*authorizeOrder\s*\(/s';
        $hasAuthorizeOrderCall = preg_match($pattern, $content);
        
        if ($hasAuthorizeOrderCall) {
            $this->testResults[] = [
                'name' => 'Calls authorizeOrder',
                'passed' => true,
                'message' => 'Code calls authorizeOrder when should_capture is false'
            ];
            echo "  ✓ PASS: authorizeOrder called correctly\n";
        } else {
            $this->testResults[] = [
                'name' => 'Calls authorizeOrder',
                'passed' => false,
                'message' => 'authorizeOrder not called in the correct condition'
            ];
            echo "  ❌ FAIL: authorizeOrder not called correctly\n";
        }
    }
    
    /**
     * Test that wallet modules pass transaction mode to captureWalletPayment
     */
    private function testWalletModulesPassTransactionMode(): void
    {
        echo "Test 5: Verify wallet modules pass transaction mode...\n";
        
        $walletModules = [
            'paypalr_applepay.php' => 'apple_pay',
            'paypalr_googlepay.php' => 'google_pay',
            'paypalr_venmo.php' => 'venmo',
        ];
        
        $allPassed = true;
        $messages = [];
        
        foreach ($walletModules as $moduleName => $expectedType) {
            $modulePath = dirname(__DIR__) . '/includes/modules/payment/' . $moduleName;
            
            if (!file_exists($modulePath)) {
                $allPassed = false;
                $messages[] = "$moduleName: File not found";
                continue;
            }
            
            $content = file_get_contents($modulePath);
            
            // Look for the call to captureWalletPayment with MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE
            $passesTransactionMode = preg_match(
                '/captureWalletPayment\s*\([^)]*MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE[^)]*\)/',
                $content
            );
            
            // Also check that it passes the correct ppr_type
            $passesPprType = strpos($content, "'$expectedType'") !== false;
            
            if ($passesTransactionMode && $passesPprType) {
                $messages[] = "$moduleName: ✓ Passes transaction mode and ppr_type";
            } else {
                $allPassed = false;
                $messages[] = "$moduleName: ✗ Missing parameters (mode=" . ($passesTransactionMode ? 'yes' : 'no') . ", type=" . ($passesPprType ? 'yes' : 'no') . ")";
            }
        }
        
        if ($allPassed) {
            $this->testResults[] = [
                'name' => 'Wallet modules pass transaction mode',
                'passed' => true,
                'message' => 'All wallet modules correctly pass transaction mode and ppr_type'
            ];
            echo "  ✓ PASS: All wallet modules updated\n";
            foreach ($messages as $msg) {
                echo "    - $msg\n";
            }
        } else {
            $this->testResults[] = [
                'name' => 'Wallet modules pass transaction mode',
                'passed' => false,
                'message' => 'Some wallet modules missing parameters: ' . implode(', ', $messages)
            ];
            echo "  ❌ FAIL: Some wallet modules not updated\n";
            foreach ($messages as $msg) {
                echo "    - $msg\n";
            }
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
$test = new WalletCaptureOrAuthorizeIntentTest();
$test->run();
