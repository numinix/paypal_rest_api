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
//
/**
 * Observer class used to handle recurring payments
 *
 */
class savedCreditCardsRecurringObserver extends base
{
  protected function isPayPalDirectModule($moduleCode)
  {
    return in_array(strtolower((string) $moduleCode), array('paypalwpp', 'paypalr'), true);
  }

  function __construct()
  {
    global $zco_notifier;
    $zco_notifier->attach($this, array('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS'));
  }

  function update(&$class, $eventID, $paramsArray)
  {
    global $zco_notifier;
    if (isset($_SESSION['order_number_created']) && $_SESSION['order_number_created'] > 0 && isset($_SESSION['saved_card_id']) && $_SESSION['saved_card_id'] > 0) {
      global $db;

      require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');

      require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'order.php');

      $paypalSavedCardRecurring = new paypalSavedCardRecurring();
      $order = new order($_SESSION['order_number_created']);


      if ($this->isPayPalDirectModule($order->info['payment_module_code']) || $_SESSION['in_cron']) {//subscription already created with PayPal directly, or subscription already exists and this is a payment via the cron.
        return false;

      }

      $order_contains_a_future_start_date = $paypalSavedCardRecurring->order_contains_future_start_date();
      $subscriptions = $paypalSavedCardRecurring->find_subscription_products_in_order();

      foreach ($subscriptions as $products_id => $subscription) {

        $query = $db->execute('SELECT orders_products_id, products_name, products_model FROM ' . TABLE_ORDERS_PRODUCTS . ' WHERE orders_id = ' . $_SESSION['order_number_created'] . ' AND products_prid = "' . $products_id . '"');
        $orders_products_id = $query->fields['orders_products_id'];
        $order_products_name = isset($query->fields['products_name']) ? $query->fields['products_name'] : '';
        $order_products_model = isset($query->fields['products_model']) ? $query->fields['products_model'] : '';

        if (function_exists('nmx_check_domain')) {
          $domain = nmx_check_domain($orders_products_id);
        } else {
          $domain = '';
        }
        
        $saved_card_id = $_SESSION['saved_card_id'];
        $existing_subscription = $paypalSavedCardRecurring->customer_has_subscription($_SESSION['customer_id'], $products_id, $domain);
        $next_billing_date = 0;

        //if user entered a start_date, use that
        if (strtotime($subscription['startdate']) > strtotime('today')) {
          $next_billing_date = date("Y-m-d", strtotime($subscription['startdate']));
        }
        //use end date of last subscription as the start_date, if there is an existing subscription
        elseif ($existing_subscription) {
          $existing_stats = $paypalSavedCardRecurring->get_payment_details($existing_subscription);
          $start_date = $existing_stats['date'];
        } else {
          $start_date = date('Y-m-d'); //default to today
        }

        if (!($next_billing_date > 0) && $order_contains_a_future_start_date) {//no payment is being taken, so schedule the subscription payment for today/the start date.
          $next_billing_date = $start_date;
        } else if (!($next_billing_date > 0)) {//payment is being taken now, so the next payment date needs to be incrimented.
          $next_billing_date = date("Y-m-d", strtotime($start_date . ' +' . (int) $subscription['billingfrequency'] . ' ' . $subscription['billingperiod']));
        }
        $comment = '';

        //BOF NX mod: cancel PayPal direct subscriptions to prevent duplicates
        //todo: this can be built into the paypal recurring module as a setting
        $prepaid_days = $paypalSavedCardRecurring->cancel_subscription_paypalwpp_direct($_SESSION['customer_id'], $products_id, $subscription['domain']);
        if ($prepaid_days > 0) {
          $next_billing_date = date("Y-m-d", strtotime($next_billing_date . ' +' . (int) $prepaid_days . ' days')); //add days before billing again
          $comment = (int) $prepaid_days . ' days transfered from Paypal wpp subscription.  ';
        }
        //EOF NX mod: cancel paypalwpp subscriptions to prevent duplicates

        // BOF::NX-4461::Recurring payments improvement
        $day = date('d', strtotime($next_billing_date));
        if ($day > 28) {
          $next_billing_date = date("Y-m-28", strtotime($next_billing_date));
        }
        // EOF::NX-4461::Recurring payments improvement

        if ($existing_subscription && !$_SESSION['automatic_subscription_order']) {//increase next scheduled payment date because a payment has just been taken
          $paypalSavedCardRecurring->update_payment_info($existing_subscription, array('date' => $next_billing_date, 'comments' => 'Order recieved for existing subscription. Date of next scheduled payment increased.  ' . $comment));
          $paypal_saved_card_recurring_id = $existing_subscription;
        }

        $scheduled_amount = $subscription['amt'] > 0 ? $subscription['amt'] : zen_get_products_actual_price($products_id);
        $currency_code = '';
        if (isset($subscription['currencycode']) && $subscription['currencycode'] !== '') {
          $currency_code = $subscription['currencycode'];
        } elseif (isset($order->info['currency']) && $order->info['currency'] !== '') {
          $currency_code = $order->info['currency'];
        } elseif (isset($_SESSION['currency']) && $_SESSION['currency'] !== '') {
          $currency_code = $_SESSION['currency'];
        }

        $subscription_attributes = array(
          'billingperiod' => isset($subscription['billingperiod']) ? $subscription['billingperiod'] : null,
          'billingfrequency' => isset($subscription['billingfrequency']) ? $subscription['billingfrequency'] : null,
          'totalbillingcycles' => isset($subscription['totalbillingcycles']) ? $subscription['totalbillingcycles'] : null,
          'startdate' => isset($subscription['startdate']) ? $subscription['startdate'] : null,
          'quantity' => isset($subscription['quantity']) ? $subscription['quantity'] : null,
          'currencycode' => $currency_code,
          'domain' => $domain,
          'amount' => $scheduled_amount,
        );

        $metadata = array(
          'products_id' => $products_id,
          'products_name' => $order_products_name !== '' ? $order_products_name : (isset($subscription['desc']) ? $subscription['desc'] : ''),
          'products_model' => $order_products_model,
          'currency_code' => $currency_code,
          'billing_period' => isset($subscription['billingperiod']) ? $subscription['billingperiod'] : null,
          'billing_frequency' => isset($subscription['billingfrequency']) ? $subscription['billingfrequency'] : null,
          'total_billing_cycles' => isset($subscription['totalbillingcycles']) ? $subscription['totalbillingcycles'] : null,
          'domain' => $domain,
          'subscription_attributes' => $subscription_attributes,
        );

        if ($existing_subscription && !$_SESSION['automatic_subscription_order']) {//increase next scheduled payment date because a payment has just been taken
          $paypalSavedCardRecurring->update_payment_info($existing_subscription, array('date' => $next_billing_date, 'comments'=> 'Order recieved for existing subscription. Date of next scheduled payment increased.  ' . $comment));
          $paypal_saved_card_recurring_id = $existing_subscription;
        } elseif (strpos($subscription['billingfrequency'], 'Lifetime') === false) { //schedule next payment if it's not a lifetime license
          $paypal_saved_card_recurring_id = $paypalSavedCardRecurring->schedule_payment($scheduled_amount, $next_billing_date, $saved_card_id, $orders_products_id, 'Scheduled at time of order.  ' . $comment, $metadata);
        }

        //Add user to pricing group, except for start dates in the future. This will do nothing if the product is not a plan.
        if (!(strtotime($subscription['startdate']) > strtotime('today'))) {
          $payment_details = $paypalSavedCardRecurring->get_payment_details($paypal_saved_card_recurring_id);
          $paypalSavedCardRecurring->create_group_pricing($products_id, $_SESSION['customer_id']);
          // this is already handled by a different observer which calls nmx_log_license
          /*
          if (function_exists('nmx_log_license')) {
              nmx_log_license($_SESSION['order_number_created']);
          } else {
              $paypalSavedCardRecurring->add_licence($_SESSION['order_number_created'], $products_id, $next_billing_date, $subscription['domain'], $payment_details['products_name'], $payment_details['products_model']);
          }
          */
        }
      }//end foreach subscription

      // BOF NX mod: a customer can only have one plan at a time
      $subscription = $paypalSavedCardRecurring->get_payment_details($paypal_saved_card_recurring_id);
      if (zen_product_in_category($subscription['products_id'], CATEGORY_ID_PLANS) || zen_product_in_category($subscription['products_id'], CATEGORY_ID_CUSTOM_PLANS)) {// 
        $paypalSavedCardRecurring->cancel_other_subscriptions_in_category($subscription['customers_id'], $paypal_saved_card_recurring_id, array(CATEGORY_ID_PLANS, CATEGORY_ID_CUSTOM_PLANS));
      }
      // EOF NX mod: a customer can only have one plan at a time
    }
    $zco_notifier->notify('NOTIFY_RECURRING_ORDER_LOGGED');
  }
}
