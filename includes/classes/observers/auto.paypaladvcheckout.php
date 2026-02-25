<?php
/**
 * Part of the paypalac (PayPal Advanced Checkout) payment module.
 * This observer class handles the JS SDK integration logic.
 * It also watches for notifications from the 'order_total' class,
 * introduced in this (https://github.com/zencart/zencart/pull/6090) Zen Cart PR,
 * to determine an order's overall value and what amounts each order-total
 * module has added/subtracted to the order's overall value.
 *
 * Last updated: v1.3.1
 */

use PayPalAdvancedCheckout\Api\Data\CountryCodes;
use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;
use PayPalAdvancedCheckout\Common\Logger;
use PayPalAdvancedCheckout\Zc2Pp\Amount;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Compatibility/ObserverManager.php';
}

class zcObserverPaypaladvcheckout
{
    use \Zencart\Traits\ObserverManager;

    protected array $lastOrderValues = [];
    protected array $orderTotalChanges = [];
    protected bool $freeShippingCoupon = false;
    protected bool $headerAssetsSent = false;
    protected ?Logger $log = null;

    public function __construct()
    {
        // -----
        // If the base paypalac payment-module isn't installed, nothing further to do here.
        // The observer is needed as long as any PayPal payment module is enabled.
        //
        if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
            return;
        }

        // -----
        // Check if at least one PayPal payment module is enabled
        //
        $anyModuleEnabled = (
            (defined('MODULE_PAYMENT_PAYPALAC_STATUS') && MODULE_PAYMENT_PAYPALAC_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS') && MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') && MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS') && MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS === 'True')
        );

        if (!$anyModuleEnabled) {
            return;
        }

        // -----
        // Initialize the logger for SDK configuration debugging.
        //
        $this->log = new Logger();
        if (strpos(MODULE_PAYMENT_PAYPALAC_DEBUGGING, 'Log') !== false) {
            $this->log->enableDebug();
        }

        // -----
        // If currently on either the 3-page or OPC checkout-confirmation pages, need to monitor
        // calls to the order-totals' pre_confirmation_check method. That method is run on that
        // page prior to paypalac's pre_confirmation_check method.
        //
        // NOTE: The page that's set during the AJAX checkout-payment class is 'index'!
        //
        global $current_page_base;
        $numinixOpcPageKeys = $this->getNuminixOpcPageKeys();
        $pages_to_watch = [
            FILENAME_CHECKOUT_CONFIRMATION,
            FILENAME_DEFAULT,
        ];
        if (defined('FILENAME_CHECKOUT_ONE_CONFIRMATION')) {
            $pages_to_watch[] = FILENAME_CHECKOUT_ONE_CONFIRMATION;
        }
        $pages_to_watch = array_unique(array_merge($pages_to_watch, $numinixOpcPageKeys));

        $oprcPageKeys = array_filter(
            $numinixOpcPageKeys,
            static function ($pageBase) {
                return is_string($pageBase) && strpos($pageBase, 'oprc_checkout_') === 0;
            }
        );
        $oprcProcessKey = in_array('oprc_checkout_process', $oprcPageKeys, true) ? 'oprc_checkout_process' : null;

        if (in_array($current_page_base, $pages_to_watch, true)) {
            $this->attach($this, [
                'NOTIFY_ORDER_TOTAL_PRE_CONFIRMATION_CHECK_STARTS',
                'NOTIFY_ORDER_TOTAL_PRE_CONFIRMATION_CHECK_NEXT',
                'NOTIFY_OT_COUPON_CALCS_FINISHED',
            ]);
        // -----
        // If currently on the checkout_process page, need to monitor calls to the
        // order-totals' process method.  That method's run on that page prior to
        // paypalac's before_process method.
        //
        }
        if ($current_page_base === FILENAME_CHECKOUT_PROCESS || ($oprcProcessKey !== null && $current_page_base === $oprcProcessKey)) {
            $this->attach($this, [
                'NOTIFY_ORDER_TOTAL_PROCESS_STARTS',
                'NOTIFY_ORDER_TOTAL_PROCESS_NEXT',
                'NOTIFY_OT_COUPON_CALCS_FINISHED',
            ]);
        }

        // -----
        // Attach to header to render JS SDK assets.
        $this->attach($this, ['NOTIFY_HTML_HEAD_JS_BEGIN']); // NOTE: this might come too early to detect pageType properly
        $this->attach($this, ['NOTIFY_HTML_HEAD_END']);
        // Attach to footer to instantiate the JS.
        $this->attach($this, ['NOTIFY_FOOTER_END']);
    }

    // -----
    // Notification 'update' handlers for the notifications from order-totals' pre_confirmation_check method.
    //
    public function updateNotifyOrderTotalPreConfirmationCheckStarts(&$class, $eventID, array $starting_order_info)
    {
        $this->setLastOrderValues($starting_order_info['order_info']);
    }
    public function updateNotifyOrderTotalPreConfirmationCheckNext(&$class, $eventID, array $ot_updates)
    {
        $this->setOrderTotalUpdate($ot_updates);
    }

    // -----
    // Notification 'update' handlers for the notifications from order-totals' process method.
    //
    public function updateNotifyOrderTotalProcessStarts(&$class, $eventID, array $starting_order_info)
    {
        $this->setLastOrderValues($starting_order_info['order_info']);
    }
    public function updateNotifyOrderTotalProcessNext(&$class, $eventID, array $ot_updates)
    {
        $this->setOrderTotalUpdate($ot_updates);
    }

    // -----
    // Notification 'update' handler for ot_coupon, letting us know if the associated
    // coupon provides free shipping.
    //
    public function updateNotifyOtCouponCalcsFinished(&$class, $eventID, array $parameters)
    {
        $coupon = $parameters['coupon'];
        // Handle both array and object structures for coupon data
        if (isset($coupon->fields) && isset($coupon->fields['coupon_type'])) {
            // Object with fields property
            $coupon_type = $coupon->fields['coupon_type'];
        } elseif (is_array($coupon) && isset($coupon['coupon_type'])) {
            // Array with coupon_type key
            $coupon_type = $coupon['coupon_type'];
        } else {
            // Fallback to empty string if neither structure is found
            $coupon_type = '';
        }
        $this->freeShippingCoupon = in_array($coupon_type, ['S', 'E', 'O']);
    }

    public function updateNotifyHtmlHeadEnd(&$class, $eventID, $current_page_base): void
    {
        // This is a fallback for older versions, to ensure we only output the header JS once.
        if ($this->headerAssetsSent) {
            return;
        }
        $this->outputJsSdkHeaderAssets($current_page_base);
    }
    public function updateNotifyHtmlHeadJsBegin(&$class, $eventID, $current_page_base): void
    {
        $this->outputJsSdkHeaderAssets($current_page_base);
        $this->headerAssetsSent = true;
    }
    public function updateNotifyFooterEnd(&$class, $eventID, $current_page_base): void
    {
        $this->outputJsFooter($current_page_base);
    }

    // -----
    // Set the last order-values seen, based on the associated 'start' notification.
    //
    protected function setLastOrderValues(array $order_info)
    {
        $this->lastOrderValues = [
            'total' => $order_info['total'],
            'tax' => $order_info['tax'],
            'subtotal' => $order_info['subtotal'],
            'shipping_cost' => $order_info['shipping_cost'],
            'shipping_tax' => $order_info['shipping_tax'],
            'tax_groups' => $order_info['tax_groups'],
        ];
    }

    protected function getNuminixOpcPageKeys(): array
    {
        $pageKeys = [
            'one_page_checkout',
            'one_page_confirmation',
            'oprc_checkout_process',
            'oprc_checkout_payment',
            'oprc_checkout_confirmation',
            'oprc_confirmation'
        ];

        $potentialConstants = [
            'FILENAME_ONE_PAGE_CHECKOUT',
            'FILENAME_ONE_PAGE_CONFIRMATION',
            'FILENAME_OPRC_CONFIRMATION',
            'FILENAME_CHECKOUT'
        ];

        foreach ($potentialConstants as $constantName) {
            if (defined($constantName)) {
                $constantValue = constant($constantName);
                if (is_string($constantValue) && $constantValue !== '') {
                    $pageKeys[] = $constantValue;
                }
            }
        }

        return array_values(array_unique($pageKeys));
    }

    // -----
    // Determine the difference to the current order's values for the current
    // order-total module.
    //
    // The $ot_updates is an associative array containing these keys:
    //
    // - class ........ The name of the order-total module currently being processed.
    // - order_info ... Contains the $order->info array *after* the order-total has been processed.
    // - output ....... The 'output' provided by the order-total currently being processed.
    //
    // Note: Fuzzy comparisons are used on values throughout this method, since we're dealing
    // with floating-point values.
    //
    protected function setOrderTotalUpdate(array $ot_updates)
    {
        $updated_order = $ot_updates['order_info'];

        // -----
        // Loop through each of the 'pertinent' elements of the $order->info array, to
        // see what (if any) changes have been provided by the current order-total module.
        //
        $diff = [];
        foreach ($this->lastOrderValues as $key => $value) {
            // -----
            // All elements _other than_ the tax_groups are scalar values, just
            // check if the current order-total has made changes to the value.
            //
            if ($key !== 'tax_groups') {
                $value_difference = $updated_order[$key] - $value;
                if ($value_difference != 0) {
                    $diff[$key] = $value_difference;
                }
                continue;
            }

            // -----
            // Loop through each of the tax-groups *last seen* in the order, determining
            // whether the current order-total has make changes.
            //
            // Once processed, remove the tax-group from the updates so that any
            // *additions* can be handled.
            //
            foreach ($this->lastOrderValues['tax_groups'] as $tax_group_name => $tax_value) {
                $value_difference = $updated_order['tax_groups'][$tax_group_name] - $tax_value;
                if ($value_difference != 0) {
                    $diff['tax_groups'][$tax_group_name] = $value_difference;
                }
                unset($updated_order['tax_groups'][$tax_group_name]);
            }

            // -----
            // If any tax-groups remain in the updated order-info, then the current
            // order-total has *added* that tax-group element to the order.
            //
            foreach ($updated_order['tax_groups'] as $tax_group_name => $tax_value) {
                if ($tax_value != 0) {
                    $diff['tax_groups'][$tax_group_name] = $tax_value;
                }
            }
        }

        // -----
        // If the current order-total has made changes to the order-info, record
        // that information for use by the paypalac payment-module's processing.
        //
        if (count($diff) !== 0) {
            $this->orderTotalChanges[$ot_updates['class']] = [
                'diff' => $diff,
                'output' => $ot_updates['ot_output'],
            ];
        }

        // -----
        // Register the order-info after the current order-total has run.  These
        // values are used when checking the next order-total's changes; the
        // final result seen will be the order-info that's associated with
        // the order itself.
        //
        $this->setLastOrderValues($ot_updates['order_info']);
    }

    // -----
    // Public methods (used by the paypalac payment-module) to retrieve the results
    // of the notifications' processing.
    //
    // Note: If getLastOrderValues returns an empty array, the implication is that
    // the required notifications have not been added to the order_total.php class.
    // In that case, we fall back to getting the values directly from the global
    // $order object, which provides compatibility with older checkouts like OPRC.
    //
    public function getLastOrderValues(): array
    {
        // -----
        // If we have values from the notifications, return them.
        //
        if (count($this->lastOrderValues) !== 0) {
            return $this->lastOrderValues;
        }

        // -----
        // Fallback: If notifications weren't received (e.g., older order_total.php
        // class without the required notifications, or one-page checkout modules
        // like OPRC that call process() on confirmation pages), get values directly
        // from the global $order object.
        //
        global $order;
        if (isset($order) && is_object($order) && isset($order->info)) {
            return [
                'total' => $order->info['total'] ?? 0,
                'tax' => $order->info['tax'] ?? 0,
                'subtotal' => $order->info['subtotal'] ?? 0,
                'shipping_cost' => $order->info['shipping_cost'] ?? 0,
                'shipping_tax' => $order->info['shipping_tax'] ?? 0,
                'tax_groups' => $order->info['tax_groups'] ?? [],
            ];
        }

        return [];
    }
    public function getOrderTotalChanges(): array
    {
        return $this->orderTotalChanges;
    }
    public function orderHasFreeShippingCoupon(): bool
    {
        return $this->freeShippingCoupon;
    }

    // -----
    // Ensure notify() method is available, even if the ObserverManager trait
    // doesn't provide it (compatibility fix for various Zen Cart versions).
    // This method delegates to the appropriate notification system based on
    // what's available in the current environment.
    //
    public function notify(
        $eventID,
        $param1 = [],
        &$param2 = null,
        &$param3 = null,
        &$param4 = null,
        &$param5 = null,
        &$param6 = null,
        &$param7 = null,
        &$param8 = null,
        &$param9 = null
    ) {
        // -----
        // Check if the newer EventDto notifier is available (ZC 2.0+)
        //
        if (class_exists('\\Zencart\\Events\\EventDto')) {
            $eventDispatcher = \Zencart\Events\EventDto::getInstance();

            if (method_exists($eventDispatcher, 'notify')) {
                $eventDispatcher->notify(
                    $eventID,
                    $param1,
                    $param2,
                    $param3,
                    $param4,
                    $param5,
                    $param6,
                    $param7,
                    $param8,
                    $param9
                );
                return;
            }

            if (method_exists($eventDispatcher, 'dispatch')) {
                $eventDispatcher->dispatch(
                    $eventID,
                    $param1,
                    $param2,
                    $param3,
                    $param4,
                    $param5,
                    $param6,
                    $param7,
                    $param8,
                    $param9
                );
                return;
            }
        }

        // -----
        // Fall back to the legacy $zco_notifier (ZC 1.5.x)
        //
        global $zco_notifier;
        if (is_object($zco_notifier) && method_exists($zco_notifier, 'notify')) {
            $zco_notifier->notify(
                $eventID,
                $param1,
                $param2,
                $param3,
                $param4,
                $param5,
                $param6,
                $param7,
                $param8,
                $param9
            );
        }
    }

    /** Internal methods **/

    /**
     * Get sanitized CSP nonce value if available.
     * 
     * @return string Sanitized CSP nonce or empty string if not set
     */
    protected function getCspNonce(): string
    {
        if (isset($GLOBALS['CSP_NONCE']) && !empty($GLOBALS['CSP_NONCE'])) {
            return htmlspecialchars($GLOBALS['CSP_NONCE'], ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    /**
     * Check if the current user is logged in.
     * 
     * @return bool True if user is logged in, false otherwise
     */
    protected function isUserLoggedIn(): bool
    {
        return isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0;
    }

    /**
     * Check if guest wallet is enabled for Google Pay.
     * 
     * @return bool True if guest wallet is enabled, false otherwise
     */
    protected function isGuestWalletEnabled(): bool
    {
        return defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_ENABLE_GUEST_WALLET') 
            && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_ENABLE_GUEST_WALLET === 'True';
    }

    protected function outputJsSdkHeaderAssets($current_page): void
    {
        global $current_page_base, $order, $paypalSandboxBuyerCountryCodeOverride, $paypalSandboxLocaleOverride;
        if (empty($current_page)) {
            $current_page = $current_page_base;
        }

        $js_url = 'https://www.paypal.com/sdk/js';
        $js_fields = [];
        $js_scriptparams = [];

        $js_fields['client-id'] = MODULE_PAYMENT_PAYPALAC_SERVER === 'live' ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : MODULE_PAYMENT_PAYPALAC_CLIENTID_S;
        $buyerCountry = $this->determineBuyerCountryCode();

        if (MODULE_PAYMENT_PAYPALAC_SERVER === 'sandbox') {
            $js_fields['client-id'] = 'sb'; // 'sb' for sandbox
            $js_fields['debug'] = 'true'; // sandbox only, un-minifies the JS
            $js_fields['buyer-country'] = $paypalSandboxBuyerCountryCodeOverride ?? $buyerCountry; // sandbox only
            $js_fields['locale'] = $paypalSandboxLocaleOverride ?? 'en_US'; // only passing this in sandbox to allow override testing; otherwise just letting it default to customer's browser
        } else {
            $js_fields['buyer-country'] = $buyerCountry;
        }

        if (!empty($order->info['currency'])) {
            $amount = new Amount($order->info['currency']);
            $js_fields['currency'] = $amount->getDefaultCurrencyCode();
        }

        // -----
        // Build the list of SDK components to load. Valid components are:
        // buttons, marks, messages, funding-eligibility, hosted-fields, card-fields, googlepay, applepay
        //
        // Note: 'venmo' is NOT a valid SDK component. Venmo is a funding source that works through
        // the 'buttons' component using paypal.FUNDING.VENMO. When Venmo is enabled, we add the
        // 'buttons' component which allows rendering Venmo buttons via paypal.Buttons({ fundingSource: paypal.FUNDING.VENMO }).
        //
        $components = ['messages'];

        // Add 'buttons' component if any wallet payment method is enabled that uses PayPal Buttons
        // (Venmo uses buttons with FUNDING.VENMO, PayPal wallet uses buttons with default funding,
        // Pay Later uses buttons with FUNDING.PAYLATER)
        $needsButtonsComponent = (
            (defined('MODULE_PAYMENT_PAYPALAC_STATUS') && MODULE_PAYMENT_PAYPALAC_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') && MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS') && MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS === 'True')
        );
        if ($needsButtonsComponent) {
            $components[] = 'buttons';
        }

        // Load Google Pay SDK component based on user status and guest wallet setting
        // Per PayPal support guidance, we can use PayPal SDK for both logged-in and guest users
        // without requiring direct Google Pay SDK or merchant verification
        if (defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True') {
            // Load googlepay component if:
            // 1. User is logged in (uses PayPal SDK, email from session)
            // 2. Guest wallet is enabled (uses PayPal SDK, email collected via emailRequired in PaymentDataRequest)
            if ($this->isUserLoggedIn() || $this->isGuestWalletEnabled()) {
                $components[] = 'googlepay';
            }
        }
        if (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True') {
            $components[] = 'applepay';
        }
        
        // Add 'card-fields' component for saved credit cards page (for adding new cards)
        if (defined('FILENAME_ACCOUNT_SAVED_CREDIT_CARDS') && $current_page === FILENAME_ACCOUNT_SAVED_CREDIT_CARDS) {
            $components[] = 'card-fields';
        }
        
        $js_fields['components'] = implode(',', array_unique($components));

        $js_page_type = $this->getMessagesPageType();

        if (!empty($js_page_type) && !in_array($js_page_type, ['home', 'other', 'None'], true)) {
            $js_scriptparams[] = 'data-page-type="' . $js_page_type . '"';
        }

        $js_fields['integration-date'] = '2025-08-01';
        $js_scriptparams[] = 'data-partner-attribution-id="' . PayPalAdvancedCheckoutApi::PARTNER_ATTRIBUTION_ID . '"';
        $js_scriptparams[] = 'data-namespace="PayPalSDK"';

        // -----
        // Add CSP nonce attribute if available (Zen Cart 2.0+ CSP support)
        //
        $csp_nonce = $this->getCspNonce();
        if (!empty($csp_nonce)) {
            $js_scriptparams[] = 'nonce="' . $csp_nonce . '"';
        }

        // -----
        // Log SDK configuration for debugging purposes. This helps diagnose issues
        // like 400 errors from PayPal SDK when components are not enabled for the account.
        //
        if ($this->log !== null) {
            $sdk_url = $js_url . '?' . str_replace('%2C', ',', http_build_query($js_fields));
            $loggedClientId = (strlen($js_fields['client-id']) > 10)
                ? substr($js_fields['client-id'], 0, 6) . '...' . substr($js_fields['client-id'], -4)
                : $js_fields['client-id'];
            
            // Additional info for Google Pay conditional loading
            $googlePayComponentLoaded = strpos($js_fields['components'], 'googlepay') !== false;
            
            $this->log->write(
                "PayPal SDK Configuration for page '$current_page':\n" .
                "  - Environment: " . MODULE_PAYMENT_PAYPALAC_SERVER . "\n" .
                "  - Client ID: " . $loggedClientId . "\n" .
                "  - Components: " . $js_fields['components'] . "\n" .
                "  - Currency: " . ($js_fields['currency'] ?? 'not set') . "\n" .
                "  - Buyer Country: " . ($js_fields['buyer-country'] ?? 'not set') . "\n" .
                "  - Enabled Modules: " .
                    "PayPal=" . (defined('MODULE_PAYMENT_PAYPALAC_STATUS') ? MODULE_PAYMENT_PAYPALAC_STATUS : 'n/a') . ", " .
                    "GooglePay=" . (defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') ? MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS : 'n/a') . ", " .
                    "ApplePay=" . (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') ? MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS : 'n/a') . ", " .
                    "Venmo=" . (defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') ? MODULE_PAYMENT_PAYPALAC_VENMO_STATUS : 'n/a') . ", " .
                    "PayLater=" . (defined('MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS') ? MODULE_PAYMENT_PAYPALAC_PAYLATER_STATUS : 'n/a') . "\n" .
                "  - User Logged In: " . ($this->isUserLoggedIn() ? 'yes' : 'no') . "\n" .
                "  - Guest Wallet Enabled: " . ($this->isGuestWalletEnabled() ? 'yes' : 'no') . "\n" .
                "  - Google Pay Component Loaded: " . ($googlePayComponentLoaded ? 'yes' : 'no') . "\n" .
                "  - SDK URL: " . $sdk_url,
                true,
                'before'
            );
        }
?>

<script title="PayPalSDK" id="PayPalJSSDK" src="<?= $js_url . '?'. str_replace('%2C', ',', http_build_query($js_fields)) ?>" <?= implode(' ', $js_scriptparams) ?> async></script>

<?php
    }

    protected function determineBuyerCountryCode(): string
    {
        global $order;

        $countryCodes = [];
        if (isset($order) && is_object($order)) {
            $deliveryIsoCode = $order->delivery['country']['iso_code_2'] ?? '';
            $billingIsoCode = $order->billing['country']['iso_code_2'] ?? '';

            if ($deliveryIsoCode !== '') {
                $countryCodes[] = $deliveryIsoCode;
            }
            if ($billingIsoCode !== '') {
                $countryCodes[] = $billingIsoCode;
            }
        }

        foreach ($countryCodes as $isoCode) {
            $convertedCode = CountryCodes::convertCountryCode($isoCode);
            if ($convertedCode !== '') {
                return $convertedCode;
            }
        }

        return 'US';
    }

    protected function outputJsFooter($current_page_base): void
    {
        $containingElement = null;
        $priceSelector = null;
        $outputElement = null;
        $messageStyles = [
            "layout" => "text",
            "logo" => [
                "type" => "inline",
                "position" => "top"
            ],
            "text" => [
                "align" => "center"
            ]
        ];
        $pageType = $this->getMessagesPageType();
        $this->notify('NOTIFY_PAYPAL_PAYLATER_SELECTORS', ['current_page_base' => $current_page_base, 'pageType' => $pageType], $containingElement, $priceSelector, $outputElement, $messageStyles);

        $override = null;
        if (!empty($containingElement) && !empty($priceSelector) && !empty($outputElement)) {
            $override = [
                'pageType' => $pageType,
                'container' => $containingElement,
                'price' => $priceSelector,
                'outputElement' => $outputElement,
                'styleAlign' => $messageStyles['text']['align'] ?? 'center',
            ];
        }

        // -----
        // Add CSP nonce attribute if available (Zen Cart 2.0+ CSP support)
        //
        $csp_nonce = $this->getCspNonce();
        $csp_nonce_attr = !empty($csp_nonce) ? ' nonce="' . $csp_nonce . '"' : '';
?>
<script title="PayPal Pay Later Messaging"<?= $csp_nonce_attr ?>>
// PayPal PayLater messaging set up
let paypalMessagesPageType = '<?= $pageType ?>';
let paypalMessageableOverride = <?= $override ? json_encode($override) : '{}' ?>;
let paypalMessageableStyles = <?= !empty($messageStyles) ? json_encode($messageStyles) : '{}' ?>;
<?= file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.jssdk_messages.js'); ?>
</script>
<?php
        return;
    }

    protected function getMessagesPageType(): string
    {
        global $current_page_base, $this_is_home_page, $category_depth, $tpl_page_body;

        $limit = defined('MODULE_PAYMENT_PAYPALAC_PAYLATER_MESSAGING') ? MODULE_PAYMENT_PAYPALAC_PAYLATER_MESSAGING : 'All';
        $limit = explode(', ', $limit);

        $limitAllCheckout = !empty(array_intersect($limit, ['All', 'Checkout']));
        if ($limitAllCheckout && strpos((string)$current_page_base, 'checkout') === 0) {
            return 'checkout';
        }

        if (!empty(array_intersect($limit, ['All', 'Shopping Cart'])) && $current_page_base === 'shopping_cart') {
            return 'cart';
        }

        //if (!empty(array_intersect($limit, ['All', 'Shopping Cart'])) && $current_page_base === 'mini-cart') {
        //    return 'mini-cart'; // @TODO this is more for a header box
        //}

        if (!empty(array_intersect($limit, ['All', 'Product Pages'])) && in_array($current_page_base, zen_get_buyable_product_type_handlers(), true)) {
            return 'product-details';
        }

        if (!empty(array_intersect($limit, ['All', 'Product Listings and Search Results']))
            && ($category_depth === 'products' || ($tpl_page_body ?? null) === 'tpl_index_product_list.php')
        ) {
            return 'product-listing';
        }

        if (!empty(array_intersect($limit, ['All', 'Product Listings and Search Results']))
            && substr((string)$current_page_base, -strlen('search_result')) === 'search_result'
        ) {
            return 'search-results';
        }

        if (!empty($limit) && $this_is_home_page) {
            return 'home';
        }

        if (!empty($limit)) {
            return 'other';
        }

        return 'None';
    }
}





/*****************************/
// Backward Compatibility for prior to ZC v2.2.0
if (!function_exists('zen_get_buyable_product_type_handlers')) {
    /**
     * Get a list of product page names that identify buyable products.
     * This allows us to mark a page as containing a product which can
     * be allowed to add-to-cart or buy-now with various modules.
     */
    function zen_get_buyable_product_type_handlers(): array
    {
        global $db;
        $sql = "SELECT type_handler from " . TABLE_PRODUCT_TYPES . " WHERE allow_add_to_cart = 'Y'";
        $results = $db->Execute($sql);
        $retVal = [];
        foreach ($results as $result) {
            $retVal[] = $result['type_handler'] . '_info';
        }
        return $retVal;
    }
}
/*****************************/
