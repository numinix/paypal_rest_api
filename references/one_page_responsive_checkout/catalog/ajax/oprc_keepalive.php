<?php
/**
 * AJAX endpoint to keep the Zen Cart session alive.
 * Sends periodic requests to prevent session timeout during checkout.
 */
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'oprc';
$show_all_errors = false;
require_once('includes/application_top.php');
require_once(__DIR__ . '/includes/oprc_ajax_common.php');
header('HTTP/1.1 200 OK');
header('Content-type: application/json');
echo json_encode(oprc_keepalive_response($_SESSION ?? []));
require_once('includes/application_bottom.php');
