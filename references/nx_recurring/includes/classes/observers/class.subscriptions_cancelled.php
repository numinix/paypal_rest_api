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
//  $Id: class.subscriptions_cancelled.php 12 2010-09-03 20:44:47Z numinix $
//
/**
 * Observer class used to handle reward points in an order
 *
 */
class subscriptionCancelledObserver extends base 
{
	public function __construct() {
		global $zco_notifier;
		$zco_notifier->attach($this, array('NOTIFY_PAYPAL_WPP_RECURRING_CANCELLED', 'NOTIFY_PAYPAL_WPP_RECURRING_EXPIRED', 'NOTIFY_PAYPAL_WPP_RECURRING_SKIPPED'));
	}
	
	function update(&$class, $eventID, $paramsArray) 
	{
    global $db, $customers_id, $orders_id, $products_id;
    // expire the credit
    $check_store_credit = $db->Execute("SELECT log_id 
                                        FROM " . TABLE_SC_REWARD_POINT_LOGS . " 
                                        WHERE orders_id = " . (int)$orders_id . " 
                                        AND products_id = " . (int)$products_id . "
                                        AND customers_id = " . (int)$customers_id . "
                                        LIMIT 1;");
    if ($check_store_credit->RecordCount() > 0) {
      $now = time();
      $credit_expires = $now;
      $db->Execute("UPDATE " . TABLE_SC_REWARD_POINT_LOGS . " SET expires_on = " . (int)$credit_expires . " WHERE log_id = " . (int)$check_store_credit->fields['log_id'] . " LIMIT 1;");
      // force cron to run
      require_once(DIR_WS_CATALOG . 'store_credit_cron.php');                    
    }
    
    require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
    $paypalSavedCardRecurring = new paypalSavedCardRecurring();
    
    // cancel group pricing if product is a subscription 
    $paypalSavedCardRecurring->remove_group_pricing((int)$_SESSION['customer_id'], $products_id);
    
  }
}