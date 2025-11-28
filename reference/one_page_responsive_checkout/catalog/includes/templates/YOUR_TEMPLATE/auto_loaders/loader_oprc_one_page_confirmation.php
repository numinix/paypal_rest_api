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
	'conditions' => array('pages' => array(FILENAME_ONE_PAGE_CONFIRMATION)),
		'jscript_files' => array(
			'jquery/jquery-1.12.0.min.js' => 1,
        	'jquery/jquery-migrate-1.3.0.min.js' => 2,
			'jquery/jquery.blockUI.js' => 3,
			'jquery/jquery_one_page_confirmation.php' => 4
		),
		'css_files' => array(
	      	'one_page_checkout.css' => 2,
        	'auto_loaders/one_page_checkout_overrides.css' => 99
	    )
	);