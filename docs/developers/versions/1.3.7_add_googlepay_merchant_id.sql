-- Version 1.3.7: Reintroduce optional Google Pay Merchant ID configuration
-- Adds a validated configuration entry for MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID.
-- When present, the value is passed to the PayPal SDK as google-pay-merchant-id.

INSERT IGNORE INTO configuration
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
VALUES
    (
        'Google Pay Merchant ID (optional)',
        'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID',
        '',
        'Optional Google Merchant ID used for the PayPal SDK google-pay-merchant-id parameter. Must be 5-20 alphanumeric characters. Leave blank unless instructed by PayPal.',
        6,
        0,
        NULL,
        NULL,
        now()
    );
