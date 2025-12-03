<?php
// Update the description for MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID to reflect new multi-currency format
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_description = 'Specify merchant account IDs for currencies. For multiple currencies, use format: USD:merchant_usd,CAD:merchant_cad. For single merchant account (all currencies), enter just the merchant account ID. Leave blank to auto-select based on currency.'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID' LIMIT 1");
