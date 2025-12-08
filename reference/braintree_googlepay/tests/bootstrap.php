<?php
// Define paths for stubs
define('DIR_FS_CATALOG', __DIR__ . '/stubs/');
define('DIR_WS_MODULES', '');
define('DIR_FS_ADMIN', __DIR__ . '/stubs/');

// Basic Zen Cart constants used by module
define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS', 'True');
define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE', 'Google Pay');
define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_DESCRIPTION', 'Google Pay');
define('DEFAULT_CURRENCY', 'USD');

define('IS_ADMIN_FLAG', false);

// Stub global functions
function zen_draw_hidden_field($name, $value) {
    return '<input type="hidden" name="' . $name . '" value="' . $value . '" />';
}
function zen_session_name() { return 'zenid'; }
function zen_session_id() { return 'abc123'; }

// Dummy order object
class DummyOrder {
    public $info = ['currency' => 'USD', 'total' => 100.00];
}
$order = new DummyOrder();

// Dummy currencies object for Zen Cart
class DummyCurrencies {
    private $rates = [
        'USD' => 1.0,
        'EUR' => 0.85,
        'GBP' => 0.73,
        'JPY' => 110.0
    ];
    
    public function value($amount, $calculate_currencies = false, $currency_type = '') {
        // In Zen Cart, value() converts from default currency to selected currency
        // For testing, we'll use USD as default and convert to the order currency
        global $order;
        $targetCurrency = $order->info['currency'] ?? 'USD';
        
        if (!isset($this->rates[$targetCurrency])) {
            return $amount;
        }
        
        return $amount * $this->rates[$targetCurrency];
    }
    
    public function get_value($currency) {
        return $this->rates[$currency] ?? 1.0;
    }
    
    public function get_decimal_places($currency) {
        return ($currency === 'JPY') ? 0 : 2;
    }
}
$currencies = new DummyCurrencies();

// base class stub
class base {}

?>
