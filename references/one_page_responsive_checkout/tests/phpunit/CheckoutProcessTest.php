<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('zen_href_link')) {
    function zen_href_link($page, $parameters = '', $connection = 'NONSSL')
    {
        return $page . ($parameters !== '' ? '?' . $parameters : '');
    }
}

if (!function_exists('zen_output_string_protected')) {
    function zen_output_string_protected($string)
    {
        return htmlspecialchars($string, ENT_COMPAT, defined('CHARSET') ? CHARSET : 'UTF-8');
    }
}

if (!function_exists('zen_get_customer_validate_session')) {
    function zen_get_customer_validate_session($customerId)
    {
        return true;
    }
}

final class CheckoutProcessTest extends TestCase
{
    private static string $stubBaseDir;
    private static array $createdFiles = [];
    private static bool $definitionsLoaded = false;

    public static function setUpBeforeClass(): void
    {
        self::$stubBaseDir = __DIR__ . '/stubs';
        self::ensureStubDirectories();
        self::defineFrameworkConstants();
        self::createStubFiles();
        self::loadCheckoutProcessDefinitions();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (array_reverse(self::$createdFiles) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $directories = [
            self::$stubBaseDir . '/modules',
            self::$stubBaseDir . '/classes',
            self::$stubBaseDir . '/includes',
            self::$stubBaseDir,
        ];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];

        global $messageStack, $zco_notifier, $credit_covers;

        $messageStack = new class {
            public array $messages = [];
            private array $sizes = [];

            public function add_session($stack, $message, $type)
            {
                $this->messages[] = [
                    'params' => '',
                    'text' => $message,
                ];
                $this->sizes[$stack] = ($this->sizes[$stack] ?? 0) + 1;
            }

            public function size($stack)
            {
                return $this->sizes[$stack] ?? 0;
            }
        };

        $zco_notifier = new class {
            public array $events = [];

            public function notify($event, $insertId = null)
            {
                $this->events[] = [$event, $insertId];
            }
        };

        $credit_covers = false;
    }

    public function testThrowsWhenCartIsEmpty(): void
    {
        $_SESSION['cart'] = new class {
            public function count_contents()
            {
                return 0;
            }

            public function reset($flag)
            {
            }
        };

        try {
            oprc_checkout_process(['request_type' => 'ajax']);
            $this->fail('Expected exception not thrown.');
        } catch (OprcAjaxCheckoutException $exception) {
            $this->assertSame('Your shopping cart is empty.', $exception->getMessage());
            $this->assertSame('time_out', $exception->getRedirectUrl());
        }
    }

    public function testThrowsWhenCustomerSessionMissing(): void
    {
        $_SESSION['cart'] = new class {
            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
            }
        };

        $_SESSION['navigation'] = new class {
            public array $snapshots = [];

            public function set_snapshot($snapshot = null)
            {
                $this->snapshots[] = $snapshot;
            }
        };

        try {
            oprc_checkout_process(['request_type' => 'ajax']);
            $this->fail('Expected exception not thrown.');
        } catch (OprcAjaxCheckoutException $exception) {
            $this->assertSame('Please log in to complete your order.', $exception->getMessage());
            $this->assertSame('login', $exception->getRedirectUrl());
            $this->assertSame([
                ['mode' => 'SSL', 'page' => FILENAME_ONE_PAGE_CHECKOUT],
            ], $_SESSION['navigation']->snapshots);
        }
    }

    public function testCompletesCheckoutWithValidSession(): void
    {
        $cart = new class {
            public string $cartID = 'ABC123';
            public array $resetCalls = [];

            public function count_contents()
            {
                return 2;
            }

            public function reset($flag)
            {
                $this->resetCalls[] = $flag;
            }
        };

        $_SESSION['customer_id'] = 5;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'ABC123';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 10;
        $_SESSION['billto'] = 11;
        $_SESSION['comments'] = 'Existing';
        $_SESSION['credit_covers'] = true;

        $_POST['payment'] = 'cod';
        $_POST['comments'] = 'Thanks for the order!';

        $response = oprc_checkout_process(['request_type' => 'ajax']);

        $this->assertSame('ajax', $_SESSION['request']);
        $this->assertSame([
            ['NOTIFY_CHECKOUT_PROCESS_BEGIN', null],
            ['NOTIFY_CHECKOUT_SLAMMING_ALERT', 1],
            ['NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION', null],
            ['NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PRE_CONFIRMATION_CHECK', null],
            ['NOTIFY_HEADER_END_CHECKOUT_CONFIRMATION', null],
            ['NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_BEFOREPROCESS', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS', null],
            ['NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL', null],
            ['NOTIFY_GENERATE_PDF_AFTER_SEND_ORDER_EMAIL', null],
            ['NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES', null],
            ['NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET', 99],
            ['NOTIFY_HEADER_END_CHECKOUT_PROCESS', 99],
        ], $GLOBALS['zco_notifier']->events);

        $this->assertSame([
            'status' => 'success',
            'order_id' => 99,
            'redirect_url' => 'checkout_success',
            'messages' => '',
            'confirmation' => [],
        ], $response);

        $this->assertSame(['ajax'], array_values(array_intersect_key($_SESSION, ['request' => null])));
        $this->assertArrayNotHasKey('sendto', $_SESSION);
        $this->assertArrayNotHasKey('billto', $_SESSION);
        $this->assertArrayNotHasKey('shipping', $_SESSION);
        $this->assertArrayNotHasKey('payment', $_SESSION);
        $this->assertArrayNotHasKey('comments', $_SESSION);
        $this->assertSame([true], $cart->resetCalls);
    }

    /**
     * Verify that order_total->pre_confirmation_check() is called even when
     * the payment method starts with 'paypal'. This ensures coupon discounts
     * and other order total adjustments are applied to the PayPal order amount.
     */
    public function testOrderTotalPreConfirmationCheckCalledForPayPal(): void
    {
        $GLOBALS['oprc_test_pre_confirmation_check_called'] = false;

        $cart = new class {
            public string $cartID = 'PPR001';

            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
            }
        };

        $_SESSION['customer_id'] = 15;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'PPR001';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 5;
        $_SESSION['billto'] = 6;
        $_SESSION['payment'] = 'paypalr';
        $_SESSION['credit_covers'] = false;

        $_POST['payment'] = 'paypalr';

        $paypalrModule = new class {
            public string $code = 'paypalr';
            public string $form_action_url = '';

            public function update_status(): void
            {
            }

            public function pre_confirmation_check(): void
            {
            }

            public function confirmation(): array
            {
                return [];
            }

            public function process_button_ajax($payload = null)
            {
                return [
                    'form_action_url' => 'https://paypal.example.com/checkout',
                    'orderId' => 'PAYPAL123',
                ];
            }

            public function process_button(): string
            {
                return '';
            }
        };

        $GLOBALS['paypalr'] = $paypalrModule;

        try {
            oprc_checkout_process(['request_type' => 'ajax']);
        } catch (OprcAjaxCheckoutException $exception) {
            $this->fail('Unexpected exception: ' . $exception->getMessage());
        } finally {
            unset($GLOBALS['paypalr']);
        }

        $this->assertTrue(
            $GLOBALS['oprc_test_pre_confirmation_check_called'],
            'order_total->pre_confirmation_check() must be called for PayPal payments so that coupon discounts are applied to the PayPal order amount.'
        );
    }

    public function testUsesProcessButtonFormActionOverride(): void
    {
        $cart = new class {
            public string $cartID = 'DEF456';

            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
            }
        };

        $_SESSION['customer_id'] = 9;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'DEF456';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 3;
        $_SESSION['billto'] = 4;
        $_SESSION['payment'] = 'paypal';
        $_SESSION['credit_covers'] = false;

        $_POST['payment'] = 'paypal';

        $paypalModule = new class {
            public string $code = 'paypal';
            public string $form_action_url = '';

            public function update_status(): void
            {
            }

            public function pre_confirmation_check(): void
            {
            }

            public function confirmation(): array
            {
                return [];
            }

            public function process_button_ajax($payload = null)
            {
                return [
                    'form_action_url' => 'https://example.com/paypal/checkout',
                    'token' => 'TOKEN123',
                ];
            }

            public function process_button(): string
            {
                return '';
            }
        };

        $GLOBALS['paypal'] = $paypalModule;

        try {
            $response = oprc_checkout_process(['request_type' => 'ajax']);
        } catch (OprcAjaxCheckoutException $exception) {
            $this->fail('Unexpected exception: ' . $exception->getMessage());
            return;
        } finally {
            unset($GLOBALS['paypal']);
        }

        $this->assertSame('requires_external', $response['status'] ?? null);
        $this->assertSame('https://example.com/paypal/checkout', $response['confirmation_form']['action'] ?? null);
        $this->assertArrayHasKey('token', $response['confirmation_form']['fields']);
        $this->assertArrayNotHasKey('form_action_url', $response['confirmation_form']['fields']);
    }

    public function testProcessButtonHtmlProvidesFormMethod(): void
    {
        $cart = new class {
            public string $cartID = 'XYZ789';

            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
            }
        };

        $_SESSION['customer_id'] = 12;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'XYZ789';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 7;
        $_SESSION['billto'] = 8;
        $_SESSION['payment'] = 'externalpay';
        $_SESSION['credit_covers'] = false;

        $_POST['payment'] = 'externalpay';

        $externalModule = new class {
            public string $code = 'externalpay';
            public string $form_action_url = '';

            public function update_status(): void
            {
            }

            public function pre_confirmation_check(): void
            {
            }

            public function confirmation(): array
            {
                return [];
            }

            public function process_button_ajax($payload = null)
            {
                return [];
            }

            public function process_button(): string
            {
                return "<form action='https://gateway.example/checkout' method='GET'>" .
                    "<input type='hidden' name='token' value='ABC123' />" .
                    '</form>';
            }
        };

        $GLOBALS['externalpay'] = $externalModule;

        try {
            $response = oprc_checkout_process(['request_type' => 'ajax']);
        } catch (OprcAjaxCheckoutException $exception) {
            $this->fail('Unexpected exception: ' . $exception->getMessage());
            return;
        } finally {
            unset($GLOBALS['externalpay']);
        }

        $this->assertSame('requires_external', $response['status'] ?? null);
        $this->assertSame('https://gateway.example/checkout', $response['confirmation_form']['action'] ?? null);
        $this->assertSame('get', $response['confirmation_form']['method'] ?? null);
        $this->assertSame('ABC123', $response['confirmation_form']['fields']['token'] ?? null);
        $this->assertArrayNotHasKey('form_method', $response['confirmation_form']['fields']);
        $this->assertArrayNotHasKey('form_action_url', $response['confirmation_form']['fields']);
    }

    private static function ensureStubDirectories(): void
    {
        foreach (['', '/classes', '/modules', '/includes'] as $suffix) {
            $directory = self::$stubBaseDir . $suffix;
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

    private static function defineFrameworkConstants(): void
    {
        $constants = [
            'DIR_FS_CATALOG' => '',
            'DIR_WS_CLASSES' => self::$stubBaseDir . '/classes/',
            'DIR_WS_MODULES' => self::$stubBaseDir . '/modules/',
            'DIR_WS_INCLUDES' => self::$stubBaseDir . '/includes/',
            'FILENAME_TIME_OUT' => 'time_out',
            'FILENAME_LOGIN' => 'login',
            'FILENAME_ONE_PAGE_CHECKOUT' => 'one_page_checkout',
            'FILENAME_CHECKOUT_PROCESS' => 'checkout_process',
            'FILENAME_CHECKOUT_SUCCESS' => 'checkout_success',
            'DISPLAY_CONDITIONS_ON_CHECKOUT' => 'false',
            'CHARSET' => 'UTF-8',
        ];

        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    private static function createStubFiles(): void
    {
        self::createStubFile(self::$stubBaseDir . '/classes/order.php', <<<'PHP'
<?php
class order
{
    public array $info;
    public string $content_type;
    public array $products;

    public function __construct()
    {
        $this->info = ['total' => 100];
        $this->content_type = 'physical';
        $this->products = [
            [
                'id' => 1,
                'model' => 'TEST',
                'qty' => 1,
            ],
        ];
    }
}
PHP
        );

        self::createStubFile(self::$stubBaseDir . '/classes/shipping.php', <<<'PHP'
<?php
class shipping
{
    public array $modules = [];
}
PHP
        );

        self::createStubFile(self::$stubBaseDir . '/classes/order_total.php', <<<'PHP'
<?php
class order_total
{
    public function collect_posts(): void
    {
    }

    public function pre_confirmation_check(): void
    {
        $GLOBALS['oprc_test_pre_confirmation_check_called'] = true;
    }

    public function process(): array
    {
        return [];
    }

    public function clear_posts(): void
    {
    }
}
PHP
        );

        self::createStubFile(self::$stubBaseDir . '/classes/payment.php', <<<'PHP'
<?php
class payment
{
    public array $modules;

    public function __construct($selection)
    {
        $this->modules = [];
    }

    public function update_status(): void
    {
    }

    public function pre_confirmation_check(): void
    {
    }

    public function before_process(): void
    {
    }

    public function after_process(): void
    {
    }
}
PHP
        );

        self::createStubFile(self::$stubBaseDir . '/modules/checkout_process.php', <<<'PHP'
<?php
$insert_id = $insert_id ?? 99;
PHP
        );

        self::createStubFile(self::$stubBaseDir . '/includes/application_bottom.php', <<<'PHP'
<?php
PHP
        );
    }

    private static function createStubFile(string $path, string $contents): void
    {
        file_put_contents($path, $contents);
        self::$createdFiles[] = $path;
    }

    private static function loadCheckoutProcessDefinitions(): void
    {
        if (self::$definitionsLoaded) {
            return;
        }

        require_once __DIR__ . '/../../catalog/includes/functions/extra_functions/oprc_checkout_process.php';
        self::$definitionsLoaded = true;
    }

}
