<?php
/**
 * paypalac_applepay.php payment module class for handling Apple Pay via PayPal Advanced Checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.3
 */
/**
 * Load the support class' auto-loader and common class.
 */
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_common.php');

use PayPalAdvancedCheckout\Admin\AdminMain;
use PayPalAdvancedCheckout\Admin\DoAuthorization;
use PayPalAdvancedCheckout\Admin\DoCapture;
use PayPalAdvancedCheckout\Admin\DoRefund;
use PayPalAdvancedCheckout\Admin\DoVoid;
use PayPalAdvancedCheckout\Admin\GetPayPalOrderTransactions;
use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;
use PayPalAdvancedCheckout\Api\Data\CountryCodes;
use PayPalAdvancedCheckout\Common\ErrorInfo;
use PayPalAdvancedCheckout\Common\Helpers;
use PayPalAdvancedCheckout\Common\Logger;
use PayPalAdvancedCheckout\Common\VaultManager;
use PayPalAdvancedCheckout\Compatibility\Language as LanguageCompatibility;
use PayPalAdvancedCheckout\Zc2Pp\Amount;
use PayPalAdvancedCheckout\Zc2Pp\ConfirmPayPalPaymentChoiceRequest;
use PayPalAdvancedCheckout\Zc2Pp\CreatePayPalOrderRequest;

LanguageCompatibility::load('paypalac_applepay');

/**
 * The PayPal Apple Pay payment module using PayPal's REST APIs (v2)
 */
class paypalac_applepay extends base
{
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') ? MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS : 'False';
    }

    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALAC_APPLEPAY_SORT_ORDER : null;
    }

    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_ZONE') ? (int)MODULE_PAYMENT_PAYPALAC_APPLEPAY_ZONE : 0;
    }

    protected const CURRENT_VERSION = '1.3.4';
    protected const WALLET_SUCCESS_STATUSES = [
        PayPalAdvancedCheckoutApi::STATUS_APPROVED,
        PayPalAdvancedCheckoutApi::STATUS_COMPLETED,
        PayPalAdvancedCheckoutApi::STATUS_CAPTURED,
    ];

    public string $code;
    public string $title;
    public string $description = '';
    public bool $enabled;
    public ?int $sort_order = null;
    public int $zone = 0;
    public int $order_status = 0;
    
    // Apple Pay never uses on-site card entry
    public bool $cardsAccepted = false;
    public bool $collectsCardDataOnsite = false;

    public PayPalAdvancedCheckoutApi $ppr;
    protected ErrorInfo $errorInfo;
    public Logger $log;
    public bool $emailAlerts = false;
    protected PayPalCommon $paypalCommon;
    protected array $orderInfo = [];
    protected bool $paymentIsPending = false;
    protected bool $billingCountryIsSupported = true;
    protected bool $shippingCountryIsSupported = true;
    public array $orderCustomerCache = [];
    protected bool $onOpcConfirmationPage = false;
    protected array $paypalAdvCheckoutSessionOnEntry = [];

    public function getErrorInfo(): ErrorInfo
    {
        return $this->errorInfo;
    }

    /**
     * class constructor
     */
    public function __construct()
    {
        global $order, $messageStack, $loaderPrefix, $current_page;

        $this->code = 'paypalac_applepay';

        $curl_installed = (function_exists('curl_init'));

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_TITLE ?? 'PayPal Apple Pay';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_TITLE_ADMIN ?? 'PayPal Apple Pay') . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL ?? 'cURL not installed'));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_DESCRIPTION ?? 'Apple Pay via PayPal Advanced Checkout (v%s)', self::CURRENT_VERSION);
            
            // Add upgrade button if current version is less than latest version
            $installed_version = defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION') ? MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION : '0.0.0';
            if ($installed_version !== '0.0.0' && version_compare($installed_version, self::CURRENT_VERSION, '<')) {
                $this->description .= sprintf(
                    MODULE_PAYMENT_PAYPALAC_TEXT_ADMIN_UPGRADE_AVAILABLE ??
                    '<br><br><p><strong>Update Available:</strong> Version %2$s is available. You are currently running version %1$s.</p><p><a class="paypalac-upgrade-button" href="%3$s">Upgrade to %2$s</a></p>',
                    $installed_version,
                    self::CURRENT_VERSION,
                    zen_href_link('paypalac_upgrade.php', 'module=paypalac_applepay&action=upgrade', 'SSL')
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
        $debug = (strpos(MODULE_PAYMENT_PAYPALAC_DEBUGGING, 'Log') !== false);
        if ($debug === true) {
            $this->log->enableDebug();
        }
        $this->emailAlerts = (MODULE_PAYMENT_PAYPALAC_DEBUGGING === 'Alerts Only' || MODULE_PAYMENT_PAYPALAC_DEBUGGING === 'Log and Email');

        // Initialize the shared PayPal common class
        $this->paypalCommon = new PayPalCommon($this);

        // -----
        // An order's *initial* order-status depends on the mode in which the PayPal transaction
        // is to be performed. Apple Pay is a wallet payment (ppac_type !== 'card').
        //
        if (MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Final Sale' || MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Auth Only (Card-Only)') {
            $order_status = (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID;
        } else {
            $order_status = (int)MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID;
        }
        $this->order_status = ($order_status > 0) ? $order_status : (int)DEFAULT_ORDERS_STATUS_ID;

        $this->zone = $this->getModuleZoneSetting();

        if (IS_ADMIN_FLAG === true) {
            if ($module_status_setting === 'Retired') {
                $this->title .= ' <strong>(Retired)</strong>';
            }
            if (MODULE_PAYMENT_PAYPALAC_SERVER === 'sandbox') {
                $this->title .= $this->alertMsg(' (sandbox active)');
            }
            if ($debug === true) {
                $this->title .= ' <strong>(Debug)</strong>';
            }
            $this->tableCheckup();
        } elseif ($this->enabled === true) {
            global $zcObserverPaypaladvcheckout;
            if (!isset($zcObserverPaypaladvcheckout)) {
                $this->enabled = false;

                if (in_array($loaderPrefix ?? '', ['paypal_ipn', 'webhook'], true)) {
                    return;
                }
                $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALAC_ALERT_MISSING_OBSERVER ?? 'Observer missing');
                return;
            }
        }

        // Validate the configuration
        if (IS_ADMIN_FLAG === true && isset($current_page) && $current_page === FILENAME_MODULES) {
            // Don't validate when simply listing modules
        } else {
            $this->enabled = ($this->enabled === true && $this->validateConfiguration($curl_installed));
            // Note: Webhook registration is handled by the main paypalac module since
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
            $this->paypalAdvCheckoutSessionOnEntry = $_SESSION['PayPalAdvancedCheckout'] ?? [];
            $this->attach($this, ['NOTIFY_OPC_OBSERVER_SESSION_FIXUPS']);
        }

        // Load wallet-specific language file
        $this->paypalCommon->loadWalletLanguageFile($this->code);

        // Check for required main PayPal module
        if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_PAYPAL_REQUIRED ?? 'Main PayPal module required');
            }
            return;
        }
    }

    public function updateNotifyOpcObserverSessionFixups(&$class, $eventID, $empty_string, &$session_data)
    {
        if ($this->onOpcConfirmationPage === false || empty($this->paypalAdvCheckoutSessionOnEntry)) {
            return;
        }
        $session_data['PayPalAdvancedCheckout'] = $this->paypalAdvCheckoutSessionOnEntry;
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
        if (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION === $current_version) {
            return;
        }
        
        // Check for version-specific configuration updates
        if (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION')) {
            switch (true) {
                case version_compare(MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION, '1.3.4', '<'):
                    // Add configuration options to control button display on cart and product pages
                    $db->Execute(
                        "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                         VALUES
                            ('Enable on Shopping Cart Page?', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_SHOPPING_CART', 'True', 'Do you want to display the Apple Pay button on the shopping cart page?', 6, 0, 'zen_cfg_select_option([''True'', ''False''], ', NULL, now()),
                            ('Enable on Product Page?', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_PRODUCT_PAGE', 'True', 'Do you want to display the Apple Pay button on product pages?', 6, 0, 'zen_cfg_select_option([''True'', ''False''], ', NULL, now())"
                    );
                    // Fall through to update the stored version number
                
                default:
                    break;
            }
        }
        
        // Record the current version of the payment module into its database configuration setting
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '$current_version',
                    last_modified = now()
              WHERE configuration_key = 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION'
              LIMIT 1"
        );
    }

    protected function validateConfiguration(bool $curl_installed): bool
    {
        if ($curl_installed === false) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL ?? 'cURL not installed');
            return false;
        }

        $this->ppr = $this->getPayPalAdvancedCheckoutApi();
        if ($this->ppr === null) {
            return false;
        }

        return true;
    }

    protected function getPayPalAdvancedCheckoutApi(): ?PayPalAdvancedCheckoutApi
    {
        $client_id = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        $secret = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_SECRET_L : MODULE_PAYMENT_PAYPALAC_SECRET_S;

        // Trim credentials to match PayPalAdvancedCheckoutApi::getConfiguredCredentials behavior
        $client_id = trim($client_id);
        $secret = trim($secret);

        if (empty($client_id) || empty($secret)) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALAC_ALERT_INVALID_CONFIGURATION ?? 'Invalid configuration');
            return null;
        }

        try {
            $ppr = new PayPalAdvancedCheckoutApi(
                MODULE_PAYMENT_PAYPALAC_SERVER,
                $client_id,
                $secret
            );
            return $ppr;
        } catch (\Exception $e) {
            $this->log->write('Apple Pay: Error creating PayPalAdvancedCheckoutApi: ' . $e->getMessage());
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

        // Disable Apple Pay for subscription products - Apple Pay does not support vaulting for recurring payments
        if ($this->cartContainsSubscriptionProduct()) {
            $this->enabled = false;
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

    /**
     * Check if the cart contains any subscription products that require vaulting.
     * 
     * Apple Pay does not support vaulting for recurring payments, so this module
     * should be disabled when the cart contains subscription products.
     * 
     * @return bool True if the cart contains subscription products
     */
    protected function cartContainsSubscriptionProduct(): bool
    {
        if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
            return false;
        }

        $products = $_SESSION['cart']->get_products();
        if (!is_array($products) || empty($products)) {
            return false;
        }

        // Check using paypalSavedCardRecurring class if available
        if (!class_exists('paypalSavedCardRecurring')) {
            $savedCardRecurringPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
            if (file_exists($savedCardRecurringPath)) {
                require_once $savedCardRecurringPath;
            }
        }

        if (class_exists('paypalSavedCardRecurring')) {
            $recurring = new \paypalSavedCardRecurring();
            $subscriptions = $recurring->find_subscription_products_in_order($products);
            if (!empty($subscriptions)) {
                return true;
            }
        }

        // Also check for plan_id attribute-based subscriptions
        return $this->cartHasPlanIdSubscription($products);
    }

    /**
     * Check if any products in the cart have a PayPal subscription plan_id attribute.
     * 
     * @param array $products Products from the cart
     * @return bool True if any product has a plan_id attribute
     */
    protected function cartHasPlanIdSubscription(array $products): bool
    {
        global $db;

        foreach ($products as $product) {
            if (!is_array($product['attributes'] ?? null)) {
                continue;
            }
            foreach ($product['attributes'] as $options_id => $options_values_id) {
                $options = $db->Execute(
                    "SELECT products_options_name FROM " . TABLE_PRODUCTS_OPTIONS . " WHERE products_options_id = " . (int)$options_id . " LIMIT 1;"
                );
                if ($options->RecordCount() === 0) {
                    continue;
                }
                $normalized = $this->normalizeAttributeKey((string)$options->fields['products_options_name']);
                if ($normalized === 'paypal_subscription_plan_id' || $normalized === 'plan_id') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize an attribute key for comparison.
     * 
     * @param string $label The attribute label to normalize
     * @return string The normalized key
     */
    protected function normalizeAttributeKey(string $label): string
    {
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? $label;
        return trim($label, '_');
    }

    public function javascript_validation(): string
    {
        return '';
    }

    protected function getWalletAssets(string $scriptFilename): string
    {
        $css = '';
        if (!defined('MODULE_PAYMENT_PAYPALAC_WALLET_ASSETS_LOADED')) {
            define('MODULE_PAYMENT_PAYPALAC_WALLET_ASSETS_LOADED', true);
            $css = '<style>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/paypalac.css') . '</style>';
        }

        return $css . '<script>' . file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/' . $scriptFilename) . '</script>';
    }

    public function selection(): array
    {
        unset($_SESSION['PayPalAdvancedCheckout']['Order']['wallet_payment_confirmed']);

        $selectionLabel = MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_SELECTION ?? 'Apple Pay';
        $buttonContainer = '<div id="paypalac-applepay-button" class="paypalac-applepay-button"></div>';
        $hiddenFields =
            zen_draw_hidden_field('ppac_type', 'apple_pay') .
            zen_draw_hidden_field('paypalac_applepay_payload', '', 'id="paypalac-applepay-payload"') .
            zen_draw_hidden_field('paypalac_applepay_status', '', 'id="paypalac-applepay-status"');

        $script = $this->getWalletAssets('jquery.paypalac.applepay.js');

        return [
            'id' => $this->code,
            'module' => $selectionLabel,
            'fields' => [
                [
                    'title' => $selectionLabel,
                    'field' => $buttonContainer . $hiddenFields . $script,
                ],
            ],
        ];
    }

    /**
     * Get wallet SDK configuration without creating a PayPal order.
     * Used during initial button rendering - the actual order creation
     * happens when user clicks the button (in createOrder callback).
     *
     * @return array
     */
    public function ajaxGetWalletConfig(): array
    {
        $client_id = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        $client_id = trim($client_id);

        // Note: Apple Pay via PayPal does not require a separate merchant-id parameter in the SDK URL.
        // PayPal manages the Apple Pay merchant configuration internally through your PayPal account.
        // Do NOT use MODULE_PAYMENT_PAYPALAC_MERCHANT_ID as it's a language label, not a configuration value.
        $merchant_id = '';

        $intent = (MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Final Sale' || MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Auth Only (Card-Only)')
            ? 'capture'
            : 'authorize';

        // -----
        // Log wallet configuration request for debugging SDK 400 errors
        //
        $loggedClientId = (strlen($client_id) > 10)
            ? substr($client_id, 0, 6) . '...' . substr($client_id, -4)
            : ($client_id === '' ? '(empty)' : $client_id);
        $this->log->write(
            "Apple Pay ajaxGetWalletConfig:\n" .
            "  - Environment: " . MODULE_PAYMENT_PAYPALAC_SERVER . "\n" .
            "  - Client ID: " . $loggedClientId . "\n" .
            "  - Currency: " . ($_SESSION['currency'] ?? 'USD') . "\n" .
            "  - Intent: " . $intent . "\n" .
            "  - Module Enabled: " . ($this->enabled ? 'Yes' : 'No'),
            true,
            'before'
        );

        if ($client_id === '') {
            $this->log->write("Apple Pay ajaxGetWalletConfig FAILED: Client ID is empty", true, 'after');
            return ['success' => false, 'message' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_INITIALIZE ?? 'Unable to start Apple Pay. Please try again.'];
        }

        return [
            'success' => true,
            'clientId' => $client_id,
            'merchantId' => $merchant_id,
            'currency' => $_SESSION['currency'] ?? 'USD',
            'intent' => $intent,
            'environment' => MODULE_PAYMENT_PAYPALAC_SERVER,
        ];
    }

    public function ajaxCreateWalletOrder(): array
    {
        $response = $this->buildWalletAjaxResponse('apple_pay');
        if ($response['success'] === false && empty($response['message'])) {
            $response['message'] = MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_INITIALIZE ?? 'Unable to start Apple Pay. Please try again.';
        }

        return $response;
    }

    public function pre_confirmation_check()
    {
        $this->paypalCommon->processWalletConfirmation(
            'apple_pay',
            'paypalac_applepay_payload',
            [
                'title' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_TITLE ?? 'Apple Pay',
                'payload_missing' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_PAYLOAD_MISSING ?? 'Payload missing',
                'payload_invalid' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_PAYLOAD_INVALID ?? 'Invalid payload',
                'confirm_failed' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_CONFIRM_FAILED ?? 'Confirmation failed',
                'payer_action' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_PAYER_ACTION ?? 'Payer action required',
            ]
        );
    }

    protected function isOpcAjaxRequest(): bool
    {
        return (defined('IS_AJAX_REQUEST') && IS_AJAX_REQUEST === true);
    }

    public function createPayPalOrder(string $ppac_type, bool $redirectOnError = true): bool
    {
        global $order, $currencies;

        $order_info = $this->getOrderTotalsInfo($redirectOnError);

        if (count($order_info) === 0) {
            return false;
        }

        return $this->paypalCommon->createPayPalOrder($this, $order, $order_info, $ppac_type, $currencies);
    }

    protected function getOrderTotalsInfo(bool $redirectOnError = true): array
    {
        global $zcObserverPaypaladvcheckout;

        if (!isset($zcObserverPaypaladvcheckout) || !is_object($zcObserverPaypaladvcheckout)) {
            $message = MODULE_PAYMENT_PAYPALAC_ALERT_MISSING_OBSERVER ?? 'Observer missing';

            if ($redirectOnError) {
                $this->setMessageAndRedirect($message, FILENAME_CHECKOUT_PAYMENT);
            } else {
                $this->log->write('Apple Pay: ' . $message . '; wallet request will not redirect.');
            }

            return [];
        }

        $order_info = $zcObserverPaypaladvcheckout->getLastOrderValues();

        if (count($order_info) === 0) {
            $message = 'Missing order_total modifications; getLastOrderValues returned empty array.';
            $this->log->write('Apple Pay: ' . $message);

            if ($redirectOnError) {
                $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
            }

            return [];
        }

        $order_info['free_shipping_coupon'] = $zcObserverPaypaladvcheckout->orderHasFreeShippingCoupon();

        return $order_info;
    }

    protected function createOrderGuid(\order $order, string $ppac_type): string
    {
        return $this->paypalCommon->createOrderGuid($order, $ppac_type);
    }

    protected function buildWalletAjaxResponse(string $ppac_type): array
    {
        $client_id = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        $client_id = trim($client_id);

        // Note: Apple Pay via PayPal does not require a separate merchant-id parameter in the SDK URL.
        // PayPal manages the Apple Pay merchant configuration internally through your PayPal account.
        // Do NOT use MODULE_PAYMENT_PAYPALAC_MERCHANT_ID as it's a language label, not a configuration value.
        $merchant_id = '';

        $intent = (MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Final Sale' || ($ppac_type !== 'card' && MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Auth Only (Card-Only)'))
            ? 'CAPTURE'
            : 'AUTHORIZE';

        if ($client_id === '') {
            return ['success' => false, 'message' => MODULE_PAYMENT_PAYPALAC_APPLEPAY_ERROR_INITIALIZE ?? 'Unable to start Apple Pay. Please try again.'];
        }

        if ($this->createPayPalOrder($ppac_type, false) === false) {
            return ['success' => false];
        }

        $orderData = $_SESSION['PayPalAdvancedCheckout']['Order'] ?? [];
        $current = $orderData['current']['purchase_units'][0]['amount'] ?? [];
        $amount = $current['value'] ?? '';

        // Validate that amount is present and non-empty after order creation
        if ($amount === '' || !isset($orderData['id'])) {
            $this->log->write('Apple Pay: Order created but amount or orderID is missing from session structure', true);
            return ['success' => false, 'message' => 'Order validation failed'];
        }

        return [
            'success' => true,
            'orderID' => $orderData['id'],
            'amount' => $amount,
            'currency' => $current['currency_code'] ?? ($_SESSION['currency'] ?? ''),
            'intent' => $orderData['current']['intent'] ?? $intent,
            'clientId' => $client_id,
            'merchantId' => $merchant_id,
        ];
    }

    public function setMessageAndRedirect(string $error_message, string $redirect_page, bool $log_only = false)
    {
        global $messageStack;

        $this->log->write('Apple Pay redirect: ' . $error_message);

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
        unset($_SESSION['PayPalAdvancedCheckout']);
    }

    public function before_process()
    {
        global $order;

        // Verify the wallet payment flow was completed. If a JS error caused a raw
        // form POST without the payment modal completing, the session will not have
        // a valid PayPal order for this wallet type.
        if (empty($_SESSION['PayPalAdvancedCheckout']['Order']['id']) || empty($_SESSION['PayPalAdvancedCheckout']['Order']['wallet_payment_confirmed'])) {
            $this->log->write('Apple Pay::before_process, wallet payment not confirmed in session; aborting.');
            unset($_SESSION['PayPalAdvancedCheckout']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALAC_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info = $this->getOrderTotalsInfo();

        $this->paymentIsPending = false;

        $wallet_status = $_SESSION['PayPalAdvancedCheckout']['Order']['status'] ?? '';
        $wallet_user_action = $_SESSION['PayPalAdvancedCheckout']['Order']['user_action'] ?? '';
        $payer_action_fast_path = ($wallet_status === PayPalAdvancedCheckoutApi::STATUS_PAYER_ACTION_REQUIRED && $wallet_user_action === 'PAY_NOW');
        
        if (!in_array($wallet_status, self::WALLET_SUCCESS_STATUSES, true) && $payer_action_fast_path === false) {
            $this->log->write('Apple Pay::before_process, cannot capture/authorize; wrong status' . "\n" . Logger::logJSON($_SESSION['PayPalAdvancedCheckout']['Order'] ?? []));
            unset($_SESSION['PayPalAdvancedCheckout']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALAC_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }
        
        $response = $this->captureOrAuthorizePayment('apple_pay');

        $_SESSION['PayPalAdvancedCheckout']['Order']['status'] = $response['status'];
        unset($response['links']);
        $this->orderInfo = $response;

        if ($this->paymentIsPending === true) {
            $pending_status = (int)MODULE_PAYMENT_PAYPALAC_HELD_STATUS_ID;
            if ($pending_status > 0) {
                $this->order_status = $pending_status;
                $order->info['order_status'] = $pending_status;
            }
        }

        $txn_type = $this->orderInfo['intent'];
        $payment = $this->orderInfo['purchase_units'][0]['payments']['captures'][0] ?? $this->orderInfo['purchase_units'][0]['payments']['authorizations'][0];
        $payment_status = ($payment['status'] !== PayPalAdvancedCheckoutApi::STATUS_COMPLETED) ? $payment['status'] : (($txn_type === 'CAPTURE') ? PayPalAdvancedCheckoutApi::STATUS_CAPTURED : PayPalAdvancedCheckoutApi::STATUS_APPROVED);

        $this->orderInfo['payment_status'] = $payment_status;
        $this->orderInfo['paypal_payment_status'] = $payment['status'];
        $this->orderInfo['txn_type'] = $txn_type;

        $this->orderInfo['expiration_time'] = $payment['expiration_time'] ?? null;

        // -----
        // If the order's PayPal status doesn't indicate successful capture, ensure that
        // the overall order's status is set to this payment-module's PENDING status and set
        // a processing flag so that the after_process method will alert the store admin if
        // configured. Authorized payments (STATUS_CREATED) should use pending status since
        // they have not been captured yet.
        //
        $this->orderInfo['admin_alert_needed'] = false;
        if ($payment_status !== PayPalAdvancedCheckoutApi::STATUS_CAPTURED) {
            $this->order_status = (int)MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID;
            $order->info['order_status'] = $this->order_status;
            $this->orderInfo['admin_alert_needed'] = true;

            $this->log->write("==> Apple Pay::before_process: Payment status {$payment['status']} received from PayPal; order's status forced to pending.");
        }
    }

    protected function captureOrAuthorizePayment(string $payment_source): array
    {
        $response = $this->paypalCommon->captureWalletPayment(
            $this->ppr, 
            $this->log, 
            'Apple Pay',
            MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE,
            'apple_pay'
        );
        
        if ($response === false) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_TEXT_CAPTURE_FAILED ?? 'Capture failed', FILENAME_CHECKOUT_PAYMENT);
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
        $this->paypalCommon->updateOrderHistory($this->orderInfo, 'apple_pay');
        $this->paypalCommon->resetOrder();
    }

    protected function recordPayPalOrderDetails(int $orders_id): void
    {
        // Delegate to common class - but for applepay we have specific handling
        // Implementation similar to paypalac but adapted for Apple Pay
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
        
        $payment_type = 'apple_pay';
        $payment_source = $this->orderInfo['payment_source'][$payment_type] ?? [];
        
        $card_source = $payment_source['card'] ?? [];
        $name = $payment_source['name'] ?? [];
        
        $first_name = is_array($name) ? ($name['given_name'] ?? '') : '';
        $last_name = is_array($name) ? ($name['surname'] ?? '') : '';
        $email_address = $payment_source['email_address'] ?? '';
        
        $memo = [
            'source' => 'apple_pay',
            'card_info' => $card_source,
        ];
        $memo['paypal_order_id'] = $this->orderInfo['id'];
        $memo['paypal_payment_id'] = $payment['id'];
        if (isset($this->orderInfo['txn_type'])) {
            $memo['paypal_txn_type'] = $this->orderInfo['txn_type'];
        }

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
        $ppr = $this->getPayPalAdvancedCheckoutApi();
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
        return $this->paypalCommon->processRefund($oID, $this->getPayPalAdvancedCheckoutApi(), $this->code, self::CURRENT_VERSION);
    }

    public function _doAuth($oID, $order_amt, $currency = 'USD')
    {
        return $this->paypalCommon->processAuthorization($oID, $this->getPayPalAdvancedCheckoutApi(), $this->code, self::CURRENT_VERSION, $order_amt, $currency, false);
    }

    public function _doCapt($oID, $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        return $this->paypalCommon->processCapture($oID, $this->getPayPalAdvancedCheckoutApi(), $this->code, self::CURRENT_VERSION, $captureType, $order_amt, $order_currency);
    }

    public function _doVoid($oID)
    {
        return $this->paypalCommon->processVoid($oID, $this->getPayPalAdvancedCheckoutApi(), $this->code, self::CURRENT_VERSION);
    }

    public function check(): bool
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS'");
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
                ('Module Version', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION', '$current_version', 'Currently-installed module version.', 6, 0, 'zen_cfg_read_only(', NULL, now()),
                ('Enable PayPal Apple Pay?', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS', 'False', 'Do you want to enable PayPal Apple Pay payments?', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),
                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),
                ('Payment Zone', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );
        
        // -----
        // Migrate configuration values from the old paypalr_applepay module if it was previously installed.
        // This allows stores upgrading from paypalr to paypalac to keep their existing settings
        // without having to reconfigure.
        //
        $old_config = $db->Execute(
            "SELECT configuration_key, configuration_value
               FROM " . TABLE_CONFIGURATION . "
              WHERE configuration_key LIKE 'MODULE\_PAYMENT\_PAYPALR\_APPLEPAY\_%'"
        );
        while (!$old_config->EOF) {
            $old_key = $old_config->fields['configuration_key'];
            $new_key = str_replace('MODULE_PAYMENT_PAYPALR_APPLEPAY_', 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_', $old_key);
            $old_value = $old_config->fields['configuration_value'];
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION . "
                    SET configuration_value = '" . zen_db_input($old_value) . "'
                  WHERE configuration_key = '" . zen_db_input($new_key) . "'
                  LIMIT 1"
            );
            $old_config->MoveNext();
        }

        // Define the module's current version so that the tableCheckup method will apply all changes
        define('MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION', '0.0.0');
        $this->tableCheckup();
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_VERSION',
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS',
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_ZONE',
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_SHOPPING_CART',
            'MODULE_PAYMENT_PAYPALAC_APPLEPAY_PRODUCT_PAGE',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_%'");
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
