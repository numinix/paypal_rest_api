<?php
/**
 * Wallet helper endpoint for PayPal Advanced Checkout wallets (Google Pay, Apple Pay, Venmo).
 */

$autoloaderPath = __DIR__ . '/includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

header('Content-Type: application/json');

// -----
// Validate cart state before proceeding. A valid session and non-empty cart are required.
//
if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart']) || $_SESSION['cart']->count_contents() < 1) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty or session expired']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

// -----
// Initialize order and order_totals to populate $order->info with proper values.
// This is necessary because the wallet modules need order total information
// to create PayPal orders, and the fallback in the observer relies on $order->info.
//
global $order, $order_total_modules;

try {
    if (!isset($order) || !is_object($order)) {
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new order();
    }

    if (!isset($order_total_modules) || !is_object($order_total_modules)) {
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new order_total();
    }

    // Run order totals processing to ensure $order->info is populated
    $order_total_modules->collect_posts();
    $order_total_modules->pre_confirmation_check();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to initialize order totals']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true) ?: [];

$wallet = $requestData['wallet'] ?? '';
$configOnly = !empty($requestData['config_only']);

$moduleMap = [
    'google_pay' => 'paypalr_googlepay',
    'apple_pay' => 'paypalr_applepay',
    'venmo' => 'paypalr_venmo',
];

if (!array_key_exists($wallet, $moduleMap)) {
    echo json_encode(['success' => false, 'message' => 'Invalid wallet type']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

$moduleCode = $moduleMap[$wallet];

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/' . $moduleCode . '.php';

$moduleInstance = new $moduleCode();
if (empty($moduleInstance->enabled)) {
    echo json_encode(['success' => false, 'message' => 'Wallet module is disabled']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

// -----
// If config_only is requested, return just the SDK configuration needed to initialize
// the PayPal button without creating a PayPal order. This is used during initial page
// load to render the button. The actual order creation happens when user clicks the button.
//
if ($configOnly) {
    if (!method_exists($moduleInstance, 'ajaxGetWalletConfig')) {
        echo json_encode(['success' => false, 'message' => 'Wallet module missing config handler']);
        require DIR_WS_INCLUDES . 'application_bottom.php';
        return;
    }
    $response = $moduleInstance->ajaxGetWalletConfig();
} else {
    if (!method_exists($moduleInstance, 'ajaxCreateWalletOrder')) {
        echo json_encode(['success' => false, 'message' => 'Wallet module missing AJAX handler']);
        require DIR_WS_INCLUDES . 'application_bottom.php';
        return;
    }
    $response = $moduleInstance->ajaxCreateWalletOrder();
}

if (!is_array($response)) {
    $response = ['success' => false, 'message' => 'Unexpected wallet response'];
}

echo json_encode($response);

require DIR_WS_INCLUDES . 'application_bottom.php';
