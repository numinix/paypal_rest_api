<?php
declare(strict_types=1);

/**
 * Test that validates the paypalac_creditcard module can be instantiated
 * and has the correct basic properties.
 */

namespace {
    // Define required constants for testing
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
        define('IS_ADMIN_FLAG', true); // Admin mode for simpler testing
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
    
    // Define PayPal configuration constants needed by parent module
    if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_STATUS', 'True');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
        define('MODULE_PAYMENT_PAYPALAC_SERVER', 'sandbox');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_DEBUGGING')) {
        define('MODULE_PAYMENT_PAYPALAC_DEBUGGING', 'Off');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_S', 'test_client_id');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_S')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_S', 'test_secret');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ACCEPT_CARDS')) {
        define('MODULE_PAYMENT_PAYPALAC_ACCEPT_CARDS', 'true');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID')) {
        define('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID', '2');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID')) {
        define('MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID', '1');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ZONE')) {
        define('MODULE_PAYMENT_PAYPALAC_ZONE', '0');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SORT_ORDER')) {
        define('MODULE_PAYMENT_PAYPALAC_SORT_ORDER', '-1');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
        define('MODULE_PAYMENT_PAYPALAC_VERSION', '1.3.3');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE')) {
        define('MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE', 'Final Sale');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CURRENCY')) {
        define('MODULE_PAYMENT_PAYPALAC_CURRENCY', 'Selected Currency');
    }
    if (!defined('DEFAULT_ORDERS_STATUS_ID')) {
        define('DEFAULT_ORDERS_STATUS_ID', '1');
    }
    if (!defined('DEFAULT_CURRENCY')) {
        define('DEFAULT_CURRENCY', 'USD');
    }
    
    // Define credit card module constants
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS', 'True');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER', '0');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE', '0');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION', '1.3.5');
    }
    
    // Define language constants
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE', 'Credit Card');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE_ADMIN')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE_ADMIN', 'PayPal Credit Cards');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_DESCRIPTION')) {
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_DESCRIPTION', 'Accept credit card payments via PayPal Advanced Checkout (v%s)');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL')) {
        define('MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL', 'cURL not installed');
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

    // Include required files
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypalac_creditcard.php';
}

namespace PayPalCreditCardModuleTest {
    use PHPUnit\Framework\TestCase;

    class PayPalCreditCardModuleInstantiationTest extends TestCase
    {
        public function testModuleCanBeInstantiated(): void
        {
            $module = new \paypalac_creditcard();
            
            $this->assertInstanceOf(\paypalac_creditcard::class, $module);
            $this->assertEquals('paypalac_creditcard', $module->code);
        }

        public function testModuleHasCorrectTitle(): void
        {
            $module = new \paypalac_creditcard();
            
            $this->assertStringContainsString('PayPal Credit Cards', $module->title);
        }

        public function testModuleExtendsPayPalac(): void
        {
            $module = new \paypalac_creditcard();
            
            $this->assertInstanceOf(\paypalac::class, $module);
        }

        public function testModuleConfigurationKeys(): void
        {
            $module = new \paypalac_creditcard();
            $keys = $module->keys();
            
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS', $keys);
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER', $keys);
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE', $keys);
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION', $keys);
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS', $keys);
            $this->assertContains('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX', $keys);
        }

        public function testCheckMethodReturnsFalseWhenNotInstalled(): void
        {
            $module = new \paypalac_creditcard();
            
            // Since we're using a mock DB that returns EOF=true, check() should return false
            $this->assertFalse($module->check());
        }
    }
}
