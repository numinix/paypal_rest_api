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
//  $Id: class.subscriptions_created.php 12 2010-09-03 20:44:47Z numinix $
//
/**
 * Observer class used to handle reward points in an order
 *
 */
class subscriptionCreatedObserver extends base {
    function __construct() {
        global $zco_notifier;
        $zco_notifier->attach($this, array('NOTIFY_RECURRING_ORDER_LOGGED'));
    }

    function update(&$class, $eventID, $paramsArray) {
        global $db;
        $group_percentage = 0;
        $group_id = false;
        if (is_array($_SESSION['paypal_recurring_profile']) && count($_SESSION['paypal_recurring_profile']) > 0) {
            foreach ($_SESSION['paypal_recurring_profile'] as $profile) {
                $profile_details = $db->Execute("SELECT * FROM " . TABLE_PAYPAL_RECURRING . " WHERE profile_id = '" . $profile['profile_id'] . "' AND customers_id = " . (int) $_SESSION['customer_id'] . " LIMIT 1;");
                if ($profile_details->RecordCount() > 0) {
                    $products_name = zen_get_products_name($profile_details->fields['products_id']);
                    $group = $db->Execute("SELECT group_id, group_percentage FROM " . TABLE_GROUP_PRICING . " WHERE group_name = '" . $products_name . "' LIMIT 1;");
                    if ($group->RecordCount() > 0 && $group->fields['group_percentage'] > $group_percentage) {
                        $group_id = $group->fields['group_id'];
                        $group_percentage = $group->fields['group_percentage'];
                    }
                }
            }
        }

        // remove any pending cancellations from the database for this customer
        $db->Execute("DELETE FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . " WHERE customers_id = " . (int) $_SESSION['customer_id'] . ";");

        if ($group_id > 0) {
            // set the customer's group ID
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_group_pricing = " . (int) $group_id . " WHERE customers_id = " . (int) $_SESSION['customer_id'] . " LIMIT 1;");
        }
    }
}