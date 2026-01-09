<?php
// Update MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID description to clarify currency requirements
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_description = 'Specify merchant account IDs for currencies. For multiple currencies, use format: USD:merchant_usd,CAD:merchant_cad. For single merchant account (all currencies), enter just the merchant account ID. Leave blank to auto-select based on currency. IMPORTANT: Each merchant account settles in a specific currency. Customers will be charged in the merchant account\'s settlement currency, not necessarily the currency they selected.'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID' LIMIT 1");

// Update MODULE_PAYMENT_BRAINTREE_CURRENCY description for clarity
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_description = 'Your default Merchant Account settlement currency. This setting is informational only - actual settlement currency is determined by the merchant account used for each transaction.'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_CURRENCY' LIMIT 1");
