<?php    
  // process login
  // This should be first line of the script:
  $zco_notifier->notify('NOTIFY_HEADER_START_OPRC_LOGIN');
  
  $messageStack->reset();

  // redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled (or the session has not started)
  if ($session_started == false) {
    //zen_redirect(zen_href_link(FILENAME_COOKIE_USAGE));
  }

  // if the customer is logged in already, redirect them to the My account page
  //if (isset($_SESSION['customer_id']) and $_SESSION['customer_id'] != '') {
    //zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
  //}
  
  // check if products in cart before logging in
  $my_account = false; // default
  if (!$_SESSION['cart']->count_contents() > 0 || (isset($_SESSION['gv_no']) && $_SESSION['gv_no'] != '')) $my_account = true;  
  
  require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . FILENAME_LOGIN . '.php');
	
  $error = false;
  if (isset($_REQUEST['oprcaction']) && ($_REQUEST['oprcaction'] == 'process')) {
    if ($_GET['autologin'] == 'true' && OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN == 'true' && isset($_COOKIE['email_address']) && isset($_COOKIE['password'])) {
      $auto_login = true;
      $email_address = zen_db_prepare_input($_COOKIE['email_address']);
      $password = zen_db_prepare_input($_COOKIE['password']);
    } else {
      $auto_login = false;
      $email_address = zen_db_prepare_input($_POST['email_address']);
      $password = zen_db_prepare_input($_POST['password']);
    }
    
    if (strlen($password) < (int)ENTRY_PASSWORD_MIN_LENGTH) {
      $min_password_failed = true;
    }

    if ( !$auto_login && ((!isset($_SESSION['securityToken']) || !isset($_POST['securityToken'])) || ($_SESSION['securityToken'] !== $_POST['securityToken'])) && (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 3.8)) ) {
      $error = true;
      $messageStack->add_session('login', ERROR_SECURITY_ERROR);
    } else {

      // Check if email exists (use permanent account first)
      $check_customer_query = "SELECT customers_id, customers_firstname, customers_lastname, customers_password,
                                      customers_email_address, customers_default_address_id,
                                      customers_authorization, customers_referral, COWOA_account
                             FROM " . TABLE_CUSTOMERS . "
                             WHERE customers_email_address = :emailAddress
                             ORDER BY COWOA_account DESC";

      $check_customer_query = $db->bindVars($check_customer_query, ':emailAddress', $email_address, 'string');
      $check_customer = $db->Execute($check_customer_query);
      if (!$check_customer->RecordCount()) {
        $error = true;
        $messageStack->add_session('login', TEXT_LOGIN_ERROR);
      } elseif ($check_customer->fields['customers_authorization'] == '4') {
        // this account is banned
        $zco_notifier->notify('NOTIFY_LOGIN_BANNED');
        $messageStack->add('login', TEXT_LOGIN_BANNED);
      } else {
        // Check that password is good
        // *** start Encrypted Master Password by stagebrace ***
        if (OPRC_MASTER_PASSWORD == 'true') {
          $get_admin_query = "SELECT admin_pass
                              FROM " . TABLE_ADMIN;
          $check_administrator = $db->Execute($get_admin_query);
        
          while (!$check_administrator->EOF) {
            $administrator = (zen_validate_password($password, $check_administrator->fields['admin_pass']));
            if ($administrator) break;
            $check_administrator->MoveNext();
          }
        }
        $full_account_exists = false;
        while (!$check_customer->EOF) {
          $customer = (zen_validate_password($password, $check_customer->fields['customers_password']));
          if ((!$customer) && (OPRC_MASTER_PASSWORD == 'false' || (OPRC_MASTER_PASSWORD == 'true' && !$administrator))) {
            if ($check_customer->fields['COWOA_account'] == 0) $full_account_exists = true;
            $check_customer->MoveNext();
          } else {
            $customers_id = $check_customer->fields['customers_id']; // save to use outside of loop
            break; // valid account found
          }
        }
        if ($customer || ($administrator && OPRC_MASTER_PASSWORD == 'true')) {
          if ($administrator && OPRC_MASTER_PASSWORD == 'true') $_SESSION['master_password'] = true;
          $ProceedToLogin = true;
          $check_customer_query = "SELECT customers_id, customers_firstname, customers_lastname, customers_password,
                                          customers_email_address, customers_default_address_id,
                                          customers_authorization, customers_referral
                                   FROM " . TABLE_CUSTOMERS . "
                                   WHERE customers_id = " . $customers_id . "
                                   LIMIT 1";
          $check_customer = $db->Execute($check_customer_query);
                    
          // check if account is COWOA and convert to customer
          $cowoa_check = $db->Execute("SELECT COWOA_account FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $check_customer->fields['customers_id'] . " AND COWOA_account = 1 LIMIT 1;");
          if ($cowoa_check->RecordCount() > 0) {
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET COWOA_account = 0 WHERE customers_id = " . $check_customer->fields['customers_id'] . " LIMIT 1;"); 
          } elseif (OPRC_NOACCOUNT_COMBINE == 'true') {
            // account must be permanent, check for COWOA accounts and combine them
            $cowoa_check = $db->Execute("SELECT COWOA_account, customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . $check_customer->fields['customers_email_address'] . "' AND COWOA_account = 1;");
            while (!$cowoa_check->EOF) {
              // update orders
              $update_orders = "UPDATE " . TABLE_ORDERS . " SET customers_id = " . $check_customer->fields['customers_id'] . " WHERE customers_id = " . $cowoa_check->fields['customers_id'] . ";";
              $db->Execute($update_orders);
              // delete accounts
              $delete_customers = "DELETE FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $cowoa_check->fields['customers_id'] . " LIMIT 1;";
              $db->Execute($delete_customers);
              $cowoa_check->MoveNext();
            } 
          }
        } else {
          $ProceedToLogin = false;
        }
        
        // fix bug where customers_default_address_id does not exist in address book table
        $address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int)$check_customer->fields['customers_default_address_id'] . " LIMIT 1;");
        if ($address_book->RecordCount() <= 0) {
          // address book doesn't exist, so get newest for customer
          $new_address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$check_customer->fields['customers_id'] . " ORDER BY address_book_id DESC LIMIT 1;");
          if ($new_address_book->fields['address_book_id'] > 0) {
            $check_customer->fields['customers_default_address_id'] = $new_address_book->fields['address_book_id'];
            // update the customers table
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_default_address_id = " . $new_address_book->fields['address_book_id'] . " WHERE customers_id = " . (int)$check_customer->fields['customers_id'] . " LIMIT 1;");
          }
        }
        
        // fix bug where customers_default_shipping_address_id does not exist in address book table
        $address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int)$check_customer->fields['customers_default_shipping_address_id'] . " LIMIT 1;");
        if ($address_book->RecordCount() <= 0) {
          // address book doesn't exist, so get newest for customer
          $new_address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$check_customer->fields['customers_id'] . " ORDER BY address_book_id DESC LIMIT 1;");
          if ($new_address_book->fields['address_book_id'] > 0) {
            $check_customer->fields['customers_default_shipping_address_id'] = $new_address_book->fields['address_book_id'];
            // update the customers table
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_default_shipping_address_id = " . $new_address_book->fields['address_book_id'] . " WHERE customers_id = " . (int)$check_customer->fields['customers_id'] . " LIMIT 1;");
          }
        }        
        
        if (!($ProceedToLogin)) {
          if ($auto_login) {
            // unset cookies and redirect back to login page
            unset($_COOKIE['email_address']);
            unset($_COOKIE['password']);
            zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
          }
        // *** end Encrypted Master Password by stagebrace ***
          $error = true;
          if ($full_account_exists) {
            $messageStack->add_session('login', TEXT_LOGIN_ERROR);
          } else {
            $messageStack->add_session('login', TEXT_LOGIN_ERROR_GUEST);
          }
        } else {
          if (isset($_POST['loginCookie']) && $_POST['loginCookie'] == '1') {
            setcookie('email_address', $email_address, time() + 1209600);
            setcookie('password', $password, time() + 1209600);  
          }
          if (SESSION_RECREATE == 'True') {
            zen_session_recreate();
          }

          $check_country_query = "SELECT entry_country_id, entry_zone_id
                                FROM " . TABLE_ADDRESS_BOOK . "
                                WHERE customers_id = :customersID
                                AND address_book_id = :addressBookID";

          $check_country_query = $db->bindVars($check_country_query, ':customersID', $check_customer->fields['customers_id'], 'integer');
          $check_country_query = $db->bindVars($check_country_query, ':addressBookID', $check_customer->fields['customers_default_address_id'], 'integer');
          $check_country = $db->Execute($check_country_query);

          $_SESSION['customer_id'] = $check_customer->fields['customers_id'];
          $_SESSION['cart_address_id'] = $_SESSION['customer_default_address_id'] = $check_customer->fields['customers_default_address_id'];
          $_SESSION['sendto'] = $_SESSION['customers_default_shipping_address_id'] = $check_customer->fields['customers_default_shipping_address_id'];
          //config to always the billing address as default shipping (overriding default shipping)
          if(OPRC_DEFAULT_BILLING_FOR_SHIPPING == 'true'){
              $_SESSION['sendto'] = $_SESSION['customers_default_shipping_address_id'] = $_SESSION['customer_default_address_id'];
          }
          $_SESSION['customers_authorization'] = $check_customer->fields['customers_authorization'];
          $_SESSION['customer_first_name'] = $check_customer->fields['customers_firstname'];
          $_SESSION['customer_last_name'] = $check_customer->fields['customers_lastname'];
          $_SESSION['customer_country_id'] = $check_country->fields['entry_country_id'];
          $_SESSION['customer_zone_id'] = $check_country->fields['entry_zone_id'];

          $sql = "UPDATE " . TABLE_CUSTOMERS_INFO . "
                  SET customers_info_date_of_last_logon = now(),
                      customers_info_number_of_logons = customers_info_number_of_logons+1
                  WHERE customers_info_id = :customersID";

          $sql = $db->bindVars($sql, ':customersID',  $_SESSION['customer_id'], 'integer');
          $db->Execute($sql);
          $zco_notifier->notify('NOTIFY_LOGIN_SUCCESS');

          // bof: contents merge notice
          // save current cart contents count if required
          if (SHOW_SHOPPING_CART_COMBINED > 0) {
            $zc_check_basket_before = $_SESSION['cart']->count_contents();
          }

          // bof: not require part of contents merge notice
          // restore cart contents
          $_SESSION['cart']->restore_contents();
          // eof: not require part of contents merge notice
        }
      }
      if ($error == true) {
        $zco_notifier->notify('NOTIFY_LOGIN_FAILURE');
      }
    }
  }
  
  if ($min_password_failed && isset($_SESSION['customer_id'])) {
    // account failed minimum password requirements, redirect to change password page with error
    $messageStack->add_session('account_password', ENTRY_PASSWORD_ERROR);
    zen_redirect(zen_href_link(FILENAME_ACCOUNT_PASSWORD, '', 'SSL'));
  } else { 
    if ($my_account && isset($_SESSION['customer_id'])) {
      // customer logged in without adding products to their cart
      zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL', false));
      //zen_redirect(oprc_back_link(true));
    }

    else {
      // check current cart contents count if required
      if (SHOW_SHOPPING_CART_COMBINED > 0 && $zc_check_basket_before > 0) {
        $zc_check_basket_after = $_SESSION['cart']->count_contents();
        if (($zc_check_basket_before != $zc_check_basket_after) && $_SESSION['cart']->count_contents() > 0 && SHOW_SHOPPING_CART_COMBINED > 0) {
          if (SHOW_SHOPPING_CART_COMBINED == 2) {
            // warning only do not send to cart
            $messageStack->add_session('checkout', WARNING_SHOPPING_CART_COMBINED, 'caution');
            zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL', false));
          }
          if (SHOW_SHOPPING_CART_COMBINED == 1) {
            // show warning and send to shopping cart for review, not really, stay here motherfucker
            $messageStack->add_session('checkout', WARNING_SHOPPING_CART_COMBINED, 'caution');
            zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
          }
        } else { // no warning
          zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
        }
      }
    }

  }

  //$breadcrumb->add(NAVBAR_TITLE);

  // Check for PayPal express checkout button suitability:
  //$paypalec_enabled = (defined('MODULE_PAYMENT_PAYPALWPP_STATUS') && MODULE_PAYMENT_PAYPALWPP_STATUS == 'True');
  // Check for express checkout button suitability:
  //$ec_button_enabled = ($paypalec_enabled && ($_SESSION['cart']->count_contents() > 0 && $_SESSION['cart']->total > 0));


  // This should be last line of the script:
  $zco_notifier->notify('NOTIFY_HEADER_END_OPRC_LOGIN');
  exit();
?>