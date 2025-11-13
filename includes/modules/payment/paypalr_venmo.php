<?php
/**
 * paypalr_venmo.php payment module class for handling Venmo via PayPal Advanced Checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.2
 */
/**
 * Load the support class' auto-loader and common class.
 */
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_common.php');

use PayPalRestful\Admin\AdminMain;
use PayPalRestful\Admin\DoAuthorization;
use PayPalRestful\Admin\DoCapture;
use PayPalRestful\Admin\DoRefund;
use PayPalRestful\Admin\DoVoid;
use PayPalRestful\Admin\GetPayPalOrderTransactions;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Api\Data\CountryCodes;
use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;
use PayPalRestful\Common\VaultManager;
use PayPalRestful\Compatibility\Language as LanguageCompatibility;
use PayPalRestful\Zc2Pp\Amount;
use PayPalRestful\Zc2Pp\ConfirmPayPalPaymentChoiceRequest;
use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

LanguageCompatibility::load();

/**
 * The PayPal Venmo payment module using PayPal's REST APIs (v2)
 */
class paypalr_venmo extends base
{
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALR_VENMO_STATUS') ? MODULE_PAYMENT_PAYPALR_VENMO_STATUS : 'False';
    }

    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALR_VENMO_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALR_VENMO_SORT_ORDER : null;
    }

    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALR_VENMO_ZONE') ? (int)MODULE_PAYMENT_PAYPALR_VENMO_ZONE : 0;
    }

    protected const CURRENT_VERSION = '1.3.2';
    protected const WALLET_SUCCESS_STATUSES = [
        PayPalRestfulApi::STATUS_APPROVED,
        PayPalRestfulApi::STATUS_COMPLETED,
        PayPalRestfulApi::STATUS_CAPTURED,
    ];

    public string $code;
    public string $title;
    public string $description = '';
    public bool $enabled;
    public ?int $sort_order = null;
    public int $zone = 0;
    public int $order_status = 0;
    
    // Venmo never uses on-site card entry
    public bool $cardsAccepted = false;
    public bool $collectsCardDataOnsite = false;

    protected PayPalRestfulApi $ppr;
    protected ErrorInfo $errorInfo;
    protected Logger $log;
    protected bool $emailAlerts = false;
    protected PayPalCommon $paypalCommon;
    protected array $orderInfo = [];
    protected bool $paymentIsPending = false;
    protected bool $billingCountryIsSupported = true;
    protected bool $shippingCountryIsSupported = true;
    protected array $orderCustomerCache = [];
    protected bool $onOpcConfirmationPage = false;
    protected array $paypalRestfulSessionOnEntry = [];

    /**
     * class constructor
     */
    public function __construct()
    {
        global $order, $messageStack, $loaderPrefix;

        $this->code = 'paypalr_venmo';

        $curl_installed = (function_exists('curl_init'));

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_VENMO_TEXT_TITLE ?? 'PayPal Venmo';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALR_VENMO_TEXT_TITLE_ADMIN ?? 'PayPal Venmo') . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL ?? 'cURL not installed'));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALR_VENMO_TEXT_DESCRIPTION ?? 'Venmo via PayPal Advanced Checkout (v%s)', self::CURRENT_VERSION);
        }

        $this->sort_order = $this->getModuleSortOrder();
        if (null === $this->sort_order) {
            return;
        }

        $module_status_setting = $this->getModuleStatusSetting();
        $this->enabled = ($module_status_setting === 'True' || (IS_ADMIN_FLAG === true && $module_status_setting === 'Retired'));

        $this->errorInfo = new ErrorInfo();

        $this->log = new Logger();
        $debug = (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false);
        if ($debug === true) {
            $this->log->enableDebug();
        }
        $this->emailAlerts = (MODULE_PAYMENT_PAYPALR_DEBUGGING === 'Alerts Only' || MODULE_PAYMENT_PAYPALR_DEBUGGING === 'Log and Email');

        // Initialize the shared PayPal common class
        $this->paypalCommon = new PayPalCommon($this);

        // Venmo uses final sale mode
        $order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
        $this->order_status = ($order_status > 1) ? $order_status : (int)DEFAULT_ORDERS_STATUS_ID;

        $this->zone = $this->getModuleZoneSetting();

        if (IS_ADMIN_FLAG === true) {
            if ($module_status_setting === 'Retired') {
                $this->title .= ' <strong>(Retired)</strong>';
            }
            if (MODULE_PAYMENT_PAYPALR_SERVER === 'sandbox') {
                $this->title .= $this->alertMsg(' (sandbox active)');
            }
            if ($debug === true) {
                $this->title .= ' <strong>(Debug)</strong>';
            }
            $this->tableCheckup();
        } elseif ($this->enabled === true) {
            global $zcObserverPaypalrestful;
            if (!isset($zcObserverPaypalrestful)) {
                $this->enabled = false;

                if (in_array($loaderPrefix ?? '', ['paypal_ipn', 'webhook'], true)) {
                    return;
                }
                $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing');
                return;
            }
        }

        // Validate the configuration
        if (IS_ADMIN_FLAG === true && $current_page === FILENAME_MODULES) {
            // Don't validate when simply listing modules
        } else {
            $this->enabled = ($this->enabled === true && $this->validateConfiguration($curl_installed));
            if ($this->enabled && IS_ADMIN_FLAG === true && $current_page !== FILENAME_MODULES) {
                $this->ppr->registerAndUpdateSubscribedWebhooks();
            }
        }
        if ($this->enabled === false || IS_ADMIN_FLAG === true || $loaderPrefix === 'webhook') {
            return;
        }

        if (is_object($order)) {
            $this->update_status();
            if ($this->enabled === false) {
                return;
            }

            if (isset($order->billing['country'])) {
                $this->billingCountryIsSupported = (CountryCodes::ConvertCountryCode($order->billing['country']['iso_code_2']) !== '');
            }
            if ($_SESSION['cart']->get_content_type() !== 'virtual') {
                $this->shippingCountryIsSupported = (CountryCodes::ConvertCountryCode($order->delivery['country']['iso_code_2'] ?? '??') !== '');
            }
        }

        global $current_page_base;
        if (defined('FILENAME_CHECKOUT_ONE_CONFIRMATION') && $current_page_base === FILENAME_CHECKOUT_ONE_CONFIRMATION) {
            $this->onOpcConfirmationPage = true;
            $this->paypalRestfulSessionOnEntry = $_SESSION['PayPalRestful'] ?? [];
            $this->attach($this, ['NOTIFY_OPC_OBSERVER_SESSION_FIXUPS']);
        }

        // Load wallet-specific language file
        $this->paypalCommon->loadWalletLanguageFile($this->code);

        // Check for required main PayPal module
        if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALR_VENMO_ERROR_PAYPAL_REQUIRED ?? 'Main PayPal module required');
            }
            return;
        }
    }

    public function updateNotifyOpcObserverSessionFixups(&$class, $eventID, $empty_string, &$session_data)
    {
        if ($this->onOpcConfirmationPage === false || empty($this->paypalRestfulSessionOnEntry)) {
            return;
        }
        $session_data['PayPalRestful'] = $this->paypalRestfulSessionOnEntry;
    }

    protected function alertMsg(string $msg): string
    {
        return '<span class="alert">' . $msg . '</span>';
    }

    protected function tableCheckup()
    {
        $this->paypalCommon->tableCheckup();
    }

    protected function validateConfiguration(bool $curl_installed): bool
    {
        if ($curl_installed === false) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL ?? 'cURL not installed');
            return false;
        }

        $this->ppr = $this->getPayPalRestfulApi();
        if ($this->ppr === null) {
            return false;
        }

        return true;
    }

    protected function getPayPalRestfulApi(): ?PayPalRestfulApi
    {
        $client_id = (MODULE_PAYMENT_PAYPALR_SERVER === 'live') ? MODULE_PAYMENT_PAYPALR_CLIENTID_L : MODULE_PAYMENT_PAYPALR_CLIENTID_S;
        $secret = (MODULE_PAYMENT_PAYPALR_SERVER === 'live') ? MODULE_PAYMENT_PAYPALR_SECRET_L : MODULE_PAYMENT_PAYPALR_SECRET_S;

        if (empty($client_id) || empty($secret)) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALR_ALERT_INVALID_CONFIGURATION ?? 'Invalid configuration');
            return null;
        }

        try {
            $ppr = new PayPalRestfulApi(
                $client_id,
                $secret,
                MODULE_PAYMENT_PAYPALR_SERVER,
                $this->log
            );
            return $ppr;
        } catch (\Exception $e) {
            $this->log->write('Venmo: Error creating PayPalRestfulApi: ' . $e->getMessage());
            $this->setConfigurationDisabled($e->getMessage());
            return null;
        }
    }

    protected function setConfigurationDisabled(string $error_message, bool $force_disable = false)
    {
        $this->enabled = false;
        if (IS_ADMIN_FLAG === true || $force_disable === true) {
            $this->title .= $this->alertMsg($error_message);
        }
    }

    public function update_status()
    {
        global $order, $db;

        if ($this->enabled === false || !is_object($order)) {
            return;
        }

        if ((int)$this->zone > 0 && isset($order->billing['country']['id'])) {
            $check_flag = false;
            $check = $db->Execute(
                "SELECT zone_id
                   FROM " . TABLE_ZONES_TO_GEO_ZONES . "
                  WHERE geo_zone_id = '" . (int)$this->zone . "'
                    AND zone_country_id = '" . (int)$order->billing['country']['id'] . "'
               ORDER BY zone_id"
            );
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1 || $check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag === false) {
                $this->enabled = false;
            }
        }
    }



    public function javascript_validation(): string
    {
        return '';
    }

    public function selection(): array
    {
        unset($_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed']);

        $buttonContainer = '<div id="paypalr-venmo-button" class="paypalr-venmo-button"></div>';
        $hiddenFields =
            zen_draw_hidden_field('ppr_type', 'venmo') .
            zen_draw_hidden_field('paypalr_venmo_payload', '', 'id="paypalr-venmo-payload"') .
            zen_draw_hidden_field('paypalr_venmo_status', '', 'id="paypalr-venmo-status"');

        $script = '<script>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.venmo.js') . '</script>';

        return [
            'id' => $this->code,
            'module' => MODULE_PAYMENT_PAYPALR_VENMO_TEXT_SELECTION ?? 'Venmo',
            'fields' => [
                [
                    'title' => $buttonContainer,
                    'field' => $hiddenFields . $script,
                ],
            ],
        ];
    }

    public function pre_confirmation_check()
    {
        $this->paypalCommon->processWalletConfirmation(
            'venmo',
            'paypalr_venmo_payload',
            [
                'title' => MODULE_PAYMENT_PAYPALR_VENMO_TEXT_TITLE ?? 'Venmo',
                'payload_missing' => MODULE_PAYMENT_PAYPALR_VENMO_ERROR_PAYLOAD_MISSING ?? 'Payload missing',
                'payload_invalid' => MODULE_PAYMENT_PAYPALR_VENMO_ERROR_PAYLOAD_INVALID ?? 'Invalid payload',
                'confirm_failed' => MODULE_PAYMENT_PAYPALR_VENMO_ERROR_CONFIRM_FAILED ?? 'Confirmation failed',
                'payer_action' => MODULE_PAYMENT_PAYPALR_VENMO_ERROR_PAYER_ACTION ?? 'Payer action required',
            ]
        );
    }

    protected function isOpcAjaxRequest(): bool
    {
        return (defined('IS_AJAX_REQUEST') && IS_AJAX_REQUEST === true);
    }

    protected function createPayPalOrder(string $ppr_type): bool
    {
        global $order, $currencies;

        $order_info = $this->getOrderTotalsInfo();

        $create_order_request = new CreatePayPalOrderRequest(
            $order,
            $order_info,
            $ppr_type,
            $this->createOrderGuid($order, $ppr_type),
            $currencies
        );

        $paypal_order = $this->ppr->createOrder($create_order_request);

        if ($paypal_order === false) {
            return false;
        }

        $_SESSION['PayPalRestful']['Order'] = [
            'id' => $paypal_order['id'],
            'status' => $paypal_order['status'],
            'payment_source' => $ppr_type,
            'amount_mismatch' => false,
        ];

        return true;
    }

    protected function getOrderTotalsInfo(): array
    {
        global $zcObserverPaypalrestful;

        if (!isset($zcObserverPaypalrestful) || !is_object($zcObserverPaypalrestful)) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info = $zcObserverPaypalrestful->getOrderInfo();

        if ($order_info === false) {
            $this->log->write('Venmo: Missing order_total modifications; getOrderInfo returned false.');
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        return $order_info;
    }

    protected function createOrderGuid(\order $order, string $ppr_type): string
    {
        return $this->paypalCommon->createOrderGuid($order, $ppr_type);
    }

    protected function setMessageAndRedirect(string $error_message, string $redirect_page, bool $log_only = false)
    {
        global $messageStack;

        $this->log->write('Venmo redirect: ' . $error_message);

        if ($log_only === false) {
            $messageStack->add_session('checkout_payment', $error_message, 'error');
        }

        zen_redirect(zen_href_link($redirect_page, '', 'SSL'));
    }

    public function confirmation()
    {
        return false;
    }

    public function process_button()
    {
        return false;
    }

    public function process_button_ajax()
    {
        return [];
    }

    public function alterShippingEditButton()
    {
        return '';
    }

    public function clear_payments()
    {
        unset($_SESSION['PayPalRestful']);
    }

    public function before_process()
    {
        global $order;

        $order_info = $this->getOrderTotalsInfo();

        $this->paymentIsPending = false;

        $wallet_status = $_SESSION['PayPalRestful']['Order']['status'] ?? '';
        $wallet_user_action = $_SESSION['PayPalRestful']['Order']['user_action'] ?? '';
        $payer_action_fast_path = ($wallet_status === PayPalRestfulApi::STATUS_PAYER_ACTION_REQUIRED && $wallet_user_action === 'PAY_NOW');
        
        if (!in_array($wallet_status, self::WALLET_SUCCESS_STATUSES, true) && $payer_action_fast_path === false) {
            $this->log->write('Venmo::before_process, cannot capture/authorize; wrong status' . "\n" . Logger::logJSON($_SESSION['PayPalRestful']['Order'] ?? []));
            unset($_SESSION['PayPalRestful']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALR_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }
        
        $response = $this->captureOrAuthorizePayment('venmo');

        $_SESSION['PayPalRestful']['Order']['status'] = $response['status'];
        unset($response['links']);
        $this->orderInfo = $response;

        if ($this->paymentIsPending === true) {
            $pending_status = (int)MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID;
            if ($pending_status > 0) {
                $this->order_status = $pending_status;
                $order->info['order_status'] = $pending_status;
            }
        }

        $txn_type = $this->orderInfo['intent'];
        $payment = $this->orderInfo['purchase_units'][0]['payments']['captures'][0] ?? $this->orderInfo['purchase_units'][0]['payments']['authorizations'][0];
        $payment_status = ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED) ? $payment['status'] : (($txn_type === 'CAPTURE') ? PayPalRestfulApi::STATUS_CAPTURED : PayPalRestfulApi::STATUS_APPROVED);

        $this->orderInfo['payment_status'] = $payment_status;
        $this->orderInfo['paypal_payment_status'] = $payment['status'];
        $this->orderInfo['txn_type'] = $txn_type;

        $this->orderInfo['expiration_time'] = $payment['expiration_time'] ?? null;

        if ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED) {
            $pending_status = (int)MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID;
            if ($pending_status > 0) {
                $this->order_status = $pending_status;
                $order->info['order_status'] = $pending_status;
            }
            $this->orderInfo['admin_alert_needed'] = true;
        } else {
            $this->orderInfo['admin_alert_needed'] = false;
        }
    }

    protected function captureOrAuthorizePayment(string $payment_source): array
    {
        $response = $this->paypalCommon->captureWalletPayment($this->ppr, $this->log, 'Venmo');
        
        if ($response === false) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_TEXT_CAPTURE_FAILED ?? 'Capture failed', FILENAME_CHECKOUT_PAYMENT);
        }

        return $response;
    }

    public function after_order_create($orders_id)
    {
        // Placeholder for future functionality
    }

    public function after_process()
    {
        $this->paypalCommon->processAfterOrder($this->orderInfo);
        $this->paypalCommon->updateOrderHistory($this->orderInfo, 'venmo');
        $this->paypalCommon->resetOrder();
    }

    protected function recordPayPalOrderDetails(int $orders_id): void
    {
        // Delegate to common class - but for venmo we have specific handling
        // Implementation similar to paypalr but adapted for Venmo
        if ($orders_id <= 0) {
            return;
        }

        $this->orderInfo['orders_id'] = $orders_id;

        if ($this->paypalCommon->paypalOrderRecordsExist($orders_id) === true) {
            return;
        }

        // Record order details in PayPal table
        global $db;
        
        $purchase_unit = $this->orderInfo['purchase_units'][0];
        $payment = $purchase_unit['payments']['captures'][0] ?? $purchase_unit['payments']['authorizations'][0];
        
        $payment_type = 'venmo';
        $payment_source = $this->orderInfo['payment_source'][$payment_type] ?? [];
        
        $name = $payment_source['name'] ?? [];
        
        $first_name = is_array($name) ? ($name['given_name'] ?? '') : '';
        $last_name = is_array($name) ? ($name['surname'] ?? '') : '';
        $email_address = $payment_source['email_address'] ?? '';
        
        $memo = [
            'source' => 'venmo',
        ];

        $sql_data_array = [
            'order_id' => $orders_id,
            'txn_type' => 'CREATE',
            'module_name' => $this->code,
            'module_mode' => $this->orderInfo['txn_type'],
            'payment_type' => $payment_type,
            'payment_status' => $this->orderInfo['payment_status'],
            'mc_currency' => $payment['amount']['currency_code'],
            'first_name' => substr($first_name, 0, 32),
            'last_name' => substr($last_name, 0, 32),
            'payer_email' => $email_address,
            'txn_id' => $this->orderInfo['id'],
            'mc_gross' => $payment['amount']['value'],
            'date_added' => Helpers::convertPayPalDatePay2Db($this->orderInfo['create_time']),
            'notify_version' => self::CURRENT_VERSION,
            'memo' => json_encode($memo),
        ];

        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
    }

    public function admin_notification($zf_order_id)
    {
        $zf_order_id = (int)$zf_order_id;
        $ppr = $this->getPayPalRestfulApi();
        if ($ppr === null) {
            return '';
        }

        $admin_main = new AdminMain($this->code, self::CURRENT_VERSION, $zf_order_id, $ppr);

        return $admin_main->get();
    }

    public function help()
    {
        return '';
    }

    public function _doRefund($oID)
    {
        $ppr = $this->getPayPalRestfulApi();
        if ($ppr === null) {
            return false;
        }

        $do_refund = new DoRefund($oID, $ppr, $this->code);
        return $do_refund->process();
    }

    public function _doAuth($oID, $order_amt, $currency = 'USD')
    {
        return false; // Venmo doesn't support separate auth
    }

    public function _doCapt($oID, $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        $ppr = $this->getPayPalRestfulApi();
        if ($ppr === null) {
            return false;
        }

        $do_capture = new DoCapture($oID, $ppr, $this->code);
        return $do_capture->process($captureType, $order_amt, $order_currency);
    }

    public function _doVoid($oID)
    {
        $ppr = $this->getPayPalRestfulApi();
        if ($ppr === null) {
            return false;
        }

        $do_void = new DoVoid($oID, $ppr, $this->code);
        return $do_void->process();
    }

    public function check(): bool
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_VENMO_STATUS'");
            $this->_check = !$check_query->EOF;
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
             VALUES
                ('Enable PayPal Venmo?', 'MODULE_PAYMENT_PAYPALR_VENMO_STATUS', 'False', 'Do you want to enable PayPal Venmo payments?', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),
                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_VENMO_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),
                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_VENMO_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALR_VENMO_STATUS',
            'MODULE_PAYMENT_PAYPALR_VENMO_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_VENMO_ZONE',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALR_VENMO_%'");
    }

    public function sendAlertEmail(string $subject_detail, string $message, bool $force_send = false)
    {
        $this->paypalCommon->sendAlertEmail($subject_detail, $message, $force_send);
    }

    public function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }
}
