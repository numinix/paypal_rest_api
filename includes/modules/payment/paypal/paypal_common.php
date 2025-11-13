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
                'payment_date' => Helpers::convertPayPalDatePay2Db($payment['create_time']),
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
            $this->storeVaultCardData($orders_id, $card_source, $this->paymentModule->orderCustomerCache ?? []);
        }

        // Build and insert the database record
        global $db;
        
        $sql_data_array = array_merge(
            [
                'order_id' => $orders_id,
                'txn_type' => $orderInfo['txn_type'],
                'module_name' => $this->paymentModule->title,
                'module_mode' => MODULE_PAYMENT_PAYPALR_SERVER,
                'reason_code' => null,
                'payment_type' => $payment_type,
                'payment_status' => $orderInfo['payment_status'],
                'pending_reason' => $payment['status_details']['reason'] ?? null,
                'invoice' => $orderInfo['purchase_units'][0]['invoice_id'] ?? null,
                'first_name' => substr($first_name, 0, 32),
                'last_name' => substr($last_name, 0, 32),
                'payer_business_name' => null,
                'payer_email' => substr($email_address, 0, 127),
                'payer_id' => substr($payer_id, 0, 32),
                'payer_status' => null,
                'mc_currency' => $payment['amount']['currency_code'],
                'mc_gross' => $payment['amount']['value'],
                'mc_fee' => $payment_info['payment_fee'] ?? 0,
                'txn_id' => substr($payment['id'], 0, 20),
                'parent_txn_id' => null,
                'memo' => (count($memo) === 0) ? null : json_encode($memo),
                'notify_version' => $this->paymentModule->getCurrentVersion(),
                'date_added' => 'now()',
            ],
            $payment_info,
            $address_info
        );

        // Add expiration time if present
        if (isset($orderInfo['expiration_time'])) {
            $sql_data_array['expiration_time'] = $orderInfo['expiration_time'];
        }

        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
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
}
