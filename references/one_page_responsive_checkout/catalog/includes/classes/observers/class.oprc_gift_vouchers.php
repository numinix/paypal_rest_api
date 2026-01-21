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
//  $Id: class.oprc_gift_vouchers.php 3 2012-07-08 21:11:34Z numinix $
//
/**
 * Observer class used to redirect to the Easy Sign-up page
 *
 */
if (!class_exists('GiftVouchersObserver', false)) {
    class GiftVouchersObserver extends base
    {
      function __construct()
      {
        global $zco_notifier;
        $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS'));
        $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT'));
        $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS_VIA_OPRC_CREATE_ACCOUNT'));
        $zco_notifier->attach($this, array('NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT'));
      }

      function update(&$class, $eventID, $paramsArray) {
        global $messageStack, $db, $messageStack;
        // if a gift voucher has been redeemed but not yet credited, credit it now
        if (OPRC_STATUS == 'true' && isset($_SESSION['gv_id'])) {
          // Update redeem status
          $gv_query = "INSERT INTO  " . TABLE_COUPON_REDEEM_TRACK . "(coupon_id, customer_id, redeem_date, redeem_ip)
                       VALUES (:couponID, :customersID, now(), :remoteADDR)";

          $gv_query = $db->bindVars($gv_query, ':customersID', $_SESSION['customer_id'], 'integer');
          $gv_query = $db->bindVars($gv_query, ':couponID', $_SESSION['gv_id'], 'integer');
          $gv_query = $db->bindVars($gv_query, ':remoteADDR', zen_get_ip_address(), 'string');
          $db->Execute($gv_query);

          $gv_update = "UPDATE " . TABLE_COUPONS . "
                        SET coupon_active = 'N'
                        WHERE coupon_id = :couponID";

          $gv_update = $db->bindVars($gv_update, ':couponID', $_SESSION['gv_id'], 'integer');
          $db->Execute($gv_update);

          zen_gv_account_update($_SESSION['customer_id'], $_SESSION['gv_id']);
          $_SESSION['gv_id'] = '';

          $messageStack->add_session('header', VOUCHER_REDEEMED, 'success');
        }
      }
    }
}
// eof
