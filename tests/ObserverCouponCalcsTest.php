<?php
declare(strict_types=1);

/**
 * Test to verify that the observer correctly handles coupon type access
 * in updateNotifyOtCouponCalcsFinished method.
 * 
 * The method must handle both array and object structures for coupon data
 * that may be passed by different versions of the ot_coupon module.
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

namespace PayPalAdvancedCheckout\Api\Data {
    class CountryCodes {
        public static function convertCountryCode($code) {
            return $code;
        }
    }
}

namespace PayPalAdvancedCheckout\Api {
    class PayPalAdvancedCheckoutApi {
        const PARTNER_ATTRIBUTION_ID = 'TEST_BN_CODE';
    }
}

namespace PayPalAdvancedCheckout\Zc2Pp {
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

    require_once dirname(__DIR__) . '/includes/classes/observers/auto.paypaladvcheckout.php';

    /**
     * Test class for observer coupon calculations
     */
    final class ObserverCouponCalcsTest extends TestCase
    {
        /**
         * Test that the observer correctly handles coupon data as an array
         */
        public function testCouponTypeArrayAccess(): void
        {
            $observer = new zcObserverPaypaladvcheckout();
            
            // Simulate coupon data as an array (traditional format)
            $parameters = [
                'coupon' => [
                    'coupon_type' => 'S',
                    'coupon_code' => 'FREESHIP',
                ]
            ];
            
            $class = null;
            $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
            
            // Call the method
            $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
            
            // Verify free shipping coupon is detected
            $this->assertTrue(
                $observer->orderHasFreeShippingCoupon(),
                'Free shipping coupon (type S) should be detected from array structure'
            );
        }

        /**
         * Test that the observer correctly handles coupon data as an object with fields
         */
        public function testCouponTypeObjectAccess(): void
        {
            $observer = new zcObserverPaypaladvcheckout();
            
            // Simulate coupon data as an object with fields (newer format)
            $couponObject = new \stdClass();
            $couponObject->fields = [
                'coupon_type' => 'E',
                'coupon_code' => 'FREESHIP2',
            ];
            
            $parameters = [
                'coupon' => $couponObject
            ];
            
            $class = null;
            $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
            
            // Call the method
            $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
            
            // Verify free shipping coupon is detected
            $this->assertTrue(
                $observer->orderHasFreeShippingCoupon(),
                'Free shipping coupon (type E) should be detected from object structure'
            );
        }

        /**
         * Test that non-free-shipping coupons are correctly identified
         */
        public function testNonFreeShippingCoupon(): void
        {
            $observer = new zcObserverPaypaladvcheckout();
            
            // Test with a percentage discount coupon (type P)
            $parameters = [
                'coupon' => [
                    'coupon_type' => 'P',
                    'coupon_code' => '10PERCENT',
                ]
            ];
            
            $class = null;
            $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
            
            $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
            
            // Verify non-free-shipping coupon is not flagged
            $this->assertFalse(
                $observer->orderHasFreeShippingCoupon(),
                'Percentage coupon (type P) should not be detected as free shipping'
            );
        }

        /**
         * Test that all free shipping coupon types are detected
         */
        public function testAllFreeShippingTypes(): void
        {
            $freeShippingTypes = ['S', 'E', 'O'];
            
            foreach ($freeShippingTypes as $type) {
                $observer = new zcObserverPaypaladvcheckout();
                
                $parameters = [
                    'coupon' => [
                        'coupon_type' => $type,
                        'coupon_code' => 'TEST',
                    ]
                ];
                
                $class = null;
                $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
                
                $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
                
                $this->assertTrue(
                    $observer->orderHasFreeShippingCoupon(),
                    "Free shipping coupon type '{$type}' should be detected"
                );
            }
        }

        /**
         * Test graceful handling when coupon_type is missing
         */
        public function testMissingCouponType(): void
        {
            $observer = new zcObserverPaypaladvcheckout();
            
            // Coupon without coupon_type
            $parameters = [
                'coupon' => [
                    'coupon_code' => 'NOCOUPONTYPE',
                ]
            ];
            
            $class = null;
            $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
            
            // Should not throw an error
            $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
            
            // Should default to false for free shipping
            $this->assertFalse(
                $observer->orderHasFreeShippingCoupon(),
                'Missing coupon_type should default to non-free-shipping'
            );
        }

        /**
         * Test graceful handling when coupon is an empty object
         */
        public function testEmptyObjectCoupon(): void
        {
            $observer = new zcObserverPaypaladvcheckout();
            
            // Empty object
            $couponObject = new \stdClass();
            
            $parameters = [
                'coupon' => $couponObject
            ];
            
            $class = null;
            $eventID = 'NOTIFY_OT_COUPON_CALCS_FINISHED';
            
            // Should not throw an error
            $observer->updateNotifyOtCouponCalcsFinished($class, $eventID, $parameters);
            
            // Should default to false for free shipping
            $this->assertFalse(
                $observer->orderHasFreeShippingCoupon(),
                'Empty object should default to non-free-shipping'
            );
        }
    }
}
