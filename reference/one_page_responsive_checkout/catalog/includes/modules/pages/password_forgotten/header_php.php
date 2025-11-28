<?php
/**
 * Password Forgotten
 *
 * @package page
 * @copyright Copyright 2003-2014 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version GIT: $Id: Author: DrByte  Tue Jul 31 18:47:04 2012 -0400 Modified in v1.5.1 $
 */

// This should be first line of the script:
$zco_notifier->notify('NOTIFY_HEADER_START_PASSWORD_FORGOTTEN');

//BOF Reset Password URL
if(defined('RESET_PASSWORD_URL_TYPE') && RESET_PASSWORD_URL_TYPE == 'Password Reset URL') {
  define('TEXT_MAIN', PASSWORD_RESET_PAGE_TEXT);
}
//EOF Reset Password URL

require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));


// remove from snapshot
$_SESSION['navigation']->remove_current_page();

if (isset($_GET['action']) && ($_GET['action'] == 'process')) {
// Slam prevention:
  if ($_SESSION['login_attempt'] > 9)
  {
    header('HTTP/1.1 406 Not Acceptable');
    exit(0);
  }
  // BEGIN SLAM PREVENTION
  if ($_POST['email_address'] != '')
  {
    if (! isset($_SESSION['login_attempt'])) $_SESSION['login_attempt'] = 0;
    $_SESSION['login_attempt'] ++;
  } // END SLAM PREVENTION

  $email_address = zen_db_prepare_input($_POST['email_address']);

  // below modified for OPRC Guest Checkout - Always use full account first
  $check_customer_query = "SELECT customers_firstname, customers_lastname, customers_password, customers_id
                           FROM " . TABLE_CUSTOMERS . "
                           WHERE customers_email_address = :emailAddress
                           ORDER BY COWOA_account ASC";

  $check_customer_query = $db->bindVars($check_customer_query, ':emailAddress', $email_address, 'string');
  $check_customer = $db->Execute($check_customer_query);

  if ($check_customer->RecordCount() > 0) {

    //BOF Reset Password URL
    if(defined('RESET_PASSWORD_URL_TYPE') && RESET_PASSWORD_URL_TYPE == 'Password Reset URL') {
      define(TOKEN_LENGTH, 24);
      $token = zen_create_random_value(TOKEN_LENGTH);
      $sql = "UPDATE " . TABLE_CUSTOMERS . "
            SET password_reset_token = :token
            WHERE customers_id = :customersID";

    $sql = $db->bindVars($sql, ':token', $token, 'string');
    $sql = $db->bindVars($sql, ':customersID', $check_customer->fields['customers_id'], 'integer');
    $db->Execute($sql);

    $html_msg['EMAIL_CUSTOMERS_NAME'] = $check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'];
    $html_msg['EMAIL_MESSAGE_HTML'] = sprintf(PASSWORD_RESET_EMAIL_BODY, $token);

    // send the email
    zen_mail($check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'], $email_address, PASSWORD_RESET_EMAIL_SUBJECT, sprintf(PASSWORD_RESET_EMAIL_BODY, $token), STORE_NAME, EMAIL_FROM, $html_msg,'password_forgotten');

    $messageStack->add_session('login', PASSWORD_RESET_SUCCESS_PASSWORD_SENT, 'success');

     }
     else {
     //EOF Reset Password URL

    $zco_notifier->notify('NOTIFY_PASSWORD_FORGOTTEN_VALIDATED', $email_address);

    if (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.3')) {
      $new_password = zen_create_PADSS_password( (ENTRY_PASSWORD_MIN_LENGTH > 0 ? ENTRY_PASSWORD_MIN_LENGTH : 5) );
	} else {
      $new_password = zen_create_random_value( (ENTRY_PASSWORD_MIN_LENGTH > 0 ? ENTRY_PASSWORD_MIN_LENGTH : 5) );
	}
    $crypted_password = zen_encrypt_password($new_password);

    $sql = "UPDATE " . TABLE_CUSTOMERS . "
            SET customers_password = :password
            WHERE customers_id = :customersID";

    $sql = $db->bindVars($sql, ':password', $crypted_password, 'string');
    $sql = $db->bindVars($sql, ':customersID', $check_customer->fields['customers_id'], 'integer');
    $db->Execute($sql);

    $html_msg['EMAIL_CUSTOMERS_NAME'] = $check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'];
    $html_msg['EMAIL_MESSAGE_HTML'] = sprintf(EMAIL_PASSWORD_REMINDER_BODY, $new_password);

    // send the email
    zen_mail($check_customer->fields['customers_firstname'] . ' ' . $check_customer->fields['customers_lastname'], $email_address, EMAIL_PASSWORD_REMINDER_SUBJECT, sprintf(EMAIL_PASSWORD_REMINDER_BODY, $new_password), STORE_NAME, EMAIL_FROM, $html_msg,'password_forgotten');

    $messageStack->add_session('login', SUCCESS_PASSWORD_SENT, 'success');
    
    //BOF Reset Password URL
     }
    //EOF Reset Password URL

    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
  } else {
    $messageStack->add('password_forgotten', TEXT_NO_EMAIL_ADDRESS_FOUND);
    $zco_notifier->notify('NOTIFY_PASSWORD_FORGOTTEN_NOT_FOUND', $email_address);
  }
}

$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_LOGIN, '', 'SSL'));
$breadcrumb->add(NAVBAR_TITLE_2);

// This should be last line of the script:
$zco_notifier->notify('NOTIFY_HEADER_END_PASSWORD_FORGOTTEN');
