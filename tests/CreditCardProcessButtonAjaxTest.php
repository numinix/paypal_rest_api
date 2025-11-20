<?php
declare(strict_types=1);

/**
 * Test that validates the paypalr_creditcard module's process_button_ajax method
 * returns the correct credit card field mappings for AJAX checkout.
 * 
 * This test verifies that the credit card module properly returns field mappings
 * when process_button_ajax() is called, preventing AJAX 500 errors during checkout.
 */

echo "Testing paypalr_creditcard::process_button_ajax() method...\n\n";

// Simple assertion helper
function assert_true($condition, $message) {
    if ($condition) {
        echo "  ✓ " . $message . "\n";
        return true;
    } else {
        echo "  ✗ FAILED: " . $message . "\n";
        return false;
    }
}

function assert_equals($expected, $actual, $message) {
    if ($expected === $actual) {
        echo "  ✓ " . $message . "\n";
        return true;
    } else {
        echo "  ✗ FAILED: " . $message . " (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n";
        return false;
    }
}

function assert_array_has_key($key, $array, $message) {
    if (isset($array[$key])) {
        echo "  ✓ " . $message . "\n";
        return true;
    } else {
        echo "  ✗ FAILED: " . $message . " (key '$key' not found)\n";
        return false;
    }
}

function assert_array_not_has_key($key, $array, $message) {
    if (!isset($array[$key])) {
        echo "  ✓ " . $message . "\n";
        return true;
    } else {
        echo "  ✗ FAILED: " . $message . " (key '$key' should not exist)\n";
        return false;
    }
}

// Test setup
if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_WS_CATALOG')) {
    define('DIR_WS_CATALOG', '/');
}
if (!defined('DIR_FS_LOGS')) {
    define('DIR_FS_LOGS', sys_get_temp_dir() . '/');
}
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', true);
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}
if (!defined('TABLE_CONFIGURATION')) {
    define('TABLE_CONFIGURATION', 'configuration');
}
if (!defined('TABLE_PAYPAL')) {
    define('TABLE_PAYPAL', 'paypal');
}
if (!defined('FILENAME_MODULES')) {
    define('FILENAME_MODULES', 'modules.php');
}

// Define PayPal configuration constants
if (!defined('MODULE_PAYMENT_PAYPALR_STATUS')) {
    define('MODULE_PAYMENT_PAYPALR_STATUS', 'True');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
    define('MODULE_PAYMENT_PAYPALR_SERVER', 'sandbox');
}
if (!defined('MODULE_PAYMENT_PAYPALR_DEBUGGING')) {
    define('MODULE_PAYMENT_PAYPALR_DEBUGGING', 'Off');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CLIENTID_S')) {
    define('MODULE_PAYMENT_PAYPALR_CLIENTID_S', 'test_client_id');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SECRET_S')) {
    define('MODULE_PAYMENT_PAYPALR_SECRET_S', 'test_secret');
}
if (!defined('MODULE_PAYMENT_PAYPALR_ACCEPT_CARDS')) {
    define('MODULE_PAYMENT_PAYPALR_ACCEPT_CARDS', 'true');
}
if (!defined('MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID')) {
    define('MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID', '2');
}
if (!defined('MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID')) {
    define('MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID', '1');
}
if (!defined('MODULE_PAYMENT_PAYPALR_ZONE')) {
    define('MODULE_PAYMENT_PAYPALR_ZONE', '0');
}
if (!defined('MODULE_PAYMENT_PAYPALR_SORT_ORDER')) {
    define('MODULE_PAYMENT_PAYPALR_SORT_ORDER', '-1');
}
if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
    define('MODULE_PAYMENT_PAYPALR_VERSION', '1.3.3');
}
if (!defined('MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE')) {
    define('MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE', 'Final Sale');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CURRENCY')) {
    define('MODULE_PAYMENT_PAYPALR_CURRENCY', 'Selected Currency');
}
if (!defined('DEFAULT_ORDERS_STATUS_ID')) {
    define('DEFAULT_ORDERS_STATUS_ID', '1');
}
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'USD');
}

// Define credit card module constants
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS', 'True');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER', '0');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE', '0');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION', '1.3.3');
}

// Define language constants
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE', 'Credit Card');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE_ADMIN')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE_ADMIN', 'PayPal Credit Cards');
}
if (!defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_DESCRIPTION')) {
    define('MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_DESCRIPTION', 'Accept credit card payments via PayPal Advanced Checkout (v%s)');
}
if (!defined('MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL')) {
    define('MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL', 'cURL not installed');
}

// Mock database class
class queryFactoryResult {
    public $EOF = true;
    public function __construct(public array $fields = []) {
        $this->EOF = empty($fields);
    }
}

class queryFactory {
    public function Execute($sql) {
        return new queryFactoryResult();
    }
    public function bindVars($sql, $param, $value, $type = null) {
        return $sql;
    }
}

$db = new queryFactory();
$current_page = FILENAME_MODULES;

// Mock the PSR-4 autoloader
class psr4AutoloaderMock {
    public function addPrefix($prefix, $path) {
        // No-op for testing
    }
}

if (!isset($GLOBALS['psr4Autoloader'])) {
    $GLOBALS['psr4Autoloader'] = new psr4AutoloaderMock();
}

// Include required files
require_once DIR_FS_CATALOG . 'includes/modules/payment/paypalr_creditcard.php';

// Run tests
$all_passed = true;

echo "Test 1: process_button_ajax returns field mappings for new card...\n";
$_POST = ['paypalr_saved_card' => 'new'];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_true(is_array($result), "Result is an array");
$all_passed &= assert_array_has_key('ccFields', $result, "Result has ccFields key");
$all_passed &= assert_array_has_key('ppr_saved_card', $result['ccFields'], "Has ppr_saved_card field");
$all_passed &= assert_array_has_key('ppr_cc_owner', $result['ccFields'], "Has ppr_cc_owner field");
$all_passed &= assert_array_has_key('ppr_cc_number', $result['ccFields'], "Has ppr_cc_number field");
$all_passed &= assert_array_has_key('ppr_cc_cvv', $result['ccFields'], "Has ppr_cc_cvv field");
$all_passed &= assert_array_has_key('ppr_cc_expires_month', $result['ccFields'], "Has ppr_cc_expires_month field");
$all_passed &= assert_array_has_key('ppr_cc_expires_year', $result['ccFields'], "Has ppr_cc_expires_year field");
$all_passed &= assert_equals('paypalr_cc_owner', $result['ccFields']['ppr_cc_owner'], "Correct mapping for cc_owner");
echo "\n";

echo "Test 2: process_button_ajax includes save card field when checked...\n";
$_POST = ['paypalr_saved_card' => 'new', 'paypalr_cc_save_card' => 'on'];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_array_has_key('ppr_cc_save_card', $result['ccFields'], "Has ppr_cc_save_card field when checkbox is checked");
echo "\n";

echo "Test 3: process_button_ajax excludes save card field when not checked...\n";
$_POST = ['paypalr_saved_card' => 'new'];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_array_not_has_key('ppr_cc_save_card', $result['ccFields'], "Does not have ppr_cc_save_card field when checkbox is not checked");
echo "\n";

echo "Test 4: process_button_ajax includes SCA field when present...\n";
$_POST = ['paypalr_saved_card' => 'new', 'paypalr_cc_sca_always' => '1'];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_array_has_key('ppr_cc_sca_always', $result['ccFields'], "Has ppr_cc_sca_always field when present in POST");
echo "\n";

echo "Test 5: process_button_ajax returns minimal fields for saved card...\n";
$_POST = ['paypalr_saved_card' => 'vault-id-123'];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_array_has_key('ppr_saved_card', $result['ccFields'], "Has ppr_saved_card field for saved card");
$all_passed &= assert_array_not_has_key('ppr_cc_owner', $result['ccFields'], "Does not have ppr_cc_owner field for saved card");
$all_passed &= assert_array_not_has_key('ppr_cc_number', $result['ccFields'], "Does not have ppr_cc_number field for saved card");
echo "\n";

echo "Test 6: process_button_ajax defaults to new card when no selection...\n";
$_POST = [];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_array_has_key('ppr_cc_owner', $result['ccFields'], "Defaults to new card fields when no selection");
$all_passed &= assert_array_has_key('ppr_cc_number', $result['ccFields'], "Defaults to new card fields when no selection");
echo "\n";

echo "Test 7: process_button_ajax returns non-empty result (fixes AJAX 500 error)...\n";
$_POST = [];
$module = new paypalr_creditcard();
$result = $module->process_button_ajax();
$all_passed &= assert_true(!empty($result), "Result is not empty");
$all_passed &= assert_true($result !== false, "Result is not false");
$all_passed &= assert_true(is_array($result), "Result is an array");
echo "\n";

if ($all_passed) {
    echo "✅ All process_button_ajax tests passed!\n";
    echo "   - Credit card module now properly returns field mappings for AJAX checkout\n";
    echo "   - This fixes the 500 Internal Server Error when submitting checkout payment form\n";
    exit(0);
} else {
    echo "❌ Some tests failed!\n";
    exit(1);
}
