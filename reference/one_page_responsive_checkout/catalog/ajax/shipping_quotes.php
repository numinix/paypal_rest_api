<?php
// this file will get the current shipping quotes from shipping modules
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once('includes/application_top.php');
header('HTTP/1.1 200 OK');
header('Content-type: text/plain');

require_once(DIR_WS_CLASSES . 'http_client.php');

require_once(DIR_WS_CLASSES . 'order.php');
$order = new order();

$shipping_weight = $total_weight = $_SESSION['cart']->show_weight();
$total_count = $_SESSION['cart']->count_contents();

// load all enabled shipping modules
$total_weight = $_SESSION['cart']->show_weight();
$total_count = $_SESSION['cart']->count_contents();
require_once(DIR_WS_CLASSES . 'shipping.php');
$shipping_modules = new shipping();

$quotes = $shipping_modules->quote();

// if no shipping method has been selected, automatically select the cheapest method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
if ( !$_SESSION['shipping'] || ( $_SESSION['shipping'] && ($_SESSION['shipping'] == false) && (zen_count_shipping_modules() > 1) ) ) {
	$_SESSION['shipping'] = $shipping_modules->cheapest();
}

// if cart contents have changed, keep selected shipping method but update the cost
$found_match = false;
if ( isset($_SESSION['shipping']) && isset($_SESSION['shipping']['id']) ) {
	foreach ( $quotes as $shipping_module ) { //find matching method in new quotes
		if ( is_array($shipping_module['methods']) ) {
			foreach ($shipping_module['methods'] as $shipping_method) {
				if ( isset($shipping_module['id']) && ($shipping_module['id'] . '_' . $shipping_method['id'] == $_SESSION['shipping']['id']) ) {
					 $_SESSION['shipping']['cost'] = $shipping_method['cost'];
					 $_SESSION['shipping']['title'] = $shipping_module['module'] . ' (' . $shipping_method['title'] . ')';
					 $found_match = true;
				 }
			}
		}
	}
}

if ( !$found_match ) { // the selected method is not available for the new cart contents.
	unset($_SESSION['shipping']);
}

if ( !isset($_SESSION['shipping']) )  {
	$ajax_request = true;
	$_GET['oprcaction'] = 'process';
	require(DIR_WS_MODULES . 'oprc_update_shipping.php');
}

if ( $order->content_type != 'virtual' || (isset($_SESSION['shipping']['id']) && $_SESSION['shipping']['id'] != 'free_free') ) {
	require($template->get_template_dir('tpl_modules_oprc_shipping_quotes.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping_quotes.php');
}

require_once('includes/application_bottom.php');
