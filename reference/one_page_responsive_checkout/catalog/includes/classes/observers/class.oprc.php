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
//  $Id: class.oprc.php 3 2012-07-08 21:11:34Z numinix $
//
/**
 * Observer class used to redirect to the OPRC page
 *
 */
class OPRCObserver extends base 
{
	function OPRCObserver()
	{
	  global $zco_notifier;
	  $zco_notifier->attach($this, array('NOTIFY_HEADER_START_CHECKOUT_SHIPPING'));
      $zco_notifier->attach($this, array('NOTIFY_HEADER_START_CHECKOUT_PAYMENT'));
	}
	
	function update(&$class, $eventID, $paramsArray) {
    global $messageStack; 
      if (OPRC_STATUS == 'true') { //&& $_SESSION['javascript_enabled'] == true) {
        // mCommerce
        $mobile_browser = '0';
        if (defined('ENABLE_JQUERY_MOBILE_FOR_ZEN_CART') && ENABLE_JQUERY_MOBILE_FOR_ZEN_CART == 'true') {
          $detect = new Mobile_Detect();
          if ($detect->isMobile())  {
            $mobile_browser++;
          }      
        }
        // end mCommerce    
        if ((($_GET['main_page'] == FILENAME_CHECKOUT_PAYMENT || $_GET['main_page'] == FILENAME_CHECKOUT_CONFIRMATION || $_GET['main_page'] == FILENAME_CHECKOUT_SHIPPING) && sizeof($messageStack->messages) > 0) && $mobile_browser == 0) {
          $messageStackNew = new messageStack();
          for ($i=0, $n=sizeof($messageStack->messages); $i<$n; $i++) {
            if(OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') {
              $messageStack->messages[$i]['class'] = ($messageStack->messages[$i]['class'] =='checkout_shipping' ? 'checkout_payment' : $messageStack->messages[$i]['class']);
            }
            $messageStackNew->add_session($messageStack->messages[$i]['class'], strip_tags($messageStack->messages[$i]['text']), 'error');
          }
          $messageStack->reset();
          $messageStack = $messageStackNew;
        }
        if ($_GET['credit_class_error']) {
          $error = true;
          $messageStack->add_session('checkout_payment', htmlspecialchars(urldecode($_GET['credit_class_error'])), 'error');
        }
        //if ($error) { $_SESSION['request'] = $_REQUEST['request'] = 'nonajax'; }
        if (OPRC_NOACCOUNT_SWITCH == 'true' && OPRC_NOACCOUNT_DEFAULT == 'true' && $_SESSION['cart']->count_contents() > 0) {
          if($_SESSION['cart']->get_content_type() == 'physical') {
            $cowoa_action = 'type=cowoa';
          } else {
            $cowoa_action = (OPRC_NOACCOUNT_VIRTUAL == 'true' ? 'type=cowoa' : '');
          }
        }
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, $cowoa_action, 'SSL'));
      }
	}
}
// eof
