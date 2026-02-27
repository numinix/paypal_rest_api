<?php
/**
 * tpl_modules_paypalac_product_applepay.php
 * Apple Pay button template for product page
 * 
 * Uses native ApplePaySession API to retrieve user email addresses,
 * then processes payment through the PayPal Advanced Checkout module.
 * Based on Braintree implementation pattern.
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalac_applepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac_applepay.php');

    $applePayModule = new paypalac_applepay();
    
    // Get wallet configuration - pass product ID so shipping requirement is based on
    // the viewed product's virtual status, not the cart contents
    $walletConfig = $applePayModule->ajaxGetWalletConfig((int)$_GET['products_id']);
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    $clientId = $walletConfig['clientId'];
    $merchantId = $walletConfig['merchantId'] ?? '';
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $environment = $walletConfig['environment'] ?? 'sandbox';
    
    // For product page, calculate based on product price
    $productId = (int)$_GET['products_id'];
    $initialTotal = number_format($currencies->value(zen_get_products_base_price($productId)), 2, '.', '');
    
    // Get store country code for Apple Pay
    $country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
    $country_result = $db->Execute($country_query);
    $storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';
    
    // Build PayPal SDK URL - still needed for order creation/capture
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

<?php
    // Load the native Apple Pay JavaScript integration for product/cart pages
    $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.applepay.wallet.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    } else {
        // Log error if the required JavaScript file is missing
        error_log('Apple Pay Error: jquery.paypalac.applepay.wallet.js not found at: ' . $scriptPath);
        return;
    }
?>

<script>
"use strict";

// Check if customer is logged in to determine which SDK approach to use
window.paypalacWalletIsLoggedIn = <?php echo (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) ? 'true' : 'false'; ?>;

// Configuration for native Apple Pay integration
window.paypalacApplePayConfig = {
    clientId: "<?php echo addslashes($clientId); ?>",
    merchantId: "<?php echo addslashes($merchantId); ?>",
    storeCountryCode: "<?php echo $storeCountryCode; ?>",
    currencyCode: "<?php echo $currency; ?>",
    initialTotal: "<?php echo $initialTotal; ?>",
    storeName: "<?php echo addslashes(STORE_NAME); ?>",
    environment: "<?php echo $environment; ?>",
    sessionAppend: "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>",
    context: 'product',
    productId: <?php echo $productId; ?>
};

// Initialize Apple Pay when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.paypalacApplePayRender === 'function') {
            window.paypalacApplePayRender();
        }
    });
} else {
    if (typeof window.paypalacApplePayRender === 'function') {
        window.paypalacApplePayRender();
    }
}
</script>

<!-- Apple Pay Button Container -->
<div id="paypalac-applepay-button" class="paypalac-applepay-button"></div>
<div id="paypalac-applepay-error" style="display:none; color:red; margin-top:10px;"></div>

<style>
#paypalac-applepay-button {
    min-height: 50px;
    margin-top: 20px;
    max-width: 320px;
}

#paypalac-applepay-button apple-pay-button {
    --apple-pay-button-width: 100%;
    --apple-pay-button-height: 50px;
    --apple-pay-button-border-radius: 3px;
    display: block;
    width: 100%;
    height: 50px;
}

@media (max-width:768px) {
    #paypalac-applepay-button {
        width: 100% !important;
        max-width: 100%;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
