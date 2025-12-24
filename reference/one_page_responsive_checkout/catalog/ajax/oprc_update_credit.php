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

if (!isset($_POST['oprcaction']) || $_POST['oprcaction'] === '') {
    $_POST['oprcaction'] = 'updateCredit';
}

$_GET['oprcaction'] = $_POST['oprcaction'];
$_POST['request'] = 'ajax';
$_REQUEST['request'] = 'ajax';

if (!isset($messageStack) || !is_object($messageStack)) {
    if (!class_exists('messageStack')) {
        require_once(DIR_WS_CLASSES . 'message_stack.php');
    }
    $messageStack = new messageStack();
}

try {
    $payload = oprc_build_checkout_refresh_payload([
        'messageAreas' => ['redemptions', 'checkout_payment', 'header']
    ]);

    $response = array_merge(
        [
            'status' => $payload['status'],
            'messagesHtml' => $payload['messagesHtml'],
        ],
        array_diff_key($payload, ['status' => true, 'messagesHtml' => true])
    );
} catch (Throwable $error) {
    $response['messagesHtml'] = $error->getMessage();
}

echo oprc_encode_json_response($response);

require_once('includes/application_bottom.php');
