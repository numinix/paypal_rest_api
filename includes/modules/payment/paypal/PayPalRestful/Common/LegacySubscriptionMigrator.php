<?php
/**
 * Migration helper to align legacy paypal_recurring data with the REST subscription schema.
 */

namespace PayPalRestful\Common;

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

        // Unset orders_products_id if it's 0 to avoid UNIQUE constraint violations
        // NULL values are allowed in UNIQUE indexes and won't cause duplicates
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

    private static function tableExists(string $tableName): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW TABLES LIKE '" . zen_db_input($tableName) . "'"
        );

        return ($result instanceof \queryFactoryResult && !$result->EOF);
    }
}
