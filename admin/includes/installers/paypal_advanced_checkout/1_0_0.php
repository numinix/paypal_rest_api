<?php
/**
 * Installer for PayPal Advanced Checkout Admin Pages
 * Registers all PayPal subscription and report pages in the Zen Cart admin menu
 * 
 * This installer runs once when any of the pages is first accessed and registers
 * all admin pages for the PayPal Advanced Checkout plugin.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));

if ($zc150 && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) {
    
    // Register Vaulted Subscriptions under Customers menu
    if (!zen_page_key_exists('paypalrSubscriptions')) {
        zen_register_admin_page(
            'paypalrSubscriptions',
            'BOX_PAYPALR_SUBSCRIPTIONS',
            'FILENAME_PAYPALR_SUBSCRIPTIONS',
            '',
            'customers',
            'Y',
            10
        );
    }
    
    // Register Saved Card Subscriptions under Customers menu
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
    
    // Register Active Subscriptions Report under Reports menu
    if (!zen_page_key_exists('paypalrSubscriptionsReport')) {
        zen_register_admin_page(
            'paypalrSubscriptionsReport',
            'BOX_PAYPALR_SUBSCRIPTIONS_REPORT',
            'FILENAME_PAYPALR_SUBSCRIPTIONS_REPORT',
            '',
            'reports',
            'Y',
            100
        );
    }
}
