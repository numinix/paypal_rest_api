<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'one_page_checkout';
$loaderPrefix = 'oprc';
require_once('includes/application_top.php');
// Ensure downstream modules recognize that this request is operating within
// the One Page Checkout context so they can render page-specific elements
// (e.g., the coupon "remove" link) the same way they do during a full page
// load.
if (!isset($_GET['main_page']) || $_GET['main_page'] === '') {
    $_GET['main_page'] = FILENAME_ONE_PAGE_CHECKOUT;
}

if (!isset($_POST['main_page']) || $_POST['main_page'] === '') {
    $_POST['main_page'] = FILENAME_ONE_PAGE_CHECKOUT;
}

if (!isset($_REQUEST['main_page']) || $_REQUEST['main_page'] === '') {
    $_REQUEST['main_page'] = FILENAME_ONE_PAGE_CHECKOUT;
}
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

$shippingSelection = oprc_determine_shipping_selection($_POST ?? []);

if ($shippingSelection === null) {
    $response['messagesHtml'] = '';
    echo oprc_encode_json_response($response);
    require_once('includes/application_bottom.php');
    return;
}

require_once(DIR_WS_CLASSES . 'order.php');
$order = new order();

require_once(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping();

$originalGetAction = $_GET['oprcaction'] ?? null;
$originalPostAction = $_POST['oprcaction'] ?? null;
$originalRequestFlag = $_REQUEST['request'] ?? null;

$_POST['shipping'] = $shippingSelection;
$_POST['oprcaction'] = 'process';
$_GET['oprcaction'] = 'process';
$_REQUEST['request'] = 'ajax';
$ajax_request = true;

require(DIR_WS_MODULES . 'oprc_update_shipping.php');

$existingUpdateValues = [];
if (isset($GLOBALS['oprc_last_shipping_update']) && is_array($GLOBALS['oprc_last_shipping_update'])) {
    $shippingUpdate = $GLOBALS['oprc_last_shipping_update'];
    if (!empty($shippingUpdate['delivery_updates']) && is_array($shippingUpdate['delivery_updates'])) {
        $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['delivery_updates']);
    }
    if (!empty($shippingUpdate['module_dates']) && is_array($shippingUpdate['module_dates'])) {
        $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['module_dates']);
    }
}

$quotesList = isset($quotes) && is_array($quotes) ? $quotes : [];
$deliveryData = oprc_prepare_delivery_updates_for_quotes($quotesList, $shipping_modules, $existingUpdateValues);
$shippingModuleDates = $deliveryData['module_dates'];
$methodDeliveryUpdates = $deliveryData['method_dates'];
$renderedDeliveryUpdates = $deliveryData['rendered_updates'];

// What the UI needs:
$deliveryUpdates = $renderedDeliveryUpdates;

// What later AJAX refreshes should consult:
$GLOBALS['oprc_last_shipping_update'] = [
    'order' => isset($order) ? $order : null,
    'shipping_modules' => isset($shipping_modules) ? $shipping_modules : null,
    'globals' => [
        'quotes' => isset($quotes) ? $quotes : null,
        'free_shipping' => isset($free_shipping) ? $free_shipping : null,
        'shipping_weight' => isset($shipping_weight) ? $shipping_weight : null,
        'total_weight' => isset($total_weight) ? $total_weight : null,
        'total_count' => isset($total_count) ? $total_count : null,
        'recalculate_shipping_cost' => isset($recalculate_shipping_cost) ? $recalculate_shipping_cost : null,
        'pass' => isset($pass) ? $pass : null,
    ],
    'module_dates' => $shippingModuleDates,
    // ⬇️ keep raw method-level values here
    'delivery_updates' => $methodDeliveryUpdates,
    // and keep the rendered ones too, for last-ditch fallback
    'rendered_delivery_updates' => $renderedDeliveryUpdates,
];

if ($originalGetAction !== null) {
    $_GET['oprcaction'] = $originalGetAction;
} else {
    unset($_GET['oprcaction']);
}

if ($originalPostAction !== null) {
    $_POST['oprcaction'] = $originalPostAction;
} else {
    unset($_POST['oprcaction']);
}

if ($originalRequestFlag !== null) {
    $_REQUEST['request'] = $originalRequestFlag;
} else {
    unset($_REQUEST['request']);
}

$payload = oprc_build_shipping_update_payload($GLOBALS['oprc_last_shipping_update'] ?? null);

$response = array_merge(
    [
        'status' => $payload['status'],
        'messagesHtml' => $payload['messagesHtml'],
    ],
    array_diff_key($payload, ['status' => true, 'messagesHtml' => true])
);

echo oprc_encode_json_response($response);

require_once('includes/application_bottom.php');

function oprc_build_shipping_update_payload($shippingUpdate = null)
{
    global $template, $current_page_base, $messageStack;
    global $order, $order_total_modules, $payment_modules, $shipping_modules, $credit_covers;
    global $quotes;

    oprc_ensure_language_is_loaded('lang.one_page_checkout');

    if ($shippingUpdate === null && isset($GLOBALS['oprc_last_shipping_update'])) {
        $shippingUpdate = $GLOBALS['oprc_last_shipping_update'];
    }

    require_once(DIR_WS_CLASSES . 'order.php');
    $order = new order();

    require_once(DIR_WS_CLASSES . 'payment.php');
    $payment_modules = new payment();

    require_once(DIR_WS_CLASSES . 'shipping.php');
    if (is_array($shippingUpdate) && isset($shippingUpdate['shipping_modules']) && is_object($shippingUpdate['shipping_modules'])) {
        $shipping_modules = $shippingUpdate['shipping_modules'];
    } else {
        $shipping_modules = new shipping();
    }

    if (is_array($shippingUpdate) && isset($shippingUpdate['globals']) && is_array($shippingUpdate['globals'])) {
        foreach ($shippingUpdate['globals'] as $globalKey => $globalValue) {
            $GLOBALS[$globalKey] = $globalValue;
        }
    }

    oprc_restore_module_dates($shippingUpdate);

    require_once(DIR_WS_CLASSES . 'order_total.php');
    $order_total_modules = new order_total();
    $order_total_modules->collect_posts();
    $order_total_modules->pre_confirmation_check();
    $order_total_modules->process();

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

    $step3Html = oprc_capture_template_output('tpl_modules_oprc_step_3.php');
    $orderTotalHtml = oprc_capture_template_output('tpl_modules_oprc_ordertotal.php');

    $current_page_base = $originalPageBase;

    $shippingContainerHtml = oprc_extract_inner_html($step3Html, 'shippingMethodContainer');

    $shouldRefreshPayment = oprc_should_refresh_payment_container();
    $paymentHtml = null;
    $paymentOuterHtml = null;

    if ($shouldRefreshPayment) {
        $paymentHtml = oprc_extract_inner_html($step3Html, 'paymentMethodContainer');
        $paymentOuterHtml = oprc_extract_outer_html($step3Html, 'paymentMethodContainer');
    }
    $discountsHtml = oprc_extract_inner_html($step3Html, 'discountsContainer');
    $orderTotalWrapper = oprc_extract_inner_html($orderTotalHtml, 'shopBagWrapper');

    $shippingMethodsHtml = oprc_capture_shipping_methods_html();

    $existingUpdateValues = [];
    if (is_array($shippingUpdate)) {
        if (isset($shippingUpdate['delivery_updates']) && is_array($shippingUpdate['delivery_updates'])) {
            $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['delivery_updates']);
        }

        if (isset($shippingUpdate['module_dates']) && is_array($shippingUpdate['module_dates'])) {
            $existingUpdateValues = array_merge($existingUpdateValues, $shippingUpdate['module_dates']);
        }
    }

    $quotesList = isset($quotes) && is_array($quotes) ? $quotes : [];
    $deliveryData = oprc_prepare_delivery_updates_for_quotes($quotesList, $shipping_modules, $existingUpdateValues);
    $deliveryUpdates = $deliveryData['rendered_updates'];

    if (empty($deliveryUpdates) && is_array($shippingUpdate) && isset($shippingUpdate['rendered_delivery_updates']) && is_array($shippingUpdate['rendered_delivery_updates'])) {
        $deliveryUpdates = $shippingUpdate['rendered_delivery_updates'];
    }

    $messagesHtml = (isset($messageStack) && is_object($messageStack)) ? $messageStack->output('checkout_shipping') : '';
    $status = (trim(strip_tags($messagesHtml)) !== '') ? 'warning' : 'success';

    $payload = [
        'status' => $status,
        'messagesHtml' => $messagesHtml,
        'shippingMethodContainer' => $shippingContainerHtml,
        'shippingMethodsHtml' => $shippingMethodsHtml,
        'discountsContainer' => $discountsHtml,
        'shopBagWrapper' => $orderTotalWrapper,
        'deliveryUpdates' => $deliveryUpdates,
        // Optional debugging fields
        'moduleDeliveryDates' => isset($deliveryData['module_dates']) ? $deliveryData['module_dates'] : [],
        'methodDeliveryDates' => isset($deliveryData['method_dates']) ? $deliveryData['method_dates'] : [],
    ];

    if ($shouldRefreshPayment) {
        $payload['paymentMethodContainer'] = $paymentHtml;
        $payload['paymentMethodContainerOuter'] = $paymentOuterHtml;
    }

    return $payload;
}
