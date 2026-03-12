<?php
/**
 * Migration helper to align legacy paypal_recurring data with the REST subscription schema.
 */

namespace PayPalAdvancedCheckout\Common;

use function date;
use function is_array;
use function json_decode;
use function json_encode;
use function strtolower;
use function strtoupper;
use function substr;
use function zen_db_input;
use function trim;
use function strtotime;

class LegacySubscriptionMigrator
{
    public static function syncLegacySubscriptions(): void
    {
        SubscriptionManager::ensureSchema();
        VaultManager::ensureSchema();
        self::migrateLegacyRecurring();
        self::migrateSavedCardRecurringSubscriptions();
    }

    private static function migrateLegacyRecurring(): void
    {
        if (!defined('TABLE_PAYPAL_RECURRING')) {
            define('TABLE_PAYPAL_RECURRING', DB_PREFIX . 'paypal_recurring');
        }

        if (!self::tableExists(TABLE_PAYPAL_RECURRING)) {
            return;
        }

        global $db;

        $records = $db->Execute('SELECT * FROM ' . TABLE_PAYPAL_RECURRING);

        while ($records instanceof \queryFactoryResult && !$records->EOF) {
            $normalized = self::normalizeLegacyRow($records->fields);
            self::upsertSubscription($normalized);
            $records->MoveNext();
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalizeLegacyRow(array $row): array
    {
        $now = date('Y-m-d H:i:s');

        $billingPeriod = strtoupper(trim((string)($row['billingperiod'] ?? $row['billing_period'] ?? '')));
        $billingFrequency = (int)($row['billingfrequency'] ?? $row['billing_frequency'] ?? 0);
        $totalCycles = (int)($row['totalbillingcycles'] ?? $row['total_billing_cycles'] ?? 0);

        $attributes = self::normalizeAttributes($row, $billingPeriod, $billingFrequency, $totalCycles);

        return [
            'legacy_subscription_id' => (int)($row['subscription_id'] ?? $row['paypal_subscription_id'] ?? 0),
            'customers_id' => (int)($row['customers_id'] ?? 0),
            'orders_id' => (int)($row['orders_id'] ?? 0),
            'orders_products_id' => (int)($row['orders_products_id'] ?? $row['order_product_id'] ?? 0),
            'products_id' => (int)($row['products_id'] ?? 0),
            'products_name' => (string)($row['products_name'] ?? ''),
            'plan_id' => substr((string)($row['plan_id'] ?? ''), 0, 64),
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'total_billing_cycles' => $totalCycles,
            'setup_fee' => (float)($row['setup_fee'] ?? 0),
            'amount' => (float)($row['amount'] ?? $row['recurring_amount'] ?? 0),
            'currency_code' => substr((string)($row['currencycode'] ?? $row['currency_code'] ?? ''), 0, 3),
            'currency_value' => (float)($row['currency_value'] ?? 1),
            'paypal_vault_id' => (int)($row['paypal_vault_id'] ?? 0),
            'vault_id' => substr((string)($row['vault_id'] ?? ''), 0, 64),
            'status' => strtolower((string)($row['status'] ?? SubscriptionManager::STATUS_PENDING)),
            'profile_id' => substr((string)($row['profile_id'] ?? ''), 0, 64),
            'next_payment_date' => self::normalizeDateValue($row['next_payment_date'] ?? $row['next_payment_due'] ?? $row['next_billing_date'] ?? null),
            'next_payment_due' => self::normalizeDateValue($row['next_payment_due'] ?? null),
            'next_payment_due_date' => self::normalizeDateValue($row['next_payment_due_date'] ?? null),
            'next_billing_date' => self::normalizeDateValue($row['next_billing_date'] ?? null),
            'expiration_date' => self::normalizeDateValue($row['expiration_date'] ?? null),
            'domain' => (string)($row['domain'] ?? ''),
            'attributes' => $attributes,
            'date_added' => self::normalizeDateTime($row['date_added'] ?? $row['created_at'] ?? '') ?? $now,
            'last_modified' => self::normalizeDateTime($row['last_modified'] ?? $row['updated_at'] ?? '') ?? $now,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalizeAttributes(array $row, string $billingPeriod, int $billingFrequency, int $totalCycles): array
    {
        $attributes = [];

        if (isset($row['subscription_attributes']) && is_array($row['subscription_attributes'])) {
            $attributes = $row['subscription_attributes'];
        } elseif (!empty($row['subscription_attributes_json'])) {
            $decoded = json_decode((string)$row['subscription_attributes_json'], true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        if (!isset($attributes['billingperiod']) && $billingPeriod !== '') {
            $attributes['billingperiod'] = $billingPeriod;
        }
        if (!isset($attributes['billingfrequency']) && $billingFrequency > 0) {
            $attributes['billingfrequency'] = $billingFrequency;
        }
        if (!isset($attributes['totalbillingcycles']) && $totalCycles > 0) {
            $attributes['totalbillingcycles'] = $totalCycles;
        }

        return $attributes;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function upsertSubscription(array $data): void
    {
        global $db;

        $record = $data;

        if (is_array($record['attributes']) && !empty($record['attributes'])) {
            $encoded = json_encode($record['attributes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $record['attributes'] = $encoded;
            }
        } else {
            $record['attributes'] = '';
        }

        // Set orders_products_id to NULL if it's 0 to avoid UNIQUE constraint violations
        // NULL values are allowed in UNIQUE indexes and won't cause duplicates
        // We must unset the key entirely because zen_db_perform converts null to empty string
        if (isset($record['orders_products_id']) && (int)$record['orders_products_id'] === 0) {
            unset($record['orders_products_id']);
        }

        $existing = null;

        if (isset($data['legacy_subscription_id']) && (int)$data['legacy_subscription_id'] > 0) {
            $existing = $db->Execute(
                'SELECT paypal_subscription_id FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS
                . ' WHERE legacy_subscription_id = ' . (int)$data['legacy_subscription_id']
                . ' LIMIT 1'
            );
        }

        if (($existing === null || $existing->EOF) && isset($data['profile_id']) && $data['profile_id'] !== '') {
            $existing = $db->Execute(
                "SELECT paypal_subscription_id FROM " . TABLE_PAYPAL_SUBSCRIPTIONS
                . " WHERE profile_id = '" . zen_db_input($data['profile_id']) . "' LIMIT 1"
            );
        }

        if (($existing === null || $existing->EOF) && isset($data['orders_products_id']) && (int)$data['orders_products_id'] > 0) {
            $existing = $db->Execute(
                'SELECT paypal_subscription_id FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS
                . ' WHERE orders_products_id = ' . (int)$data['orders_products_id']
                . ' LIMIT 1'
            );
        }

        if ($existing instanceof \queryFactoryResult && !$existing->EOF) {
            zen_db_perform(
                TABLE_PAYPAL_SUBSCRIPTIONS,
                $record,
                'update',
                'paypal_subscription_id = ' . (int)$existing->fields['paypal_subscription_id']
            );
            return;
        }

        zen_db_perform(TABLE_PAYPAL_SUBSCRIPTIONS, $record);
    }

    private static function normalizeDateValue($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return null;
    }

    private static function normalizeDateTime($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * Migrate saved-card recurring subscriptions that have been assigned a PayPal vault card
     * into TABLE_PAYPAL_SUBSCRIPTIONS so they appear in the paypalac_subscriptions admin page.
     *
     * Only records whose linked saved_credit_card has a non-empty vault_id are migrated; pure
     * legacy (non-vault) subscriptions are left in TABLE_SAVED_CREDIT_CARDS_RECURRING only.
     */
    public static function migrateSavedCardRecurringSubscriptions(): void
    {
        if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
            return;
        }

        if (!self::tableExists(TABLE_SAVED_CREDIT_CARDS_RECURRING)) {
            return;
        }

        if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
            return;
        }

        if (!self::tableExists(TABLE_SAVED_CREDIT_CARDS)) {
            return;
        }

        global $db;

        // Only fetch records that are linked to a vault card and not yet in TABLE_PAYPAL_SUBSCRIPTIONS.
        // We detect vault cards by checking vault_id in TABLE_SAVED_CREDIT_CARDS.
        // Also check if the TABLE_PAYPAL_VAULT table exists so we can resolve paypal_vault_id.
        $hasVaultTable = defined('TABLE_PAYPAL_VAULT') && self::tableExists(TABLE_PAYPAL_VAULT);
        $hasLegacySubIdColumn = self::columnExistsInTable(TABLE_PAYPAL_SUBSCRIPTIONS, 'legacy_subscription_id');

        // Determine which column holds the orders_products_id reference in the recurring table.
        // Store just the column name (no table alias) for use in both queries and field access.
        $opIdColumnName = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'orders_products_id')
            ? 'orders_products_id'
            : (self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'original_orders_products_id')
                ? 'original_orders_products_id'
                : null);

        // Determine which date column to use for next_payment_date.
        $hasNextPaymentDate = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'next_payment_date');
        $hasDateColumn = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'date');

        $records = $db->Execute(
            'SELECT sccr.*, scc.vault_id AS scc_vault_id, scc.customers_id AS scc_customers_id'
            . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
            . ' LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id'
            . " WHERE scc.vault_id IS NOT NULL AND scc.vault_id <> ''"
            . " AND sccr.status IN ('scheduled', 'active')"
        );

        if (!($records instanceof \queryFactoryResult)) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        while (!$records->EOF) {
            $row = $records->fields;
            $vaultId = (string)($row['scc_vault_id'] ?? '');
            if ($vaultId === '') {
                $records->MoveNext();
                continue;
            }

            $recurringId = (int)$row['saved_credit_card_recurring_id'];

            // Always upsert — do NOT skip already-migrated subscriptions.
            // upsertSubscription() will UPDATE existing rows so that mutable
            // fields (next_payment_date, status, amount) stay in sync with
            // TABLE_SAVED_CREDIT_CARDS_RECURRING on every cron cycle.  Skipping
            // already-migrated rows was the root cause of the stale
            // next_payment_date bug: a payment processed by the legacy cron or a
            // checkout observer updated SCCR but left TABLE_PAYPAL_SUBSCRIPTIONS
            // unchanged, causing the paypalac cron to attempt a double charge on
            // the next run.

            // Resolve paypal_vault_id from TABLE_PAYPAL_VAULT if available.
            $paypalVaultId = 0;
            if ($hasVaultTable) {
                $vaultRecord = $db->Execute(
                    "SELECT paypal_vault_id FROM " . TABLE_PAYPAL_VAULT
                    . " WHERE vault_id = '" . zen_db_input($vaultId) . "' LIMIT 1"
                );
                if ($vaultRecord instanceof \queryFactoryResult && !$vaultRecord->EOF) {
                    $paypalVaultId = (int)$vaultRecord->fields['paypal_vault_id'];
                }
            }

            // Determine next_payment_date value.
            $nextPaymentDate = null;
            if ($hasNextPaymentDate && !empty($row['next_payment_date'])) {
                $nextPaymentDate = self::normalizeDateValue($row['next_payment_date']);
            } elseif ($hasDateColumn && !empty($row['date'])) {
                $nextPaymentDate = self::normalizeDateValue($row['date']);
            }

            // Determine the orders_products_id value using just the column name.
            $ordersProductsId = ($opIdColumnName !== null) ? (int)($row[$opIdColumnName] ?? 0) : 0;

            // Determine customers_id: prefer scc_customers_id, fall back to sccr.customers_id.
            $customersId = (int)($row['scc_customers_id'] ?? $row['customers_id'] ?? 0);

            $subscriptionData = [
                'legacy_subscription_id' => $recurringId,
                'customers_id' => $customersId,
                'orders_id' => (int)($row['recurring_orders_id'] ?? $row['orders_id'] ?? 0),
                'products_id' => (int)($row['products_id'] ?? 0),
                'products_name' => (string)($row['products_name'] ?? ''),
                'products_quantity' => 1,
                'plan_id' => substr((string)($row['profile_id'] ?? ''), 0, 64),
                'billing_period' => strtoupper(trim((string)($row['billing_period'] ?? ''))),
                'billing_frequency' => (int)($row['billing_frequency'] ?? 0),
                'total_billing_cycles' => (int)($row['total_billing_cycles'] ?? 0),
                'amount' => (float)($row['amount'] ?? 0),
                'currency_code' => substr((string)($row['currency_code'] ?? ''), 0, 3),
                'currency_value' => 1.0,
                'paypal_vault_id' => $paypalVaultId,
                'vault_id' => substr($vaultId, 0, 64),
                'status' => strtolower((string)($row['status'] ?? 'scheduled')),
                'next_payment_date' => $nextPaymentDate,
                'domain' => (string)($row['domain'] ?? ''),
                'date_added' => self::normalizeDateTime($row['date_added'] ?? '') ?? $now,
                'last_modified' => $now,
            ];

            if ($ordersProductsId > 0) {
                $subscriptionData['orders_products_id'] = $ordersProductsId;
            }

            // Decode subscription attributes for the attributes column.
            $attributes = [];
            if (!empty($row['subscription_attributes_json'])) {
                $decoded = json_decode((string)$row['subscription_attributes_json'], true);
                if (is_array($decoded)) {
                    $attributes = $decoded;
                }
            }
            if (!empty($attributes)) {
                $subscriptionData['attributes'] = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            self::upsertSubscription($subscriptionData);
            $records->MoveNext();
        }
    }

    /**
     * Migrate a single saved-card recurring subscription to TABLE_PAYPAL_SUBSCRIPTIONS.
     *
     * Called when a legacy subscription's card is updated to a PayPal vault card so the
     * subscription immediately becomes visible on the paypalac_subscriptions admin page.
     *
     * @param int $savedCardRecurringId The saved_credit_card_recurring_id to migrate.
     */
    public static function migrateSingleSavedCardRecurringSubscription(int $savedCardRecurringId): void
    {
        if ($savedCardRecurringId <= 0) {
            return;
        }

        if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') || !defined('TABLE_SAVED_CREDIT_CARDS')) {
            return;
        }

        if (!self::tableExists(TABLE_SAVED_CREDIT_CARDS_RECURRING) || !self::tableExists(TABLE_SAVED_CREDIT_CARDS)) {
            return;
        }

        SubscriptionManager::ensureSchema();
        VaultManager::ensureSchema();

        global $db;

        $hasVaultTable = defined('TABLE_PAYPAL_VAULT') && self::tableExists(TABLE_PAYPAL_VAULT);

        $opIdColumnName = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'orders_products_id')
            ? 'orders_products_id'
            : (self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'original_orders_products_id')
                ? 'original_orders_products_id'
                : null);

        $hasNextPaymentDate = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'next_payment_date');
        $hasDateColumn = self::columnExistsInTable(TABLE_SAVED_CREDIT_CARDS_RECURRING, 'date');

        $result = $db->Execute(
            'SELECT sccr.*, scc.vault_id AS scc_vault_id, scc.customers_id AS scc_customers_id'
            . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
            . ' LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id'
            . ' WHERE sccr.saved_credit_card_recurring_id = ' . $savedCardRecurringId
            . ' LIMIT 1'
        );

        if (!($result instanceof \queryFactoryResult) || $result->EOF) {
            return;
        }

        $row = $result->fields;
        $vaultId = (string)($row['scc_vault_id'] ?? '');
        if ($vaultId === '') {
            return;
        }

        $paypalVaultId = 0;
        if ($hasVaultTable) {
            $vaultRecord = $db->Execute(
                "SELECT paypal_vault_id FROM " . TABLE_PAYPAL_VAULT
                . " WHERE vault_id = '" . zen_db_input($vaultId) . "' LIMIT 1"
            );
            if ($vaultRecord instanceof \queryFactoryResult && !$vaultRecord->EOF) {
                $paypalVaultId = (int)$vaultRecord->fields['paypal_vault_id'];
            }
        }

        $nextPaymentDate = null;
        if ($hasNextPaymentDate && !empty($row['next_payment_date'])) {
            $nextPaymentDate = self::normalizeDateValue($row['next_payment_date']);
        } elseif ($hasDateColumn && !empty($row['date'])) {
            $nextPaymentDate = self::normalizeDateValue($row['date']);
        }

        $ordersProductsId = ($opIdColumnName !== null) ? (int)($row[$opIdColumnName] ?? 0) : 0;

        $customersId = (int)($row['scc_customers_id'] ?? $row['customers_id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $subscriptionData = [
            'legacy_subscription_id' => $savedCardRecurringId,
            'customers_id' => $customersId,
            'orders_id' => (int)($row['recurring_orders_id'] ?? $row['orders_id'] ?? 0),
            'products_id' => (int)($row['products_id'] ?? 0),
            'products_name' => (string)($row['products_name'] ?? ''),
            'products_quantity' => 1,
            'plan_id' => substr((string)($row['profile_id'] ?? ''), 0, 64),
            'billing_period' => strtoupper(trim((string)($row['billing_period'] ?? ''))),
            'billing_frequency' => (int)($row['billing_frequency'] ?? 0),
            'total_billing_cycles' => (int)($row['total_billing_cycles'] ?? 0),
            'amount' => (float)($row['amount'] ?? 0),
            'currency_code' => substr((string)($row['currency_code'] ?? ''), 0, 3),
            'currency_value' => 1.0,
            'paypal_vault_id' => $paypalVaultId,
            'vault_id' => substr($vaultId, 0, 64),
            'status' => strtolower((string)($row['status'] ?? 'scheduled')),
            'next_payment_date' => $nextPaymentDate,
            'domain' => (string)($row['domain'] ?? ''),
            'date_added' => self::normalizeDateTime($row['date_added'] ?? '') ?? $now,
            'last_modified' => $now,
        ];

        if ($ordersProductsId > 0) {
            $subscriptionData['orders_products_id'] = $ordersProductsId;
        }

        $attributes = [];
        if (!empty($row['subscription_attributes_json'])) {
            $decoded = json_decode((string)$row['subscription_attributes_json'], true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }
        if (!empty($attributes)) {
            $subscriptionData['attributes'] = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        self::upsertSubscription($subscriptionData);
    }

    /**
     * Check if a column exists in the given table.
     */
    private static function columnExistsInTable(string $tableName, string $column): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW COLUMNS FROM " . $tableName . " LIKE '" . zen_db_input($column) . "'"
        );

        return ($result instanceof \queryFactoryResult && !$result->EOF);
    }

    private static function tableExists(string $tableName): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW TABLES LIKE '" . zen_db_input($tableName) . "'"
        );

        return ($result instanceof \queryFactoryResult && !$result->EOF);
    }
}
