<?php
/**
 * Installer for PayPal Saved Card Recurring Admin Page
 * Registers the paypalr_saved_card_recurring.php page in the Zen Cart admin menu
 * 
 * This installer runs once when the page is first accessed and registers
 * the admin page so it appears under the "Customers" menu.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));

// Register the Saved Card Subscriptions admin page under the Customers menu
if ($zc150 && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) {
    if (!zen_page_key_exists('paypalrSavedCardRecurring')) {
        zen_register_admin_page(
            'paypalrSavedCardRecurring',
            'BOX_PAYPALR_SAVED_CARD_RECURRING',
            'FILENAME_PAYPALR_SAVED_CARD_RECURRING',
            '',
            'customers',
            'Y',
            11
        );
    }
}
