<?php
// tpl_paypalac_product_info.php
// Dynamically fetch store's ISO country code
$country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
$country_result = $db->Execute($country_query);
$storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';  // Fallback to 'US' if not found

$currencyCode = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
$storeName = STORE_NAME;
$initialTotal = number_format($currencies->value(zen_get_products_base_price((int)$_GET['products_id'])), 2, '.', '');

// Load Google Pay template if enabled for product page
if (
    defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') &&
    MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True' &&
    defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_PRODUCT_PAGE') &&
    MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_PRODUCT_PAGE === 'True'
) {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_product_googlepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_product_googlepay.php';
    }
    include($template_path);
}

// Load Apple Pay template if enabled for product page
if (
    defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') &&
    MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True' &&
    defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_PRODUCT_PAGE') &&
    MODULE_PAYMENT_PAYPALAC_APPLEPAY_PRODUCT_PAGE === 'True'
) {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_product_applepay.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_product_applepay.php';
    }
    include($template_path);
}

// Load Venmo template if enabled for product page
if (
    defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') &&
    MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True' &&
    defined('MODULE_PAYMENT_PAYPALAC_VENMO_PRODUCT_PAGE') &&
    MODULE_PAYMENT_PAYPALAC_VENMO_PRODUCT_PAGE === 'True'
) {
    $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_modules_paypalac_product_venmo.php';
    if (!file_exists($template_path)) {
        $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_modules_paypalac_product_venmo.php';
    }
    include($template_path);
}
?>
