<?php
/**
 * Regression: CreatePayPalOrderRequest session fallback for gift credit + coupons.
 *
 * One-page checkout can mint a PayPal order before ot_gv / ot_coupon stick on
 * $order. The request must still subtract:
 * - gift credit (cot_gv), excluding GIFT-model products
 * - discount coupons (cc_id / ot_coupon)
 * - store credit (storecredit)
 * and must not double-apply when ot_diffs already carries those modules.
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
    if (!defined('DEFAULT_CURRENCY')) {
        define('DEFAULT_CURRENCY', 'USD');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CURRENCY_FALLBACK')) {
        define('MODULE_PAYMENT_PAYPALAC_CURRENCY_FALLBACK', 'USD');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE')) {
        define('MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE', 'Final Sale');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_HANDLING_OT')) {
        define('MODULE_PAYMENT_PAYPALAC_HANDLING_OT', '');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_INSURANCE_OT')) {
        define('MODULE_PAYMENT_PAYPALAC_INSURANCE_OT', '');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_DISCOUNT_OT')) {
        define('MODULE_PAYMENT_PAYPALAC_DISCOUNT_OT', '');
    }
    if (!defined('SHIPPING_ORIGIN_ZIP')) {
        define('SHIPPING_ORIGIN_ZIP', '');
    }

    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/ErrorInfo.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/Data/CountryCodes.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Amount.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Address.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Name.php';
    require_once DIR_FS_CATALOG . 'zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/CreatePayPalOrderRequest.php';

    class currencies
    {
        public function rateAdjusted($value, bool $use_defaults = true, string $currency_code = ''): float
        {
            return (float)$value;
        }

        public function normalizeValue($value)
        {
            return (float)$value;
        }

        public function value($value, $use_defaults = true, $currency_code = '')
        {
            return (float)$value;
        }
    }

    class order
    {
        public array $info;
        public array $products;
        public array $totals = [];
        public array $billing;
        public array $delivery;
        public array $customer;

        public function __construct(array $info, array $products)
        {
            $this->info = $info;
            $this->products = $products;
            $this->billing = [
                'firstname' => 'Jane',
                'lastname' => 'Doe',
                'street_address' => '1 Test Way',
                'city' => 'Testville',
                'state' => 'Test State',
                'state_code' => 'TS',
                'postcode' => '12345',
                'country' => ['iso_code_2' => 'US'],
            ];
            $this->delivery = $this->billing;
            $this->delivery['name'] = $this->billing['firstname'] . ' ' . $this->billing['lastname'];
            $this->customer = [
                'email_address' => 'customer@example.com',
            ];
        }
    }

    class NullDbResult
    {
        public bool $EOF = true;
    }

    class NullDb
    {
        public function Execute($query)
        {
            return new NullDbResult();
        }
    }

    class TestCart
    {
        private array $products;

        public function __construct(array $products)
        {
            $this->products = $products;
        }

        public function get_products(): array
        {
            return $this->products;
        }
    }

    class StubOtCoupon
    {
        private float $deduction;

        public function __construct(float $deduction)
        {
            $this->deduction = $deduction;
        }

        public function calculate_deductions(): array
        {
            return [
                [
                    'name' => 'TESTCOUPON',
                    'cc_id' => 178,
                    'total' => $this->deduction,
                    'type' => 'F',
                ],
            ];
        }
    }

    $currencies = new currencies();
    $db = new NullDb();

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.save_path', sys_get_temp_dir());
        @session_start();
    }
    $_SESSION['customer_id'] = 42;
    $_SESSION['customer_first_name'] = 'Jane';
    $_SESSION['customer_last_name'] = 'Doe';
}

namespace {
    use PayPalAdvancedCheckout\Zc2Pp\CreatePayPalOrderRequest;

    $failures = 0;

    $assertAmount = static function (array $payload, float $expectedAmount, float $expectedDiscount, string $label) use (&$failures): void {
        $purchase_unit = $payload['purchase_units'][0] ?? [];
        $amount = isset($purchase_unit['amount']['value']) ? (float)$purchase_unit['amount']['value'] : null;
        $discount = isset($purchase_unit['amount']['breakdown']['discount']['value'])
            ? (float)$purchase_unit['amount']['breakdown']['discount']['value']
            : 0.0;

        if ($amount === null || abs($amount - $expectedAmount) >= 0.009) {
            fwrite(STDERR, sprintf("%s: expected amount %.2f, got %s.\n", $label, $expectedAmount, $amount === null ? 'missing' : (string)$amount));
            $failures++;
        }
        if (abs($discount - $expectedDiscount) >= 0.009) {
            fwrite(STDERR, sprintf("%s: expected discount %.2f, got %.2f.\n", $label, $expectedDiscount, $discount));
            $failures++;
        }
    };

    $makeOrder = static function (array $products, float $shipping = 0.0, float $orderTotal = 0.0): order {
        $computed = $orderTotal;
        if ($computed <= 0.0) {
            foreach ($products as $product) {
                $computed += (float)($product['final_price'] ?? 0) * (float)($product['qty'] ?? 0);
            }
            $computed += $shipping;
        }

        return new order([
            'currency' => 'USD',
            'shipping_cost' => $shipping,
            'shipping_tax' => 0.00,
            'total' => $computed,
        ], $products);
    };

    $regularProduct = [
        'id' => 1,
        'name' => 'Acoustic Bass Strings',
        'model' => 'EPBB170',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 39.38,
        'price' => 39.38,
        'quantity' => 1,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ];

    // Case 1: gift credit alone (session fallback, empty ot_diffs).
    unset($_SESSION['cot_gv'], $_SESSION['storecredit'], $_SESSION['cc_id'], $_SESSION['cart'], $GLOBALS['ot_coupon'], $GLOBALS['ot_gv']);
    $_SESSION['cot_gv'] = 15.00;
    $_SESSION['cart'] = new TestCart([$regularProduct]);
    $order = $makeOrder([$regularProduct], 3.95);
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 43.33,
        'shipping_tax' => 0.00,
    ], []);
    $assertAmount($request->get(), 28.33, 15.00, 'gift credit alone');

    // Case 2: coupon alone via ot_coupon calculate_deductions stub.
    unset($_SESSION['cot_gv'], $_SESSION['storecredit'], $_SESSION['cart'], $GLOBALS['ot_gv']);
    $_SESSION['cc_id'] = [178];
    $GLOBALS['ot_coupon'] = new StubOtCoupon(10.00);
    $order = $makeOrder([$regularProduct], 3.95);
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 43.33,
        'shipping_tax' => 0.00,
    ], []);
    $assertAmount($request->get(), 33.33, 10.00, 'coupon alone');

    // Case 3: gift credit + coupon together.
    $_SESSION['cot_gv'] = 15.00;
    $_SESSION['cc_id'] = [178];
    $_SESSION['cart'] = new TestCart([$regularProduct]);
    $GLOBALS['ot_coupon'] = new StubOtCoupon(10.00);
    $order = $makeOrder([$regularProduct], 3.95);
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 43.33,
        'shipping_tax' => 0.00,
    ], []);
    $assertAmount($request->get(), 18.33, 25.00, 'gift credit + coupon');

    // Case 4: GIFT-model products cannot be paid with gift credit.
    $giftProduct = [
        'id' => 2,
        'name' => 'Gift Certificate',
        'model' => 'GIFT25',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 10.00,
        'price' => 10.00,
        'quantity' => 1,
        'onetime_charges' => 0.00,
        'products_virtual' => 1,
        'attributes' => [],
    ];
    $mixedProducts = [$regularProduct, $giftProduct];
    unset($_SESSION['cc_id'], $GLOBALS['ot_coupon']);
    $_SESSION['cot_gv'] = 49.38;
    $_SESSION['cart'] = new TestCart($mixedProducts);
    $order = $makeOrder($mixedProducts, 0.00);
    $order->info['total'] = 49.38;
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 49.38,
        'shipping_tax' => 0.00,
    ], []);
    // Eligible base excludes the $10 GIFT line, so only $39.38 of credit applies.
    $assertAmount($request->get(), 10.00, 39.38, 'GIFT product exclusion');

    // Case 5: do not double-apply cot_gv when ot_diffs already has ot_gv.
    unset($_SESSION['cart'], $_SESSION['cc_id'], $GLOBALS['ot_coupon']);
    $_SESSION['cot_gv'] = 15.00;
    $order = $makeOrder([$regularProduct], 3.95);
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 28.33,
        'shipping_tax' => 0.00,
    ], [
        'ot_gv' => [
            'diff' => [
                'total' => -15.00,
                'shipping_cost' => 0.00,
                'shipping_tax' => 0.00,
            ],
        ],
    ]);
    $assertAmount($request->get(), 28.33, 15.00, 'ot_diffs ot_gv no double apply');

    // Case 6: coupon session fallback still applies when another discount is present.
    unset($_SESSION['cot_gv'], $_SESSION['cart']);
    $_SESSION['cc_id'] = [178];
    $GLOBALS['ot_coupon'] = new StubOtCoupon(10.00);
    $order = $makeOrder([$regularProduct], 3.95);
    $request = new CreatePayPalOrderRequest('paypal', $order, [], [
        'total' => 38.33,
        'shipping_tax' => 0.00,
    ], [
        'ot_group_pricing' => [
            'diff' => [
                'total' => -5.00,
                'shipping_cost' => 0.00,
                'shipping_tax' => 0.00,
            ],
        ],
    ]);
    $assertAmount($request->get(), 28.33, 15.00, 'coupon on top of other discount');

    if ($failures > 0) {
        fwrite(STDERR, sprintf("CreatePayPalOrderRequest session credit discount test FAILED (%d).\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest session credit discount test passed.\n");
}
