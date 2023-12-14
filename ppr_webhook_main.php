<?php
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

//trigger_error(json_encode($_GET, JSON_PRETTY_PRINT) . "\n" . json_encode($_POST, JSON_PRETTY_PRINT) . "\n" . json_encode($_SERVER, JSON_PRETTY_PRINT) . "\n" . json_encode($_SESSION, JSON_PRETTY_PRINT), E_USER_WARNING);

$op = $_GET['op'] ?? '';

if (($op !== 'cancel' && $op !== 'return') || !isset($_GET['token'], $_SESSION['PayPalRestful']['Order']['id']) || $_GET['token'] !== $_SESSION['PayPalRestful']['Order']['id']) {
    unset($_SESSION['PayPalRestful']['Order']);
    zen_redirect(zen_href_link(FILENAME_DEFAULT));  //- FIXME?
}

if ($op === 'cancel') {
    $messageStack->add_session('checkout', '**FIXME** Cancelled from PayPal payment choice.', 'caution');
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT));
}

$logger = new Logger();
if (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false) {
    $logger->enableDebug();
}
$logger->write('ppr_webhook_main (return) starts.', true, 'before');

require DIR_WS_MODULES . 'payment/paypalr.php';
[$client_id, $secret] = paypalr::getEnvironmentInfo();

$ppr = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER, $client_id, $secret);
$order_status = $ppr->getOrderStatus($_GET['token']);
if ($order_status === false) {
    unset($_SESSION['PayPalRestful']['Order']);
    $logger->write('==> getOrderStatus failed, redirecting to shopping-cart', true, 'after');
    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
}

// -----
// Save the PayPal status response's (er) status in the session-based PayPal
// order array and indicate that the payment has been confirmed so that
// the base payment module "knows" that the payment-confirmation at PayPal
// has been completed.
//
$_SESSION['PayPalRestful']['Order']['status'] = $order_status['status'];
$_SESSION['PayPalRestful']['Order']['payment_confirmed'] = true;
$logger->write("Order's status set to {$order_status['status']}; redirecting to checkout_confirmation.", true, 'after');
zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION));

// -----
// Note, redundant as execution will never reach here!
//
require DIR_WS_INCLUDES . 'application_bottom.php';
