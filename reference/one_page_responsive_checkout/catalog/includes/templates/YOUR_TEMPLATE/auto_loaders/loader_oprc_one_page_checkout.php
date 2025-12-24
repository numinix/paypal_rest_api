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
    'conditions' => array('pages' => array(FILENAME_ONE_PAGE_CHECKOUT, FILENAME_OPRC_CHECKOUT_BILLING_ADDRESS, FILENAME_OPRC_CHECKOUT_SHIPPING_ADDRESS)),
    'jscript_files' => array(
        'jquery/jquery-1.12.0.min.js' => 1,
        'jquery/jquery-migrate-1.3.0.min.js' => 2,
        'jquery/jquery.blockUI.js' => 3,
        'jquery/jquery.history.js' => 4,
        'jquery/jquery.fancybox.js' => 5,
        'jquery/jquery_form_check.js' => 6,
        'jquery/jquery.stickem.js' => 7,
        'jquery/jquery_addr_pulldowns.php' => 8,
        'jquery/jquery_form_check.php' => 9,
        'jquery/jquery_new_address.php' => 10,
        'jquery/jquery_one_page_checkout.php' => 11,
        'jquery/jquery_one_page_checkout.js' => 12
    ),
    'css_files' => array(
        'auto_loaders/jquery.fancybox.css' => 1,
        'one_page_checkout.css' => 2,
        'auto_loaders/one_page_checkout_overrides.css' => 99
    )
);

if(OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT == 'true') $loader['jscript_files']['jquery/jquery_oprc_express_paypal.php'] = 13;
$loaders[] = $loader;
