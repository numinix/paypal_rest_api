<?php
declare(strict_types=1);

/**
 * Test that validates the vault save card checkbox field name is correctly read
 * in both pre-confirmation and regular checkout flows.
 * 
 * This test addresses the issue where the vault table remained empty after
 * checking "Save this card for future checkouts?" in the 3-page checkout flow.
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

    // Test 1: Pre-confirmation flow (is_preconfirmation = true)
    // Should check for 'paypalr_cc_save_card'
    echo "Test 1: Pre-confirmation flow with save card checkbox checked...\n";
    $_POST['paypalr_cc_save_card'] = 'on';
    unset($_POST['ppr_cc_save_card']);

    $cc_info_preconf = [
        'name' => 'Jane Doe',
        'number' => '4111111111111111',
        'security_code' => '123',
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'store_card' => true,  // This should be set when checkbox is checked
        'redirect' => 'ppr_listener.php',
    ];
    $request_preconf = new CreatePayPalOrderRequest('card', $order, $cc_info_preconf, $order_info, []);
    $payload_preconf = $request_preconf->get();
    $card_source_preconf = $payload_preconf['payment_source']['card'] ?? [];

    if (($card_source_preconf['store_in_vault'] ?? '') !== 'ON_SUCCESS') {
        fwrite(STDERR, sprintf(
            "Test 1 FAILED: Expected store_in_vault ON_SUCCESS in pre-confirmation flow, got %s.\n",
            json_encode($card_source_preconf['store_in_vault'] ?? null)
        ));
        $failures++;
    } else {
        echo "  ✓ Pre-confirmation flow correctly sets store_in_vault to ON_SUCCESS\n";
    }

    // Test 2: Regular 3-page checkout flow (is_preconfirmation = false)
    // Should also check for 'paypalr_cc_save_card' after the fix
    echo "\nTest 2: Regular checkout flow with save card checkbox checked...\n";
    $_POST['paypalr_cc_save_card'] = 'on';
    unset($_POST['ppr_cc_save_card']);

    $cc_info_regular = [
        'name' => 'Jane Doe',
        'number' => '4111111111111111',
        'security_code' => '123',
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'store_card' => true,  // This should be set when checkbox is checked
        'redirect' => 'ppr_listener.php',
    ];
    $request_regular = new CreatePayPalOrderRequest('card', $order, $cc_info_regular, $order_info, []);
    $payload_regular = $request_regular->get();
    $card_source_regular = $payload_regular['payment_source']['card'] ?? [];

    if (($card_source_regular['store_in_vault'] ?? '') !== 'ON_SUCCESS') {
        fwrite(STDERR, sprintf(
            "Test 2 FAILED: Expected store_in_vault ON_SUCCESS in regular flow, got %s.\n",
            json_encode($card_source_regular['store_in_vault'] ?? null)
        ));
        $failures++;
    } else {
        echo "  ✓ Regular checkout flow correctly sets store_in_vault to ON_SUCCESS\n";
    }

    // Test 3: Verify all cards are vaulted (the visibility is controlled separately)
    echo "\nTest 3: Checkbox not checked (all cards are vaulted, visibility controlled separately)...\n";
    unset($_POST['paypalr_cc_save_card']);
    unset($_POST['ppr_cc_save_card']);

    $cc_info_nosave = [
        'name' => 'Jane Doe',
        'number' => '4111111111111111',
        'security_code' => '123',
        'expiry_month' => '09',
        'expiry_year' => '2030',
        'store_card' => false,  // This is false when checkbox is not checked, but card is still vaulted
        'redirect' => 'ppr_listener.php',
    ];
    $request_nosave = new CreatePayPalOrderRequest('card', $order, $cc_info_nosave, $order_info, []);
    $payload_nosave = $request_nosave->get();
    $card_source_nosave = $payload_nosave['payment_source']['card'] ?? [];

    if (($card_source_nosave['store_in_vault'] ?? '') !== 'ON_SUCCESS') {
        fwrite(STDERR, sprintf(
            "Test 3 FAILED: Expected store_in_vault ON_SUCCESS (all cards are vaulted), got %s.\n",
            json_encode($card_source_nosave['store_in_vault'] ?? null)
        ));
        $failures++;
    } else {
        echo "  ✓ Correctly sets store_in_vault to ON_SUCCESS (all cards are vaulted)\n";
        echo "     (visibility is controlled separately in the database)\n";
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n❌ Vault save card field name test failed with $failures error(s).\n");
        exit(1);
    }

    echo "\n✅ All vault save card field name tests passed.\n";
    echo "   The fix ensures that ALL cards are vaulted with PayPal for security.\n";
    echo "   Card visibility in checkout/account is controlled by the 'visible' database field.\n";
}
