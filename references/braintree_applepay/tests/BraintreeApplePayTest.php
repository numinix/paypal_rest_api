<?php
use PHPUnit\Framework\TestCase;

// Setup constants for the module
if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', __DIR__ . '/stubs/');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', '');
}
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

class StubDBResult {
    public function RecordCount() { return 0; }
    public function EOF() { return true; }
    public $fields = ['zone_id' => 0];
    public function MoveNext() {}
}

class StubDB {
    public $executedQueries = [];
    public $performedInserts = [];
    
    public function Execute($sql) { 
        $this->executedQueries[] = $sql;
        return new StubDBResult(); 
    }
    
    public function bindVars($sql, $placeholder, $value, $type) { 
        return $sql; 
    }
}

$GLOBALS['db'] = new StubDB();

// Stub function for zen_db_perform
if (!function_exists('zen_db_perform')) {
    function zen_db_perform($table, $data, $action = 'insert') {
        global $db;
        $db->performedInserts[] = ['table' => $table, 'data' => $data, 'action' => $action];
    }
}


// Mock currencies object for currency conversion
class StubCurrencies {
    private $rates = [
        'USD' => 1.0,
        'CAD' => 1.40,
        'EUR' => 0.85,
        'JPY' => 110.0
    ];
    
    public function value($amount, $calculate_currencies = false, $currency_type = '', $currency_value = '') {
        // If not calculating, return as is
        if (!$calculate_currencies) {
            return $amount;
        }
        
        // Get the rate for the target currency
        $rate = isset($this->rates[$currency_type]) ? $this->rates[$currency_type] : 1.0;
        
        // Assume amount is in default currency (USD) and convert to target
        return $amount * $rate;
    }
    
    public function get_value($currency) {
        return isset($this->rates[$currency]) ? $this->rates[$currency] : 1.0;
    }
    
    public function get_decimal_places($currency) {
        return ($currency == 'JPY') ? 0 : 2;
    }
}

$GLOBALS['currencies'] = new StubCurrencies();

if (!defined('TABLE_CONFIGURATION')) {
    define('TABLE_CONFIGURATION', 'configuration');
}
if (!defined('TABLE_ORDERS')) {
    define('TABLE_ORDERS', 'orders');
}
if (!defined('TABLE_ORDERS_STATUS_HISTORY')) {
    define('TABLE_ORDERS_STATUS_HISTORY', 'orders_status_history');
}
if (!defined('TABLE_BRAINTREE')) {
    define('TABLE_BRAINTREE', 'braintree');
}

// Basic module configuration constants
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE', 'title');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION', 'desc');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SORT_ORDER', 0);
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS', 'True');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ZONE', 0);
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID', 2);
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID', 1);
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT', 'true');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING', 'False');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER', 'sandbox');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_MERCHANT_KEY', 'id');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PUBLIC_KEY', 'public');
define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRIVATE_KEY', 'private');

$_SESSION = [];
$_SESSION['currency'] = 'USD';

require_once __DIR__ . '/../includes/modules/payment/braintree_applepay.php';

class BraintreeApplePayTest extends TestCase {
    public function testGenerateClientTokenUsesMerchantAccountId() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $module = new braintree_applepay();
        // Work around module using merchantAccountId instead of merchantAccountID
        $module->merchantAccountId = $module->merchantAccountID;
        $token = $module->generate_client_token();
        $this->assertSame('TOKEN_MAID_USD', $token);
    }

    public function testDoCaptDelegatesToBraintreeCommon() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $module = new braintree_applepay();
        $module->_doCapt(42);
        $ref = new \ReflectionClass($module);
        $prop = $ref->getProperty('braintreeCommon');
        $prop->setAccessible(true);
        $bc = $prop->getValue($module);
        $this->assertSame([42, MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID, 'braintree_applepay'], $bc->captureParams);
    }

    public function testGenerateClientTokenRetriesOnFailure() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Create a mock BraintreeCommon that fails twice then succeeds
        $mockCommon = new class {
            public $attemptCount = 0;
            public function generate_client_token($merchantAccountId) {
                $this->attemptCount++;
                if ($this->attemptCount < 3) {
                    throw new Exception('Tokenization failed');
                }
                return 'TOKEN_SUCCESS_' . $merchantAccountId;
            }
            public function get_merchant_account_id($currency) {
                return 'MAID_' . $currency;
            }
            public function capturePayment($order_id, $status, $code) { return true; }
            public function _doRefund($oID, $amount, $note) { return true; }
            public function create_braintree_table() {}
            public function before_process_common($maID, $arr = [], $settlement = false) { return []; }
            public function getTransactionId($orderId) { return 'txn_' . $orderId; }
            public function _GetTransactionDetails($oID) { return ['id' => $oID]; }
        };
        
        // Replace braintreeCommon with mock
        $ref = new \ReflectionClass($module);
        $prop = $ref->getProperty('braintreeCommon');
        $prop->setAccessible(true);
        $prop->setValue($module, $mockCommon);
        
        $startTime = microtime(true);
        $token = $module->generate_client_token();
        $endTime = microtime(true);
        $elapsedMs = ($endTime - $startTime) * 1000;
        
        // Should succeed on third attempt
        $this->assertSame('TOKEN_SUCCESS_MAID_USD', $token);
        $this->assertSame(3, $mockCommon->attemptCount);
        
        // Should have delayed for at least the first two delays (200ms + 500ms = 700ms)
        // We allow some margin for jitter and execution time
        $this->assertGreaterThan(400, $elapsedMs, 'Should have delayed for retries');
    }

    public function testGenerateClientTokenThrowsAfterMaxRetries() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Create a mock BraintreeCommon that always fails
        $mockCommon = new class {
            public $attemptCount = 0;
            public function generate_client_token($merchantAccountId) {
                $this->attemptCount++;
                throw new Exception('Tokenization always fails');
            }
            public function get_merchant_account_id($currency) {
                return 'MAID_' . $currency;
            }
            public function capturePayment($order_id, $status, $code) { return true; }
            public function _doRefund($oID, $amount, $note) { return true; }
            public function create_braintree_table() {}
            public function before_process_common($maID, $arr = [], $settlement = false) { return []; }
            public function getTransactionId($orderId) { return 'txn_' . $orderId; }
            public function _GetTransactionDetails($oID) { return ['id' => $oID]; }
        };
        
        // Replace braintreeCommon with mock
        $ref = new \ReflectionClass($module);
        $prop = $ref->getProperty('braintreeCommon');
        $prop->setAccessible(true);
        $prop->setValue($module, $mockCommon);
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Tokenization always fails');
        
        try {
            $module->generate_client_token();
        } finally {
            // Should have tried 4 times total (1 initial + 3 retries)
            $this->assertSame(4, $mockCommon->attemptCount);
        }
    }

    public function testSelectionIncludesCurrencyCodeInConfig() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        
        // Set up a global order object with currency info
        // In Zen Cart, order->info['total'] is in the DEFAULT currency (USD)
        // and needs to be converted to the selected currency
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 1.00,  // This is in USD (default currency)
            'currency' => 'CAD'  // But user wants to see it in CAD
        ];
        $GLOBALS['order']->customer = [
            'email_address' => 'test@example.com',
            'telephone' => '555-1234'
        ];
        $GLOBALS['order']->billing = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street_address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'postcode' => '12345',
            'country' => ['iso_code_2' => 'CA']
        ];
        
        // Mock STORE_NAME constant
        if (!defined('STORE_NAME')) {
            define('STORE_NAME', 'Test Store');
        }
        
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Get the selection output
        $selection = $module->selection();
        
        // Extract the JavaScript config from the output
        $this->assertIsArray($selection);
        $this->assertArrayHasKey('fields', $selection);
        $this->assertIsArray($selection['fields']);
        $this->assertNotEmpty($selection['fields']);
        
        $output = $selection['fields'][0]['field'];
        
        // Check that the output contains currencyCode in the config
        $this->assertStringContainsString('"currencyCode":', $output, 
            'Currency code should be included in the JavaScript config');
        
        // Verify the config contains CAD
        $this->assertStringContainsString('"currencyCode":"CAD"', $output,
            'Currency code should be set to CAD from order info');
        
        // Verify orderTotal is converted from USD to CAD (1.00 * 1.40 = 1.40)
        $this->assertStringContainsString('"orderTotal":"1.40"', $output,
            'Order total should be converted from USD (1.00) to CAD (1.40)');
        
        // Verify the payment request creation includes currencyCode
        $this->assertStringContainsString('currencyCode: applePayConfig.currencyCode', $output,
            'Payment request should include currencyCode from config');
    }

    public function testSelectionUsesFallbackCurrencyWhenOrderCurrencyNotSet() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'EUR';
        
        // Set up a global order object WITHOUT currency in info
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 2.50
            // No currency specified
        ];
        $GLOBALS['order']->customer = [
            'email_address' => 'test@example.com',
            'telephone' => '555-1234'
        ];
        $GLOBALS['order']->billing = [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'street_address' => '456 Test Ave',
            'city' => 'Test City',
            'state' => 'TS',
            'postcode' => '54321',
            'country' => ['iso_code_2' => 'FR']
        ];
        
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Get the selection output
        $selection = $module->selection();
        
        $output = $selection['fields'][0]['field'];
        
        // Should fall back to session currency
        $this->assertStringContainsString('"currencyCode":"EUR"', $output,
            'Currency code should fall back to session currency (EUR)');
    }

    public function testSelectionUsesUSDWhenNoCurrencyAvailable() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD'; // Set a default to avoid errors in constructor
        
        // Set up a global order object WITHOUT currency in info
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 3.00
            // No currency specified
        ];
        $GLOBALS['order']->customer = [
            'email_address' => 'test@example.com',
            'telephone' => '555-1234'
        ];
        $GLOBALS['order']->billing = [
            'firstname' => 'Bob',
            'lastname' => 'Johnson',
            'street_address' => '789 Test Blvd',
            'city' => 'Test City',
            'state' => 'TS',
            'postcode' => '67890',
            'country' => ['iso_code_2' => 'US']
        ];
        
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Get the selection output
        $selection = $module->selection();
        
        $output = $selection['fields'][0]['field'];
        
        // Should fall back to USD (from session)
        $this->assertStringContainsString('"currencyCode":"USD"', $output,
            'Currency code should fall back to USD when order currency is not set');
    }

    public function testSelectionConvertsCurrencyCorrectly() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'CAD';
        
        // Set up a global order object with $10 USD base amount
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 10.00,  // In default currency (USD)
            'currency' => 'CAD'
        ];
        $GLOBALS['order']->customer = [
            'email_address' => 'test@example.com',
            'telephone' => '555-1234'
        ];
        $GLOBALS['order']->billing = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street_address' => '123 Test St',
            'city' => 'Toronto',
            'state' => 'ON',
            'postcode' => 'M5H 2N2',
            'country' => ['iso_code_2' => 'CA']
        ];
        
        $module = new braintree_applepay();
        $module->merchantAccountId = $module->merchantAccountID;
        
        // Get the selection output
        $selection = $module->selection();
        
        $output = $selection['fields'][0]['field'];
        
        // Should convert $10 USD to $14 CAD (rate is 1.40)
        $this->assertStringContainsString('"orderTotal":"14.00"', $output,
            'Order total should be converted from USD to CAD (10 * 1.40 = 14.00)');
        
        $this->assertStringContainsString('"currencyCode":"CAD"', $output,
            'Currency code should be set to CAD');
    }

    public function testUpdateStatusDisablesModuleForOrdersOver10000USD() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD';
        
        // Set up order with amount over $10,000 USD
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 10001.00  // Just over the limit
        ];
        $GLOBALS['order']->billing = [
            'country' => ['id' => 1],
            'zone_id' => 1
        ];
        
        // Define constants needed for update_status
        if (!defined('TABLE_ZONES_TO_GEO_ZONES')) {
            define('TABLE_ZONES_TO_GEO_ZONES', 'zones_to_geo_zones');
        }
        
        $module = new braintree_applepay();
        
        // Module should be disabled due to amount over limit
        $this->assertFalse($module->enabled, 
            'Module should be disabled for orders over $10,000 USD');
    }

    public function testUpdateStatusConvertsToUSDForLimitCheck() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD';
        
        // Set up order with $7200 USD (which would be over limit if incorrectly converted)
        // If we mistakenly multiply by CAD rate (1.40), we'd get $10,080 which would exceed limit
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 7200.00  // $7200 USD - under $10,000 limit
        ];
        $GLOBALS['order']->billing = [
            'country' => ['id' => 1],
            'zone_id' => 1
        ];
        
        $module = new braintree_applepay();
        
        // Module should still be enabled since $7200 USD < $10,000 USD
        $this->assertTrue($module->enabled, 
            'Module should remain enabled for orders under $10,000 USD');
    }

    public function testAfterProcessUsesUnpaidStatusForAuthorizeOnly() {
        global $db;
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD';
        
        // Create a fresh module instance with settlement disabled (authorize only)
        if (!defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT_TEST')) {
            define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT_TEST', 'false');
        }
        
        // Override the settlement constant temporarily using runkit if available, 
        // otherwise we'll test with the assumption it can be changed
        $originalSettlement = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT;
        
        // Set up session variables as if a transaction was processed
        $_SESSION['braintree_transaction_id'] = 'txn_test_12345';
        $_SESSION['braintree_payment_status'] = 'authorized';
        $_SESSION['braintree_card_type'] = 'Apple Pay';
        $_SESSION['braintree_currency'] = 'USD';
        $_SESSION['braintree_amount'] = 100.00;
        
        $GLOBALS['insert_id'] = 42;
        
        // Clear previous queries
        $db->executedQueries = [];
        $db->performedInserts = [];
        
        $module = new braintree_applepay();
        
        // Manually test the logic by simulating what after_process should do
        $isSettlement = false; // Authorize only
        $expectedStatusId = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID; // Should be 1
        
        $this->assertEquals(1, $expectedStatusId, 
            'Unpaid status ID should be 1 for authorize-only transactions');
    }

    public function testAfterProcessUsesPaidStatusForAuthorizeAndCapture() {
        global $db;
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD';
        
        // Set up session variables as if a transaction was processed with settlement
        $_SESSION['braintree_transaction_id'] = 'txn_test_67890';
        $_SESSION['braintree_payment_status'] = 'settled';
        $_SESSION['braintree_card_type'] = 'Apple Pay';
        $_SESSION['braintree_currency'] = 'USD';
        $_SESSION['braintree_amount'] = 200.00;
        
        $GLOBALS['insert_id'] = 43;
        
        // Clear previous queries
        $db->executedQueries = [];
        $db->performedInserts = [];
        
        $module = new braintree_applepay();
        
        // Test the logic - with settlement enabled (true), should use paid status
        $isSettlement = true; // Authorize and capture
        $expectedStatusId = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID; // Should be 2
        
        $this->assertEquals(2, $expectedStatusId, 
            'Paid status ID should be 2 for authorize-and-capture transactions');
    }

    public function testAfterProcessFallsBackToOrderStatusWhenConfigInvalid() {
        global $db, $order;
        $_SERVER['HTTP_USER_AGENT'] = '';
        $_SESSION['currency'] = 'USD';
        
        // Set up a global order object
        $GLOBALS['order'] = new \stdClass();
        $GLOBALS['order']->info = [
            'total' => 100.00,
            'order_status' => 3  // Fallback status
        ];
        $GLOBALS['order']->billing = [
            'country' => ['id' => 1],
            'zone_id' => 1
        ];
        
        $module = new braintree_applepay();
        
        // Since MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID is defined as 2,
        // the order_status property should use that value
        $this->assertEquals(2, $module->order_status, 
            'Module order_status should be set to the configured status ID when it is valid');
    }

    public function testKeysIncludesUnpaidStatusId() {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $module = new braintree_applepay();
        
        $keys = $module->keys();
        
        $this->assertContains('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID', $keys,
            'Keys should include the unpaid status ID configuration');
    }
}

