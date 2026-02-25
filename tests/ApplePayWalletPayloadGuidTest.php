<?php
/**
 * Verify that Apple Pay wallet payloads influence the order GUID to ensure
 * a new PayPal order is created once the token is available (avoiding reuse
 * of a tokenless order that would fail confirmPaymentSource).
 */

declare(strict_types=1);

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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php';
}

namespace Tests {
    use PayPalCommon;

    class ApplePayWalletPayloadGuidTest
    {
        public function run(): void
        {
            $_SESSION = [
                'customer_id' => 123,
                'cartID' => 'abc',
                'PayPalAdvancedCheckout' => [
                    'CompletedOrders' => 0,
                ],
            ];

            $order = new class {
                public $products = [
                    ['id' => 'SKU1', 'qty' => 1],
                ];
            };

            $common = new PayPalCommon(new class {
            });

            $baseGuid = $common->createOrderGuid($order, 'apple_pay');

            $_SESSION['PayPalAdvancedCheckout']['WalletPayload']['apple_pay'] = ['token' => 'token-123'];
            $tokenGuid = $common->createOrderGuid($order, 'apple_pay');

            $_SESSION['PayPalAdvancedCheckout']['WalletPayload']['apple_pay'] = ['token' => 'token-456'];
            $secondTokenGuid = $common->createOrderGuid($order, 'apple_pay');

            if ($baseGuid === $tokenGuid) {
                throw new \Exception('GUID should change when Apple Pay token is added.');
            }

            if ($tokenGuid === $secondTokenGuid) {
                throw new \Exception('GUID should change when Apple Pay token value changes.');
            }

            echo "ApplePayWalletPayloadGuidTest passed\n";
        }
    }
}

namespace {
    if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0])) {
        (new \Tests\ApplePayWalletPayloadGuidTest())->run();
    }
}
