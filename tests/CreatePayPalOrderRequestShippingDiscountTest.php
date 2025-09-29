<?php
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
    if (!defined('MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK')) {
        define('MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK', 'USD');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE')) {
        define('MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE', 'Final Sale');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_HANDLING_OT')) {
        define('MODULE_PAYMENT_PAYPALR_HANDLING_OT', '');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_INSURANCE_OT')) {
        define('MODULE_PAYMENT_PAYPALR_INSURANCE_OT', '');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_DISCOUNT_OT')) {
        define('MODULE_PAYMENT_PAYPALR_DISCOUNT_OT', '');
    }
    if (!defined('SHIPPING_ORIGIN_ZIP')) {
        define('SHIPPING_ORIGIN_ZIP', '');
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/ErrorInfo.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Logger.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Api/Data/CountryCodes.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Zc2Pp/Amount.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Zc2Pp/Address.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Zc2Pp/Name.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Zc2Pp/CreatePayPalOrderRequest.php';

    class currencies
    {
        public function rateAdjusted($value, bool $use_defaults = true, string $currency_code = ''): float
        {
            return (float)$value;
        }
    }

    class order
    {
        public array $info;
        public array $products;
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

    $currencies = new currencies();
    $db = new NullDb();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['customer_id'] = 42;
    $_SESSION['customer_first_name'] = 'Jane';
    $_SESSION['customer_last_name'] = 'Doe';
}

namespace {
    use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

    $order_info = [
        'total' => 40.00,
        'shipping_tax' => 0.00,
        'free_shipping_coupon' => true,
    ];

    $products = [[
        'id' => 1,
        'name' => 'Sample Item',
        'model' => 'SAMPLE',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 40.00,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ]];

    $order = new order([
        'currency' => 'USD',
        'shipping_cost' => 5.00,
        'shipping_tax' => 0.00,
    ], $products);

    $ot_diffs = [
        'ot_coupon' => [
            'diff' => [
                'total' => -5.00,
                'shipping_cost' => -5.00,
                'shipping_tax' => 0.00,
            ],
        ],
    ];

    $request = new CreatePayPalOrderRequest('paypal', $order, [], $order_info, $ot_diffs);
    $payload = $request->get();
    $purchase_unit = $payload['purchase_units'][0];

    $failures = 0;

    if (!isset($purchase_unit['amount']['breakdown'])) {
        fwrite(STDERR, "Amount breakdown missing from payload.\n");
        $failures++;
    } else {
        $breakdown = $purchase_unit['amount']['breakdown'];

        if (!isset($breakdown['shipping_discount']) || (float)$breakdown['shipping_discount']['value'] !== 5.00) {
            fwrite(STDERR, sprintf("Expected shipping_discount of 5.00, got %s.\n", json_encode($breakdown['shipping_discount'] ?? null)));
            $failures++;
        }

        if (isset($breakdown['discount'])) {
            fwrite(STDERR, 'Expected discount element to be omitted when only a shipping discount applies.' . "\n");
            $failures++;
        }

        $expected_total = 0.0;
        foreach ($breakdown as $name => $amount) {
            $value = (float)$amount['value'];
            if (in_array($name, ['discount', 'shipping_discount'], true)) {
                $expected_total -= $value;
            } else {
                $expected_total += $value;
            }
        }
        $expected_total = number_format($expected_total, 2, '.', '');
        if ($expected_total !== $purchase_unit['amount']['value']) {
            fwrite(STDERR, sprintf(
                'Breakdown total %s did not match amount %s.' . "\n",
                $expected_total,
                $purchase_unit['amount']['value']
            ));
            $failures++;
        }
    }

    if (empty($purchase_unit['items'])) {
        fwrite(STDERR, "Expected item breakdown to be retained.\n");
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest shipping discount test passed.\n");
}
