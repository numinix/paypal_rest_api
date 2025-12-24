<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'one_page_checkout';
$loaderPrefix = 'oprc';
require_once('includes/application_top.php');
require_once(__DIR__ . '/includes/oprc_ajax_common.php');

header('HTTP/1.1 200 OK');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$response = [
    'status' => 'error',
];

$preconditions = oprc_validate_checkout_state(
    $_SESSION ?? [],
    $_SESSION['cart'] ?? null,
    function ($customerId) {
        if (function_exists('zen_get_customer_validate_session')) {
            return zen_get_customer_validate_session($customerId) !== false;
        }

        return true;
    },
    function () {
        return zen_href_link(FILENAME_LOGIN, '', 'SSL');
    },
    function () {
        return zen_href_link(FILENAME_SHOPPING_CART);
    }
);

if ($preconditions !== null) {
    echo oprc_encode_json_response(array_merge($response, $preconditions));
    require_once('includes/application_bottom.php');
    return;
}

$addressType = $_POST['addressType'] ?? 'billto';
if (!in_array($addressType, ['billto', 'shipto', 'both'], true)) {
    $addressType = 'billto';
}

$previousSendTo = $_SESSION['sendto'] ?? null;

if (!isset($_POST['oprc_change_address']) || $_POST['oprc_change_address'] === '') {
    $_POST['oprc_change_address'] = 'submit';
}

if (!isset($_POST['action']) || $_POST['action'] === '') {
    $_POST['action'] = 'submit';
}

$_POST['addressType'] = $addressType;

if (!isset($messageStack) || !is_object($messageStack)) {
    if (!class_exists('messageStack')) {
        require_once(DIR_WS_CLASSES . 'message_stack.php');
    }

    $messageStack = new messageStack();
}

oprc_import_session_messages($messageStack);

$initialCheckoutAddressMessages = oprc_snapshot_message_stack_area($messageStack, 'checkout_address');

require(DIR_WS_MODULES . zen_get_module_directory('checkout_new_address.php'));

$payload = oprc_build_change_address_payload([
    'baselineMessages' => $initialCheckoutAddressMessages,
]);

$hasSubmission = oprc_is_change_address_submission($_POST ?? []);
$hasErrors = !empty($payload['hasErrors']) && $hasSubmission;

$currentSendTo = $_SESSION['sendto'] ?? null;
$shippingAddressChanged = oprc_has_shipping_address_changed($addressType, $previousSendTo, $currentSendTo);

$response = array_merge(
    [
        'status' => $hasErrors ? 'warning' : 'success',
        'messagesHtml' => $payload['messagesHtml'],
        'shippingAddressChanged' => $shippingAddressChanged,
    ],
    array_diff_key($payload, ['hasErrors' => true, 'messagesHtml' => true])
);

echo oprc_encode_json_response($response);

require_once('includes/application_bottom.php');

function oprc_build_change_address_payload(array $options = [])
{
    global $template, $current_page_base, $messageStack;
    global $order, $order_total_modules, $payment_modules, $shipping_modules, $credit_covers;
    global $quotes;

    oprc_ensure_language_is_loaded('lang.one_page_checkout');

    require_once(DIR_WS_CLASSES . 'order.php');
    $order = new order();

    require_once(DIR_WS_CLASSES . 'shipping.php');
    $shipping_modules = new shipping();

    // Capture shipping update data before it gets cleared by oprc_capture_shipping_methods_html
    $shippingUpdate = $GLOBALS['oprc_last_shipping_update'] ?? null;
    $existingUpdateValues = [];
    if (is_array($shippingUpdate)) {
        if (!empty($shippingUpdate['delivery_updates']) && is_array($shippingUpdate['delivery_updates'])) {
            $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['delivery_updates']);
        }
        if (!empty($shippingUpdate['module_dates']) && is_array($shippingUpdate['module_dates'])) {
            $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['module_dates']);
        }
    }

    $shippingMethodsHtml = oprc_capture_shipping_methods_html();

    $quotesList = isset($quotes) && is_array($quotes)
        ? $quotes
        : (isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes']) ? $GLOBALS['quotes'] : []);

    $deliveryPayload = [];
    oprc_attach_delivery_updates($deliveryPayload, $quotesList, $shipping_modules, $existingUpdateValues);

    $deliveryUpdates = isset($deliveryPayload['deliveryUpdates']) ? $deliveryPayload['deliveryUpdates'] : [];

    // Refresh the order and shipping modules to ensure they reflect any updates
    // made by the shipping-quote recalculation.  The order totals need to be
    // rebuilt after the shipping selection has been adjusted so that the
    // displayed shipping amount remains accurate when the address changes.
    $order = new order();
    $shipping_modules = new shipping();

    require_once(DIR_WS_CLASSES . 'payment.php');
    $payment_modules = new payment();

    require_once(DIR_WS_CLASSES . 'order_total.php');
    $order_total_modules = new order_total();
    $order_total_modules->collect_posts();
    $order_total_modules->pre_confirmation_check();
    $order_totals = $order_total_modules->process();

    $credit_covers = (isset($_SESSION['credit_covers']) && $_SESSION['credit_covers'] == true);
    if ($credit_covers) {
        unset($_SESSION['payment']);
    }

    $originalPageBase = $current_page_base;
    $current_page_base = 'one_page_checkout';

    if (!isset($messageStack) || !is_object($messageStack)) {
        if (!class_exists('messageStack')) {
            require_once(DIR_WS_CLASSES . 'message_stack.php');
        }
        $messageStack = new messageStack();
    }

    oprc_import_session_messages($messageStack);

    $step3Html = oprc_capture_template_output('tpl_modules_oprc_step_3.php');
    $orderTotalHtml = oprc_capture_template_output('tpl_modules_oprc_ordertotal.php');

    $current_page_base = $originalPageBase;

    $baselineMessages = [];
    if (isset($options['baselineMessages']) && is_array($options['baselineMessages'])) {
        $baselineMessages = $options['baselineMessages'];
    }

    $currentMessages = oprc_snapshot_message_stack_area($messageStack, 'checkout_address');

    $payload = [
        'step3Html' => $step3Html,
        'orderTotalHtml' => $orderTotalHtml,
        'oprcAddressMissing' => oprc_is_address_missing() ? 'true' : 'false',
        'hasErrors' => oprc_message_stack_has_new_entries($baselineMessages, $currentMessages),
        'messagesHtml' => (isset($messageStack) && is_object($messageStack)) ? $messageStack->output('checkout_address') : '',
    ];

    $addressesHtml = oprc_extract_inner_html($step3Html, 'oprcAddresses');
    if ($addressesHtml !== null) {
        $payload['oprcAddresses'] = $addressesHtml;
    }

    $shippingHtml = oprc_extract_inner_html($step3Html, 'shippingMethodContainer');
    if ($shippingHtml !== null) {
        $payload['shippingMethodContainer'] = $shippingHtml;
    }

    if ($shippingMethodsHtml !== null) {
        $payload['shippingMethodsHtml'] = $shippingMethodsHtml;
    }

    $payload['deliveryUpdates'] = $deliveryUpdates;
    // (debug – optional)
    $payload['moduleDeliveryDates'] = isset($deliveryPayload['moduleDeliveryDates']) ? $deliveryPayload['moduleDeliveryDates'] : [];
    $payload['methodDeliveryDates'] = isset($deliveryPayload['methodDeliveryDates']) ? $deliveryPayload['methodDeliveryDates'] : [];

    if (oprc_should_refresh_payment_container()) {
        $paymentHtml = oprc_extract_inner_html($step3Html, 'paymentMethodContainer');
        if ($paymentHtml !== null) {
            $payload['paymentMethodContainer'] = $paymentHtml;
        }

        $paymentOuterHtml = oprc_extract_outer_html($step3Html, 'paymentMethodContainer');
        if ($paymentOuterHtml !== null && $paymentOuterHtml !== '') {
            $payload['paymentMethodContainerOuter'] = $paymentOuterHtml;
        }
    }

    $orderTotalWrapper = oprc_extract_inner_html($orderTotalHtml, 'shopBagWrapper');
    if ($orderTotalWrapper !== null) {
        $payload['shopBagWrapper'] = $orderTotalWrapper;
    }

    return $payload;
}

