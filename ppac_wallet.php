<?php
/**
 * Wallet helper endpoint for PayPal Advanced Checkout wallets (Google Pay, Apple Pay, Venmo, Pay Later).
 */

$autoloaderPath = __DIR__ . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalAdvancedCheckout\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

header('Content-Type: application/json');

// Parse the request body early so we can check config_only before cart validation.
$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true) ?: [];

$wallet = $requestData['wallet'] ?? '';
$configOnly = !empty($requestData['config_only']);
$payloadData = $requestData['payload'] ?? null;

// -----
// Product page "Buy Now": when products_id is provided the wallet buttons act
// as a Buy Now button.  If the shipping quote endpoint (ajax/paypalac_wallet.php)
// already set up the cart during onshippingcontactselected, the cart will contain
// just this product.  If not (e.g. virtual products that skip shipping), we set
// it up here before order creation.
//
if (!$configOnly && !empty($requestData['products_id']) && (int)$requestData['products_id'] > 0) {
    $buyNowProductId = (int)$requestData['products_id'];
    $buyNowQty = !empty($requestData['cart_quantity']) && (int)$requestData['cart_quantity'] > 0
        ? (int)$requestData['cart_quantity']
        : 1;
    // Parse attributes from the request
    $buyNowAttributes = [];
    if (!empty($requestData['attributes']) && is_array($requestData['attributes'])) {
        foreach ($requestData['attributes'] as $key => $value) {
            if (preg_match('/^id\[(\d+)\]/', $key, $m)) {
                $buyNowAttributes[(int)$m[1]] = $value;
            }
        }
    }

    // Save original cart once per wallet session
    if (!isset($_SESSION['paypalac_wallet_saved_cart'])) {
        $_SESSION['paypalac_wallet_saved_cart'] = !empty($_SESSION['cart']->contents)
            ? serialize($_SESSION['cart']->contents)
            : null;
    }

    // Reset and add just the Buy Now product
    $_SESSION['cart']->reset(false);
    $_SESSION['cart']->add_cart(
        $buyNowProductId,
        $buyNowQty,
        !empty($buyNowAttributes) ? $buyNowAttributes : '',
        false
    );
}

// -----
// Validate cart state before proceeding. A non-empty cart is required for order
// creation and payload caching, but config_only requests (used to render the
// button on product pages) only need SDK configuration and work without a cart.
//
$hasValidCart = isset($_SESSION['cart']) && is_object($_SESSION['cart']) && $_SESSION['cart']->count_contents() > 0;

if (!$hasValidCart && !$configOnly) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty or session expired']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

// -----
// Initialize order and order_totals to populate $order->info with proper values.
// This is necessary because the wallet modules need order total information
// to create PayPal orders, and the fallback in the observer relies on $order->info.
//
// Suppress errors that may occur when called from the cart page where some
// order total modules expect checkout-specific session variables to be present.
// The observer's getLastOrderValues() fallback will still retrieve basic totals
// from $order->info even if the full order_total processing encounters issues.
//
// Skip order initialization for config_only requests since they don't need it.
//
global $order, $order_total_modules;

// Helper function to sanitize error messages for logging
// Prevents information disclosure by redacting sensitive values
if (!function_exists('ppac_wallet_sanitize_error_message')) {
    function ppac_wallet_sanitize_error_message($message) {
        // Replace sensitive values while preserving message structure
        // Pattern: password=value becomes password=[REDACTED]
        return preg_replace('/(\b(?:password|secret|key|token)\b\s*[=:]\s*)[^\s]+/i', '$1[REDACTED]', $message);
    }
}

if (!$configOnly && $hasValidCart) {
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Only suppress non-critical errors - let fatal errors through
    // E_ERROR and E_CORE_ERROR will still halt execution as expected
    $suppressibleErrors = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_STRICT;
    
    if (!($errno & $suppressibleErrors)) {
        // This is a critical error - don't suppress it
        restore_error_handler();
        return false; // Let PHP handle it normally
    }
    
    // Log but don't fail - let the fallback mechanism handle it
    $sanitizedFile = basename($errfile);
    $sanitizedError = ppac_wallet_sanitize_error_message($errstr);
    
    error_log("PayPal Wallet: Order totals initialization notice: $sanitizedError in $sanitizedFile:$errline");
    return true; // Suppress the error
});

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
} catch (\Exception $e) {
    // Log the error but continue - the observer fallback will use $order->info
    $sanitizedMessage = ppac_wallet_sanitize_error_message($e->getMessage());
    error_log('PayPal Wallet: Order totals initialization exception: ' . $sanitizedMessage);
    // Do not exit - allow the request to continue with the fallback mechanism
} finally {
    restore_error_handler();
}
} // end if (!$configOnly && $hasValidCart)

$moduleMap = [
    'google_pay' => 'paypalac_googlepay',
    'apple_pay' => 'paypalac_applepay',
    'venmo' => 'paypalac_venmo',
    'paylater' => 'paypalac_paylater',
];

if (!array_key_exists($wallet, $moduleMap)) {
    echo json_encode(['success' => false, 'message' => 'Invalid wallet type']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

$moduleCode = $moduleMap[$wallet];

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/' . $moduleCode . '.php';

$moduleInstance = new $moduleCode();
if (empty($moduleInstance->enabled)) {
    echo json_encode(['success' => false, 'message' => 'Wallet module is disabled']);
    require DIR_WS_INCLUDES . 'application_bottom.php';
    return;
}

// Save posted wallet payloads (e.g., Venmo onApprove) into the session before proceeding to confirmation
if ($payloadData !== null) {
    $isPayloadArray = is_array($payloadData);
    $hasOrderId = $isPayloadArray && isset($payloadData['orderID']) && is_string($payloadData['orderID']) && trim($payloadData['orderID']) !== '';

    if (!$isPayloadArray || !$hasOrderId) {
        echo json_encode(['success' => false, 'message' => 'Invalid wallet payload']);
        require DIR_WS_INCLUDES . 'application_bottom.php';
        return;
    }

    if (!isset($_SESSION['PayPalAdvancedCheckout']['WalletPayload'])) {
        $_SESSION['PayPalAdvancedCheckout']['WalletPayload'] = [];
    }

    $_SESSION['PayPalAdvancedCheckout']['WalletPayload'][$wallet] = $payloadData;

    echo json_encode(['success' => true, 'cached' => true]);
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
    // Pass products_id from request so product-page wallets can determine
    // whether the viewed product is virtual (not based on the cart).
    $productsId = isset($requestData['products_id']) ? (int)$requestData['products_id'] : 0;
    $response = $moduleInstance->ajaxGetWalletConfig($productsId);
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
