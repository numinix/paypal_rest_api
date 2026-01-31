<?php
/**
 * Language definitions for PayPal Advanced Checkout Vaulted Subscriptions Admin Page
 * 
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

// Page heading
define('HEADING_TITLE', 'PayPal Subscriptions');

// Error messages
define('ERROR_SUBSCRIPTION_MISSING_IDENTIFIER', 'Unable to update the subscription. Missing identifier.');
define('ERROR_SUBSCRIPTION_INVALID_DATE_FORMAT', 'Invalid date format for next payment date. Please use YYYY-MM-DD format.');
define('ERROR_SUBSCRIPTION_INVALID_JSON', 'The attributes JSON is invalid and was not saved.');
define('ERROR_SUBSCRIPTION_VAULT_NOT_FOUND', 'Unable to link the selected vaulted instrument. Please verify it still exists.');
define('ERROR_SUBSCRIPTION_CANCEL_MISSING_ID', 'Unable to cancel subscription. Missing identifier.');
define('ERROR_SUBSCRIPTION_SUSPEND_MISSING_ID', 'Unable to suspend subscription. Missing identifier.');
define('ERROR_SUBSCRIPTION_REACTIVATE_MISSING_ID', 'Unable to reactivate subscription. Missing identifier.');
define('ERROR_SUBSCRIPTION_ARCHIVE_MISSING_ID', 'Unable to archive subscription. Missing identifier.');
define('ERROR_SUBSCRIPTION_UNARCHIVE_MISSING_ID', 'Unable to unarchive subscription. Missing identifier.');
define('ERROR_BULK_ARCHIVE_NO_SELECTION', 'No subscriptions selected for bulk archive.');
define('ERROR_BULK_UNARCHIVE_NO_SELECTION', 'No subscriptions selected for bulk unarchive.');

// Success messages
define('SUCCESS_SUBSCRIPTION_CANCELLED', 'Subscription #%d has been cancelled.');
define('SUCCESS_SUBSCRIPTION_SUSPENDED', 'Subscription #%d has been suspended.');
define('SUCCESS_SUBSCRIPTION_REACTIVATED', 'Subscription #%d has been reactivated.');
define('SUCCESS_SUBSCRIPTION_ARCHIVED', 'Subscription #%d has been archived.');
define('SUCCESS_SUBSCRIPTION_UNARCHIVED', 'Subscription #%d has been unarchived.');
define('SUCCESS_SUBSCRIPTION_STATUS_UPDATED', 'Subscription #%d status has been updated to %s.');
define('SUCCESS_SUBSCRIPTION_UPDATED', 'Subscription #%d has been updated.');
define('SUCCESS_BULK_ARCHIVED', 'Successfully archived %d subscription(s).');
define('SUCCESS_BULK_UNARCHIVED', 'Successfully unarchived %d subscription(s).');

// Order log labels
if (!defined('TEXT_PAYPALR_SUBSCRIPTION_ORDER_LOG')) {
    define('TEXT_PAYPALR_SUBSCRIPTION_ORDER_LOG', 'Order Log');
}
if (!defined('TEXT_PAYPALR_SUBSCRIPTION_ORDER_LOG_EMPTY')) {
    define('TEXT_PAYPALR_SUBSCRIPTION_ORDER_LOG_EMPTY', 'No orders logged yet.');
}
