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
// | to obtain it through the world-wide-web, please s_END a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: header_php.php 14 2012-09-17 23:47:08Z numinix $
//
  // reset messageStack for Ajax
  //$messageStack->reset(); 
  $zco_notifier->notify('NOTIFY_HEADER_START_OPRC');
  require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

  // begin checkout maintenance
  $down_for_maintenance = false;
  $exclude_ip = strstr(EXCLUDE_ADMIN_IP_FOR_MAINTENANCE, $_SERVER['REMOTE_ADDR']);
  if (OPRC_MAINTENANCE == 'true' && !$exclude_ip) {
    $down_for_maintenance = true;
  } else {
    if (OPRC_MAINTENANCE_SCHEDULE == 'true') {
      $today = strtoupper(date('l', time() + OPRC_MAINTENANCE_SCHEDULE_OFFSET * 60 * 60)); // SUNDAY
      $current_hour = date('G') + OPRC_MAINTENANCE_SCHEDULE_OFFSET; // 13
      if (constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END') < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START')) {
        if ( ($current_hour >= constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END')) && ($current_hour < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START')) ) {
          $down_for_maintenance = false;
        } elseif (!$exclude_ip) {
          $down_for_maintenance = true;
        } 
      } else {
        if ( !$exclude_ip && ($current_hour >= constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_START')) && ($current_hour < constant('OPRC_MAINTENANCE_SCHEDULE_' . $today . '_END')) ) {
          $down_for_maintenance = true;
        }
      }
    } 
  }
  
  if (!$down_for_maintenance) {
    
    $customer_check = (isset($_SESSION['customer_id']) ? true : false);
    
    // order steps
    /*
    if (isset($_GET['step'])) {
      $step = $_GET['step'];
      switch($step) {
        case '1':
        case '2':
          if ($customer_check) {
            require_once(DIR_WS_CLASSES . 'order.php');
            $order = new order;            
            // customer is on the login page. If the customer is logged in, save their cart and then log them out
            // set the sessions to prefill the registration page
            // get all of the values for each field
            $customers_info = $db->Execute("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " LIMIT 1;");
            $shipping_address = $db->Execute("SELECT * FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND address_book_id = " . (int)$_SESSION['sendto'] . " LIMIT 1;");
            $billing_address = $db->Execute("SELECT * FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND address_book_id = " . (int)$_SESSION['billto'] . " LIMIT 1;");
            $_SESSION['email_address_register'] = $order->customer['email_address'];
            $_SESSION['email_address_confirm'] = $order->customer['email_address'];
            $_SESSION['password-register'] = ''; // has to be blank since we don't know the password
            $_SESSION['password-confirmation'] = '';
            $_SESSION['customers_referral'] = $customers_info->fields['customers_referral'];
            $_SESSION['fax'] = $customers_info->fields['customers_fax'];
            $_SESSION['dob_month'] = date('m', strtotime($customers_info->fields['customers_dob']));
            $_SESSION['dob_day'] = date('d', strtotime($customers_info->fields['customers_dob']));
            $_SESSION['dob_year'] = date('Y', strtotime($customers_info->fields['customers_dob']));
            $_SESSION['referred_by_code'] = $customers_info->fields['referrer_code'];
            $_SESSION['newsletter'] = $customers_info->fields['customers_newsletter'];
            $_SESSION['nick'] = ''; // can't set this as it will already be taken
            // billing            
            $_SESSION['telephone'] = $billing_address->fields['entry_telephone'];
            $_SESSION['company'] = $order->billing['company'];
            $_SESSION['firstname'] = $order->billing['firstname'];
            $_SESSION['lastname'] = $order->billing['lastname'];
            $_SESSION['street_address'] = $order->billing['street_address'];
            $_SESSION['suburb'] = $order->billing['suburb'];
            $_SESSION['postcode'] = $order->billing['postcode'];
            $_SESSION['city'] = $order->billing['city'];
            $_SESSION['state'] = $order->billing['state'];
            $_SESSION['zone_id'] = $order->billing['zone_id'];
            $_SESSION['zone_country_id'] = $order->billing['zone_country_id'];
            $_SESSION['gender'] = $billing_address->fields['entry_gender'];
            // shipping
            $_SESSION['gender_shipping'] = $shipping_address->fields['entry_gender'];
            $_SESSION['company_shipping'] = $order->delivery['company'];
            $_SESSION['firstname_shipping'] = $order->delivery['firstname'];
            $_SESSION['lastname_shipping'] = $order->delivery['lastname'];
            $_SESSION['street_address_shipping'] = $order->delivery['street_address'];
            $_SESSION['suburb_shipping'] = $order->delivery['suburb'];
            $_SESSION['postcode_shipping'] = $order->delivery['postcode'];
            $_SESSION['city_shipping'] = $order->delivery['city'];
            $_SESSION['state_shipping'] = $order->delivery['state'];
            $_SESSION['zone_id_shipping'] = $order->delivery['zone_id'];
            $_SESSION['zone_country_id_shipping'] = $order->delivery['country_id'];
            $_SESSION['telephone_shipping'] = $shipping_address->fields['entry_telephone'];  
            $saved_products = $_SESSION['cart']->get_products();
            // reset the shopping cart
            $_SESSION['cart']->reset();
            // log the customer out
            unset($_SESSION['customer_id']);
            // re-add all of the products
            foreach($saved_products as $saved_product) {
              $_SESSION['cart']->add_cart($saved_product['id'], $saved_product['quantity'], $saved_product['attribute_values'], false);
            }
            // redirect back to the checkout page
            if ($_SESSION['cowoa']) {
              zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'type=cowoa&step=' . $step, 'SSL'));
            } else {
              $messageStack->add_session('one_page_checkout', 'You\'ve been logged out of your account.  If you wish to continue checking out with the same email address, login or continue as a guest.', 'warning');
              zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=' . $step, 'SSL'));
            }
          }
          break;
        case '3':
          // redirect back into the cart without a step
          //zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
          // do nothing
        default:
          // do nothing
          break;
      }
    } else {
      if ($customer_check && !isset($_GET['step'])) {
        // we could force a step here but this will cause a loss of messageStack sessions
        //zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=3', 'SSL'));
      }
    }
    */
        
    switch($customer_check) {
      case true:
        if ($_SESSION['cart']->count_contents() > 0) {
          // default page for checkout
          if ($_POST['oprcaction'] == 'updateCredit') {
            $messageStack->reset();
          }  
          require(DIR_WS_MODULES . zen_get_module_directory('oprc_updates.php'));
        } else {
          if (!in_array(zen_back_link(true), array(zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'), zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'), zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'), zen_href_link(FILENAME_OPRC_CONFIRMATION, '', 'SSL')))) {
            zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
          } else {
            zen_redirect(zen_href_link(FILENAME_ACCOUNT));
          }
        }
        $breadcrumb->add(NAVBAR_TITLE_1_CHECKOUT, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
      break;
      default: // customer is not logged in
        // Auto Login
        if (OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN == 'true' && isset($_COOKIE['email_address']) && isset($_COOKIE['password'])) {
          zen_redirect(zen_href_link(FILENAME_OPRC_LOGIN, 'oprcaction=process&autologin=true', 'SSL'));
        }
		
		// if guest checkout only, go straight to step 2
		if ($_GET['step'] != 1 && $_GET['step'] != 2 && $_GET['type'] == 'cowoa' && OPRC_NOACCOUNT_ONLY_SWITCH == 'true') {
			zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=2&type=cowoa', 'SSL'));
		}
        
        // if there is nothing in the cart, redirect to the regular login page
        if (isset($_SESSION['cart']) && $_SESSION['cart']->count_contents() > 0) {    
          // BOF Captcha
          if(CAPTCHA_CREATE_ACCOUNT == 'true' && file_exists(DIR_WS_CLASSES . 'captcha.php')) { // check exists because file is not included with OPRC
            require(DIR_WS_CLASSES . 'captcha.php');
            $captcha = new captcha();
          }
          // EOF Captcha
          
          // ajax check
          // check if shipping address should be displayed
          if (OPRC_SHIPPING_ADDRESS == 'true') $shippingAddressCheck = true;
          // check if the copybilling checkbox should be checked
          $shippingAddress = true;
          /*
          * Set flags for template use:
          */
          $selected_country = (isset($_SESSION['zone_country_id']) && $_SESSION['zone_country_id'] != '') ? $_SESSION['zone_country_id'] : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
          $selected_country_shipping = (isset($_SESSION['zone_country_id_shipping']) && $_SESSION['zone_country_id_shipping'] != '') ? $_SESSION['zone_country_id_shipping'] : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
          $flag_show_pulldown_states = ((($process == true || $entry_state_has_zones == true) && $zone_name == '') || ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true' || $error_state_input) ? true : false;
          $flag_show_pulldown_states_shipping = ((($process_shipping == true || $entry_state_has_zones_shipping == true) && $zone_name_shipping == '') || ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true' || $error_state_input_shipping) ? true : false;
          $state = $_SESSION['state'];
          $state_shipping = $_SESSION['state_shipping'];
          $state_field_label = ($flag_show_pulldown_states) ? '' : ENTRY_STATE;
          $state_field_label_shipping = ($flag_show_pulldown_states_shipping) ? '' : ENTRY_STATE;
          $zone_id = $_SESSION['zone_id']; 
          $zone_id_shipping = $_SESSION['zone_id_shipping']; 
          
          if (!isset($email_format)) $email_format = (ACCOUNT_EMAIL_PREFERENCE == '1' ? 'HTML' : 'TEXT');
          if (!isset($newsletter))   $newsletter = (ACCOUNT_NEWSLETTER_STATUS == '1' ? false : true);
          
          require_once(DIR_WS_CLASSES . 'order.php');
          $order = new order;
          require_once(DIR_WS_CLASSES . 'order_total.php');
          $order_total_modules = new order_total;
          
          $breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
          // _END registration
        } else {
          zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
        }
      break;
    }
  }
?>