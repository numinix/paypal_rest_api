<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once DIR_FS_CATALOG . 'includes/modules/payment/braintree_paypal.php';

class BraintreePaypalTest extends TestCase {
    protected $module;
    protected $order;
    protected $db;
    protected $messageStack;

    protected function setUp(): void {
        global $db, $messageStack, $order, $currencies;
        $this->db = $db = new FakeDB();
        $this->messageStack = $messageStack = new FakeMessageStack();
        $currencies = new FakeCurrencies();
        $order = (object)[
            'info' => [
                'total' => 10.00,
                'currency' => 'USD',
                'order_status' => 1
            ],
            'billing' => [
                'country' => ['id' => 1, 'iso_code_2' => 'US', 'title' => 'United States'],
                'zone_id' => 1,
                'firstname' => 'John',
                'lastname' => 'Doe',
                'company' => '',
                'street_address' => '123 st',
                'city' => 'City',
                'state' => 'State',
                'postcode' => '12345'
            ],
            'customer' => [
                'email_address' => 'cust@example.com'
            ]
        ];
        $_SESSION['currency'] = 'USD';
        $this->module = new braintree_paypal();
    }

    public function testGenerateClientToken() {
        $token = $this->module->generate_client_token();
        $this->assertEquals('token-acct-USD', $token);
    }

    public function testGenerateClientTokenRetries() {
        // Configure mock to fail 2 times before succeeding
        $GLOBALS['mock_token_fail_count'] = 2;
        
        // Create a new instance to reset the fail counter
        $module = new braintree_paypal();
        
        $startTime = microtime(true);
        $token = $module->generate_client_token();
        $endTime = microtime(true);
        
        // Should succeed after retries
        $this->assertEquals('token-acct-USD', $token);
        
        // Should have taken at least 200ms + 500ms = 700ms (0.7s) for 2 retries
        // (allowing some margin for test execution time)
        $elapsedMs = ($endTime - $startTime) * 1000;
        $this->assertGreaterThan(600, $elapsedMs, "Expected at least 600ms for 2 retries");
        
        // Cleanup
        unset($GLOBALS['mock_token_fail_count']);
    }

    public function testGenerateClientTokenExhaustsRetries() {
        // Configure mock to fail all attempts (4 total: 1 initial + 3 retries)
        $GLOBALS['mock_token_fail_count'] = 4;
        
        // Create a new instance to reset the fail counter
        $module = new braintree_paypal();
        
        $token = $module->generate_client_token();
        
        // Should return false after all retries exhausted
        $this->assertFalse($token);
        
        // Cleanup
        unset($GLOBALS['mock_token_fail_count']);
    }

    public function testGetPaypalLocaleByCountry() {
        $this->assertEquals('en_US', $this->module->getPaypalLocaleByCountry('US'));
        $this->assertEquals('', $this->module->getPaypalLocaleByCountry('ZZ'));
    }

    public function testJavascriptValidation() {
        $this->assertFalse($this->module->javascript_validation());
    }

    public function testProcessButton() {
        $_POST['payment_method_nonce'] = 'nonce';
        $html = $this->module->process_button();
        $this->assertStringContainsString("nonce", $html);
    }

    public function testProcessButtonAjax() {
        $_POST['payment_method_nonce'] = 'nonce';
        $result = $this->module->process_button_ajax();
        $this->assertEquals('nonce', $result['ccFields']['bt_nonce']);
        $this->assertEquals('paypal', $result['ccFields']['bt_payment_type']);
        $this->assertEquals('USD', $result['ccFields']['bt_currency_code']);
    }

    public function testBeforeProcess() {
        $this->assertTrue($this->module->before_process());
    }

    public function testAfterProcessInsertsHistory() {
        global $insert_id;
        $insert_id = 5;
        $_SESSION['braintree_transaction_id'] = 'txn';
        $_SESSION['braintree_payment_status'] = 'Completed';
        $_SESSION['braintree_currency'] = 'USD';
        $_SESSION['braintree_amount'] = '10.00';
        $this->module->after_process();
        $this->assertNotEmpty($GLOBALS['db_performed']);
        $this->assertArrayNotHasKey('braintree_transaction_id', $_SESSION);
    }

    public function testGetTransactionId() {
        $this->assertEquals('txn-10', $this->module->getTransactionId(10));
    }

    public function testDoRefund() {
        $this->module->_doRefund(1, 'Full', '');
        $this->assertStringContainsString('orders.php', $GLOBALS['last_redirect']);
    }

    public function testDoCapture() {
        $this->assertTrue($this->module->_doCapt(1));
    }

    public function testInstallCreatesTable() {
        $this->module->install();
        $this->assertTrue(isset($GLOBALS['table_created']) && $GLOBALS['table_created']);
    }

    public function testCheckReturnsCount() {
        $this->db->nextRecordSet = new FakeRecordSet([['configuration_value'=>1]]);
        $this->assertEquals(1, $this->module->check());
    }

    public function testKeysList() {
        $keys = $this->module->keys();
        $this->assertContains('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS', $keys);
    }

    public function testRemoveDeletesConfig() {
        $this->module->remove();
        $this->assertNotEmpty($this->messageStack->messages);
    }

    public function testAdminNotificationReturnsString() {
        $output = $this->module->admin_notification(1);
        $this->assertSame('', $output);
    }

    public function testCurrencyConversionInSelection() {
        global $order, $currencies;
        
        // Set order total to 100 USD (default currency)
        $order->info['total'] = 100.00;
        $order->info['currency'] = 'EUR';
        
        $selection = $this->module->selection();
        
        // Amount should be converted to EUR (100 * 0.85 = 85.00)
        $this->assertStringContainsString('"amount":"85.00"', $selection['fields'][0]['field']);
        $this->assertStringContainsString('"currency":"EUR"', $selection['fields'][0]['field']);
    }

    public function testCurrencyConversionWithDifferentCurrencies() {
        global $order, $currencies;
        
        // Test with GBP as selected currency
        // Order total is 100 USD, convert to GBP (100 * 0.75 = 75)
        $order->info['total'] = 100.00;
        $order->info['currency'] = 'GBP';
        
        $selection = $this->module->selection();
        
        // Verify the amount is properly converted
        $this->assertStringContainsString('"amount":"75.00"', $selection['fields'][0]['field']);
        $this->assertStringContainsString('"currency":"GBP"', $selection['fields'][0]['field']);
    }

    public function testCurrencyConversionWithSameCurrency() {
        global $order, $currencies;
        
        // Test with USD (default) - no conversion needed
        $order->info['total'] = 50.00;
        $order->info['currency'] = 'USD';
        
        $selection = $this->module->selection();
        
        // Amount should remain 50.00
        $this->assertStringContainsString('"amount":"50.00"', $selection['fields'][0]['field']);
        $this->assertStringContainsString('"currency":"USD"', $selection['fields'][0]['field']);
    }

    public function testCurrencyConversionWithJPY() {
        global $order, $currencies;
        
        // Test with JPY (no decimal places)
        // Order total is 100 USD, convert to JPY (100 * 110 = 11000)
        $order->info['total'] = 100.00;
        $order->info['currency'] = 'JPY';
        
        $selection = $this->module->selection();
        
        // JPY should have no decimal places but our conversion will format to 2 decimals
        $this->assertStringContainsString('"amount":"11000.00"', $selection['fields'][0]['field']);
        $this->assertStringContainsString('"currency":"JPY"', $selection['fields'][0]['field']);
    }

    public function testUnpaidStatusKeyIsIncluded() {
        $keys = $this->module->keys();
        $this->assertContains('MODULE_PAYMENT_BRAINTREE_PAYPAL_UNPAID_STATUS_ID', $keys);
    }
}
?>
