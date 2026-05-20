<?php
/**
 * On-demand PayPal Catalog Product + Billing Plan provisioner.
 *
 * Subscription products in Zen Cart carry three attributes (Billing Period,
 * Billing Frequency, Total Billing Cycles) instead of a pre-defined PayPal
 * plan_id. To route those products through PayPal's REST Subscriptions API
 * (so that PayPal owns the recurring schedule and the customer can manage
 * the subscription from inside their PayPal account) we still need a
 * plan_id. This class transparently provisions a catalog product + billing
 * plan against PayPal and caches the resulting plan_id by a stable hash of
 * the subscription configuration, so repeat purchases of the same product
 * reuse the same plan.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;

class PayPalPlanProvisioner
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_CREATED = 'CREATED';

    /** @var PayPalAdvancedCheckoutApi */
    protected $api;

    /** @var Logger|null */
    protected $log;

    public function __construct(PayPalAdvancedCheckoutApi $api, ?Logger $log = null)
    {
        $this->api = $api;
        $this->log = $log ?? new Logger('plan-provisioner');
    }

    /**
     * Ensure a PayPal billing plan exists for the supplied subscription config.
     *
     * Returns the cached or freshly-created plan_id, or null if PayPal rejected
     * the request. The caller should fall back to the Zen Cart-managed flow when
     * null is returned, so the customer can still complete checkout.
     *
     * Required keys in $subscriptionConfig:
     *   - products_id          int    Zen Cart products_id (for cache scoping & display)
     *   - products_name        string Display name (max 127 chars; PayPal truncates)
     *   - currency_code        string ISO 4217 (e.g. USD)
     *   - amount               float  Per-billing-cycle charge
     *   - billing_period       string DAY|WEEK|MONTH|YEAR (PayPal interval unit)
     *   - billing_frequency    int    interval_count
     *   - total_billing_cycles int    0 = infinite, otherwise number of regular cycles
     *
     * Optional keys (passed through unchanged when present and >0):
     *   - trial_period         string
     *   - trial_frequency      int
     *   - trial_total_cycles   int
     *   - setup_fee            float
     *
     * @param array<string,mixed> $subscriptionConfig
     */
    public function provisionPlan(array $subscriptionConfig): ?string
    {
        $normalized = $this->normalizeConfig($subscriptionConfig);
        if ($normalized === null) {
            $this->log->write('PayPalPlanProvisioner::provisionPlan rejected: invalid config.');
            return null;
        }

        self::ensureSchema();

        $hash = $this->hashConfig($normalized);

        $cached = $this->lookupCacheByHash($hash);
        if ($cached !== null) {
            $planId = (string)($cached['plan_id'] ?? '');
            if ($planId !== '') {
                $this->touchCache((int)$cached['paypal_plan_cache_id']);
                $this->log->write("PayPalPlanProvisioner: reusing cached plan_id $planId for hash $hash.");
                return $planId;
            }
        }

        $productResponse = $this->api->createProduct($this->buildProductRequest($normalized));
        if (!is_array($productResponse) || empty($productResponse['id'])) {
            $this->log->write('PayPalPlanProvisioner: createProduct failed; response: ' . Logger::logJSON($productResponse));
            return null;
        }
        $paypalProductId = (string)$productResponse['id'];

        $planResponse = $this->api->createPlan($this->buildPlanRequest($paypalProductId, $normalized));
        if (!is_array($planResponse) || empty($planResponse['id'])) {
            $this->log->write('PayPalPlanProvisioner: createPlan failed; response: ' . Logger::logJSON($planResponse));
            return null;
        }
        $planId = (string)$planResponse['id'];

        // createPlan returns the plan in CREATED status by default; activate so it
        // becomes usable by the Subscriptions JS button.
        $planStatus = strtoupper((string)($planResponse['status'] ?? ''));
        if ($planStatus !== self::STATUS_ACTIVE) {
            $activated = $this->api->activatePlan($planId);
            if ($activated === false) {
                $this->log->write("PayPalPlanProvisioner: activatePlan failed for $planId.");
                // We still cache the inactive plan_id so we don't try to re-create
                // it; the next checkout attempt will retry activation via the
                // refresh routine before use.
            }
        }

        $this->insertCache($hash, $paypalProductId, $planId, $normalized);
        $this->log->write("PayPalPlanProvisioner: provisioned new plan $planId (product $paypalProductId) for hash $hash.");

        return $planId;
    }

    /**
     * Create the paypal_plan_cache table if it doesn't exist.
     */
    public static function ensureSchema(): void
    {
        defined('TABLE_PAYPAL_PLAN_CACHE') or define('TABLE_PAYPAL_PLAN_CACHE', DB_PREFIX . 'paypal_plan_cache');

        global $db;

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_PLAN_CACHE . " (
                paypal_plan_cache_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                config_hash CHAR(64) NOT NULL,
                products_id INT UNSIGNED NOT NULL DEFAULT 0,
                products_name VARCHAR(127) NOT NULL DEFAULT '',
                currency_code CHAR(3) NOT NULL DEFAULT '',
                amount DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                billing_period VARCHAR(16) NOT NULL DEFAULT '',
                billing_frequency SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                total_billing_cycles SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                trial_period VARCHAR(16) NOT NULL DEFAULT '',
                trial_frequency SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                trial_total_cycles SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                setup_fee DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                paypal_product_id VARCHAR(50) NOT NULL DEFAULT '',
                plan_id VARCHAR(64) NOT NULL DEFAULT '',
                status VARCHAR(16) NOT NULL DEFAULT 'CREATED',
                date_added DATETIME NOT NULL,
                last_used DATETIME DEFAULT NULL,
                PRIMARY KEY (paypal_plan_cache_id),
                UNIQUE KEY idx_config_hash (config_hash),
                KEY idx_products_id (products_id),
                KEY idx_plan_id (plan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Normalize and validate the supplied config, returning null on rejection.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>|null
     */
    protected function normalizeConfig(array $config): ?array
    {
        $billingPeriod = strtoupper(trim((string)($config['billing_period'] ?? '')));
        $allowedPeriods = ['DAY', 'WEEK', 'MONTH', 'YEAR'];
        if (!in_array($billingPeriod, $allowedPeriods, true)) {
            return null;
        }

        $billingFrequency = (int)($config['billing_frequency'] ?? 0);
        if ($billingFrequency <= 0) {
            return null;
        }

        $totalCycles = (int)($config['total_billing_cycles'] ?? 0);
        if ($totalCycles < 0) {
            $totalCycles = 0;
        }

        $amount = (float)($config['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $currency = strtoupper(trim((string)($config['currency_code'] ?? '')));
        if ($currency === '' || strlen($currency) !== 3) {
            return null;
        }

        $productsId = (int)($config['products_id'] ?? 0);
        if ($productsId <= 0) {
            return null;
        }

        $productsName = substr(trim((string)($config['products_name'] ?? 'Subscription product')), 0, 127);
        if ($productsName === '') {
            $productsName = 'Subscription product #' . $productsId;
        }

        $trialPeriod = strtoupper(trim((string)($config['trial_period'] ?? '')));
        if ($trialPeriod !== '' && !in_array($trialPeriod, $allowedPeriods, true)) {
            $trialPeriod = '';
        }
        $trialFrequency = max(0, (int)($config['trial_frequency'] ?? 0));
        $trialTotalCycles = max(0, (int)($config['trial_total_cycles'] ?? 0));
        if ($trialPeriod === '' || $trialFrequency <= 0 || $trialTotalCycles <= 0) {
            $trialPeriod = '';
            $trialFrequency = 0;
            $trialTotalCycles = 0;
        }

        $setupFee = (float)($config['setup_fee'] ?? 0);
        if ($setupFee < 0) {
            $setupFee = 0.0;
        }

        return [
            'products_id' => $productsId,
            'products_name' => $productsName,
            'currency_code' => $currency,
            'amount' => round($amount, 2),
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'total_billing_cycles' => $totalCycles,
            'trial_period' => $trialPeriod,
            'trial_frequency' => $trialFrequency,
            'trial_total_cycles' => $trialTotalCycles,
            'setup_fee' => round($setupFee, 2),
        ];
    }

    /**
     * Stable hash of the normalized config used as the cache key. Any change
     * to the subscription configuration (amount, period, trial, etc.) yields
     * a new hash and therefore a new PayPal plan, ensuring billing accuracy.
     */
    protected function hashConfig(array $normalized): string
    {
        $payload = [
            'products_id' => $normalized['products_id'],
            'currency' => $normalized['currency_code'],
            'amount' => sprintf('%.2f', $normalized['amount']),
            'billing_period' => $normalized['billing_period'],
            'billing_frequency' => $normalized['billing_frequency'],
            'total_billing_cycles' => $normalized['total_billing_cycles'],
            'trial_period' => $normalized['trial_period'],
            'trial_frequency' => $normalized['trial_frequency'],
            'trial_total_cycles' => $normalized['trial_total_cycles'],
            'setup_fee' => sprintf('%.2f', $normalized['setup_fee']),
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    protected function buildProductRequest(array $normalized): array
    {
        return [
            'name' => $normalized['products_name'],
            'description' => 'Recurring subscription for ' . $normalized['products_name'],
            'type' => 'DIGITAL',
            'category' => 'SOFTWARE',
        ];
    }

    /**
     * @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    protected function buildPlanRequest(string $paypalProductId, array $normalized): array
    {
        $billingCycles = [];

        if ($normalized['trial_period'] !== '' && $normalized['trial_frequency'] > 0 && $normalized['trial_total_cycles'] > 0) {
            $billingCycles[] = [
                'frequency' => [
                    'interval_unit' => $normalized['trial_period'],
                    'interval_count' => $normalized['trial_frequency'],
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => $normalized['trial_total_cycles'],
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => sprintf('%.2f', 0.00),
                        'currency_code' => $normalized['currency_code'],
                    ],
                ],
            ];
        }

        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => $normalized['billing_period'],
                'interval_count' => $normalized['billing_frequency'],
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => count($billingCycles) + 1,
            'total_cycles' => $normalized['total_billing_cycles'],
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => sprintf('%.2f', $normalized['amount']),
                    'currency_code' => $normalized['currency_code'],
                ],
            ],
        ];

        return [
            'product_id' => $paypalProductId,
            'name' => $normalized['products_name'],
            'description' => 'Auto-provisioned recurring plan for ' . $normalized['products_name'],
            'status' => self::STATUS_ACTIVE,
            'billing_cycles' => $billingCycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => sprintf('%.2f', $normalized['setup_fee']),
                    'currency_code' => $normalized['currency_code'],
                ],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function lookupCacheByHash(string $hash): ?array
    {
        global $db;

        $safeHash = zen_db_input($hash);
        $result = $db->Execute(
            "SELECT * FROM " . TABLE_PAYPAL_PLAN_CACHE
            . " WHERE config_hash = '$safeHash' LIMIT 1"
        );
        if (!is_object($result) || $result->EOF) {
            return null;
        }
        return $result->fields;
    }

    protected function touchCache(int $cacheId): void
    {
        if ($cacheId <= 0) {
            return;
        }
        global $db;

        $now = date('Y-m-d H:i:s');
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL_PLAN_CACHE
            . " SET last_used = '" . zen_db_input($now) . "'"
            . " WHERE paypal_plan_cache_id = " . (int)$cacheId
        );
    }

    /**
     * @param array<string,mixed> $normalized
     */
    protected function insertCache(string $hash, string $paypalProductId, string $planId, array $normalized): void
    {
        global $db;

        $now = date('Y-m-d H:i:s');

        $sqlData = [
            'config_hash' => $hash,
            'products_id' => $normalized['products_id'],
            'products_name' => $normalized['products_name'],
            'currency_code' => $normalized['currency_code'],
            'amount' => sprintf('%.4f', $normalized['amount']),
            'billing_period' => $normalized['billing_period'],
            'billing_frequency' => $normalized['billing_frequency'],
            'total_billing_cycles' => $normalized['total_billing_cycles'],
            'trial_period' => $normalized['trial_period'],
            'trial_frequency' => $normalized['trial_frequency'],
            'trial_total_cycles' => $normalized['trial_total_cycles'],
            'setup_fee' => sprintf('%.4f', $normalized['setup_fee']),
            'paypal_product_id' => $paypalProductId,
            'plan_id' => $planId,
            'status' => self::STATUS_ACTIVE,
            'date_added' => $now,
            'last_used' => $now,
        ];

        $columns = implode(', ', array_map(static fn($c) => "`$c`", array_keys($sqlData)));
        $values = implode(', ', array_map(static fn($v) => "'" . zen_db_input((string)$v) . "'", $sqlData));
        $updates = [];
        foreach ($sqlData as $col => $val) {
            if ($col === 'config_hash' || $col === 'date_added') {
                continue;
            }
            $updates[] = "`$col` = '" . zen_db_input((string)$val) . "'";
        }
        $updateClause = implode(', ', $updates);

        $db->Execute(
            "INSERT INTO " . TABLE_PAYPAL_PLAN_CACHE . " ($columns) VALUES ($values)"
            . " ON DUPLICATE KEY UPDATE $updateClause"
        );
    }
}
