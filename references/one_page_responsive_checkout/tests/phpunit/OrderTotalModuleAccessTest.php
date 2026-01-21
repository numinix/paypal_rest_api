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

/**
 * Test that simulates how an order total module (like ot_gv) would access globals
 */
final class OrderTotalModuleAccessTest extends TestCase
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

        global $messageStack, $zco_notifier, $credit_covers, $db;

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
        
        // Initialize a mock database object that modules might need
        $db = new class {
            public function Execute($sql) {
                return true;
            }
        };
    }

    public function testOrderTotalModuleCanAccessGlobalsDuringAfterProcess(): void
    {
        // This test simulates what an order total module like ot_gv.php does
        // when it tries to access $db, $order, and $insert_id globals
        
        $cart = new class {
            public string $cartID = 'MODTEST123';

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
        $_SESSION['cartID'] = 'MODTEST123';
        $_SESSION['shipping'] = ['id' => 'flat.flat'];
        $_SESSION['sendto'] = 10;
        $_SESSION['billto'] = 11;
        $_SESSION['payment'] = 'cod';

        $_POST['payment'] = 'cod';

        // Track whether our simulated module can access the globals
        $globalAccessTest = new class {
            public bool $dbAccessible = false;
            public bool $orderAccessible = false;
            public bool $insertIdAccessible = false;
            public ?int $capturedInsertId = null;
            
            public function simulateOrderTotalModuleAccess()
            {
                // This simulates what happens inside an order total module's after_process method
                global $db, $order, $insert_id;
                
                $this->dbAccessible = isset($db);
                $this->orderAccessible = isset($order) && is_object($order);
                $this->insertIdAccessible = isset($insert_id);
                $this->capturedInsertId = $insert_id ?? null;
            }
        };

        // Run checkout process
        $response = oprc_checkout_process(['request_type' => 'ajax']);

        // After checkout, simulate an order total module trying to access globals
        $globalAccessTest->simulateOrderTotalModuleAccess();

        // Verify the module can access all required globals
        $this->assertTrue(
            $globalAccessTest->dbAccessible,
            'Order total modules should be able to access global $db'
        );
        
        $this->assertTrue(
            $globalAccessTest->orderAccessible,
            'Order total modules should be able to access global $order as an object'
        );
        
        $this->assertTrue(
            $globalAccessTest->insertIdAccessible,
            'Order total modules should be able to access global $insert_id'
        );
        
        $this->assertSame(
            99,
            $globalAccessTest->capturedInsertId,
            'The global $insert_id should match the created order ID'
        );
        
        // Verify checkout completed successfully
        $this->assertSame('success', $response['status']);
        $this->assertSame(99, $response['order_id']);
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
