<?php
/**
 * tpl_modules_paypalr_product_googlepay.php
 * Google Pay button template for product page
 * 
 * Google Pay uses PayPal SDK with native Google Pay integration.
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_googlepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_googlepay.php');

    $googlePayModule = new paypalr_googlepay();
    
    // Get wallet configuration
    $walletConfig = $googlePayModule->ajaxGetWalletConfig();
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    $clientId = $walletConfig['clientId'];
    $merchantId = $walletConfig['googleMerchantId'] ?? '';
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $environment = $walletConfig['environment'] ?? 'sandbox';
    
    // For product page, calculate based on product price
    $productId = (int)$_GET['products_id'];
    $initialTotal = number_format($currencies->value(zen_get_products_base_price($productId)), 2, '.', '');
    
    // Build SDK URL with components
    // Note: As of 2025, PayPal SDK no longer accepts google-pay-merchant-id or intent parameters
    // Intent is specified when creating the order, not when loading the SDK
    //
    // All wallet components (buttons,googlepay,applepay) are loaded together to ensure
    // compatibility when multiple payment methods are enabled. This prevents SDK reload
    // issues when users have both Google Pay and Apple Pay available.
    $sdkComponents = 'buttons,googlepay,applepay';
    $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
    $sdkUrl .= '&components=' . urlencode($sdkComponents);
    $sdkUrl .= '&currency=' . urlencode($currency);
    
    // Add buyer-country for sandbox mode (required for testing)
    if ($environment === 'sandbox') {
        $sdkUrl .= '&buyer-country=US';
    }
?>

<script src="<?php echo $sdkUrl; ?>"></script>
<script src="https://pay.google.com/gp/p/js/pay.js"></script>

<?php
    // Load the PayPal Google Pay JavaScript
    $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    }
?>

<script>
"use strict";

window.paypalrGooglePaySessionAppend = window.paypalrGooglePaySessionAppend || "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";

// Initialize Google Pay when PayPal SDK is loaded
if (window.paypal) {
    var googlePayContainer = document.getElementById('paypalr-googlepay-button');
    if (googlePayContainer) {
        googlePayContainer.style.minHeight = '50px';
        // Mark this as a product page context
        googlePayContainer.setAttribute('data-context', 'product');
    }
} else {
    console.error('PayPal SDK not loaded for Google Pay');
}
</script>

<!-- Google Pay Button Container -->
<div id="paypalr-googlepay-button" class="paypalr-googlepay-button"></div>
<div id="paypalr-googlepay-error" style="display:none; color:red; margin-top:10px;"></div>

<style>
#paypalr-googlepay-button {
    min-height: 50px;
    margin-top: 20px;
}

@media (max-width:768px) {
    #paypalr-googlepay-button {
        width: 100% !important;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
