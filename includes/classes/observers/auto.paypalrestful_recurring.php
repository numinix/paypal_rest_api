<?php
/**
 * Recurring-subscription observer for PayPal Advanced Checkout.
 *
 * Detects subscription-ready products during order creation and logs them so
 * follow-up billing can reference the vaulted card profile captured at checkout.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use PayPalRestful\Common\SubscriptionManager;
use PayPalRestful\Common\VaultManager;
use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/ObserverManager.php';
}

class zcObserverPaypalrestfulRecurring
{
    use ObserverManager;

    /** @var array<int,bool> */
    protected array $processedOrders = [];

    /**
     * Normalized attribute keys mapped to the values this observer understands.
     */
    protected const ATTRIBUTE_KEY_MAP = [
        'plan_id' => 'paypal_subscription_plan_id',
        'billing_period' => 'paypal_subscription_billing_period',
        'billing_frequency' => 'paypal_subscription_billing_frequency',
        'total_billing_cycles' => 'paypal_subscription_total_billing_cycles',
        'trial_period' => 'paypal_subscription_trial_period',
        'trial_frequency' => 'paypal_subscription_trial_frequency',
        'trial_total_cycles' => 'paypal_subscription_trial_total_cycles',
        'setup_fee' => 'paypal_subscription_setup_fee',
    ];

    protected const REQUIRED_FIELDS = ['plan_id', 'billing_period', 'billing_frequency'];

    protected const PERIOD_MAP = [
        'DAY' => 'DAY',
        'DAYS' => 'DAY',
        'WEEK' => 'WEEK',
        'WEEKS' => 'WEEK',
        'MONTH' => 'MONTH',
        'MONTHS' => 'MONTH',
        'YEAR' => 'YEAR',
        'YEARS' => 'YEAR',
        'SEMI-MONTH' => 'SEMI_MONTH',
        'SEMI_MONTH' => 'SEMI_MONTH',
        'SEMIMONTH' => 'SEMI_MONTH',
        'SEMI-MONTHLY' => 'SEMI_MONTH',
    ];

    public function __construct()
    {
        // -----
        // If the base paypalr payment-module isn't installed, nothing further to do here.
        // The observer is needed as long as any PayPal payment module is enabled.
        //
        if (!defined('MODULE_PAYMENT_PAYPALR_VERSION')) {
            return;
        }

        // -----
        // Check if at least one PayPal payment module is enabled
        //
        $anyModuleEnabled = (
            (defined('MODULE_PAYMENT_PAYPALR_STATUS') && MODULE_PAYMENT_PAYPALR_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS') && MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_APPLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALR_GOOGLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALR_VENMO_STATUS') && MODULE_PAYMENT_PAYPALR_VENMO_STATUS === 'True')
        );

        if (!$anyModuleEnabled) {
            return;
        }

        $this->attach($this, ['NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS']);
    }

    public function updateNotifyCheckoutProcessAfterOrderCreateAddProducts(&$class, $eventID, $params): void
    {
        $ordersId = (int)($_SESSION['order_number_created'] ?? 0);
        if ($ordersId <= 0 || isset($this->processedOrders[$ordersId])) {
            return;
        }

        $this->processedOrders[$ordersId] = true;

        global $db, $zco_notifier;

        $orderInfo = $db->Execute(
            "SELECT customers_id, currency, currency_value
               FROM " . TABLE_ORDERS . "
              WHERE orders_id = " . $ordersId . "
              LIMIT 1"
        );

        if ($orderInfo->EOF) {
            return;
        }

        $customersId = (int)$orderInfo->fields['customers_id'];
        $currency = (string)($orderInfo->fields['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : ''));
        $currencyValue = (float)($orderInfo->fields['currency_value'] ?? 1.0);

        $products = $db->Execute(
            "SELECT orders_products_id, products_id, products_name, products_quantity, final_price
               FROM " . TABLE_ORDERS_PRODUCTS . "
              WHERE orders_id = " . $ordersId
        );

        if ($products->EOF) {
            return;
        }

        $vaultRecord = $this->findVaultRecord($customersId, $ordersId);
        $loggedAny = false;

        while (!$products->EOF) {
            $ordersProductsId = (int)$products->fields['orders_products_id'];
            $attributeMap = $this->getAttributeMap($ordersProductsId);
            $subscriptionAttributes = $this->extractSubscriptionAttributes($attributeMap);
            if ($subscriptionAttributes === null) {
                $products->MoveNext();
                continue;
            }

            $status = SubscriptionManager::STATUS_PENDING;
            $paypalVaultId = 0;
            $vaultId = '';

            if ($vaultRecord !== null) {
                $paypalVaultId = (int)($vaultRecord['paypal_vault_id'] ?? 0);
                $vaultId = (string)($vaultRecord['vault_id'] ?? '');
                if ($vaultId === '') {
                    $status = SubscriptionManager::STATUS_AWAITING_VAULT;
                }
            } else {
                $status = SubscriptionManager::STATUS_AWAITING_VAULT;
            }

            $subscriptionId = SubscriptionManager::logSubscription([
                'customers_id' => $customersId,
                'orders_id' => $ordersId,
                'orders_products_id' => $ordersProductsId,
                'products_id' => (int)$products->fields['products_id'],
                'products_name' => (string)$products->fields['products_name'],
                'products_quantity' => (float)$products->fields['products_quantity'],
                'plan_id' => $subscriptionAttributes['plan_id'],
                'billing_period' => $subscriptionAttributes['billing_period'],
                'billing_frequency' => $subscriptionAttributes['billing_frequency'],
                'total_billing_cycles' => $subscriptionAttributes['total_billing_cycles'],
                'trial_period' => $subscriptionAttributes['trial_period'],
                'trial_frequency' => $subscriptionAttributes['trial_frequency'],
                'trial_total_cycles' => $subscriptionAttributes['trial_total_cycles'],
                'setup_fee' => $subscriptionAttributes['setup_fee'],
                'amount' => (float)$products->fields['final_price'],
                'currency_code' => $currency,
                'currency_value' => $currencyValue,
                'paypal_vault_id' => $paypalVaultId,
                'vault_id' => $vaultId,
                'status' => $status,
                'attributes' => $attributeMap,
            ]);

            if ($subscriptionId > 0) {
                $loggedAny = true;
            }

            $products->MoveNext();
        }

        if ($loggedAny) {
            $zco_notifier->notify('NOTIFY_RECURRING_ORDER_LOGGED', [
                'orders_id' => $ordersId,
            ]);
        }
    }

    /**
     * @return array<string,string>
     */
    protected function getAttributeMap(int $ordersProductsId): array
    {
        global $db;

        $attributes = [];
        $result = $db->Execute(
            "SELECT products_options, products_options_values
               FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . "
              WHERE orders_products_id = " . $ordersProductsId
        );

        while (!$result->EOF) {
            $key = $this->normalizeAttributeKey((string)$result->fields['products_options']);
            $attributes[$key] = trim((string)$result->fields['products_options_values']);
            $result->MoveNext();
        }

        return $attributes;
    }

    /**
     * @param array<string,string> $attributeMap
     * @return array<string,mixed>|null
     */
    protected function extractSubscriptionAttributes(array $attributeMap): ?array
    {
        $normalized = [];
        foreach (self::ATTRIBUTE_KEY_MAP as $field => $key) {
            if (isset($attributeMap[$key])) {
                $normalized[$field] = $attributeMap[$key];
            }
        }

        foreach (self::REQUIRED_FIELDS as $requiredField) {
            if (empty($normalized[$requiredField])) {
                return null;
            }
        }

        $billingPeriod = $this->normalizePeriod((string)$normalized['billing_period']);
        if ($billingPeriod === null) {
            return null;
        }

        $billingFrequency = (int)$normalized['billing_frequency'];
        if ($billingFrequency <= 0) {
            return null;
        }

        $totalCycles = (int)($normalized['total_billing_cycles'] ?? 0);

        $trialPeriodRaw = (string)($normalized['trial_period'] ?? '');
        $trialPeriod = '';
        if ($trialPeriodRaw !== '') {
            $trialPeriodNormalized = $this->normalizePeriod($trialPeriodRaw, true);
            if ($trialPeriodNormalized === null) {
                $trialPeriod = '';
            } else {
                $trialPeriod = $trialPeriodNormalized;
            }
        }

        $trialFrequency = (int)($normalized['trial_frequency'] ?? 0);
        if ($trialPeriod === '') {
            $trialFrequency = 0;
        }

        $trialTotalCycles = (int)($normalized['trial_total_cycles'] ?? 0);
        if ($trialPeriod === '') {
            $trialTotalCycles = 0;
        }

        $setupFee = $this->parseAmount($normalized['setup_fee'] ?? '');

        return [
            'plan_id' => (string)$normalized['plan_id'],
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'total_billing_cycles' => $totalCycles,
            'trial_period' => $trialPeriod,
            'trial_frequency' => $trialFrequency,
            'trial_total_cycles' => $trialTotalCycles,
            'setup_fee' => $setupFee,
            'attributes' => $attributeMap,
        ];
    }

    protected function normalizePeriod(string $value, bool $allowEmpty = false): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return $allowEmpty ? '' : null;
        }

        $value = str_replace([' ', '\t'], '_', $value);
        if (!isset(self::PERIOD_MAP[$value])) {
            return $allowEmpty ? '' : null;
        }

        return self::PERIOD_MAP[$value];
    }

    protected function parseAmount(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace([',', ' '], ['', ''], $raw);
        $normalized = preg_replace('/[^0-9\.-]/', '', $normalized) ?? '';

        return (float)$normalized;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function findVaultRecord(int $customersId, int $ordersId): ?array
    {
        if ($customersId <= 0) {
            return null;
        }

        $records = VaultManager::getCustomerVaultedCards($customersId, false);
        if (empty($records)) {
            return null;
        }

        foreach ($records as $record) {
            if ((int)($record['orders_id'] ?? 0) === $ordersId) {
                return $record;
            }
        }

        return $records[0];
    }

    protected function normalizeAttributeKey(string $label): string
    {
        $label = strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? $label;
        return trim($label, '_');
    }
}
