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
//  $Id: class.paypal_wpp_recurring.php 12 2010-09-03 20:44:47Z numinix $
//
/**
 * Observer class used to handle reward points in an order
 *
 */
class paypalWPPRecurringObserver extends base 
{
  function __construct()
  {
	  global $zco_notifier;
	  $zco_notifier->attach($this, array('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS'));
  }
  
  function update(&$class, $eventID, $paramsArray) 
  {
    global $zco_notifier;
    // check if  $_SESSION['paypal_recurring_profile']) is an array and has elements using the is_array() function along with the count() function.
    if (is_array($_SESSION['paypal_recurring_profile']) && count($_SESSION['paypal_recurring_profile']) > 0 && $_SESSION['order_number_created'] > 0) {
      
      if (sizeof($_SESSION['paypal_recurring_profile']) > 0 && $_SESSION['order_number_created'] > 0) {
        global $db;
        
        foreach($_SESSION['paypal_recurring_profile'] as $paypal_recurring_profile) {
          if ($paypal_recurring_profile['profile_id'] != '') {
            // make sure that the product was in the order, just in case something went wrong - this could be a redundant step
            $orders_products = $db->Execute("SELECT products_id FROM " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = " . (int)$_SESSION['order_number_created'] . " AND products_id = " . (int)$paypal_recurring_profile['products_id'] . " LIMIT 1;");
            if ($orders_products->RecordCount() > 0) {
              $subscription = $db->Execute("SELECT * FROM " . TABLE_PAYPAL_RECURRING . " WHERE profile_id = '" . $paypal_recurring_profile['profile_id'] . "' LIMIT 1;");
              if ($subscription->RecordCount() > 0) {
                switch($subscription->fields['billingperiod']) {
                  case 'Day':
                    $seconds = 86400;  
                    break;
                  case 'Week';
                    $seconds = 604800;
                    break;
                  case 'SemiMonth':
                    $seconds = 1209600;
                    break;
                  case 'Month':
                    $seconds = 2419200;
                    break;
                  case 'Year':
                    $seconds = 29030400;
                    break;
                }
                $now = time();
                // check if subscription has a limited number of billing cycles
                if ((int)$subscription->fields['totalbillingcycles'] > 0) {
                  $total_seconds_in_future = $seconds * (int)$subscription->fields['billingfrequency'] * (int)$subscription->fields['totalbillingcycles'];
                  $expiration_date = $now + $total_seconds_in_future;
                  // convert to datetime
                  $expiration_date = date('Y-m-d', $expiration_date); 
                } else {
                  // no billing cycle was set so set expiration date to 0
                  $expiration_date = 0; 
                }            
                // update the expiration date and orders ID
                $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET orders_id = " . (int)$_SESSION['order_number_created'] . ", expiration_date = '" . $expiration_date . "' WHERE profile_id = '" . $paypal_recurring_profile['profile_id'] . "' LIMIT 1;");
              }
            }
          }        
        }
      }
    }
    
    $zco_notifier->notify('NOTIFY_RECURRING_ORDER_LOGGED'); 
    unset($_SESSION['paypal_recurring_profile']);
  }
}
