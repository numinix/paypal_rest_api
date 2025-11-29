<?php
/**
 * Header code file for the saved credit card page
 *
 * @package page
 * @copyright Copyright 2003-2011 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: header_php.php 18695 2011-05-04 05:24:19Z drbyte $
 */
// This should be first line of the script:
//$zco_notifier->notify('NOTIFY_HEADER_START_ADDRESS_BOOK_PROCESS');


if (!$_SESSION['customer_id']) {
  $_SESSION['navigation']->set_snapshot();
  zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$currentCustomerId = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
if (!isset($_SESSION['saved_card_subscription_tokens']) || !is_array($_SESSION['saved_card_subscription_tokens'])) {
  $_SESSION['saved_card_subscription_tokens'] = array();
} else {
  $subscriptionTokenExpiry = time() - 3600;
  foreach ($_SESSION['saved_card_subscription_tokens'] as $tokenKey => $tokenData) {
    $tokenCustomerId = isset($tokenData['customer_id']) ? (int) $tokenData['customer_id'] : 0;
    $tokenTimestamp = isset($tokenData['created_at']) ? (int) $tokenData['created_at'] : 0;
    if ($tokenCustomerId !== $currentCustomerId || ($tokenTimestamp > 0 && $tokenTimestamp < $subscriptionTokenExpiry)) {
      unset($_SESSION['saved_card_subscription_tokens'][$tokenKey]);
    }
  }
}

require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_curl.php');
require_once(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
require_once(DIR_WS_MODULES . 'payment/paypalsavedcard.php');

if (!function_exists('zen_paypal_rest_call_method')) {
  function zen_paypal_rest_call_method($client, $method, $arguments = array()) {
    $candidates = array($method);
    $candidates[] = lcfirst($method);
    $candidates[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
    foreach (array_unique($candidates) as $candidate) {
      if (method_exists($client, $candidate)) {
        return call_user_func_array(array($client, $candidate), $arguments);
      }
    }
    throw new BadMethodCallException('Method ' . $method . ' is not available on the PayPal REST client');
  }
}

if (!function_exists('zen_paypal_update_saved_card_rest')) {
  function zen_paypal_update_saved_card_rest($paypalsavedcard, $tokenId, array $cardPayload = array()) {
    if (!is_object($paypalsavedcard)) {
      return array('success' => false, 'message' => 'Invalid PayPal client instance', 'retry' => true);
    }
    if (!(is_string($tokenId) && strlen($tokenId) > 0)) {
      return array('success' => false, 'message' => 'Missing stored credential identifier', 'retry' => true);
    }
    if (!isset($paypalsavedcard->PayPalRestful) || !$paypalsavedcard->PayPalRestful) {
      if (!$paypalsavedcard->initiate_paypalr()) {
        return array('success' => false, 'message' => 'PayPal REST client unavailable', 'retry' => true);
      }
    }
    $client = $paypalsavedcard->PayPalRestful;
    $payload = array('payment_source' => array('token' => array('id' => $tokenId)));
    if (!empty($cardPayload)) {
      $payload['payment_source']['card'] = $cardPayload;
    }
    $methods = array(
      array('updatePaymentToken', array($tokenId, $payload)),
      array('updateToken', array($tokenId, $payload)),
      array('patchPaymentToken', array($tokenId, $payload)),
      array('patchToken', array($tokenId, $payload)),
      array('updateBillingAgreement', array($tokenId, $payload)),
      array('updatePaymentSource', array($tokenId, $payload))
    );
    $lastException = null;
    foreach ($methods as $entry) {
      list($method, $arguments) = $entry;
      try {
        $result = zen_paypal_rest_call_method($client, $method, $arguments);
        if ($result !== null) {
          return array('success' => true);
        }
      }
      catch (BadMethodCallException $e) {
        $lastException = $e;
        continue;
      }
      catch (Exception $e) {
        return array('success' => false, 'message' => $e->getMessage());
      }
    }
    if ($lastException instanceof BadMethodCallException) {
      return array('success' => false, 'message' => $lastException->getMessage(), 'retry' => true);
    }
    return array('success' => false, 'message' => 'Unable to update PayPal REST record.', 'retry' => true);
  }
}

$paypalsavedcard = new paypalsavedcard();

//(MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false)
$PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);


/*$PayPalConfig = array(
                'Sandbox' => (true),
                'APIUsername' => 'kristin_api1.numinix.com',
                'APIPassword' => '1407891473',
                'APISignature' => 'AFcWxV21C7fd0v3bYYYRCpSSRl31A.64wjvZo3iLG2NO0.3H-0RB8RXP'
        );
*/
require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));

/**
 * Process deletes
 */

if (isset($_GET['delete_confirm']) && is_numeric($_GET['delete_confirm']))  {
  $delete_card_id = zen_db_prepare_input($_GET['delete_confirm']);
  $sql = "SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND is_deleted = '0' AND saved_credit_card_id = " . $delete_card_id;
  $result = $db->Execute($sql);
  $saved_credit_cards = array();
  $delete_card = $result->fields;

}

if (isset($_GET['delete']) && is_numeric($_GET['delete']))  {
  // bof modified for NX-1519 : Debug Subscriptions Cancelling if No Default Card Set
  $sql = 'SELECT saved_credit_card_id FROM ' . TABLE_SAVED_CREDIT_CARDS . ' WHERE saved_credit_card_id != :credit_card_id and is_deleted = \'0\' and customers_id = ' . $_SESSION['customer_id'];
  $sql = $db->bindVars($sql, ':credit_card_id', $_GET['delete'], 'integer');
  $saved_credit_cards_id = $db->Execute($sql);

  if($saved_credit_cards_id->RecordCount() == 0){ //only one card
    $sql = 'SELECT saved_credit_card_id FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE status = \'scheduled\' AND saved_credit_card_id = ' . (int) $_GET['delete']; //check if there's an active sub with this card
    $check_credit_card_with_subs = $db->Execute($sql);
    if($check_credit_card_with_subs->RecordCount() > 0){
        $messageStack->add_session('saved_credit_cards', CREATE_NEW_CARD_BEFORE_DELETE, 'error');
        zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
    }
  }
  // eof modified for NX-1519 : Debug Subscriptions Cancelling if No Default Card Set

  $reassigned_card_message = $paypalsavedcard->delete_card(zen_db_prepare_input($_GET['delete']), $_SESSION['customer_id']);  
  $zco_notifier->notify('NOTIFY_HEADER_CREDIT_CARD_DELETION_DONE');

  //BOF saved card recurring mod. Handling if a card linked to a subscription is deleted.
  if(!class_exists('paypalSavedCardRecurring')) {
    require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
  }
  $paypalSavedCardRecurring = new paypalSavedCardRecurring();
  $reassigned_card_message = $paypalSavedCardRecurring->card_was_deleted(zen_db_prepare_input($_GET['delete']), $_SESSION['customer_id']);
  //EOF saved card recurring mod

  $messageStack->add_session('saved_credit_cards', SUCCESS_SAVED_CREDIT_CARD_DELETED . ' ' . $reassigned_card_message, 'success');
}
 
/*
* Process set primary card
* 
*/
if (isset($_GET['setprimary']) && is_numeric($_GET['setprimary'])) {

  $set_primary_id = zen_db_prepare_input($_GET['setprimary']);
  $sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . " SET is_primary = '0' WHERE customers_id = " . (int)$_SESSION['customer_id'];
  $db->execute($sql);
 
  $sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . " SET is_primary = '1' WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND saved_credit_card_id = " . $set_primary_id;
  $db->execute($sql);

  $zco_notifier->notify('NOTIFY_HEADER_CREDIT_CARD_PRIMARY_UPDATED', array('customers_id' => $_SESSION['customer_id']));
  
} 

/*
* Process get card details for editing
* 
*/
if ((isset($_GET['edit']) && is_numeric($_GET['edit'])) || (isset($_POST['existing_address_id']) && $_POST['existing_address_id'] > 0)) {
  $edit_card_id = is_numeric($_GET['edit']) ? zen_db_prepare_input($_GET['edit']) : $_POST['existing_address_id'];
  $sql = "SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND is_deleted = '0' AND saved_credit_card_id = " . $edit_card_id;
  $result = $db->Execute($sql);
  $saved_credit_cards = array();
  $edit_card = $result->fields; 
}

/**
 * Save form
 */

if (isset($_POST['action'])) {
   
  $saved_credit_card_id = zen_db_prepare_input($_POST['saved_credit_card_id']); 
  $fullname = zen_db_prepare_input($_POST['fullname']);
  $cardnumber = zen_db_prepare_input($_POST['cardnumber']);
  $cvv = zen_db_prepare_input($_POST['cvv']);
  $expirydate = zen_db_prepare_input($_POST['monthexpiry']) . substr(zen_db_prepare_input($_POST['yearexpiry']), -2);
  $paymenttype = zen_db_prepare_input($_POST['paymenttype']);
  $primary = zen_db_prepare_input($_POST['primary']);
  $primary = ($primary == 'on') ? '1' : '0';

  $existing_card = null;
  if ($saved_credit_card_id > 0) {
    $existing_card_query = $db->Execute("SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND saved_credit_card_id = " . (int)$saved_credit_card_id . " LIMIT 1;");
    if ($existing_card_query->RecordCount() > 0) {
      $existing_card = $existing_card_query->fields;
    }
  }

  if($_POST['address_book_id'] == $_POST['existing_address_id'] || $_POST['address_book_id'] == 0) { //updating existing address or adding new address

  //country is stored differently in orders table, the input form, and paypal.  Find different formats here.
      $country_id =  zen_db_prepare_input($_POST['zone_country_id']);
       if(is_numeric($country_id)) {
         $country_name = zen_get_country_name($country_id);
         $country_info = zen_get_countries($country_id);
         $country_code = $country_info['countries_iso_code_2'];
       }
       else {
         $country_name = '';
      }

     $order_address_update = array(
         'billing_street_address' => zen_db_prepare_input($_POST['street_address']),
         'billing_suburb' => zen_db_prepare_input($_POST['suburb']),
         'billing_city' => zen_db_prepare_input($_POST['city']),
         'billing_postcode' => zen_db_prepare_input($_POST['postcode']),
         'billing_state' => zen_db_prepare_input($_POST['state']),
         'billing_zone_id' =>zen_db_prepare_input($_POST['zone_id']), 
         'billing_country' => $country_name
       );

     $paypal_address_info = $paypalsavedcard->make_address($order_address_update['billing_street_address'], $order_address_update['billing_suburb'], $order_address_update['billing_city'], $order_address_update['billing_postcode'], $order_address_update['billing_state'], $order_address_update['billing_zone_id'], $country_code);
     }
     else { //use saved address
       $sql = 'SELECT * FROM ' . TABLE_ADDRESS_BOOK . ' WHERE address_book_id = ' . $_POST['address_book_id']  . ' AND customers_id = ' . (int)$_SESSION['customer_id'];
       $result = $db->Execute($sql);
       $saved_address = $result->fields;

       $country_info = zen_get_countries($saved_address['entry_country_id']);
       $country_code = $country_info['countries_iso_code_2'];

      $paypal_address_info = $paypalsavedcard->make_address($saved_address['entry_street_address'], $saved_address['entry_suburb'], $saved_address['entry_city'], $saved_address['entry_postcode'], $saved_address['entry_state'], $saved_address['entry_zone_id'], $country_code);
    }

    $rest_reuse_token = '';
    if (is_array($existing_card) && isset($existing_card['api_type']) && strtolower($existing_card['api_type']) == 'paypalr') {
      $stored_token_id = '';
      if (isset($existing_card['paypal_stored_credential_id']) && strlen($existing_card['paypal_stored_credential_id']) > 0) {
        $stored_token_id = $existing_card['paypal_stored_credential_id'];
      } elseif (isset($existing_card['paypal_transaction_id']) && strlen($existing_card['paypal_transaction_id']) > 0) {
        $stored_token_id = $existing_card['paypal_transaction_id'];
      }
      if (strlen($stored_token_id) > 0) {
        $cardPayload = array();
        $sanitizedNumber = preg_replace('/[^0-9]/', '', $cardnumber);
        if (strlen($sanitizedNumber) > 0) {
          $cardPayload['number'] = $sanitizedNumber;
        }
        if (strlen($fullname) > 0) {
          $cardPayload['name'] = trim($fullname);
        }
        if (strlen($paymenttype) > 0) {
          $cardPayload['brand'] = strtoupper($paymenttype);
        }
        if (strlen($cvv) > 0) {
          $cardPayload['security_code'] = $cvv;
        }
        if (strlen($expirydate) >= 4) {
          $expMonth = substr($expirydate, 0, 2);
          $expYear = substr($expirydate, -2);
          $cardPayload['expiry'] = '20' . $expYear . '-' . $expMonth;
        }
        if (is_array($paypal_address_info)) {
          $billing = array('address_line_1' => $paypal_address_info['street'], 'postal_code' => $paypal_address_info['zip'], 'country_code' => $paypal_address_info['countrycode']);
          if (isset($paypal_address_info['street2']) && strlen($paypal_address_info['street2']) > 0) {
            $billing['address_line_2'] = $paypal_address_info['street2'];
          }
          if (isset($paypal_address_info['city']) && strlen($paypal_address_info['city']) > 0) {
            $billing['admin_area_2'] = $paypal_address_info['city'];
          }
          if (isset($paypal_address_info['state']) && strlen($paypal_address_info['state']) > 0) {
            $billing['admin_area_1'] = $paypal_address_info['state'];
          }
          $cardPayload['billing_address'] = $billing;
        }
        $rest_update = zen_paypal_update_saved_card_rest($paypalsavedcard, $stored_token_id, $cardPayload);
        if (empty($rest_update['success'])) {
          $error_message = isset($rest_update['message']) && strlen($rest_update['message']) > 0 ? $rest_update['message'] : 'An unknown error occurred.';
          $messageStack->add_session('saved_credit_cards', 'Your saved card could not be updated with PayPal:<br />' . $error_message, 'error');
          zen_redirect(zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));
        }
        $rest_reuse_token = $stored_token_id;
      }
    }

    $rest_module_key = '';
    $paymentModulesWasSet = isset($payment_modules);
    if ($paymentModulesWasSet) {
      $originalPaymentModules = $payment_modules;
    }
    if (strlen($rest_reuse_token) > 0) {
      $rest_module_key = '__paypal_savedcard_rest_update__';
      $payment_modules = new stdClass();
      $payment_modules->selected_module = $rest_module_key;
      $GLOBALS[$rest_module_key] = new stdClass();
      $GLOBALS[$rest_module_key]->transaction_id = $rest_reuse_token;
    }

      $new_credit_card_id = $paypalsavedcard->add_saved_card($cardnumber, $cvv, $expirydate, $fullname, $paymenttype, 1, $primary, $saved_credit_card_id, $paypal_address_info); //will either update or add the card

    if (strlen($rest_module_key) > 0) {
      unset($GLOBALS[$rest_module_key]);
      if ($paymentModulesWasSet) {
        $payment_modules = $originalPaymentModules;
      } else {
        unset($payment_modules);
      }
    }

  if($new_credit_card_id > 0) {

    if($_POST['address_book_id'] == $_POST['existing_address_id'] || $_POST['address_book_id'] == 0) { //updating existing address or adding new address
       $sql_data_array= array(
          array('fieldName'=>'entry_street_address', 'value'=>$order_address_update['billing_street_address'], 'type'=>'string'),
          array('fieldName'=>'entry_suburb', 'value'=>$order_address_update['billing_suburb'], 'type'=>'string'),
          array('fieldName'=>'entry_city', 'value'=>$order_address_update['billing_city'], 'type'=>'string'),
          array('fieldName'=>'entry_postcode', 'value'=>$order_address_update['billing_postcode'], 'type'=>'string'),
          array('fieldName'=>'entry_state', 'value'=>$order_address_update['billing_state'], 'type'=>'string'),
          array('fieldName'=>'entry_zone_id', 'value'=>$order_address_update['billing_zone_id'], 'type'=>'string'),
          array('fieldName'=>'entry_country_id', 'value'=>$country_id, 'type'=>'string'),
          array('fieldName'=>'entry_telephone', 'value'=>$telephone, 'type'=>'string'),
          array('fieldName'=>'address_title', 'value'=>'Billing', 'type'=>'string'),
          array('fieldName'=>'customers_id', 'value'=>(int)$_SESSION['customer_id'], 'type'=>'integer')
        );

      if($_POST['address_book_id'] > 0) { //update existing address
        $address_book_id = $_POST['address_book_id'];
        $where = 'address_book_id = ' . $_POST['address_book_id'] . ' AND customers_id = ' . (int)$_SESSION['customer_id'];
        $db->perform(TABLE_ADDRESS_BOOK, $sql_data_array, 'update', $where);
      }
      else {  //add new address
        $db->perform(TABLE_ADDRESS_BOOK, $sql_data_array);
        $address_book_id = $db->insert_ID();
      }
    }
    else {
      $address_book_id = $_POST['address_book_id']; //use address selected by user
    }

    $sql = 'UPDATE ' . TABLE_SAVED_CREDIT_CARDS . ' SET address_id = ' . $address_book_id . ' WHERE saved_credit_card_id = ' . $new_credit_card_id;
    $db->execute($sql);

    $messageStack->add_session('saved_credit_cards', $saved_credit_card_id > 0 ? NOTIFY_HEADER_CREDIT_CARD_EDITED : NOTIFY_HEADER_CREDIT_CARD_ADDED, 'success');

    if ($saved_credit_card_id <= 0) {
      $postedSubscriptionToken = isset($_POST['subscription_card_token']) ? trim($_POST['subscription_card_token']) : '';
      if ($postedSubscriptionToken !== '' && isset($_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken])) {
        $tokenData = $_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken];
        $tokenSubscriptionId = isset($tokenData['saved_card_recurring_id']) ? (int) $tokenData['saved_card_recurring_id'] : 0;
        $tokenCustomerId = isset($tokenData['customer_id']) ? (int) $tokenData['customer_id'] : 0;
        $tokenTimestamp = isset($tokenData['created_at']) ? (int) $tokenData['created_at'] : 0;
        $tokenExpiry = time() - 3600;

        if ($tokenSubscriptionId > 0 && $tokenCustomerId === $currentCustomerId && ($tokenTimestamp === 0 || $tokenTimestamp >= $tokenExpiry)) {
          if (!class_exists('paypalSavedCardRecurring')) {
            require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
          }
          $subscriptionUpdater = new paypalSavedCardRecurring();
          $subscriptionDetails = $subscriptionUpdater->get_payment_details($tokenSubscriptionId);
          $subscriptionOwnerId = 0;
          if (is_array($subscriptionDetails)) {
            if (isset($subscriptionDetails['saved_card_customer_id']) && (int) $subscriptionDetails['saved_card_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscriptionDetails['saved_card_customer_id'];
            } elseif (isset($subscriptionDetails['subscription_customer_id']) && (int) $subscriptionDetails['subscription_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscriptionDetails['subscription_customer_id'];
            } elseif (isset($subscriptionDetails['customers_id'])) {
              $subscriptionOwnerId = (int) $subscriptionDetails['customers_id'];
            }
          }

          if ($subscriptionOwnerId === $currentCustomerId) {
            $subscriptionUpdater->update_payment_info(
              $tokenSubscriptionId,
              array(
                'saved_credit_card_id' => $new_credit_card_id,
                'comments' => '  Card updated by customer after adding a new saved card.  '
              )
            );
            $messageStack->add_session('my_subscriptions', 'Your subscription payment method has been updated.', 'success');
            $messageStack->add_session('saved_credit_cards', 'Your new saved card has been applied to your subscription.', 'success');
            unset($_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken]);
            zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
          }
        }

        unset($_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken]);
      }
    }
    elseif (isset($_POST['subscription_card_token'])) {
      $postedSubscriptionToken = trim($_POST['subscription_card_token']);
      if ($postedSubscriptionToken !== '' && isset($_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken])) {
        unset($_SESSION['saved_card_subscription_tokens'][$postedSubscriptionToken]);
      }
    }
  }
  else {
    $messageStack->add_session('saved_credit_cards', NOTIFY_HEADER_CREDIT_CARD_FAILED, 'error');
  }
}

/*
* Get customers credit cards for displaying on page
*/

$sql = "SELECT scc.saved_credit_card_id, scc.customers_id, scc.type, scc.last_digits, scc.name_on_card, scc.expiry, scc.is_primary, scc.is_visible, scc.address_id, scc.is_deleted,
               ab.address_book_id, ab.entry_firstname as firstname, ab.entry_lastname as lastname,
               ab.entry_company as company, ab.entry_street_address as street_address,
               ab.entry_suburb as suburb, ab.entry_city as city, ab.entry_telephone as telephone, ab.entry_postcode as postcode, ab.entry_state as state, ab.entry_zone_id as zone_id, ab.entry_country_id as country_id
    FROM " . TABLE_SAVED_CREDIT_CARDS . " scc
    LEFT JOIN " . TABLE_ADDRESS_BOOK . " ab ON (scc.address_id = ab.address_book_id)
    WHERE scc.customers_id = " . (int)$_SESSION['customer_id'] . "
    AND scc.is_deleted = '0' AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) > CURDATE()";
$result = $db->Execute($sql);
$saved_credit_cards = array();

while(!$result->EOF) {
  $format_id = zen_get_address_format_id($result->fields['country_id']);
  $saved_credit_cards[] = array(
    'saved_credit_card_id'=>$result->fields['saved_credit_card_id'],
    'type'=>$result->fields['type'],
    'last_digits'=>$result->fields['last_digits'],
    'name_on_card'=>$result->fields['name_on_card'],
    'expiry'=>$result->fields['expiry'],
    'is_primary'=>$result->fields['is_primary'],
    'format_id'=>$format_id,
    'address'=>$result->fields);
  $result->moveNext();
}


/*
*  Get customers addresses for displaying on page
*/

$sql = 'SELECT * FROM ' . TABLE_ADDRESS_BOOK . ' WHERE customers_id = ' . (int)$_SESSION['customer_id'];
$result = $db->Execute($sql);
$saved_addresses = array();
$saved_addresses[] = array('id'=>0, 'text'=>'Enter new address');

while(!$result->EOF) {
  $saved_addresses[] = array('id'=>$result->fields['address_book_id'], 'text'=>$result->fields['entry_street_address']);
  $result->moveNext();
}

$addresses_query = "SELECT address_book_id, entry_firstname as firstname, entry_lastname as lastname,
                           entry_company as company, entry_street_address as street_address,
                           entry_suburb as suburb, entry_city as city, entry_postcode as postcode,
                           entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id, entry_telephone as telephone
                    FROM   " . TABLE_ADDRESS_BOOK . "
                    WHERE  customers_id = :customersID
                    ORDER BY firstname, lastname";

$addresses_query = $db->bindVars($addresses_query, ':customersID', $_SESSION['customer_id'], 'integer');
$addresses = $db->Execute($addresses_query);

$addressArray = array();
while (!$addresses->EOF) {
  $format_id = zen_get_address_format_id($addresses->fields['country_id']);

  $addressArray[] = array('firstname'=>$addresses->fields['firstname'],
  'lastname'=>$addresses->fields['lastname'],
  'address_book_id'=>$addresses->fields['address_book_id'],
  'format_id'=>$format_id,
  'address'=>$addresses->fields);
  $addresses->MoveNext();
}

/*
* Get address associated with this saved card
*/

if($edit_card['address_id'] > 0) {
  $addresses_query = 'SELECT
                     * 
                    FROM ' . TABLE_ADDRESS_BOOK . ' WHERE address_book_id = ' . $edit_card['address_id'];

//   $addresses_query = $db->bindVars($addresses_query, ':customersID', $_SESSION['customer_id'], 'integer');
   $entry = $db->Execute($addresses_query);
}
if ($process == false) {
  $selected_country = $entry->fields['entry_country_id'];
} else {
  $selected_country = (isset($_POST['zone_country_id']) && $_POST['zone_country_id'] != '') ? $country : SHOW_CREATE_ACCOUNT_DEFAULT_COUNTRY;
  $entry->fields['entry_country_id'] = $selected_country;
}
$flag_show_pulldown_states = ((($process == true || $entry_state_has_zones == true) && $zone_name == '') || ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true' || $error_state_input) ? true : false;
$state = ($flag_show_pulldown_states && $state != FALSE) ? $state : $zone_name;
$state_field_label = ($flag_show_pulldown_states) ? '' : ENTRY_STATE;

$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2, zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'));

// if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
//   $breadcrumb->add(NAVBAR_TITLE_SAVED_CREDIT_CARD_MODIFY_ENTRY);
// } elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
//   $breadcrumb->add(NAVBAR_TITLE_SAVED_CREDIT_CARD_DELETE_ENTRY);
// }elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
//   $breadcrumb->add(HEADING_TITLE_ADD_NEW_CARD);
// } 
// else {
//   $breadcrumb->add(HEADING_TITLE);
// }

// This should be last line of the script:
//$zco_notifier->notify('NOTIFY_HEADER_END_ADDRESS_BOOK_PROCESS');
