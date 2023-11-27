<?php
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

//trigger_error(json_encode($_GET, JSON_PRETTY_PRINT) . "\n" . json_encode($_POST, JSON_PRETTY_PRINT) . "\n" . json_encode($_SERVER, JSON_PRETTY_PRINT) . "\n" . json_encode($_SESSION, JSON_PRETTY_PRINT), E_USER_WARNING);

$op = $_GET['op'] ?? '';

if ($op === 'cancel' || $op === 'return') {
    if (!isset($_GET['token'], $_SESSION['PayPalRestful']['Order']['id']) || $_GET['token'] !== $_SESSION['PayPalRestful']['Order']['id']) {
        zen_redirect(zen_href_link(FILENAME_DEFAULT));  //- FIXME
    }

    if ($op === 'cancel') {
        $messageStack->add_session('shopping_cart', '**FIXME** Cancelled from PayPal payment choice.', 'caution');
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
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
        $logger->write('==> getOrderStatus failed, redirecting to shopping-cart', true, 'after');
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }

    $_SESSION['PayPalRestful']['Order']['status'] = $order_status['status'];
    $logger->write("Order's status set to {$order_status['status']}; redirecting to checkout_confirmation.", true, 'after');
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION));
}

require DIR_WS_INCLUDES . 'application_bottom.php';
