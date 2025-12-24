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
//  $Id: class.oprc_logoff.php 3 2012-07-08 21:11:34Z numinix $
//
/**
 * Observer class used to redirect to the OPRC page
 *
 */
class OPRCLogoffObserver extends base 
{
	function OPRCLogoffObserver()
	{
		global $zco_notifier;
		$zco_notifier->attach($this, array('NOTIFY_HEADER_START_LOGOFF'));
        $zco_notifier->attach($this, array('NOTIFY_TEMPLATE_END_CHECKOUT_SUCCESS'));
	}
	
	function update(&$class, $eventID, $paramsArray) {
	  if (OPRC_STATUS == 'true') {
        switch($_GET['main_page']) {
          case FILENAME_LOGOFF:
            setcookie('email_address', 0, time() - 3600);
            setcookie('password', 0, time() - 3600);
            break;
          case FILENAME_CHECKOUT_SUCCESS:
            if (isset($_SESSION['COWOA']) && $_SESSION['COWOA']) {
              zen_session_destroy();
            }
            break;
        }
	  }
	}
}
// eof
