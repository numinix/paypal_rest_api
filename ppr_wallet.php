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

$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true) ?: [];

$wallet = $requestData['wallet'] ?? '';

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

if (!method_exists($moduleInstance, 'ajaxCreateWalletOrder')) {
    echo json_encode(['success' => false, 'message' => 'Wallet module missing AJAX handler']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

$response = $moduleInstance->ajaxCreateWalletOrder();
if (!is_array($response)) {
    $response = ['success' => false, 'message' => 'Unexpected wallet response'];
}

echo json_encode($response);

require DIR_WS_INCLUDES . 'application_bottom.php';
