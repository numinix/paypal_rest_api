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
    if (!defined('HTTP_SERVER')) {
        define('HTTP_SERVER', 'https://example.com');
    }
    if (!defined('DIR_WS_CATALOG')) {
        define('DIR_WS_CATALOG', '/shop/');
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
                'suburb' => '',
                'city' => 'Testville',
                'state' => 'Test State',
                'state_code' => 'TS',
                'postcode' => '12345',
                'country' => [
                    'iso_code_2' => 'US',
                    'iso_code_3' => 'USA',
                    'title' => 'United States',
                ],
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
    $_SESSION['customer_id'] = 99;
    $_SESSION['customer_first_name'] = 'Jane';
    $_SESSION['customer_last_name'] = 'Doe';
}

namespace {
    use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

    $order_info = [
        'total' => 100.00,
        'shipping_tax' => 0.00,
    ];

    $products = [[
        'id' => 1,
        'name' => 'Sample Item',
        'model' => 'SAMPLE',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 100.00,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ]];

    $order = new order([
        'currency' => 'USD',
        'shipping_cost' => 0.00,
        'shipping_tax' => 0.00,
    ], $products);

    $failures = 0;

    // New card saved to vault.
    $cc_info_save = [
        'name' => 'Jane Doe',
        'number' => '4111111111111111',
        'security_code' => '123',
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'store_card' => true,
        'redirect' => 'ppr_listener.php',
    ];
    $request_save = new CreatePayPalOrderRequest('card', $order, $cc_info_save, $order_info, []);
    $payload_save = $request_save->get();
    $card_source_save = $payload_save['payment_source']['card'] ?? [];

    if (($card_source_save['store_in_vault'] ?? '') !== 'ON_SUCCESS') {
        fwrite(STDERR, sprintf(
            "Expected store_in_vault ON_SUCCESS for saved card request, got %s.\n",
            json_encode($card_source_save['store_in_vault'] ?? null)
        ));
        $failures++;
    }

    // New card without saving to vault.
    $cc_info_nosave = $cc_info_save;
    unset($cc_info_nosave['store_card']);
    $request_nosave = new CreatePayPalOrderRequest('card', $order, $cc_info_nosave, $order_info, []);
    $payload_nosave = $request_nosave->get();
    $card_source_nosave = $payload_nosave['payment_source']['card'] ?? [];

    if (($card_source_nosave['store_in_vault'] ?? '') !== 'OFF') {
        fwrite(STDERR, sprintf(
            "Expected store_in_vault OFF when save not requested, got %s.\n",
            json_encode($card_source_nosave['store_in_vault'] ?? null)
        ));
        $failures++;
    }

    // Existing vaulted card reuse path.
    $cc_info_vault = [
        'type' => 'Visa',
        'name' => 'Jane Doe',
        'vault_id' => 'VAULT-123',
        'expiry' => '2030-09',
        'last_digits' => '1111',
        'billing_address' => [
            'address_line_1' => '1 Test Way',
            'admin_area_2' => 'Testville',
            'admin_area_1' => 'TS',
            'postal_code' => '12345',
            'country_code' => 'US',
        ],
        'stored_credential' => [
            'payment_initiator' => 'CUSTOMER',
            'payment_type' => 'UNSCHEDULED',
            'usage' => 'SUBSEQUENT',
        ],
    ];
    $request_vault = new CreatePayPalOrderRequest('card', $order, $cc_info_vault, $order_info, []);
    $payload_vault = $request_vault->get();
    $card_source_vault = $payload_vault['payment_source']['card'] ?? [];

    if (($card_source_vault['vault_id'] ?? '') !== 'VAULT-123') {
        fwrite(STDERR, sprintf(
            "Expected vault_id VAULT-123, got %s.\n",
            json_encode($card_source_vault['vault_id'] ?? null)
        ));
        $failures++;
    }

    if (isset($card_source_vault['store_in_vault'])) {
        fwrite(STDERR, 'store_in_vault should not be present when using a vaulted card.' . "\n");
        $failures++;
    }

    if (($card_source_vault['expiry'] ?? '') !== '2030-09') {
        fwrite(STDERR, sprintf(
            "Expected expiry 2030-09, got %s.\n",
            json_encode($card_source_vault['expiry'] ?? null)
        ));
        $failures++;
    }

    if (($card_source_vault['last_digits'] ?? '') !== '1111') {
        fwrite(STDERR, sprintf(
            "Expected last_digits 1111, got %s.\n",
            json_encode($card_source_vault['last_digits'] ?? null)
        ));
        $failures++;
    }

    if (empty($card_source_vault['billing_address'])) {
        fwrite(STDERR, "Expected billing_address to be retained for vaulted card." . "\n");
        $failures++;
    }

    if (($card_source_vault['attributes']['stored_credential'] ?? []) !== $cc_info_vault['stored_credential']) {
        fwrite(STDERR, 'Stored credential attributes were not passed through for vaulted card usage.' . "\n");
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest vault handling test passed.\n");
}
