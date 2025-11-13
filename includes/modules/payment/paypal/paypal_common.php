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
use PayPalRestful\Common\Logger;

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
}
