<?php
/**
 * Language definitions for PayPal Advanced Checkout Admin Rebill
 */

define('HEADING_TITLE', 'PayPal Vault Rebill');
define('TEXT_VAULT_DISABLED_WARNING', 'PayPal Vault is not enabled. Enable PayPal Vault in the PayPal Advanced Checkout payment settings before using this page.');
define('TEXT_FIND_CUSTOMER', 'Find customer');
define('TEXT_SEARCH_LABEL', 'Search');
define('TEXT_SEARCH_PLACEHOLDER', 'Email, name, or customer ID');
define('TEXT_NO_CUSTOMERS_FOUND', 'No customers matched that search.');
define('TEXT_CUSTOMER', 'Customer');
define('TEXT_NO_VAULT_CARDS', 'This customer has no saved cards available for rebill.');
define('TEXT_AMOUNT', 'Amount');
define('TEXT_CURRENCY', 'Currency');
define('TEXT_COMMENTS', 'Admin comments (order history)');
define('TEXT_CREATE_ORDER', 'Create a new Zen Cart order for this charge');
define('TEXT_EXISTING_ORDER', 'Or attach to existing order ID (optional)');
define('TEXT_EXISTING_ORDER_PLACEHOLDER', 'e.g. 1005527');
define('TEXT_CONFIRM_REBILL', 'Charge this vaulted card now?');
define('TEXT_REBILL_PRODUCT_NAME', 'Admin rebill');
define('TEXT_LAST_ORDER', 'Last rebill order');

define('TABLE_HEADING_ID', 'ID');
define('TABLE_HEADING_NAME', 'Name');
define('TABLE_HEADING_EMAIL', 'Email');
define('TABLE_HEADING_CARD', 'Card');
define('TABLE_HEADING_EXPIRY', 'Expiry');
define('TABLE_HEADING_AVAILABILITY', 'Available to');
define('TABLE_HEADING_VAULT', 'Vault ID');
define('TEXT_AVAILABLE_CHECKOUT', 'Customer checkout');
define('TEXT_ADMIN_ONLY', 'Admin rebill only');

define('BUTTON_SEARCH', 'Search');
define('BUTTON_RESET', 'Reset');
define('BUTTON_SELECT', 'Select');
define('BUTTON_CHARGE', 'Charge card');

define('ERROR_SECURITY_TOKEN', 'Security token mismatch. Please try again.');
define('ERROR_REBILL_CARD_NOT_ELIGIBLE', 'That card is not available for rebill.');
define('ERROR_REBILL_CLASS_MISSING', 'paypalacSavedCardRecurring is not available.');
define('ERROR_REBILL_CHARGE_FAILED', 'Rebill charge failed: %s');
define('ERROR_REBILL_ORDER_FAILED', 'Charge succeeded (txn %s) but order recording failed: %s');
define('SUCCESS_REBILL_WITH_ORDER', 'Rebill successful. Transaction %s recorded on order #%d.');
define('SUCCESS_REBILL_CHARGE_ONLY', 'Rebill charge successful (txn %s). No Zen Cart order was created.');
