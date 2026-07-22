<?php
/**
 * A class that provides the actions needed to refund a payment for an order placed with
 * the PayPal Advanced Checkout payment module.
 *
 * @copyright Copyright 2023-2024 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 *
 * Last updated: v1.3.20
 */
namespace PayPalAdvancedCheckout\Admin;

use PayPalAdvancedCheckout\Admin\GetPayPalOrderTransactions;
use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;
use PayPalAdvancedCheckout\Common\Helpers;
use PayPalAdvancedCheckout\Zc2Pp\Amount;

class DoRefund
{
    public function __construct(int $oID, PayPalAdvancedCheckoutApi $ppr, string $module_name, string $module_version)
    {
        global $messageStack;

        if (!isset($_POST['ppr-amount'], $_POST['doRefundOid'], $_POST['capture_txn_id'], $_POST['ppr-refund-note']) || $oID !== (int)$_POST['doRefundOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALAC_REFUND_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppac_txns = new GetPayPalOrderTransactions($module_name, $module_version, $oID, $ppr);
        $ppac_capture_db_txns = $ppac_txns->getDatabaseTxns('CAPTURE');
        if (count($ppac_capture_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALAC_NO_RECORDS, 'CAPTURE', $oID), 'error');
            return;
        }

        $capture_id_txn = false;
        $total_amount_captured = 0;
        foreach ($ppac_capture_db_txns as $next_txn) {
            if ($next_txn['txn_id'] === $_POST['capture_txn_id']) {
                $capture_id_txn = $next_txn;
            }
            $total_amount_captured += $next_txn['mc_gross'];
        }
        if ($capture_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALAC_REFUND_PARAM_ERROR, 2), 'error');
            return;
        }

        $capture_currency = $capture_id_txn['mc_currency'];

        $payer_note = $_POST['ppr-refund-note'];
        $invoice_id = $ppac_txns->getInvoiceId();

        // -----
        // Detect whether the capture row we're refunding came from a PayPal-managed
        // subscription cycle (PAYMENT.SALE.* webhook) vs. a regular v2 checkout
        // capture. Subscription cycles store the subscription remote id
        // (e.g. "I-MDK9B2RL9TN1") in `parent_txn_id`; that namespace doesn't
        // overlap with v2 capture-parent ids (which are PayPal order ids or
        // authorization ids of a different shape). For subscription cycles we
        // must call the v1 sale-refund endpoint -- v2/payments/captures/{id}
        // returns RESOURCE_NOT_FOUND for sale ids.
        //
        $parent_txn_id_raw = (string)($capture_id_txn['parent_txn_id'] ?? '');
        $is_subscription_cycle = (stripos($parent_txn_id_raw, 'I-') === 0);

        $full_refund = isset($_POST['ppr-refund-full']);
        $amount = new Amount($capture_currency);
        if ($is_subscription_cycle) {
            if ($full_refund === true) {
                $refund_response = $ppr->refundSaleFull($_POST['capture_txn_id'], $invoice_id, $payer_note);
            } else {
                $refund_amount = $amount->getValueFromString($_POST['ppr-amount']);
                $refund_response = $ppr->refundSalePartial($_POST['capture_txn_id'], $capture_currency, $refund_amount, $invoice_id, $payer_note);
            }
        } else {
            if ($full_refund === true) {
                $refund_response = $ppr->refundCaptureFull($_POST['capture_txn_id'], $invoice_id, $payer_note);
            } else {
                $refund_amount = $amount->getValueFromString($_POST['ppr-amount']);
                $refund_response = $ppr->refundCapturePartial($_POST['capture_txn_id'], $capture_currency, $refund_amount, $invoice_id, $payer_note);
            }
        }

        if ($refund_response === false) {
            $error_info = $ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                default:
                    $error_message = MODULE_PAYMENT_PAYPALAC_REFUND_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $ppac_txns->addDbTransaction('REFUND', $refund_response);

        // Use the matching status-fetch endpoint for the capture's namespace
        // (v1 sale vs. v2 capture). Both helpers return v2-shaped payloads so
        // updateParentTxnDateAndStatus stays unaware of the difference.
        if ($is_subscription_cycle) {
            $parent_capture_status = $ppr->getSaleStatus($_POST['capture_txn_id']);
        } else {
            $parent_capture_status = $ppr->getCaptureStatus($_POST['capture_txn_id']);
        }
        if ($parent_capture_status === false) {
            $messageStack->add_session("Error retrieving capture status:\n" . json_encode($ppr->getErrorInfo()), 'warning');
        } else {
            $ppac_txns->updateParentTxnDateAndStatus($parent_capture_status);
        }

        $ppac_txns->updateMainTransaction($refund_response);

        // -----
        // Sum up all refunds for this order (there might be multiple captures
        // that are refundable).  If the sum of all refunds equals the sum of all
        // captures for the order, the order's been fully refunded and the order's status
        // is updated to reflect the configured status value; otherwise the order's status
        // is unchanged.
        //
        // Note: This current refund wasn't recorded in the database when the PayPal
        // transactions for the order were retrieved!
        //
        $refund_status = -1;
        $total_amount_refunded = $refund_response['amount']['value'];

        $ppac_refund_db_txns = $ppac_txns->getDatabaseTxns('REFUND');
        foreach ($ppac_refund_db_txns as $next_txn) {
            $total_amount_refunded += $next_txn['mc_gross'];
        }
        if ($amount->getValueFromFloat((float)$total_amount_refunded) === $amount->getValueFromFloat((float)$total_amount_captured)) {
            $refund_status = (int)MODULE_PAYMENT_PAYPALAC_REFUNDED_STATUS_ID;
            $refund_status = ($refund_status > 0) ? $refund_status : 1;
        }

        $amount_refunded = $refund_response['amount']['value'] . ' ' . $refund_response['amount']['currency_code'];
        $payer_note = "\n$payer_note";
        $comments =
            'REFUNDED. Trans ID: ' . $refund_response['id'] . "\n" .
            'Amount: ' . $amount_refunded . "\n" .
            $payer_note;

        zen_update_orders_history($oID, $comments, null, $refund_status, 0);

        // -----
        // Publish the authoritative PayPal refund amount so site integrations
        // (e.g. TaxJar) do not rely on the submitted $_POST['ppr-amount'] or
        // the "full refund" checkbox, both of which can differ from what PayPal
        // actually refunded for this capture.
        //
        global $zco_notifier;
        if (isset($zco_notifier) && is_object($zco_notifier) && method_exists($zco_notifier, 'notify')) {
            $zco_notifier->notify(
                'NOTIFY_PAYPALAC_ADMIN_REFUND_COMPLETE',
                [
                    'orders_id' => $oID,
                    'capture_txn_id' => (string)$_POST['capture_txn_id'],
                    'refund_txn_id' => (string)($refund_response['id'] ?? ''),
                    'refund_amount' => (float)($refund_response['amount']['value'] ?? 0),
                    'currency_code' => (string)($refund_response['amount']['currency_code'] ?? $capture_currency),
                    'full_refund_requested' => $full_refund,
                    'is_subscription_cycle' => $is_subscription_cycle,
                    'refund_response' => $refund_response,
                ]
            );
        }

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALAC_REFUND_COMPLETE, $amount_refunded), 'success');
    }
}
