<?php
/**
 * Language definitions for PayPal Advanced Checkout Orphan Captures admin page.
 */

define('HEADING_TITLE', 'PayPal Orphan Captures');
define('TEXT_ORPHAN_INTRO', 'These PayPal capture/authorization IDs were reserved during checkout but never linked to a Zen Cart order (orders_id = 0). Review PayPal and any follow-up money-order orders before refunding. Use Dismiss to remove the reservation row without calling PayPal (e.g. already refunded manually or paid another way).');

define('TABLE_HEADING_CAPTURE', 'Capture / Auth ID');
define('TABLE_HEADING_CUSTOMER', 'Customer');
define('TABLE_HEADING_PAYPAL_ORDER', 'PayPal Order ID');
define('TABLE_HEADING_CREATED', 'Created');
define('TABLE_HEADING_ALERTED', 'Last alerted');
define('TABLE_HEADING_ACTIONS', 'Actions');

define('TEXT_NO_ORPHANS', 'No orphan capture reservations found.');
define('TEXT_CUSTOMER_UNKNOWN', 'Customer #%d');
define('TEXT_NEVER_ALERTED', '—');

define('BUTTON_REFUND', 'Refund');
define('BUTTON_DISMISS', 'Dismiss');
define('TEXT_CONFIRM_REFUND', 'Refund this PayPal capture via the API and remove the reservation row?');
define('TEXT_CONFIRM_DISMISS', 'Delete this reservation row without refunding at PayPal?');

define('ERROR_SECURITY_TOKEN', 'Security token mismatch. Please try again.');
define('ERROR_MISSING_CAPTURE_ID', 'Missing capture/authorization ID.');
define('ERROR_ROW_NOT_FOUND', 'Reservation row not found (or already linked to an order).');
define('ERROR_MODULE_MISSING', 'PayPal Advanced Checkout module is not available.');
define('ERROR_API_CREDENTIALS', 'PayPal API credentials are not configured.');
define('ERROR_REFUND_FAILED', 'PayPal refund failed for %s: %s');
define('SUCCESS_REFUND', 'Refund accepted for %s (refund id %s). Reservation row removed.');
define('SUCCESS_DISMISS', 'Reservation row for %s dismissed (removed without PayPal refund).');
