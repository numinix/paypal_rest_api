<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'oprc';
//$loaderPrefix = 'oprc_login_check';
$show_all_errors = FALSE;
require_once('includes/application_top.php');
require_once(__DIR__ . '/includes/oprc_ajax_common.php');
header('HTTP/1.1 200 OK');
header('Content-type: text/plain');
echo oprc_login_check_response($_SESSION ?? []);
require_once('includes/application_bottom.php');
?>