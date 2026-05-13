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
    use PayPalAdvancedCheckout\Zc2Pp\CreatePayPalOrderRequest;

    $order_info = [
        'total' => 10.72,
        'shipping_tax' => 0.00,
    ];

    $products = [[
        'id' => 1,
        'name' => 'Sample Item',
        'model' => 'SAMPLE',
        'qty' => 1,
        'tax' => 7.25,
        'final_price' => 10.00,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ]];

    $order = new order([
        'currency' => 'USD',
        'shipping_cost' => 0.00,
        'shipping_tax' => 0.00,
    ], $products);

    $request = new CreatePayPalOrderRequest('paypal', $order, [], $order_info, []);
    $payload = $request->get();
    $purchase_unit = $payload['purchase_units'][0];
    $breakdown = $purchase_unit['amount']['breakdown'] ?? [];

    $failures = 0;
    $tax_total = (float)($breakdown['tax_total']['value'] ?? -1);
    $item_tax_total = 0.0;
    foreach ($purchase_unit['items'] ?? [] as $item) {
        $item_tax_total += (float)$item['quantity'] * (float)$item['tax']['value'];
    }

    if (abs($tax_total - $item_tax_total) > 0.001) {
        fwrite(STDERR, sprintf("Expected tax_total %0.2f to match item taxes %0.2f.\n", $tax_total, $item_tax_total));
        $failures++;
    }

    if (!isset($breakdown['discount']) || (float)$breakdown['discount']['value'] !== 0.01) {
        fwrite(STDERR, sprintf("Expected rounding discount of 0.01, got %s.\n", json_encode($breakdown['discount'] ?? null)));
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

    if (number_format($expected_total, 2, '.', '') !== $purchase_unit['amount']['value']) {
        fwrite(STDERR, sprintf("Expected breakdown total to match amount %s, got %0.2f.\n", $purchase_unit['amount']['value'], $expected_total));
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest tax total mismatch prevention test passed.\n");
}
