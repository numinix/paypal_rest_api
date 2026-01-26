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

use PayPalRestful\Common\Logger;
use PayPalRestful\Common\SubscriptionManager;
use PayPalRestful\Common\VaultManager;
use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/ObserverManager.php';
}

// Load paypalSavedCardRecurring class for Zen Cart-managed subscriptions
if (!class_exists('paypalSavedCardRecurring')) {
    $savedCardRecurringPath = DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
    if (file_exists($savedCardRecurringPath)) {
        require_once $savedCardRecurringPath;
    }
}

class zcObserverPaypalrestfulRecurring
{
    use ObserverManager;

    /** @var array<int,bool> */
    protected array $processedOrders = [];

    /** @var Logger */
    protected Logger $log;

    /**
     * Normalized attribute keys mapped to the values this observer understands.
     */
    protected const ATTRIBUTE_KEY_MAP = [
        'plan_id' => ['paypal_subscription_plan_id'],
        'billing_period' => ['paypal_subscription_billing_period', 'billing_period', 'billingperiod'],
        'billing_frequency' => ['paypal_subscription_billing_frequency', 'billing_frequency', 'billingfrequency'],
        'total_billing_cycles' => ['paypal_subscription_total_billing_cycles', 'total_billing_cycles', 'totalbillingcycles'],
        'trial_period' => ['paypal_subscription_trial_period'],
        'trial_frequency' => ['paypal_subscription_trial_frequency'],
        'trial_total_cycles' => ['paypal_subscription_trial_total_cycles'],
        'setup_fee' => ['paypal_subscription_setup_fee'],
    ];

    protected const PERIOD_MAP = [
        'DAY' => 'DAY',
        'DAYS' => 'DAY',
        'DAILY' => 'DAY',
        'WEEK' => 'WEEK',
        'WEEKS' => 'WEEK',
        'WEEKLY' => 'WEEK',
        'MONTH' => 'MONTH',
        'MONTHS' => 'MONTH',
        'MONTHLY' => 'MONTH',
        'YEAR' => 'YEAR',
        'YEARS' => 'YEAR',
        'YEARLY' => 'YEAR',
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
            (defined('MODULE_PAYMENT_PAYPALR_VENMO_STATUS') && MODULE_PAYMENT_PAYPALR_VENMO_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS') && MODULE_PAYMENT_PAYPALR_SAVEDCARD_STATUS === 'True')
        );

        if (!$anyModuleEnabled) {
            return;
        }

        // Initialize logger only if we're actually going to attach to notifications
        $this->log = new Logger('recurring-observer');
        $this->log->write('PayPalRestful Recurring Observer: Initialized and attached to notifications.');
        
        $this->attach($this, [
            'NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS',
            'NOTIFY_PAYPALR_VAULT_CARD_SAVED',
        ]);
    }

    public function updateNotifyCheckoutProcessAfterOrderCreateAddProducts(&$class, $eventID, $params): void
    {
        $ordersId = (int)($_SESSION['order_number_created'] ?? 0);
        
        $this->log->write("==> Subscription Observer: NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS fired.");
        $this->log->write("    Order ID: " . ($ordersId > 0 ? $ordersId : 'NOT SET'));
        
        if ($ordersId <= 0 || isset($this->processedOrders[$ordersId])) {
            if ($ordersId <= 0) {
                $this->log->write("    Skipping: Order ID is not set or invalid.");
            } else {
                $this->log->write("    Skipping: Order #$ordersId already processed.");
            }
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
            $this->log->write("    ERROR: Order #$ordersId not found in database.");
            return;
        }

        $customersId = (int)$orderInfo->fields['customers_id'];
        $currency = (string)($orderInfo->fields['currency'] ?? (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : ''));
        $currencyValue = (float)($orderInfo->fields['currency_value'] ?? 1.0);
        
        $this->log->write("    Customer ID: $customersId, Currency: $currency, Currency Value: $currencyValue");

        $products = $db->Execute(
            "SELECT orders_products_id, products_id, products_name, products_quantity, final_price
               FROM " . TABLE_ORDERS_PRODUCTS . "
              WHERE orders_id = " . $ordersId
        );

        if ($products->EOF) {
            $this->log->write("    No products found in order #$ordersId.");
            return;
        }

        $vaultRecord = $this->findVaultRecord($customersId, $ordersId);
        $loggedAny = false;
        
        if ($vaultRecord !== null) {
            $this->log->write("    Vault record found: " . Logger::logJSON($vaultRecord));
        } else {
            $this->log->write("    No vault record found for customer #$customersId / order #$ordersId.");
        }

        while (!$products->EOF) {
            $ordersProductsId = (int)$products->fields['orders_products_id'];
            $this->log->write("    Checking product #$ordersProductsId: " . $products->fields['products_name']);
            
            $attributeMap = $this->getAttributeMap($ordersProductsId);
            $this->log->write("    Product attributes: " . Logger::logJSON($attributeMap));
            
            $subscriptionAttributes = $this->extractSubscriptionAttributes($attributeMap);
            if ($subscriptionAttributes === null) {
                $this->log->write("    Product #$ordersProductsId: No valid subscription attributes found, skipping.");
                $products->MoveNext();
                continue;
            }
            
            $this->log->write("    Product #$ordersProductsId: Valid subscription attributes extracted: " . Logger::logJSON($subscriptionAttributes));

            // Check if this is a Zen Cart-managed subscription (no plan_id)
            // If so, use saved_credit_cards_recurring table instead of vaulted subscriptions
            $hasPlanId = !empty($subscriptionAttributes['plan_id']);
            
            if (!$hasPlanId) {
                // Zen Cart-managed subscription -> Save to saved_credit_cards_recurring table
                $this->log->write("    Product #$ordersProductsId: Zen Cart-managed subscription (no plan_id), routing to saved_credit_cards_recurring.");
                
                $savedCreditCardId = $this->getSavedCreditCardId($vaultRecord);
                if ($savedCreditCardId === 0) {
                    $this->log->write("    WARNING: No saved_credit_card_id found for vault, subscription cannot be created yet.");
                    $products->MoveNext();
                    continue;
                }
                
                // Calculate next billing date
                $nextBillingDate = $this->calculateNextBillingDate($subscriptionAttributes);
                
                // Create subscription using saved card recurring class
                if (!class_exists('paypalSavedCardRecurring')) {
                    $this->log->write("    ERROR: paypalSavedCardRecurring class not available.");
                    $products->MoveNext();
                    continue;
                }
                
                $savedCardRecurring = new paypalSavedCardRecurring();
                $subscriptionId = $savedCardRecurring->schedule_payment(
                    (float)$products->fields['final_price'],
                    $nextBillingDate,
                    $savedCreditCardId,
                    $ordersProductsId,
                    'Subscription created from order #' . $ordersId,
                    [
                        'products_id' => (int)$products->fields['products_id'],
                        'products_name' => (string)$products->fields['products_name'],
                        'currency_code' => $currency,
                        'billing_period' => $subscriptionAttributes['billing_period'],
                        'billing_frequency' => $subscriptionAttributes['billing_frequency'],
                        'total_billing_cycles' => $subscriptionAttributes['total_billing_cycles'],
                        'subscription_attributes' => $attributeMap,
                    ]
                );
                
                if ($subscriptionId > 0) {
                    $loggedAny = true;
                    $this->log->write("    SUCCESS: Saved card subscription #$subscriptionId created for product #$ordersProductsId.");
                } else {
                    $this->log->write("    ERROR: Failed to create saved card subscription for product #$ordersProductsId.");
                }
            } else {
                // PayPal-managed subscription (has plan_id) -> Save to paypal_subscriptions table
                $this->log->write("    Product #$ordersProductsId: PayPal-managed subscription (has plan_id), routing to paypal_subscriptions.");
                
                $status = SubscriptionManager::STATUS_PENDING;
                $paypalVaultId = 0;
                $vaultId = '';

                if ($vaultRecord !== null) {
                    $paypalVaultId = (int)($vaultRecord['paypal_vault_id'] ?? 0);
                    $vaultId = (string)($vaultRecord['vault_id'] ?? '');
                    if ($vaultId === '' || $paypalVaultId === 0) {
                        // Vault is incomplete or not yet available
                        $status = SubscriptionManager::STATUS_AWAITING_VAULT;
                        $this->log->write("    Vault incomplete (paypal_vault_id=$paypalVaultId, vault_id=$vaultId), status: AWAITING_VAULT");
                    } else {
                        // Vault is fully available (e.g., using a saved card from a previous order)
                        // Set status to 'active' immediately instead of 'pending'
                        $status = SubscriptionManager::STATUS_ACTIVE;
                        $this->log->write("    Vault complete (paypal_vault_id=$paypalVaultId, vault_id=$vaultId), status: ACTIVE");
                    }
                } else {
                    $status = SubscriptionManager::STATUS_AWAITING_VAULT;
                    $this->log->write("    No vault record, status: AWAITING_VAULT");
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
                    $this->log->write("    SUCCESS: Vaulted subscription #$subscriptionId created for product #$ordersProductsId.");
                } else {
                    $this->log->write("    ERROR: Failed to create vaulted subscription for product #$ordersProductsId.");
                }
            }

            $products->MoveNext();
        }

        if ($loggedAny) {
            $this->log->write("    Subscription(s) created successfully, triggering NOTIFY_RECURRING_ORDER_LOGGED.");
            $zco_notifier->notify('NOTIFY_RECURRING_ORDER_LOGGED', [
                'orders_id' => $ordersId,
            ]);
        } else {
            $this->log->write("    No subscriptions were created for order #$ordersId.");
        }
    }

    /**
     * Handle vault card save notification to activate subscriptions that were awaiting vault.
     *
     * When a vault card is saved (either immediately after order or later via webhook),
     * this method finds any subscriptions for the same customer/order that are in
     * 'awaiting_vault' status and activates them by linking them to the vault.
     *
     * @param object $class The calling class
     * @param string $eventID The event identifier
     * @param array $vaultRecord The saved vault record from VaultManager
     */
    public function updateNotifyPaypalrVaultCardSaved(&$class, $eventID, $vaultRecord): void
    {
        $this->log->write("==> Subscription Observer: NOTIFY_PAYPALR_VAULT_CARD_SAVED fired.");
        
        if (!is_array($vaultRecord) || empty($vaultRecord)) {
            $this->log->write("    ERROR: Vault record is empty or invalid.");
            return;
        }
        
        $this->log->write("    Vault record received: " . Logger::logJSON($vaultRecord));

        $customersId = (int)($vaultRecord['customers_id'] ?? 0);
        $ordersId = (int)($vaultRecord['orders_id'] ?? 0);
        $paypalVaultId = (int)($vaultRecord['paypal_vault_id'] ?? 0);
        $vaultId = (string)($vaultRecord['vault_id'] ?? '');

        if ($customersId <= 0 || $ordersId <= 0 || $paypalVaultId <= 0 || $vaultId === '') {
            $this->log->write("    ERROR: Incomplete vault record data (customers_id=$customersId, orders_id=$ordersId, paypal_vault_id=$paypalVaultId, vault_id=$vaultId).");
            return;
        }

        // Activate any subscriptions that were awaiting this vault
        $activatedCount = SubscriptionManager::activateSubscriptionsWithVault(
            $customersId,
            $ordersId,
            $paypalVaultId,
            $vaultId
        );
        
        $this->log->write("    Activated $activatedCount subscription(s) with vault (customer #$customersId, order #$ordersId).");

        if ($activatedCount > 0) {
            global $zco_notifier;
            $zco_notifier->notify('NOTIFY_SUBSCRIPTIONS_ACTIVATED', [
                'customers_id' => $customersId,
                'orders_id' => $ordersId,
                'vault_id' => $vaultId,
                'activated_count' => $activatedCount,
            ]);
            $this->log->write("    NOTIFY_SUBSCRIPTIONS_ACTIVATED notification sent.");
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
        foreach (self::ATTRIBUTE_KEY_MAP as $field => $keys) {
            foreach ($keys as $key) {
                if (isset($attributeMap[$key])) {
                    $normalized[$field] = $attributeMap[$key];
                    break;
                }
            }
        }

        // Either/Or validation logic:
        // IF plan_id is provided -> Only plan_id is required (PayPal-managed subscription)
        // IF plan_id is NOT provided -> billing_period and billing_frequency are required (Zen Cart-managed subscription)
        
        $hasPlanId = !empty($normalized['plan_id']);
        $hasBillingPeriod = !empty($normalized['billing_period']);
        $hasBillingFrequency = !empty($normalized['billing_frequency']);
        
        if ($hasPlanId) {
            // PayPal-managed subscription: plan_id is sufficient
            // Return early with plan_id only (other attributes will be ignored by PayPal)
            return [
                'plan_id' => (string)$normalized['plan_id'],
                'billing_period' => '',
                'billing_frequency' => 0,
                'total_billing_cycles' => 0,
                'trial_period' => '',
                'trial_frequency' => 0,
                'trial_total_cycles' => 0,
                'setup_fee' => 0.0,
                'attributes' => $attributeMap,
            ];
        }
        
        // Zen Cart-managed subscription: billing_period and billing_frequency are required
        if (!$hasBillingPeriod || !$hasBillingFrequency) {
            return null;
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
            'plan_id' => '',
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

    /**
     * Get saved_credit_card_id from vault record by looking up in TABLE_SAVED_CREDIT_CARDS.
     * 
     * @param array|null $vaultRecord The vault record from VaultManager
     * @return int The saved_credit_card_id or 0 if not found
     */
    protected function getSavedCreditCardId(?array $vaultRecord): int
    {
        if ($vaultRecord === null) {
            return 0;
        }
        
        $vaultId = (string)($vaultRecord['vault_id'] ?? '');
        if ($vaultId === '') {
            return 0;
        }
        
        global $db;
        
        // Ensure TABLE_SAVED_CREDIT_CARDS is defined
        if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
            if (class_exists('PayPalRestful\\Common\\SavedCreditCardsManager')) {
                \PayPalRestful\Common\SavedCreditCardsManager::ensureSchema();
            }
        }
        
        if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
            $this->log->write("    ERROR: TABLE_SAVED_CREDIT_CARDS constant not defined.");
            return 0;
        }
        
        // Use parameterized query approach - zen_db_input provides escaping
        $safeVaultId = zen_db_input($vaultId);
        $result = $db->Execute(
            "SELECT saved_credit_card_id FROM " . TABLE_SAVED_CREDIT_CARDS . "
             WHERE vault_id = '$safeVaultId'
             AND is_deleted = 0
             LIMIT 1"
        );
        
        if ($result->EOF) {
            $this->log->write("    No saved_credit_card found for vault_id: $vaultId");
            return 0;
        }
        
        return (int)$result->fields['saved_credit_card_id'];
    }

    /**
     * Calculate the next billing date based on subscription attributes.
     * 
     * @param array $subscriptionAttributes The subscription attributes
     * @return string The next billing date in Y-m-d format
     */
    protected function calculateNextBillingDate(array $subscriptionAttributes): string
    {
        $billingPeriod = $subscriptionAttributes['billing_period'] ?? 'MONTH';
        $billingFrequency = (int)($subscriptionAttributes['billing_frequency'] ?? 1);
        
        if ($billingFrequency <= 0) {
            $billingFrequency = 1;
        }
        
        // Start from today
        $date = new DateTime();
        
        // Map PayPal period to PHP DateInterval
        switch (strtoupper($billingPeriod)) {
            case 'DAY':
                $date->modify('+' . $billingFrequency . ' days');
                break;
            case 'WEEK':
                $date->modify('+' . ($billingFrequency * 7) . ' days');
                break;
            case 'SEMI_MONTH':
                // Semi-monthly is approximately 15 days
                $date->modify('+15 days');
                break;
            case 'YEAR':
                $date->modify('+' . $billingFrequency . ' years');
                break;
            case 'MONTH':
            default:
                $date->modify('+' . $billingFrequency . ' months');
                break;
        }
        
        return $date->format('Y-m-d');
    }
}
