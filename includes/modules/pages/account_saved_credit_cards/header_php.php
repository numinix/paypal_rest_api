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
$zco_notifier->notify('NOTIFY_HEADER_START_ACCOUNT_SAVED_CREDIT_CARDS'); 


if (!$_SESSION['customer_id']) {
  $_SESSION['navigation']->set_snapshot();
  zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_curl.php');
require_once(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
require_once(DIR_WS_MODULES . 'payment/paypalsavedcard.php');

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
  $_SESSION['invalid_count'] = isset($_SESSION['invalid_count']) ? $_SESSION['invalid_count'] : 0; // bof modified for STRIN-1374
  $saved_credit_card_id = zen_db_prepare_input($_POST['saved_credit_card_id']); 
  $fullname = zen_db_prepare_input($_POST['fullname']);
  $cardnumber = zen_db_prepare_input($_POST['cardnumber']);
  $cvv = zen_db_prepare_input(str_replace(' ', '', $_POST['cvv']));
  $expirydate = zen_db_prepare_input($_POST['monthexpiry']) . substr(zen_db_prepare_input($_POST['yearexpiry']), -2);
  $paymenttype = zen_db_prepare_input($_POST['paymenttype']);
  $primary = zen_db_prepare_input($_POST['primary']); 
  $primary = ($primary == 'on') ? '1' : '0';
  
  if (!class_exists('cc_validation')) {
      include_once (DIR_WS_CLASSES . 'cc_validation.php');
  }
  if (!is_object($cc_validation)) {
      $cc_validation = new cc_validation();
  }
  $validCard = $cc_validation->validate($_POST['cardnumber'], $_POST['monthexpiry'], $_POST['yearexpiry'], null, null); // Switch and Solo needs start date
  
  if($validCard){
      $cardnumber = $cc_validation->cc_number;
      $expirydate = $cc_validation->cc_expiry_month . substr($cc_validation->cc_expiry_year, - 2);
      $paymenttype = $cc_validation->cc_type;

      if($_POST['address_book_id'] != $_POST['existing_address_id'] || $_POST['address_book_id'] == 0) { //updating existing address or adding new address
    
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
          //bof modified for STRIN-1516 :: Saved card updating wipes out address
          if(isset($_POST['company']))
            $company = $_POST['company'];
          else
            $company = '';
          //eof modified for STRIN-1516 :: Saved card updating wipes out address
         // bof modified for STRIN-710 : Add Address Line 3
         $order_address_update = array(
          //bof modified for STRIN-1516 :: Saved card updating wipes out address
             'billing_firstname' => zen_db_prepare_input($_POST['firstname']),
             'billing_lastname' => zen_db_prepare_input($_POST['lastname']),
             'billing_company' => $company,
             //eof modified for STRIN-1516 :: Saved card updating wipes out address
             'billing_street_address' => zen_db_prepare_input($_POST['street_address']),
             'billing_suburb' => zen_db_prepare_input($_POST['suburb']),
             'billing_suburb2' => zen_db_prepare_input($_POST['suburb2']),
             'billing_city' => zen_db_prepare_input($_POST['city']),
             'billing_postcode' => zen_db_prepare_input($_POST['postcode']),
             'billing_state' => zen_db_prepare_input($_POST['state']),
             'billing_zone_id' =>zen_db_prepare_input($_POST['zone_id']), 
             'billing_country' => $country_name
           );
    
         $paypal_address_info = $paypalsavedcard->make_address($order_address_update['billing_street_address'], $order_address_update['billing_suburb'], $order_address_update['billing_suburb2'], $order_address_update['billing_city'], $order_address_update['billing_postcode'], $order_address_update['billing_state'], $order_address_update['billing_zone_id'], $country_code);
         }
         else { //use saved address
           $sql = 'SELECT * FROM ' . TABLE_ADDRESS_BOOK . ' WHERE address_book_id = ' . $_POST['address_book_id']  . ' AND customers_id = ' . (int)$_SESSION['customer_id'];
           $result = $db->Execute($sql);
           $saved_address = $result->fields;
    
           $country_info = zen_get_countries($saved_address['entry_country_id']);
           $country_code = $country_info['countries_iso_code_2'];
    
           $paypal_address_info = $paypalsavedcard->make_address($saved_address['entry_street_address'], $saved_address['entry_suburb'], $saved_address['entry_suburb2'], $saved_address['entry_city'], $saved_address['entry_postcode'], $saved_address['entry_state'], $saved_address['entry_zone_id'], $country_code);
         }
    
          $new_credit_card_id = $paypalsavedcard->add_saved_card($cardnumber, $cvv, $expirydate, $fullname, $paymenttype, 1, $primary, $saved_credit_card_id, $paypal_address_info); //will either update or add the card
    
      if($new_credit_card_id > 0) {
    
        if($_POST['address_book_id'] == $_POST['existing_address_id'] || $_POST['address_book_id'] == 0) { //updating existing address or adding new address
           $sql_data_array= array(
            //bof modified for STRIN-1516 :: Saved card updating wipes out address
              array('fieldName'=>'entry_firstname', 'value'=>$order_address_update['billing_firstname'], 'type'=>'string'),
              array('fieldName'=>'entry_lastname', 'value'=>$order_address_update['billing_lastname'], 'type'=>'string'),
              array('fieldName'=>'entry_company', 'value'=>$order_address_update['billing_company'], 'type'=>'string'),
              //eof modified for STRIN-1516 :: Saved card updating wipes out address
              array('fieldName'=>'entry_street_address', 'value'=>$order_address_update['billing_street_address'], 'type'=>'string'),
              array('fieldName'=>'entry_suburb', 'value'=>$order_address_update['billing_suburb'], 'type'=>'string'),
              array('fieldName'=>'entry_suburb2', 'value'=>$order_address_update['billing_suburb2'], 'type'=>'string'),
              array('fieldName'=>'entry_city', 'value'=>$order_address_update['billing_city'], 'type'=>'string'),
              array('fieldName'=>'entry_postcode', 'value'=>$order_address_update['billing_postcode'], 'type'=>'string'),
              array('fieldName'=>'entry_state', 'value'=>$order_address_update['billing_state'], 'type'=>'string'),
              array('fieldName'=>'entry_zone_id', 'value'=>$order_address_update['billing_zone_id'], 'type'=>'string'),
              array('fieldName'=>'entry_country_id', 'value'=>$country_id, 'type'=>'string'),
              array('fieldName'=>'address_title', 'value'=>'Billing', 'type'=>'string'),
              array('fieldName'=>'customers_id', 'value'=>(int)$_SESSION['customer_id'], 'type'=>'integer')
            );
            // eof modified for STRIN-710 : Add Address Line 3
    
          if($_POST['address_book_id'] > 0) { //update existing address
            $address_book_id = $_POST['address_book_id'];
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
        $_SESSION['invalid_count'] = 0; // bof modified for STRIN-1374
        $messageStack->add_session('saved_credit_cards', $saved_credit_card_id > 0 ? NOTIFY_HEADER_CREDIT_CARD_EDITED : NOTIFY_HEADER_CREDIT_CARD_ADDED, 'success');
      }
      else {
        $_SESSION['invalid_count'] += 1; // bof modified for STRIN-1374
        $messageStack->add_session('saved_credit_cards', NOTIFY_HEADER_CREDIT_CARD_FAILED, 'error');
      }
  }
  else {
    $_SESSION['invalid_count'] += 1; // bof modified for STRIN-1374
    $messageStack->add_session('saved_credit_cards', NOTIFY_HEADER_CREDIT_CARD_FAILED, 'error');
  }
  // bof modified for STRIN-1374
  if ($_SESSION['invalid_count'] > 2) {
    zen_session_destroy();
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
  }
  // eof modified for STRIN-1374
}

/*
* Get customers credit cards for displaying on page
*/

$sql = "SELECT *
    FROM " . TABLE_SAVED_CREDIT_CARDS . " scc
    LEFT JOIN " . TABLE_ADDRESS_BOOK . " ab ON (scc.address_id = ab.address_book_id)
    WHERE scc.customers_id = " . (int)$_SESSION['customer_id'] . "
    AND scc.is_deleted = 0
    AND scc.is_visible = 1";
$result = $db->Execute($sql);
$saved_credit_cards = array();

while(!$result->EOF) {
  $saved_credit_cards[] = $result->fields;
  $result->moveNext();
}


/*
*  Get customers addresses for displaying on page
*/

$sql = 'SELECT * FROM ' . TABLE_ADDRESS_BOOK . ' WHERE customers_id = ' . (int)$_SESSION['customer_id'];
$result = $db->Execute($sql);
$saved_addresses = array();

// STRIN-525: only shows enter new address if there is user has less addresses than max allowed
$address_count = zen_count_customer_address_book_entries((int)$_SESSION['customer_id']);
$add_address_enabled = ($address_count < MAX_ADDRESS_BOOK_ENTRIES ? true : false);
if($add_address_enabled) {
  $saved_addresses[] = array('id'=> 0, 'text'=> OPTION_ENTER_NEW_ADDRESS);
}

while(!$result->EOF) {
  $saved_addresses[] = array('id'=>$result->fields['address_book_id'], 'text'=>$result->fields['entry_street_address']);
  $result->moveNext();
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
$zco_notifier->notify('NOTIFY_HEADER_END_ACCOUNT_SAVED_CREDIT_CARDS');
