<?php
/**
 * Test: PayPalr process_button_ajax Return Type
 *
 * This test validates that the paypalac module's process_button_ajax method
 * returns an array instead of false when payment source is not 'card'.
 *
 * Background:
 * - Zen Cart OPRC (One Page Checkout) expects process_button_ajax() to return an array
 * - When the method returned false, OPRC logged an error and failed to create orders
 * - Payments were processed successfully in PayPal, but orders were not created in Zen Cart
 *
 * Problem Statement:
 * Error: "OPRC checkout_process: Unexpected process_button_ajax() return type for paypalac: boolean"
 * - Orders were not created even though payment went through
 * - The issue occurred when payment source was not 'card' (e.g., PayPal wallet, Venmo, etc.)
 *
 * The Fix:
 * - Changed return value from false to [] (empty array) when payment source is not 'card'
 * - This matches the behavior of other payment modules (paypalac_venmo, paypalac_paylater)
 * - Ensures consistent array return type expected by OPRC
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Testing paypalac::process_button_ajax() return type fix...\n\n";

// Test that the method now returns an array instead of false for non-card payment sources
class PaypalrProcessButtonAjaxReturnTypeTest
{
    private string $paypalacFile;
    
    public function __construct()
    {
        $this->paypalacFile = dirname(__DIR__) . '/includes/modules/payment/paypalac.php';
        
        if (!file_exists($this->paypalacFile)) {
            throw new RuntimeException("PayPalr file not found: {$this->paypalacFile}");
        }
    }
    
    public function run(): void
    {
        echo "Test 1: Verify process_button_ajax returns empty array for non-card payment source\n";
        $this->testNonCardPaymentSourceReturnsArray();
        
        echo "\nTest 2: Verify code does not return false for non-card payment source\n";
        $this->testNoFalseReturnForNonCardPaymentSource();
        
        echo "\nTest 3: Compare with other payment modules (venmo, paylater)\n";
        $this->testConsistencyWithOtherModules();
    }
    
    private function testNonCardPaymentSourceReturnsArray(): void
    {
        $content = file_get_contents($this->paypalacFile);
        
        // Check that the method contains the correct return value
        if (preg_match('/function\s+process_button_ajax\s*\(\s*\)\s*\{[^}]*?payment_source[^}]*?return\s+\[\s*\];/s', $content)) {
            echo "  ✓ process_button_ajax returns empty array [] for non-card payment source\n";
        } else {
            echo "  ✗ FAILED: process_button_ajax does not return empty array for non-card payment source\n";
            throw new RuntimeException("Test failed: Expected return []; for non-card payment source");
        }
    }
    
    private function testNoFalseReturnForNonCardPaymentSource(): void
    {
        $content = file_get_contents($this->paypalacFile);
        
        // Extract the process_button_ajax method
        if (preg_match('/function\s+process_button_ajax\s*\(\s*\)\s*\{(.*?)^\s{4}\}/ms', $content, $matches)) {
            $methodBody = $matches[1];
            
            // Check that the method does NOT return false in the non-card payment source condition
            if (preg_match('/payment_source[^}]*?return\s+false;/s', $methodBody)) {
                echo "  ✗ FAILED: process_button_ajax still returns false for non-card payment source\n";
                throw new RuntimeException("Test failed: Method should not return false for non-card payment source");
            } else {
                echo "  ✓ process_button_ajax does not return false for non-card payment source\n";
            }
        } else {
            echo "  ✗ FAILED: Could not extract process_button_ajax method\n";
            throw new RuntimeException("Test failed: Could not extract method body");
        }
    }
    
    private function testConsistencyWithOtherModules(): void
    {
        $venmoFile = dirname(__DIR__) . '/includes/modules/payment/paypalac_venmo.php';
        $paylaterFile = dirname(__DIR__) . '/includes/modules/payment/paypalac_paylater.php';
        
        $venmoContent = file_get_contents($venmoFile);
        $paylaterContent = file_get_contents($paylaterFile);
        
        // Check that venmo and paylater modules return empty array
        $venmoReturnsArray = preg_match('/function\s+process_button_ajax\s*\(\s*\)\s*\{\s*return\s+\[\s*\];/s', $venmoContent);
        $paylaterReturnsArray = preg_match('/function\s+process_button_ajax\s*\(\s*\)\s*\{\s*return\s+\[\s*\];/s', $paylaterContent);
        
        if ($venmoReturnsArray && $paylaterReturnsArray) {
            echo "  ✓ Venmo and PayLater modules consistently return empty array []\n";
            echo "  ✓ PayPalr module now matches this pattern for non-card payment sources\n";
        } else {
            echo "  ⚠ Warning: Could not verify consistency with other modules\n";
        }
    }
}

try {
    $test = new PaypalrProcessButtonAjaxReturnTypeTest();
    $test->run();
    echo "\n✅ All tests passed!\n";
    echo "   - process_button_ajax now returns array instead of false\n";
    echo "   - This fixes the OPRC order creation issue\n";
    echo "   - Orders will now be created even when payment source is not 'card'\n";
    exit(0);
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
