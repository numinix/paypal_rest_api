<?php
// tpl_paypalac_shopping_cart.php
// Shared wallet button CSS (equal Apple/Google widths, etc.). Checkout modules
// inject this via getWalletAssets(); cart/product templates do not, so load it
// here once before the wallet button includes.
if (!class_exists(\PayPalAdvancedCheckout\Common\PluginPaths::class, false)) {
    $ppacAutoload = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
    if (is_file($ppacAutoload)) {
        require_once $ppacAutoload;
    }
}
if (class_exists(\PayPalAdvancedCheckout\Common\PluginPaths::class, false)) {
    $paypalacSharedCss = \PayPalAdvancedCheckout\Common\PluginPaths::readSupportFile('paypalac.css');
    if ($paypalacSharedCss !== '') {
        echo '<style id="paypalac-wallet-shared-css">' . $paypalacSharedCss . '</style>' . "\n";
    }
}

// Dynamically fetch store's ISO country code
$country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
$country_result = $db->Execute($country_query);
$storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';  // Fallback to 'US' if not found
$currencyCode         = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
$initialTotal         = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
$storeName = STORE_NAME;

// Load Google Pay template
if (defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_SHOPPING_CART') && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_googlepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_googlepay.php';
    }
    include($template_path);
}

// Load Apple Pay template
if (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_SHOPPING_CART') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_applepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_applepay.php';
    }
    include($template_path);
}

// Load Venmo template
if (defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') && MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALAC_VENMO_SHOPPING_CART') && MODULE_PAYMENT_PAYPALAC_VENMO_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_venmo.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_venmo.php';
    }
    include($template_path);
}
?>

<style>
/* PayPal wallet buttons updates */
/* New PayPal Advanced Checkout wallet button containers.
   Width is intentionally NOT set here - it is controlled by the shared
   --paypalac-wallet-button-width rule in paypalac.css (see the
   #paypalac-applepay-button / #paypalac-googlepay-button !important block)
   so Apple Pay and Google Pay always render at the exact same width.
   This block previously hardcoded width:228px, which competed with
   paypalac.css and the Google Pay wallet JS's own inline width, causing
   Apple Pay and Google Pay to render at different widths. */
#paypalac-googlepay-button,
#paypalac-applepay-button {
    margin-top: 15px;
    margin-left: auto;
    width: 240px !important;
    min-width: 200px !important;
    max-width: 320px !important;
}
#paypalac-googlepay-button apple-pay-button,
#paypalac-applepay-button apple-pay-button {
    --apple-pay-button-width: 100%;
    --apple-pay-button-height: 50px;
    --apple-pay-button-border-radius: 4px;
    display: block;
    width: 100%;
    height: 50px;
}
/* Legacy Braintree wallet button containers */
div#google-pay-button-container {
    margin-top:20px !important;
    width:228px !important;
    margin-left:auto;
}
.gpay-card-info-container {
    top:0 !important;
}
div#google-pay-button-container > div {
    height: 100%;
}
div#google-pay-button-container > div > button {
    height: 50px;
    display:block;
}
div#google-pay-button-container > div > button > iframe {
    margin-top:5px;
    height: 45px;
}
div#venmo-button-container {
    margin-bottom: 0px;
    height: 50px;
    margin-top: 20px;
    margin-left:auto !important;
    width: 228px;
}
#venmo-button-container > div {
    display: block;
    height: 50px;
}
#apple-pay-button-container {
    width: 228px;
    height: 50px;
    margin-left: auto !important;
    margin-top: 20px !important;
}
#apple-pay-button-container .apple-pay-button {
    height: 50px;
    margin: 0;
    border-radius: 3px;
    width: 100% !important;
    max-width: 100%;
}
@media (max-width:768px) {
    #paypalac-googlepay-button,
    #paypalac-applepay-button {
        width: 100% !important;
    }
    div#google-pay-button-container {
        width:100% !important;
    }
    div#venmo-button-container {
        width:100% !important;
    }
    #apple-pay-button-container {
        width:100% !important;
    }
    .btn-continue-checkout {
        width:100% !important;
    }
}
</style>
