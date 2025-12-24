<?php
/**
 * @package Pages
 * @copyright Copyright 2008-2009 RubikIntegration.com
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: loader_one_page_confirmation.php 14 2012-09-17 23:47:08Z numinix $
 */

$loaders[] = array(
    'name' => 'OPRC - One Page Confirmation Scripts',
    'conditions' => array(
        'pages' => array(FILENAME_ONE_PAGE_CONFIRMATION),
    ),
    'jscript_files' => array(
        'jquery/jquery-3.7.1.min.js' => array('order' => 1, 'location' => 'header'),
        'jquery/jquery-migrate-3.4.1.min.js' => array('order' => 2, 'location' => 'header'),
        'jquery/oprc_processing_overlay.js' => array('order' => 3, 'location' => 'header'),
        'jquery/jquery_one_page_confirmation.php' => 4,
    ),
    'css_files' => array(
        'one_page_checkout.css' => array('order' => 2),
        'auto_loaders/one_page_checkout_overrides.css' => array('order' => 99),
    ),
);

