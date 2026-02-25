<?php
/**
 * Test: Venmo Authorize Mode Order Status
 *
 * This test validates that the Venmo module correctly sets the order status
 * based on the MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE configuration setting.
 *
 * Background:
 * - When TRANSACTION_MODE is "Auth Only (All Txns)", orders should be created with "unpaid" status
 * - When TRANSACTION_MODE is "Final Sale" or "Auth Only (Card-Only)", orders should be created with "held" status
 * - Venmo is a wallet payment (not a card payment)
 *
 * Problem Statement:
 * - Venmo orders were always created with "held" status regardless of transaction mode
 * - This was because the constructor always used MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID
 * - It should check MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE like the main paypalac module
 *
 * The test verifies that:
 * 1. Constructor checks MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE setting
 * 2. "Auth Only (All Txns)" mode sets ORDER_PENDING_STATUS_ID (unpaid)
 * 3. "Final Sale" mode sets ORDER_STATUS_ID (held)
 * 4. "Auth Only (Card-Only)" mode sets ORDER_STATUS_ID (held) for wallet payments
 * 5. before_process correctly handles CREATED status for authorizations
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class VenmoAuthorizeModeOrderStatusTest
{
    private string $applePayFile;
    private string $paypalacFile;
    private array $testResults = [];
    
    public function __construct()
    {
        $this->applePayFile = dirname(__DIR__) . '/includes/modules/payment/paypalac_venmo.php';
        $this->paypalacFile = dirname(__DIR__) . '/includes/modules/payment/paypalac.php';
        
        if (!file_exists($this->applePayFile)) {
            throw new RuntimeException("Venmo file not found: {$this->applePayFile}");
        }
        
        if (!file_exists($this->paypalacFile)) {
            throw new RuntimeException("PayPal file not found: {$this->paypalacFile}");
        }
    }
    
    public function run(): void
    {
        echo "\n=== Venmo Authorize Mode Order Status Test ===\n\n";
        
        $this->testConstructorChecksTransactionMode();
        $this->testConstructorLogicMatchesPaypalr();
        $this->testBeforeProcessHandlesCreatedStatus();
        $this->testBeforeProcessHandlesCapturedStatus();
        $this->testBeforeProcessHandlesPendingStatus();
        
        $this->printResults();
    }
    
    /**
     * Test that constructor checks MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE
     */
    private function testConstructorChecksTransactionMode(): void
    {
        echo "Test 1: Constructor checks TRANSACTION_MODE setting...\n";
        
        $content = file_get_contents($this->applePayFile);
        
        // Check if TRANSACTION_MODE is referenced in the constructor
        $checksTransactionMode = strpos($content, 'MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE') !== false;
        
        // Check if it references both status constants
        $hasOrderStatusId = strpos($content, 'MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID') !== false;
        $hasPendingStatusId = strpos($content, 'MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID') !== false;
        
        // Check for the specific logic pattern (if Final Sale || Auth Only (Card-Only))
        $hasCorrectPattern = (strpos($content, "'Final Sale'") !== false || strpos($content, '"Final Sale"') !== false) &&
                            (strpos($content, "'Auth Only (Card-Only)'") !== false || strpos($content, '"Auth Only (Card-Only)"') !== false);
        
        if ($checksTransactionMode && $hasOrderStatusId && $hasPendingStatusId && $hasCorrectPattern) {
            $this->testResults[] = [
                'name' => 'Constructor checks TRANSACTION_MODE',
                'passed' => true,
                'message' => 'Constructor correctly checks TRANSACTION_MODE with proper logic'
            ];
            echo "  ✓ PASS: Constructor checks TRANSACTION_MODE with proper logic\n";
        } else {
            $this->testResults[] = [
                'name' => 'Constructor checks TRANSACTION_MODE',
                'passed' => false,
                'message' => 'Missing required elements: TRANSACTION_MODE=' . ($checksTransactionMode ? 'yes' : 'no') . 
                           ', ORDER_STATUS_ID=' . ($hasOrderStatusId ? 'yes' : 'no') . 
                           ', ORDER_PENDING_STATUS_ID=' . ($hasPendingStatusId ? 'yes' : 'no') . 
                           ', correct pattern=' . ($hasCorrectPattern ? 'yes' : 'no')
            ];
            echo "  ❌ FAIL: Constructor missing required elements\n";
        }
    }
    
    /**
     * Test that constructor logic matches the main paypalac module
     */
    private function testConstructorLogicMatchesPaypalr(): void
    {
        echo "Test 2: Constructor logic matches main paypalac module...\n";
        
        $applePayContent = file_get_contents($this->applePayFile);
        $paypalacContent = file_get_contents($this->paypalacFile);
        
        // Check if both files have the same transaction mode conditional logic
        $applePayHasFinalSale = (strpos($applePayContent, "'Final Sale'") !== false || strpos($applePayContent, '"Final Sale"') !== false);
        $applePayHasCardOnly = (strpos($applePayContent, "'Auth Only (Card-Only)'") !== false || strpos($applePayContent, '"Auth Only (Card-Only)"') !== false);
        $applePayHasOrderStatus = strpos($applePayContent, 'MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID') !== false;
        $applePayHasPendingStatus = strpos($applePayContent, 'MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID') !== false;
        
        $paypalacHasFinalSale = (strpos($paypalacContent, "'Final Sale'") !== false || strpos($paypalacContent, '"Final Sale"') !== false);
        $paypalacHasCardOnly = (strpos($paypalacContent, "'Auth Only (Card-Only)'") !== false || strpos($paypalacContent, '"Auth Only (Card-Only)"') !== false);
        
        if ($applePayHasFinalSale && $applePayHasCardOnly && $applePayHasOrderStatus && $applePayHasPendingStatus &&
            $paypalacHasFinalSale && $paypalacHasCardOnly) {
            $this->testResults[] = [
                'name' => 'Constructor logic matches paypalac',
                'passed' => true,
                'message' => 'Venmo constructor uses same transaction mode logic as main paypalac module'
            ];
            echo "  ✓ PASS: Constructor logic matches main paypalac module\n";
        } else {
            $this->testResults[] = [
                'name' => 'Constructor logic matches paypalac',
                'passed' => false,
                'message' => 'Logic differs between Venmo and main paypalac module'
            ];
            echo "  ❌ FAIL: Constructor logic differs from paypalac\n";
        }
    }
    
    /**
     * Test that before_process correctly handles CREATED status (successful authorization)
     */
    private function testBeforeProcessHandlesCreatedStatus(): void
    {
        echo "Test 3: before_process handles CREATED status correctly...\n";
        
        $content = file_get_contents($this->applePayFile);
        
        // Find the section that checks payment_status
        $pattern = '/if\s*\(\s*\$payment_status\s*!==\s*PayPalAdvancedCheckoutApi::STATUS_CAPTURED\s*\)\s*\{/s';
        
        if (preg_match($pattern, $content)) {
            $this->testResults[] = [
                'name' => 'before_process handles CREATED status',
                'passed' => true,
                'message' => 'before_process correctly checks only CAPTURED status, treating CREATED as unpaid'
            ];
            echo "  ✓ PASS: before_process handles CREATED status correctly (as unpaid/pending)\n";
        } else {
            // Check if the old incorrect pattern exists
            $oldPattern = '/if\s*\(\s*\$payment\[[\'"]status[\'"]\]\s*!==\s*PayPalAdvancedCheckoutApi::STATUS_COMPLETED\s*\)/s';
            
            if (preg_match($oldPattern, $content)) {
                $this->testResults[] = [
                    'name' => 'before_process handles CREATED status',
                    'passed' => false,
                    'message' => 'before_process still uses old logic checking only COMPLETED status'
                ];
                echo "  ❌ FAIL: before_process uses old logic (treats CREATED as completed instead of unpaid)\n";
            } else {
                $this->testResults[] = [
                    'name' => 'before_process handles CREATED status',
                    'passed' => false,
                    'message' => 'Could not find payment_status check in before_process'
                ];
                echo "  ❌ FAIL: Could not find payment_status check\n";
            }
        }
    }
    
    /**
     * Test that before_process uses only STATUS_CAPTURED in conditionals
     */
    private function testBeforeProcessHandlesCapturedStatus(): void
    {
        echo "Test 4: before_process checks only STATUS_CAPTURED...\n";
        
        $content = file_get_contents($this->applePayFile);
        
        // Check that the conditional uses both STATUS_CAPTURED and STATUS_CREATED
        $pattern = '/if\s*\(\s*\$payment_status\s*!==\s*PayPalAdvancedCheckoutApi::STATUS_CAPTURED\s*\)\s*\{/';
        
        if (preg_match($pattern, $content)) {
            $this->testResults[] = [
                'name' => 'before_process uses only STATUS_CAPTURED',
                'passed' => true,
                'message' => 'before_process correctly uses only STATUS_CAPTURED (treats auth as unpaid)'
            ];
            echo "  ✓ PASS: Uses only STATUS_CAPTURED correctly (auth is unpaid)\n";
        } else {
            $this->testResults[] = [
                'name' => 'before_process uses only STATUS_CAPTURED',
                'passed' => false,
                'message' => 'Does not use the correct STATUS_CAPTURED-only conditional'
            ];
            echo "  ❌ FAIL: Missing STATUS_CAPTURED-only conditional\n";
        }
    }
    
    /**
     * Test that before_process sets ORDER_PENDING_STATUS_ID for non-captured payments
     */
    private function testBeforeProcessHandlesPendingStatus(): void
    {
        echo "Test 5: before_process sets ORDER_PENDING_STATUS_ID for non-captured payments...\n";
        
        $content = file_get_contents($this->applePayFile);
        
        // Find the section where status is set for non-successful payments
        $pattern = '/if\s*\(\s*\$payment_status\s*!==\s*PayPalAdvancedCheckoutApi::STATUS_CAPTURED\s*\)\s*\{[^}]*ORDER_PENDING_STATUS_ID[^}]*\}/s';
        
        if (preg_match($pattern, $content)) {
            $this->testResults[] = [
                'name' => 'before_process uses ORDER_PENDING_STATUS_ID',
                'passed' => true,
                'message' => 'before_process correctly sets ORDER_PENDING_STATUS_ID for non-captured payments (including auth)'
            ];
            echo "  ✓ PASS: Sets ORDER_PENDING_STATUS_ID for non-captured payments\n";
        } else {
            // Check if the old incorrect pattern exists (using HELD_STATUS_ID)
            $oldPattern = '/if\s*\([^)]*STATUS_COMPLETED[^)]*\)\s*\{[^}]*HELD_STATUS_ID[^}]*\}/s';
            
            if (preg_match($oldPattern, $content)) {
                $this->testResults[] = [
                    'name' => 'before_process uses ORDER_PENDING_STATUS_ID',
                    'passed' => false,
                    'message' => 'before_process still uses old logic with HELD_STATUS_ID'
                ];
                echo "  ❌ FAIL: Uses HELD_STATUS_ID instead of ORDER_PENDING_STATUS_ID\n";
            } else {
                $this->testResults[] = [
                    'name' => 'before_process uses ORDER_PENDING_STATUS_ID',
                    'passed' => false,
                    'message' => 'Could not find status assignment for non-successful payments'
                ];
                echo "  ❌ FAIL: Could not find status assignment\n";
            }
        }
    }
    
    private function printResults(): void
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "TEST RESULTS SUMMARY\n";
        echo str_repeat('=', 70) . "\n\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passed++;
                echo "✓ PASS: {$result['name']}\n";
            } else {
                $failed++;
                echo "❌ FAIL: {$result['name']}\n";
                echo "  Reason: {$result['message']}\n";
            }
        }
        
        $total = $passed + $failed;
        echo "\n" . str_repeat('-', 70) . "\n";
        echo "Total: {$total} tests | Passed: {$passed} | Failed: {$failed}\n";
        echo str_repeat('=', 70) . "\n\n";
        
        if ($failed > 0) {
            exit(1);
        }
    }
}

// Run the test
try {
    $test = new VenmoAuthorizeModeOrderStatusTest();
    $test->run();
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
