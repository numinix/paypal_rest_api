<?php
/**
 * Test to verify wallet payment types have correct payment_source handling:
 * - Apple Pay: Include payment_source.apple_pay with token ONLY when token is available in session
 *              When token is not available, do NOT send payment_source (the order will be 
 *              recreated with the token during server-side processWalletConfirmation)
 * - Google Pay, Venmo: Do NOT include payment_source (SDK handles it)
 * - Card, PayPal: Include full payment_source details
 *
 * Apple Pay flow (UPDATED in fix for 422/500 errors):
 * 1. Initial order creation (button click): No token in session -> NO payment_source
 * 2. Server-side confirmation: Token available -> recreate order with payment_source.apple_pay.token
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

    if (!class_exists('currencies')) {
        class currencies
        {
            public function rateAdjusted($value, bool $use_defaults = true, string $currency_code = ''): float
            {
                return (float)$value;
            }
        }
    }

    if (!class_exists('order')) {
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
                    'telephone' => '555-123-4567',
                ];
            }
        }
    }

    if (!class_exists('NullDbResult')) {
        class NullDbResult
        {
            public bool $EOF = true;
        }
    }

    if (!class_exists('NullDb')) {
        class NullDb
        {
            public function Execute($query)
            {
                return new NullDbResult();
            }
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
    $applePaymentData = [
        'data' => 'encrypted-data',
        'signature' => 'signature',
        'header' => ['ephemeralPublicKey' => 'abc'],
        'version' => 'EC_v1',
    ];

    $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] = [
        'token' => json_encode([
            'paymentData' => $applePaymentData,
            'paymentMethod' => ['type' => 'credit'],
        ]),
    ];
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

    // Test 1: Google Pay should NOT have payment_source
    $request_googlepay = new CreatePayPalOrderRequest('google_pay', $order, [], $order_info, []);
    $payload_googlepay = $request_googlepay->get();

    if (isset($payload_googlepay['payment_source']['google_pay'])) {
        fwrite(STDERR, "FAIL: google_pay request should NOT include payment_source.google_pay\n");
        fwrite(STDERR, "  The PayPal SDK handles the payment source during the wallet authorization flow.\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ google_pay: No payment_source included (correct behavior)\n");
    }

    // Test 2a: Apple Pay WITH token in session SHOULD include payment_source.apple_pay with token
    $request_applepay = new CreatePayPalOrderRequest('apple_pay', $order, [], $order_info, []);
    $payload_applepay = $request_applepay->get();

    if (!isset($payload_applepay['payment_source']['apple_pay'])) {
        fwrite(STDERR, "FAIL: apple_pay request with token in session SHOULD include payment_source.apple_pay\n");
        fwrite(STDERR, "  Token in session should be included in the order request.\n");
        $failures++;
    } elseif ($payload_applepay['payment_source']['apple_pay']['token'] !== $applePaymentData) {
        fwrite(STDERR, "FAIL: apple_pay payment_source.apple_pay should include paymentData token from session\n");
        fwrite(STDERR, "  token payload: " . json_encode($payload_applepay['payment_source']['apple_pay']['token']) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ apple_pay (with token): Has paymentData-only payment_source.apple_pay (correct behavior)\n");
    }

    // Test 2a-extended: Apple Pay token stored as JSON should be decoded and flattened to paymentData
    $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] = [
        'token' => json_encode([
            'paymentData' => array_merge($applePaymentData, ['foo' => 'bar']),
        ]),
    ];
    $request_applepay_json = new CreatePayPalOrderRequest('apple_pay', $order, [], $order_info, []);
    $payload_applepay_json = $request_applepay_json->get();

    $flattenedToken = $payload_applepay_json['payment_source']['apple_pay']['token'] ?? null;
    if (!is_array($flattenedToken) || ($flattenedToken['foo'] ?? '') !== 'bar' || ($flattenedToken['data'] ?? '') !== 'encrypted-data') {
        fwrite(STDERR, "FAIL: apple_pay token should be decoded and flattened to paymentData before request\n");
        fwrite(STDERR, "  token payload: " . json_encode($flattenedToken) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ apple_pay: JSON token decoded and flattened to paymentData for createOrder (correct behavior)\n");
    }

    // Test 2b: Apple Pay WITHOUT token in session should NOT include payment_source.apple_pay
    // This simulates the initial order creation when the button is clicked but before token is available
    // PayPal rejects an empty apple_pay object with MALFORMED_REQUEST_JSON, so it must be omitted
    unset($_SESSION['PayPalRestful']['WalletPayload']['apple_pay']);
    $request_applepay_no_token = new CreatePayPalOrderRequest('apple_pay', $order, [], $order_info, []);
    $payload_applepay_no_token = $request_applepay_no_token->get();

    if (isset($payload_applepay_no_token['payment_source']['apple_pay'])) {
        fwrite(STDERR, "FAIL: apple_pay request without token should NOT include payment_source.apple_pay\n");
        fwrite(STDERR, "  payment_source: " . json_encode($payload_applepay_no_token['payment_source'] ?? []) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ apple_pay (without token): payment_source.apple_pay omitted (correct behavior)\n");
    }
    
    // Restore token for subsequent tests
    $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] = ['token' => 'test-apple-token'];

    // Test 3: Venmo should NOT have payment_source
    $request_venmo = new CreatePayPalOrderRequest('venmo', $order, [], $order_info, []);
    $payload_venmo = $request_venmo->get();

    if (isset($payload_venmo['payment_source']['venmo'])) {
        fwrite(STDERR, "FAIL: venmo request should NOT include payment_source.venmo\n");
        fwrite(STDERR, "  Including it causes NOT_ENABLED_TO_VAULT_PAYMENT_SOURCE error if vaulting isn't enabled.\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ venmo: No payment_source included (correct behavior)\n");
    }

    // Test 4: Card payment SHOULD have payment_source
    $cc_info = [
        'name' => 'Jane Doe',
        'number' => '4111111111111111',
        'security_code' => '123',
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'redirect' => 'ppr_listener.php',
    ];
    $request_card = new CreatePayPalOrderRequest('card', $order, $cc_info, $order_info, []);
    $payload_card = $request_card->get();

    if (!isset($payload_card['payment_source']['card'])) {
        fwrite(STDERR, "FAIL: card request SHOULD include payment_source.card\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ card: Has payment_source.card (correct behavior)\n");
    }

    // Test 5: PayPal payment SHOULD have payment_source
    $request_paypal = new CreatePayPalOrderRequest('paypal', $order, [], $order_info, []);
    $payload_paypal = $request_paypal->get();

    if (!isset($payload_paypal['payment_source']['paypal'])) {
        fwrite(STDERR, "FAIL: paypal request SHOULD include payment_source.paypal\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ paypal: Has payment_source.paypal (correct behavior)\n");
    }

    // Test 6: Wallet payment requests should still have the required fields
    // (intent, purchase_units, etc.)
    if (!isset($payload_googlepay['intent'])) {
        fwrite(STDERR, "FAIL: google_pay request should have intent\n");
        $failures++;
    }
    if (!isset($payload_googlepay['purchase_units'])) {
        fwrite(STDERR, "FAIL: google_pay request should have purchase_units\n");
        $failures++;
    }

    if (!isset($payload_applepay['intent'])) {
        fwrite(STDERR, "FAIL: apple_pay request should have intent\n");
        $failures++;
    }
    if (!isset($payload_applepay['purchase_units'])) {
        fwrite(STDERR, "FAIL: apple_pay request should have purchase_units\n");
        $failures++;
    }

    if (!isset($payload_venmo['intent'])) {
        fwrite(STDERR, "FAIL: venmo request should have intent\n");
        $failures++;
    }
    if (!isset($payload_venmo['purchase_units'])) {
        fwrite(STDERR, "FAIL: venmo request should have purchase_units\n");
        $failures++;
    }

    if ($failures === 0) {
        fwrite(STDOUT, "\n  ✓ All wallet payment requests have required fields (intent, purchase_units)\n");
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    }

    fwrite(STDOUT, "\n✓ CreatePayPalOrderRequest wallet payment source test passed.\n");
}
