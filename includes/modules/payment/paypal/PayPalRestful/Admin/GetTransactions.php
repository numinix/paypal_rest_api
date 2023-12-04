<?php
/**
 * A class that returns an array of 'current' transactions for a specified order for
 * Cart processing for the PayPal Restful payment module's admin_notifications processing.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Admin;

use PayPalRestful\Admin\Formatters\Messages;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;

class GetTransactions
{
    protected string $moduleName;

    protected string $moduleVersion;

    protected int $oID;

    protected PayPalRestfulApi $ppr;

    protected Logger $log;

    protected array $databaseTxns = [];

    protected Messages $messages;

    protected string $paymentType;

    protected array $paypalTransactions = [];

    public function __construct(string $module_name, string $module_version, int $oID, PayPalRestfulApi $ppr)
    {
        $this->moduleName = $module_name;
        $this->moduleVersion = $module_version;
        $this->oID = $oID;
        $this->ppr = $ppr;

        $this->log = new Logger();
        $this->messages = new Messages();
    }

    public function getDatabaseTxns(string $txn_type = ''): array
    {
        $this->databaseTxns = $this->getPayPalDatabaseTransactionsForOrder($txn_type);
        return $this->databaseTxns;
    }

    public function getPaypalTxns(): array
    {
        $this->getPayPalUpdates($this->oID);

        if ($this->messages->size !== 0) {
            $this->databaseTxns = $this->getPayPalDatabaseTransactionsForOrder($this->moduleName);
        }
        return $this->paypalTransactions;
    }

    public function getMessages(): string
    {
        return $this->messages->output();
    }

    protected function getPayPalDatabaseTransactionsForOrder(string $txn_type): array
    {
        global $db;

        // -----
        // Grab the transactions for the current order from the database.  The
        // original order (with a txn_type of "CREATE") is always displayed first;
        // the remaining ones are included in date_added order.
        //
        // This funkiness is needed since the date_added for the order-creation
        // is the same as that for its first transaction (AUTHORIZE or CAPTURE).
        //
        $paypal_txns = [];
        $and_clause = ($txn_type === '') ? '' : "AND txn_type = '$txn_type'";
        $txns = $db->ExecuteNoCache(
            "SELECT *
               FROM " . TABLE_PAYPAL . "
              WHERE order_id = {$this->oID}
                AND module_name = '{$this->moduleName}'
                $and_clause
              ORDER BY
                CASE txn_type
                    WHEN 'CREATE' THEN -1
                    ELSE 1
                END ASC, date_added ASC"
        );
        foreach ($txns as $txn) {
            $paypal_txns[] = $txn;
        }
        return $paypal_txns;
    }

    protected function getPayPalUpdates()
    {
        // -----
        // Retrieve the current status information for the primary/order transaction
        // from PayPal.
        //
        $primary_txn_id = $this->databaseTxns[0]['txn_id'];
        $txns = $this->ppr->getOrderStatus($primary_txn_id);
        if ($txns === false) {
            $this->messages->add(MODULE_PAYMENT_PAYPALR_TEXT_GETDETAILS_ERROR, 'error');
            return;
        }
        $this->paypalTransactions = $txns;

        // -----
        // Determine the type of payment, e.g. 'paypal' vs. 'card', associated with this order.
        //
        $this->paymentType = array_key_first($txns['payment_source']);

        // -----
        // The primary (initially-created) transaction has already been recorded in the
        // database.  Loop through the 'payments' applied to this transaction, updating
        // the database with any that are missing as an order might have been updated in
        // the store's PayPal Management Console.
        //
        foreach ($txns['purchase_units'][0]['payments'] as $record_type => $child_txns) {
            switch ($record_type) {
                case 'authorizations':
                    $this->updateAuthorizations($child_txns);
                    break;
                case 'captures':
                    $this->updateCaptures($child_txns);
                    break;
                case 'refunds':
                    $this->updateRefunds($child_txns);
                    break;
                default:
                    $this->messages->add("Unknown payment record ($record_type) provided by PayPal.", 'error');
                    break;
            }
        }
    }

    protected function updateAuthorizations(array $authorizations)
    {
        foreach ($authorizations as $next_authorization) {
            $authorization_txn_id = $next_authorization['id'];
            if ($this->transactionExists($authorization_txn_id) === true) {
                continue;
            }
            $this->addDbTransaction('AUTHORIZE', $next_authorization);
        }
    }

    protected function updateCaptures(array $captures)
    {
        foreach ($captures as $next_capture) {
            $capture_txn_id = $next_capture['id'];
            if ($this->transactionExists($capture_txn_id) === true) {
                continue;
            }
            $this->addDbTransaction('CAPTURE', $next_capture);
        }
    }

    protected function updateRefunds(array $refunds)
    {
        foreach ($refunds as $next_refund) {
            $refund_txn_id = $next_refund['id'];
            if ($this->transactionExists($refund_txn_id) === true) {
                continue;
            }
        }
        $this->addDbTransaction('REFUND', $next_refund);
    }

    protected function transactionExists(string $txn_id): bool
    {
        $txn_exists = false;
        foreach ($this->databaseTxns as $next_txn) {
            if ($next_txn['txn_id'] === $txn_id) {
                $txn_exists = true;
                break;
            }
        }
        return $txn_exists;
    }

    public function addDbTransaction(string $txn_type, array $paypal_response, string $memo_comment = '')
    {
        $this->log->write("addDbTransaction($txn_type, ..., $memo_comment):\n" . Logger::logJSON($paypal_response));

        $date_added = Helpers::convertPayPalDatePay2Db($paypal_response['create_time']);

        $payment_info = $this->getPaymentInfo($paypal_response);
        if ($txn_type === 'CAPTURE' && count($payment_info) !== 0) {
            $payment_info['payment_date'] = $date_added;
        }

        $note_to_payer = $paypal_response['note_to_payer'] ?? '';
        if ($note_to_payer !== '') {
            $note_to_payer = "\n\nPayment Note: $note_to_payer";
        }

        if ($memo_comment === '') {
            $memo_comment = 'Added during PayPal Management Console action.';
        }

        $sql_data_array = [
            'order_id' => $this->oID,
            'txn_type' => $txn_type,
            'module_name' => $this->moduleName,
            'module_mode' => '',
            'reason_code' => $paypal_response['status_details']['reason'] ?? '',
            'payment_type' => $this->paymentType ?? $this->databaseTxns[0]['payment_type'],
            'payment_status' => $paypal_response['status'],
            'mc_currency' => $paypal_response['amount']['currency_code'],
            'txn_id' => $paypal_response['id'],
            'mc_gross' => $paypal_response['amount']['value'],
            'date_added' => $date_added,
            'notify_version' => $this->moduleVersion,
            'last_modified' => Helpers::convertPayPalDatePay2Db($paypal_response['update_time']),
            'memo' => $memo_comment . $note_to_payer,
        ];
        $sql_data_array = array_merge($sql_data_array, $payment_info);
        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
    }

    protected function getPaymentInfo(array $paypal_response): array
    {
        $payment_info = $paypal_response['seller_receivable_breakdown'] ?? $paypal_response['seller_payable_breakdown'] ?? [];
        if (count($payment_info) === 0) {
            return [];
        }

        //- FIXME, refunds/auths/voids don't include exchange-rate; that's set when the payment is captured
        return [
            'payment_gross' => $payment_info['gross_amount']['value'],
            'payment_fee' => $payment_info['paypal_fee']['value'],
            'settle_amount' => $payment_info['receivable_amount']['value'] ?? $payment_info['net_amount']['value'],
            'settle_currency' => $payment_info['receivable_amount']['currency_code'] ?? $payment_info['net_amount']['currency_code'],
            'exchange_rate' => $payment_info['exchange_rate']['value'] ?? 'null',
        ];
    }

    public function updateMainTransaction(int $oID, array $paypal_response, string $memo)
    {
        global $db;

        $modification_date = Helpers::convertPayPalDatePay2Db($paypal_response['update_time']);
        $memo = "\n$modification_date: $memo";
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL . "
                SET last_modified = '$modification_date',
                    notify_version = '" . $this->moduleVersion . "',
                    memo = CONCAT(IFNULL(memo, ''), '$memo')
              WHERE order_id = $oID
                AND txn_type = 'CREATE'
              LIMIT 1"
        );
    }
}
