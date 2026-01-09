<?php
// Add configuration for Merchant Account ID override
$configuration_key = 'MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID';
$configuration_title = 'Merchant Account ID (Optional)';
$configuration_description = 'Specify merchant account IDs for currencies. For multiple currencies, use format: USD:merchant_usd,CAD:merchant_cad. For single merchant account (all currencies), enter just the merchant account ID. Leave blank to auto-select based on currency.';
$configuration_default = '';

$check_query = $db->Execute("SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . zen_db_input($configuration_key) . "' LIMIT 1");
if ($check_query->RecordCount() == 0) {
    // Insert after CURRENCY field (sort_order should be right after it)
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('" . zen_db_input($configuration_title) . "', '" . zen_db_input($configuration_key) . "', '" . zen_db_input($configuration_default) . "', '" . zen_db_input($configuration_description) . "', 6, 0, NULL, NULL, NOW())");
}
