<?php
// tpl_braintree_shopping_cart.php
// Dynamically fetch store's ISO country code
$country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
$country_result = $db->Execute($country_query);
$storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';  // Fallback to 'US' if not found
$currencyCode         = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
$initialTotal         = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
$storeName = STORE_NAME;

// Load Google Pay template
if (defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS === 'True' && defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SHOPPING_CART') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_braintree_googlepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_braintree_googlepay.php';
    }
    include($template_path);
}

// Load Apple Pay template
if (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS === 'True' && defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SHOPPING_CART') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_braintree_applepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_braintree_applepay.php';
    }
    include($template_path);
}
/*
//for future use when Braintree support shipping options via PayPal
// Load PayPal template
if (defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS') && MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS === 'True' && defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_SHOPPING_CART') && MODULE_PAYMENT_BRAINTREE_PAYPAL_SHOPPING_CART === 'True') {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_braintree_paypal.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_braintree_paypal.php';
    }
    include($template_path);
}
*/
?>

<style>
/* buttons updates */
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
div#PPECbutton {
    margin-bottom: 0px;
    height: 50px;
    margin-top: 20px;
    margin-left:auto !important;
    width: 228px;
}
#PPECbutton > a {
    display: block;
    height: 50px;
    background-position:center;
    background-repeat:no-repeat;
    background-color:#FFC439;
    border-radius:4px;
}
#PPECbutton > a:hover {
    opacity: 0.7;
}
#PPECbutton > a > img {
    height: 50px;
    display: block;
    margin: 0 auto;
    clip-path: inset(7px 7px 7px 7px);
    max-width: 100%;
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
    div#PPECbutton {
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