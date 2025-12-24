<?php

/**
 * @package Pages
 * @copyright Copyright 2008-2009 RubikIntegration.com
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: loader_one_page_checkout.php 31 2012-09-28 19:14:55Z numinix $
 */
$loader = array(
    'name' => 'OPRC - One Page Checkout Scripts',
    'conditions' => array(
        'pages' => array(
            FILENAME_ONE_PAGE_CHECKOUT,
            FILENAME_OPRC_CHECKOUT_BILLING_ADDRESS,
            FILENAME_OPRC_CHECKOUT_SHIPPING_ADDRESS,
        ),
    ),
    'jscript_files' => array(
        'jquery/jquery-3.7.1.min.js' => array('order' => 1, 'location' => 'header'),
        'jquery/jquery-migrate-3.4.1.min.js' => array('order' => 2, 'location' => 'header'),
        'jquery/oprc_processing_overlay.js' => array('order' => 3, 'location' => 'header'),
        'jquery/jquery.history.js' => 4,
        'jquery/jquery.fancybox.js' => 5,
        'jquery/jquery_form_check.js' => 6,
        'jquery/jquery_addr_pulldowns.php' => 7,
        'jquery/jquery_form_check.php' => 8,
        'jquery/jquery_new_address.php' => 9,
        'jquery/jquery_one_page_checkout.php' => 10,
        'jquery/jquery_one_page_checkout.js' => 11,
    ),
    'css_files' => array(
        'auto_loaders/jquery.fancybox.css' => array('order' => 1),
        'one_page_checkout.css' => array('order' => 2),
        'auto_loaders/one_page_checkout_overrides.css' => array('order' => 99),
    ),
);

if (defined('OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT') && OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT == 'true') {
    $loader['jscript_files']['jquery/jquery_oprc_express_paypal.php'] = array('order' => 12, 'location' => 'header');
}
$loaders[] = $loader;

