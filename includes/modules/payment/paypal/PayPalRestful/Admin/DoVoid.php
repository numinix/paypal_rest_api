<?php
/**
 * A class that provides the actions needed to void an order placed with
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

class DoVoid
{
    public function __construct(int $oID, PayPalRestfulApi $ppr, string $module_name, string $module_version)
    {
        global $db, $messageStack;

        if (!isset($_POST['ppr-void-id'], $_POST['doVoidOid'], $_POST['ppr-void-note']) || $oID !== (int)$_POST['doVoidOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($module_name, $module_version, $oID, $ppr);
        $ppr_db_txns = $ppr_txns->getDatabaseTxns('AUTHORIZE');
        if (count($ppr_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'AUTHORIZE', $oID), 'error');
            return;
        }

        $auth_id_txn = false;
        foreach ($ppr_txns as $next_txn) {
            if ($next_txn_id === $_POST['ppr-void-id']) {
                $auth_id_txn = $next_txn;
                break;
            }
        }
        if ($auth_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_PARAM_ERROR, 2), 'error');
            return;
        }

        $void_response = $ppr->voidPayment($_POST['ppr-void-id']);
        if ($void_response === false) {
             $messageStack->add_session(MODULE_PAYMENT_PAYPALR_VOID_ERROR . "\n" . json_encode($ppr->getErrorInfo()), 'error');
             return;
        }

        // -----
        // Note: An authorization void returns *no additional information*, with a 204 http-code.
        // Simply update this authorization's status to indicate that it's been voided.
        //
        $void_memo = sprintf(MODULE_PAYMENT_PAYPALR_VOID_MEMO, zen_updated_by_admin()) . "\n\n";
        $void_note = strip_tags($_POST['ppr-void-note']);
        $modification_date = Helpers::convertPayPalDatePay2Db($void_response['update_time']);
        $memo = zen_db_input("\n$modification_date: $void_memo$void_note");
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL . "
                SET last_modified = '$modification_date',
                    payment_status = 'VOIDED',
                    notify_version = '" . $this->moduleVersion . "',
                    memo = CONCAT(IFNULL(memo, ''), '$memo'),
                    last_updated = now()
              WHERE paypal_ipn_id = " . $auth_id_txn['paypal_ipn_id'] . "
              LIMIT 1"
        );

        $comments =
            'VOIDED. Trans ID: ' . $last_auth_txn['txn_id'] . "\n" .
            $void_note;

        $voided_status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;
        $voided_status = ($voided_status > 0) ? $voided_status : 1;
        zen_update_orders_history($oID, $comments, null, $voided_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_COMPLETE, $oID), 'warning');
    }
}
