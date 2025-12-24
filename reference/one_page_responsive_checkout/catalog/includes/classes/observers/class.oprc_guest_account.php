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
//  $Id: class.oprc_guest_account.php 3 2012-07-08 21:11:34Z numinix $
//

class noAccountObserver extends base {
  function noAccountObserver() {
	global $zco_notifier;
	$zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT_EDIT'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT_HISTORY'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT_HISTORY_INFO'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT_NOTIFICATION'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ACCOUNT_PASSWORD'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ADDRESS_BOOK'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_ADDRESS_BOOK_PROCESS'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_REGISTERED_USERS_ONLY'));
    $zco_notifier->attach($this, array('NOTIFY_HEADER_START_GV_SEND'));
  }
  
  function update(&$class, $eventID, $paramsArray) {
    global $messageStack;
    if (OPRC_STATUS == 'true') {
      if (isset($_SESSION['COWOA']) && $_SESSION['COWOA'] == true) {
        $messageStack->add_session('header', 'Only registered customers can access account features.  You are currently using our guest checkout option.  Please logout and sign-in with your registered account to access all account features.', 'caution');
        $last_link = zen_back_link(true);
        zen_redirect(zen_href_link(FILENAME_DEFAULT));
      } elseif (!isset($_SESSION['customer_id'])) {
        $_SESSION['redirect_url'] = zen_href_link($_GET['main_page'], zen_get_all_get_params(array('main_page')), 'SSL');
      }
	}
  }
}
// eof
