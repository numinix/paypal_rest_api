<?php
/**
 * paypalr_googlepay.php payment module class for handling Google Pay via PayPal Advanced Checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.3
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

LanguageCompatibility::load('paypalr_googlepay');

/**
 * The PayPal Google Pay payment module using PayPal's REST APIs (v2)
 */
class paypalr_googlepay extends base
{
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS') ? MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS : 'False';
    }

    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SORT_ORDER : null;
    }

    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ZONE') ? (int)MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ZONE : 0;
    }

    protected const CURRENT_VERSION = '1.3.3';
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
    
    // Google Pay never uses on-site card entry
    public bool $cardsAccepted = false;
    public bool $collectsCardDataOnsite = false;

    public PayPalRestfulApi $ppr;
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
        global $order, $messageStack, $loaderPrefix, $current_page;

        $this->code = 'paypalr_googlepay';

        $curl_installed = (function_exists('curl_init'));

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_GOOGLEPAY_TEXT_TITLE ?? 'PayPal Google Pay';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALR_GOOGLEPAY_TEXT_TITLE_ADMIN ?? 'PayPal Google Pay') . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL ?? 'cURL not installed'));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALR_GOOGLEPAY_TEXT_DESCRIPTION ?? 'Google Pay via PayPal Advanced Checkout (v%s)', self::CURRENT_VERSION);
            
            // Add upgrade button if current version is less than latest version
            $installed_version = defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION') ? MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION : '0.0.0';
            if ($installed_version !== '0.0.0' && version_compare($installed_version, self::CURRENT_VERSION, '<')) {
                $this->description .= sprintf(
                    MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_UPGRADE_AVAILABLE ??
                    '<br><br><p><strong>Update Available:</strong> Version %2$s is available. You are currently running version %1$s.</p><p><a class="paypalr-upgrade-button" href="%3$s">Upgrade to %2$s</a></p>',
                    $installed_version,
                    self::CURRENT_VERSION,
                    zen_href_link('paypalr_upgrade.php', 'module=paypalr_googlepay&action=upgrade', 'SSL')
                );
            }
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

        // Google Pay uses final sale mode
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
        if (IS_ADMIN_FLAG === true && isset($current_page) && $current_page === FILENAME_MODULES) {
            // Don't validate when simply listing modules
        } else {
            $this->enabled = ($this->enabled === true && $this->validateConfiguration($curl_installed));
            // Note: Webhook registration is handled by the main paypalr module since
            // webhooks are shared across all PayPal payment modules
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
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ERROR_PAYPAL_REQUIRED ?? 'Main PayPal module required');
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
        global $db;

        // First, let the paypalCommon handle its tableCheckup
        if (!isset($this->paypalCommon)) {
            $this->paypalCommon = new PayPalCommon($this);
        }
        $this->paypalCommon->tableCheckup();
        
        // If the payment module is installed and at the current version, nothing to be done.
        $current_version = self::CURRENT_VERSION;
        if (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION') && MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION === $current_version) {
            return;
        }
        
        // Check for version-specific configuration updates
        if (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION')) {
            switch (true) {
                // Add future version-specific upgrades here
                // case version_compare(MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION, '1.3.4', '<'):
                //     // Add v1.3.4-specific changes here
                
                default:
                    break;
            }
        }
        
        // Record the current version of the payment module into its database configuration setting
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '$current_version',
                    last_modified = now()
              WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION'
              LIMIT 1"
        );
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

        // Trim credentials to match PayPalRestfulApi::getConfiguredCredentials behavior
        $client_id = trim($client_id);
        $secret = trim($secret);

        if (empty($client_id) || empty($secret)) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALR_ALERT_INVALID_CONFIGURATION ?? 'Invalid configuration');
            return null;
        }

        try {
            $ppr = new PayPalRestfulApi(
                MODULE_PAYMENT_PAYPALR_SERVER,
                $client_id,
                $secret
            );
            return $ppr;
        } catch (\Exception $e) {
            $this->log->write('Google Pay: Error creating PayPalRestfulApi: ' . $e->getMessage());
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

    protected function getWalletAssets(string $scriptFilename): string
    {
        $css = '';
        if (!defined('MODULE_PAYMENT_PAYPALR_WALLET_ASSETS_LOADED')) {
            define('MODULE_PAYMENT_PAYPALR_WALLET_ASSETS_LOADED', true);
            $css = '<style>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/paypalr.css') . '</style>';
        }

        return $css . '<script>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/' . $scriptFilename) . '</script>';
    }

    public function selection(): array
    {
        unset($_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed']);

        $buttonContainer = '<div id="paypalr-googlepay-button" class="paypalr-googlepay-button"></div>';
        $hiddenFields =
            zen_draw_hidden_field('ppr_type', 'google_pay') .
            zen_draw_hidden_field('paypalr_googlepay_payload', '', 'id="paypalr-googlepay-payload"') .
            zen_draw_hidden_field('paypalr_googlepay_status', '', 'id="paypalr-googlepay-status"');

        $script = $this->getWalletAssets('jquery.paypalr.googlepay.js');

        return [
            'id' => $this->code,
            'module' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_TEXT_SELECTION ?? 'Google Pay',
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
            'google_pay',
            'paypalr_googlepay_payload',
            [
                'title' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_TEXT_TITLE ?? 'Google Pay',
                'payload_missing' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ERROR_PAYLOAD_MISSING ?? 'Payload missing',
                'payload_invalid' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ERROR_PAYLOAD_INVALID ?? 'Invalid payload',
                'confirm_failed' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ERROR_CONFIRM_FAILED ?? 'Confirmation failed',
                'payer_action' => MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ERROR_PAYER_ACTION ?? 'Payer action required',
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

        return $this->paypalCommon->createPayPalOrder($this, $order, $order_info, $ppr_type, $currencies);
    }

    protected function getOrderTotalsInfo(): array
    {
        global $zcObserverPaypalrestful;

        if (!isset($zcObserverPaypalrestful) || !is_object($zcObserverPaypalrestful)) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info = $zcObserverPaypalrestful->getLastOrderValues();

        if (count($order_info) === 0) {
            $this->log->write('Google Pay: Missing order_total modifications; getLastOrderValues returned empty array.');
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info['free_shipping_coupon'] = $zcObserverPaypalrestful->orderHasFreeShippingCoupon();

        return $order_info;
    }

    protected function createOrderGuid(\order $order, string $ppr_type): string
    {
        return $this->paypalCommon->createOrderGuid($order, $ppr_type);
    }

    public function setMessageAndRedirect(string $error_message, string $redirect_page, bool $log_only = false)
    {
        global $messageStack;

        $this->log->write('Google Pay redirect: ' . $error_message);

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
            $this->log->write('Google Pay::before_process, cannot capture/authorize; wrong status' . "\n" . Logger::logJSON($_SESSION['PayPalRestful']['Order'] ?? []));
            unset($_SESSION['PayPalRestful']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALR_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }
        
        $response = $this->captureOrAuthorizePayment('google_pay');

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
        $response = $this->paypalCommon->captureWalletPayment($this->ppr, $this->log, 'Google Pay');
        
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
        $this->paypalCommon->updateOrderHistory($this->orderInfo, 'google_pay');
        $this->paypalCommon->resetOrder();
    }

    protected function recordPayPalOrderDetails(int $orders_id): void
    {
        // Delegate to common class - but for googlepay we have specific handling
        // Implementation similar to paypalr but adapted for Google Pay
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
        
        $payment_type = 'google_pay';
        $payment_source = $this->orderInfo['payment_source'][$payment_type] ?? [];
        
        $card_source = $payment_source['card'] ?? [];
        $name = $payment_source['name'] ?? [];
        
        $first_name = is_array($name) ? ($name['given_name'] ?? '') : '';
        $last_name = is_array($name) ? ($name['surname'] ?? '') : '';
        $email_address = $payment_source['email_address'] ?? '';
        
        $memo = [
            'source' => 'google_pay',
            'card_info' => $card_source,
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
        return $this->paypalCommon->processRefund($oID, $this->getPayPalRestfulApi(), $this->code, self::CURRENT_VERSION);
    }

    public function _doAuth($oID, $order_amt, $currency = 'USD')
    {
        return $this->paypalCommon->processAuthorization($oID, $this->getPayPalRestfulApi(), $this->code, self::CURRENT_VERSION, $order_amt, $currency, false);
    }

    public function _doCapt($oID, $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        return $this->paypalCommon->processCapture($oID, $this->getPayPalRestfulApi(), $this->code, self::CURRENT_VERSION, $captureType, $order_amt, $order_currency);
    }

    public function _doVoid($oID)
    {
        return $this->paypalCommon->processVoid($oID, $this->getPayPalRestfulApi(), $this->code, self::CURRENT_VERSION);
    }

    public function check(): bool
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS'");
            $this->_check = !$check_query->EOF;
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        
        $current_version = self::CURRENT_VERSION;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
             VALUES
                ('Module Version', 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION', '$current_version', 'Currently-installed module version.', 6, 0, 'zen_cfg_read_only(', NULL, now()),
                ('Enable PayPal Google Pay?', 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS', 'False', 'Do you want to enable PayPal Google Pay payments?', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),
                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),
                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );
        
        // Define the module's current version so that the tableCheckup method will apply all changes
        define('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION', '0.0.0');
        $this->tableCheckup();
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION',
            'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS',
            'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_ZONE',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_%'");
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
