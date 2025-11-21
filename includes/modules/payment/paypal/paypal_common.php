<?php
/**
 * PayPalCommon
 *
 * A shared class to centralize common PayPal functions for all PayPal payment modules.
 * This class contains helper methods used by paypalr and its variant modules
 * (paypalr_googlepay, paypalr_applepay, paypalr_venmo).
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;
use PayPalRestful\Common\VaultManager;

class PayPalCommon {
    /**
     * Reference to the parent payment module instance
     * @var object
     */
    protected $paymentModule;

    /**
     * Constructor
     *
     * @param object $paymentModule The payment module instance (paypalr or variant)
     */
    public function __construct($paymentModule) {
        $this->paymentModule = $paymentModule;
    }

    /**
     * Handle wallet payment confirmation for digital wallet modules
     * (Google Pay, Apple Pay, Venmo)
     *
     * @param string $walletType The wallet type (google_pay, apple_pay, venmo)
     * @param string $payloadFieldName The POST field name containing the wallet payload
     * @param array $errorMessages Array of error message constants
     * @return void
     */
    public function processWalletConfirmation($walletType, $payloadFieldName, $errorMessages) {
        global $messageStack;

        if ($messageStack->size('checkout_payment') > 0) {
            return;
        }

        $_POST['ppr_type'] = $walletType;
        $_SESSION['PayPalRestful']['ppr_type'] = $walletType;

        $payloadRaw = $_POST[$payloadFieldName] ?? '';
        if (trim($payloadRaw) === '') {
            $this->paymentModule->setMessageAndRedirect($errorMessages['payload_missing'], FILENAME_CHECKOUT_PAYMENT);
        }

        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
        }

        $paypal_order_created = $this->paymentModule->createPayPalOrder($walletType);
        if ($paypal_order_created === false) {
            $error_info = $this->paymentModule->ppr->getErrorInfo();
            $error_code = $error_info['details'][0]['issue'] ?? 'OTHER';
            $this->paymentModule->sendAlertEmail(
                MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN,
                MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATE . Logger::logJSON($error_info)
            );
            $this->paymentModule->setMessageAndRedirect(
                sprintf(MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE, $errorMessages['title'], $error_code),
                FILENAME_CHECKOUT_PAYMENT
            );
        }

        $confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
            $_SESSION['PayPalRestful']['Order']['id'],
            [$walletType => $payload]
        );
        if ($confirm_response === false) {
            $this->paymentModule->errorInfo->copyErrorInfo($this->paymentModule->ppr->getErrorInfo());
            $this->paymentModule->setMessageAndRedirect($errorMessages['confirm_failed'], FILENAME_CHECKOUT_PAYMENT);
        }

        $response_status = $confirm_response['status'] ?? '';
        if ($response_status === PayPalRestfulApi::STATUS_PAYER_ACTION_REQUIRED) {
            $this->paymentModule->log->write("pre_confirmation_check ($walletType) unexpected payer action requirement." . Logger::logJSON($confirm_response), true, 'after');
            $this->paymentModule->setMessageAndRedirect($errorMessages['payer_action'], FILENAME_CHECKOUT_PAYMENT);
        }

        $walletSuccessStatuses = [
            PayPalRestfulApi::STATUS_APPROVED,
            PayPalRestfulApi::STATUS_COMPLETED,
            PayPalRestfulApi::STATUS_CAPTURED,
        ];

        if ($response_status !== '' && in_array($response_status, $walletSuccessStatuses, true)) {
            $_SESSION['PayPalRestful']['Order']['status'] = $response_status;
        }

        $_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed'] = true;
        $_SESSION['PayPalRestful']['Order']['payment_source'] = $walletType;

        $this->paymentModule->log->write("pre_confirmation_check ($walletType) completed successfully.", true, 'after');
    }

    /**
     * Load wallet-specific language file
     *
     * @param string $code The module code (e.g., 'paypalr_googlepay')
     * @return void
     */
    public function loadWalletLanguageFile($code) {
        $language = $_SESSION['language'] ?? 'english';
        if (IS_ADMIN_FLAG === true) {
            $language = $_SESSION['admin_language'] ?? 'english';
        }
        
        $langFile = DIR_FS_CATALOG . rtrim(DIR_WS_LANGUAGES, '/') . '/' . $language . '/modules/payment/lang.' . $code . '.php';
        if (file_exists($langFile)) {
            $definitions = include $langFile;
            if (is_array($definitions)) {
                foreach ($definitions as $constant => $value) {
                    if (!defined($constant)) {
                        define($constant, $value);
                    }
                }
            }
        }
    }

    /**
     * Check if PayPal order records exist in the database
     *
     * @param int $orders_id The order ID
     * @return bool
     */
    public function paypalOrderRecordsExist(int $orders_id): bool
    {
        if ($orders_id <= 0) {
            return false;
        }

        global $db;
        $order_lookup = $db->Execute(
            "SELECT order_id FROM " . TABLE_PAYPAL . " WHERE order_id = " . (int)$orders_id . " LIMIT 1"
        );

        return ($order_lookup->EOF === false);
    }

    /**
     * Record PayPal order details in the database
     *
     * @param int $orders_id The order ID
     * @param array $orderInfo The order information from PayPal
     * @return void
     */
    public function recordPayPalOrderDetails(int $orders_id, array &$orderInfo): void
    {
        if ($orders_id <= 0) {
            return;
        }

        $orderInfo['orders_id'] = $orders_id;

        if ($this->paypalOrderRecordsExist($orders_id) === true) {
            return;
        }

        $purchase_unit = $orderInfo['purchase_units'][0];
        $address_info = [];
        if (isset($purchase_unit['shipping']['address'])) {
            $shipping_address = $purchase_unit['shipping']['address'];
            $address_street = $shipping_address['address_line_1'];
            if (!empty($shipping_address['address_line_2'])) {
                $address_street .= ', ' . $shipping_address['address_line_2'];
            }
            $address_street = substr($address_street, 0, 254);
            $address_info = [
                'address_name' => substr($purchase_unit['shipping']['name']['full_name'], 0, 64),
                'address_street' => $address_street,
                'address_city' => substr($shipping_address['admin_area_2'] ?? '', 0, 120),
                'address_state' => substr($shipping_address['admin_area_1'] ?? '', 0, 120),
                'address_zip' => substr($shipping_address['postal_code'] ?? '', 0, 10),
                'address_country' => substr($shipping_address['country_code'] ?? '', 0, 64),
            ];
        }

        $payment = $purchase_unit['payments']['captures'][0] ?? $purchase_unit['payments']['authorizations'][0];

        $payment_info = [];
        if (isset($payment['seller_receivable_breakdown'])) {
            $seller_receivable = $payment['seller_receivable_breakdown'];
            $payment_info = [
                'payment_date' => 'now()',
                'payment_gross' => $seller_receivable['gross_amount']['value'],
                'payment_fee' => $seller_receivable['paypal_fee']['value'],
                'settle_amount' => $seller_receivable['receivable_amount']['value'] ?? $seller_receivable['net_amount']['value'],
                'settle_currency' => $seller_receivable['receivable_amount']['currency_code'] ?? $seller_receivable['net_amount']['currency_code'],
                'exchange_rate' => $seller_receivable['exchange_rate']['value'] ?? 'null',
            ];
        }

        $payment_type = array_key_first($orderInfo['payment_source']);
        $orderInfo['payment_info'] = [
            'payment_type' => $payment_type,
            'amount' => $payment['amount']['value'] . ' ' . $payment['amount']['currency_code'],
            'created_date' => $payment['created_date'] ?? '',
        ];

        $payment_source = $orderInfo['payment_source'][$payment_type];
        $card_like_payment = in_array($payment_type, ['card', 'google_pay', 'apple_pay'], true);
        if ($card_like_payment === false) {
            $first_name = $payment_source['name']['given_name'];
            $last_name = $payment_source['name']['surname'];
            $email_address = $payment_source['email_address'];
            $payer_id = $orderInfo['payer']['payer_id'];
            $memo = [];
        } else {
            if (in_array($payment_type, ['google_pay', 'apple_pay'], true)) {
                $card_source = $payment_source['card'] ?? [];
                if (isset($payment_source['vault'])) {
                    $card_source['vault'] = $payment_source['vault'];
                }

                $name = $payment_source['name'] ?? [];
                if (is_array($name)) {
                    $first_name = $name['given_name'] ?? '';
                    $last_name = $name['surname'] ?? '';
                } else {
                    $first_name = $payment_source['name'] ?? '';
                    $last_name = '';
                }
                $email_address = $payment_source['email_address'] ?? '';
                $payer_id = '';
                $memo = ['source' => $payment_type];
            } else {
                $card_source = $payment_source;
                $first_name = $payment_source['name'] ?? '';
                $last_name = '';
                $email_address = '';
                $payer_id = '';
                $memo = [];
            }

            // Store vault card data if present
            $orderCustomerCache = $this->paymentModule->orderCustomerCache ?? [];
            $this->storeVaultCardData($orders_id, $card_source, $orderCustomerCache);
        }

        // Build and insert the database records
        // Insert two records like the base paypalr module:
        // 1. CREATE record (the PayPal order creation)
        // 2. CAPTURE/AUTHORIZE record (the payment transaction)
        global $db;
        
        $expiration_time = (isset($orderInfo['expiration_time'])) ? Helpers::convertPayPalDatePay2Db($orderInfo['expiration_time']) : 'null';
        $num_cart_items = $_SESSION['cart']->count_contents();
        
        // First record: CREATE
        $sql_data_array = [
            'order_id' => $orders_id,
            'txn_type' => 'CREATE',
            'module_name' => $this->paymentModule->code,
            'module_mode' => $orderInfo['txn_type'],
            'reason_code' => $payment['status_details']['reason'] ?? '',
            'payment_type' => $payment_type,
            'payment_status' => $orderInfo['payment_status'],
            'invoice' => $purchase_unit['invoice_id'] ?? $purchase_unit['custom_id'] ?? '',
            'mc_currency' => $payment['amount']['currency_code'],
            'first_name' => substr($first_name, 0, 32),
            'last_name' => substr($last_name, 0, 32),
            'payer_email' => $email_address,
            'payer_id' => $payer_id,
            'payer_status' => $orderInfo['payment_source'][$payment_type]['account_status'] ?? 'UNKNOWN',
            'receiver_email' => $purchase_unit['payee']['email_address'] ?? '',
            'receiver_id' => $purchase_unit['payee']['merchant_id'] ?? '',
            'txn_id' => $orderInfo['id'],
            'num_cart_items' => $num_cart_items,
            'mc_gross' => $payment['amount']['value'],
            'date_added' => 'now()',
            'last_modified' => 'now()',
            'notify_version' => $this->paymentModule->getCurrentVersion(),
            'expiration_time' => $expiration_time,
            'memo' => json_encode($memo),
        ];
        $sql_data_array = array_merge($sql_data_array, $address_info, $payment_info);
        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
        
        // Second record: CAPTURE or AUTHORIZE
        $sql_data_array = [
            'order_id' => $orders_id,
            'txn_type' => $orderInfo['txn_type'],
            'final_capture' => (int)($orderInfo['txn_type'] === 'CAPTURE'),
            'module_name' => $this->paymentModule->code,
            'module_mode' => '',
            'reason_code' => $payment['status_details']['reason'] ?? '',
            'payment_type' => $payment_type,
            'payment_status' => $payment['status'],
            'mc_currency' => $payment['amount']['currency_code'],
            'txn_id' => $payment['id'],
            'parent_txn_id' => $orderInfo['id'],
            'num_cart_items' => $num_cart_items,
            'mc_gross' => $payment['amount']['value'],
            'notify_version' => $this->paymentModule->getCurrentVersion(),
            'date_added' => 'now()',
            'last_modified' => 'now()',
            'expiration_time' => $expiration_time,
        ];
        $sql_data_array = array_merge($sql_data_array, $payment_info);
        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
        
        if ($orderInfo['txn_type'] === 'CAPTURE') {
            global $zco_notifier;
            $zco_notifier->notify('NOTIFY_PAYPALR_FUNDS_CAPTURED', $sql_data_array);
        }
        
        $orderInfo['admin_alert_needed'] = ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED);
    }

    /**
     * Store vaulted card data
     *
     * @param int $orders_id The order ID
     * @param array $card_source The card source data from PayPal
     * @param array &$orderCustomerCache Cache of order customer IDs
     * @return array|null
     */
    public function storeVaultCardData(int $orders_id, array $card_source, array &$orderCustomerCache): ?array
    {
        if (($card_source['vault']['id'] ?? '') === '') {
            return null;
        }

        $customers_id = $this->getCustomersIdForOrder($orders_id, $orderCustomerCache);
        if ($customers_id <= 0) {
            return null;
        }

        $storedVault = VaultManager::saveVaultedCard($customers_id, $orders_id, $card_source);
        if ($storedVault !== null) {
            $this->paymentModule->notify('NOTIFY_PAYPALR_VAULT_CARD_SAVED', $storedVault);
        }

        return $storedVault;
    }

    /**
     * Get customer ID for an order
     *
     * @param int $orders_id The order ID
     * @param array &$orderCustomerCache Cache of order customer IDs
     * @return int
     */
    public function getCustomersIdForOrder(int $orders_id, array &$orderCustomerCache): int
    {
        $orders_id = (int)$orders_id;
        if ($orders_id <= 0) {
            return 0;
        }

        if (isset($orderCustomerCache[$orders_id])) {
            return $orderCustomerCache[$orders_id];
        }

        global $db;

        $customerLookup = $db->Execute(
            "SELECT customers_id" .
            "   FROM " . TABLE_ORDERS .
            "  WHERE orders_id = $orders_id" .
            "  LIMIT 1"
        );

        $customers_id = ($customerLookup->EOF) ? 0 : (int)$customerLookup->fields['customers_id'];
        $orderCustomerCache[$orders_id] = $customers_id;

        return $customers_id;
    }

    /**
     * Send alert email to store owner
     *
     * @param string $subject_detail Subject detail
     * @param string $message Email message
     * @param bool $force_send Force sending even if alerts are disabled
     * @return void
     */
    public function sendAlertEmail(string $subject_detail, string $message, bool $force_send = false)
    {
        $emailAlerts = $this->paymentModule->emailAlerts ?? false;
        if ($emailAlerts === true || $force_send === true) {
            zen_mail(
                STORE_NAME,
                STORE_OWNER_EMAIL_ADDRESS,
                sprintf(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT, $subject_detail),
                $message,
                STORE_OWNER,
                STORE_OWNER_EMAIL_ADDRESS,
                ['EMAIL_MESSAGE_HTML' => nl2br($message, false)],
                'paymentalert'
            );
        }
    }

    /**
     * Common table checkup - ensures PayPal table exists
     *
     * @return void
     */
    public function tableCheckup()
    {
        global $db, $sniffer;
        if (!defined('TABLE_PAYPAL')) {
            define('TABLE_PAYPAL', DB_PREFIX . 'paypal');
        }
        $paypal_table = $db->Execute("SHOW TABLES LIKE '" . TABLE_PAYPAL . "'");
        if ($paypal_table->EOF) {
            $this->createPayPalTable();
        }
    }

    /**
     * Create PayPal tracking table
     *
     * @return void
     */
    protected function createPayPalTable()
    {
        global $db;
        $db->Execute(
            "CREATE TABLE " . TABLE_PAYPAL . " (
                paypal_ipn_id int(11) NOT NULL auto_increment,
                order_id int(11) NOT NULL default 0,
                txn_type varchar(40) NOT NULL default '',
                module_name varchar(40) NOT NULL default '',
                module_mode varchar(40) default NULL,
                reason_code varchar(40) default NULL,
                payment_type varchar(40) NOT NULL default '',
                payment_status varchar(32) NOT NULL default '',
                pending_reason varchar(64) default NULL,
                invoice varchar(128) default NULL,
                mc_currency char(3) NOT NULL default '',
                first_name varchar(32) NOT NULL default '',
                last_name varchar(32) NOT NULL default '',
                payer_business_name varchar(64) default NULL,
                address_name varchar(64) default NULL,
                address_street varchar(254) default NULL,
                address_city varchar(120) default NULL,
                address_state varchar(120) default NULL,
                address_zip varchar(10) default NULL,
                address_country varchar(64) default NULL,
                address_status varchar(11) default NULL,
                payer_email varchar(127) default NULL,
                payer_id varchar(32) NOT NULL default '',
                payer_status varchar(10) default NULL,
                payment_date datetime default NULL,
                business varchar(64) default NULL,
                receiver_email varchar(127) default NULL,
                receiver_id varchar(32) default NULL,
                txn_id varchar(20) NOT NULL default '',
                parent_txn_id varchar(20) default NULL,
                num_cart_items int(2) default 0,
                mc_gross decimal(15,4) default NULL,
                mc_fee decimal(15,4) default NULL,
                payment_gross decimal(15,4) default NULL,
                payment_fee decimal(15,4) default NULL,
                settle_amount decimal(15,4) default NULL,
                settle_currency char(3) default NULL,
                exchange_rate decimal(15,4) default NULL,
                notify_version varchar(20) NOT NULL default '',
                verify_sign varchar(127) default NULL,
                date_added datetime NOT NULL default '0001-01-01 00:00:00',
                last_modified datetime default NULL,
                expiration_time datetime default NULL,
                memo text,
                final_capture tinyint(1) NOT NULL default 0,
                PRIMARY KEY  (paypal_ipn_id),
                KEY idx_order_id_zen (order_id)
            )"
        );
    }

    /**
     * Common captureOrAuthorizePayment method for wallet modules
     * Wallet modules always use capture (final sale)
     *
     * @param PayPalRestfulApi $ppr PayPal REST API instance
     * @param Logger $log Logger instance
     * @param string $payment_source Payment source name (e.g., 'google_pay')
     * @return array|false
     */
    public function captureWalletPayment(PayPalRestfulApi $ppr, Logger $log, string $payment_source)
    {
        $paypal_order_id = $_SESSION['PayPalRestful']['Order']['id'];
        
        // Wallets always use capture (final sale)
        $response = $ppr->captureOrder($paypal_order_id);

        if ($response === false) {
            $log->write($payment_source . ': capture failed. ' . Logger::logJSON($ppr->getErrorInfo()));
            unset($_SESSION['PayPalRestful']['Order'], $_SESSION['payment']);
            return false;
        }

        return $response;
    }

    /**
     * Common after_process logic for recording orders
     *
     * @param array &$orderInfo Order information
     * @return void
     */
    public function processAfterOrder(array &$orderInfo)
    {
        $_SESSION['PayPalRestful']['CompletedOrders'] = ($_SESSION['PayPalRestful']['CompletedOrders'] ?? 0) + 1;

        $orders_id = (int)($orderInfo['orders_id'] ?? 0);
        $paypal_records_exist = ($orders_id > 0) ? $this->paypalOrderRecordsExist($orders_id) : false;
        
        if ($orders_id === 0 || $paypal_records_exist === false) {
            $orders_id = (int)($orders_id ?: ($_SESSION['order_number_created'] ?? 0));
            if ($orders_id > 0) {
                $this->recordPayPalOrderDetails($orders_id, $orderInfo);
            }
        }
    }

    /**
     * Common resetOrder logic
     *
     * @return void
     */
    public function resetOrder()
    {
        unset($_SESSION['PayPalRestful']['Order']);
        $_SESSION['PayPalRestful']['ppr_type'] = '';
        unset($_SESSION['ppcheckout']);
    }

    /**
     * Common createOrderGuid logic
     *
     * @param \order $order Order object
     * @param string $ppr_type Payment type
     * @return string
     */
    public function createOrderGuid($order, string $ppr_type): string
    {
        $orders_completed = $_SESSION['PayPalRestful']['CompletedOrders'] ?? 0;
        $guid_base = $_SESSION['customer_id'] . '-' . $_SESSION['cartID'] . '-' . $orders_completed . '-' . $ppr_type;

        $cart_hash = '';
        foreach ($order->products as $product) {
            $cart_hash .= $product['id'] . $product['qty'];
        }
        $cart_hash = md5($cart_hash);

        return substr($guid_base . '-' . $cart_hash, 0, 127);
    }

    /**
     * Common update order history logic
     *
     * @param array $orderInfo Order information
     * @param string $payment_type Payment type (e.g., 'google_pay', 'apple_pay', 'venmo')
     * @return void
     */
    public function updateOrderHistory(array $orderInfo, string $payment_type)
    {
        $payment_info = $orderInfo['payment_info'] ?? [];
        $timestamp = '';
        if (isset($payment_info['created_date']) && $payment_info['created_date'] !== '') {
            $timestamp = 'Timestamp: ' . $payment_info['created_date'] . "\n";
        }

        $message =
            MODULE_PAYMENT_PAYPALR_TRANSACTION_ID . ($orderInfo['id'] ?? '') . "\n" .
            sprintf(MODULE_PAYMENT_PAYPALR_TRANSACTION_TYPE, $payment_info['payment_type'] ?? $payment_type) . "\n" .
            $timestamp .
            MODULE_PAYMENT_PAYPALR_TRANSACTION_PAYMENT_STATUS . ($orderInfo['payment_status'] ?? '') . "\n" .
            MODULE_PAYMENT_PAYPALR_TRANSACTION_AMOUNT . ($payment_info['amount'] ?? '') . "\n";

        $payment_source_type = $orderInfo['payment_info']['payment_type'] ?? $payment_type;
        $message .= MODULE_PAYMENT_PAYPALR_FUNDING_SOURCE . $payment_source_type . "\n";

        if (!empty($orderInfo['payment_source'][$payment_source_type]['email_address'])) {
            $message .= MODULE_PAYMENT_PAYPALR_BUYER_EMAIL . $orderInfo['payment_source'][$payment_source_type]['email_address'] . "\n";
        }

        zen_update_orders_history($orderInfo['orders_id'], $message, null, -1, 0);

        if (($orderInfo['admin_alert_needed'] ?? false) === true) {
            $this->sendAlertEmail(
                MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN ?? 'Order Attention',
                sprintf(MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATION ?? 'Order %d created with status %s', $orderInfo['orders_id'], $orderInfo['paypal_payment_status'])
            );
        }
    }

    /**
     * Process credit card payment (capture or authorize)
     * 
     * @param PayPalRestfulApi $ppr PayPal API instance
     * @param Logger $log Logger instance
     * @param string $transaction_mode Transaction mode (Final Sale, Auth Only, etc.)
     * @param string $ppr_type Payment type (card, paypal, etc.)
     * @return array|false Response array or false on failure
     */
    public function processCreditCardPayment(PayPalRestfulApi $ppr, Logger $log, string $transaction_mode, string $ppr_type)
    {
        $paypal_order_id = $_SESSION['PayPalRestful']['Order']['id'] ?? '';

        if (empty($paypal_order_id)) {
            $log->write('Credit Card: No PayPal order ID found in session');
            return false;
        }

        $order_status = $_SESSION['PayPalRestful']['Order']['status'] ?? '';
        // Check for captures in the correct location - they're stored in the 'current' subkey
        $captures = $_SESSION['PayPalRestful']['Order']['current']['purchase_units'][0]['payments']['captures'] ?? [];

        if ($order_status === PayPalRestfulApi::STATUS_COMPLETED && $captures !== []) {
            $log->write('Credit Card: capture skipped; order was already completed during createOrder.');
            // Fetch the full order details from PayPal since we need the complete response structure
            // with all fields that the calling code expects
            $response = $ppr->getOrderStatus($paypal_order_id);
            if ($response === false) {
                $log->write('Credit Card: failed to fetch completed order details. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
            return $response;
        }

        // Determine if we should capture or authorize based on transaction mode
        $should_capture = ($transaction_mode === 'Final Sale' ||
                          ($ppr_type !== 'card' && $transaction_mode === 'Auth Only (Card-Only)'));

        if ($should_capture) {
            $response = $ppr->captureOrder($paypal_order_id);
            if ($response === false) {
                $log->write('Credit Card: capture failed. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
        } else {
            $response = $ppr->authorizeOrder($paypal_order_id);
            if ($response === false) {
                $log->write('Credit Card: authorization failed. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
        }

        return $response;
    }

    /**
     * Get vaulted cards for a customer
     *
     * @param int $customers_id Customer ID
     * @param bool $activeOnly If true, only return visible cards; if false, return all cards
     * @return array Array of vaulted card information
     */
    public function getVaultedCardsForCustomer(int $customers_id, bool $activeOnly = true): array
    {
        if ($customers_id <= 0) {
            return [];
        }

        global $db;
        
        $active_clause = $activeOnly ? ' AND visible = 1' : '';
        $vault_query = $db->Execute(
            "SELECT vault_id, status, last_digits, card_type, expiry, billing_name, 
                    billing_address_line_1, billing_address_line_2, billing_admin_area_2,
                    billing_admin_area_1, billing_postal_code, billing_country_code,
                    last_used, created_at, visible
             FROM " . TABLE_PAYPAL_VAULT . "
             WHERE customers_id = " . (int)$customers_id . 
             $active_clause . "
             ORDER BY last_used DESC, created_at DESC"
        );

        $cards = [];
        while (!$vault_query->EOF) {
            $cards[] = [
                'vault_id' => $vault_query->fields['vault_id'],
                'status' => $vault_query->fields['status'],
                'last_digits' => $vault_query->fields['last_digits'],
                'card_type' => $vault_query->fields['card_type'],
                'expiry' => $vault_query->fields['expiry'],
                'billing_address' => [
                    'name' => $vault_query->fields['billing_name'],
                    'address_line_1' => $vault_query->fields['billing_address_line_1'],
                    'address_line_2' => $vault_query->fields['billing_address_line_2'],
                    'admin_area_2' => $vault_query->fields['billing_admin_area_2'],
                    'admin_area_1' => $vault_query->fields['billing_admin_area_1'],
                    'postal_code' => $vault_query->fields['billing_postal_code'],
                    'country_code' => $vault_query->fields['billing_country_code'],
                ],
                'last_used' => $vault_query->fields['last_used'],
                'created_at' => $vault_query->fields['created_at'],
                'visible' => $vault_query->fields['visible'],
            ];
            $vault_query->MoveNext();
        }

        return $cards;
    }

    /**
     * Create PayPal order for payment processing
     * Shared by all payment modules
     *
     * @param object $paymentModule The payment module instance
     * @param object $order The Zen Cart order object
     * @param array $order_info Order totals information
     * @param string $ppr_type Payment type (card, paypal, google_pay, etc.)
     * @param object $currencies Currency object
     * @return bool True on success, false on failure
     */
    public function createPayPalOrder($paymentModule, $order, array $order_info, string $ppr_type, $currencies): bool
    {
        /** @var zcObserverPaypalrestful $zcObserverPaypalrestful */
        global $zcObserverPaypalrestful;

        // Create a GUID (Globally Unique IDentifier) for the order's current 'state'.
        $order_guid = $this->createOrderGuid($order, $ppr_type);

        // If a PayPal order already exists in the session for this GUID, reuse it.
        if (isset($_SESSION['PayPalRestful']['Order']['guid']) && $_SESSION['PayPalRestful']['Order']['guid'] === $order_guid) {
            return true;
        }

        $cc_info = property_exists($paymentModule, 'ccInfo') ? ($paymentModule->ccInfo ?? []) : [];
        $order_total_differences = (isset($zcObserverPaypalrestful) && is_object($zcObserverPaypalrestful))
            ? $zcObserverPaypalrestful->getOrderTotalChanges()
            : [];

        $create_order_request = new \PayPalRestful\Zc2Pp\CreatePayPalOrderRequest(
            $ppr_type,
            $order,
            $cc_info,
            $order_info,
            $order_total_differences
        );

        $order_amount_mismatch = $create_order_request->getBreakdownMismatch();
        if (count($order_amount_mismatch) !== 0) {
            $paymentModule->sendAlertEmail(
                MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_TOTAL_MISMATCH,
                MODULE_PAYMENT_PAYPALR_ALERT_TOTAL_MISMATCH . "\n\n" . Logger::logJSON($order_amount_mismatch)
            );
        }

        $paymentModule->ppr->setPayPalRequestId($order_guid);
        $order_request = $create_order_request->get();
        $paypal_order = $paymentModule->ppr->createOrder($order_request);

        if ($paypal_order === false) {
            if (isset($paymentModule->errorInfo)) {
                $paymentModule->errorInfo->copyErrorInfo($paymentModule->ppr->getErrorInfo());
            }
            return false;
        }

        $paypal_id = $paypal_order['id'];
        $status = $paypal_order['status'];
        unset(
            $paypal_order['id'],
            $paypal_order['status'],
            $paypal_order['create_time'],
            $paypal_order['links'],
            $paypal_order['purchase_units'][0]['reference_id'],
            $paypal_order['purchase_units'][0]['payee']
        );

        $_SESSION['PayPalRestful']['Order'] = [
            'current' => $paypal_order,
            'id' => $paypal_id,
            'status' => $status,
            'guid' => $order_guid,
            'payment_source' => $ppr_type,
            'amount_mismatch' => $order_amount_mismatch,
        ];

        return true;
    }

    /**
     * Process refund transaction
     * Shared by all payment modules
     *
     * @param int $oID Order ID
     * @param object $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @return mixed Refund result
     */
    public function processRefund($oID, $ppr, string $module_code)
    {
        if ($ppr === null) {
            return false;
        }

        $do_refund = new \PayPalRestful\Admin\DoRefund($oID, $ppr, $module_code);
        return $do_refund->process();
    }

    /**
     * Process authorization transaction  
     * Shared by all payment modules (returns false for modules that don't support it)
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @param float $order_amt Order amount
     * @param string $currency Currency code
     * @param bool $supports_auth Whether this module supports separate authorization
     * @return mixed Authorization result or false
     */
    public function processAuthorization($oID, $ppr, string $module_code, $order_amt, $currency, bool $supports_auth = false)
    {
        if (!$supports_auth) {
            return false;
        }

        if ($ppr === null) {
            return false;
        }

        $do_auth = new \PayPalRestful\Admin\DoAuthorization($oID, $ppr, $module_code);
        return $do_auth->process($order_amt, $currency);
    }

    /**
     * Process capture transaction
     * Shared by all payment modules
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @param string $captureType Capture type (Complete, etc.)
     * @param float $order_amt Order amount
     * @param string $order_currency Currency code
     * @return mixed Capture result
     */
    public function processCapture($oID, $ppr, string $module_code, string $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        if ($ppr === null) {
            return false;
        }

        $do_capture = new \PayPalRestful\Admin\DoCapture($oID, $ppr, $module_code);
        return $do_capture->process($captureType, $order_amt, $order_currency);
    }

    /**
     * Process void transaction
     * Shared by all payment modules
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @return mixed Void result
     */
    public function processVoid($oID, $ppr, string $module_code)
    {
        if ($ppr === null) {
            return false;
        }

        $do_void = new \PayPalRestful\Admin\DoVoid($oID, $ppr, $module_code);
        return $do_void->process();
    }
}
