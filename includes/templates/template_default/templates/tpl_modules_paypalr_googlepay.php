<?php
/**
 * tpl_modules_paypalr_googlepay.php
 * Google Pay button template for shopping cart page
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
    $initialTotal = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
    
    // Build SDK URL with components
    $sdkComponents = 'buttons,googlepay';
    $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
    $sdkUrl .= '&components=' . urlencode($sdkComponents);
    $sdkUrl .= '&currency=' . urlencode($currency);
    $sdkUrl .= '&intent=' . urlencode($intent);
    
    if (!empty($merchantId)) {
        $sdkUrl .= '&merchant-id=' . urlencode($merchantId);
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
    margin-left: auto !important;
    width: 228px;
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
