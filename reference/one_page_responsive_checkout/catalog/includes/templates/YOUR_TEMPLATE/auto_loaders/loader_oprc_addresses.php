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
	'conditions' => array('pages' => array(FILENAME_OPRC_CHECKOUT_BILLING_ADDRESS, FILENAME_OPRC_CHECKOUT_SHIPPING_ADDRESS)),
		'jscript_files' => array(
		),
		'css_files' => array(
	      	'one_page_checkout.css' => 1,
	      	'auto_loaders/one_page_checkout_overrides.css' => 99
	    )
	); 