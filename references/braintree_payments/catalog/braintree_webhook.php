<?php
/**
 * braintree_webhook.php
 *
 * This file handles webhook notifications from Braintree.
 * It verifies the webhook payload using the Braintree PHP SDK,
 * processes the notification based on its type, and updates the
 * corresponding order status and history using configuration values
 * for paid, pending, and refunded statuses.
 *
 * Place this file in the root directory of your Zen Cart catalog.
 */

// Include Zen Cart initialization
require('includes/application_top.php');

// Include the Braintree SDK and common class
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/Braintree.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_common.php');

// Configure Braintree using your PayPal module constants
$config = [
    'environment' => MODULE_PAYMENT_BRAINTREE_SERVER,  // 'sandbox' or 'production'
    'merchant_id' => MODULE_PAYMENT_BRAINTREE_MERCHANTID,
    'public_key'  => MODULE_PAYMENT_BRAINTREE_PUBLICKEY,
    'private_key' => MODULE_PAYMENT_BRAINTREE_PRIVATEKEY,
];

$timeout = defined('MODULE_PAYMENT_BRAINTREE_TIMEOUT')
    ? BraintreeCommon::normalize_timeout_seconds(MODULE_PAYMENT_BRAINTREE_TIMEOUT)
    : null;

if (is_int($timeout) && $timeout > 0) {
    $config['timeout'] = $timeout;
}

// Instantiate the Braintree gateway and common class
$gateway = new Braintree\Gateway($config);
$braintreeCommon = new BraintreeCommon($config);

// Retrieve the webhook parameters sent via POST
$bt_signature = $_POST["bt_signature"] ?? '';
$bt_payload   = $_POST["bt_payload"] ?? '';

if (empty($bt_signature) || empty($bt_payload)) {
    http_response_code(400);
    echo "Missing webhook parameters.";
    exit;
}

try {
    // Parse and verify the webhook notification using the Braintree SDK
    $webhookNotification = $gateway->webhookNotification()->parse($bt_signature, $bt_payload);

    // Log the webhook kind and timestamp for debugging
    $log  = "Webhook received: " . $webhookNotification->kind . " at " . $webhookNotification->timestamp->format('Y-m-d H:i:s') . "\n";
    // Optionally, write this log to a file:
    // file_put_contents('braintree_webhook.log', $log, FILE_APPEND);

    // Process dispute-related webhooks
    if (isset($webhookNotification->dispute)) {
        $dispute = $webhookNotification->dispute;
        $transaction_id = $dispute->transaction->id;

        // Retrieve the order ID using the transaction ID
        $order_query = "SELECT order_id FROM " . TABLE_BRAINTREE . " WHERE transaction_id = '" . $db->prepare_input($transaction_id) . "'";
        $order_result = $db->Execute($order_query);

        if ($order_result->RecordCount() > 0) {
            $order_id = $order_result->fields['order_id'];

            // Retrieve the module name from the Braintree table for this order.
            $module_query = "SELECT module_name FROM " . TABLE_BRAINTREE . " WHERE order_id = " . (int)$order_id;
            $module_result = $db->Execute($module_query);
            $module_name = ($module_result->RecordCount() > 0) ? $module_result->fields['module_name'] : '';

            // Set module-specific status values using a single switch block.
            switch ($module_name) {
                case 'braintree_paypal':
                    $paid_status     = MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS;
                    $pending_status  = MODULE_PAYMENT_BRAINTREE_PAYPAL_PENDING_STATUS_ID;
                    $refunded_status = MODULE_PAYMENT_BRAINTREE_PAYPAL_REFUNDED_STATUS_ID;
                    break;
                case 'braintree_applepay':
                    $paid_status     = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS;
                    $pending_status  = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PENDING_STATUS_ID;
                    $refunded_status = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_REFUNDED_STATUS_ID;
                    break;
                case 'braintree_googlepay':
                    $paid_status     = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS;
                    $pending_status  = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID;
                    $refunded_status = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_REFUNDED_STATUS_ID;
                    break;
                case 'braintree_api':
                    $paid_status     = MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID;
                    $pending_status  = MODULE_PAYMENT_BRAINTREE_ORDER_PENDING_STATUS_ID;
                    $refunded_status = MODULE_PAYMENT_BRAINTREE_REFUNDED_STATUS_ID;
                    break;
                default:
                    $paid_status     = 2; // Default "Processing"
                    $pending_status  = 1; // Default "Pending"
                    $refunded_status = 1; // Default "Pending" or "Refunded" as needed.
                    break;
            }

            // Determine the new order status based on the dispute status
            switch ($webhookNotification->kind) {
                case Braintree\WebhookNotification::KIND_DISPUTE_OPENED:
                    $new_status = $pending_status;
                    $comment = "Braintree webhook update: Dispute opened for Transaction " . $transaction_id . ". Order marked as Pending.";
                    break;
                case Braintree\WebhookNotification::KIND_DISPUTE_LOST:
                    $new_status = $refunded_status;
                    $comment = "Braintree webhook update: Dispute lost for Transaction " . $transaction_id . ". Order marked as Refunded.";
                    break;
                case Braintree\WebhookNotification::KIND_DISPUTE_WON:
                    $new_status = $paid_status;
                    $comment = "Braintree webhook update: Dispute won for Transaction " . $transaction_id . ". Order marked as Paid.";
                    break;
                default:
                    // If the webhook is not related to a dispute, do nothing.
                    http_response_code(200);
                    echo "Webhook not applicable.";
                    exit;
            }

            // Update the order status in the database
            $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . (int)$new_status . " WHERE orders_id = " . (int)$order_id);
            $braintreeCommon->updateOrderStatusHistory($order_id, $new_status, $comment);

            // Log the update
            // file_put_contents('braintree_webhook.log', "Order " . $order_id . " updated to status " . $new_status . "\n", FILE_APPEND);
        }
    }

    // Respond with a 200 HTTP status code to acknowledge receipt
    http_response_code(200);
    echo "Webhook processed successfully.";
} catch (Exception $e) {
    // Log the error for debugging (optional)
    // file_put_contents('braintree_webhook.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo "Error processing webhook: " . $e->getMessage();
    exit;
}

// Include Zen Cart footer processing
require('includes/application_bottom.php');