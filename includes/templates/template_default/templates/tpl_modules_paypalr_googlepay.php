<?php
/**
 * tpl_modules_paypalr_googlepay.php
 * Google Pay button template for shopping cart page
 * 
 * Uses native google.payments.api to retrieve user email addresses,
 * then processes payment through PayPal REST API.
 * Based on Braintree implementation pattern.
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
    $googleMerchantId = $walletConfig['googleMerchantId'] ?? '';
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $googlePayEnvironment = $walletConfig['environment'] === 'sandbox' ? 'TEST' : 'PRODUCTION';
    $initialTotal = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
    
    // Get store country code for Google Pay
    $country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
    $country_result = $db->Execute($country_query);
    $storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';
    
    // Build PayPal SDK URL - still needed for order creation/capture
    $sdkComponents = 'buttons,googlepay,applepay';
    $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
    $sdkUrl .= '&components=' . urlencode($sdkComponents);
    $sdkUrl .= '&currency=' . urlencode($currency);
    
    // Add buyer-country for sandbox mode (required for testing)
    if ($walletConfig['environment'] === 'sandbox') {
        $sdkUrl .= '&buyer-country=US';
    }
?>

<script src="<?php echo $sdkUrl; ?>"></script>
<script src="https://pay.google.com/gp/p/js/pay.js"></script>

<?php
    // Load the native Google Pay JavaScript integration
    $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.native.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    } else {
        // Fallback to original implementation if native version doesn't exist yet
        $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js';
        if (file_exists($scriptPath)) {
            echo '<script>' . file_get_contents($scriptPath) . '</script>';
        }
    }
?>

<script>
"use strict";

// Configuration for native Google Pay integration
window.paypalrGooglePayConfig = {
    clientId: "<?php echo addslashes($clientId); ?>",
    googleMerchantId: "<?php echo addslashes($googleMerchantId); ?>",
    storeCountryCode: "<?php echo $storeCountryCode; ?>",
    currencyCode: "<?php echo $currency; ?>",
    initialTotal: "<?php echo $initialTotal; ?>",
    storeName: "<?php echo addslashes(STORE_NAME); ?>",
    environment: "<?php echo $googlePayEnvironment; ?>",
    sessionAppend: "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>",
    context: 'cart'
};

// Initialize Google Pay when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.initPayPalRGooglePay === 'function') {
            window.initPayPalRGooglePay();
        }
    });
} else {
    if (typeof window.initPayPalRGooglePay === 'function') {
        window.initPayPalRGooglePay();
    }
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
