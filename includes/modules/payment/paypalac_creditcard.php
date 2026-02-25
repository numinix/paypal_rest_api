<?php
/**
 * paypalac_creditcard.php payment module class for handling Credit Cards via PayPal Advanced Checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.5
 */
/**
 * Load the support class' auto-loader and common class.
 */
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
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

LanguageCompatibility::load('paypalac_creditcard');

/**
 * The PayPal Credit Cards payment module using PayPal's REST APIs (v2)
 */
class paypalac_creditcard extends base
{
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS') ? MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS : 'False';
    }

    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER : null;
    }

    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE') ? (int)MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE : 0;
    }

    protected const CURRENT_VERSION = '1.3.5';
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
    
    // Credit Cards uses on-site card entry but doesn't use AJAX pre-confirmation
    public bool $cardsAccepted = true;
    public bool $collectsCardDataOnsite = false;


    public PayPalRestfulApi $ppr;
    protected ErrorInfo $errorInfo;
    public Logger $log;
    public bool $emailAlerts = false;
    protected PayPalCommon $paypalCommon;
    protected array $ccInfo = [];
    protected array $orderInfo = [];
    protected bool $paymentIsPending = false;
    protected bool $billingCountryIsSupported = true;
    protected bool $shippingCountryIsSupported = true;
    public array $orderCustomerCache = [];
    protected bool $onOpcConfirmationPage = false;
    protected array $paypalRestfulSessionOnEntry = [];

    /**
     * Get the credit card information for PayPal order creation.
     *
     * This method provides public access to the protected ccInfo property,
     * which is needed by PayPalCommon::createPayPalOrder() to build the
     * payment source for vault-based card payments.
     *
     * @return array A copy of the credit card/vault information
     */
    public function getCcInfo(): array
    {
        // Return a copy to prevent external modification of internal state
        return [...$this->ccInfo];
    }

    /**
     * class constructor
     */
    public function __construct()
    {
        global $order, $messageStack, $loaderPrefix, $current_page;

        $this->code = 'paypalac_creditcard';

        $curl_installed = (function_exists('curl_init'));

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE ?? 'Credit Card';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE_ADMIN ?? 'Credit Cards via PayPal Advanced Checkout') . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL ?? 'cURL not installed'));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_DESCRIPTION ?? 'Accept credit card payments via PayPal Advanced Checkout (v%s)', self::CURRENT_VERSION);
            
            // Add upgrade button if current version is less than latest version
            // Only show upgrade link if the module is actually installed (version > 0.0.0)
            $installed_version = defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION') ? MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION : '0.0.0';
            if ($installed_version !== '0.0.0' && version_compare($installed_version, self::CURRENT_VERSION, '<')) {
                $this->description .= sprintf(
                    MODULE_PAYMENT_PAYPALAC_TEXT_ADMIN_UPGRADE_AVAILABLE ?? 
                    '<br><br><p><strong>Update Available:</strong> Version %2$s is available. You are currently running version %1$s.</p><p><a class="paypalac-upgrade-button" href="%3$s">Upgrade to %2$s</a></p>',
                    $installed_version,
                    self::CURRENT_VERSION,
                    zen_href_link('paypalac_upgrade.php', 'module=paypalac_creditcard&action=upgrade', 'SSL')
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

        // Credit cards support both auth-only and final sale modes
        $ppac_type = $_SESSION['PayPalRestful']['ppac_type'] ?? 'card';
        if (MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Final Sale' || ($ppac_type !== 'card' && MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE === 'Auth Only (Card-Only)')) {
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
            global $zcObserverPaypalrestful;
            if (!isset($zcObserverPaypalrestful)) {
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
            $this->paypalRestfulSessionOnEntry = $_SESSION['PayPalRestful'] ?? [];
            $this->attach($this, ['NOTIFY_OPC_OBSERVER_SESSION_FIXUPS']);
        }

        // Check for required main PayPal module
        if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALAC_CREDITCARD_ERROR_PAYPAL_REQUIRED ?? ' (Requires main PayPal module)');
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
        if (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION') && MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION === $current_version) {
            return;
        }
        
        // Check for version-specific configuration updates
        if (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION')) {
            switch (true) {
                case version_compare(MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION, '1.3.4', '<'):
                    $db->Execute(
                        "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                         VALUES
                            ('Accepted Card Brands', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS', 'amex,discover,jcb,maestro,mastercard,solo,visa', 'Select the card brands you accept for PayPal Advanced Card Fields. These selections control which card logos are displayed on the saved cards add form.', 6, 0, 'zen_cfg_select_multioption([\'amex\', \'discover\', \'jcb\', \'maestro\', \'mastercard\', \'solo\', \'visa\'], ', NULL, now())"
                    );
                
                case version_compare(MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION, '1.3.5', '<'):
                    $db->Execute(
                        "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
                            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                         VALUES
                            ('Show Save Card Checkbox', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX', 'True', 'Display the \"Save Card\" checkbox during checkout? If disabled, customers will not see the option to save their card for future use. Note: For orders containing subscriptions, a notice will still be displayed that the card will be saved.', 6, 0, 'zen_cfg_select_option([''True'', ''False''], ', NULL, now())"
                    );
                
                default:
                    break;
            }
        }
        
        // Record the current version of the payment module into its database configuration setting
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '$current_version',
                    last_modified = now()
              WHERE configuration_key = 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION'
              LIMIT 1"
        );
    }

    protected function validateConfiguration(bool $curl_installed): bool
    {
        if ($curl_installed === false) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALAC_ERROR_NO_CURL ?? 'cURL not installed');
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
        if (isset($this->ppr) && $this->ppr instanceof PayPalRestfulApi) {
            return $this->ppr;
        }

        $client_id = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        $secret = (MODULE_PAYMENT_PAYPALAC_SERVER === 'live') ? MODULE_PAYMENT_PAYPALAC_SECRET_L : MODULE_PAYMENT_PAYPALAC_SECRET_S;

        // Trim credentials to match PayPalRestfulApi::getConfiguredCredentials behavior
        $client_id = trim($client_id);
        $secret = trim($secret);

        if (empty($client_id) || empty($secret)) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALAC_ALERT_INVALID_CONFIGURATION ?? 'Invalid configuration');
            return null;
        }

        try {
            $this->ppr = new PayPalRestfulApi(
                MODULE_PAYMENT_PAYPALAC_SERVER,
                $client_id,
                $secret
            );
            return $this->ppr;
        } catch (\Exception $e) {
            $this->log->write('Credit Cards: Error creating PayPalRestfulApi: ' . $e->getMessage());
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
        $js = '';
        if (defined('CC_OWNER_MIN_LENGTH') && defined('CC_NUMBER_MIN_LENGTH')) {
            $js = '  if (payment_value == "' . $this->code . '") {' . "\n" .
                  '    var saved_card_field = document.checkout_payment.paypalac_saved_card;' . "\n" .
                  '    var saved_card_value = "";' . "\n" .
                  '    if (saved_card_field) {' . "\n" .
                  '      if (saved_card_field.length && saved_card_field[0].type === "radio") {' . "\n" .
                  '        for (var i = 0; i < saved_card_field.length; i++) {' . "\n" .
                  '          if (saved_card_field[i].checked) {' . "\n" .
                  '            saved_card_value = saved_card_field[i].value;' . "\n" .
                  '            break;' . "\n" .
                  '          }' . "\n" .
                  '        }' . "\n" .
                  '      } else {' . "\n" .
                  '        saved_card_value = saved_card_field.value;' . "\n" .
                  '      }' . "\n" .
                  '    }' . "\n" .
                  '    var using_saved_card = saved_card_value && saved_card_value !== "new";' . "\n" .
                  '    if (!using_saved_card) {' . "\n" .
                  '      var cc_owner_field = document.checkout_payment.paypalac_cc_owner;' . "\n" .
                  '      var cc_number_field = document.checkout_payment.paypalac_cc_number;' . "\n" .
                  '      if (cc_owner_field && cc_number_field) {' . "\n" .
                  '        var cc_owner = cc_owner_field.value;' . "\n" .
                  '        var cc_number = cc_number_field.value;' . "\n";
            
            if (CC_OWNER_MIN_LENGTH > 0) {
                $js .= '        if (cc_owner == "" || cc_owner.length < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n" .
                       '          error_message = error_message + "' . MODULE_PAYMENT_PAYPALAC_TEXT_JS_CC_OWNER . '";' . "\n" .
                       '          error = 1;' . "\n" .
                       '        }' . "\n";
            }
            
            if (CC_NUMBER_MIN_LENGTH > 0) {
                $js .= '        if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n" .
                       '          error_message = error_message + "' . MODULE_PAYMENT_PAYPALAC_TEXT_JS_CC_NUMBER . '";' . "\n" .
                       '          error = 1;' . "\n" .
                       '        }' . "\n";
            }
            
            $js .= '      }' . "\n" .
                   '    }' . "\n" .
                   '  }' . "\n";
        }
        return $js;
    }

    public function selection(): array
    {
        global $order;
        
        unset($_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed']);

        $creditCardCss = '<link rel="stylesheet" href="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/paypalac.css' . '">';

        // Create dropdowns for expiry date
        $expires_month = [];
        $expires_year = [];
        for ($month = 1; $month < 13; $month++) {
            $expires_month[] = ['id' => sprintf('%02u', $month), 'text' => date('F - (m)', mktime(0, 0, 0, $month, 1))];
        }
        $this_year = date('Y');
        for ($year = $this_year; $year < (int)$this_year + 15; $year++) {
            $expires_year[] = ['id' => $year, 'text' => $year];
        }

        // Get vaulted cards if enabled
        // NOTE: If the paypalac_savedcard module is enabled, saved cards will be displayed
        // as separate top-level payment options instead of within this module.
        $vaultEnabled = (defined('MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT') && MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT === 'True');
        $savedCardModuleEnabled = (defined('MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS') && MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS === 'True');
        
        $vaultedCards = [];
        if ($vaultEnabled && !$savedCardModuleEnabled && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) {
            // Only fetch and display vaulted cards here if the separate saved card module is not enabled
            $vaultedCards = $this->paypalCommon->getVaultedCardsForCustomer($_SESSION['customer_id'], true);
        }

        $savedCardSelection = $_POST['paypalac_saved_card'] ?? ($_SESSION['PayPalRestful']['saved_card'] ?? 'new');
        if ($savedCardSelection === '' && !empty($vaultedCards)) {
            $savedCardSelection = $vaultedCards[0]['vault_id'];
        }
        if ($savedCardSelection !== 'new' && !empty($vaultedCards)) {
            $validSavedCard = false;
            foreach ($vaultedCards as $card) {
                if ($card['vault_id'] === $savedCardSelection) {
                    $validSavedCard = true;
                    break;
                }
            }
            if ($validSavedCard === false) {
                $savedCardSelection = 'new';
            }
        }

        $allowSaveCard = ($_SESSION['customer_id'] ?? 0) > 0;
        $forceSaveCard = $allowSaveCard && $savedCardSelection === 'new' && $this->orderRequiresVaultedCard();
        $saveCardChecked = $allowSaveCard && ($forceSaveCard || !empty($_POST['paypalac_cc_save_card']) || (!empty($_SESSION['PayPalRestful']['save_card'])));
        if ($savedCardSelection !== 'new') {
            $saveCardChecked = false;
            $forceSaveCard = false;
        }

        $billing_name = zen_output_string_protected($order->billing['firstname'] . ' ' . $order->billing['lastname']);

        // Check if bootstrap template
        $is_bootstrap_template = (function_exists('zca_bootstrap_active') && zca_bootstrap_active() === true);

        // Build onfocus attribute for radio button selection (compatibility with Zen Cart's methodSelect function)
        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        // Build fields array
        $fields = [];

        // Card owner name
        $fields[] = [
            'title' => MODULE_PAYMENT_PAYPALAC_CC_OWNER ?? 'Cardholder Name',
            'field' => zen_draw_input_field('paypalac_cc_owner', $billing_name, 'class="ppr-creditcard-field ppr-card-new" id="paypalac-cc-owner" autocomplete="cc-name"' . $onFocus),
            'tag' => 'paypalac-cc-owner',
        ];

        // Card number
        $fields[] = [
            'title' => MODULE_PAYMENT_PAYPALAC_CC_NUMBER ?? 'Card Number',
            'field' => zen_draw_input_field('paypalac_cc_number', '', 'class="ppr-creditcard-field ppr-card-new" id="paypalac-cc-number" autocomplete="cc-number"' . $onFocus),
            'tag' => 'paypalac-cc-number',
        ];

        // Expiry date
        $fields[] = [
            'title' => MODULE_PAYMENT_PAYPALAC_CC_EXPIRES ?? 'Expiration Date',
            'field' =>
                '<div class="ppr-cc-expiration">' .
                zen_draw_pull_down_menu('paypalac_cc_expires_month', $expires_month, date('m'), 'class="ppr-creditcard-field ppr-card-new" id="paypalac-cc-expires-month"' . $onFocus) .
                zen_draw_pull_down_menu('paypalac_cc_expires_year', $expires_year, $this_year, 'class="ppr-creditcard-field ppr-card-new" id="paypalac-cc-expires-year"' . $onFocus) .
                '</div>',
            'tag' => 'paypalac-cc-expires-month',
        ];

        // CVV
        $fields[] = [
            'title' => MODULE_PAYMENT_PAYPALAC_CC_CVV ?? 'CVV',
            'field' => zen_draw_input_field('paypalac_cc_cvv', '', 'class="ppr-creditcard-field ppr-card-new" id="paypalac-cc-cvv" size="4" maxlength="4" autocomplete="cc-csc"' . $onFocus),
            'tag' => 'paypalac-cc-cvv',
        ];

        // Save card checkbox / subscription notice
        $showSaveCardCheckbox = (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX') && MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX === 'True');
        if ($vaultEnabled && $allowSaveCard) {
            if ($forceSaveCard) {
                // For subscriptions, always show the notice that the card will be saved
                $notice = MODULE_PAYMENT_PAYPALAC_SAVE_CARD_SUBSCRIPTION_NOTICE ?? 'This card will be stored to process your subscription payments.';
                $fields[] = [
                    'title' => MODULE_PAYMENT_PAYPALAC_SAVE_CARD_PROMPT ?? 'Save for future use',
                    'field' =>
                        zen_draw_hidden_field('paypalac_cc_save_card', 'on') .
                        '<span class="ppr-save-card-note">' . zen_output_string_protected($notice) . '</span>',
                    'tag' => 'ppr-cc-save-card',
                ];
            } elseif ($showSaveCardCheckbox) {
                // Only show the checkbox if the configuration allows it
                if ($is_bootstrap_template === false) {
                    $fields[] = [
                        'title' => MODULE_PAYMENT_PAYPALAC_SAVE_CARD_PROMPT ?? 'Save for future use',
                        'field' => zen_draw_checkbox_field('paypalac_cc_save_card', 'on', $saveCardChecked, 'class="ppr-creditcard-field ppr-card-new" id="ppr-cc-save-card"'),
                        'tag' => 'ppr-cc-save-card',
                    ];
                } else {
                    $fields[] = [
                        'title' => '&nbsp;',
                        'field' =>
                            '<div class="custom-control custom-checkbox ppr-creditcard-field ppr-card-new">' .
                                zen_draw_checkbox_field('paypalac_cc_save_card', 'on', $saveCardChecked, 'id="ppr-cc-save-card" class="custom-control-input"') .
                                '<label class="custom-control-label checkboxLabel" for="ppr-cc-save-card">' . (MODULE_PAYMENT_PAYPALAC_SAVE_CARD_PROMPT ?? 'Save for future use') . '</label>' .
                            '</div>',
                    ];
                }
            }
        }

        // Build module display with title and card images
        $moduleDisplay = $this->title;
        $cardsAccepted = $this->buildCardsAccepted();
        if (!empty($cardsAccepted)) {
            $moduleDisplay = trim($moduleDisplay . ' ' . $cardsAccepted);
        }
        if ($vaultEnabled && !empty($vaultedCards)) {
            $moduleDisplay .= $this->buildSavedCardInlineOptions($vaultedCards, $savedCardSelection, $onFocus);
        }

        // Load the checkout script to handle radio button selection when focusing on fields
        // Append it to the module output to avoid creating a separate div row
        $checkoutScript = $creditCardCss . '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalac.checkout.js"></script>';
        $moduleDisplay .= $checkoutScript;

        return [
            'id' => $this->code,
            'module' => $moduleDisplay,
            'fields' => $fields,
        ];
    }

    protected function buildSavedCardOptions(array $vaultedCards, string $selectedVaultId, string $onFocus = ''): string
    {
        $html = '<select name="paypalac_saved_card" id="paypalac-saved-card" class="ppr-saved-card-select"' . $onFocus . '>';
        $html .= '<option value="new"' . ($selectedVaultId === 'new' ? ' selected="selected"' : '') . '>' . 
                 (MODULE_PAYMENT_PAYPALAC_NEW_CARD ?? 'Use a new card') . '</option>';
        
        foreach ($vaultedCards as $card) {
            $brand = $card['brand'] ?: ($card['card_type'] ?: (MODULE_PAYMENT_PAYPALAC_SAVED_CARD_GENERIC ?? 'Card'));
            $card_label = $brand . ' ending in ' . $card['last_digits'];
            if (!empty($card['expiry'])) {
                $card_label .= ' (Exp: ' . $card['expiry'] . ')';
            }
            $selected = ($card['vault_id'] === $selectedVaultId) ? ' selected="selected"' : '';
            $html .= '<option value="' . zen_output_string($card['vault_id']) . '"' . $selected . '>' . 
                     zen_output_string($card_label) . '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }

    protected function buildCardsAccepted(): string
    {
        $cards_accepted = '';
        if (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS') && strlen(MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS) > 0) {
            // Map card type names to image filenames
            $cardImageMap = [
                'amex' => 'american_express.png',
                'discover' => 'discover.png',
                'jcb' => 'jcb.png',
                'maestro' => 'maestro.png',
                'mastercard' => 'mastercard.png',
                'solo' => 'solo.png',
                'visa' => 'visa.png',
            ];
            $cardLabelMap = [
                'amex' => 'American Express',
                'discover' => 'Discover',
                'jcb' => 'JCB',
                'maestro' => 'Maestro',
                'mastercard' => 'Mastercard',
                'solo' => 'Solo',
                'visa' => 'Visa',
            ];
            
            $accepted_types = explode(',', MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS);
            $cardImages = [];
            foreach ($accepted_types as $type) {
                $type = strtolower(trim($type));
                if (isset($cardImageMap[$type])) {
                    $imagePath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/images/' . $cardImageMap[$type];
                    $cardLabel = $cardLabelMap[$type] ?? $type;
                    $cardImages[] = zen_image($imagePath, $cardLabel, '', '', 'class="paypalac-card-logo"');
                }
            }
            if (!empty($cardImages)) {
                $cards_accepted = '<span class="paypalac-card-logos">' . implode('', $cardImages) . '</span>';
            }
        }
        return $cards_accepted;
    }

    protected function buildSavedCardInlineOptions(array $vaultedCards, string $selectedVaultId, string $onFocus = ''): string
    {
        $html = '<div class="ppr-saved-card-inline">';

        $html .= '<label class="ppr-saved-card-option">' .
            zen_draw_radio_field('paypalac_saved_card', 'new', $selectedVaultId === 'new', 'class="ppr-saved-card" id="paypalac-saved-card-new"' . $onFocus) .
            '<span>' . (MODULE_PAYMENT_PAYPALAC_NEW_CARD ?? 'Use a new card') . '</span>' .
            '</label>';

        foreach ($vaultedCards as $card) {
            $brand = $card['brand'] ?: ($card['card_type'] ?: (MODULE_PAYMENT_PAYPALAC_SAVED_CARD_GENERIC ?? 'Card'));
            $card_label = $brand . ' ending in ' . $card['last_digits'];
            if (!empty($card['expiry'])) {
                $card_label .= ' (Exp: ' . $card['expiry'] . ')';
            }
            $html .= '<label class="ppr-saved-card-option">' .
                zen_draw_radio_field(
                    'paypalac_saved_card',
                    zen_output_string($card['vault_id']),
                    $card['vault_id'] === $selectedVaultId,
                    'class="ppr-saved-card" id="paypalac-saved-card-' . zen_output_string($card['vault_id']) . '"' . $onFocus
                ) .
                '<span>' . zen_output_string($card_label) . '</span>' .
                '</label>';
        }

        $html .= '</div>';
        return $html;
    }

    protected function orderRequiresVaultedCard(): bool
    {
        if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
            return false;
        }

        $products = $_SESSION['cart']->get_products();
        if (!is_array($products) || empty($products)) {
            return false;
        }

        if (!class_exists('paypalSavedCardRecurring')) {
            $savedCardRecurringPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
            if (file_exists($savedCardRecurringPath)) {
                require_once $savedCardRecurringPath;
            }
        }

        if (class_exists('paypalSavedCardRecurring')) {
            $recurring = new paypalSavedCardRecurring();
            $subscriptions = $recurring->find_subscription_products_in_order($products);
            if (!empty($subscriptions)) {
                return true;
            }
        }

        return $this->cartHasPlanIdSubscription($products);
    }

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

    protected function normalizeAttributeKey(string $label): string
    {
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? $label;
        return trim($label, '_');
    }

    public function pre_confirmation_check()
    {
        // Set payment type - this module always uses card payments
        $_SESSION['PayPalRestful']['ppac_type'] = 'card';
        
        // Store saved card selection if provided
        // Check both direct and forwarded field names
        if (isset($_POST['paypalac_saved_card'])) {
            $_SESSION['PayPalRestful']['saved_card'] = $_POST['paypalac_saved_card'];
        } elseif (isset($_POST['ppac_saved_card'])) {
            $_SESSION['PayPalRestful']['saved_card'] = $_POST['ppac_saved_card'];
        }
        
        // Store save card preference
        if (isset($_POST['paypalac_cc_save_card'])) {
            $_SESSION['PayPalRestful']['save_card'] = $_POST['paypalac_cc_save_card'];
        } elseif (isset($_POST['ppac_cc_save_card'])) {
            $_SESSION['PayPalRestful']['save_card'] = $_POST['ppac_cc_save_card'];
        } elseif ($this->orderRequiresVaultedCard()) {
            $_SESSION['PayPalRestful']['save_card'] = true;
        }
        
        // Validate card information
        if (!$this->validateCardInformation(true)) {
            // Validation failed, redirect back to payment page
            // Error messages will already be set by validateCardInformation
            return;
        }
        
        // Create PayPal order for credit card payment
        $paypal_order_created = $this->createPayPalOrder('card');
        if ($paypal_order_created === false) {
            $error_info = $this->ppr->getErrorInfo();
            $error_code = $error_info['details'][0]['issue'] ?? 'OTHER';
            $this->sendAlertEmail(
                MODULE_PAYMENT_PAYPALAC_ALERT_SUBJECT_ORDER_ATTN,
                MODULE_PAYMENT_PAYPALAC_ALERT_ORDER_CREATE . Logger::logJSON($error_info)
            );
            $this->setMessageAndRedirect(
                sprintf(MODULE_PAYMENT_PAYPALAC_TEXT_CREATE_ORDER_ISSUE, MODULE_PAYMENT_PAYPALAC_CREDITCARD_TEXT_TITLE, $error_code),
                FILENAME_CHECKOUT_PAYMENT
            );
        }
    }

    protected function validateCardInformation(bool $is_preconfirmation): bool
    {
        global $messageStack, $order;

        $saved_card = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalRestful']['saved_card'] ?? 'new'));

        // If using a saved card, minimal validation needed
        if ($saved_card !== 'new') {
            $_SESSION['PayPalRestful']['saved_card'] = $saved_card;

            $vaultCards = $this->paypalCommon->getVaultedCardsForCustomer($_SESSION['customer_id'] ?? 0, true);
            $cardFound = false;
            foreach ($vaultCards as $card) {
                if ($card['vault_id'] === $saved_card) {
                    $cardFound = true;
                    $expiryMonth = '';
                    $expiryYear = '';
                    if (!empty($card['expiry']) && preg_match('/^(\d{4})-(\d{2})/', $card['expiry'], $matches) === 1) {
                        $expiryYear = $matches[1];
                        $expiryMonth = $matches[2];
                    }

                    $billingName = trim($order->billing['firstname'] . ' ' . $order->billing['lastname']);
                    $this->ccInfo = [
                        'type' => $card['card_type'] ?? MODULE_PAYMENT_PAYPALAC_SAVED_CARD_GENERIC ?? 'Card',
                        'number' => '0000' . ($card['last_digits'] ?? ''),
                        'last_digits' => $card['last_digits'] ?? '',
                        'expiry_month' => $expiryMonth,
                        'expiry_year' => $expiryYear,
                        'expiry' => $card['expiry'] ?? '',
                        'name' => $billingName,
                        'redirect' => $this->getListenerEndpoint(),
                        'vault_id' => $card['vault_id'],
                        'billing_address' => $card['billing_address'] ?? [],
                        'store_card' => false,
                        'use_vault' => true,
                    ];
                    break;
                }
            }

            // If the selected card wasn't found in the vault, show an error
            if (!$cardFound) {
                $error_message = MODULE_PAYMENT_PAYPALAC_TEXT_SAVED_CARD_NOT_FOUND ?? 'The selected card is no longer available. Please select a different card or enter a new one.';
                $messageStack->add_session('checkout_payment', $error_message, 'error');
                if ($is_preconfirmation) {
                    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
                }
                return false;
            }

            return true;
        }

        // Validate new card entry
        // Support fallback field names that might be forwarded from the checkout confirmation page
        $cc_owner = $_POST['paypalac_cc_owner'] ?? ($_POST['ppac_cc_owner'] ?? '');
        $cc_number_raw = $_POST['paypalac_cc_number'] ?? ($_POST['ppac_cc_number'] ?? '');
        $cc_number = preg_replace('/[^0-9]/', '', $cc_number_raw);
        $cc_cvv = $_POST['paypalac_cc_cvv'] ?? ($_POST['ppac_cc_cvv'] ?? '');

        $error = false;

        if (defined('CC_OWNER_MIN_LENGTH') && strlen($cc_owner) < CC_OWNER_MIN_LENGTH) {
            $error_message = MODULE_PAYMENT_PAYPALAC_TEXT_CC_OWNER_TOO_SHORT ?? 'Cardholder name is too short';
            $messageStack->add_session('checkout_payment', $error_message, 'error');
            $error = true;
        }

        if (defined('CC_NUMBER_MIN_LENGTH') && strlen($cc_number) < CC_NUMBER_MIN_LENGTH) {
            $error_message = MODULE_PAYMENT_PAYPALAC_TEXT_CC_NUMBER_TOO_SHORT ?? 'Card number is too short';
            $messageStack->add_session('checkout_payment', $error_message, 'error');
            $error = true;
        }

        if (strlen($cc_cvv) < 3 || strlen($cc_cvv) > 4) {
            $error_message = MODULE_PAYMENT_PAYPALAC_TEXT_CC_CVV_INVALID ?? 'CVV must be 3 or 4 digits';
            $messageStack->add_session('checkout_payment', $error_message, 'error');
            $error = true;
        }

        // Validate expiry month and year
        $expiry_month = $_POST['paypalac_cc_expires_month'] ?? ($_POST['ppac_cc_expires_month'] ?? '');
        $expiry_year = $_POST['paypalac_cc_expires_year'] ?? ($_POST['ppac_cc_expires_year'] ?? '');
        if (empty($expiry_month) || empty($expiry_year)) {
            $error_message = MODULE_PAYMENT_PAYPALAC_TEXT_CC_EXPIRY_REQUIRED ?? 'Card expiration date is required';
            $messageStack->add_session('checkout_payment', $error_message, 'error');
            $error = true;
        }

        if ($error) {
            if ($is_preconfirmation) {
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            }
            return false;
        }

        $allowSaveCard = ($_SESSION['customer_id'] ?? 0) > 0;
        $forceSaveCard = $allowSaveCard && $this->orderRequiresVaultedCard();
        $storeCard = $allowSaveCard && ($forceSaveCard || !empty($_POST['paypalac_cc_save_card']) || !empty($_POST['ppac_cc_save_card']));
        if ($storeCard === true) {
            $_SESSION['PayPalRestful']['save_card'] = true;
        } else {
            unset($_SESSION['PayPalRestful']['save_card']);
        }

        $this->ccInfo = [
            'type' => MODULE_PAYMENT_PAYPALAC_TEXT_CC_TYPE_GENERIC ?? 'Card',
            'number' => $cc_number,
            'expiry_month' => $expiry_month,
            'expiry_year' => $expiry_year,
            'name' => $cc_owner,
            'security_code' => $cc_cvv,
            'redirect' => $this->getListenerEndpoint(),
            'last_digits' => substr($cc_number, -4),
            'store_card' => $storeCard,
        ];

        // Do NOT store card data in session for security/PCI compliance
        // The card data must be re-entered or re-submitted if POST data is lost
        $_SESSION['PayPalRestful']['saved_card'] = 'new';

        return true;
    }

    protected function getListenerEndpoint(): string
    {
        if (defined('REDIRECT_LISTENER')) {
            return REDIRECT_LISTENER;
        }

        return HTTP_SERVER . DIR_WS_CATALOG . 'ppac_listener.php';
    }

    protected function isOpcAjaxRequest(): bool
    {
        return (defined('IS_AJAX_REQUEST') && IS_AJAX_REQUEST === true);
    }

    protected function createPayPalOrder(string $ppac_type): bool
    {
        global $order, $currencies;

        $order_info = $this->getOrderTotalsInfo();

        return $this->paypalCommon->createPayPalOrder($this, $order, $order_info, $ppac_type, $currencies);
    }

    protected function getOrderTotalsInfo(): array
    {
        global $zcObserverPaypalrestful;

        if (!isset($zcObserverPaypalrestful) || !is_object($zcObserverPaypalrestful)) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info = $zcObserverPaypalrestful->getLastOrderValues();

        if (count($order_info) === 0) {
            $this->log->write('Credit Cards: Missing order_total modifications; getLastOrderValues returned empty array.');
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info['free_shipping_coupon'] = $zcObserverPaypalrestful->orderHasFreeShippingCoupon();

        return $order_info;
    }

    protected function createOrderGuid(\order $order, string $ppac_type): string
    {
        return $this->paypalCommon->createOrderGuid($order, $ppac_type);
    }

    public function setMessageAndRedirect(string $error_message, string $redirect_page, bool $log_only = false)
    {
        global $messageStack;

        $this->log->write('Credit Cards redirect: ' . $error_message);

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
        // For non-AJAX checkout, generate hidden fields to forward card data
        $savedCardSelection = $_POST['paypalac_saved_card'] ?? 'new';
        $hiddenFields = zen_draw_hidden_field('ppac_saved_card', $savedCardSelection);
        
        if ($savedCardSelection === 'new') {
            $hiddenFields .= zen_draw_hidden_field('ppac_cc_owner', $_POST['paypalac_cc_owner'] ?? '');
            $hiddenFields .= zen_draw_hidden_field('ppac_cc_expires_month', $_POST['paypalac_cc_expires_month'] ?? '');
            $hiddenFields .= zen_draw_hidden_field('ppac_cc_expires_year', $_POST['paypalac_cc_expires_year'] ?? '');
            $hiddenFields .= zen_draw_hidden_field('ppac_cc_number', $_POST['paypalac_cc_number'] ?? '');
            $hiddenFields .= zen_draw_hidden_field('ppac_cc_cvv', $_POST['paypalac_cc_cvv'] ?? '');
            if (!empty($_POST['paypalac_cc_save_card'])) {
                $hiddenFields .= zen_draw_hidden_field('ppac_cc_save_card', $_POST['paypalac_cc_save_card']);
            }
            if (!empty($_POST['paypalac_cc_sca_always'])) {
                $hiddenFields .= zen_draw_hidden_field('ppac_cc_sca_always', $_POST['paypalac_cc_sca_always']);
            }
        }
        
        return $hiddenFields;
    }

    public function process_button_ajax()
    {
        // Credit card module always uses card payment source
        $savedCardSelection = $_POST['paypalac_saved_card'] ?? 'new';
        $ccFields = [
            'ccFields' => [
                'ppac_saved_card' => 'paypalac_saved_card',
            ],
        ];
        if ($savedCardSelection === 'new') {
            $ccFields['ccFields']['ppac_cc_owner'] = 'paypalac_cc_owner';
            $ccFields['ccFields']['ppac_cc_expires_month'] = 'paypalac_cc_expires_month';
            $ccFields['ccFields']['ppac_cc_expires_year'] = 'paypalac_cc_expires_year';
            $ccFields['ccFields']['ppac_cc_number'] = 'paypalac_cc_number';
            $ccFields['ccFields']['ppac_cc_cvv'] = 'paypalac_cc_cvv';
            if (!empty($_POST['paypalac_cc_save_card'])) {
                $ccFields['ccFields']['ppac_cc_save_card'] = 'paypalac_cc_save_card';
            }
            if (isset($_POST['paypalac_cc_sca_always'])) {
                $ccFields['ccFields']['ppac_cc_sca_always'] = 'paypalac_cc_sca_always';
            }
        }
        return $ccFields;
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
            $this->log->write('Credit Cards::before_process, cannot capture/authorize; wrong status' . "\n" . Logger::logJSON($_SESSION['PayPalRestful']['Order'] ?? []));
            unset($_SESSION['PayPalRestful']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALAC_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }
        
        $response = $this->captureOrAuthorizePayment('card');

        $_SESSION['PayPalRestful']['Order']['status'] = $response['status'];
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
        $payment_status = ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED) ? $payment['status'] : (($txn_type === 'CAPTURE') ? PayPalRestfulApi::STATUS_CAPTURED : PayPalRestfulApi::STATUS_APPROVED);

        $this->orderInfo['payment_status'] = $payment_status;
        $this->orderInfo['paypal_payment_status'] = $payment['status'];
        $this->orderInfo['txn_type'] = $txn_type;

        $this->orderInfo['expiration_time'] = $payment['expiration_time'] ?? null;

        if ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED) {
            $pending_status = (int)MODULE_PAYMENT_PAYPALAC_HELD_STATUS_ID;
            if ($pending_status > 0) {
                $this->order_status = $pending_status;
                $order->info['order_status'] = $pending_status;
            }
            $this->orderInfo['admin_alert_needed'] = true;
        } else {
            $this->orderInfo['admin_alert_needed'] = false;
        }

        // Store vault card data in session if present
        $this->storeVaultCardDataInSession($this->orderInfo);
    }

    protected function storeVaultCardDataInSession(array $response): void
    {
        $card_source = $this->extractCardSource($response);

        if ($card_source === null || ($card_source['vault']['id'] ?? '') === '') {
            return;
        }

        $visible = !empty($_SESSION['PayPalRestful']['save_card']);

        $_SESSION['PayPalRestful']['VaultCardData'] = [
            'card_source' => $card_source,
            'visible' => $visible,
        ];

        // Backward compatibility for any legacy consumers
        $_SESSION['PayPalRestful']['vault_card'] = $card_source;
    }

    protected function extractCardSource(array $response): ?array
    {
        $card_source = $response['payment_source']['card'] ??
            ($response['purchase_units'][0]['payments']['captures'][0]['payment_source']['card'] ?? null);

        if ($card_source === null) {
            $card_source = $response['purchase_units'][0]['payments']['authorizations'][0]['payment_source']['card'] ?? null;
        }

        if ($card_source === null) {
            return null;
        }

        // Normalize vault information when returned under attributes.vault
        if (!isset($card_source['vault']) && isset($card_source['attributes']['vault'])) {
            $card_source['vault'] = $card_source['attributes']['vault'];
        }

        return $card_source;
    }

    protected function captureOrAuthorizePayment(string $payment_source): array
    {
        $response = $this->paypalCommon->processCreditCardPayment(
            $this->ppr, 
            $this->log, 
            MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE,
            'card'
        );
        
        if ($response === false) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALAC_TEXT_CAPTURE_FAILED ?? 'Payment processing failed', FILENAME_CHECKOUT_PAYMENT);
        }

        return $response;
    }

    public function after_order_create($orders_id)
    {
        // Store vaulted card data if present in the response
        $card_source = $this->extractCardSource($this->orderInfo);

        if ($card_source !== null) {
            $this->paypalCommon->storeVaultCardData($orders_id, $card_source, $this->orderCustomerCache);
        }
    }

    public function after_process()
    {
        $this->paypalCommon->processAfterOrder($this->orderInfo);
        $this->paypalCommon->updateOrderHistory($this->orderInfo, 'card');
        $this->paypalCommon->resetOrder();
    }

    protected function recordPayPalOrderDetails(int $orders_id): void
    {
        // Delegate to common class - but for googlepay we have specific handling
        // Implementation similar to paypalac but adapted for Credit Cards
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
        
        $payment_type = 'card';
        $payment_source = $this->orderInfo['payment_source'][$payment_type] ?? [];
        
        $card_source = $payment_source['card'] ?? [];
        $name = $payment_source['name'] ?? [];
        
        $first_name = is_array($name) ? ($name['given_name'] ?? '') : '';
        $last_name = is_array($name) ? ($name['surname'] ?? '') : '';
        $email_address = $payment_source['email_address'] ?? '';
        
        $memo = [
            'source' => 'card',
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
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS'");
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
                ('Module Version', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION', '$current_version', 'Currently-installed module version.', 6, 0, 'zen_cfg_read_only(', NULL, now()),
                ('Enable PayPal Credit Cards?', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS', 'False', 'Do you want to enable PayPal Credit Cards payments?', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),
                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),
                ('Payment Zone', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now()),
                ('Accepted Card Brands', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS', 'amex,discover,jcb,maestro,mastercard,solo,visa', 'Select the card brands you accept for PayPal Advanced Card Fields. These selections control which card logos are displayed on the saved cards add form.', 6, 0, 'zen_cfg_select_multioption([\'amex\', \'discover\', \'jcb\', \'maestro\', \'mastercard\', \'solo\', \'visa\'], ', NULL, now()),
                ('Show Save Card Checkbox', 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX', 'True', 'Display the \"Save Card\" checkbox during checkout? If disabled, customers will not see the option to save their card for future use. Note: For orders containing subscriptions, a notice will still be displayed that the card will be saved.', 6, 0, 'zen_cfg_select_option([''True'', ''False''], ', NULL, now())"
        );
        
        // Define the module's current version so that the tableCheckup method will apply all changes
        define('MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION', '0.0.0');
        $this->tableCheckup();
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_VERSION',
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS',
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_ZONE',
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_ACCEPTED_CARDS',
            'MODULE_PAYMENT_PAYPALAC_CREDITCARD_SHOW_SAVE_CARD_CHECKBOX',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALAC_CREDITCARD_%'");
    }

    public function sendAlertEmail(string $subject_detail, string $message, bool $force_send = false)
    {
        if (isset($this->paypalCommon) && $this->paypalCommon instanceof PayPalCommon) {
            $this->paypalCommon->sendAlertEmail($subject_detail, $message, $force_send);
        }
    }

    public function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }
}
