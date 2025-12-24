<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'oprc';
$loaderPrefix = 'oprc';
$show_all_errors = FALSE;
require_once('includes/application_top.php');
header('HTTP/1.1 200 OK');
header('Content-type: text/plain');

if( $_POST['address_book_id'] == $_SESSION['sendto'] || $_POST['address_book_id'] == $_SESSION['billto'] 
								||  $_POST['address_book_id'] == $_POST['default_selected'] ){
	$response = array('success' => false, 'message' => ERROR_DELETE_SELECTED_ADDRESS);
} 
else {
  $default_query = 'SELECT customers_default_address_id FROM ' . TABLE_CUSTOMERS . ' WHERE customers_default_address_id = :address_book_id';
  $default_query = $db->bindVars($default_query, ':address_book_id', $_POST['address_book_id'],'integer');
//var_dump($default_query);
  $default_result = $db->Execute($default_query);
//var_dump($default_result->fields);
  if($default_result->fields['customers_default_address_id'] > 0) {
    $response = array('success' => false, 'message' => ERROR_DELETE_DEFAULT_ADDRESS); 
  }
  else {
    $delete_query = 'DELETE FROM '. TABLE_ADDRESS_BOOK . ' WHERE address_book_id = :address_book_id';
    $delete_query = $db->bindVars($delete_query, ':address_book_id', $_POST['address_book_id'], 'integer');
    $db->Execute($delete_query);

/*        //optional: reset the default address instead of disallowing the default to be deleted.
        $reset_default_query = 'UPDATE customers c, address_book ab SET c.customers_default_address_id = ab.address_book_id WHERE ab.customers_id = c.customers_id AND c.customers_default_address_id = :deleted_address_book_id;';
        $reset_default_query = $db->bindVars($reset_default_query, ':deleted_address_book_id', $_POST['address_book_id'], 'integer');
        $db->Execute($reset_default_query); */

	$response = array('success' => true, 'message' => SUCCESS_DELETE_SELECTED_ADDRESS);
  }
}
print json_encode($response);

require_once('includes/application_bottom.php');
?>
