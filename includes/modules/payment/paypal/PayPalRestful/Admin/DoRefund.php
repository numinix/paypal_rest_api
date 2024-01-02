<?php
/**
 * A class that provides the actions needed to refund a payment for an order placed with
 * the PayPal Restful payment module.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Admin;

use PayPalRestful\Admin\GetPayPalOrderTransactions;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Zc2Pp\Amount;

class DoRefund
{
    public function __construct(int $oID, PayPalRestfulApi $ppr, string $module_name, string $module_version)
    {
        global $messageStack;

        if (!isset($_POST['ppr-amount'], $_POST['doRefundOid'], $_POST['capture_txn_id'], $_POST['ppr-refund-note']) || $oID !== (int)$_POST['doRefundOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($module_name, $module_version, $oID, $ppr);
        $ppr_capture_db_txns = $ppr_txns->getDatabaseTxns('CAPTURE');
        if (count($ppr_capture_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'CAPTURE', $oID), 'error');
            return;
        }

        $capture_id_txn = false;
        foreach ($ppr_capture_db_txns as $next_txn) {
            if ($next_txn['txn_id'] === $_POST['capture_txn_id']) {
                $capture_id_txn = $next_txn;
                break;
            }
        }
        if ($capture_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_PARAM_ERROR, 2), 'error');
            return;
        }

        $capture_currency = $capture_id_txn['mc_currency'];

        $payer_note = $_POST['ppr-refund-note'];
        $invoice_id = $ppr_txns->getInvoiceId();

        $full_refund = isset($_POST['ppr-refund-full']);
        if ($full_refund === true) {
            $refund_response = $ppr->refundCaptureFull($_POST['capture_txn_id'], $invoice_id, $payer_note);
        } else {
            $amount = new Amount($capture_currency);
            $refund_amount = $amount->getValueFromString($_POST['ppr-amount']);
            $refund_response = $ppr->refundCapturePartial($_POST['capture_txn_id'], $capture_currency, $refund_amount, $invoice_id, $payer_note);
        }

        if ($refund_response === false) {
            $error_info = $ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                default:
                    $error_message = MODULE_PAYMENT_PAYPALR_REFUND_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $amount_refunded = $refund_response['amount']['value'] . ' ' . $refund_response['amount']['currency_code'];
        $payer_note = "\n$payer_note";

        $refund_memo = sprintf(MODULE_PAYMENT_PAYPALR_REFUND_MEMO, zen_updated_by_admin(), $amount_refunded) . "\n" . $payer_note;
        $ppr_txns->addDbTransaction('REFUND', $refund_response, $refund_memo);

        $parent_capture_status = $ppr->getCaptureStatus($_POST['capture_txn_id']);
        if ($parent_capture_status === false) {
            $messageStack->add_session("Error retrieving capture status:\n" . json_encode($ppr->getErrorInfo()), 'warning');
        } else {
            $ppr_txns->updateParentTxnDateAndStatus($parent_capture_status);
        }

        $ppr_txns->updateMainTransaction($refund_response);

        $comments =
            'REFUNDED. Trans ID: ' . $refund_response['id'] . "\n" .
            'Amount: ' . $amount_refunded . "\n" .
            $payer_note;

        if (($capture_id_txn['mc_gross'] . ' ' . $capture_currency) !== $amount_refunded) {
            $refund_status = -1;
        } else {
            $refund_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;   //-FIXME:  There might be multiple captures to be refunded
            $refund_status = ($refund_status > 0) ? $refund_status : 2;
        }
        zen_update_orders_history($oID, $comments, null, $refund_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_COMPLETE, $amount_refunded), 'success');
    }
}
