<?php
declare(strict_types=1);

/**
 * Test that validates the paypalr_creditcard module properly formats
 * error messages with sprintf when order creation fails.
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
        define('IS_ADMIN_FLAG', false);
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
    if (!defined('FILENAME_CHECKOUT_PAYMENT')) {
        define('FILENAME_CHECKOUT_PAYMENT', 'checkout_payment.php');
    }
    if (!defined('FILENAME_MODULES')) {
        define('FILENAME_MODULES', 'modules.php');
    }
    
    // Define PayPal configuration constants needed by parent module
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
    if (!defined('MODULE_PAYMENT_PAYPALR_ENABLE_VAULT')) {
        define('MODULE_PAYMENT_PAYPALR_ENABLE_VAULT', 'false');
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
    if (!defined('MODULE_PAYMENT_PAYPALR_TEXT_TITLE')) {
        define('MODULE_PAYMENT_PAYPALR_TEXT_TITLE', 'PayPal');
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
    if (!defined('MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE')) {
        define('MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE', 'We are unable to process your %1$s payment at this time. Please contact us for assistance, providing us with this code: <b>%2$s</b>.');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN')) {
        define('MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN', 'Order Requires Attention');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATE')) {
        define('MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATE', 'An error was returned by PayPal when attempting to initiate an order.');
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
}

namespace CreditCardErrorMessageFormattingTest {
    use PHPUnit\Framework\TestCase;

    class ErrorMessageFormattingTest extends TestCase
    {
        /**
         * Test that the error message constant contains the expected placeholders
         */
        public function testErrorMessageConstantHasPlaceholders(): void
        {
            $message = MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE;
            
            $this->assertStringContainsString('%1$s', $message, 
                'Error message should contain %1$s placeholder for payment method name');
            $this->assertStringContainsString('%2$s', $message,
                'Error message should contain %2$s placeholder for error code');
        }

        /**
         * Test that sprintf correctly formats the error message
         */
        public function testSprintfFormatsErrorMessageCorrectly(): void
        {
            $paymentMethod = MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE;
            $errorCode = 'INVALID_REQUEST';
            
            $formattedMessage = sprintf(
                MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE,
                $paymentMethod,
                $errorCode
            );
            
            // Verify placeholders are replaced
            $this->assertStringNotContainsString('%1$s', $formattedMessage,
                'Formatted message should not contain %1$s placeholder');
            $this->assertStringNotContainsString('%2$s', $formattedMessage,
                'Formatted message should not contain %2$s placeholder');
            
            // Verify actual values are present
            $this->assertStringContainsString($paymentMethod, $formattedMessage,
                'Formatted message should contain the payment method name');
            $this->assertStringContainsString($errorCode, $formattedMessage,
                'Formatted message should contain the error code');
            
            // Verify the expected output
            $expectedMessage = 'We are unable to process your Credit Card payment at this time. Please contact us for assistance, providing us with this code: <b>INVALID_REQUEST</b>.';
            $this->assertEquals($expectedMessage, $formattedMessage);
        }

        /**
         * Test that error message formatting works with the default error code
         */
        public function testSprintfFormatsErrorMessageWithDefaultErrorCode(): void
        {
            $paymentMethod = MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE;
            $errorCode = 'OTHER';
            
            $formattedMessage = sprintf(
                MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE,
                $paymentMethod,
                $errorCode
            );
            
            $expectedMessage = 'We are unable to process your Credit Card payment at this time. Please contact us for assistance, providing us with this code: <b>OTHER</b>.';
            $this->assertEquals($expectedMessage, $formattedMessage);
        }

        /**
         * Test various PayPal error codes are properly formatted
         */
        public function testSprintfFormatsVariousErrorCodes(): void
        {
            $paymentMethod = MODULE_PAYMENT_PAYPALR_CREDITCARD_TEXT_TITLE;
            $errorCodes = [
                'INVALID_REQUEST',
                'PAYMENT_DENIED',
                'INSTRUMENT_DECLINED',
                'OTHER',
                'PROCESSING_FAILURE'
            ];
            
            foreach ($errorCodes as $errorCode) {
                $formattedMessage = sprintf(
                    MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE,
                    $paymentMethod,
                    $errorCode
                );
                
                $this->assertStringContainsString($errorCode, $formattedMessage,
                    "Error code '$errorCode' should appear in formatted message");
                $this->assertStringNotContainsString('%1$s', $formattedMessage,
                    "Formatted message should not contain %1\$s placeholder for error code '$errorCode'");
                $this->assertStringNotContainsString('%2$s', $formattedMessage,
                    "Formatted message should not contain %2\$s placeholder for error code '$errorCode'");
            }
        }
    }
}
