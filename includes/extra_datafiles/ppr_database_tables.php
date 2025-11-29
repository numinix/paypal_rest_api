<?php
/**
 * Database table constants for the PayPal Rest API plugin.
 * These are loaded site-wide automatically by Zen Cart.
 */
if (!defined('TABLE_PAYPAL')) {
    define('TABLE_PAYPAL', DB_PREFIX . 'paypal');
}
if (!defined('TABLE_PAYPAL_VAULT')) {
    define('TABLE_PAYPAL_VAULT', DB_PREFIX . 'paypal_vault');
}
if (!defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
    define('TABLE_PAYPAL_SUBSCRIPTIONS', DB_PREFIX . 'paypal_subscriptions');
}
if (!defined('TABLE_PAYPAL_WEBHOOKS')) {
    define('TABLE_PAYPAL_WEBHOOKS', DB_PREFIX . 'paypal_webhooks');
}
