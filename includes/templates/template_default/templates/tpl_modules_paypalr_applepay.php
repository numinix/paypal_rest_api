<?php
/**
 * tpl_modules_paypalr_applepay.php
 * Apple Pay button template for shopping cart page
 * 
 * Apple Pay uses PayPal SDK with native Apple Pay integration.
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_applepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_applepay.php');

    $applePayModule = new paypalr_applepay();
    
    // Get wallet configuration
    $walletConfig = $applePayModule->ajaxGetWalletConfig();
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    $clientId = $walletConfig['clientId'];
    $merchantId = $walletConfig['merchantId'] ?? '';
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $environment = $walletConfig['environment'] ?? 'sandbox';
    $initialTotal = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
    
    // Build SDK URL with components
    $sdkComponents = 'buttons,applepay';
    $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
    $sdkUrl .= '&components=' . urlencode($sdkComponents);
    $sdkUrl .= '&currency=' . urlencode($currency);
    $sdkUrl .= '&intent=' . urlencode($intent);
    
    if (!empty($merchantId)) {
        $sdkUrl .= '&merchant-id=' . urlencode($merchantId);
    }
?>

<script src="<?php echo $sdkUrl; ?>"></script>

<?php
    // Load the PayPal Apple Pay JavaScript
    $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    }
?>

<script>
"use strict";

window.paypalrApplePaySessionAppend = window.paypalrApplePaySessionAppend || "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";

// Initialize Apple Pay when PayPal SDK is loaded
if (window.paypal) {
    var applePayContainer = document.getElementById('paypalr-applepay-button');
    if (applePayContainer) {
        applePayContainer.style.minHeight = '50px';
    }
} else {
    console.error('PayPal SDK not loaded for Apple Pay');
}
</script>

<!-- Apple Pay Button Container -->
<div id="paypalr-applepay-button" class="paypalr-applepay-button"></div>
<div id="paypalr-applepay-error" style="display:none; color:red; margin-top:10px;"></div>

<style>
#paypalr-applepay-button {
    min-height: 50px;
    margin-top: 20px;
    margin-left: auto !important;
    width: 228px;
}

.paypalr-applepay-button {
    width: 100%;
    max-width: 320px;
}

@media (max-width:768px) {
    #paypalr-applepay-button {
        width: 100% !important;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
