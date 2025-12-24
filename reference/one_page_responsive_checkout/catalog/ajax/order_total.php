<?php
    // this file will get the current order total and return it without currency symbols
    require('../includes/configure.php');
    ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
    chdir(DIR_FS_CATALOG);
    $current_page_base = 'oprc';
    $loaderPrefix = 'oprc';
    $show_all_errors = FALSE;
    require_once('includes/application_top.php');
    header('HTTP/1.1 200 OK');
    header('Content-type: text/plain');
    require(DIR_WS_CLASSES . 'order.php');
    $order = new order();
    require(DIR_WS_CLASSES . 'order_total.php');
    $order_total_modules = new order_total();
    $order_total_modules->collect_posts();
    $order_total_modules->pre_confirmation_check();
    if ($credit_covers) {
        echo '0';
    } else {
        echo '1';
    }
    require_once('includes/application_bottom.php');
?>