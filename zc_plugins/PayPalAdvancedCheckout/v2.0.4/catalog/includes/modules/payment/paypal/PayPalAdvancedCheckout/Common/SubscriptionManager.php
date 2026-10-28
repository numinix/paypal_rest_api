<?php
/**
 * Subscription logging helper for the PayPal Advanced Checkout module.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

use function date;
use function defined;
use function is_array;
use function json_encode;
use function zen_db_input;

class SubscriptionManager
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_VAULT = 'awaiting_vault';
    public const STATUS_ACTIVE = 'active';
    
    private const VAULT_ID_MAX_LENGTH = 64;

    /** @var bool Skip repeated schema work within a single request. */
    private static bool $schemaReady = false;

    /** @var array<string,bool> */
    private static array $columnCache = [];

    /** @var array<string,bool> */
    private static array $indexCache = [];

    /** @var bool */
    private static bool $expirationBackfillChecked = false;

    /**
     * Ensure the subscription logging table exists.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        defined('TABLE_PAYPAL_SUBSCRIPTIONS') or define('TABLE_PAYPAL_SUBSCRIPTIONS', DB_PREFIX . 'paypal_subscriptions');

        global $db;

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_SUBSCRIPTIONS . " (
                paypal_subscription_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                customers_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_products_id INT UNSIGNED DEFAULT NULL,
                products_id INT UNSIGNED NOT NULL DEFAULT 0,
                products_name VARCHAR(255) NOT NULL DEFAULT '',
                products_quantity DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
                plan_id VARCHAR(64) NOT NULL DEFAULT '',
                billing_period VARCHAR(16) NOT NULL DEFAULT '',
                billing_frequency SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                total_billing_cycles SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                trial_period VARCHAR(16) NOT NULL DEFAULT '',
                trial_frequency SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                trial_total_cycles SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                setup_fee DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                amount DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                currency_code CHAR(3) NOT NULL DEFAULT '',
                currency_value DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
                paypal_vault_id INT UNSIGNED NOT NULL DEFAULT 0,
                vault_id VARCHAR(64) NOT NULL DEFAULT '',
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                attributes TEXT,
                date_added DATETIME NOT NULL,
                last_modified DATETIME DEFAULT NULL,
                PRIMARY KEY (paypal_subscription_id),
                UNIQUE KEY idx_orders_product (orders_products_id),
                KEY idx_orders_id (orders_id),
                KEY idx_customers_id (customers_id),
                KEY idx_plan_id (plan_id),
                KEY idx_vault_id (vault_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::ensureLegacyColumns();
        self::migrateOrdersProductsIdToNullable();
        self::$schemaReady = true;
    }

    private static function ensureLegacyColumns(): void
    {
        global $db;

        $columns = [
            'legacy_subscription_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
            'profile_id' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'next_payment_date' => "DATE DEFAULT NULL",
            'next_payment_due' => "DATE DEFAULT NULL",
            'next_payment_due_date' => "DATE DEFAULT NULL",
            'next_billing_date' => "DATE DEFAULT NULL",
            'expiration_date' => "DATE DEFAULT NULL",
            'domain' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'is_archived' => "TINYINT(1) NOT NULL DEFAULT 0",
            // Stores the PayPal-side subscription identifier (e.g. I-BW452GLLEP1G) returned
            // by v1/billing/subscriptions when a subscription is created. Distinguished
            // from the primary key paypal_subscription_id (an internal autoincrement).
            'paypal_subscription_remote_id' => "VARCHAR(64) NOT NULL DEFAULT ''",
        ];

        $addedExpirationDate = false;
        foreach ($columns as $column => $definition) {
            if (!self::columnExists($column)) {
                $db->Execute(
                    'ALTER TABLE ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ADD ' . $column . ' ' . $definition
                );
                self::$columnCache[$column] = true;
                if ($column === 'expiration_date') {
                    $addedExpirationDate = true;
                }
            }
        }

        // Only scan for missing expiration dates when the column was just added or
        // a cheap existence check finds work. Running the full backfill on every
        // admin page load was a major contributor to multi-minute load times.
        if ($addedExpirationDate || self::expirationBackfillNeeded()) {
            self::backfillExpirationDates();
        }

        $indexes = [
            'idx_profile_id' => ['profile_id'],
            'idx_legacy_subscription' => ['legacy_subscription_id'],
            'idx_is_archived' => ['is_archived'],
            'idx_paypal_remote_id' => ['paypal_subscription_remote_id'],
        ];

        foreach ($indexes as $index => $columns) {
            if (!self::indexExists($index)) {
                $db->Execute(
                    'ALTER TABLE ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ADD INDEX ' . $index . ' (' . implode(',', $columns) . ')'
                );
                self::$indexCache[$index] = true;
            }
        }
    }

    /**
     * Find a subscription record by its PayPal-side remote id (I-XXXXXX).
     *
     * @return array<string,mixed>|null
     */
    public static function findByRemoteId(string $remoteId): ?array
    {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            return null;
        }

        self::ensureSchema();

        global $db;

        $safe = zen_db_input($remoteId);
        $result = $db->Execute(
            "SELECT * FROM " . TABLE_PAYPAL_SUBSCRIPTIONS
            . " WHERE paypal_subscription_remote_id = '$safe'"
            . " LIMIT 1"
        );

        if (!is_object($result) || $result->EOF) {
            return null;
        }

        return $result->fields;
    }

    /**
     * Update the status of a subscription identified by its PayPal-side remote id.
     * Returns true when the row was found and updated. Extra columns (e.g.
     * next_billing_date) may be supplied via $extraColumns and will be written
     * alongside status if they're present in the existing schema.
     *
     * @param array<string,scalar|null> $extraColumns
     */
    public static function updateStatusByRemoteId(string $remoteId, string $status, array $extraColumns = []): bool
    {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            return false;
        }

        $existing = self::findByRemoteId($remoteId);
        if ($existing === null) {
            return false;
        }

        global $db;

        $sets = [
            'status' => substr($status, 0, 32),
            'last_modified' => date('Y-m-d H:i:s'),
        ];

        foreach ($extraColumns as $col => $val) {
            if (!is_string($col) || $col === '' || !self::columnExists($col)) {
                continue;
            }
            $sets[$col] = $val;
        }

        $assignments = [];
        foreach ($sets as $col => $val) {
            if ($val === null) {
                $assignments[] = "`$col` = NULL";
                continue;
            }
            $assignments[] = "`$col` = '" . zen_db_input((string)$val) . "'";
        }
        $assignmentSql = implode(', ', $assignments);

        $safeRemote = zen_db_input($remoteId);
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL_SUBSCRIPTIONS
            . " SET $assignmentSql"
            . " WHERE paypal_subscription_remote_id = '$safeRemote'"
        );

        return true;
    }

    private static function columnExists(string $column): bool
    {
        if (isset(self::$columnCache[$column])) {
            return self::$columnCache[$column];
        }

        global $db;

        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " LIKE '" . zen_db_input($column) . "'"
        );

        return self::$columnCache[$column] = ($result instanceof \queryFactoryResult && $result->RecordCount() > 0);
    }

    /**
     * Cheap check used to skip the full expiration backfill on warm requests.
     */
    private static function expirationBackfillNeeded(): bool
    {
        if (self::$expirationBackfillChecked) {
            return false;
        }
        self::$expirationBackfillChecked = true;

        if (!self::columnExists('expiration_date')) {
            return false;
        }

        global $db;

        $probe = $db->Execute(
            'SELECT paypal_subscription_id FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS
            . ' WHERE expiration_date IS NULL'
            . ' AND total_billing_cycles > 0'
            . " AND LOWER(status) IN ('active','scheduled','pending','awaiting_vault','suspended')"
            . ' LIMIT 1'
        );

        return is_object($probe) && !$probe->EOF;
    }

    /**
     * Backfill expiration_date for active-ish rows that have a finite cycle count.
     * Uses date_added (fallback date_purchased) + (cycles * frequency) periods.
     */
    private static function backfillExpirationDates(): void
    {
        if (!self::columnExists('expiration_date')) {
            return;
        }

        if (!function_exists('paypalac_compute_subscription_expiration_date')) {
            $helper = DIR_FS_CATALOG . 'includes/functions/extra_functions/paypalac_subscription_functions.php';
            if (is_file($helper)) {
                require_once $helper;
            }
        }
        if (!function_exists('paypalac_compute_subscription_expiration_date')) {
            return;
        }

        global $db;

        $result = $db->Execute(
            'SELECT ps.paypal_subscription_id, ps.date_added, ps.billing_period, ps.billing_frequency, ps.total_billing_cycles,'
            . ' o.date_purchased'
            . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
            . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = ps.orders_id'
            . ' WHERE ps.expiration_date IS NULL'
            . ' AND ps.total_billing_cycles > 0'
            . ' AND LOWER(ps.status) IN (\'active\',\'scheduled\',\'pending\',\'awaiting_vault\',\'suspended\')'
        );

        while (is_object($result) && !$result->EOF) {
            $start = '';
            foreach (['date_added', 'date_purchased'] as $key) {
                $candidate = trim((string) ($result->fields[$key] ?? ''));
                if ($candidate !== '' && !str_starts_with($candidate, '0000-00-00')) {
                    $start = $candidate;
                    break;
                }
            }
            $expiry = $start !== ''
                ? paypalac_compute_subscription_expiration_date(
                    $start,
                    (string) ($result->fields['billing_period'] ?? ''),
                    (int) ($result->fields['billing_frequency'] ?? 0),
                    (int) ($result->fields['total_billing_cycles'] ?? 0)
                )
                : null;
            if ($expiry !== null) {
                $db->Execute(
                    'UPDATE ' . TABLE_PAYPAL_SUBSCRIPTIONS
                    . " SET expiration_date = '" . zen_db_input($expiry) . "'"
                    . ' WHERE paypal_subscription_id = ' . (int) $result->fields['paypal_subscription_id']
                    . ' AND expiration_date IS NULL'
                );
            }
            $result->MoveNext();
        }
    }

    private static function indexExists(string $index): bool
    {
        if (isset(self::$indexCache[$index])) {
            return self::$indexCache[$index];
        }

        global $db;

        $result = $db->Execute(
            "SHOW INDEX FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE Key_name = '" . zen_db_input($index) . "'"
        );

        return self::$indexCache[$index] = ($result instanceof \queryFactoryResult && $result->RecordCount() > 0);
    }

    /**
     * Migrate orders_products_id column to be nullable to prevent duplicate key errors.
     * 
     * This is needed for legacy subscriptions where orders_products_id may be 0.
     * With a UNIQUE constraint, multiple 0 values would cause duplicate key errors.
     * NULL values don't violate UNIQUE constraints in MySQL.
     */
    private static function migrateOrdersProductsIdToNullable(): void
    {
        global $db;

        // Check if the column is nullable already
        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE Field = 'orders_products_id'"
        );

        if ($result instanceof \queryFactoryResult && !$result->EOF) {
            $isNullable = ($result->fields['Null'] === 'YES');
            
            if (!$isNullable) {
                // First update all 0 values to NULL
                $db->Execute(
                    "UPDATE " . TABLE_PAYPAL_SUBSCRIPTIONS . " SET orders_products_id = NULL WHERE orders_products_id = 0"
                );
                
                // Then modify the column to be nullable
                $db->Execute(
                    "ALTER TABLE " . TABLE_PAYPAL_SUBSCRIPTIONS . " MODIFY orders_products_id INT UNSIGNED DEFAULT NULL"
                );
            }
        }
    }

    /**
     * Create or update a subscription record for a product in an order.
     *
     * @param array<string,mixed> $subscriptionData
     *
     * @return int The database identifier for the logged subscription.
     */
    public static function logSubscription(array $subscriptionData): int
    {
        self::ensureSchema();

        global $db;

        $now = date('Y-m-d H:i:s');

        $record = [
            'customers_id' => (int)($subscriptionData['customers_id'] ?? 0),
            'orders_id' => (int)($subscriptionData['orders_id'] ?? 0),
            'orders_products_id' => (int)($subscriptionData['orders_products_id'] ?? 0),
            'products_id' => (int)($subscriptionData['products_id'] ?? 0),
            'products_name' => (string)($subscriptionData['products_name'] ?? ''),
            'products_quantity' => (float)($subscriptionData['products_quantity'] ?? 1),
            'plan_id' => substr((string)($subscriptionData['plan_id'] ?? ''), 0, 64),
            'billing_period' => substr((string)($subscriptionData['billing_period'] ?? ''), 0, 16),
            'billing_frequency' => (int)($subscriptionData['billing_frequency'] ?? 0),
            'total_billing_cycles' => (int)($subscriptionData['total_billing_cycles'] ?? 0),
            'trial_period' => substr((string)($subscriptionData['trial_period'] ?? ''), 0, 16),
            'trial_frequency' => (int)($subscriptionData['trial_frequency'] ?? 0),
            'trial_total_cycles' => (int)($subscriptionData['trial_total_cycles'] ?? 0),
            'setup_fee' => (float)($subscriptionData['setup_fee'] ?? 0.0),
            'amount' => (float)($subscriptionData['amount'] ?? 0.0),
            'currency_code' => substr((string)($subscriptionData['currency_code'] ?? ''), 0, 3),
            'currency_value' => (float)($subscriptionData['currency_value'] ?? 1.0),
            'paypal_vault_id' => (int)($subscriptionData['paypal_vault_id'] ?? 0),
            'vault_id' => substr((string)($subscriptionData['vault_id'] ?? ''), 0, 64),
            'status' => substr((string)($subscriptionData['status'] ?? self::STATUS_PENDING), 0, 32),
            'paypal_subscription_remote_id' => substr((string)($subscriptionData['paypal_subscription_remote_id'] ?? ''), 0, 64),
        ];

        $attributes = $subscriptionData['attributes'] ?? [];
        if (is_array($attributes) && !empty($attributes)) {
            $json = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $record['attributes'] = $json;
            }
        }

        // Set orders_products_id to NULL if it's 0 to avoid UNIQUE constraint violations
        // NULL values are allowed in UNIQUE indexes and won't cause duplicates
        if (isset($record['orders_products_id']) && (int)$record['orders_products_id'] === 0) {
            $record['orders_products_id'] = null;
        }

        // Only check for existing subscription by orders_products_id if it's not NULL
        if ($record['orders_products_id'] !== null) {
            $existing = $db->Execute(
                "SELECT paypal_subscription_id
                   FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . "
                  WHERE orders_products_id = " . (int)$record['orders_products_id'] . "
                  LIMIT 1"
            );
        } else {
            // When orders_products_id is NULL, don't check for existing records by this field
            // as multiple records can have NULL values
            // Create empty result set (queryFactoryResult defaults to EOF = true)
            $existing = new \queryFactoryResult();
        }

        // Persist planned expiration at create/update when cycles are finite.
        if (!array_key_exists('expiration_date', $subscriptionData)) {
            if (!function_exists('paypalac_compute_subscription_expiration_date')) {
                $helper = DIR_FS_CATALOG . 'includes/functions/extra_functions/paypalac_subscription_functions.php';
                if (is_file($helper)) {
                    require_once $helper;
                }
            }
            if (function_exists('paypalac_compute_subscription_expiration_date')) {
                $startForExpiry = (string)($subscriptionData['date_added'] ?? $now);
                $computedExpiry = paypalac_compute_subscription_expiration_date(
                    $startForExpiry,
                    (string)($record['billing_period'] ?? ''),
                    (int)($record['billing_frequency'] ?? 0),
                    (int)($record['total_billing_cycles'] ?? 0)
                );
                $record['expiration_date'] = $computedExpiry;
            }
        } elseif ($subscriptionData['expiration_date'] === null || $subscriptionData['expiration_date'] === '') {
            $record['expiration_date'] = null;
        } else {
            $record['expiration_date'] = substr((string)$subscriptionData['expiration_date'], 0, 10);
        }

        if ($existing->EOF) {
            $record['date_added'] = $now;
            $record['last_modified'] = $now;
            zen_db_perform(TABLE_PAYPAL_SUBSCRIPTIONS, $record);
            return (int)$db->Insert_ID();
        }

        $record['last_modified'] = $now;
        zen_db_perform(
            TABLE_PAYPAL_SUBSCRIPTIONS,
            $record,
            'update',
            'paypal_subscription_id = ' . (int)$existing->fields['paypal_subscription_id']
        );

        return (int)$existing->fields['paypal_subscription_id'];
    }

    /**
     * Link pending subscriptions with a newly vaulted payment token and activate them.
     *
     * This method is called when a vault card becomes available (either saved immediately
     * after order or updated via webhook). It finds subscriptions for the given customer/order
     * that are awaiting vault (or active-but-missing paypal_vault_id from authorize-only checkout)
     * and updates them with the vault information, setting status to active.
     *
     * @param int $customersId The customer ID
     * @param int $ordersId The order ID
     * @param int $paypalVaultId The paypal_vault_id from the vault table
     * @param string $vaultId The PayPal vault token ID
     *
     * @return int Number of subscriptions that were activated
     */
    public static function activateSubscriptionsWithVault(int $customersId, int $ordersId, int $paypalVaultId, string $vaultId): int
    {
        if ($customersId <= 0 || $ordersId <= 0 || $paypalVaultId <= 0 || $vaultId === '') {
            return 0;
        }

        self::ensureSchema();

        global $db;

        // Find subscriptions for this order that still need vault linkage (including authorize-only
        // checkouts that were marked active before paypal_vault_id was assigned).
        $subscriptions = $db->Execute(
            "SELECT paypal_subscription_id, status
               FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . "
              WHERE customers_id = " . (int)$customersId . "
                AND orders_id = " . (int)$ordersId . "
                AND (status = '" . zen_db_input(self::STATUS_AWAITING_VAULT) . "'
                     OR (status = '" . zen_db_input(self::STATUS_PENDING) . "' AND (vault_id IS NULL OR vault_id = ''))
                     OR (status = '" . zen_db_input(self::STATUS_ACTIVE) . "' AND paypal_vault_id = 0))
                AND paypal_vault_id = 0"
        );

        if ($subscriptions->EOF) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $activatedCount = 0;

        while (!$subscriptions->EOF) {
            $subscriptionId = (int)$subscriptions->fields['paypal_subscription_id'];

            // Update subscription with vault information and set status to active
            $updateData = [
                'paypal_vault_id' => $paypalVaultId,
                'vault_id' => substr($vaultId, 0, self::VAULT_ID_MAX_LENGTH),
                'status' => self::STATUS_ACTIVE,
                'last_modified' => $now,
            ];

            zen_db_perform(
                TABLE_PAYPAL_SUBSCRIPTIONS,
                $updateData,
                'update',
                'paypal_subscription_id = ' . $subscriptionId
            );

            $activatedCount++;
            $subscriptions->MoveNext();
        }

        return $activatedCount;
    }
}
