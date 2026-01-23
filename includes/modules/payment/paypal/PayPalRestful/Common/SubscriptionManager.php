<?php
/**
 * Subscription logging helper for the PayPal Advanced Checkout module.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalRestful\Common;

use function date;
use function defined;
use function is_array;
use function json_encode;
use function zen_db_input;

class SubscriptionManager
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_VAULT = 'awaiting_vault';

    /**
     * Ensure the subscription logging table exists.
     */
    public static function ensureSchema(): void
    {
        defined('TABLE_PAYPAL_SUBSCRIPTIONS') or define('TABLE_PAYPAL_SUBSCRIPTIONS', DB_PREFIX . 'paypal_subscriptions');

        global $db;

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_SUBSCRIPTIONS . " (
                paypal_subscription_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                customers_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_products_id INT UNSIGNED NOT NULL DEFAULT 0,
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
        ];

        foreach ($columns as $column => $definition) {
            if (!self::columnExists($column)) {
                $db->Execute(
                    'ALTER TABLE ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ADD ' . $column . ' ' . $definition
                );
            }
        }

        $indexes = [
            'idx_profile_id' => ['profile_id'],
            'idx_legacy_subscription' => ['legacy_subscription_id'],
        ];

        foreach ($indexes as $index => $columns) {
            if (!self::indexExists($index)) {
                $db->Execute(
                    'ALTER TABLE ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ADD INDEX ' . $index . ' (' . implode(',', $columns) . ')'
                );
            }
        }
    }

    private static function columnExists(string $column): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " LIKE '" . zen_db_input($column) . "'"
        );

        return ($result instanceof \queryFactoryResult && $result->RecordCount() > 0);
    }

    private static function indexExists(string $index): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW INDEX FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE Key_name = '" . zen_db_input($index) . "'"
        );

        return ($result instanceof \queryFactoryResult && $result->RecordCount() > 0);
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
        ];

        $attributes = $subscriptionData['attributes'] ?? [];
        if (is_array($attributes) && !empty($attributes)) {
            $json = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $record['attributes'] = $json;
            }
        }

        $existing = $db->Execute(
            "SELECT paypal_subscription_id
               FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . "
              WHERE orders_products_id = " . (int)$record['orders_products_id'] . "
              LIMIT 1"
        );

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
     * after order or updated via webhook). It finds all subscriptions that are awaiting
     * vault for the given customer/order and updates them with the vault information,
     * changing their status from 'awaiting_vault' to 'active'.
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

        // Find all subscriptions for this order that are awaiting vault
        $subscriptions = $db->Execute(
            "SELECT paypal_subscription_id, status
               FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . "
              WHERE customers_id = " . (int)$customersId . "
                AND orders_id = " . (int)$ordersId . "
                AND (status = '" . zen_db_input(self::STATUS_AWAITING_VAULT) . "'
                     OR (status = '" . zen_db_input(self::STATUS_PENDING) . "' AND vault_id = ''))
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
                'vault_id' => substr($vaultId, 0, 64),
                'status' => 'active',
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
