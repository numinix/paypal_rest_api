<?php
/**
 * Test: Apple Pay token must be sent as JSON string to PayPal
 *
 * This test validates the fix for the MALFORMED_REQUEST_JSON error that occurs
 * when the Apple Pay token is sent as an array/object instead of a JSON string.
 *
 * Problem: PayPal expects /payment_source/apple_pay/token to be a JSON string,
 * not an object/array. The previous code was decoding the token to an array
 * and sending it that way, causing PayPal to return:
 *   400 INVALID_REQUEST - field: /payment_source/apple_pay/token
 *   issue: MALFORMED_REQUEST_JSON
 *
 * Solution:
 * 1. normalizeWalletPayload() in paypal_common.php unwraps paymentData and
 *    encodes the token as a JSON string.
 * 2. CreatePayPalOrderRequest.php keeps the token as a JSON string instead
 *    of decoding it back to an array.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
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
}

namespace {
    use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

    $order_info = [
        'total' => 15.05,
        'shipping_tax' => 0.00,
    ];

    $products = [[
        'id' => 1,
        'name' => 'Augustine Regal Blue High/High Tension Guitar Strings',
        'model' => 'REGAL-BLUE',
        'qty' => 1,
        'tax' => 0.0,
        'final_price' => 7.39,
        'onetime_charges' => 0.00,
        'products_virtual' => 0,
        'attributes' => [],
    ]];

    $order = new order([
        'currency' => 'USD',
        'shipping_cost' => 7.66,
        'shipping_tax' => 0.00,
    ], $products);

    $failures = 0;

    echo "\n=== Apple Pay Token as JSON String Test ===\n\n";

    // Simulate the token as it would be stored in session after normalizeWalletPayload()
    // This represents the actual Apple Pay payment token structure (using mock values)
    $applePaymentToken = [
        'data' => 'kHvow6zsnfZqGd+2eYYM8UQu0EaW73CaPXWQzgJhqPmhempCrumnkD58NS8s4zN2xHOR6bGQtlF0E2RsKl3kjfsZeQxVlew3/4vhrLp2a58z/6y2Bhk99B45tPWIT6QtCXr4zKTwnbtiwPmfNWeRYd6E6ImnfWhmt4aZ/VfoI/pc76WHIyqnrDafZjRHyk5Wf1CfqQy9aVl8VM3pM9Qi4AX3AhM4O8anT8edftWo7uIb8b0VvHq1Q7kImUdYhFy7ZSJxXmmysDGrpPcU/ACxtkctIr1RU7hTZFlSCXtJR3jUdZCRGg6afrsL8UQwrLG7/P/wdYqK1gPP1bG3/kJ48zo8ZN5SidL/580zY5Y8pSXPCoZBzRVpUbrvtlWQ9dlTvU3YZHj8puGT6FOJeDt+Zcl6yPX0p85svlbaPr0u2aw=',
        'signature' => 'MOCK_SIGNATURE_FOR_TESTING_PURPOSES_ONLY',
        'header' => [
            'publicKeyHash' => 'vuKS80eIGBVWIJSRM/rNPZIGY/bHhogd1I4f/jc1qdE=',
            'ephemeralPublicKey' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAErxNT9FJeIhpiftOFYG/XNUBINNi/fVSH2zjj72/OBVOkgyuxGRjjm1WnBGVzzGj2IFy3eBRAUB5ej4GSfDJPQw==',
            'transactionId' => '4735775d08717cfd911367c4b63ae4d9a8fe14514d3484ed5a91a9b6dd1c10c7',
        ],
        'version' => 'EC_v1',
    ];

    // normalizeWalletPayload() would have JSON-encoded this token
    $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] = [
        'token' => json_encode($applePaymentToken),
    ];

    // Create the PayPal order request
    $request = new CreatePayPalOrderRequest('apple_pay', $order, [], $order_info, []);
    $payload = $request->get();

    echo "Test 1: Verify payment_source.apple_pay exists...\n";
    if (!isset($payload['payment_source']['apple_pay'])) {
        fwrite(STDERR, "  ❌ FAIL: payment_source.apple_pay is missing\n");
        $failures++;
    } else {
        echo "  ✓ PASS: payment_source.apple_pay exists\n";
    }

    echo "\nTest 2: Verify token is a JSON string (not an array)...\n";
    $token = $payload['payment_source']['apple_pay']['token'] ?? null;
    if (!is_string($token)) {
        fwrite(STDERR, "  ❌ FAIL: Token is not a string (type: " . gettype($token) . ")\n");
        fwrite(STDERR, "  PayPal expects token to be a JSON string, not an array/object\n");
        $failures++;
    } else {
        echo "  ✓ PASS: Token is a string\n";
    }

    echo "\nTest 3: Verify token is valid JSON...\n";
    if (is_string($token)) {
        $decoded = json_decode($token, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            fwrite(STDERR, "  ❌ FAIL: Token is not valid JSON\n");
            $failures++;
        } else {
            echo "  ✓ PASS: Token is valid JSON\n";
        }
    } else {
        fwrite(STDERR, "  ⊘ SKIP: Token is not a string\n");
    }

    echo "\nTest 4: Verify decoded token has required fields...\n";
    if (is_string($token)) {
        $decoded = json_decode($token, true);
        $requiredFields = ['data', 'signature', 'header', 'version'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($decoded[$field])) {
                $missingFields[] = $field;
            }
        }
        if (count($missingFields) > 0) {
            fwrite(STDERR, "  ❌ FAIL: Token missing required fields: " . implode(', ', $missingFields) . "\n");
            $failures++;
        } else {
            echo "  ✓ PASS: Token has all required fields (data, signature, header, version)\n";
        }
    } else {
        fwrite(STDERR, "  ⊘ SKIP: Token is not a string\n");
    }

    echo "\nTest 5: Verify token does NOT have paymentData wrapper...\n";
    if (is_string($token)) {
        $decoded = json_decode($token, true);
        if (isset($decoded['paymentData'])) {
            fwrite(STDERR, "  ❌ FAIL: Token still contains paymentData wrapper (should be unwrapped)\n");
            fwrite(STDERR, "  normalizeWalletPayload() should unwrap this before encoding\n");
            $failures++;
        } else {
            echo "  ✓ PASS: Token is properly unwrapped (no paymentData wrapper)\n";
        }
    } else {
        fwrite(STDERR, "  ⊘ SKIP: Token is not a string\n");
    }

    echo "\nTest 6: Verify request can be JSON-encoded (as PayPal expects)...\n";
    $jsonRequest = json_encode($payload);
    if ($jsonRequest === false) {
        fwrite(STDERR, "  ❌ FAIL: Request cannot be JSON-encoded\n");
        $failures++;
    } else {
        echo "  ✓ PASS: Request can be JSON-encoded\n";
        
        // Verify the token field in the JSON is a string value, not an object
        $parsedRequest = json_decode($jsonRequest, true);
        $tokenInJson = $parsedRequest['payment_source']['apple_pay']['token'] ?? null;
        if (!is_string($tokenInJson)) {
            fwrite(STDERR, "  ❌ FAIL: Token in JSON request is not a string\n");
            $failures++;
        } else {
            echo "  ✓ PASS: Token in JSON request is a string (as PayPal expects)\n";
        }
    }

    // Summary
    echo "\n=== Test Summary ===\n";
    if ($failures > 0) {
        fwrite(STDERR, "\n❌ FAILED: $failures test(s) failed\n\n");
        fwrite(STDERR, "The fix is incomplete. The Apple Pay token must be sent as a JSON string,\n");
        fwrite(STDERR, "not as an array/object, to avoid PayPal's MALFORMED_REQUEST_JSON error.\n\n");
        exit(1);
    }

    echo "\n✅ All tests passed!\n\n";
    echo "The Apple Pay token is correctly formatted as a JSON string.\n";
    echo "This prevents the PayPal 400 MALFORMED_REQUEST_JSON error.\n\n";
}
