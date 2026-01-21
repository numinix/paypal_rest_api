<?php

namespace PayPalRestful\Compatibility {
    if (!class_exists(Language::class, false)) {
        class Language
        {
            public static function load(): void
            {
            }
        }
    }
}

namespace PayPalRestful\Admin {
    if (!class_exists(AdminMain::class, false)) {
        class AdminMain
        {
        }
    }

    if (!class_exists(DoAuthorization::class, false)) {
        class DoAuthorization
        {
        }
    }

    if (!class_exists(DoCapture::class, false)) {
        class DoCapture
        {
        }
    }

    if (!class_exists(DoRefund::class, false)) {
        class DoRefund
        {
        }
    }

    if (!class_exists(DoVoid::class, false)) {
        class DoVoid
        {
        }
    }

    if (!class_exists(GetPayPalOrderTransactions::class, false)) {
        class GetPayPalOrderTransactions
        {
        }
    }
}

namespace PayPalRestful\Api {
    if (!class_exists(PayPalRestfulApi::class, false)) {
        class PayPalRestfulApi
        {
            public const STATUS_APPROVED = 'APPROVED';
            public const STATUS_COMPLETED = 'COMPLETED';
            public const STATUS_CAPTURED = 'CAPTURED';
        }
    }
}

namespace PayPalRestful\Api\Data {
    if (!class_exists(CountryCodes::class, false)) {
        class CountryCodes
        {
            public static function ConvertCountryCode($code)
            {
                return $code ?: '';
            }
        }
    }
}

namespace PayPalRestful\Common {
    if (!class_exists(ErrorInfo::class, false)) {
        class ErrorInfo
        {
        }
    }

    if (!class_exists(Helpers::class, false)) {
        class Helpers
        {
        }
    }

    if (!class_exists(Logger::class, false)) {
        class Logger
        {
            public function enableDebug(): void
            {
            }

            public function write($message): void
            {
            }
        }
    }

    if (!class_exists(VaultManager::class, false)) {
        class VaultManager
        {
        }
    }
}

namespace PayPalRestful\Zc2Pp {
    if (!class_exists(Amount::class, false)) {
        class Amount
        {
            public function __construct($currency)
            {
            }

            public function getDefaultCurrencyCode()
            {
                return 'USD';
            }
        }
    }

    if (!class_exists(ConfirmPayPalPaymentChoiceRequest::class, false)) {
        class ConfirmPayPalPaymentChoiceRequest
        {
        }
    }

    if (!class_exists(CreatePayPalOrderRequest::class, false)) {
        class CreatePayPalOrderRequest
        {
        }
    }
}

namespace {

use PHPUnit\Framework\TestCase;

if (!class_exists('PayPalrTestBaseStub', false)) {
    class PayPalrTestBaseStub
    {
    }
}

if (!class_exists('base', false)) {
    class_alias(PayPalrTestBaseStub::class, 'base');
}

if (!function_exists('zen_draw_hidden_field')) {
    function zen_draw_hidden_field($name, $value = '', $parameters = '')
    {
        $params = $parameters !== '' ? ' ' . $parameters : '';
        return '<input type="hidden" name="' . $name . '" value="' . $value . '"' . $params . '>';
    }
}

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', __DIR__ . '/../../reference/');
}

if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}

if (!defined('MODULE_PAYMENT_PAYPALR_BUTTON_COLOR')) {
    define('MODULE_PAYMENT_PAYPALR_BUTTON_COLOR', 'YELLOW');
}

if (!defined('MODULE_PAYMENT_PAYPALR_BUTTON_IMG_YELLOW')) {
    define('MODULE_PAYMENT_PAYPALR_BUTTON_IMG_YELLOW', 'paypal-button.png');
}

if (!defined('MODULE_PAYMENT_PAYPALR_BUTTON_ALTTEXT')) {
    define('MODULE_PAYMENT_PAYPALR_BUTTON_ALTTEXT', 'Pay with PayPal');
}

if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class PayPalrSelectionTest extends TestCase
{
    private static ?string $originalCwd = null;

    public static function setUpBeforeClass(): void
    {
        $GLOBALS['psr4Autoloader'] = new class
        {
            public array $prefixes = [];

            public function addPrefix($prefix, $path)
            {
                $this->prefixes[$prefix] = $path;
            }
        };

        self::$originalCwd = getcwd();
        chdir(DIR_FS_CATALOG);

        require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$originalCwd !== null) {
            chdir(self::$originalCwd);
            self::$originalCwd = null;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testSelectionIncludesCheckoutScriptWhenCardsDisabled(): void
    {
        $reflection = new ReflectionClass(paypalr::class);
        /** @var paypalr $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        $instance->code = 'paypalr';

        $scriptPath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js';
        $this->assertFileExists($scriptPath, 'Expected PayPal checkout script to be available for selection tests.');

        $selection = $instance->selection();

        $this->assertArrayHasKey('module', $selection);
        $this->assertStringContainsString('paypalWalletIsSelected', $selection['module']);
        $this->assertStringContainsString('<script>', $selection['module']);
    }
}

}
