<?php
/**
 * AJAX endpoint for creating PayPal setup tokens for adding cards.
 */

$autoloaderPath = __DIR__ . '/includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

use PayPalRestful\Api\PayPalRestfulApi;

header('Content-Type: application/json');

// Validate that user is logged in
if (empty($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    exit;
}

// Validate that PayPal REST module is enabled
if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS') || MODULE_PAYMENT_PAYPALAC_STATUS !== 'True') {
    echo json_encode(['success' => false, 'message' => 'Payment module not available']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    exit;
}

$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true) ?: [];

// Validate JSON input size and structure
if (strlen($requestBody) > 10240) { // 10KB max
    echo json_encode(['success' => false, 'message' => 'Request too large']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    exit;
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    exit;
}

$action = $requestData['action'] ?? '';

if ($action === 'create_setup_token') {
    // Get billing address from request
    $billingAddress = $requestData['billing_address'] ?? [];
    
    // Validate required address fields
    if (empty($billingAddress['address_line_1']) || 
        empty($billingAddress['admin_area_2']) || 
        empty($billingAddress['postal_code']) || 
        empty($billingAddress['country_code'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid billing address']);
        require DIR_WS_INCLUDES . 'application_bottom.php';
        exit;
    }

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/ppacAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac.php';

    $api = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALAC_SERVER);
    
    // Create setup token for card vaulting
    $paymentSource = [
        'card' => [
            'billing_address' => $billingAddress,
            'verification_method' => 'SCA_WHEN_REQUIRED',
        ],
    ];
    
    $setupTokenResponse = $api->createSetupToken($paymentSource);
    
    if ($setupTokenResponse === false) {
        $errorInfo = $api->getErrorInfo();
        error_log('PayPal setup token creation error: ' . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'message' => 'Failed to create setup token']);
    } else {
        // Return the setup token ID
        $setupTokenId = $setupTokenResponse['id'] ?? '';
        if ($setupTokenId !== '') {
            echo json_encode([
                'success' => true,
                'setup_token_id' => $setupTokenId,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid setup token response']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

require DIR_WS_INCLUDES . 'application_bottom.php';
