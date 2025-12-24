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

$productId = $_POST['product_id'] ?? '';
if ($productId === '') {
    $response['messagesHtml'] = 'Missing product identifier.';
    echo oprc_encode_json_response($response);
    require_once('includes/application_bottom.php');
    return;
}

if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
    $response['redirect_url'] = zen_href_link(FILENAME_SHOPPING_CART);
    echo oprc_encode_json_response($response);
    require_once('includes/application_bottom.php');
    return;
}

$_SESSION['cart']->remove($productId);

if ($_SESSION['cart']->count_contents() <= 0) {
    $response = [
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_SHOPPING_CART),
    ];

    echo oprc_encode_json_response($response);
    require_once('includes/application_bottom.php');
    return;
}

$_POST['request'] = 'ajax';
$_REQUEST['request'] = 'ajax';

try {
    $payload = oprc_build_checkout_refresh_payload([
        'messageAreas' => ['checkout_payment', 'checkout_shipping', 'checkout_address', 'redemptions']
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
