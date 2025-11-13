<?php
/**
 * BraintreeCommon
 *
 * A shared class to centralize common Braintree functions for all Braintree payment modules.
 */

use Braintree\Transaction;

class BraintreeCommon {
    protected $gateway;
    protected $config;
    public $debug_logging = false;
    protected $log_dir;
    protected $log_file;
    protected $tokenizationKey = '';

    /**
     * Normalize timeout values that may be provided in seconds or milliseconds.
     *
     * @param mixed $value Raw timeout configuration value.
     *
     * @return int|null Timeout value in whole seconds or null when not usable.
     */
    public static function normalize_timeout_seconds($value)
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $unitMultiplier = 1.0;
        if (preg_match('/ms$/i', $value)) {
            $unitMultiplier = 0.001;
            $value = preg_replace('/ms$/i', '', $value);
            $value = trim($value);
        } elseif (preg_match('/s$/i', $value)) {
            $value = preg_replace('/s$/i', '', $value);
            $value = trim($value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        if (!is_finite($numeric) || $numeric <= 0) {
            return null;
        }

        if ($unitMultiplier === 1.0 && $numeric >= 1000 && strpos($value, '.') === false) {
            $numeric = $numeric / 1000.0;
        } else {
            $numeric = $numeric * $unitMultiplier;
        }

        $seconds = (int) ceil($numeric);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Constructor.
     *
     * @param array $config Braintree API configuration.
     */
    public function __construct($config) {
        $this->config = $config;
        $this->tokenizationKey = isset($config['tokenization_key']) ? trim($config['tokenization_key']) : '';
        // Set debug_logging from the config if provided; otherwise, default to false.
        $this->debug_logging = isset($config['debug_logging']) ? $config['debug_logging'] : false;
        // Only initialize the gateway if required config values exist
        if (
            isset($config['environment']) &&
            isset($config['merchant_id']) &&
            isset($config['public_key']) &&
            isset($config['private_key'])
        ) {
            $this->gateway = $this->get_braintree_gateway();
        }
        $this->log_dir = defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_CATALOG . 'cache/';

        // Generate a single log file for the entire session
        $this->log_file = $this->log_dir . '/braintree_debug_' . date('Y-m-d_H-i') . '_' . zen_create_random_value(4) . '.log';
    }

    /**
     * Log debug messages.
     */
    protected function log_debug($stage, $data) {
        if (!$this->debug_logging) {
            return;
        }
        // Ensure that SDK objects (which may contain credentials) are never written directly to disk.
        $sanitizedData = $this->sanitize_log_data($data);
        $logData = "[" . date('Y-m-d H:i:s') . "] " . strtoupper($stage) . ":\n" . print_r($sanitizedData, true) . "\n";
        file_put_contents($this->log_file, $logData, FILE_APPEND);
    }

    /**
     * Sanitize data prior to writing to the debug log.
     *
     * @param mixed $data
     * @param int   $depth
     * @return mixed
     */
    protected function sanitize_log_data($data, $depth = 0) {
        if ($depth >= 5) {
            return '[maximum depth reached]';
        }

        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitize_log_data($value, $depth + 1);
            }
            return $sanitized;
        }

        if ($data instanceof \Throwable) {
            return $data->getMessage();
        }

        if (is_object($data)) {
            $class = get_class($data);

            if (strpos($class, 'Braintree\\') === 0) {
                return sprintf('[%s omitted from debug log]', $class);
            }

            if ($data instanceof \JsonSerializable) {
                return $this->sanitize_log_data($data->jsonSerialize(), $depth + 1);
            }

            if ($data instanceof \DateTimeInterface) {
                return $data->format(DATE_ATOM);
            }

            $objectVars = get_object_vars($data);
            if (!empty($objectVars)) {
                return $this->sanitize_log_data($objectVars, $depth + 1);
            }

            return sprintf('[%s object]', $class);
        }

        return $data;
    }

    /**
     * Mask a sensitive value, leaving the start and end visible.
     *
     * @param string $value
     * @param int    $visibleStart
     * @param int    $visibleEnd
     * @return string
     */
    protected function mask_sensitive_value($value, $visibleStart = 4, $visibleEnd = 4) {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        $length = strlen($value);
        $visible = $visibleStart + $visibleEnd;

        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        $start = substr($value, 0, $visibleStart);
        $end = substr($value, -$visibleEnd);

        return $start . str_repeat('*', $length - $visible) . $end;
    }

    /**
     * Create and return a Braintree Gateway instance.
     */
    public function get_braintree_gateway() {
        // $this->log_debug('get_braintree_gateway REQUEST', $this->config);
        $timeout = isset($this->config['timeout'])
            ? self::normalize_timeout_seconds($this->config['timeout'])
            : null;

        if (!is_int($timeout)) {
            $timeout = 0;
        }

        if ($timeout < 10) {
            $timeout = 10;
        }

        $gateway = new Braintree\Gateway([
            'environment' => $this->config['environment'],
            'merchantId'  => $this->config['merchant_id'],
            'publicKey'   => $this->config['public_key'],
            'privateKey'  => $this->config['private_key'],
            'timeout'     => $timeout,
        ]);
        return $gateway;
    }

    /**
     * Retrieve the configured tokenization key, if available.
     *
     * @return string
     */
    public function get_tokenization_key() {
        return $this->tokenizationKey;
    }

    /**
     * Generate a client token.
     */
    public function generate_client_token($merchantAccountId = null) {
        try {
            $params = [];
            if (!empty($merchantAccountId)) {
                $params['merchantAccountId'] = $merchantAccountId;
            }
            return $this->gateway->clientToken()->generate($params);
        } catch (\Braintree\Exception\Authentication $e) {
            error_log('Braintree Client Token Generation Error (Authentication): ' . $e->getMessage());
            return false; // Fail gracefully
        } catch (\Braintree\Exception $e) {
            error_log('Braintree Client Token Generation Error: ' . $e->getMessage());
            return false; // Fail gracefully
        } catch (\Exception $e) {
            error_log('Braintree General Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Merchant Account ID based on currency.
     */
    public function get_merchant_account_id($currency = null) {
        // Check if a merchant account ID has been explicitly configured
        if (defined('MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID') && 
            !empty(MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID)) {
            $configuredValue = trim(MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID);
            
            // Check if the configuration uses the currency:merchant_account format
            // e.g., "USD:merchant_usd,CAD:merchant_cad"
            if (strpos($configuredValue, ':') !== false) {
                // Parse the currency:merchant_account pairs
                $pairs = array_map('trim', explode(',', $configuredValue));
                $merchantAccountMap = [];
                
                foreach ($pairs as $pair) {
                    $parts = array_map('trim', explode(':', $pair, 2));
                    if (count($parts) === 2 && !empty($parts[0]) && !empty($parts[1])) {
                        $merchantAccountMap[strtoupper($parts[0])] = $parts[1];
                    }
                }
                
                // If a currency is provided, look for a matching merchant account
                if ($currency && isset($merchantAccountMap[strtoupper($currency)])) {
                    $accountId = $merchantAccountMap[strtoupper($currency)];
                    $this->log_debug('get_merchant_account_id', [
                        'source' => 'configuration_map',
                        'currency' => $currency,
                        'account_id' => $this->mask_sensitive_value($accountId)
                    ]);
                    $_SESSION['braintree_merchant_account_id'] = $accountId;
                    return $accountId;
                } else {
                    // Currency not found in map, fall through to auto-selection
                    $this->log_debug('get_merchant_account_id', [
                        'source' => 'configuration_map_fallback',
                        'currency' => $currency,
                        'note' => 'Currency not found in configured map, falling back to auto-selection'
                    ]);
                }
            } else {
                // Simple merchant account ID without currency mapping
                $this->log_debug('get_merchant_account_id', [
                    'source' => 'configuration_simple',
                    'account_id' => $this->mask_sensitive_value($configuredValue)
                ]);
                $_SESSION['braintree_merchant_account_id'] = $configuredValue;
                return $configuredValue;
            }
        }

        // Auto-select based on currency if no explicit configuration
        try {
            $merchantAccounts = $this->gateway->merchantAccount()->all();
            $sanitizedAccounts = [];
            $defaultAccountId = null;
            $matchedAccountId = null;
            foreach ($merchantAccounts as $account) {
                $sanitizedAccounts[] = [
                    'id' => $this->mask_sensitive_value($account->id),
                    'currencyIsoCode' => $account->currencyIsoCode,
                    'default' => (bool) $account->default
                ];
                if ($account->default) {
                    $_SESSION['braintree_merchant_account_id'] = $defaultAccountId = $account->id;
                }
                if ($currency && strtoupper($account->currencyIsoCode) === strtoupper($currency)) {
                    $matchedAccountId = $account->id;
                }
            }
            $this->log_debug('MERCHANT_ACCOUNTS_LIST', $sanitizedAccounts);

            if ($matchedAccountId) {
                $this->log_debug('get_merchant_account_id', [
                    'source' => 'currency_match',
                    'currency' => $currency,
                    'account_id' => $this->mask_sensitive_value($matchedAccountId)
                ]);
                $_SESSION['braintree_merchant_account_id'] = $matchedAccountId;
                return $matchedAccountId;
            }

            $this->log_debug('get_merchant_account_id', [
                'source' => 'default',
                'currency' => $currency,
                'default_account_id' => $this->mask_sensitive_value($defaultAccountId)
            ]);
            return $defaultAccountId;
        } catch (Exception $e) {
            $this->log_debug('get_merchant_account_id ERROR', $e->getMessage());
            return null;
        }
    }

    /**
     * Before processing the payment.
     */
    public function before_process_common($merchantAccountID = '') {
        global $messageStack;

        // Ensure we have a nonce
        if (empty($_SESSION['payment_method_nonce']) && !empty($_POST['payment_method_nonce'])) {
            $_SESSION['payment_method_nonce'] = $_POST['payment_method_nonce'];
        }

        if (empty($_SESSION['payment_method_nonce'])) {
            $messageStack->add_session('checkout_payment', 'Payment processing failed: No valid payment nonce.', 'error');
            $messageStack->add_session('header', 'Your payment was not processed. Please try again.', 'error');
            if ($this->is_ajax()) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Payment declined. Please try another card.'
                ]);
                exit;
            } else {
                zen_redirect(zen_href_link(defined('FILENAME_ONE_PAGE_CHECKOUT') ? FILENAME_ONE_PAGE_CHECKOUT : FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
        }

        $nonce = $_SESSION['payment_method_nonce'];

        // ? NEW: Pull merchant account ID from session if not explicitly provided
        if (empty($merchantAccountID) && !empty($_SESSION['braintree_merchant_account_id'])) {
            $merchantAccountID = $_SESSION['braintree_merchant_account_id'];
        }

        $moduleData = !empty($merchantAccountID) ? ['merchantAccountId' => $merchantAccountID] : [];

        // Log for debugging
        $this->log_debug('before_process_common', [
            'nonce' => $nonce,
            'moduleData' => $moduleData
        ]);

        // Process payment and store transaction ID in session
        $transaction = $this->process_braintree_payment($nonce, $moduleData);
        if ($transaction) {
            $_SESSION['braintree_transaction_id'] = $transaction['id'];
            $_SESSION['braintree_payment_status'] = $transaction['status'];
            $_SESSION['braintree_card_type'] = $transaction['cardType'];
            $_SESSION['braintree_currency'] = $transaction['currency'];
            $_SESSION['braintree_amount'] = $transaction['amount'];

            // ? Clean up the merchantAccountId after successful use
            unset($_SESSION['braintree_merchant_account_id']);
        } else {
            $messageStack->add_session('checkout_payment', 'Payment processing failed. Please try again.', 'error');
            $messageStack->add_session('header', 'Your payment was not processed. Please try again.', 'error');
            if ($this->is_ajax()) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Payment declined. Please try another card.'
                ]);
                exit;
            } else {
                zen_redirect(zen_href_link(defined('FILENAME_ONE_PAGE_CHECKOUT') ? FILENAME_ONE_PAGE_CHECKOUT : FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
        }
        return false;
    }

    /**
     * Process a Braintree payment.
     */
    public function process_braintree_payment($token, $moduleData = [], $submitForSettlement = true) {
        global $order, $messageStack;

        try {
            $amount = number_format($order->info['total'], 2, '.', '');
            
            // Log currency information for debugging
            $sessionCurrency = isset($_SESSION['currency']) ? $_SESSION['currency'] : 'unknown';
            $this->log_debug('process_braintree_payment CURRENCY INFO', [
                'session_currency' => $sessionCurrency,
                'order_amount' => $amount,
                'merchant_account_id' => isset($moduleData['merchantAccountId']) ? 
                    $this->mask_sensitive_value($moduleData['merchantAccountId']) : 'not set'
            ]);
            
            $transactionData = array_merge([
                'amount' => $amount,
                'customer' => [
                    'firstName' => $order->billing['firstname'] ?? '',
                    'lastName'  => $order->billing['lastname'] ?? '',
                    'email'     => $order->customer['email_address'] ?? ''
                ],
                'billing' => [
                    'streetAddress'   => $order->billing['street_address'] ?? '',
                    'locality'        => $order->billing['city'] ?? '',
                    'region'          => $order->billing['state'] ?? '',
                    'postalCode'      => $order->billing['postcode'] ?? '',
                    'countryCodeAlpha2' => $order->billing['country']['iso_code_2'] ?? ''
                ],
                'channel' => 'Numinix_BT',
                'options' => ['submitForSettlement' => $submitForSettlement],
                'paymentMethodNonce' => $token
            ], $moduleData);

            $this->log_debug('process_braintree_payment REQUEST', $transactionData);

            $result = $this->gateway->transaction()->sale($transactionData);
            unset($_SESSION['payment_method_nonce']);
            $this->log_debug('process_braintree_payment RESPONSE', $result);

            if ($result->success) {
                $transactionCurrency = $result->transaction->currencyIsoCode ?? '';
                $sessionCurrency = isset($_SESSION['currency']) ? $_SESSION['currency'] : '';
                
                // Log warning if currencies don't match
                if ($sessionCurrency && $transactionCurrency && 
                    strtoupper($sessionCurrency) !== strtoupper($transactionCurrency)) {
                    $this->log_debug('process_braintree_payment CURRENCY MISMATCH WARNING', [
                        'session_currency' => $sessionCurrency,
                        'transaction_currency' => $transactionCurrency,
                        'note' => 'The transaction was processed in a different currency than the store currency. This may indicate a merchant account configuration issue.'
                    ]);
                }
                
                return [
                    'id'       => $result->transaction->id,
                    'status'   => $result->transaction->status,
                    'cardType' => $result->transaction->creditCard['cardType'] ?? 'Unknown',
                    'currency' => $transactionCurrency,
                    'amount'   => $result->transaction->amount ?? 0
                ];
            } else {
                $reason = '';
                $errorDetails = [];

                if (!empty($result->transaction) && !empty($result->transaction->gatewayRejectionReason)) {
                    $reason = 'Transaction Rejected: ' . ucfirst($result->transaction->gatewayRejectionReason);
                } elseif (!empty($result->message)) {
                    $reason = 'Payment Failed: ' . $result->message;
                }

                // Add detailed errors from deepAll:
                if (!empty($result->errors)) {
                    foreach ($result->errors->deepAll() as $error) {
                        $errorDetails[] = "[{$error->code}] {$error->message}";
                    }
                    $reason .= ' | Braintree Errors: ' . implode('; ', $errorDetails);
                }

                // Log full error response
                $this->log_debug('process_braintree_payment ERROR', [
                    'reason' => $reason,
                    'errors' => $result->errors,
                    'full_result' => $result
                ]);

                $messageStack->add_session('checkout_payment', $reason, 'error');
                return false;
            }

        } catch (Exception $e) {
            $this->log_debug('process_braintree_payment ERROR', $e->getMessage());
            $messageStack->add_session('checkout_payment', 'Unexpected error during payment: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create Braintree transactions table if not exists.
     */
    public function create_braintree_table() {
        global $db;
        $check_query = $db->Execute("SHOW TABLES LIKE '" . TABLE_BRAINTREE . "'");
        if ($check_query->RecordCount() == 0) {
            $sql = "CREATE TABLE " . TABLE_BRAINTREE . " (
                braintree_id int(11) NOT NULL AUTO_INCREMENT,
                order_id int(11) NOT NULL,
                txn_type varchar(256) NOT NULL,
                txn_id varchar(256) NOT NULL,
                module_name varchar(256) NOT NULL,
                payment_type varchar(256) NOT NULL,
                payment_status varchar(256) NOT NULL,
                first_name varchar(256) NOT NULL DEFAULT '',
                last_name varchar(256) NOT NULL DEFAULT '',
                payer_business_name varchar(256) NOT NULL DEFAULT '',
                address_name varchar(256) NOT NULL DEFAULT '',
                address_street varchar(256) NOT NULL DEFAULT '',
                address_city varchar(256) NOT NULL DEFAULT '',
                address_state varchar(256) NOT NULL DEFAULT '',
                address_zip varchar(256) NOT NULL DEFAULT '',
                address_country varchar(256) NOT NULL DEFAULT '',
                payer_email varchar(256) NOT NULL DEFAULT '',
                payment_date date NOT NULL DEFAULT '0000-00-00',
                settle_amount decimal(15,4) NOT NULL DEFAULT 0,
                settle_currency varchar(10) NOT NULL DEFAULT '',
                exchange_rate decimal(15,6) NOT NULL DEFAULT 0,
                date_added datetime NOT NULL,
                module_mode varchar(256) NOT NULL DEFAULT '',
                PRIMARY KEY (braintree_id),
                UNIQUE KEY order_id (order_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
            $db->Execute($sql);
            $this->log_debug('create_braintree_table', 'Table Created');
        } else {
            $this->log_debug('create_braintree_table', 'Table Already Exists');
        }
    }

    /**
     * Get Transaction Details.
     */
    public function _GetTransactionDetails($oID) {
        global $db, $messageStack;

        // Check if gateway is available (module enabled)
        if (empty($this->gateway)) {
            return []; // Module disabled, safely skip
        }

        $txnID = $this->getTransactionId($oID);
        if (!$txnID) {
            $messageStack->add("Braintree Error: No transaction ID found for order ID: $oID", 'error');
            return [];
        }

        try {
            $transaction = $this->gateway->transaction()->find($txnID);

            // Convert Braintree\Transaction object into an array
            return [
                'FIRSTNAME'           => $transaction->customerDetails->firstName ?? '',
                'LASTNAME'            => $transaction->customerDetails->lastName ?? '',
                'BUSINESS'            => $transaction->billingDetails->company ?? '',
                'BILLTOSTREET'        => $transaction->billingDetails->streetAddress ?? '',
                'BILLTOSTREET2'       => $transaction->billingDetails->extendedAddress ?? '',
                'BILLTOCITY'          => $transaction->billingDetails->locality ?? '',
                'BILLTOSTATE'         => $transaction->billingDetails->region ?? '',
                'BILLTOZIP'           => $transaction->billingDetails->postalCode ?? '',
                'BILLTOCOUNTRY'       => $transaction->billingDetails->countryName ?? '',
                'TRANSACTIONID'       => $transaction->id ?? '',
                'PARENTTRANSACTIONID' => $transaction->refundedTransactionId ?? '',
                'TRANSACTIONTYPE'     => $transaction->type ?? '',
                'PAYMENTTYPE'         => $transaction->creditCardDetails->cardType ?? 'Unknown',
                'PAYMENTSTATUS'       => $transaction->status ?? 'Unknown',
                'ORDERTIME'           => $transaction->createdAt->format('Y-m-d H:i:s') ?? '',
                'CURRENCY'            => $transaction->currencyIsoCode ?? '',
                'AMT'                 => $transaction->amount ?? 0,
                'EXCHANGERATE'        => $transaction->disbursementDetails->settlementCurrencyExchangeRate ?? 'N/A',
                'EMAIL'               => $transaction->customerDetails->email ?? ''
            ];
        } catch (Exception $e) {
            $messageStack->add("Braintree Error: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Process a refund or void for a given order.
     */
    function _doRefund($oID, $amount = 'Full', $note = '') {
        global $db, $messageStack;
        try {
            // Get Transaction ID
            $txnID = $this->getTransactionId($oID);

            // Determine refund type (Full or Partial)
            $partialRefund = 0;
            if (isset($_POST['partialrefund']) && $_POST['partialrefund'] == MODULE_PAYMENT_BRAINTREE_ENTRY_REFUND_BUTTON_TEXT_PARTIAL) {
                $partialRefund = (float) $_POST['refamt'];
                if ($partialRefund <= 0) {
                    throw new Exception(MODULE_PAYMENT_BRAINTREE_TEXT_INVALID_REFUND_AMOUNT);
                }
            } elseif (!isset($_POST['reffullconfirm']) || $_POST['reffullconfirm'] != 'on') {
                throw new Exception(MODULE_PAYMENT_BRAINTREE_TEXT_REFUND_FULL_CONFIRM_ERROR);
            }

            // Get transaction details
            $brainTreeTxn = $this->gateway->transaction()->find($txnID);

            // Perform Refund or Void depending on transaction status
            if (in_array($brainTreeTxn->status, [Transaction::SUBMITTED_FOR_SETTLEMENT, Transaction::AUTHORIZED])) {
                $result = $this->gateway->transaction()->void($txnID);
            } elseif (in_array($brainTreeTxn->status, [Transaction::SETTLED, Transaction::SETTLING])) {
                $result = ($partialRefund > 0)
                    ? $this->gateway->transaction()->refund($txnID, $partialRefund)
                    : $this->gateway->transaction()->refund($txnID);
            } else {
                throw new Exception("Invalid status for Refund or Void");
            }

            // Check if refund was successful
            if (!$result->success) {
                $errorMessage = '';
                foreach ($result->errors->deepAll() as $error) {
                    $errorMessage .= ($error->code . ": " . $error->message . "<br>");
                }
                throw new Exception($errorMessage);
            }

            // Get Refund ID
            $refundId = !empty($result->refundIds) ? implode(",", $result->refundIds) : ($result->refundId ?? null);
            $refundAmount = $result->transaction->amount ?? $partialRefund;

            // Update order status
            $new_order_status = (int) MODULE_PAYMENT_BRAINTREE_REFUNDED_STATUS_ID;
            $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = '" . (int) $new_order_status . "' WHERE orders_id = '" . (int) $oID . "'");

            // Add order status history entry
            $refundNote = strip_tags(zen_db_input($_POST['refnote']));
            $historyData = [
                'orders_id' => $oID,
                'orders_status_id' => $new_order_status,
                'date_added' => 'now()',
                'customer_notified' => 0,
                'comments' => 'REFUND PROCESSED. Transaction ID: ' . $refundId . "\n" .
                              'Amount Refunded: ' . $refundAmount . "\n" .
                              'Note: ' . $refundNote
            ];
            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $historyData);

            $messageStack->add_session(sprintf(MODULE_PAYMENT_BRAINTREE_TEXT_REFUND_INITIATED, $refundAmount, $refundId), 'success');
            return true;

        } catch (Exception $e) {
            $messageStack->add_session($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update Order Status History.
     */
    public function updateOrderStatusHistory($order_id, $new_status, $comment) {
        global $db;
        $sql_data_array = [
            ['fieldName' => 'orders_id', 'value' => $order_id, 'type' => 'integer'],
            ['fieldName' => 'orders_status_id', 'value' => $new_status, 'type' => 'integer'],
            ['fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring'],
            ['fieldName' => 'customer_notified', 'value' => 0, 'type' => 'integer'],
            ['fieldName' => 'comments', 'value' => $comment, 'type' => 'string']
        ];
        $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        $this->log_debug('updateOrderStatusHistory', ['order_id' => $order_id, 'new_status' => $new_status, 'comment' => $comment]);
    }

    /**
     * Retrieve the transaction ID for the given order.
     *
     * @param int $orderId The order ID.
     * @return string|null The transaction ID or null if not found.
     */
    public function getTransactionId($orderId) {
        global $db;
        $sql = "SELECT txn_id FROM " . TABLE_BRAINTREE . " WHERE order_id = :orderId";
        $sql = $db->bindVars($sql, ':orderId', $orderId, 'integer');
        $result = $db->Execute($sql);
        if ($result->RecordCount() > 0) {
             return $result->fields['txn_id'];
        }
        return null;
    }

    /**
     * capturePayment
     *
     * Captures an authorized transaction by submitting it for settlement.
     * Updates the Braintree transaction record to mark the payment as Captured,
     * updates the main orders table to the provided paid status, and logs the change in the order history.
     *
     * @param int    $order_id  The order ID.
     * @param int    $paid_status The order status to set if capture is successful.
     * @param string $module      The module code (e.g., 'braintree_paypal').
     * @return bool True on successful capture, false otherwise.
     */
    public function capturePayment($order_id, $paid_status, $module) {
        global $db;
        // Retrieve the transaction details from TABLE_BRAINTREE
        $query = "SELECT txn_id, settle_amount FROM " . TABLE_BRAINTREE . " WHERE order_id = " . (int)$order_id;
        $result = $db->Execute($query);
        if ($result->RecordCount() == 0) {
            return false;
        }
        $txnId  = $result->fields['txn_id'];
        $amount = $result->fields['settle_amount'];

        try {
            // Capture the payment by submitting for settlement
            $captureResult = $this->gateway->transaction()->submitForSettlement($txnId, $amount);
            if (!$captureResult->success) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
        // Update the Braintree record to mark the payment as Captured
        $db->Execute("UPDATE " . TABLE_BRAINTREE . " SET payment_status = 'Captured' WHERE order_id = " . (int)$order_id);
        // Update the main orders table to the paid status
        $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . (int)$paid_status . " WHERE orders_id = " . (int)$order_id);
        // Insert a record into the orders status history table
        $comment = "Admin captured payment via Braintree. Transaction ID: " . $txnId;
        $sql_data_array = [
            ['fieldName' => 'orders_id', 'value' => $order_id, 'type' => 'integer'],
            ['fieldName' => 'orders_status_id', 'value' => $paid_status, 'type' => 'integer'],
            ['fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring'],
            ['fieldName' => 'customer_notified', 'value' => 0, 'type' => 'integer'],
            ['fieldName' => 'comments', 'value' => $comment, 'type' => 'string']
        ];
        $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        return true;
    }

    protected function is_ajax() {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }
}