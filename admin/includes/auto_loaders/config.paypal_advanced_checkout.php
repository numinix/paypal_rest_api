<?php
/**
 * Auto-loader for PayPal Advanced Checkout Admin Pages Installer
 * 
 * This file registers the init script that will run the admin page installer
 * for PayPal Advanced Checkout subscription and report pages.
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[999][] = array(
    'autoType' => 'init_script',
    'loadFile' => 'init_paypal_advanced_checkout.php'
);
