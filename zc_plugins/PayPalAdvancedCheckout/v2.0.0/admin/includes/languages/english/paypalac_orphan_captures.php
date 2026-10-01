<?php
/**
 * Language definitions for PayPal Advanced Checkout Orphan Captures admin page.
 */

define('HEADING_TITLE', 'PayPal Orphan Captures');
define('TEXT_ORPHAN_INTRO', 'Aged PayPal capture/authorization IDs reserved during checkout but never linked to a Zen Cart order (orders_id = 0). Rows newer than 5 minutes are hidden (in-flight checkout). Review PayPal and any follow-up money-order orders before refunding or voiding. Use Dismiss to remove the reservation row without calling PayPal.');

define('TABLE_HEADING_CAPTURE', 'Capture / Auth ID');
define('TABLE_HEADING_TYPE', 'Type');
define('TABLE_HEADING_CUSTOMER', 'Customer');
define('TABLE_HEADING_PAYPAL_ORDER', 'PayPal Order ID');
define('TABLE_HEADING_CREATED', 'Created');
define('TABLE_HEADING_ALERTED', 'Last alerted');
define('TABLE_HEADING_ACTIONS', 'Actions');

define('TEXT_NO_ORPHANS', 'No aged orphan capture reservations found.');
define('TEXT_CUSTOMER_UNKNOWN', 'Customer #%d');
define('TEXT_NEVER_ALERTED', '—');
define('TEXT_TYPE_CAPTURE', 'Capture');
define('TEXT_TYPE_AUTHORIZATION', 'Authorization');
define('TEXT_TYPE_UNKNOWN', 'Unknown');

define('BUTTON_REFUND', 'Refund');
define('BUTTON_VOID', 'Void');
define('BUTTON_DISMISS', 'Dismiss');
define('BUTTON_CONFIRM', 'Confirm');
define('BUTTON_CANCEL', 'Cancel');

define('TEXT_CONFIRM_HEADING_REFUND', 'Confirm refund');
define('TEXT_CONFIRM_HEADING_VOID', 'Confirm void');
define('TEXT_CONFIRM_HEADING_DISMISS', 'Confirm dismiss');
define('TEXT_CONFIRM_REFUND', 'Refund this PayPal capture via the API and remove the reservation row? This cannot be undone from Zen Cart.');
define('TEXT_CONFIRM_VOID', 'Void this PayPal authorization via the API and remove the reservation row? This cannot be undone from Zen Cart.');
define('TEXT_CONFIRM_DISMISS', 'Delete this reservation row without refunding or voiding at PayPal? The PayPal payment will remain until handled separately.');

define('ERROR_SECURITY_TOKEN', 'Security token mismatch. Please try again.');
define('ERROR_MISSING_CAPTURE_ID', 'Missing capture/authorization ID.');
define('ERROR_CONFIRM_REQUIRED', 'Confirmation required. Choose Confirm on the confirmation panel to proceed.');
define('ERROR_ROW_NOT_FOUND', 'Reservation row not found, still in-flight (under 5 minutes), already linked to an order, or claimed by another admin action.');
define('ERROR_CHECKOUT_LOCK', 'Could not acquire checkout lock for this PayPal order (checkout may be in progress). Try again shortly.');
define('ERROR_MODULE_MISSING', 'PayPal Advanced Checkout module is not available.');
define('ERROR_API_CREDENTIALS', 'PayPal API credentials are not configured.');
define('ERROR_REFUND_FAILED', 'PayPal refund failed for %s: %s');
define('ERROR_VOID_FAILED', 'PayPal void failed for %s: %s');
define('SUCCESS_REFUND', 'Refund accepted for %s (refund id %s). Reservation row removed.');
define('SUCCESS_VOID', 'Authorization voided for %s. Reservation row removed.');
define('SUCCESS_DISMISS', 'Reservation row for %s dismissed (removed without PayPal refund/void).');
