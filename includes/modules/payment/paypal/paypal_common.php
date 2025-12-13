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
        $payload = null;

        if (trim($payloadRaw) === '') {
            $payload = $_SESSION['PayPalRestful']['WalletPayload'][$walletType] ?? null;

            if (is_array($payload)) {
                $this->paymentModule->log->write("Using cached {$walletType} payload from session", true, 'after');
            }
        } else {
            $payload = json_decode($payloadRaw, true);

            if (!is_array($payload)) {
                $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
            }
        }

        if (!is_array($payload)) {
            $this->paymentModule->setMessageAndRedirect($errorMessages['payload_missing'], FILENAME_CHECKOUT_PAYMENT);
        }

        $payload = $this->normalizeWalletPayload($walletType, $payload, $errorMessages);
        $_SESSION['PayPalRestful']['WalletPayload'][$walletType] = $payload;

        // -----------------------------------------------------------------
        // Apple Pay: Create order with token, skip confirmPaymentSource
        // to avoid PayPal 500 INTERNAL_SERVICE_ERROR issues.
        // -----------------------------------------------------------------
        if ($walletType === 'apple_pay') {
            // Ensure we DO NOT reuse the initial "button click" order.
            unset($_SESSION['PayPalRestful']['Order']);

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

            // Skip confirmPaymentSource for Apple Pay (avoids PayPal 500s).
            $_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed'] = true;
            $_SESSION['PayPalRestful']['Order']['payment_source'] = $walletType;

            $this->paymentModule->log->write(
                "pre_confirmation_check ($walletType) completed via createOrder-only flow (skipped confirmPaymentSource).",
                true,
                'after'
            );

            return;
        }

        // -----------------------------------------------------------------
        // Other Wallets (Google Pay, Venmo, etc):
        // Use the standard createOrder + confirmPaymentSource flow.
        // -----------------------------------------------------------------
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

        // Retry once on transient PayPal 500 errors (INTERNAL_SERVICE_ERROR)
        if ($confirm_response === false) {
            $error_info = $this->paymentModule->ppr->getErrorInfo();
            $errNum = $error_info['errNum'] ?? 0;
            $issue = $error_info['details'][0]['issue'] ?? '';
            $debug_id = $error_info['debug_id'] ?? '';

            if ($errNum === 500 || strcasecmp($issue, 'INTERNAL_SERVICE_ERROR') === 0) {
                $this->paymentModule->log->write(
                    "confirmPaymentSource ($walletType) received INTERNAL_SERVICE_ERROR; retrying once.\n" .
                    ($debug_id !== '' ? "  PayPal Debug ID: $debug_id" : ''),
                    true,
                    'after'
                );

                $confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
                    $_SESSION['PayPalRestful']['Order']['id'],
                    [$walletType => $payload]
                );
            }
        }

        if ($confirm_response === false) {
            $this->paymentModule->getErrorInfo()->copyErrorInfo($this->paymentModule->ppr->getErrorInfo());
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
     * Normalize wallet payloads prior to confirming the payment source.
     *
     * Apple Pay's payment token is an associative array when posted from the
     * browser. PayPal's /confirm-payment-source endpoint expects the token to
     * be a JSON string, not an array. Additionally, Apple Pay contact information
     * needs to be transformed into PayPal's expected format.
     */
    protected function normalizeWalletPayload(string $walletType, array $payload, array $errorMessages): array
    {
        if ($walletType === 'apple_pay') {
            // For Apple Pay confirmPaymentSource, PayPal only accepts the token field.
            // Contact information (name, email, billing_address) should NOT be included
            // in the payment_source as PayPal rejects them with MALFORMED_REQUEST_JSON.
            // The contact info is already in the order from createOrder.

            if (isset($payload['token'])) {
                $token = $payload['token'];

                if (is_string($token)) {
                    $decodedToken = json_decode($token, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->paymentModule->log->write(
                            "Apple Pay: Token string is not valid JSON.",
                            true,
                            'after'
                        );
                        $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
                    }

                    $token = $decodedToken;
                }

                if (is_array($token) && isset($token['paymentData']) && is_array($token['paymentData'])) {
                    $token = $token['paymentData'];
                }

                if (!is_array($token) || !isset($token['data'], $token['signature'], $token['header'], $token['version'])) {
                    $this->paymentModule->log->write(
                        "Apple Pay: Token payload missing required fields.",
                        true,
                        'after'
                    );
                    $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
                }

                // Encode token to JSON string for confirmPaymentSource
                $encodedToken = json_encode($token);
                if ($encodedToken === false) {
                    $jsonError = function_exists('json_last_error_msg') ? json_last_error_msg() : 'unknown error';
                    $this->paymentModule->log->write(
                        "Apple Pay: Failed to encode token payload ({$jsonError}).",
                        true,
                        'after'
                    );
                    $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
                }

                $payload['token'] = $encodedToken;
            }

            // Validate token is present
            if (!isset($payload['token']) || $payload['token'] === '') {
                $this->paymentModule->log->write(
                    "Apple Pay: Payment token is missing from payload.",
                    true,
                    'after'
                );
                $this->paymentModule->setMessageAndRedirect($errorMessages['payload_invalid'], FILENAME_CHECKOUT_PAYMENT);
            }

            // Return only the token field
            return ['token' => $payload['token']];
        }

        return $payload;
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
                $payment_method = $this->getPaymentMethodDisplayName($orderInfo);
                if ($payment_method !== '') {
                    $this->updateOrderPaymentMethod($orders_id, $payment_method);
                    $orderInfo['payment_method'] = $payment_method;
                }
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

        $wallet_payload_hash = '';
        if (in_array($ppr_type, ['apple_pay', 'google_pay', 'venmo'], true)) {
            $wallet_payload = $_SESSION['PayPalRestful']['WalletPayload'][$ppr_type] ?? null;
            if (is_array($wallet_payload) && !empty($wallet_payload)) {
                $wallet_payload_hash = md5(json_encode($wallet_payload));
            }
        }

        return substr(
            $guid_base
            . '-' . $cart_hash
            . ($wallet_payload_hash !== '' ? '-' . $wallet_payload_hash : ''),
            0,
            127
        );
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
     * Normalize and save the payment method used for an order.
     *
     * @param int $orders_id The order ID
     * @param string $payment_method The payment method display name
     * @return void
     */
    public function updateOrderPaymentMethod(int $orders_id, string $payment_method): void
    {
        $orders_id = (int)$orders_id;
        $payment_method = trim($payment_method);

        if ($orders_id <= 0 || $payment_method === '') {
            return;
        }

        zen_db_perform(
            TABLE_ORDERS,
            ['payment_method' => $payment_method],
            'update',
            'orders_id = ' . $orders_id
        );
    }

    /**
     * Determine the appropriate payment method label from a PayPal order response.
     *
     * @param array $orderInfo PayPal order response array
     * @return string
     */
    public function getPaymentMethodDisplayName(array $orderInfo): string
    {
        $payment_source = $orderInfo['payment_source'] ?? [];
        if (empty($payment_source)) {
            return '';
        }

        $payment_type = array_key_first($payment_source);
        if ($payment_type === null) {
            return '';
        }

        $payment_type_lower = strtolower($payment_type);
        $wallet_map = [
            'paypal' => 'PayPal',
            'google_pay' => 'Google Pay',
            'apple_pay' => 'Apple Pay',
            'venmo' => 'Venmo',
        ];

        if (isset($wallet_map[$payment_type_lower])) {
            return $wallet_map[$payment_type_lower];
        }

        $payment_details = $payment_source[$payment_type] ?? [];

        if ($payment_type_lower === 'card') {
            $brand = $payment_details['brand'] ?? ($payment_details['card_type'] ?? ($payment_details['type'] ?? ''));
            $normalized_brand = $this->normalizeCardBrand($brand);
            if ($normalized_brand !== '') {
                return $normalized_brand;
            }
        }

        if (isset($payment_details['brand'])) {
            $normalized_brand = $this->normalizeCardBrand($payment_details['brand']);
            if ($normalized_brand !== '') {
                return $normalized_brand;
            }
        }

        $fallback_method = str_replace('_', ' ', $payment_type);
        return ucwords($fallback_method);
    }

    /**
     * Normalize card brand names to the desired display labels.
     *
     * @param string $brand Raw brand name from PayPal
     * @return string
     */
    protected function normalizeCardBrand(string $brand): string
    {
        $brand = trim($brand);
        if ($brand === '') {
            return '';
        }

        $brand_lower = strtolower($brand);
        $brand_map = [
            'visa' => 'Visa',
            'mastercard' => 'MasterCard',
            'master card' => 'MasterCard',
            'amex' => 'American Express',
            'american express' => 'American Express',
            'diners' => "Diner's Club",
            'diners club' => "Diner's Club",
            'dinersclub' => "Diner's Club",
            'discover' => 'Discover Card',
            'discover card' => 'Discover Card',
            'jbl' => 'JBL',
            'jcb' => 'JCB',
        ];

        return $brand_map[$brand_lower] ?? ucwords($brand_lower);
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

        $log->write(
            "processCreditCardPayment($ppr_type) starting.\n" .
            "  PayPal Order ID: " . ($paypal_order_id ?: 'NOT SET') . "\n" .
            "  Transaction Mode: $transaction_mode\n" .
            "  Session payment source: " . ($_SESSION['PayPalRestful']['Order']['payment_source'] ?? 'not set')
        );

        if (empty($paypal_order_id)) {
            $log->write('processCreditCardPayment: FAILED - No PayPal order ID found in session');
            return false;
        }

        $order_status = $_SESSION['PayPalRestful']['Order']['status'] ?? '';
        // Check for captures and authorizations in the correct location - they're stored in the 'current' subkey
        $captures = $_SESSION['PayPalRestful']['Order']['current']['purchase_units'][0]['payments']['captures'] ?? [];
        $authorizations = $_SESSION['PayPalRestful']['Order']['current']['purchase_units'][0]['payments']['authorizations'] ?? [];

        $log->write(
            "processCreditCardPayment: Order status check.\n" .
            "  Order status: $order_status\n" .
            "  Has captures: " . (!empty($captures) ? 'yes (' . count($captures) . ')' : 'no') . "\n" .
            "  Has authorizations: " . (!empty($authorizations) ? 'yes (' . count($authorizations) . ')' : 'no')
        );

        // If the order was already completed (captured or authorized) during createOrder,
        // skip the duplicate capture/authorize call and fetch the order details instead.
        // This can happen with vault-enabled credit cards where PayPal completes the
        // authorization during createOrder.
        if ($order_status === PayPalRestfulApi::STATUS_COMPLETED && ($captures !== [] || $authorizations !== [])) {
            $skip_reason = ($captures !== []) ? 'already captured' : 'already authorized';
            $log->write("processCreditCardPayment: Capture/authorize skipped; order was $skip_reason during createOrder.");
            // Fetch the full order details from PayPal since we need the complete response structure
            // with all fields that the calling code expects
            $response = $ppr->getOrderStatus($paypal_order_id);
            if ($response === false) {
                $log->write('processCreditCardPayment: FAILED to fetch completed order details. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
            $log->write("processCreditCardPayment: Successfully fetched existing order details. Status: " . ($response['status'] ?? 'unknown'));
            return $response;
        }

        // Determine if we should capture or authorize based on transaction mode
        $should_capture = ($transaction_mode === 'Final Sale' ||
                          ($ppr_type !== 'card' && $transaction_mode === 'Auth Only (Card-Only)'));

        $log->write("processCreditCardPayment: Will " . ($should_capture ? 'CAPTURE' : 'AUTHORIZE') . " the order.");

        if ($should_capture) {
            $response = $ppr->captureOrder($paypal_order_id);
            if ($response === false) {
                $log->write('processCreditCardPayment: CAPTURE FAILED. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
            $log->write("processCreditCardPayment: CAPTURE successful. Status: " . ($response['status'] ?? 'unknown'));
        } else {
            $response = $ppr->authorizeOrder($paypal_order_id);
            if ($response === false) {
                $log->write('processCreditCardPayment: AUTHORIZATION FAILED. ' . Logger::logJSON($ppr->getErrorInfo()));
                return false;
            }
            $log->write("processCreditCardPayment: AUTHORIZATION successful. Status: " . ($response['status'] ?? 'unknown'));
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
        return VaultManager::getCustomerVaultedCards($customers_id, $activeOnly);
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

        $log = new Logger();

        // Create a GUID (Globally Unique IDentifier) for the order's current 'state'.
        $order_guid = $this->createOrderGuid($order, $ppr_type);

        // If a PayPal order already exists in the session for this GUID, reuse it.
        if (isset($_SESSION['PayPalRestful']['Order']['guid']) && $_SESSION['PayPalRestful']['Order']['guid'] === $order_guid) {
            $log->write("createPayPalOrder($ppr_type): Reusing existing PayPal order with GUID: $order_guid");
            return true;
        }

        // Get credit card info using the public getter method if available,
        // otherwise fall back to direct property access (for backward compatibility).
        // The getter method is required because ccInfo is a protected property
        // that cannot be accessed directly from this class.
        if (method_exists($paymentModule, 'getCcInfo')) {
            $cc_info = $paymentModule->getCcInfo();
        } else {
            // Fallback for modules that don't have the getter (e.g., paypalr main module)
            $cc_info = property_exists($paymentModule, 'ccInfo') ? ($paymentModule->ccInfo ?? []) : [];
        }

        // Log the cc_info data for debugging (mask sensitive data)
        $cc_info_debug = [];
        if (!empty($cc_info)) {
            $cc_info_debug = [
                'has_vault_id' => !empty($cc_info['vault_id']),
                'type' => $cc_info['type'] ?? null,
                'last_digits' => $cc_info['last_digits'] ?? null,
                'has_number' => !empty($cc_info['number']),
                'has_security_code' => !empty($cc_info['security_code']),
                'use_vault' => $cc_info['use_vault'] ?? false,
                'store_card' => $cc_info['store_card'] ?? false,
            ];
        }
        $log->write(
            "createPayPalOrder($ppr_type): Building PayPal order request.\n" .
            "  GUID: $order_guid\n" .
            "  Module: " . ($paymentModule->code ?? 'unknown') . "\n" .
            "  CC Info: " . Logger::logJSON($cc_info_debug)
        );

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

        // Log the payment source being sent to PayPal
        $payment_source = $order_request['payment_source'] ?? [];
        $payment_source_type = '';
        $has_vault_id = 'n/a';

        if (is_array($payment_source) && !empty($payment_source)) {
            $payment_source_type = array_key_first($payment_source);
            $has_vault_id = (!empty($payment_source[$payment_source_type]['vault_id']) ? 'yes' : 'no');
        }

        $log->write(
            "createPayPalOrder($ppr_type): Sending order to PayPal.\n" .
            "  Payment source type: $payment_source_type\n" .
            "  Has vault_id in source: $has_vault_id"
        );

        $paypal_order = $paymentModule->ppr->createOrder($order_request);

        if ($paypal_order === false) {
            $error_info = $paymentModule->ppr->getErrorInfo();
            $log->write(
                "createPayPalOrder($ppr_type): PayPal order creation FAILED.\n" .
                "  Error: " . Logger::logJSON($error_info)
            );
            if (method_exists($paymentModule, 'getErrorInfo')) {
                $paymentModule->getErrorInfo()->copyErrorInfo($error_info);
            }
            return false;
        }

        $paypal_id = $paypal_order['id'];
        $status = $paypal_order['status'];

        $log->write(
            "createPayPalOrder($ppr_type): PayPal order created successfully.\n" .
            "  PayPal Order ID: $paypal_id\n" .
            "  Status: $status"
        );

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
     * @param string $module_version Module version
     * @return mixed Refund result
     */
    public function processRefund($oID, $ppr, string $module_code, string $module_version)
    {
        if ($ppr === null) {
            return false;
        }

        $do_refund = new \PayPalRestful\Admin\DoRefund($oID, $ppr, $module_code, $module_version);
        return true;
    }

    /**
     * Process authorization transaction  
     * Shared by all payment modules (returns false for modules that don't support it)
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @param string $module_version Module version
     * @param float $order_amt Order amount
     * @param string $currency Currency code
     * @param bool $supports_auth Whether this module supports separate authorization
     * @return mixed Authorization result or false
     */
    public function processAuthorization($oID, $ppr, string $module_code, string $module_version, $order_amt, $currency, bool $supports_auth = false)
    {
        if (!$supports_auth) {
            return false;
        }

        if ($ppr === null) {
            return false;
        }

        $do_auth = new \PayPalRestful\Admin\DoAuthorization($oID, $ppr, $module_code, $module_version);
        return true;
    }

    /**
     * Process capture transaction
     * Shared by all payment modules
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @param string $module_version Module version
     * @param string $captureType Capture type (Complete, etc.)
     * @param float $order_amt Order amount
     * @param string $order_currency Currency code
     * @return mixed Capture result
     */
    public function processCapture($oID, $ppr, string $module_code, string $module_version, string $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        if ($ppr === null) {
            return false;
        }

        $do_capture = new \PayPalRestful\Admin\DoCapture($oID, $ppr, $module_code, $module_version);
        return true;
    }

    /**
     * Process void transaction
     * Shared by all payment modules
     *
     * @param int $oID Order ID
     * @param object|null $ppr PayPal API instance
     * @param string $module_code Module code for logging
     * @param string $module_version Module version
     * @return mixed Void result
     */
    public function processVoid($oID, $ppr, string $module_code, string $module_version)
    {
        if ($ppr === null) {
            return false;
        }

        $do_void = new \PayPalRestful\Admin\DoVoid($oID, $ppr, $module_code, $module_version);
        return true;
    }
}
