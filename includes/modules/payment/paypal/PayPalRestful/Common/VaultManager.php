<?php
/**
 * VaultManager.php maintains PayPal vault metadata for the PayPalRestful (paypalr) payment module.
 *
 * The vault stores per-customer payment tokens for Advanced Credit and Debit Card
 * transactions so that other plugins (for example, recurring billing extensions)
 * can reuse the stored instruments.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalRestful\Common;

use function json_decode;
use function json_encode;

/**
 * Provides helper methods to persist and retrieve PayPal vault metadata for storefront customers.
 */
class VaultManager
{
    /**
     * Ensure that the paypal vault table exists.
     */
    public static function ensureSchema(): void
    {
        global $db;

        defined('TABLE_PAYPAL_VAULT') or define('TABLE_PAYPAL_VAULT', DB_PREFIX . 'paypal_vault');

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_PAYPAL_VAULT . " (
                paypal_vault_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                customers_id INT UNSIGNED NOT NULL,
                orders_id INT UNSIGNED NOT NULL DEFAULT 0,
                vault_id VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT '',
                brand VARCHAR(32) NOT NULL DEFAULT '',
                last_digits VARCHAR(4) NOT NULL DEFAULT '',
                card_type VARCHAR(32) NOT NULL DEFAULT '',
                expiry CHAR(7) NOT NULL DEFAULT '',
                payer_id VARCHAR(64) NOT NULL DEFAULT '',
                paypal_customer_id VARCHAR(64) NOT NULL DEFAULT '',
                cardholder_name VARCHAR(96) NOT NULL DEFAULT '',
                billing_address TEXT DEFAULT NULL,
                card_data LONGTEXT DEFAULT NULL,
                create_time DATETIME DEFAULT NULL,
                update_time DATETIME DEFAULT NULL,
                date_added DATETIME DEFAULT NULL,
                last_modified DATETIME DEFAULT NULL,
                last_used DATETIME DEFAULT NULL,
                PRIMARY KEY (paypal_vault_id),
                UNIQUE KEY idx_paypal_vault_id (vault_id),
                KEY idx_paypal_vault_customer (customers_id),
                KEY idx_paypal_vault_status (customers_id, status)
            )"
        );
    }

    /**
     * Store or update a vaulted card for the supplied customer/order combination.
     *
     * @param int   $customers_id The Zen Cart customer's identifier.
     * @param int   $orders_id    The order identifier that produced the vault token.
     * @param array $cardSource   The card payment_source element returned by PayPal.
     *
     * @return array|null The stored record as an associative array or null if nothing was saved.
     */
    public static function saveVaultedCard(int $customers_id, int $orders_id, array $cardSource): ?array
    {
        if ($customers_id <= 0 || $orders_id <= 0) {
            return null;
        }

        $vault = $cardSource['vault'] ?? [];
        $vaultId = self::sanitizeString($vault['id'] ?? '', 64);
        if ($vaultId === '') {
            return null;
        }

        self::ensureSchema();

        global $db;

        $now = date('Y-m-d H:i:s');

        $lastDigits = $cardSource['last_digits'] ?? '';
        if ($lastDigits !== '') {
            $lastDigits = substr($lastDigits, -4);
        }

        $status = self::sanitizeString($vault['status'] ?? '', 32);
        if ($status !== '') {
            $status = strtoupper($status);
        }

        $sqlData = [
            'customers_id' => $customers_id,
            'orders_id' => $orders_id,
            'vault_id' => $vaultId,
            'status' => $status,
            'brand' => self::sanitizeString($cardSource['brand'] ?? '', 32),
            'last_digits' => self::sanitizeString($lastDigits, 4),
            'card_type' => self::sanitizeString($cardSource['type'] ?? '', 32),
            'expiry' => self::sanitizeString($cardSource['expiry'] ?? '', 7),
            'payer_id' => self::sanitizeString($vault['customer']['payer_id'] ?? '', 64),
            'paypal_customer_id' => self::sanitizeString($vault['customer']['id'] ?? '', 64),
            'cardholder_name' => self::sanitizeString($cardSource['name'] ?? '', 96),
            'last_modified' => $now,
        ];

        $billingAddress = self::encodeJson($cardSource['billing_address'] ?? null);
        if ($billingAddress !== null) {
            $sqlData['billing_address'] = $billingAddress;
        }

        $cardData = self::encodeJson($cardSource);
        if ($cardData !== null) {
            $sqlData['card_data'] = $cardData;
        }

        $createTime = self::convertPayPalDate($vault['create_time'] ?? null);
        if ($createTime !== null) {
            $sqlData['create_time'] = $createTime;
        }

        $updateTime = self::convertPayPalDate($vault['update_time'] ?? null);
        if ($updateTime !== null) {
            $sqlData['update_time'] = $updateTime;
            $sqlData['last_used'] = $updateTime;
        } else {
            $sqlData['last_used'] = $now;
        }

        $existing = $db->Execute(
            "SELECT paypal_vault_id, date_added
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE vault_id = '" . zen_db_input($vaultId) . "'
              LIMIT 1"
        );

        if ($existing->EOF) {
            $sqlData['date_added'] = $now;
            zen_db_perform(TABLE_PAYPAL_VAULT, $sqlData);
        } else {
            $paypalVaultId = (int)$existing->fields['paypal_vault_id'];
            // Do not overwrite the original creation time of the database record.
            unset($sqlData['date_added']);
            zen_db_perform(TABLE_PAYPAL_VAULT, $sqlData, 'update', 'paypal_vault_id = ' . $paypalVaultId);
        }

        $stored = $db->Execute(
            "SELECT *
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE vault_id = '" . zen_db_input($vaultId) . "'
              LIMIT 1"
        );

        if ($stored->EOF) {
            return null;
        }

        return self::mapRow($stored->fields);
    }

    /**
     * Update a stored vaulted card using a webhook payload's normalized card resource.
     *
     * @param array $resource The normalized payment_source.card payload containing vault metadata.
     *
     * @return array|null The updated record or null if no matching vault entry exists.
     */
    public static function updateFromWebhookPayload(array $resource): ?array
    {
        $vault = $resource['vault'] ?? [];
        $vaultId = self::sanitizeString($vault['id'] ?? '', 64);
        if ($vaultId === '') {
            return null;
        }

        self::ensureSchema();

        global $db;

        $existing = $db->Execute(
            "SELECT paypal_vault_id" .
            "   FROM " . TABLE_PAYPAL_VAULT .
            "  WHERE vault_id = '" . zen_db_input($vaultId) . "'" .
            "  LIMIT 1"
        );

        if ($existing->EOF) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        $sqlData = [
            'last_modified' => $now,
        ];

        $status = self::sanitizeString($vault['status'] ?? '', 32);
        if ($status !== '') {
            $sqlData['status'] = strtoupper($status);
        }

        $brand = self::sanitizeString($resource['brand'] ?? '', 32);
        if ($brand !== '') {
            $sqlData['brand'] = $brand;
        }

        $lastDigits = $resource['last_digits'] ?? '';
        if ($lastDigits !== '') {
            $sqlData['last_digits'] = self::sanitizeString(substr($lastDigits, -4), 4);
        }

        $cardType = self::sanitizeString($resource['type'] ?? '', 32);
        if ($cardType !== '') {
            $sqlData['card_type'] = $cardType;
        }

        $expiry = self::sanitizeString($resource['expiry'] ?? '', 7);
        if ($expiry !== '') {
            $sqlData['expiry'] = $expiry;
        }

        $cardholder = self::sanitizeString($resource['name'] ?? '', 96);
        if ($cardholder !== '') {
            $sqlData['cardholder_name'] = $cardholder;
        }

        if (isset($vault['customer']['payer_id'])) {
            $payerId = self::sanitizeString($vault['customer']['payer_id'], 64);
            if ($payerId !== '') {
                $sqlData['payer_id'] = $payerId;
            }
        }

        if (isset($vault['customer']['id'])) {
            $customerId = self::sanitizeString($vault['customer']['id'], 64);
            if ($customerId !== '') {
                $sqlData['paypal_customer_id'] = $customerId;
            }
        }

        if (array_key_exists('billing_address', $resource)) {
            $billingAddress = self::encodeJson($resource['billing_address']);
            if ($billingAddress !== null) {
                $sqlData['billing_address'] = $billingAddress;
            }
        }

        $cardData = self::encodeJson($resource);
        if ($cardData !== null) {
            $sqlData['card_data'] = $cardData;
        }

        $createTime = self::convertPayPalDate($vault['create_time'] ?? null);
        if ($createTime !== null) {
            $sqlData['create_time'] = $createTime;
        }

        $updateTime = self::convertPayPalDate($vault['update_time'] ?? null);
        if ($updateTime !== null) {
            $sqlData['update_time'] = $updateTime;
            $sqlData['last_used'] = $updateTime;
        } else {
            $sqlData['last_used'] = $now;
        }

        $paypalVaultId = (int)$existing->fields['paypal_vault_id'];

        zen_db_perform(TABLE_PAYPAL_VAULT, $sqlData, 'update', 'paypal_vault_id = ' . $paypalVaultId);

        $stored = $db->Execute(
            "SELECT *" .
            "   FROM " . TABLE_PAYPAL_VAULT .
            "  WHERE vault_id = '" . zen_db_input($vaultId) . "'" .
            "  LIMIT 1"
        );

        if ($stored->EOF) {
            return null;
        }

        return self::mapRow($stored->fields);
    }

    /**
     * Retrieve a customer's vaulted cards.
     *
     * @param int  $customers_id The Zen Cart customer's identifier.
     * @param bool $activeOnly   When true (default), limit to active/approved vault entries.
     *
     * @return array[]
     */
    public static function getCustomerVaultedCards(int $customers_id, bool $activeOnly = true): array
    {
        if ($customers_id <= 0) {
            return [];
        }

        self::ensureSchema();

        global $db;

        $whereClause = "customers_id = " . (int)$customers_id;
        if ($activeOnly === true) {
            $whereClause .= " AND status IN ('ACTIVE','APPROVED','VAULTED')";
        }

        $vaultedCards = [];
        $records = $db->Execute(
            "SELECT *
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE $whereClause
           ORDER BY last_modified DESC"
        );

        foreach ($records as $record) {
            $vaultedCards[] = self::mapRow($record);
        }

        return $vaultedCards;
    }

    /**
     * Convert a PayPal ISO 8601 timestamp to the database format.
     */
    protected static function convertPayPalDate(?string $paypalDate): ?string
    {
        if ($paypalDate === null || $paypalDate === '') {
            return null;
        }

        return Helpers::convertPayPalDatePay2Db($paypalDate);
    }

    /**
     * Truncate string values to fit within the table's column limits.
     */
    protected static function sanitizeString(?string $value, int $maxLength): string
    {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        return substr($value, 0, $maxLength);
    }

    /**
     * Encode an array value to JSON, returning null if encoding fails or the value is empty.
     */
    protected static function encodeJson($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $encoded = json_encode($value);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    /**
     * Decode a JSON string into an array.
     */
    protected static function decodeJson(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Normalize the database row for consumers.
     */
    protected static function mapRow(array $row): array
    {
        return [
            'paypal_vault_id' => (int)($row['paypal_vault_id'] ?? 0),
            'customers_id' => (int)($row['customers_id'] ?? 0),
            'orders_id' => (int)($row['orders_id'] ?? 0),
            'vault_id' => $row['vault_id'] ?? '',
            'status' => $row['status'] ?? '',
            'brand' => $row['brand'] ?? '',
            'last_digits' => $row['last_digits'] ?? '',
            'card_type' => $row['card_type'] ?? '',
            'expiry' => $row['expiry'] ?? '',
            'payer_id' => $row['payer_id'] ?? '',
            'paypal_customer_id' => $row['paypal_customer_id'] ?? '',
            'cardholder_name' => $row['cardholder_name'] ?? '',
            'billing_address' => self::decodeJson($row['billing_address'] ?? null),
            'card_data' => self::decodeJson($row['card_data'] ?? null),
            'create_time' => $row['create_time'] ?? null,
            'update_time' => $row['update_time'] ?? null,
            'date_added' => $row['date_added'] ?? null,
            'last_modified' => $row['last_modified'] ?? null,
            'last_used' => $row['last_used'] ?? null,
        ];
    }
}
