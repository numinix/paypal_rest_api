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

// Success messages
define('SUCCESS_SUBSCRIPTION_CANCELLED', 'Subscription #%d has been cancelled.');
define('SUCCESS_SUBSCRIPTION_SUSPENDED', 'Subscription #%d has been suspended.');
define('SUCCESS_SUBSCRIPTION_REACTIVATED', 'Subscription #%d has been reactivated.');
define('SUCCESS_SUBSCRIPTION_ARCHIVED', 'Subscription #%d has been archived.');
define('SUCCESS_SUBSCRIPTION_UNARCHIVED', 'Subscription #%d has been unarchived.');
define('SUCCESS_SUBSCRIPTION_STATUS_UPDATED', 'Subscription #%d status has been updated to %s.');
define('SUCCESS_SUBSCRIPTION_UPDATED', 'Subscription #%d has been updated.');
