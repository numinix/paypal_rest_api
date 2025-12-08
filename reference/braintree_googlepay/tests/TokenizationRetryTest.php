<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/modules/payment/braintree_googlepay.php';

class TokenizationRetryTest extends TestCase {
    private $module;
    private $mockBraintreeCommon;

    protected function setUp(): void {
        $_SESSION = [];
        global $order;
        if (!isset($order)) {
            $order = new stdClass();
            $order->info = ['currency' => 'USD', 'total' => 100.00];
            $order->customer = ['email_address' => 'test@example.com'];
            $order->billing = [
                'firstname' => 'John',
                'lastname' => 'Doe',
                'street_address' => '123 Main St',
                'city' => 'Test City',
                'state' => 'TX',
                'postcode' => '12345',
                'country' => ['iso_code_2' => 'US']
            ];
        }
        
        // Define additional constants needed for selection method
        if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID')) {
            define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID', 'test_merchant_id');
        }
        if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT')) {
            define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT', 'TEST');
        }
        if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS')) {
            define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS', 'False');
        }
        if (!defined('STORE_NAME')) {
            define('STORE_NAME', 'Test Store');
        }
    }

    protected function tearDown(): void {
        $_POST = [];
        $_SESSION = [];
    }

    /**
     * Test that successful token generation on first attempt doesn't trigger retries
     */
    public function testSuccessfulTokenGenerationOnFirstAttempt() {
        // Create a mock BraintreeCommon that succeeds on first call
        $mockCommon = $this->createMock(BraintreeCommon::class);
        $mockCommon->expects($this->once())
            ->method('generate_client_token')
            ->willReturn('test_token_123');
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
        
        // Inject the mock
        $property = $reflection->getProperty('braintreeCommon');
        $property->setAccessible(true);
        $property->setValue($this->module, $mockCommon);
        
        // Set required properties
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);
        $enabledProperty->setValue($this->module, true);
        
        $tokenProperty = $reflection->getProperty('tokenizationKey');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($this->module, '');
        
        $debugProperty = $reflection->getProperty('debug_logging');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->module, false);
        
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($this->module, 'braintree_googlepay');
        
        // Set merchantAccountID dynamically
        $this->module->merchantAccountID = 'test_merchant_account';
        
        $result = $this->module->selection();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('braintree_googlepay', $result['id']);
    }

    /**
     * Test that token generation retries on failure and succeeds on second attempt
     */
    public function testTokenGenerationSucceedsOnSecondAttempt() {
        // Create a mock that fails once then succeeds
        $mockCommon = $this->createMock(BraintreeCommon::class);
        $mockCommon->expects($this->exactly(2))
            ->method('generate_client_token')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('Temporary failure')),
                'test_token_456'
            );
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
        
        // Inject the mock
        $property = $reflection->getProperty('braintreeCommon');
        $property->setAccessible(true);
        $property->setValue($this->module, $mockCommon);
        
        // Set required properties
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);
        $enabledProperty->setValue($this->module, true);
        
        $debugProperty = $reflection->getProperty('debug_logging');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->module, false);
        
        $tokenProperty = $reflection->getProperty('tokenizationKey');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($this->module, '');
        
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($this->module, 'braintree_googlepay');
        
        // Set merchantAccountID dynamically
        $this->module->merchantAccountID = 'test_merchant_account';
        
        $result = $this->module->selection();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('braintree_googlepay', $result['id']);
    }

    /**
     * Test that token generation retries on failure and succeeds on third attempt
     */
    public function testTokenGenerationSucceedsOnThirdAttempt() {
        // Create a mock that fails twice then succeeds
        $mockCommon = $this->createMock(BraintreeCommon::class);
        $mockCommon->expects($this->exactly(3))
            ->method('generate_client_token')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('Temporary failure 1')),
                $this->throwException(new Exception('Temporary failure 2')),
                'test_token_789'
            );
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
        
        // Inject the mock
        $property = $reflection->getProperty('braintreeCommon');
        $property->setAccessible(true);
        $property->setValue($this->module, $mockCommon);
        
        // Set required properties
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);
        $enabledProperty->setValue($this->module, true);
        
        $debugProperty = $reflection->getProperty('debug_logging');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->module, false);
        
        $tokenProperty = $reflection->getProperty('tokenizationKey');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($this->module, '');
        
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($this->module, 'braintree_googlepay');
        
        // Set merchantAccountID dynamically
        $this->module->merchantAccountID = 'test_merchant_account';
        
        $result = $this->module->selection();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('braintree_googlepay', $result['id']);
    }

    /**
     * Test that after all retries fail, falls back to tokenization key if available
     */
    public function testFallsBackToTokenizationKeyAfterAllRetriesFail() {
        // Create a mock that always fails
        $mockCommon = $this->createMock(BraintreeCommon::class);
        $mockCommon->expects($this->exactly(4)) // initial + 3 retries
            ->method('generate_client_token')
            ->willThrowException(new Exception('Permanent failure'));
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
        
        // Inject the mock
        $property = $reflection->getProperty('braintreeCommon');
        $property->setAccessible(true);
        $property->setValue($this->module, $mockCommon);
        
        // Set required properties
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);
        $enabledProperty->setValue($this->module, true);
        
        $debugProperty = $reflection->getProperty('debug_logging');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->module, false);
        
        $tokenProperty = $reflection->getProperty('tokenizationKey');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($this->module, 'fallback_tokenization_key');
        
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($this->module, 'braintree_googlepay');
        
        // Set merchantAccountID dynamically
        $this->module->merchantAccountID = 'test_merchant_account';
        
        $result = $this->module->selection();
        
        // Should still return a valid selection with tokenization key fallback
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('braintree_googlepay', $result['id']);
    }

    /**
     * Test that when all retries fail and no tokenization key is available, error message is shown
     */
    public function testShowsErrorMessageWhenAllRetriesFailAndNoFallback() {
        // Create a mock that always fails
        $mockCommon = $this->createMock(BraintreeCommon::class);
        $mockCommon->expects($this->exactly(4)) // initial + 3 retries
            ->method('generate_client_token')
            ->willThrowException(new Exception('Permanent failure'));
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
        
        // Inject the mock
        $property = $reflection->getProperty('braintreeCommon');
        $property->setAccessible(true);
        $property->setValue($this->module, $mockCommon);
        
        // Set required properties
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);
        $enabledProperty->setValue($this->module, true);
        
        $debugProperty = $reflection->getProperty('debug_logging');
        $debugProperty->setAccessible(true);
        $debugProperty->setValue($this->module, false);
        
        $tokenProperty = $reflection->getProperty('tokenizationKey');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($this->module, '');
        
        $codeProperty = $reflection->getProperty('code');
        $codeProperty->setAccessible(true);
        $codeProperty->setValue($this->module, 'braintree_googlepay');
        
        // Set merchantAccountID dynamically
        $this->module->merchantAccountID = 'test_merchant_account';
        
        $result = $this->module->selection();
        
        // Should return error message to user
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertIsArray($result['fields']);
        $this->assertNotEmpty($result['fields']);
        // Check that the error message is present
        $fieldsString = json_encode($result['fields']);
        $this->assertStringContainsString('Incorrect Braintree Configuration', $fieldsString);
    }
}
?>
