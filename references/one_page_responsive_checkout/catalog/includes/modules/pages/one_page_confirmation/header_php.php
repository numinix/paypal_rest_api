<?php
/**
 * checkout_confirmation header_php.php
 *
 * @package page
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: header_php.php 3 2012-07-08 21:11:34Z numinix $
 */

// This should be first line of the script:
$zco_notifier->notify('NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION');
$flag_disable_left = true;
$flag_disable_right = true;
require_once(DIR_WS_CLASSES . 'http_client.php');
$messageStack->reset();
// all action to this page are not ajax
$_SESSION['request'] = $_POST['request'] = $_GET['request'] = 'nonajax'; // set this so that error messages from oprc_confirmation will display full html
if (!defined('OPRC_CONFIRMATION_AUTOSUBMIT')) {
  define('OPRC_CONFIRMATION_AUTOSUBMIT', false);
}
// if there is nothing in the customers cart, redirect them to the shopping cart page
if ($_SESSION['cart']->count_contents() <= 0) {
    zen_redirect(zen_href_link(FILENAME_TIME_OUT));
}

// if the customer is not logged on, redirect them to the login page
  if (!$_SESSION['customer_id']) {
    $_SESSION['navigation']->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_ONE_PAGE_CHECKOUT));
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL', false));
  } else {
    // validate customer
    if (zen_get_customer_validate_session($_SESSION['customer_id']) == false) {
      $_SESSION['navigation']->set_snapshot();
      zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL', false));
    }
  }

// avoid hack attempts during the checkout procedure by checking the internal cartID
if (isset($_SESSION['cart']->cartID) && $_SESSION['cartID']) {
  if ($_SESSION['cart']->cartID != $_SESSION['cartID']) {
    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
  }
}

if (isset($_POST['payment'])) $_SESSION['payment'] = $_POST['payment'];
$_SESSION['comments'] = zen_output_string_protected($_POST['comments']);

//'checkout_payment_discounts'
//zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));


if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
  if (!isset($_POST['conditions']) || ($_POST['conditions'] != '1')) {
    $messageStack->add_session('conditions', ERROR_CONDITIONS_NOT_ACCEPTED, 'error');
    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
  }
}
//echo $messageStack->size('checkout_payment');

$total_weight = $_SESSION['cart']->show_weight();
$total_count = $_SESSION['cart']->count_contents();

require(DIR_WS_CLASSES . 'order.php');
$order = new order;
// load the selected shipping module
require(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping();

// process modules
$oprc_process_dir_full = DIR_FS_CATALOG . DIR_WS_MODULES . 'one_page_checkout_process/';
$oprc_process_dir = DIR_WS_MODULES . 'one_page_checkout_process/';
if ($dir = @dir($oprc_process_dir_full)) {
  while ($file = $dir->read()) {
    if (!is_dir($oprc_process_dir_full . $file)) {
      if (preg_match('/\.php$/', $file) > 0) {
        //include init file
        include($oprc_process_dir . $file);
      }
    }
  }
  $dir->close();
}

if (isset($_GET['oprcaction']) && $_GET['oprcaction'] == 'process') {
  //for ($i=0; $i<=1; $i++) { // perform twice, this should be optimized in the future
    if (isset($_POST['shipping'])) {
      $bool = true; //tell a freand
      $_SESSION['shipping']['id'] = $_POST['shipping'];
      if ( (zen_count_shipping_modules() > 0) || ($free_shipping == true) ) {
        if ( (isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_')) ) {
          $_SESSION['shipping']['id'] = $_POST['shipping']; // process shipping
          list($module, $method) = explode('_', $_SESSION['shipping']['id']);
          if ( is_object(${$module}) || ($_SESSION['shipping']['id'] == 'free_free') ) {
            if ($_SESSION['shipping']['id'] == 'free_free') {
              $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
              $quote[0]['methods'][0]['cost'] = '0';
            } else {
              // avoid incorrect calculations during redirect
              $shipping_modules = new shipping();            
              $quote = $shipping_modules->quote($method, $module);
              //$debug_logger->log_event (__FILE__, __LINE__, $quote); 
            }
            if (isset($quote['error'])) {
              $_SESSION['shipping'] = '';
            } else {
              if ( (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost'])) ) {
                $_SESSION['shipping'] = array('id' => $_SESSION['shipping']['id'],
                                  'title' => (($free_shipping == true) ?  $quote[0]['methods'][0]['title'] : $quote[0]['module'] . ' (' . $quote[0]['methods'][0]['title'] . ')'),
                                  'cost' => $quote[0]['methods'][0]['cost']);              
              }
            }
          } else {
            unset($_SESSION['shipping']);
          }
        }
      } else {
        unset($_SESSION['shipping']);
      }
      // unset post to avoid setting shipping twice
      //unset($_POST['shipping']);
    }
  //}
  // get new order again
  $order = new order;
  // reset shipping_modules to newly set shipping
  $shipping_modules = new shipping($_SESSION['shipping']);
}


// if no shipping method has been selected, redirect the customer to the shipping method selection page
if (!$_SESSION['shipping']) {
  $messageStack->add_session('checkout_shipping', "Please select a shipping method", 'error');
  zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
}

require(DIR_WS_CLASSES . 'order_total.php');
$order_total_modules = new order_total;
$order_total_modules->collect_posts();
$order_total_modules->pre_confirmation_check();
$order_totals = $order_total_modules->process();

// load the selected payment module
require(DIR_WS_CLASSES . 'payment.php');
// BEGIN REWARDS POINTS
// if credit does not cover order total or isn't selected
if ($_SESSION['credit_covers'] != true) {
// check that a gift   voucher isn't being used that is larger than the order
  if ($_SESSION['cot_gv'] < $order->info['total']) {
    $credit_covers =   false;
  }
}
// END REWARDS POINTS
if ($credit_covers || $_SESSION['credit_covers'] || $order->info['total'] == 0) {
  $credit_covers = true;
  unset($_SESSION['payment']);
  $_SESSION['payment'] = '';
}

//@debug echo ($credit_covers == true) ? 'TRUE' : 'FALSE';

//BOF saved credit card modifications
list($saved_cc_module, $saved_credit_card_id) = explode('_', $_SESSION['payment']);
if($saved_cc_module == 'savedcard' && is_numeric($saved_credit_card_id)) {
  $_SESSION['payment'] = 'paypalsavedcard';
  $_SESSION['saved_card_id'] = $saved_credit_card_id;

  include_once(DIR_WS_MODULES . 'payment/paypalsavedcard.php');
  $paypalsavedcard = new paypalsavedcard();
}

//EOF saved credt card modifications

//BOF saved card recurring modification
   if(defined('PAYPAL_SAVED_CARD_RECURRING_ENABLED') && PAYPAL_SAVED_CARD_RECURRING_ENABLED == '1') { 

//validation that the user has a card that can be used for recurring payments before buying a subscription product
require_once(DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
$paypalSavedCardRecurring = new paypalSavedCardRecurring();

$subscriptions = $paypalSavedCardRecurring->find_subscription_products_in_order();
if (sizeof($subscriptions) > 0) {
    //the customer cannot buy a subscription with a future start date in the same order as non-subscription products (because the order will be authorized only!)
    if((sizeof($subscriptions) != sizeof($_SESSION['cart']->get_products())) && $paypalSavedCardRecurring->order_contains_future_start_date()) {
        $_SESSION['saved_card_needed'] = true;
        $messageStack->add_session('checkout_payment', ERROR_MIXED_ORDER, 'error');
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
    }
    if($_SESSION['payment'] != 'paypalwpp' && (!isset($_SESSION['saved_card_id']) || !($_SESSION['saved_card_id'] > 0))) {
      $_SESSION['saved_card_id'] = $paypalSavedCardRecurring->get_customers_saved_card($_SESSION['customer_id']);
      if($_SESSION['saved_card_id'] == false && !(strlen($_POST['paypalwpp_cc_number']) > 0)) {//tried to automatically select card, none was found.  No new card has been entered either.
        $_SESSION['saved_card_needed'] = true;
        $messageStack->add_session('checkout_payment', ERROR_NO_SAVED_CARD, 'error');
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'));
      }
    }
} 
}
//EOF saved card recurring modification



$payment_modules = new payment($_SESSION['payment']);
$payment_modules->update_status();

if ( ($_SESSION['payment'] == '' || !is_object(${$_SESSION['payment']}) ) && $credit_covers === FALSE) {
  $messageStack->add_session('checkout_payment', ERROR_NO_PAYMENT_MODULE_SELECTED, 'error');
}

if (is_array($payment_modules->modules)) {
  $payment_modules->pre_confirmation_check();
}

if ($messageStack->size('checkout_payment') > 0) {
  zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
}
//echo $messageStack->size('checkout_payment');
//die('here');

// Stock Check
$flagAnyOutOfStock = false;
$stock_check = array();
if (STOCK_CHECK == 'true') {
  // bof numinix products variants  - check stock
  if( defined('NMX_PRODUCT_VARIANTS_STATUS') && NMX_PRODUCT_VARIANTS_STATUS == 'true' ){
    // get option_id/option_value_id array
    $products_attributes = array();
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
      if( isset($order->products[$i]['attributes']) ){
        foreach( $order->products[$i]['attributes'] as $products_attribute ){
          $products_attributes[$products_attribute['option_id']] = $products_attribute['value_id'];
        }
      } else {
        $products_attributes = '';
      }

      $stockUpdate = zen_get_products_stock($order->products[$i]['id'], $products_attributes);
      $stockAvailable = is_array($stockUpdate) ? $stockUpdate['quantity'] : $stockUpdate;
      if($stockAvailable - $order->products[$i]['qty'] < 0) {
        $flagAnyOutOfStock = true;
        $flagStockCheck = STOCK_MARK_PRODUCT_OUT_OF_STOCK;
      }
    }
  }
  // eof numinix products variants  - check stock
  
  for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
    if ($stock_check[$i] = zen_check_stock($order->products[$i]['id'], $order->products[$i]['qty'])) {
      $flagAnyOutOfStock = true;
    }
  }
  // Out of Stock
  if ( (STOCK_ALLOW_CHECKOUT != 'true') && ($flagAnyOutOfStock == true) ) {
    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL', false));
  }
}

// update customers_referral with $_SESSION['gv_id']
if ($_SESSION['cc_id']) {
  $discount_coupon_query = "SELECT coupon_code
                            FROM " . TABLE_COUPONS . "
                            WHERE coupon_id = :couponID";

  $discount_coupon_query = $db->bindVars($discount_coupon_query, ':couponID', $_SESSION['cc_id'], 'integer');
  $discount_coupon = $db->Execute($discount_coupon_query);

  $customers_referral_query = "SELECT customers_referral
                               FROM " . TABLE_CUSTOMERS . "
                               WHERE customers_id = :customersID";

  $customers_referral_query = $db->bindVars($customers_referral_query, ':customersID', $_SESSION['customer_id'], 'integer');
  $customers_referral = $db->Execute($customers_referral_query);

  // only use discount coupon if set by coupon
  if ($customers_referral->fields['customers_referral'] == '' and CUSTOMERS_REFERRAL_STATUS == 1) {
    $sql = "UPDATE " . TABLE_CUSTOMERS . "
            SET customers_referral = :customersReferral
            WHERE customers_id = :customersID";

    $sql = $db->bindVars($sql, ':customersID', $_SESSION['customer_id'], 'integer');
    $sql = $db->bindVars($sql, ':customersReferral', $discount_coupon->fields['coupon_code'], 'string');
    $db->Execute($sql);
  } else {
    // do not update referral was added before
  }
}

if (isset(${$_SESSION['payment']}->form_action_url)) {
  $form_action_url = ${$_SESSION['payment']}->form_action_url;
} else {
  $form_action_url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', false);
}

// if shipping-edit button should be overridden, do so
$editShippingButtonLink = zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false);  
if (method_exists(${$_SESSION['payment']}, 'alterShippingEditButton')) {
  $theLink = ${$_SESSION['payment']}->alterShippingEditButton();
  if ($theLink) $editShippingButtonLink = $theLink;
}
// deal with billing address edit button
$flagDisablePaymentAddressChange = false;
if (isset(${$_SESSION['payment']}->flagDisablePaymentAddressChange)) {
  $flagDisablePaymentAddressChange = ${$_SESSION['payment']}->flagDisablePaymentAddressChange;
}

// disable shipping address is products virtual
if ($order->content_type == 'virtual') {
  $_SESSION['sendto'] = false;
}

// redirect to update shipping if needed
//if ($redirectOPRC == true) {
  //zen_redirect(zen_href_link(FILENAME_OPRC_CONFIRMATION, '', 'SSL'));
//}

  $temp = $current_page_base;
  $current_page_base = 'lang.' . $current_page_base;
  require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));
  $current_page_base = $temp;
$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
$breadcrumb->add(NAVBAR_TITLE_2);

// This should be last line of the script:
$zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT_CONFIRMATION');
