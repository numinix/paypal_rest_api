<?php
/**
 * Installer for PayPal Subscriptions Report Admin Page
 * Registers the paypalr_subscriptions_report.php page in the Zen Cart admin menu
 * 
 * This installer runs once when the page is first accessed and registers
 * the admin page so it appears under the "Customers" menu.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));

// Register the Active Subscriptions Report admin page under the Customers menu
if ($zc150 && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) {
    if (!zen_page_key_exists('paypalrSubscriptionsReport')) {
        zen_register_admin_page(
            'paypalrSubscriptionsReport',
            'BOX_PAYPALR_SUBSCRIPTIONS_REPORT',
            'FILENAME_PAYPALR_SUBSCRIPTIONS_REPORT',
            '',
            'customers',
            'Y',
            12
        );
    }
}
