<?php
/**
 * AJAX endpoint for creating PayPal setup tokens for adding cards.
 */

$autoloaderPath = __DIR__ . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalAdvancedCheckout\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;

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
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/ppacAutoload.php';
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac.php';

    // Support either a direct billing_address object or an address_book_id reference
    if (!empty($requestData['address_book_id'])) {
        $addressBookId = (int)$requestData['address_book_id'];
        global $db;
        $addressRecord = $db->Execute(
            "SELECT ab.entry_street_address, ab.entry_suburb, ab.entry_city, " .
            "       ab.entry_postcode, ab.entry_state, ab.entry_country_id, ab.entry_zone_id, " .
            "       c.countries_iso_code_2, z.zone_code" .
            "   FROM " . TABLE_ADDRESS_BOOK . " ab" .
            "   LEFT JOIN " . TABLE_COUNTRIES . " c ON ab.entry_country_id = c.countries_id" .
            "   LEFT JOIN " . TABLE_ZONES . " z ON ab.entry_zone_id = z.zone_id" .
            "  WHERE ab.customers_id = " . (int)$_SESSION['customer_id'] .
            "    AND ab.address_book_id = " . (int)$addressBookId .
            "  LIMIT 1"
        );

        if (!is_object($addressRecord) || $addressRecord->EOF) {
            echo json_encode(['success' => false, 'message' => 'Address not found']);
            require DIR_WS_INCLUDES . 'application_bottom.php';
            exit;
        }

        $entry = $addressRecord->fields;
        $billingAddress = [
            'address_line_1' => trim((string)($entry['entry_street_address'] ?? '')),
            'admin_area_2'   => trim((string)($entry['entry_city'] ?? '')),
            'postal_code'    => trim((string)($entry['entry_postcode'] ?? '')),
            'country_code'   => strtoupper(trim((string)($entry['countries_iso_code_2'] ?? ''))),
        ];

        $stateCode = trim((string)($entry['zone_code'] ?? ''));
        if ($stateCode === '') {
            $stateCode = trim((string)($entry['entry_state'] ?? ''));
        }
        if ($stateCode !== '') {
            $billingAddress['admin_area_1'] = $stateCode;
        }

        $streetLine2 = trim((string)($entry['entry_suburb'] ?? ''));
        if ($streetLine2 !== '') {
            $billingAddress['address_line_2'] = $streetLine2;
        }

        $billingAddress = array_filter($billingAddress);
    } else {
        $billingAddress = $requestData['billing_address'] ?? [];
    }

    // Validate required address fields
    if (empty($billingAddress['address_line_1']) || 
        empty($billingAddress['admin_area_2']) || 
        empty($billingAddress['postal_code']) || 
        empty($billingAddress['country_code'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid billing address']);
        require DIR_WS_INCLUDES . 'application_bottom.php';
        exit;
    }

    $api = new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER);

    // Build the return/cancel URLs for 3DS verification
    $returnUrl = str_replace('&amp;', '&', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'add=1', 'SSL', true, true, true));
    $cancelUrl = $returnUrl;

    // Create setup token for card vaulting
    $paymentSource = [
        'card' => [
            'billing_address' => $billingAddress,
            'verification_method' => 'SCA_WHEN_REQUIRED',
            'experience_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
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
