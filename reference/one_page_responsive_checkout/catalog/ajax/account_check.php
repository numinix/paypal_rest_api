<?php
// this file is ONLY used by the hideregistration process of FEAC
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'oprc';
$loaderPrefix = 'oprc';
$show_all_errors = FALSE;
require_once('includes/application_top.php');
header('HTTP/1.1 200 OK');
header('Content-type: text/plain');
switch($_POST['checkoutType']) {
  case 'account':
    $email_address = $_POST['hide_email_address_register'];
    $email_address_check = $db->Execute("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . $email_address . "' AND COWOA_account != 1 LIMIT 1;");
    if ($email_address_check->RecordCount() > 0) {
      echo '1'; // match found
    } else {
      echo '0'; // no match found
    }
    break;
  case 'guest':
    if (OPRC_NOACCOUNT_ALWAYS == 'true') {
      echo '0'; // no match found
    } else {
      $email_address = $_POST['hide_email_address_register'];
      $email_address_check = $db->Execute("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . $email_address . "' AND COWOA_account != 1 LIMIT 1;");
      if ($email_address_check->RecordCount() > 0) {
        echo '1'; // match found
      } else {
        echo '0'; // no match found
      }
    }
    break;
  default:
    // store must be missing the field, return no match found in order to allow customer to continue
    echo '0'; // no match found
    break;      
}
require_once('includes/application_bottom.php'); 
?>