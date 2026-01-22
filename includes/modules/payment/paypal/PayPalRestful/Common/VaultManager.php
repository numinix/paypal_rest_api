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
                visible TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (paypal_vault_id),
                UNIQUE KEY idx_paypal_vault_id (vault_id),
                KEY idx_paypal_vault_customer (customers_id),
                KEY idx_paypal_vault_status (customers_id, status)
            )"
        );
        
        // Add visible column to existing tables if it doesn't exist
        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_PAYPAL_VAULT . " LIKE 'visible'"
        );
        if ($result->EOF) {
            $db->Execute(
                "ALTER TABLE " . TABLE_PAYPAL_VAULT . " 
                 ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1"
            );
        }
    }

    /**
     * Store or update a vaulted card for the supplied customer/order combination.
     *
     * @param int   $customers_id The Zen Cart customer's identifier.
     * @param int   $orders_id    The order identifier that produced the vault token. 
     *                             Use 0 for cards added directly without a purchase (e.g., from account management page).
     *                             Cards with orders_id=0 are available for future transactions but not linked to a specific order.
     * @param array $cardSource   The card payment_source element returned by PayPal.
     * @param bool  $visible      Whether the card should be visible in checkout/account (default: true for backward compatibility).
     *
     * @return array|null The stored record as an associative array or null if nothing was saved.
     */
    public static function saveVaultedCard(int $customers_id, int $orders_id, array $cardSource, bool $visible = true): ?array
    {
        if ($customers_id <= 0 || $orders_id < 0) {
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
            'visible' => $visible ? 1 : 0,
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

        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle race conditions at the database level
        // This avoids the duplicate key error that occurs when multiple processes try to insert
        // the same vault_id simultaneously.
        $sqlData['date_added'] = $now;
        
        // Build the INSERT query
        $columns = array_keys($sqlData);
        $values = array_map(function($col) use ($sqlData) {
            $val = $sqlData[$col];
            if ($val === null) {
                return 'NULL';
            }
            return "'" . zen_db_input($val) . "'";
        }, $columns);
        
        // Build the ON DUPLICATE KEY UPDATE clause
        // Exclude date_added from updates since we want to preserve the original value
        $updateClauses = [];
        foreach ($sqlData as $col => $val) {
            if ($col !== 'date_added') {
                if ($val === null) {
                    $updateClauses[] = "`$col` = NULL";
                } else {
                    $updateClauses[] = "`$col` = '" . zen_db_input($val) . "'";
                }
            }
        }
        
        // Build and execute the INSERT ... ON DUPLICATE KEY UPDATE query
        $columnList = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
        $valueList = implode(', ', $values);
        $updateList = implode(', ', $updateClauses);
        
        $insertSql = "INSERT INTO " . TABLE_PAYPAL_VAULT . " ($columnList)
                      VALUES ($valueList)
                      ON DUPLICATE KEY UPDATE $updateList";
        
        $db->Execute($insertSql);

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
     * Update a stored vaulted card from a PayPal vault token response.
     */
    public static function updateFromVaultToken(int $customers_id, int $paypal_vault_id, array $token): ?array
    {
        if ($customers_id <= 0 || $paypal_vault_id <= 0) {
            return null;
        }

        $paymentSource = $token['payment_source']['card'] ?? null;
        if (!is_array($paymentSource)) {
            return null;
        }

        $vaultMeta = [
            'id' => self::sanitizeString($token['id'] ?? '', 64),
            'status' => self::sanitizeString($token['status'] ?? '', 32),
        ];

        if (isset($token['customer_id'])) {
            $vaultMeta['customer']['id'] = self::sanitizeString($token['customer_id'], 64);
        }
        if (isset($token['payer_id'])) {
            $vaultMeta['customer']['payer_id'] = self::sanitizeString($token['payer_id'], 64);
        }
        if (isset($token['customer']) && is_array($token['customer'])) {
            if (isset($token['customer']['id'])) {
                $vaultMeta['customer']['id'] = self::sanitizeString($token['customer']['id'], 64);
            }
            if (isset($token['customer']['payer_id'])) {
                $vaultMeta['customer']['payer_id'] = self::sanitizeString($token['customer']['payer_id'], 64);
            }
        }

        if (isset($token['update_time'])) {
            $vaultMeta['update_time'] = $token['update_time'];
        } elseif (isset($token['time_updated'])) {
            $vaultMeta['update_time'] = $token['time_updated'];
        }

        if (isset($token['create_time'])) {
            $vaultMeta['create_time'] = $token['create_time'];
        } elseif (isset($token['time_created'])) {
            $vaultMeta['create_time'] = $token['time_created'];
        }

        return self::applyCardUpdate($customers_id, $paypal_vault_id, $paymentSource, $vaultMeta);
    }

    /**
     * Apply card updates to an existing vault record using normalized card and vault metadata.
     *
     * @param array<string,mixed> $cardSource
     * @param array<string,mixed> $vaultMeta
     */
    public static function applyCardUpdate(int $customers_id, int $paypal_vault_id, array $cardSource, array $vaultMeta = []): ?array
    {
        if ($customers_id <= 0 || $paypal_vault_id <= 0) {
            return null;
        }

        self::ensureSchema();

        global $db;

        $existing = $db->Execute(
            "SELECT paypal_vault_id" .
            "   FROM " . TABLE_PAYPAL_VAULT .
            "  WHERE paypal_vault_id = " . (int)$paypal_vault_id .
            "    AND customers_id = " . (int)$customers_id .
            "  LIMIT 1"
        );

        if (!is_object($existing) || $existing->EOF) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        $sqlData = [
            'last_modified' => $now,
        ];

        $status = self::sanitizeString($vaultMeta['status'] ?? '', 32);
        if ($status !== '') {
            $sqlData['status'] = strtoupper($status);
        }

        $brand = self::sanitizeString($cardSource['brand'] ?? '', 32);
        if ($brand !== '') {
            $sqlData['brand'] = $brand;
        }

        $lastDigits = $cardSource['last_digits'] ?? ($cardSource['number'] ?? '');
        if ($lastDigits !== '') {
            $sqlData['last_digits'] = self::sanitizeString(substr($lastDigits, -4), 4);
        }

        $cardType = self::sanitizeString($cardSource['type'] ?? '', 32);
        if ($cardType !== '') {
            $sqlData['card_type'] = $cardType;
        }

        $expiry = self::sanitizeString($cardSource['expiry'] ?? '', 7);
        if ($expiry !== '') {
            $sqlData['expiry'] = $expiry;
        }

        $cardholder = self::sanitizeString($cardSource['name'] ?? '', 96);
        if ($cardholder !== '') {
            $sqlData['cardholder_name'] = $cardholder;
        }

        if (isset($vaultMeta['customer']['payer_id'])) {
            $payerId = self::sanitizeString($vaultMeta['customer']['payer_id'], 64);
            if ($payerId !== '') {
                $sqlData['payer_id'] = $payerId;
            }
        }

        if (isset($vaultMeta['customer']['id'])) {
            $customerId = self::sanitizeString($vaultMeta['customer']['id'], 64);
            if ($customerId !== '') {
                $sqlData['paypal_customer_id'] = $customerId;
            }
        }

        if (array_key_exists('billing_address', $cardSource)) {
            $billingAddress = self::encodeJson($cardSource['billing_address']);
            if ($billingAddress !== null) {
                $sqlData['billing_address'] = $billingAddress;
            }
        }

        $cardData = $cardSource;
        if (!empty($vaultMeta)) {
            $cardData['vault'] = $vaultMeta;
        }
        $encodedCardData = self::encodeJson($cardData);
        if ($encodedCardData !== null) {
            $sqlData['card_data'] = $encodedCardData;
        }

        $createTime = self::convertPayPalDate($vaultMeta['create_time'] ?? null);
        if ($createTime !== null) {
            $sqlData['create_time'] = $createTime;
        }

        $updateTime = self::convertPayPalDate($vaultMeta['update_time'] ?? null);
        if ($updateTime !== null) {
            $sqlData['update_time'] = $updateTime;
            $sqlData['last_used'] = $updateTime;
        }

        zen_db_perform(
            TABLE_PAYPAL_VAULT,
            $sqlData,
            'update',
            'paypal_vault_id = ' . (int)$paypal_vault_id . ' AND customers_id = ' . (int)$customers_id
        );

        $stored = $db->Execute(
            "SELECT *" .
            "   FROM " . TABLE_PAYPAL_VAULT .
            "  WHERE paypal_vault_id = " . (int)$paypal_vault_id .
            "    AND customers_id = " . (int)$customers_id .
            "  LIMIT 1"
        );

        if (!is_object($stored) || $stored->EOF) {
            return null;
        }

        return self::mapRow($stored->fields);
    }

    /**
     * Retrieve a customer's vaulted cards.
     *
     * @param int  $customers_id The Zen Cart customer's identifier.
     * @param bool $activeOnly   When true (default), limit to active/approved vault entries, exclude expired cards, and only return visible cards.
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
            $whereClause .= " AND status IN ('ACTIVE','APPROVED','VAULTED') AND visible = 1";
        }

        $vaultedCards = [];
        $records = $db->Execute(
            "SELECT *
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE $whereClause
           ORDER BY last_modified DESC"
        );

        if (is_object($records)) {
            while (!$records->EOF) {
                $card = self::mapRow($records->fields);
                // When activeOnly is true (checkout context), exclude expired cards
                // When activeOnly is false (account context), include all cards for updating
                if (!$activeOnly || !self::isCardExpired($card['expiry'])) {
                    $vaultedCards[] = $card;
                }
                $records->MoveNext();
            }
        }

        return $vaultedCards;
    }

    /**
     * Retrieve a single vaulted card for the supplied customer.
     */
    public static function getCustomerVaultCard(int $customers_id, int $paypal_vault_id): ?array
    {
        if ($customers_id <= 0 || $paypal_vault_id <= 0) {
            return null;
        }

        self::ensureSchema();

        global $db;

        $record = $db->Execute(
            "SELECT *
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE paypal_vault_id = " . (int)$paypal_vault_id .
            " AND customers_id = " . (int)$customers_id .
            " LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            return null;
        }

        return self::mapRow($record->fields);
    }

    /**
     * Remove a vaulted card for the supplied customer.
     */
    public static function deleteCustomerVaultCard(int $customers_id, int $paypal_vault_id): bool
    {
        if ($customers_id <= 0 || $paypal_vault_id <= 0) {
            return false;
        }

        self::ensureSchema();

        global $db;

        $record = $db->Execute(
            "SELECT paypal_vault_id
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE paypal_vault_id = " . (int)$paypal_vault_id .
            " AND customers_id = " . (int)$customers_id .
            " LIMIT 1"
        );

        if (!is_object($record) || $record->EOF) {
            return false;
        }

        $db->Execute(
            "DELETE
               FROM " . TABLE_PAYPAL_VAULT . "
              WHERE paypal_vault_id = " . (int)$paypal_vault_id .
            " AND customers_id = " . (int)$customers_id .
            " LIMIT 1"
        );

        return true;
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
     * Check if a card has expired based on its expiry date.
     *
     * @param string $expiry Card expiry in YYYY-MM format (e.g., "2030-09")
     * @return bool True if the card is expired, false otherwise
     */
    protected static function isCardExpired(string $expiry): bool
    {
        if ($expiry === '') {
            return false;
        }

        // Parse YYYY-MM format
        if (!preg_match('/^(\d{4})-(\d{2})$/', $expiry, $matches)) {
            return false;
        }

        $expiryYear = (int)$matches[1];
        $expiryMonth = (int)$matches[2];

        // Get current year and month
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        // Card is expired if the expiry date is before the current month
        if ($expiryYear < $currentYear) {
            return true;
        }

        if ($expiryYear === $currentYear && $expiryMonth < $currentMonth) {
            return true;
        }

        return false;
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
            'visible' => (bool)($row['visible'] ?? true),
        ];
    }
}
