<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2007-2008 Numinix Technology http://www.numinix.com    |
// |                                                                      |
// | Portions Copyright (c) 2003-2006 Zen Cart Development Team           |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: class.oprc_reset_shipping_quotes_cache.php 3 2012-07-08 21:11:34Z numinix $
//
/**
 * Observer class used to redirect to the Easy Sign-up page
 *
 */
class resetShippingQuotes extends base 
{
	function resetShippingQuotes()
	{
		global $zco_notifier;
		$zco_notifier->attach(
            $this, 
            array(
                'NOTIFY_MODULE_END_CHECKOUT_NEW_ADDRESS', 
                'NOTIFIER_CART_ADD_CART_END', 
                'NOTIFIER_CART_UPDATE_QUANTITY_END', 
                'NOTIFIER_CART_REMOVE_END', 
                'NOTIFY_LOGIN_SUCCESS', 
                'NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT', 
                'NOTIFY_LOGIN_SUCCESS_VIA_OPRC_CREATE_ACCOUNT',
                'NOTIFY_HEADER_START_UPDATE_AJAX_ESTIMATOR',
                'NOTIFY_HEADER_START_SHOPPING_CART'
            )
        );
	}
	
	function update(&$class, $eventID, $paramsArray) {
		unset($_SESSION['shipping_quotes']);
	}
}
// eof