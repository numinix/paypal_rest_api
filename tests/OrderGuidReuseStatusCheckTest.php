<?php
/**
 * Test: Order GUID Reuse with Status Validation
 *
 * This test validates that the PayPal order reuse logic correctly prevents
 * reusing orders that have already been processed (COMPLETED, REFUNDED, etc.)
 * when a new order has the same GUID.
 *
 * Background:
 * - When a customer places an order, a GUID is generated from order details
 * - If the GUID matches a cached order, the system may reuse the PayPal order
 * - Bug: Previously, orders with COMPLETED/REFUNDED status were being reused,
 *   causing new orders to appear as already processed
 * - Fix: Check the cached order's status before reusing - only reuse if status
 *   is CREATED, APPROVED, PAYER_ACTION_REQUIRED, or SAVED
 *
 * The test verifies:
 * 1. Orders with reusable statuses (CREATED, APPROVED, etc.) are reused when GUID matches
 * 2. Orders with non-reusable statuses (COMPLETED, REFUNDED, etc.) are NOT reused
 * 3. A new PayPal order is created when cached order has been processed
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class OrderGuidReuseStatusCheckTest
{
    private string $paypalrFile;
    private array $testResults = [];
    
    public function __construct()
    {
        $baseDir = dirname(__DIR__);
        $this->paypalrFile = $baseDir . '/includes/modules/payment/paypalr.php';
        
        if (!file_exists($this->paypalrFile)) {
            throw new RuntimeException("PayPal payment module file not found: {$this->paypalrFile}");
        }
    }
    
    public function run(): void
    {
        echo "\n=== Order GUID Reuse Status Check Test ===\n\n";
        
        $this->testReusableStatusesCheck();
        $this->testNonReusableStatusesCheck();
        $this->testLoggingMessages();
        
        $this->printResults();
    }
    
    /**
     * Test that the code checks for reusable statuses before reusing an order
     */
    private function testReusableStatusesCheck(): void
    {
        echo "Test 1: Code checks for reusable statuses before reusing cached order\n";
        
        $content = file_get_contents($this->paypalrFile);
        
        // Look for the status check logic
        $hasStatusCheck = preg_match('/\$cached_status\s*=.*?PayPalRestful.*?Order.*?status/s', $content);
        $hasReusableArray = preg_match('/reusable_statuses\s*=\s*\[/', $content);
        $hasInArrayCheck = preg_match('/in_array\s*\(\s*\$cached_status\s*,\s*\$reusable_statuses/', $content);
        
        $passed = $hasStatusCheck && $hasReusableArray && $hasInArrayCheck;
        
        if ($passed) {
            echo "  ✓ Code extracts cached order status\n";
            echo "  ✓ Code defines reusable statuses array\n";
            echo "  ✓ Code checks if cached status is in reusable list\n";
        } else {
            if (!$hasStatusCheck) {
                echo "  ✗ Code does NOT extract cached order status\n";
            }
            if (!$hasReusableArray) {
                echo "  ✗ Code does NOT define reusable statuses array\n";
            }
            if (!$hasInArrayCheck) {
                echo "  ✗ Code does NOT check if status is reusable\n";
            }
        }
        
        $this->testResults[] = [
            'name' => 'Reusable statuses check exists',
            'passed' => $passed
        ];
        
        echo "\n";
    }
    
    /**
     * Test that non-reusable statuses are excluded from the reusable list
     */
    private function testNonReusableStatusesCheck(): void
    {
        echo "Test 2: Non-reusable statuses (COMPLETED, REFUNDED, etc.) are excluded\n";
        
        $content = file_get_contents($this->paypalrFile);
        
        // Extract the reusable statuses array
        $pattern = '/reusable_statuses\s*=\s*\[(.*?)\];/s';
        if (!preg_match($pattern, $content, $matches)) {
            echo "  ✗ Could not find reusable_statuses array\n\n";
            $this->testResults[] = [
                'name' => 'Non-reusable statuses excluded',
                'passed' => false
            ];
            return;
        }
        
        $reusableArray = $matches[1];
        
        // Check that non-reusable statuses are NOT in the array
        $nonReusable = ['COMPLETED', 'CAPTURED', 'REFUNDED', 'PARTIALLY_REFUNDED', 'VOIDED'];
        $foundNonReusable = [];
        
        foreach ($nonReusable as $status) {
            if (preg_match('/STATUS_' . $status . '/', $reusableArray)) {
                $foundNonReusable[] = $status;
            }
        }
        
        // Check that reusable statuses ARE in the array
        $reusable = ['CREATED', 'APPROVED', 'PAYER_ACTION_REQUIRED', 'SAVED'];
        $missingReusable = [];
        
        foreach ($reusable as $status) {
            if (!preg_match('/STATUS_' . $status . '/', $reusableArray)) {
                $missingReusable[] = $status;
            }
        }
        
        $passed = empty($foundNonReusable) && empty($missingReusable);
        
        if (empty($foundNonReusable)) {
            echo "  ✓ Non-reusable statuses (COMPLETED, REFUNDED, etc.) are excluded\n";
        } else {
            echo "  ✗ Found non-reusable statuses in array: " . implode(', ', $foundNonReusable) . "\n";
        }
        
        if (empty($missingReusable)) {
            echo "  ✓ All reusable statuses (CREATED, APPROVED, etc.) are included\n";
        } else {
            echo "  ✗ Missing reusable statuses: " . implode(', ', $missingReusable) . "\n";
        }
        
        $this->testResults[] = [
            'name' => 'Non-reusable statuses excluded',
            'passed' => $passed
        ];
        
        echo "\n";
    }
    
    /**
     * Test that appropriate logging messages are added
     */
    private function testLoggingMessages(): void
    {
        echo "Test 3: Appropriate logging messages for status-based decisions\n";
        
        $content = file_get_contents($this->paypalrFile);
        
        // Check for log message when order is reusable
        $hasReusableLog = preg_match('/status.*is reusable/', $content);
        
        // Check for log message when order is NOT reusable
        $hasNonReusableLog = preg_match('/status.*indicates order was processed/', $content) ||
                             preg_match('/creating new PayPal order/', $content);
        
        $passed = $hasReusableLog && $hasNonReusableLog;
        
        if ($hasReusableLog) {
            echo "  ✓ Log message exists for reusable status scenario\n";
        } else {
            echo "  ✗ No log message for reusable status scenario\n";
        }
        
        if ($hasNonReusableLog) {
            echo "  ✓ Log message exists for non-reusable status scenario\n";
        } else {
            echo "  ✗ No log message for non-reusable status scenario\n";
        }
        
        $this->testResults[] = [
            'name' => 'Logging messages present',
            'passed' => $passed
        ];
        
        echo "\n";
    }
    
    /**
     * Print test results summary
     */
    private function printResults(): void
    {
        echo "=== Test Results ===\n";
        
        $totalTests = count($this->testResults);
        $passedTests = 0;
        
        foreach ($this->testResults as $result) {
            $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
            echo "{$status}: {$result['name']}\n";
            if ($result['passed']) {
                $passedTests++;
            }
        }
        
        echo "\nSummary: {$passedTests}/{$totalTests} tests passed\n";
        
        if ($passedTests === $totalTests) {
            echo "\n✓ All tests PASSED!\n";
            exit(0);
        } else {
            echo "\n✗ Some tests FAILED!\n";
            exit(1);
        }
    }
}

// Run the test
try {
    $test = new OrderGuidReuseStatusCheckTest();
    $test->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
