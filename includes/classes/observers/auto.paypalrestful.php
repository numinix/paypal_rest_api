<?php
/**
 * Part of the paypalr (PayPal Advanced Checkout) payment module.
 * This observer class handles the JS SDK integration logic.
 * It also watches for notifications from the 'order_total' class,
 * introduced in this (https://github.com/zencart/zencart/pull/6090) Zen Cart PR,
 * to determine an order's overall value and what amounts each order-total
 * module has added/subtracted to the order's overall value.
 *
 * Last updated: v1.3.1
 */

use PayPalRestful\Api\Data\CountryCodes;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Zc2Pp\Amount;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

class zcObserverPaypalrestful
{
    use \Zencart\Traits\ObserverManager;

    protected array $lastOrderValues = [];
    protected array $orderTotalChanges = [];
    protected bool $freeShippingCoupon = false;
    protected bool $headerAssetsSent = false;

    public function __construct()
    {
        // -----
        // If the paypalr payment-module isn't installed or isn't configured to be
        // enabled, nothing further to do here.
        //
        if (!defined('MODULE_PAYMENT_PAYPALR_STATUS') || MODULE_PAYMENT_PAYPALR_STATUS !== 'True') {
            return;
        }

        // -----
        // If currently on either the 3-page or OPC checkout-confirmation pages, need to monitor
        // calls to the order-totals' pre_confirmation_check method. That method is run on that
        // page prior to paypalr's pre_confirmation_check method.
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
        // paypalr's before_process method.
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
        $coupon_type = $parameters['coupon']['coupon_type'];
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
        ];

        $potentialConstants = [
            'FILENAME_ONE_PAGE_CHECKOUT',
            'FILENAME_ONE_PAGE_CONFIRMATION',
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
        // that information for use by the paypalr payment-module's processing.
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
    // Public methods (used by the paypalr payment-module) to retrieve the results
    // of the notifications' processing.
    //
    // Note: If getLastOrderValues returns an empty array, the implication is that
    // the required notifications have not been added to the order_total.php class.
    //
    public function getLastOrderValues(): array
    {
        return $this->lastOrderValues;
    }
    public function getOrderTotalChanges(): array
    {
        return $this->orderTotalChanges;
    }
    public function orderHasFreeShippingCoupon(): bool
    {
        return $this->freeShippingCoupon;
    }


    /** Internal methods **/

    protected function outputJsSdkHeaderAssets($current_page): void
    {
        global $current_page_base, $order, $paypalSandboxBuyerCountryCodeOverride, $paypalSandboxLocaleOverride;
        if (empty($current_page)) {
            $current_page = $current_page_base;
        }

        $js_url = 'https://www.paypal.com/sdk/js';
        $js_fields = [];
        $js_scriptparams = [];

        $js_fields['client-id'] = MODULE_PAYMENT_PAYPALR_SERVER === 'live' ? MODULE_PAYMENT_PAYPALR_CLIENTID_L : MODULE_PAYMENT_PAYPALR_CLIENTID_S;
        $buyerCountry = $this->determineBuyerCountryCode();

        if (MODULE_PAYMENT_PAYPALR_SERVER === 'sandbox') {
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

        // possible components for future SDK integration: buttons,marks,messages,funding-eligibility,hosted-fields,card-fields,applepay
        $components = ['messages'];
        if (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS !== 'False') {
            $components[] = 'googlepay';
        }
        if (defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS !== 'False') {
            $components[] = 'applepay';
        }
        if (defined('MODULE_PAYMENT_PAYPALR_VENMO_STATUS') && MODULE_PAYMENT_PAYPALR_VENMO_STATUS !== 'False') {
            $components[] = 'venmo';
        }
        $js_fields['components'] = implode(',', array_unique($components));

        $js_page_type = $this->getMessagesPageType();

        if (!empty($js_page_type) && !in_array($js_page_type, ['home', 'other', 'None'], true)) {
            $js_scriptparams[] = 'data-page-type="' . $js_page_type . '"';
        }

        $js_fields['integration-date'] = '2025-08-01';
        $js_scriptparams[] = 'data-partner-attribution-id="' . PayPalRestfulApi::PARTNER_ATTRIBUTION_ID . '"';
        $js_scriptparams[] = 'data-namespace="PayPalSDK"';
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
?>
<script title="PayPal Pay Later Messaging">
// PayPal PayLater messaging set up
let paypalMessagesPageType = '<?= $pageType ?>';
let paypalMessageableOverride = <?= $override ? json_encode($override) : '{}' ?>;
let paypalMessageableStyles = <?= !empty($messageStyles) ? json_encode($messageStyles) : '{}' ?>;
<?= file_get_contents(DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.jssdk_messages.js'); ?>
</script>
<?php
        return;
    }

    protected function getMessagesPageType(): string
    {
        global $current_page_base, $this_is_home_page, $category_depth, $tpl_page_body;

        $limit = defined('MODULE_PAYMENT_PAYPALR_PAYLATER_MESSAGING') ? MODULE_PAYMENT_PAYPALR_PAYLATER_MESSAGING : 'All';
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

    private function observerManagerUsesEventDto(): bool
    {
        static $supportsEventDto = null;

        if ($supportsEventDto === null) {
            $supportsEventDto = class_exists('\\Zencart\\Events\\EventDto');
        }

        return $supportsEventDto;
    }

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
        if ($this->observerManagerUsesEventDto()) {
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
