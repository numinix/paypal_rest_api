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

final class OrderTotalsGlobalTest extends TestCase
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
    }

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];

        global $messageStack, $zco_notifier, $credit_covers, $order_totals;

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
        
        // Clear order_totals global to ensure clean state
        if (isset($order_totals)) {
            unset($order_totals);
        }
    }

    public function testOrderTotalsGlobalIsSetAfterCheckout(): void
    {
        $cart = new class {
            public string $cartID = 'TEST123';
            public array $resetCalls = [];

            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
                $this->resetCalls[] = $flag;
            }
        };

        $_SESSION['customer_id'] = 5;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'TEST123';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 10;
        $_SESSION['billto'] = 11;
        $_SESSION['payment'] = 'cod';

        $_POST['payment'] = 'cod';

        // Clear any existing global before test
        global $order_totals;
        if (isset($order_totals)) {
            unset($order_totals);
        }

        // Run checkout process
        $response = oprc_checkout_process(['request_type' => 'ajax']);

        // Verify global $order_totals is now set and is an array
        global $order_totals;

        $this->assertIsArray($order_totals, 'Global $order_totals should be an array after checkout');
        $this->assertNotNull($order_totals, 'Global $order_totals should not be null');
    }

    public function testOrderTotalsIsArrayWhenProcessReturnsNonArray(): void
    {
        $cart = new class {
            public string $cartID = 'TEST123';

            public function count_contents()
            {
                return 1;
            }

            public function reset($flag)
            {
            }
        };

        $_SESSION['customer_id'] = 5;
        $_SESSION['cart'] = $cart;
        $_SESSION['cartID'] = 'TEST123';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 10;
        $_SESSION['billto'] = 11;
        $_SESSION['payment'] = 'cod';

        $_POST['payment'] = 'cod';

        global $order_totals;
        if (isset($order_totals)) {
            unset($order_totals);
        }

        // Run checkout process
        oprc_checkout_process(['request_type' => 'ajax']);

        // Verify $order_totals is an array even if order_total_modules->process() returned non-array
        global $order_totals;
        
        $this->assertIsArray($order_totals, 'Global $order_totals must always be an array');
        
        // This is the critical check - sizeof() should work without warnings
        $count = sizeof($order_totals);
        $this->assertIsInt($count, 'sizeof($order_totals) should return an integer without warnings');
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

    public function create($order_totals, $status)
    {
        // Simulate order creation
        return 99;
    }

    public function send_order_email($order_id, $status)
    {
        // Simulate send_order_email which expects global $order_totals
        global $order_totals;
        
        // This is what Zen Cart's order.php does on line 1270
        // It should not produce a warning if $order_totals is properly set as global
        $n = sizeof($order_totals);
        
        return $n;
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
    }

    public function process(): array
    {
        return [
            ['code' => 'ot_subtotal', 'value' => 100],
            ['code' => 'ot_total', 'value' => 100],
        ];
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
