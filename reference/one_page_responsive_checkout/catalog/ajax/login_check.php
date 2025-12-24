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
if (isset($_SESSION['customer_id'])) {
  echo '1';
} else {
  echo '0';
}
require_once('includes/application_bottom.php'); 
?>