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

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/ErrorInfo.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/Data/CountryCodes.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Amount.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Address.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/Name.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/CreatePayPalOrderRequest.php';

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
        public array $totals = [];

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

    if (!defined('MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS')) {
        define('MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS', 'true');
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
    use PayPalAdvancedCheckout\Zc2Pp\CreatePayPalOrderRequest;

    $order_info = [
        'total' => 10.50,
        'shipping_tax' => 0.00,
    ];

    $products = [[
        'id' => 1,
        'name' => 'Sample Item',
        'model' => 'SAMPLE',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 10.00,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ]];

    $base_order = new order([
        'currency' => 'USD',
        'shipping_cost' => 0.00,
        'shipping_tax' => 0.00,
    ], $products);

    $failures = 0;

    $ot_diffs_with_local = [
        'ot_local_sales_taxes' => [
            'diff' => [
                'total' => 0.50,
                'tax' => 0.50,
            ],
        ],
    ];

    $request_with_diffs = new CreatePayPalOrderRequest('paypal', $base_order, [], $order_info, $ot_diffs_with_local);
    $payload_with_diffs = $request_with_diffs->get();
    $tax_total_with_diffs = (float)$payload_with_diffs['purchase_units'][0]['amount']['breakdown']['tax_total']['value'];
    if (abs($tax_total_with_diffs - 0.50) > 0.001) {
        fwrite(STDERR, sprintf("Expected tax_total of 0.50 from ot_diffs, got %0.2f.\n", $tax_total_with_diffs));
        $failures++;
    }

    $order_with_totals = new order([
        'currency' => 'USD',
        'shipping_cost' => 0.00,
        'shipping_tax' => 0.00,
    ], $products);
    $order_with_totals->totals = [
        [
            'class' => 'ot_local_sales_taxes',
            'value' => 0.75,
        ],
    ];

    $request_with_order_totals = new CreatePayPalOrderRequest('paypal', $order_with_totals, [], [
        'total' => 10.75,
        'shipping_tax' => 0.00,
    ], []);
    $payload_with_order_totals = $request_with_order_totals->get();
    $tax_total_with_order_totals = (float)$payload_with_order_totals['purchase_units'][0]['amount']['breakdown']['tax_total']['value'];
    if (abs($tax_total_with_order_totals - 0.75) > 0.001) {
        fwrite(STDERR, sprintf("Expected tax_total of 0.75 from order totals fallback, got %0.2f.\n", $tax_total_with_order_totals));
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest local sales tax plugin compatibility test passed.\n");
}
