<?php
// checkout_payment_discounts
require(DIR_WS_CLASSES . 'order.php');
$order = new order;
// load the selected shipping module
require_once(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping($_SESSION['shipping']);

require_once(DIR_WS_CLASSES . 'order_total.php');
$order_total_modules = new order_total;
$order_total_modules->collect_posts();
$order_total_modules->pre_confirmation_check();

// load the selected payment module
require_once(DIR_WS_CLASSES . 'payment.php');
$_SESSION['payment'] = $_POST['payment'];
if ($credit_covers) {
  unset($_SESSION['payment']);
  $_SESSION['payment'] = '';
}

//@debug echo ($credit_covers == true) ? 'TRUE' : 'FALSE';

$payment_modules = new payment($_SESSION['payment']);
$payment_modules->update_status();

// Stores should add some post collection below for older browsers that require page reload, example for PayPal WPP below.  
// Remember to set default values in the PayPalDP module to use the sessions created!
//$_SESSION['paypalwpp_cc_number'] = $_POST['paypalwpp_cc_number'];
//$_SESSION['paypalwpp_cc_expires_month'] = $_POST['paypalwpp_cc_expires_month'];
//$_SESSION['paypalwpp_cc_expires_year'] = $_POST['paypalwpp_cc_expires_year'];
//$_SESSION['paypalwpp_cc_checkcode'] = $_POST['paypalwpp_cc_checkcode'];  

if ($messageStack->size('checkout_payment') > 0) {
  zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', false));
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