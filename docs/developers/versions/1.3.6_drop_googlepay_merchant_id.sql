-- Version 1.3.6: Remove unused Google Pay Merchant ID configuration entry
-- The PayPal REST API does not require a Google Merchant ID for Google Pay requests.
-- This script deletes the obsolete MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID row
-- from the configuration table so upgrades stop exposing the unused setting.

DELETE FROM configuration
 WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID'
 LIMIT 1;
