<?php
/**
 * Test: Wallet createPayPalOrder Method Visibility
 *
 * This test validates that the createPayPalOrder method in wallet payment modules
 * (Apple Pay, Google Pay, Venmo) is publicly accessible so it can be called from
 * PayPalCommon::processWalletConfirmation().
 *
 * Background:
 * - When processing wallet payments, PayPalCommon::processWalletConfirmation() needs
 *   to call $this->paymentModule->createPayPalOrder()
 * - If this method is protected, it causes a fatal error:
 *   "Call to protected method paypalr_applepay::createPayPalOrder() from scope PayPalCommon"
 *
 * The test verifies:
 * 1. The createPayPalOrder method exists in each wallet module
 * 2. The method has public visibility (not protected or private)
 * 3. PayPalCommon::processWalletConfirmation calls this method correctly
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

class WalletCreatePayPalOrderVisibilityTest
{
    private array $walletModules = [
        'paypalr_applepay' => 'includes/modules/payment/paypalr_applepay.php',
        'paypalr_googlepay' => 'includes/modules/payment/paypalr_googlepay.php',
        'paypalr_venmo' => 'includes/modules/payment/paypalr_venmo.php',
    ];
    private string $commonFile;
    private array $testResults = [];
    
    public function __construct()
    {
        $baseDir = dirname(__DIR__);
        $this->commonFile = $baseDir . '/includes/modules/payment/paypal/paypal_common.php';
        
        // Verify common file exists
        if (!file_exists($this->commonFile)) {
            throw new RuntimeException("PayPal common file not found: {$this->commonFile}");
        }
        
        // Verify wallet module files exist
        foreach ($this->walletModules as $module => $relativePath) {
            $fullPath = $baseDir . '/' . $relativePath;
            if (!file_exists($fullPath)) {
                throw new RuntimeException("Wallet module file not found: {$fullPath}");
            }
            $this->walletModules[$module] = $fullPath;
        }
    }
    
    public function run(): void
    {
        echo "\n=== Wallet createPayPalOrder Method Visibility Test ===\n\n";
        
        $this->testCreatePayPalOrderMethodExists();
        $this->testCreatePayPalOrderIsPublic();
        $this->testPayPalCommonCallsCreatePayPalOrder();
        
        $this->printResults();
    }
    
    /**
     * Test that createPayPalOrder method exists in each wallet module
     */
    private function testCreatePayPalOrderMethodExists(): void
    {
        echo "Test 1: createPayPalOrder method exists in wallet modules\n";
        
        $allPassed = true;
        foreach ($this->walletModules as $moduleName => $filePath) {
            $content = file_get_contents($filePath);
            
            // Look for the createPayPalOrder method definition
            $pattern = '/function\s+createPayPalOrder\s*\(/';
            $found = preg_match($pattern, $content);
            
            if ($found) {
                echo "  ✓ {$moduleName}: createPayPalOrder method found\n";
            } else {
                echo "  ✗ {$moduleName}: createPayPalOrder method NOT FOUND\n";
                $allPassed = false;
            }
        }
        
        $this->testResults[] = [
            'name' => 'createPayPalOrder method exists',
            'passed' => $allPassed
        ];
        
        echo "\n";
    }
    
    /**
     * Test that createPayPalOrder method has public visibility in each wallet module
     */
    private function testCreatePayPalOrderIsPublic(): void
    {
        echo "Test 2: createPayPalOrder method is public in wallet modules\n";
        
        $allPassed = true;
        foreach ($this->walletModules as $moduleName => $filePath) {
            $content = file_get_contents($filePath);
            
            // Look for public function createPayPalOrder
            $publicPattern = '/public\s+function\s+createPayPalOrder\s*\(/';
            $isPublic = preg_match($publicPattern, $content);
            
            // Look for protected function createPayPalOrder (should NOT match)
            $protectedPattern = '/protected\s+function\s+createPayPalOrder\s*\(/';
            $isProtected = preg_match($protectedPattern, $content);
            
            // Look for private function createPayPalOrder (should NOT match)
            $privatePattern = '/private\s+function\s+createPayPalOrder\s*\(/';
            $isPrivate = preg_match($privatePattern, $content);
            
            if ($isPublic && !$isProtected && !$isPrivate) {
                echo "  ✓ {$moduleName}: createPayPalOrder is PUBLIC\n";
            } else if ($isProtected) {
                echo "  ✗ {$moduleName}: createPayPalOrder is PROTECTED (should be public)\n";
                $allPassed = false;
            } else if ($isPrivate) {
                echo "  ✗ {$moduleName}: createPayPalOrder is PRIVATE (should be public)\n";
                $allPassed = false;
            } else {
                echo "  ✗ {$moduleName}: createPayPalOrder visibility not determined\n";
                $allPassed = false;
            }
        }
        
        $this->testResults[] = [
            'name' => 'createPayPalOrder method is public',
            'passed' => $allPassed
        ];
        
        echo "\n";
    }
    
    /**
     * Test that PayPalCommon::processWalletConfirmation calls createPayPalOrder
     */
    private function testPayPalCommonCallsCreatePayPalOrder(): void
    {
        echo "Test 3: PayPalCommon::processWalletConfirmation calls createPayPalOrder\n";
        
        $content = file_get_contents($this->commonFile);
        
        // Extract the processWalletConfirmation method
        $pattern = '/function\s+processWalletConfirmation.*?\{(.*?)(?=\n\s{4}(?:public|protected|private|\/\*\*|\}))/s';
        if (!preg_match($pattern, $content, $matches)) {
            echo "  ✗ Could not find processWalletConfirmation method\n\n";
            $this->testResults[] = [
                'name' => 'PayPalCommon calls createPayPalOrder',
                'passed' => false
            ];
            return;
        }
        
        $methodBody = $matches[1];
        
        // Check if it calls $this->paymentModule->createPayPalOrder
        $callPattern = '/\$this->paymentModule->createPayPalOrder\s*\(/';
        $callsMethod = preg_match($callPattern, $methodBody);
        
        if ($callsMethod) {
            echo "  ✓ processWalletConfirmation calls \$this->paymentModule->createPayPalOrder()\n";
            echo "  ✓ This requires createPayPalOrder to be public in wallet modules\n";
        } else {
            echo "  ✗ processWalletConfirmation does NOT call \$this->paymentModule->createPayPalOrder()\n";
        }
        
        $this->testResults[] = [
            'name' => 'PayPalCommon calls createPayPalOrder',
            'passed' => $callsMethod
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
    $test = new WalletCreatePayPalOrderVisibilityTest();
    $test->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
