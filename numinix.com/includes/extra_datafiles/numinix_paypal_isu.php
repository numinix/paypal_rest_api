<?php
/**
 * Storefront filename constants for the Numinix PayPal onboarding tools.
 */
if (!defined('FILENAME_PAYPAL_SIGNUP')) {
    define('FILENAME_PAYPAL_SIGNUP', 'paypal_signup');
}

if (!defined('FILENAME_PAYPAL_API')) {
    define('FILENAME_PAYPAL_API', 'paypal_api');
}

/**
 * Database table for cross-session merchant_id persistence during onboarding.
 * This table allows the PayPal redirect callback to store the merchant_id
 * so it can be retrieved by subsequent status polling requests.
 */
if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
    define('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING', DB_PREFIX . 'numinix_paypal_onboarding_tracking');
}
