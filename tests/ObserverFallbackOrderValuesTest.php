<?php
declare(strict_types=1);

/**
 * Test to verify that the observer correctly falls back to $order->info
 * when notifications are not available (e.g., OPRC or older ZC versions).
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('FILENAME_CHECKOUT_CONFIRMATION')) {
        define('FILENAME_CHECKOUT_CONFIRMATION', 'checkout_confirmation');
    }
    if (!defined('FILENAME_CHECKOUT_PROCESS')) {
        define('FILENAME_CHECKOUT_PROCESS', 'checkout_process');
    }
    if (!defined('FILENAME_DEFAULT')) {
        define('FILENAME_DEFAULT', 'index');
    }

    if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_STATUS', 'True');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
        define('MODULE_PAYMENT_PAYPALAC_SERVER', 'sandbox');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_L', 'LiveClientId');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_L')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_L', 'LiveClientSecret');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_S', 'SandboxClientId');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_S')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_S', 'SandboxClientSecret');
    }

    if (!class_exists('base')) {
        class base {}
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }

    $current_page_base = 'checkout_confirmation';
}

namespace PayPalRestful\Api\Data {
    class CountryCodes {
        public static function convertCountryCode($code) {
            return $code;
        }
    }
}

namespace PayPalRestful\Api {
    class PayPalRestfulApi {
        const PARTNER_ATTRIBUTION_ID = 'TEST_BN_CODE';
    }
}

namespace PayPalRestful\Zc2Pp {
    class Amount {
        public function __construct($currency) {}
        public function getDefaultCurrencyCode() { return 'USD'; }
    }
}

namespace Zencart\Traits {
    if (!trait_exists('ObserverManager')) {
        trait ObserverManager {
            public function attach($observer, $eventIDArray) {}
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    // Mock the global autoloader
    global $psr4Autoloader;
    if (!isset($psr4Autoloader)) {
        $psr4Autoloader = new class {
            public function addPrefix($prefix, $path) {}
        };
    }

    require_once dirname(__DIR__) . '/includes/classes/observers/auto.paypalrestful.php';

    /**
     * Test class for observer fallback order values functionality
     */
    final class ObserverFallbackOrderValuesTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            // Clear global $order before each test
            global $order;
            $order = null;
        }

        /**
         * Test that getLastOrderValues returns empty array when no order and no notifications
         */
        public function testGetLastOrderValuesReturnsEmptyWhenNoOrder(): void
        {
            global $order;
            $order = null;

            $observer = new zcObserverPaypalrestful();
            $result = $observer->getLastOrderValues();

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }

        /**
         * Test that getLastOrderValues falls back to $order->info when notifications not received
         */
        public function testGetLastOrderValuesFallbackToOrderInfo(): void
        {
            global $order;
            $order = new \stdClass();
            $order->info = [
                'total' => 150.00,
                'tax' => 10.00,
                'subtotal' => 125.00,
                'shipping_cost' => 15.00,
                'shipping_tax' => 1.50,
                'tax_groups' => ['Tax 8%' => 10.00],
            ];

            $observer = new zcObserverPaypalrestful();
            $result = $observer->getLastOrderValues();

            $this->assertIsArray($result);
            $this->assertNotEmpty($result);
            $this->assertEquals(150.00, $result['total']);
            $this->assertEquals(10.00, $result['tax']);
            $this->assertEquals(125.00, $result['subtotal']);
            $this->assertEquals(15.00, $result['shipping_cost']);
            $this->assertEquals(1.50, $result['shipping_tax']);
            $this->assertEquals(['Tax 8%' => 10.00], $result['tax_groups']);
        }

        /**
         * Test that getLastOrderValues uses notification values when available
         */
        public function testGetLastOrderValuesUsesNotificationValues(): void
        {
            global $order;
            // Set up a different value in $order->info
            $order = new \stdClass();
            $order->info = [
                'total' => 999.99,
                'tax' => 99.99,
                'subtotal' => 888.88,
                'shipping_cost' => 88.88,
                'shipping_tax' => 8.88,
                'tax_groups' => ['Different Tax' => 99.99],
            ];

            $observer = new zcObserverPaypalrestful();
            
            // Simulate receiving a notification
            $notificationData = [
                'order_info' => [
                    'total' => 100.00,
                    'tax' => 5.00,
                    'subtotal' => 85.00,
                    'shipping_cost' => 10.00,
                    'shipping_tax' => 0.50,
                    'tax_groups' => ['Tax 5%' => 5.00],
                ]
            ];
            
            $class = null;
            $eventID = 'NOTIFY_ORDER_TOTAL_PRE_CONFIRMATION_CHECK_STARTS';
            $observer->updateNotifyOrderTotalPreConfirmationCheckStarts($class, $eventID, $notificationData);

            $result = $observer->getLastOrderValues();

            // Should use notification values, not $order->info
            $this->assertEquals(100.00, $result['total']);
            $this->assertEquals(5.00, $result['tax']);
            $this->assertEquals(85.00, $result['subtotal']);
            $this->assertEquals(10.00, $result['shipping_cost']);
            $this->assertEquals(0.50, $result['shipping_tax']);
            $this->assertEquals(['Tax 5%' => 5.00], $result['tax_groups']);
        }

        /**
         * Test that fallback handles missing fields gracefully
         */
        public function testGetLastOrderValuesFallbackHandlesMissingFields(): void
        {
            global $order;
            $order = new \stdClass();
            // Partial order info - missing some fields
            $order->info = [
                'total' => 50.00,
                'tax' => 3.00,
            ];

            $observer = new zcObserverPaypalrestful();
            $result = $observer->getLastOrderValues();

            $this->assertIsArray($result);
            $this->assertNotEmpty($result);
            $this->assertEquals(50.00, $result['total']);
            $this->assertEquals(3.00, $result['tax']);
            // Missing fields should default to 0 or empty array
            $this->assertEquals(0, $result['subtotal']);
            $this->assertEquals(0, $result['shipping_cost']);
            $this->assertEquals(0, $result['shipping_tax']);
            $this->assertEquals([], $result['tax_groups']);
        }

        /**
         * Test fallback when order is not an object
         */
        public function testGetLastOrderValuesFallbackWhenOrderNotObject(): void
        {
            global $order;
            $order = 'not an object';

            $observer = new zcObserverPaypalrestful();
            $result = $observer->getLastOrderValues();

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }

        /**
         * Test fallback when order->info is not set
         */
        public function testGetLastOrderValuesFallbackWhenOrderInfoNotSet(): void
        {
            global $order;
            $order = new \stdClass();
            // No info property

            $observer = new zcObserverPaypalrestful();
            $result = $observer->getLastOrderValues();

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }
    }
}
