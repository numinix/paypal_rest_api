<?php
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

$op = $_GET['op'] ?? '';

if (($op !== 'cancel' && $op !== 'return') || !isset($_GET['token'], $_SESSION['PayPalRestful']['Order']['id']) || $_GET['token'] !== $_SESSION['PayPalRestful']['Order']['id']) {
    unset($_SESSION['PayPalRestful']['Order']);
    zen_redirect(zen_href_link(FILENAME_DEFAULT));  //- FIXME?
}

// -----
// Customer chose to pay with PayPal, was sent to PayPal to choose their means
// of payment and the customer chose to cancel-back from PayPal.
//
if ($op === 'cancel') {
    unset($_SESSION['PayPalRestful']['Order']['PayerAction']);
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT));
}

// -----
// Customer chose to pay with PayPal, was sent to PayPal where the chose their means
// of payment and chose to return to the site to review/pay their order prior to
// confirmation.
//
$logger = new Logger();
if (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false) {
    $logger->enableDebug();
}
$logger->write('ppr_webhook_main (return) starts.', true, 'before');

if (!isset($_SESSION['PayPalRestful']['Order']['PayerAction'])) {
    $logger->write('ppr_webhook_main, redirecting to checkout_payment; no PayerAction variables.', true, 'after');
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT));
}

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
// Save the PayPal status response's status in the session-based PayPal
// order array and indicate that the payment has been confirmed so that
// the base payment module "knows" that the payment-confirmation at PayPal
// has been completed.
//
$_SESSION['PayPalRestful']['Order']['status'] = $order_status['status'];
$_SESSION['PayPalRestful']['Order']['payment_confirmed'] = true;

// -----
// Create a self-submitting form to post back to the confirmation page
// from which the PayPal 'payer-action' was sent.  This is especially
// required for the integration with OPC when the associated payment-module
// doesn't require that the confirmation page be displayed.
//
$confirmation_page = $_SESSION['PayPalRestful']['Order']['PayerAction']['current_page_base'];
$logger->write("Order's status set to {$order_status['status']}; posting back to $confirmation_page.", true, 'after');
?>
<html>
<body onload="document.transfer_form.submit();">
   <form action="<?php echo zen_href_link($confirmation_page); ?>" name="transfer_form" method="post">
<?php
foreach ($_SESSION['PayPalRestful']['Order']['PayerAction']['savedPosts'] as $key => $value) {
    if (is_string($value)) {
        echo zen_draw_hidden_field($key, $value);
        continue;
    }

    $array_key_name = $key . '[:sub_key:]';
    foreach ($value as $sub_key => $sub_value) {
        echo zen_draw_hidden_field(str_replace(':sub_key:', $sub_key, $array_key_name), $sub_value);
    }
}
unset($_SESSION['PayPalRestful']['Order']['PayerAction']);
?>
   </form>
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php';
