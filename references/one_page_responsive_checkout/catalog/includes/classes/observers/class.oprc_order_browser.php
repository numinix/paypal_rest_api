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
//  $Id: class.feac_order_browser.php 88 2009-08-27 21:03:25Z numinix $
//
if (!class_exists('OPRCBrowserObserver', false)) {
    class OPRCBrowserObserver extends base
    {
        public function __construct()
        {
            global $zco_notifier;
            $zco_notifier->attach($this, array('NOTIFY_HEADER_START_CHECKOUT_SUCCESS'));
        }

        public function update(&$class, $eventID, $paramsArray)
        {
            if (OPRC_STATUS == 'true') {
                if (isset($_SESSION['browser']) && $_SESSION['browser'] != '') {
                    global $db;

                    if (isset($_SESSION['order_number_created']) && $_SESSION['order_number_created'] >= 1) {
                        $orders_id = $_SESSION['order_number_created'];
                    } else {
                        // find out the last order number generated for this customer account
                        $orders_query = "SELECT * FROM " . TABLE_ORDERS . "
                       WHERE customers_id = :customersID
                       ORDER BY date_purchased DESC LIMIT 1";
                        $orders_query = $db->bindVars($orders_query, ':customersID', $_SESSION['customer_id'], 'integer');
                        $orders = $db->Execute($orders_query);
                        $orders_id = $orders->fields['orders_id'];
                    }
                    $db->Execute("UPDATE " . TABLE_ORDERS . " SET customers_browser = '" . $_SESSION['browser'] . "' WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND orders_id = " . (int)$orders_id . " LIMIT 1;");
                }
            }
        }
    }
}
// eof
