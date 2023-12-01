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
use PayPalRestful\Common\Logger;

class GetTransactions
{
    protected $moduleName;

    protected int $oID;

    protected PayPalRestfulApi $ppr;

    protected logger $log;

    protected array $databaseTxns = [];

    protected Messages $messages;

    protected string $paymentType;

    protected array $paypalTransactions = [];

    public function __construct(string $module_name, int $oID, PayPalRestfulApi $ppr)
    {
        $this->moduleName = $module_name;
        $this->oID = $oID;
        $this->ppr = $ppr;

        $this->log = new Logger();
        $this->messages = new Messages();

        $this->databaseTxns = $this->getPayPalDatabaseTransactionsForOrder($module_name);
        if (count($this->databaseTxns) === 0) {
            return;
        }

        $this->getPayPalUpdates($oID);

        if ($this->messages->size !== 0) {
            $this->databaseTxns = $this->getPayPalDatabaseTransactionsForOrder($module_name);
        }
    }

    public function getDatabaseTxns(): array
    {
        return $this->databaseTxns;
    }

    public function getPaypalTxns(): array
    {
        return $this->paypalTransactions;
    }

    public function getMessages(): string
    {
        return $this->messages->output();
    }

    protected function getPayPalDatabaseTransactionsForOrder(string $module_name): array
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
        $txns = $db->ExecuteNoCache(
            "SELECT *
               FROM " . TABLE_PAYPAL . "
              WHERE order_id = {$this->oID}
                AND module_name = '$module_name'
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
        $parent_txn_id = $this->databaseTxns[0]['txn_id'];
        $txns = $this->ppr->getOrderStatus($parent_txn_id);
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
            $parent_txn_id = $this->getParentTxnId($next_authorization['links']);
            if ($this->transactionExists($parent_txn_id, $authorization_txn_id) === true) {
                continue;
            }
            $this->addTransaction('AUTHORIZE', $parent_txn_id, $authorization_txn_id, $next_authorization);
        }
    }

    protected function updateCaptures(array $captures)
    {
        foreach ($captures as $next_capture) {
            $capture_txn_id = $next_capture['id'];
            $parent_txn_id = $this->getParentTxnId($next_capture['links']);
            if ($this->transactionExists($parent_txn_id, $capture_txn_id) === true) {
                continue;
            }
            $this->addTransaction('CAPTURE', $parent_txn_id, $capture_txn_id, $next_capture);
        }
    }

    protected function updateRefunds(array $refunds)
    {
        foreach ($refunds as $next_refund) {
            $refund_txn_id = $next_refund['id'];
            $parent_txn_id = $this->getParentTxnId($next_refund['links']);
            if ($this->transactionExists($parent_txn_id, $refund_txn_id) === true) {
                continue;
            }
        }
        $this->addTransaction('REFUND', $parent_txn_id, $refund_txn_id, $next_refund);
    }

    protected function getParentTxnId(array $links): string
    {
        $parent_txn_id = '';
        foreach ($links as $next_link) {
            if ($next_link['rel'] === 'up') {
                $pieces = explode('/', $next_link['href']);
                $parent_txn_id = end($pieces);
                break;
            }
        }
        return $parent_txn_id;
    }

    protected function transactionExists(string $parent_txn_id, string $txn_id): bool
    {
        $txn_exists = false;
        foreach ($this->databaseTxns as $next_txn) {
            if ($next_txn['txn_id'] === $txn_id && $next_txn['parent_txn_id'] === $parent_txn_id) {
                $txn_exists = true;
                break;
            }
        }
        return $txn_exists;
    }

    protected function addTransaction(string $txn_type, string $parent_txn_id, string $txn_id, array $paypal_response)
    {
        $this->log->write("addTransaction($txn_type, $parent_txn_id, $txn_id):\n" . Logger::logJSON($paypal_response));

        $date_added = convertToLocalTimeZone(trim(preg_replace('/[^0-9-:]/', ' ', $paypal_response['create_time'])));

        $payment_info = $this->getPaymentInfo($paypal_response);
        if ($txn_type === 'CAPTURE' && count($payment_info) !== 0) {
            $payment_info['payment_date'] = $date_added;
        }

        $note_to_payer = $paypal_response['note_to_payer'] ?? '';
        if ($note_to_payer !== '') {
            $note_to_payer = "\n\nPayment Note: $note_to_payer";
        }

        $sql_data_array = [
            'order_id' => $this->oID,
            'txn_type' => $txn_type,
            'module_name' => $this->moduleName,
            'module_mode' => '',
            'reason_code' => $paypal_response['status_details']['reason'] ?? '',
            'payment_type' => $this->paymentType,
            'payment_status' => $paypal_response['status'],
            'invoice' => $this->orderInfo['invoice_id'] ?? $this->order_info['custom_id'] ?? '',
            'mc_currency' => $paypal_response['amount']['currency_code'],
            'txn_id' => $txn_id,
            'parent_txn_id' => $parent_txn_id,
            'mc_gross' => $paypal_response['amount']['value'],
            'date_added' => $date_added,
            'last_modified' => convertToLocalTimeZone(trim(preg_replace('/[^0-9-:]/', ' ', $paypal_response['update_time']))),
            'memo' => 'Added during PayPal Management Console action' . $note_to_payer,
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

        //- FIXME, refunds don't include exchange-rate; that's set when the payment is captured
        return [
            'payment_gross' => $payment_info['gross_amount']['value'],
            'payment_fee' => $payment_info['paypal_fee']['value'],
            'settle_amount' => $payment_info['receivable_amount']['value'] ?? $payment_info['net_amount']['value'],
            'settle_currency' => $payment_info['receivable_amount']['currency_code'] ?? $payment_info['net_amount']['currency_code'],
            'exchange_rate' => $payment_info['exchange_rate']['value'] ?? 'null',
        ];
    }
}
