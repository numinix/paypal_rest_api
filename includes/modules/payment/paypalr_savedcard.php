<?php
/**
 * paypalr_savedcard.php payment module class for handling Saved Cards via PayPal Advanced Checkout.
 *
 * This module dynamically creates separate payment options for each saved card,
 * displaying them as top-level payment module options rather than as options
 * within a select box inside the credit card module.
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
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Api\Data\CountryCodes;
use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;
use PayPalRestful\Common\VaultManager;
use PayPalRestful\Compatibility\Language as LanguageCompatibility;
use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

LanguageCompatibility::load('paypalr_savedcard');

/**
 * The PayPal Saved Card payment module using PayPal's REST APIs (v2)
 *
 * This module enables customers to pay using their previously saved credit cards.
 * Each saved card appears as a separate top-level payment option.
 */
class paypalr_savedcard extends base
{
    protected function getModuleStatusSetting(): string
    {
        return defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS') ? MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS : 'False';
    }

    protected function getModuleSortOrder(): ?int
    {
        return defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_SORT_ORDER') ? (int)MODULE_PAYMENT_PAYPALR_SAVEDCARD_SORT_ORDER : null;
    }

    protected function getModuleZoneSetting(): int
    {
        return defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_ZONE') ? (int)MODULE_PAYMENT_PAYPALR_SAVEDCARD_ZONE : 0;
    }

    protected const CURRENT_VERSION = '1.3.4';
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

    // Saved cards do not collect card data on-site
    public bool $cardsAccepted = false;
    public bool $collectsCardDataOnsite = false;

    public ?PayPalRestfulApi $ppr = null;
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
     * The saved card info for the current payment (set during selection or payment processing).
     */
    protected ?array $selectedCard = null;

    /**
     * Cache of the customer's vaulted cards.
     */
    protected ?array $vaultedCards = null;

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

        $this->code = 'paypalr_savedcard';

        $curl_installed = (function_exists('curl_init'));

        // For storefront, the title will be dynamically set to show card details in selection()
        // For admin, show a descriptive title
        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_ADMIN ?? 'Saved Card';
        } else {
            $this->title = (MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_ADMIN ?? 'Saved Cards via PayPal Advanced Checkout') . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL ?? 'cURL not installed'));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_DESCRIPTION ?? 'Saved Cards via PayPal Advanced Checkout (v%s)', self::CURRENT_VERSION);

            // Add upgrade button if current version is less than latest version
            // Only show upgrade link if the module is actually installed (version > 0.0.0)
            $installed_version = defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION') ? MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION : '0.0.0';
            if ($installed_version !== '0.0.0' && version_compare($installed_version, self::CURRENT_VERSION, '<')) {
                $this->description .= sprintf(
                    MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_UPGRADE_AVAILABLE ??
                    '<br><br><p><strong>Update Available:</strong> Version %2$s is available. You are currently running version %1$s.</p><p><a class="paypalr-upgrade-button" href="%3$s">Upgrade to %2$s</a></p>',
                    $installed_version,
                    self::CURRENT_VERSION,
                    zen_href_link('paypalr_upgrade.php', 'module=paypalr_savedcard&action=upgrade', 'SSL')
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

        // Saved cards support both auth-only and final sale modes
        $ppr_type = $_SESSION['PayPalRestful']['ppr_type'] ?? 'card';
        if (MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE === 'Final Sale' || ($ppr_type !== 'card' && MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE === 'Auth Only (Card-Only)')) {
            $order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
        } else {
            $order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
        }
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
        }
        if ($this->enabled === false || IS_ADMIN_FLAG === true || $loaderPrefix === 'webhook') {
            return;
        }

        // Check for required main PayPal module and vault enabled
        if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= $this->alertMsg(MODULE_PAYMENT_PAYPALR_CREDITCARD_ERROR_PAYPAL_REQUIRED ?? ' (Requires main PayPal module)');
            }
            return;
        }

        // Check if vault is enabled
        $vaultEnabled = (defined('MODULE_PAYMENT_PAYPALR_ENABLE_VAULT') && MODULE_PAYMENT_PAYPALR_ENABLE_VAULT === 'True');
        if (!$vaultEnabled) {
            $this->enabled = false;
            return;
        }

        // Check if the customer has any vaulted cards
        if (IS_ADMIN_FLAG === false) {
            $this->vaultedCards = $this->getActiveVaultedCards();
            if (empty($this->vaultedCards)) {
                $this->enabled = false;
                return;
            }
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
        if (defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION') && MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION === $current_version) {
            return;
        }

        // Check for version-specific configuration updates
        if (defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION')) {
            switch (true) {
                // Add future version-specific upgrades here
                // case version_compare(MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION, '1.3.5', '<'):
                //     // Add v1.3.5-specific changes here
                //     break;
                
                default:
                    break;
            }
        }

        // Record the current version of the payment module into its database configuration setting
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '$current_version',
                    last_modified = now()
              WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION'
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
        if (isset($this->ppr) && $this->ppr instanceof PayPalRestfulApi) {
            return $this->ppr;
        }

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
            $this->ppr = new PayPalRestfulApi(
                MODULE_PAYMENT_PAYPALR_SERVER,
                $client_id,
                $secret
            );
            return $this->ppr;
        } catch (\Exception $e) {
            $this->log->write('Saved Card: Error creating PayPalRestfulApi: ' . $e->getMessage());
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

    /**
     * Get the customer's active vaulted cards.
     *
     * @return array
     */
    protected function getActiveVaultedCards(): array
    {
        if ($this->vaultedCards !== null) {
            return $this->vaultedCards;
        }

        $customers_id = $_SESSION['customer_id'] ?? 0;
        if ($customers_id <= 0) {
            return [];
        }

        $this->vaultedCards = $this->paypalCommon->getVaultedCardsForCustomer($customers_id, true);
        return $this->vaultedCards;
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
        // No JavaScript validation needed for saved cards
        return '';
    }

    /**
     * Generate selection array for saved cards displayed in a select box.
     *
     * This method creates a single selection with a dropdown containing all saved cards,
     * which takes up less vertical space than individual radio buttons.
     *
     * @return array|array[]
     */
    public function selection()
    {
        unset($_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed']);

        $vaultedCards = $this->getActiveVaultedCards();
        if (empty($vaultedCards)) {
            return [];
        }

        // Load the checkout script to handle radio button selection
        $checkoutScript = '<script defer src="' . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.checkout.js"></script>';

        // Build select box options for saved cards
        $selectedVaultId = $_POST['paypalr_savedcard_vault_id'] ?? ($_SESSION['PayPalRestful']['saved_card'] ?? '');
        
        // If no selection made, default to first card
        if (empty($selectedVaultId) && !empty($vaultedCards)) {
            $selectedVaultId = $vaultedCards[0]['vault_id'];
        }

        $selectOptions = [];
        foreach ($vaultedCards as $card) {
            $cardTitle = $this->buildCardTitle($card);
            $selectOptions[] = [
                'id' => $card['vault_id'],
                'text' => $cardTitle,
            ];
        }

        // Build the select box with onchange/onfocus handlers to auto-select parent radio
        $selectAttributes = 'id="paypalr-savedcard-select" class="ppr-savedcard-select" ' .
            'onchange="if(typeof methodSelect===\'function\')methodSelect(\'pmt-' . $this->code . '\')" ' .
            'onfocus="if(typeof methodSelect===\'function\')methodSelect(\'pmt-' . $this->code . '\')"';

        $selectBox = zen_draw_pull_down_menu(
            'paypalr_savedcard_vault_id',
            $selectOptions,
            $selectedVaultId,
            $selectAttributes
        );

        $selectLabel = defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL') 
            ? MODULE_PAYMENT_PAYPALR_SAVEDCARD_SELECT_LABEL 
            : 'Select Card:';

        $fields = [
            [
                'title' => $selectLabel,
                'field' => $selectBox,
                'tag' => 'paypalr-savedcard-select',
            ],
        ];

        // Build module display title
        $moduleTitle = defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_SHORT') 
            ? MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_SHORT 
            : 'Pay with Saved Card';

        return [
            'id' => $this->code,
            'module' => $moduleTitle . $checkoutScript,
            'fields' => $fields,
        ];
    }

    /**
     * Get saved card data for external use (e.g., by observers).
     *
     * @return array[] Array of card data with vault_id, brand, last_digits, expiry
     */
    public function getSelections(): array
    {
        $vaultedCards = $this->getActiveVaultedCards();
        if (empty($vaultedCards)) {
            return [];
        }

        $selections = [];
        foreach ($vaultedCards as $index => $card) {
            $selections[] = [
                'id' => $this->code,
                'vault_id' => $card['vault_id'],
                'brand' => $this->getCardDisplayBrand($card),
                'last_digits' => $card['last_digits'] ?? '****',
                'expiry' => $card['expiry'] ?? '',
                'title' => $this->buildCardTitle($card),
                'sort_order' => $this->sort_order,
            ];
        }

        return $selections;
    }

    /**
     * Format expiry date for display.
     *
     * @param string $expiry YYYY-MM format
     * @return string MM/YYYY format
     */
    protected function formatExpiry(string $expiry): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $matches)) {
            return $matches[2] . '/' . $matches[1];
        }
        return $expiry;
    }

    /**
     * Get the display brand for a card.
     *
     * @param array $card Card data array
     * @return string The brand to display
     */
    protected function getCardDisplayBrand(array $card): string
    {
        return $card['brand'] ?: ($card['card_type'] ?: (MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD ?? 'Card'));
    }

    /**
     * Build the title label for a saved card.
     *
     * @param array $card Card data array
     * @return string The formatted card title
     */
    protected function buildCardTitle(array $card): string
    {
        $brand = $this->getCardDisplayBrand($card);
        $lastDigits = $card['last_digits'] ?? '****';

        $cardTitle = sprintf(
            MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE ?? '%s ending in %s',
            zen_output_string_protected($brand),
            zen_output_string_protected($lastDigits)
        );

        if (!empty($card['expiry'])) {
            $cardTitle .= sprintf(
                MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_EXPIRY ?? ' (Exp: %s)',
                zen_output_string_protected($this->formatExpiry($card['expiry']))
            );
        }

        return $cardTitle;
    }

    /**
     * Get the image HTML for a card brand.
     *
     * @param string $brand Card brand (VISA, MASTERCARD, AMEX, etc.)
     * @return string HTML img tag or empty string
     */
    protected function getCardBrandImage(string $brand): string
    {
        $brandLower = strtolower($brand);
        $brandMap = [
            'visa' => 'cc_visa.png',
            'mastercard' => 'cc_mastercard.png',
            'amex' => 'cc_amex.png',
            'american express' => 'cc_amex.png',
            'discover' => 'cc_discover.png',
            'jcb' => 'cc_jcb.png',
            'maestro' => 'cc_maestro.png',
        ];

        if (isset($brandMap[$brandLower])) {
            $imagePath = DIR_WS_TEMPLATE_IMAGES . $brandMap[$brandLower];
            if (file_exists(DIR_FS_CATALOG . $imagePath)) {
                return '<img src="' . $imagePath . '" alt="' . zen_output_string_protected($brand) . '" title="' . zen_output_string_protected($brand) . '" style="vertical-align: middle; margin-right: 5px;">';
            }
        }

        return '';
    }

    public function pre_confirmation_check()
    {
        // Set payment type - this module always uses saved card payments
        $_SESSION['PayPalRestful']['ppr_type'] = 'card';

        // Get the selected vault ID from POST or session
        // The radio button directly submits the vault_id value
        $vaultId = $_POST['paypalr_savedcard_vault_id'] ?? ($_SESSION['PayPalRestful']['saved_card'] ?? '');

        if (empty($vaultId)) {
            $this->setMessageAndRedirect(
                MODULE_PAYMENT_PAYPALR_TEXT_SAVED_CARD_NOT_FOUND ?? 'No saved card selected. Please select a card or enter a new one.',
                FILENAME_CHECKOUT_PAYMENT
            );
        }

        $_SESSION['PayPalRestful']['saved_card'] = $vaultId;

        // Validate the saved card
        if (!$this->validateSavedCard($vaultId)) {
            $this->setMessageAndRedirect(
                MODULE_PAYMENT_PAYPALR_TEXT_SAVED_CARD_NOT_FOUND ?? 'The selected card is no longer available. Please select a different card or enter a new one.',
                FILENAME_CHECKOUT_PAYMENT
            );
        }

        // Update the title to show the selected card details in the order's payment method.
        // This makes the payment method descriptive (e.g., "VISA ending in 4242")
        // instead of a generic "Saved Card" label.
        if ($this->selectedCard !== null) {
            $this->title = $this->buildCardTitle($this->selectedCard);
        }

        // Create PayPal order for saved card payment
        $paypal_order_created = $this->createPayPalOrder('card');
        if ($paypal_order_created === false) {
            $error_info = $this->ppr->getErrorInfo();
            $error_code = $error_info['details'][0]['issue'] ?? 'OTHER';
            $this->sendAlertEmail(
                MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN,
                MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATE . Logger::logJSON($error_info)
            );
            $this->setMessageAndRedirect(
                sprintf(MODULE_PAYMENT_PAYPALR_TEXT_CREATE_ORDER_ISSUE, MODULE_PAYMENT_PAYPALR_SAVEDCARD_TEXT_TITLE_ADMIN ?? 'Saved Card', $error_code),
                FILENAME_CHECKOUT_PAYMENT
            );
        }
    }

    /**
     * Validate and set up the saved card for payment.
     *
     * @param string $vaultId The vault ID of the saved card
     * @return bool
     */
    protected function validateSavedCard(string $vaultId): bool
    {
        global $order;

        $vaultCards = $this->getActiveVaultedCards();
        foreach ($vaultCards as $card) {
            if ($card['vault_id'] === $vaultId) {
                $expiryMonth = '';
                $expiryYear = '';
                if (!empty($card['expiry']) && preg_match('/^(\d{4})-(\d{2})/', $card['expiry'], $matches) === 1) {
                    $expiryYear = $matches[1];
                    $expiryMonth = $matches[2];
                }

                $billingName = trim($order->billing['firstname'] . ' ' . $order->billing['lastname']);
                $this->ccInfo = [
                    'type' => $card['card_type'] ?? MODULE_PAYMENT_PAYPALR_SAVEDCARD_UNKNOWN_CARD ?? 'Card',
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

                $this->selectedCard = $card;
                return true;
            }
        }

        return false;
    }

    protected function getListenerEndpoint(): string
    {
        if (defined('REDIRECT_LISTENER')) {
            return REDIRECT_LISTENER;
        }

        return HTTP_SERVER . DIR_WS_CATALOG . 'ppr_listener.php';
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
            $this->log->write('Saved Card: Missing order_total modifications; getLastOrderValues returned empty array.');
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_ALERT_MISSING_OBSERVER ?? 'Observer missing', FILENAME_CHECKOUT_PAYMENT);
        }

        $order_info['free_shipping_coupon'] = $zcObserverPaypalrestful->orderHasFreeShippingCoupon();

        return $order_info;
    }

    public function setMessageAndRedirect(string $error_message, string $redirect_page, bool $log_only = false)
    {
        global $messageStack;

        $this->log->write('Saved Card redirect: ' . $error_message);

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
        // Forward the selected vault ID
        $vaultId = $_SESSION['PayPalRestful']['saved_card'] ?? '';
        return zen_draw_hidden_field('paypalr_savedcard_vault_id', $vaultId);
    }

    public function process_button_ajax()
    {
        return [
            'ccFields' => [
                'paypalr_savedcard_vault_id' => 'paypalr_savedcard_vault_id',
            ],
        ];
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
            $this->log->write('Saved Card::before_process, cannot capture/authorize; wrong status' . "\n" . Logger::logJSON($_SESSION['PayPalRestful']['Order'] ?? []));
            unset($_SESSION['PayPalRestful']['Order'], $_SESSION['payment']);
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_TEXT_STATUS_MISMATCH . "\n" . MODULE_PAYMENT_PAYPALR_TEXT_TRY_AGAIN, FILENAME_CHECKOUT_PAYMENT);
        }

        $response = $this->captureOrAuthorizePayment('card');

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

        // -----
        // If the order's PayPal status doesn't indicate successful completion, ensure that
        // the overall order's status is set to this payment-module's PENDING status and set
        // a processing flag so that the after_process method will alert the store admin if
        // configured.
        //
        // Setting the order's overall status here, since zc158a and earlier don't acknowledge
        // a payment-module's change in status during the payment processing!
        //
        $this->orderInfo['admin_alert_needed'] = false;
        if ($payment_status !== PayPalRestfulApi::STATUS_CAPTURED && $payment_status !== PayPalRestfulApi::STATUS_CREATED) {
            $this->order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
            $order->info['order_status'] = $this->order_status;
            $this->orderInfo['admin_alert_needed'] = true;

            $this->log->write("==> paypalr_savedcard::before_process: Payment status {$payment['status']} received from PayPal; order's status forced to pending.");
        }

        $this->notify('NOTIFY_PAYPALR_BEFORE_PROCESS_FINISHED', $this->orderInfo);
    }

    protected function captureOrAuthorizePayment(string $payment_source): array
    {
        $response = $this->paypalCommon->processCreditCardPayment(
            $this->ppr,
            $this->log,
            MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE,
            'card'
        );

        if ($response === false) {
            $this->setMessageAndRedirect(MODULE_PAYMENT_PAYPALR_TEXT_CAPTURE_FAILED ?? 'Payment processing failed', FILENAME_CHECKOUT_PAYMENT);
        }

        return $response;
    }

    public function after_order_create($orders_id)
    {
        // Saved cards don't need to store new vault data
    }

    public function after_process()
    {
        $this->paypalCommon->processAfterOrder($this->orderInfo);
        $this->paypalCommon->updateOrderHistory($this->orderInfo, 'card');
        $this->paypalCommon->resetOrder();
    }

    public function admin_notification($zf_order_id)
    {
        $zf_order_id = (int)$zf_order_id;
        $ppr = $this->getPayPalRestfulApi();
        if ($ppr === null) {
            return '';
        }

        $admin_main = new AdminMain($this->code, self::CURRENT_VERSION, $zf_order_id, $ppr);

        if ($admin_main->externalTxnAdded() === true) {
            zen_update_orders_history($zf_order_id, MODULE_PAYMENT_PAYPALR_EXTERNAL_ADDITION);
            $this->sendAlertEmail(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN, sprintf(MODULE_PAYMENT_PAYPALR_ALERT_EXTERNAL_TXNS, $zf_order_id));
        }

        return $admin_main->get();
    }

    public function help()
    {
        return [
            'link' => 'https://github.com/lat9/paypalr/wiki'
        ];
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
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS'");
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
                ('Module Version', 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION', '$current_version', 'Currently-installed module version.', 6, 0, 'zen_cfg_read_only(', NULL, now()),
                ('Enable PayPal Saved Cards?', 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS', 'False', 'Do you want to enable PayPal Saved Card payments? Each saved card will appear as a separate payment option.<br><br><b>Note:</b> This module requires that PayPal Vault is enabled in the main PayPal module and that the Credit Card module is also installed.', 6, 0, 'zen_cfg_select_option([''True'', ''False'', ''Retired''], ', NULL, now()),
                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first. Each saved card will be displayed incrementally from this value.', 6, 0, NULL, NULL, now()),
                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now())"
        );

        // Define the module's current version so that the tableCheckup method will apply all changes
        define('MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION', '0.0.0');
        $this->tableCheckup();
    }

    public function keys(): array
    {
        return [
            'MODULE_PAYMENT_PAYPALR_SAVEDCARD_VERSION',
            'MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS',
            'MODULE_PAYMENT_PAYPALR_SAVEDCARD_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_SAVEDCARD_ZONE',
        ];
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALR_SAVEDCARD_%'");
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
