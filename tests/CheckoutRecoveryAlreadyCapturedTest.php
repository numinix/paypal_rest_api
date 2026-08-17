<?php
declare(strict_types=1);

/**
 * Checkout recovery helpers and wiring for ORDER_ALREADY_CAPTURED leftovers.
 *
 * Capture must not reuse the create-order PayPal-Request-Id. When PayPal reports
 * ORDER_ALREADY_CAPTURED, GET the live order and treat an existing capture as
 * success, or recapture an APPROVED leftover with a distinct retry Request-Id.
 */

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}

spl_autoload_register(function ($class) {
    $prefix = 'PayPalAdvancedCheckout\\';
    $base_dir = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use PayPalAdvancedCheckout\Common\CheckoutRecovery;

$failures = 0;

function checkout_recovery_assert(bool $condition, string $message) {
    global $failures;
    if ($condition) {
        echo "✓ $message\n";
        return;
    }
    $failures++;
    fwrite(STDERR, "✗ $message\n");
}

echo "=== CheckoutRecovery ORDER_ALREADY_CAPTURED tests ===\n\n";

echo "Test 1: Capture Request-Id is distinct from the create-order GUID\n";
$guid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
$capture_id = CheckoutRecovery::paymentActionRequestId($guid, true);
$authorize_id = CheckoutRecovery::paymentActionRequestId($guid, false);
checkout_recovery_assert($capture_id !== $guid, 'capture Request-Id differs from GUID');
checkout_recovery_assert(str_ends_with($capture_id, '-capture'), 'capture Request-Id ends with -capture');
checkout_recovery_assert(str_ends_with($authorize_id, '-authorize'), 'authorize Request-Id ends with -authorize');
checkout_recovery_assert($capture_id !== $authorize_id, 'capture and authorize Request-Ids differ');

echo "\nTest 2: Retry Request-Id is unique per attempt\n";
$retry_a = CheckoutRecovery::paymentActionRetryRequestId($capture_id);
$retry_b = CheckoutRecovery::paymentActionRetryRequestId($capture_id);
checkout_recovery_assert($retry_a !== $capture_id, 'retry Request-Id differs from capture Request-Id');
checkout_recovery_assert($retry_a !== $retry_b, 'retry Request-Ids are unique');
checkout_recovery_assert(str_contains($retry_a, '-retry-'), 'retry Request-Id includes -retry-');

echo "\nTest 3: Duplicate payment-action issue detection\n";
$captured_error = [
    'name' => 'UNPROCESSABLE_ENTITY',
    'details' => [
        ['issue' => 'ORDER_ALREADY_CAPTURED', 'description' => 'The order was already captured.'],
    ],
];
$authorized_error = [
    'name' => 'ORDER_ALREADY_AUTHORIZED',
    'details' => [],
];
$declined_error = [
    'name' => 'UNPROCESSABLE_ENTITY',
    'details' => [
        ['issue' => 'INSTRUMENT_DECLINED', 'description' => 'The instrument was declined.'],
    ],
];
checkout_recovery_assert(CheckoutRecovery::isDuplicatePaymentActionIssue($captured_error), 'ORDER_ALREADY_CAPTURED is a duplicate issue');
checkout_recovery_assert(CheckoutRecovery::isDuplicatePaymentActionIssue($authorized_error), 'ORDER_ALREADY_AUTHORIZED is a duplicate issue');
checkout_recovery_assert(!CheckoutRecovery::isDuplicatePaymentActionIssue($declined_error), 'INSTRUMENT_DECLINED is not a duplicate issue');
checkout_recovery_assert(CheckoutRecovery::paypalIssueFromErrorInfo($captured_error) === 'ORDER_ALREADY_CAPTURED', 'issue extracted from details');

echo "\nTest 4: Successful payment detection and APPROVED leftover retry\n";
$approved_order = [
    'id' => '6XH02186HH922551G',
    'status' => 'APPROVED',
    'intent' => 'CAPTURE',
];
$completed_order = [
    'id' => '6XH02186HH922551G',
    'status' => 'COMPLETED',
    'purchase_units' => [
        [
            'payments' => [
                'captures' => [
                    [
                        'id' => 'CAP123',
                        'status' => 'COMPLETED',
                    ],
                ],
            ],
        ],
    ],
];
$authorized_order = [
    'id' => 'AUTHORDER',
    'status' => 'COMPLETED',
    'purchase_units' => [
        [
            'payments' => [
                'authorizations' => [
                    [
                        'id' => 'AUTH123',
                        'status' => 'CREATED',
                    ],
                ],
            ],
        ],
    ],
];
checkout_recovery_assert(!CheckoutRecovery::paypalOrderHasSuccessfulPaymentAction($approved_order, true), 'APPROVED order is not a successful capture');
checkout_recovery_assert(CheckoutRecovery::paypalOrderHasSuccessfulPaymentAction($completed_order, true), 'COMPLETED capture is successful');
checkout_recovery_assert(CheckoutRecovery::paypalOrderHasSuccessfulPaymentAction($authorized_order, false), 'CREATED authorization is successful');
checkout_recovery_assert(CheckoutRecovery::paypalOrderIsReusable($approved_order), 'APPROVED leftover is reusable');
checkout_recovery_assert(CheckoutRecovery::paypalOrderIsReusable($completed_order), 'COMPLETED captured order is reusable for checkout resume');

echo "\nTest 5: Preserve APPROVED session state on error\n";
$_SESSION['PayPalAdvancedCheckout'] = [
    'Order' => [
        'id' => '6XH02186HH922551G',
        'status' => 'APPROVED',
        'guid' => $guid,
    ],
];
checkout_recovery_assert(CheckoutRecovery::shouldPreservePayPalOrderOnError(), 'APPROVED PayPal order is preserved');
unset($_SESSION['PayPalAdvancedCheckout']['Order']['id']);
checkout_recovery_assert(!CheckoutRecovery::shouldPreservePayPalOrderOnError(), 'missing PayPal order id is not preserved');
$_SESSION['PayPalAdvancedCheckout']['Order'] = [
    'id' => 'DEADORDER',
    'status' => 'VOIDED',
];
checkout_recovery_assert(!CheckoutRecovery::shouldPreservePayPalOrderOnError(), 'VOIDED PayPal order is not preserved');

echo "\nTest 6: Alert context includes customer and PayPal identifiers\n";
$_SESSION['customer_id'] = 101520;
$_SESSION['customers_email_address'] = 'kmk_y2003@example.com';
$_SESSION['PayPalAdvancedCheckout']['Order'] = [
    'id' => '6XH02186HH922551G',
    'status' => 'APPROVED',
    'guid' => $guid,
];
$context = CheckoutRecovery::checkoutAlertContext();
checkout_recovery_assert(str_contains($context, 'Customer ID: 101520'), 'alert context includes customer id');
checkout_recovery_assert(str_contains($context, 'kmk_y2003@example.com'), 'alert context includes customer email');
checkout_recovery_assert(str_contains($context, '6XH02186HH922551G'), 'alert context includes PayPal order id');

echo "\nTest 7: paypalac capture path uses recovery instead of the create GUID\n";
$paypalac = file_get_contents(DIR_FS_CATALOG . 'includes/modules/payment/paypalac.php');
$common = file_get_contents(DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php');
checkout_recovery_assert(
    strpos($paypalac, "setPayPalRequestId(\$_SESSION['PayPalAdvancedCheckout']['Order']['guid'])") === false,
    'paypalac capture no longer sets Request-Id to the create GUID'
);
checkout_recovery_assert(
    str_contains($paypalac, 'captureOrAuthorizePaymentWithRecovery'),
    'paypalac capture uses captureOrAuthorizePaymentWithRecovery'
);
checkout_recovery_assert(
    str_contains($paypalac, 'shouldPreservePayPalOrderOnError'),
    'paypalac preserves recoverable PayPal orders on redirect'
);
checkout_recovery_assert(
    str_contains($paypalac, "CURRENT_VERSION = '1.3.22'"),
    'CURRENT_VERSION is 1.3.22'
);
checkout_recovery_assert(
    str_contains($paypalac, "case version_compare(MODULE_PAYMENT_PAYPALAC_VERSION, '1.3.22', '<')"),
    'tableCheckup includes 1.3.22 fall-through'
);
checkout_recovery_assert(
    str_contains($common, 'CheckoutRecovery::paymentActionRequestId'),
    'common capture helper sets a capture/authorize Request-Id suffix'
);
checkout_recovery_assert(CheckoutRecovery::shouldKeepExistingOrderWhenLiveLookupFails('COMPLETED'), 'COMPLETED cache is kept when live GET fails');
checkout_recovery_assert(!CheckoutRecovery::shouldKeepExistingOrderWhenLiveLookupFails('VOIDED'), 'VOIDED cache is not kept when live GET fails');
checkout_recovery_assert(!CheckoutRecovery::shouldKeepExistingOrderWhenLiveLookupFails('REFUNDED'), 'REFUNDED cache is not kept when live GET fails');
checkout_recovery_assert(
    str_contains($paypalac, 'keeping the existing PayPal order instead of creating a replacement'),
    'paypalac keeps the existing PayPal order when live GET fails'
);
checkout_recovery_assert(
    str_contains($common, 'recoverExistingPayPalOrderAfterDuplicateCreate'),
    'createOrder recovers ORDER_ALREADY_CAPTURED leftovers'
);
checkout_recovery_assert(
    str_contains($common, 'checkoutAlertContext'),
    'alert emails include checkout context'
);

echo "\n";
if ($failures > 0) {
    fwrite(STDERR, "✗ $failures CheckoutRecovery test(s) failed\n");
    exit(1);
}

echo "✓ All CheckoutRecovery tests passed!\n";
