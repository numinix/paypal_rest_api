<?php
// Bootstrap file for PHPUnit tests
// Define required constants
if (!defined('DIR_FS_CATALOG')) define('DIR_FS_CATALOG', __DIR__ . '/../');
if (!defined('DIR_WS_MODULES')) define('DIR_WS_MODULES', 'includes/modules/');
if (!defined('IS_ADMIN_FLAG')) define('IS_ADMIN_FLAG', false);
if (!defined('DEFAULT_CURRENCY')) define('DEFAULT_CURRENCY', 'USD');

// Table and filename constants used by the module
if (!defined('FILENAME_CHECKOUT_PAYMENT')) define('FILENAME_CHECKOUT_PAYMENT', 'checkout_payment.php');
if (!defined('FILENAME_ORDERS')) define('FILENAME_ORDERS', 'orders.php');
if (!defined('TABLE_ZONES_TO_GEO_ZONES')) define('TABLE_ZONES_TO_GEO_ZONES', 'zones_to_geo_zones');
if (!defined('TABLE_CONFIGURATION')) define('TABLE_CONFIGURATION', 'configuration');
if (!defined('TABLE_ORDERS')) define('TABLE_ORDERS', 'orders');
if (!defined('TABLE_ORDERS_STATUS_HISTORY')) define('TABLE_ORDERS_STATUS_HISTORY', 'orders_status_history');
if (!defined('TABLE_BRAINTREE')) define('TABLE_BRAINTREE', 'braintree');
if (!defined('NOTIFY_PAYMENT_BRAINTREE_UNINSTALLED')) define('NOTIFY_PAYMENT_BRAINTREE_UNINSTALLED', 'BRAINTREE_UNINSTALLED');

// Module configuration constants
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_TITLE')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_TITLE', 'PayPal');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_DESCRIPTION')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_DESCRIPTION', 'desc');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER', '0');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS', 'True');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE', 0);
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS', 1);
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING', 'Alerts Only');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_SERVER')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_SERVER', 'sandbox');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_MERCHANT_KEY')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_MERCHANT_KEY', 'mkey');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_PUBLIC_KEY')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_PUBLIC_KEY', 'pkey');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_PRIVATE_KEY')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_PRIVATE_KEY', 'prikey');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED', 'Payment failed');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT', 'true');
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_REFUNDED_STATUS_ID')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_REFUNDED_STATUS_ID', 2);
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_PENDING_STATUS_ID')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_PENDING_STATUS_ID', 3);
if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR')) define('MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR', '#orderTotal');
?>
<?php
// Stub global functions used by the module
function zen_db_perform($table, $data) {
    $GLOBALS['db_performed'][] = [$table, $data];
}
function zen_redirect($url) {
    $GLOBALS['last_redirect'] = $url;
}
function zen_href_link($file, $params = '', $type = 'NONSSL') {
    return $file . ($params ? '?' . $params : '');
}
function zen_draw_hidden_field($name, $value) {
    return "<input type='hidden' name='{$name}' value='{$value}' />";
}
function zen_session_name() { return 'zenid'; }
function zen_session_id() { return '12345'; }
function zen_get_country_iso_code_2($id) { return 'US'; }
?>
<?php
class FakeRecordSet {
    public $records; public $index = 0; public $EOF = true; public $fields = [];
    public function __construct($records = []) {
        $this->records = $records; $this->index = 0; $this->setFields();
    }
    public function setFields() {
        if ($this->index < count($this->records)) { $this->fields = $this->records[$this->index]; $this->EOF = false; }
        else { $this->fields = []; $this->EOF = true; }
    }
    public function MoveNext() { $this->index++; $this->setFields(); }
    public function RecordCount() { return count($this->records); }
}
class FakeDB {
    public $nextRecordSet; public $queries = [];
    public function bindVars($sql,$placeholder,$value,$type) {
        return str_replace($placeholder,$value,$sql);
    }
    public function Execute($sql) {
        $this->queries[] = $sql;
        $rs = $this->nextRecordSet ?: new FakeRecordSet();
        $this->nextRecordSet = null;
        return $rs;
    }
}
class FakeMessageStack {
    public $messages = [];
    public function add_session($text,$type) { $this->messages[] = [$text,$type]; }
    public function add($text,$type) { $this->messages[] = [$text,$type]; }
}
class FakeCurrencies {
    // Mock currency rates: USD=1.0, EUR=0.85, GBP=0.75
    private $rates = ['USD' => 1.0, 'EUR' => 0.85, 'GBP' => 0.75, 'JPY' => 110.0];
    private $decimals = ['USD' => 2, 'EUR' => 2, 'GBP' => 2, 'JPY' => 0];
    
    public function value($amount, $currency = '') {
        if ($currency === '' || $currency === DEFAULT_CURRENCY) {
            return $amount;
        }
        // Convert from default currency to target currency
        $rate = isset($this->rates[$currency]) ? $this->rates[$currency] : 1.0;
        return $amount * $rate;
    }
    
    public function get_value($currency) {
        return isset($this->rates[$currency]) ? $this->rates[$currency] : 1.0;
    }
    
    public function get_decimal_places($currency) {
        return isset($this->decimals[$currency]) ? $this->decimals[$currency] : 2;
    }
}
?>
