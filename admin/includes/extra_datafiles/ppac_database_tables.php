<?php
/**
 * Database table constants for the PayPal Advanced Checkout plugin (Admin).
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
     * Keep legacy recurring scripts pointed at the REST-managed subscription table
     * to prevent duplicate schemas and ensure data stays centralized.
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
