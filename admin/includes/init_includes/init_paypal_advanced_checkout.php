<?php
/**
 * Init script for PayPal Advanced Checkout Admin Pages Installer
 * 
 * This script checks if the admin pages need to be registered and runs
 * the appropriate installer version file(s).
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Check if any of the PayPal Advanced Checkout admin pages exist
// If not, we need to run the installer
if (function_exists('zen_page_key_exists')) {
    if (!zen_page_key_exists('paypalrSubscriptions') || 
        !zen_page_key_exists('paypalrSavedCardRecurring') || 
        !zen_page_key_exists('paypalrSubscriptionsReport')) {
        
        // Run the 1.0.0 installer to register admin pages
        $installer_file = DIR_FS_ADMIN . DIR_WS_INCLUDES . 'installers/paypal_advanced_checkout/1_0_0.php';
        if (file_exists($installer_file)) {
            include_once($installer_file);
        }
    }
}
