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
    if (!defined('HTTP_SERVER')) {
        define('HTTP_SERVER', 'https://example.com');
    }
    if (!defined('DIR_WS_CATALOG')) {
        define('DIR_WS_CATALOG', '/shop/');
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
    use PayPalAdvancedCheckout\Zc2Pp\CreatePayPalOrderRequest;

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

    // Test: Vaulted card with empty 'expiry' field but valid expiry_month and expiry_year
    // When using vault_id, PayPal already has the card details - no expiry should be sent.
    // The expiry_month/expiry_year fields should be ignored for vault transactions.
    $cc_info_vault_components = [
        'type' => 'Visa',
        'name' => 'Jane Doe',
        'vault_id' => 'VAULT-456',
        'expiry' => '',  // Empty expiry field
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'last_digits' => '1111',
        'billing_address' => [
            'address_line_1' => '1 Test Way',
            'admin_area_2' => 'Testville',
            'admin_area_1' => 'TS',
            'postal_code' => '12345',
            'country_code' => 'US',
        ],
    ];

    try {
        $request_vault = new CreatePayPalOrderRequest('card', $order, $cc_info_vault_components, $order_info, []);
        $payload_vault = $request_vault->get();
        $card_source_vault = $payload_vault['payment_source']['card'] ?? [];

        if (($card_source_vault['vault_id'] ?? '') !== 'VAULT-456') {
            fwrite(STDERR, sprintf(
                "Expected vault_id VAULT-456, got %s.\n",
                json_encode($card_source_vault['vault_id'] ?? null)
            ));
            $failures++;
        }

        // PayPal does NOT want expiry when vault_id is present - it causes INCOMPATIBLE_PARAMETER_VALUE
        if (isset($card_source_vault['expiry'])) {
            fwrite(STDERR, sprintf(
                "expiry should NOT be present when using vault_id (causes PayPal error), got %s.\n",
                json_encode($card_source_vault['expiry'])
            ));
            $failures++;
        }

        // PayPal does NOT want last_digits when vault_id is present
        if (isset($card_source_vault['last_digits'])) {
            fwrite(STDERR, sprintf(
                "last_digits should NOT be present when using vault_id (causes PayPal error), got %s.\n",
                json_encode($card_source_vault['last_digits'])
            ));
            $failures++;
        }

        // PayPal does NOT want billing_address when vault_id is present
        if (isset($card_source_vault['billing_address'])) {
            fwrite(STDERR, "billing_address should NOT be present when using vault_id (causes PayPal error).\n");
            $failures++;
        }

    } catch (\Exception $e) {
        fwrite(STDERR, sprintf(
            "Exception thrown when building vaulted card with component expiry fields: %s\n",
            $e->getMessage()
        ));
        $failures++;
    }

    // Test: Vaulted card with neither expiry nor components should NOT throw during creation
    // Only vault_id should be sent to PayPal - no other card details
    $cc_info_no_expiry = [
        'type' => 'Visa',
        'name' => 'Jane Doe',
        'vault_id' => 'VAULT-789',
        'last_digits' => '2222',
    ];

    try {
        $request_no_expiry = new CreatePayPalOrderRequest('card', $order, $cc_info_no_expiry, $order_info, []);
        $payload_no_expiry = $request_no_expiry->get();
        $card_source_no_expiry = $payload_no_expiry['payment_source']['card'] ?? [];

        if (($card_source_no_expiry['vault_id'] ?? '') !== 'VAULT-789') {
            fwrite(STDERR, sprintf(
                "Expected vault_id VAULT-789 for card without expiry, got %s.\n",
                json_encode($card_source_no_expiry['vault_id'] ?? null)
            ));
            $failures++;
        }

        // Should not have an expiry field when using vault_id
        if (isset($card_source_no_expiry['expiry'])) {
            fwrite(STDERR, sprintf(
                "Did not expect expiry field when using vault_id, got %s.\n",
                json_encode($card_source_no_expiry['expiry'])
            ));
            $failures++;
        }

        // Should not have last_digits when using vault_id
        if (isset($card_source_no_expiry['last_digits'])) {
            fwrite(STDERR, sprintf(
                "Did not expect last_digits field when using vault_id, got %s.\n",
                json_encode($card_source_no_expiry['last_digits'])
            ));
            $failures++;
        }

    } catch (\Exception $e) {
        fwrite(STDERR, sprintf(
            "Exception thrown for vaulted card without expiry (should be allowed): %s\n",
            $e->getMessage()
        ));
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "CreatePayPalOrderRequest vault expiry component handling test passed.\n");
}
