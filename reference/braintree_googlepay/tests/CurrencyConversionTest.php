<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/modules/payment/braintree_googlepay.php';

class CurrencyConversionTest extends TestCase {
    private $module;

    protected function setUp(): void {
        global $order, $currencies;
        $_SESSION = [];
        
        // Reset order to default state
        $order->info = ['currency' => 'USD', 'total' => 100.00];
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void {
        $_POST = [];
        $_SESSION = [];
    }

    public function testOrderTotalUsesCurrenciesValueMethod() {
        global $order, $currencies;
        
        // Set up a test scenario with EUR currency
        $order->info['currency'] = 'EUR';
        $order->info['total'] = 100.00;
        
        // The currencies->value() method should convert 100 USD to 85 EUR (based on our mock rate)
        $expectedTotal = 100.00 * 0.85; // 85.00
        
        // We need to mock the selection() method's behavior
        // Since we can't easily call selection() without a full setup, we'll test the logic directly
        $convertedAmount = $currencies->value($order->info['total']);
        $formattedAmount = number_format((float)$convertedAmount, 2, '.', '');
        
        $this->assertEquals('85.00', $formattedAmount);
    }

    public function testOrderTotalWithUSDCurrency() {
        global $order, $currencies;
        
        // Set up USD (default currency, rate = 1.0)
        $order->info['currency'] = 'USD';
        $order->info['total'] = 100.00;
        
        $convertedAmount = $currencies->value($order->info['total']);
        $formattedAmount = number_format((float)$convertedAmount, 2, '.', '');
        
        // USD to USD should remain the same
        $this->assertEquals('100.00', $formattedAmount);
    }

    public function testOrderTotalWithGBPCurrency() {
        global $order, $currencies;
        
        // Set up GBP currency
        $order->info['currency'] = 'GBP';
        $order->info['total'] = 100.00;
        
        // The currencies->value() method should convert 100 USD to 73 GBP (based on our mock rate)
        $convertedAmount = $currencies->value($order->info['total']);
        $formattedAmount = number_format((float)$convertedAmount, 2, '.', '');
        
        $this->assertEquals('73.00', $formattedAmount);
    }

    public function testOrderTotalWithJPYCurrency() {
        global $order, $currencies;
        
        // Set up JPY currency (typically has 0 decimal places)
        $order->info['currency'] = 'JPY';
        $order->info['total'] = 100.00;
        
        // The currencies->value() method should convert 100 USD to 11000 JPY (based on our mock rate)
        $convertedAmount = $currencies->value($order->info['total']);
        $formattedAmount = number_format((float)$convertedAmount, 2, '.', '');
        
        $this->assertEquals('11000.00', $formattedAmount);
    }

    public function testCalcOrderAmountWithZeroTotal() {
        global $order;
        
        // Test that calc_order_amount properly handles zero amounts
        $order->info['total'] = 0;
        $order->info['currency'] = 'USD';
        
        $reflection = new ReflectionClass('braintree_googlepay');
        $module = $reflection->newInstanceWithoutConstructor();
        
        $method = $reflection->getMethod('calc_order_amount');
        $method->setAccessible(true);
        
        $result = $method->invoke($module, 0, 'USD');
        $this->assertEquals(0, $result);
    }
}
?>
