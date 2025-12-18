<?php
// tpl_paypalr_shopping_cart.php
// Dynamically fetch store's ISO country code
$country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
$country_result = $db->Execute($country_query);
$storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';  // Fallback to 'US' if not found
$currencyCode         = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
$initialTotal         = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
$storeName = STORE_NAME;

// Load Google Pay template
if (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SHOPPING_CART') && MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalr_googlepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalr_googlepay.php';
    }
    include($template_path);
}

// Load Apple Pay template
if (defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_SHOPPING_CART') && MODULE_PAYMENT_PAYPALR_APPLEPAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalr_applepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalr_applepay.php';
    }
    include($template_path);
}

// Load Venmo template
if (defined('MODULE_PAYMENT_PAYPALR_VENMO_STATUS') && MODULE_PAYMENT_PAYPALR_VENMO_STATUS === 'True' && defined('MODULE_PAYMENT_PAYPALR_VENMO_SHOPPING_CART') && MODULE_PAYMENT_PAYPALR_VENMO_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalr_venmo.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalr_venmo.php';
    }
    include($template_path);
}
?>

<style>
/* PayPal wallet buttons updates */
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
