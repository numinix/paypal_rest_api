<?php
/**
 * A class that provides the actions needed to capture an order placed with
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

class DoCapture
{
    public function __construct(int $oID, PayPalRestfulApi $ppr, string $module_name, string $module_version)
    {
        global $db, $messageStack;

        if (!isset($_POST['ppr-amount'], $_POST['doCaptOid'], $_POST['auth_txn_id'], $_POST['ppr-capt-note']) || $oID !== (int)$_POST['doCaptOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_CAPTURE_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($module_name, $module_version, $oID, $ppr);
        $ppr_auth_db_txns = $ppr_txns->getDatabaseTxns('AUTHORIZE');
        if (count($ppr_auth_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'AUTHORIZE', $oID), 'error');
            return;
        }

        $auth_id_txn = false;
        foreach ($ppr_auth_db_txns as $next_txn) {
            if ($next_txn['txn_id'] === $_POST['auth_txn_id']) {
                $auth_id_txn = $next_txn;
                break;
            }
        }
        if ($auth_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_PARAM_ERROR, 2), 'error');
            return;
        }

        $capt_currency = $auth_id_txn['mc_currency'];
        $amount = new Amount($capt_currency);
        $capt_amount = $amount->getValueFromString($_POST['ppr-amount']);
        $payer_note = $_POST['ppr-capt-note'];
        $final_capture = isset($_POST['final_capture']);

        $capture_response = $ppr->capturePayment($_POST['auth_txn_id'], $capt_currency, $capt_amount, $ppr_txns->getInvoiceId(), $payer_note, $final_capture);
        if ($capture_response === false) {
            $error_info = $ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                default:
                    $error_message = MODULE_PAYMENT_PAYPALR_CAPTURE_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $amount = $capture_response['amount']['value'] . ' ' . $capture_response['amount']['currency_code'];
        $payer_note = "\n$payer_note";

        $capture_memo_message = ($final_capture === true) ? MODULE_PAYMENT_PAYPALR_FINAL_CAPTURE_MEMO : MODULE_PAYMENT_PAYPALR_PARTIAL_CAPTURE_MEMO;
        $capture_memo = sprintf($capture_memo_message, zen_updated_by_admin(), $amount) . "\n" . $payer_note;
        $ppr_txns->addDbTransaction('CAPTURE', $capture_response, $capture_memo);

        $parent_auth_status = $ppr->getAuthorizationStatus($_POST['auth_txn_id']);
        if ($parent_auth_status === false) {
            $messageStack->add_session("Error retrieving authorization status:\n" . json_encode($ppr->getErrorInfo()), 'warning');
        } else {
            $ppr_txns->updateParentTxnDateAndStatus($parent_auth_status);
        }

        $ppr_txns->updateMainTransaction($capture_response);

        $comments =
            'FUNDS CAPTURED. Trans ID: ' . $capture_response['id'] . "\n" .
            "Amount: $amount\n" .
            $payer_note;

        if ($final_capture === false) {
            $capture_status = -1;
        } else {
            $capture_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
            $capture_status = ($order_status > 0) ? $order_status : 2;
        }
        zen_update_orders_history($oID, $comments, null, $capture_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_CAPTURE_COMPLETE, $oID), 'success');
    }
}
