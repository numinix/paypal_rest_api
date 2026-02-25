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
if (!defined('TABLE_PAYPAL_RECURRING')) {
    /**
     * Legacy recurring components expect this constant; map it to the REST-managed subscriptions table
     * so older scripts operate on the unified schema without creating duplicate tables.
     */
    define('TABLE_PAYPAL_RECURRING', DB_PREFIX . 'paypal_subscriptions');
}
if (!defined('TABLE_PAYPAL_WEBHOOKS')) {
    define('TABLE_PAYPAL_WEBHOOKS', DB_PREFIX . 'paypal_webhooks');
}
if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
    define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
}
if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
    define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
}
