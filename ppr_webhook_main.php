<?php
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

$op = $_GET['op'] ?? '';
$logger = new Logger();
if (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false) {
    $logger->enableDebug();
}
$logger->write("ppr_webhook_main ($op) starts.\n" . Logger::logJSON($_GET), true, 'before');

$valid_operations = ['cancel', 'return', '3ds_cancel', '3ds_return'];
if (!in_array($op, $valid_operations, true)) {
    unset($_SESSION['PayPalRestful']['Order']);
    $zco_notifier->notify('NOTIFY_PPR_WEBHOOK_MAIN_UNKNOWN_OPERATION', ['op' => $op]);
    zen_redirect(zen_href_link(FILENAME_DEFAULT));  //- FIXME? Perhaps FILENAME_TIME_OUT would be better, since that would kill any session.
}

// -----
// Either the customer chose to pay ...
//
// 1) ... with their PayPal Wallet, was sent to PayPal to choose their means
//    of payment and the customer chose to cancel-back from PayPal.
// 2) ... with a credit-card (which required 3DS verification) and the
//    customer chose to cancel-back from the 3DS authorization link.
//
// In either case, the customer is redirected back to the payment phase of the
// checkout process.
//
if ($op === 'cancel' || $op === '3ds_cancel') {
    unset($_SESSION['PayPalRestful']['Order']['PayerAction']);
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT), '', 'SSL');
}

if ($op === 'return' && (!isset($_GET['token'], $_SESSION['PayPalRestful']['Order']['id']) || $_GET['token'] !== $_SESSION['PayPalRestful']['Order']['id'])) {
    unset($_SESSION['PayPalRestful']['Order']);
    zen_redirect(zen_href_link(FILENAME_DEFAULT));  //- FIXME? Perhaps FILENAME_TIME_OUT would be better, since that would kill any session.
}

// -----
// Customer chose to pay with their PayPal, was sent to PayPal where the chose their means
// of payment and chose to return to the site to review/pay their order prior to
// confirmation OR the customer's payment choice was a credit card which
// subsequently required a 3DS authorization and was redirected back here
// to complete the transaction.
//
// The 'PayerAction' session element is set with the values to be posted
// back to the pertinent phase of the checkout process.  If (for some
// unknown reason) that element's not present, the customer's sent
// back to the payment phase of the checkout process.
//
if (!isset($_SESSION['PayPalRestful']['Order']['PayerAction'])) {
    $logger->write('ppr_webhook_main, redirecting to checkout_payment; no PayerAction variables.', true, 'after');
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT), '', 'SSL');
}

// -----
// If we've gotten here, this was either a successful callback for the
// customer's PayPal Wallet selection or the customer has completed
// a 3DS verification for a credit-card payment.
//
require DIR_WS_MODULES . 'payment/paypalr.php';
[$client_id, $secret] = paypalr::getEnvironmentInfo();

$ppr = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER, $client_id, $secret);
$ppr->setKeepTxnLinks(true);
$order_status = $ppr->getOrderStatus($_SESSION['PayPalRestful']['Order']['id']);
if ($order_status === false) {
    unset($_SESSION['PayPalRestful']['Order']);
    $logger->write('==> getOrderStatus failed, redirecting to shopping-cart', true, 'after');
    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
}

// -----
// Save the PayPal status response's status in the session-based PayPal
// order array and indicate that the wallet- (or card-) payment has been confirmed so that
// the base payment module "knows" that the payment-confirmation (or creation) at PayPal
// has been completed.
//
$_SESSION['PayPalRestful']['Order']['status'] = $order_status['status'];
if ($op === 'return') {
    $_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed'] = true;
} else {
    $_SESSION['PayPalRestful']['Order']['3DS_response'] = $_SESSION['PayPalRestful']['Order']['PayerAction']['ccInfo'];
}

// -----
// Create a self-submitting form to post back to the page
// from which the PayPal 'payer-action' was sent.  This is especially
// required for the integration with OPC when the associated payment-module
// doesn't require that the confirmation page be displayed.
//
$redirect_page = $_SESSION['PayPalRestful']['Order']['PayerAction']['current_page_base'];
$logger->write("Order's status set to {$order_status['status']}; posting back to $redirect_page.", true, 'after');
?>
<html>
<body onload="document.transfer_form.submit();">
   <form action="<?php echo zen_href_link($redirect_page); ?>" name="transfer_form" method="post">
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
