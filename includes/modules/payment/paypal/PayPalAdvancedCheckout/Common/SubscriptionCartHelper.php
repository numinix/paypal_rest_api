<?php
/**
 * Cart-level subscription detection for PayPal Advanced Checkout.
 *
 * Walks the current Zen Cart cart (or any cart-shaped array) and classifies
 * each line item as a subscription product or a one-off product. A product
 * is a subscription product when its catalog attributes include the three
 * Zen Cart-managed subscription options (Billing Period / Billing Frequency
 * / Total Billing Cycles) OR a paypal_subscription_plan_id attribute.
 *
 * The helper exists so paypalac payment modules and the recurring observer
 * can take the same decision in two different places (storefront update_status
 * gating and checkout-process subscription routing) without each one
 * re-implementing the cart walk.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

class SubscriptionCartHelper
{
    /**
     * Catalog option-name fragments that indicate a subscription product.
     * Matching is case-insensitive and normalized through normalizeKey().
     * Mirrors auto.paypaladvcheckout_recurring's ATTRIBUTE_KEY_MAP.
     */
    public const PLAN_ID_KEYS = ['paypal_subscription_plan_id'];

    public const BILLING_PERIOD_KEYS = [
        'paypal_subscription_billing_period',
        'billing_period',
        'billingperiod',
    ];

    public const BILLING_FREQUENCY_KEYS = [
        'paypal_subscription_billing_frequency',
        'billing_frequency',
        'billingfrequency',
    ];

    public const TOTAL_CYCLES_KEYS = [
        'paypal_subscription_total_billing_cycles',
        'total_billing_cycles',
        'totalbillingcycles',
    ];

    /**
     * Classify the current Zen Cart cart and return a summary describing the
     * subscription/one-off mix.
     *
     * @param \shoppingCart|null $cart  Optional explicit cart; defaults to $_SESSION['cart'].
     *
     * @return array{
     *     has_subscription: bool,
     *     has_non_subscription: bool,
     *     is_mixed: bool,
     *     is_single_subscription_cart: bool,
     *     subscription_products: array<int,array<string,mixed>>
     * }
     */
    public static function summarize($cart = null): array
    {
        if ($cart === null && isset($_SESSION['cart']) && is_object($_SESSION['cart'])) {
            $cart = $_SESSION['cart'];
        }

        $summary = [
            'has_subscription' => false,
            'has_non_subscription' => false,
            'is_mixed' => false,
            'is_single_subscription_cart' => false,
            'subscription_products' => [],
        ];

        if (!is_object($cart) || !isset($cart->contents) || !is_array($cart->contents)) {
            return $summary;
        }

        $subscriptionLineCount = 0;
        $nonSubscriptionLineCount = 0;

        foreach ($cart->contents as $productIdRaw => $lineItem) {
            $productId = (int)preg_replace('/[^0-9]/', '', (string)$productIdRaw);
            if ($productId <= 0) {
                continue;
            }

            $attributeProfile = self::analyseProduct($productId);

            if ($attributeProfile['is_subscription']) {
                $quantity = (float)($lineItem['qty'] ?? 1);
                if ($quantity < 1) {
                    $quantity = 1.0;
                }
                $summary['subscription_products'][] = array_merge(
                    $attributeProfile,
                    [
                        'products_id' => $productId,
                        'quantity' => $quantity,
                    ]
                );
                $subscriptionLineCount++;
            } else {
                $nonSubscriptionLineCount++;
            }
        }

        $summary['has_subscription'] = $subscriptionLineCount > 0;
        $summary['has_non_subscription'] = $nonSubscriptionLineCount > 0;
        $summary['is_mixed'] = $summary['has_subscription'] && $summary['has_non_subscription'];

        // "Single subscription cart" = exactly one subscription line, qty=1, no
        // one-off line items. PayPal's Subscriptions JS button can only approve
        // one plan per checkout, so we use this flag to decide whether to swap
        // the regular AC button for the subscription button.
        $summary['is_single_subscription_cart'] = (
            !$summary['has_non_subscription']
            && $subscriptionLineCount === 1
            && !empty($summary['subscription_products'])
            && (float)$summary['subscription_products'][0]['quantity'] === 1.0
        );

        return $summary;
    }

    /**
     * Return true when the cart contains at least one subscription product.
     */
    public static function cartHasSubscription($cart = null): bool
    {
        return self::summarize($cart)['has_subscription'];
    }

    /**
     * Return true when the cart contains both a subscription product and a
     * non-subscription product. In this state the paypalac (PayPal account)
     * button must be hidden because PayPal Subscriptions cannot bundle the
     * one-off line items into a recurring agreement.
     */
    public static function cartIsMixedSubscription($cart = null): bool
    {
        return self::summarize($cart)['is_mixed'];
    }

    /**
     * Inspect a single product's catalog attributes and determine whether the
     * product is a subscription product. When it is, also surface the
     * normalized subscription configuration so callers can immediately feed
     * the result into PayPalPlanProvisioner.
     *
     * @return array{
     *     is_subscription: bool,
     *     has_plan_id: bool,
     *     plan_id: string,
     *     billing_period: string,
     *     billing_frequency: int,
     *     total_billing_cycles: int,
     *     trial_period: string,
     *     trial_frequency: int,
     *     trial_total_cycles: int,
     *     setup_fee: float,
     *     products_name: string,
     *     amount: float,
     *     currency_code: string
     * }
     */
    public static function analyseProduct(int $productId): array
    {
        global $db, $currencies;

        $profile = [
            'is_subscription' => false,
            'has_plan_id' => false,
            'plan_id' => '',
            'billing_period' => '',
            'billing_frequency' => 0,
            'total_billing_cycles' => 0,
            'trial_period' => '',
            'trial_frequency' => 0,
            'trial_total_cycles' => 0,
            'setup_fee' => 0.0,
            'products_name' => '',
            'amount' => 0.0,
            'currency_code' => '',
        ];

        if ($productId <= 0) {
            return $profile;
        }

        // Look at all option/value pairs available for this product. We treat
        // the FIRST default-value of each option as the "configured" subscription
        // value; admins set subscription products up with a single mandatory
        // value (e.g. Billing Period = Monthly) rather than a true choice list.
        $sql = "SELECT po.products_options_name,
                       pa.options_values_id,
                       pov.products_options_values_name
                  FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                  JOIN " . TABLE_PRODUCTS_OPTIONS . " po
                       ON po.products_options_id = pa.options_id
                       AND po.language_id = " . (int)$_SESSION['languages_id'] . "
             LEFT JOIN " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
                       ON pov.products_options_values_id = pa.options_values_id
                       AND pov.language_id = " . (int)$_SESSION['languages_id'] . "
                 WHERE pa.products_id = " . (int)$productId . "
              ORDER BY pa.products_attributes_id ASC";
        $result = $db->Execute($sql);

        $attributeMap = [];
        if (is_object($result)) {
            while (!$result->EOF) {
                $name = self::normalizeKey((string)$result->fields['products_options_name']);
                if ($name !== '' && !isset($attributeMap[$name])) {
                    $attributeMap[$name] = trim((string)$result->fields['products_options_values_name']);
                }
                $result->MoveNext();
            }
        }

        if (empty($attributeMap)) {
            return $profile;
        }

        $planId = self::pickAttribute($attributeMap, self::PLAN_ID_KEYS);
        $billingPeriodRaw = self::pickAttribute($attributeMap, self::BILLING_PERIOD_KEYS);
        $billingFrequencyRaw = self::pickAttribute($attributeMap, self::BILLING_FREQUENCY_KEYS);
        $totalCyclesRaw = self::pickAttribute($attributeMap, self::TOTAL_CYCLES_KEYS);

        $hasPlanId = $planId !== '';
        $billingPeriod = self::normalizePeriod($billingPeriodRaw);
        $billingFrequency = (int)$billingFrequencyRaw;
        $totalCycles = (int)$totalCyclesRaw;

        $hasZenCartManagedConfig = ($billingPeriod !== '' && $billingFrequency > 0);

        if (!$hasPlanId && !$hasZenCartManagedConfig) {
            return $profile;
        }

        $profile['is_subscription'] = true;
        $profile['has_plan_id'] = $hasPlanId;
        $profile['plan_id'] = $planId;
        $profile['billing_period'] = $billingPeriod;
        $profile['billing_frequency'] = $billingFrequency;
        $profile['total_billing_cycles'] = $totalCycles;

        // Optional trial / setup fee passthrough.
        $trialPeriodRaw = self::pickAttribute($attributeMap, ['paypal_subscription_trial_period']);
        $trialFrequencyRaw = self::pickAttribute($attributeMap, ['paypal_subscription_trial_frequency']);
        $trialTotalCyclesRaw = self::pickAttribute($attributeMap, ['paypal_subscription_trial_total_cycles']);
        $setupFeeRaw = self::pickAttribute($attributeMap, ['paypal_subscription_setup_fee']);

        $profile['trial_period'] = self::normalizePeriod($trialPeriodRaw);
        $profile['trial_frequency'] = max(0, (int)$trialFrequencyRaw);
        $profile['trial_total_cycles'] = max(0, (int)$trialTotalCyclesRaw);
        $profile['setup_fee'] = max(0.0, (float)$setupFeeRaw);

        // Pull product name + price + currency for plan provisioning.
        $productRow = $db->Execute(
            "SELECT pd.products_name, p.products_price
               FROM " . TABLE_PRODUCTS . " p
               JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                    ON pd.products_id = p.products_id
                    AND pd.language_id = " . (int)$_SESSION['languages_id'] . "
              WHERE p.products_id = " . (int)$productId . "
              LIMIT 1"
        );
        if (is_object($productRow) && !$productRow->EOF) {
            $profile['products_name'] = (string)$productRow->fields['products_name'];
            $profile['amount'] = (float)$productRow->fields['products_price'];
        }

        $profile['currency_code'] = self::resolveCurrencyCode();

        return $profile;
    }

    protected static function normalizeKey(string $label): string
    {
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? $label;
        return trim((string)$label, '_');
    }

    protected static function normalizePeriod(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }
        $map = [
            'DAY' => 'DAY', 'DAYS' => 'DAY', 'DAILY' => 'DAY',
            'WEEK' => 'WEEK', 'WEEKS' => 'WEEK', 'WEEKLY' => 'WEEK',
            'MONTH' => 'MONTH', 'MONTHS' => 'MONTH', 'MONTHLY' => 'MONTH',
            'YEAR' => 'YEAR', 'YEARS' => 'YEAR', 'YEARLY' => 'YEAR',
        ];
        return $map[$value] ?? '';
    }

    /**
     * Find the first non-empty value in $attributes for the supplied normalized key
     * candidates.
     *
     * @param array<string,string> $attributes
     * @param array<int,string>    $candidates
     */
    protected static function pickAttribute(array $attributes, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $key = self::normalizeKey($candidate);
            if (isset($attributes[$key]) && $attributes[$key] !== '') {
                return (string)$attributes[$key];
            }
        }
        return '';
    }

    protected static function resolveCurrencyCode(): string
    {
        if (!empty($_SESSION['currency'])) {
            return strtoupper((string)$_SESSION['currency']);
        }
        if (defined('DEFAULT_CURRENCY')) {
            return strtoupper((string)DEFAULT_CURRENCY);
        }
        return 'USD';
    }
}
