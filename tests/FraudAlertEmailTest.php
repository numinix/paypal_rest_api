<?php
declare(strict_types=1);

/**
 * Test to verify that fraud alert emails respect the debug mode setting.
 * 
 * This test confirms that fraud/lost/stolen card alert emails are only sent
 * when debug mode is enabled (not forced when debug mode is Off).
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
    if (!defined('TABLE_PAYPAL')) {
        define('TABLE_PAYPAL', 'paypal');
    }
    if (!defined('STORE_NAME')) {
        define('STORE_NAME', 'Test Store');
    }
    if (!defined('STORE_OWNER_EMAIL_ADDRESS')) {
        define('STORE_OWNER_EMAIL_ADDRESS', 'test@example.com');
    }
    if (!defined('STORE_OWNER')) {
        define('STORE_OWNER', 'Test Owner');
    }
    
    // Define language constants needed by PayPalCommon
    if (!defined('MODULE_PAYMENT_PAYPALAC_ALERT_SUBJECT')) {
        define('MODULE_PAYMENT_PAYPALAC_ALERT_SUBJECT', 'ALERT: PayPal Advanced Checkout (%s)');
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
    }

    $db = new queryFactory();
    
    // Mock zen_mail function to track email calls
    $GLOBALS['zen_mail_calls'] = [];
    if (!function_exists('zen_mail')) {
        function zen_mail($to_name, $to_address, $subject, $message, $from_name, $from_address, $extra = [], $type = '') {
            $GLOBALS['zen_mail_calls'][] = [
                'to_name' => $to_name,
                'to_address' => $to_address,
                'subject' => $subject,
                'message' => $message,
                'from_name' => $from_name,
                'from_address' => $from_address,
                'extra' => $extra,
                'type' => $type,
            ];
        }
    }
}

namespace FraudAlertEmailTest {
    use PHPUnit\Framework\TestCase;

    class FraudAlertEmailBehaviorTest extends TestCase
    {
        protected function setUp(): void
        {
            // Reset email tracking
            $GLOBALS['zen_mail_calls'] = [];
        }

        protected function createPayPalCommonMock(string $debugMode): object
        {
            // Create a mock paymentModule with emailAlerts property
            $paymentModule = new class($debugMode) {
                public bool $emailAlerts;
                
                public function __construct(string $debugMode) {
                    $this->emailAlerts = ($debugMode === 'Alerts Only' || $debugMode === 'Log and Email');
                }
            };

            // Include the PayPalCommon class
            require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php';
            
            return new \PayPalCommon($paymentModule);
        }

        public function testFraudAlertNotSentWhenDebugModeOff(): void
        {
            $paypalCommon = $this->createPayPalCommonMock('Off');
            
            // Send an alert email without forcing
            $paypalCommon->sendAlertEmail(
                'Lost/Stolen/Fraudulent Card',
                'Test fraud alert message',
                false  // Not forced
            );
            
            // Verify no email was sent
            $this->assertEmpty($GLOBALS['zen_mail_calls'], 'No email should be sent when debug mode is Off');
        }

        public function testFraudAlertSentWhenDebugModeAlertsOnly(): void
        {
            $paypalCommon = $this->createPayPalCommonMock('Alerts Only');
            
            // Send an alert email without forcing
            $paypalCommon->sendAlertEmail(
                'Lost/Stolen/Fraudulent Card',
                'Test fraud alert message',
                false  // Not forced
            );
            
            // Verify email was sent
            $this->assertCount(1, $GLOBALS['zen_mail_calls'], 'Email should be sent when debug mode is Alerts Only');
            $this->assertStringContainsString('Lost/Stolen/Fraudulent Card', $GLOBALS['zen_mail_calls'][0]['subject']);
        }

        public function testFraudAlertSentWhenDebugModeLogAndEmail(): void
        {
            $paypalCommon = $this->createPayPalCommonMock('Log and Email');
            
            // Send an alert email without forcing
            $paypalCommon->sendAlertEmail(
                'Lost/Stolen/Fraudulent Card',
                'Test fraud alert message',
                false  // Not forced
            );
            
            // Verify email was sent
            $this->assertCount(1, $GLOBALS['zen_mail_calls'], 'Email should be sent when debug mode is Log and Email');
            $this->assertStringContainsString('Lost/Stolen/Fraudulent Card', $GLOBALS['zen_mail_calls'][0]['subject']);
        }

        public function testFraudAlertNotSentWhenDebugModeLogFile(): void
        {
            $paypalCommon = $this->createPayPalCommonMock('Log File');
            
            // Send an alert email without forcing
            $paypalCommon->sendAlertEmail(
                'Lost/Stolen/Fraudulent Card',
                'Test fraud alert message',
                false  // Not forced
            );
            
            // Verify no email was sent (Log File mode doesn't send emails)
            $this->assertEmpty($GLOBALS['zen_mail_calls'], 'No email should be sent when debug mode is Log File');
        }

        public function testForceSendOverridesDebugModeSetting(): void
        {
            $paypalCommon = $this->createPayPalCommonMock('Off');
            
            // Send an alert email with forcing
            $paypalCommon->sendAlertEmail(
                'Configuration',
                'Test forced alert message',
                true  // Forced - should send regardless of debug mode
            );
            
            // Verify email was sent even though debug mode is Off
            $this->assertCount(1, $GLOBALS['zen_mail_calls'], 'Email should be sent when force_send is true');
        }
    }
}
