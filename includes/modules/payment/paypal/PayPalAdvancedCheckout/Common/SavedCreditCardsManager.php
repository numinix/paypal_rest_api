<?php
/**
 * SavedCreditCardsManager.php provides schema management for legacy saved credit cards tables.
 *
 * These tables support legacy payment modules and admin pages that manage saved credit cards
 * and their associated recurring payments. For new implementations, use VaultManager and
 * SubscriptionManager instead.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

use function defined;
use function zen_db_input;

class SavedCreditCardsManager
{
    /**
     * Ensure the saved credit cards tables exist for backward compatibility.
     * 
     * This method is automatically called by the paypalac.php payment module during:
     * - Upgrade to version 1.3.6 or later (via tableCheckup() method)
     * - Fresh installations (runs as part of all upgrade cases in tableCheckup())
     * 
     * These tables are used by legacy admin pages (paypalac_saved_card_recurring.php)
     * and older payment modules. For sites using the new PayPal REST API exclusively,
     * data is stored in paypal_vault and paypal_subscriptions tables instead.
     */
    public static function ensureSchema(): void
    {
        self::ensureSavedCreditCardsTable();
        self::ensureSavedCreditCardsRecurringTable();
    }

    /**
     * Create the saved_credit_cards table if it doesn't exist.
     */
    private static function ensureSavedCreditCardsTable(): void
    {
        defined('TABLE_SAVED_CREDIT_CARDS') or define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');

        global $db;

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_SAVED_CREDIT_CARDS . " (
                saved_credit_card_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                customers_id INT UNSIGNED NOT NULL DEFAULT 0,
                type VARCHAR(32) NOT NULL DEFAULT '',
                last_digits VARCHAR(4) NOT NULL DEFAULT '',
                expiry_month CHAR(2) NOT NULL DEFAULT '',
                expiry_year CHAR(4) NOT NULL DEFAULT '',
                holder_name VARCHAR(96) NOT NULL DEFAULT '',
                billing_address_id INT UNSIGNED NOT NULL DEFAULT 0,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                vault_id VARCHAR(64) NOT NULL DEFAULT '',
                date_added DATETIME DEFAULT NULL,
                last_modified DATETIME DEFAULT NULL,
                PRIMARY KEY (saved_credit_card_id),
                KEY idx_customer (customers_id),
                KEY idx_vault_id (vault_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Create the saved_credit_cards_recurring table if it doesn't exist.
     */
    private static function ensureSavedCreditCardsRecurringTable(): void
    {
        defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') or define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');

        global $db;

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " (
                saved_credit_card_recurring_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                saved_credit_card_id INT UNSIGNED NOT NULL DEFAULT 0,
                customers_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_id INT UNSIGNED NOT NULL DEFAULT 0,
                orders_products_id INT UNSIGNED NOT NULL DEFAULT 0,
                products_id INT UNSIGNED NOT NULL DEFAULT 0,
                products_name VARCHAR(255) NOT NULL DEFAULT '',
                products_model VARCHAR(255) NOT NULL DEFAULT '',
                billing_period VARCHAR(16) NOT NULL DEFAULT '',
                billing_frequency SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                total_billing_cycles SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                amount DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                currency_code CHAR(3) NOT NULL DEFAULT '',
                status VARCHAR(32) NOT NULL DEFAULT '',
                profile_id VARCHAR(64) NOT NULL DEFAULT '',
                next_payment_date DATE DEFAULT NULL,
                subscription_attributes_json TEXT,
                date_added DATETIME DEFAULT NULL,
                last_modified DATETIME DEFAULT NULL,
                PRIMARY KEY (saved_credit_card_recurring_id),
                KEY idx_card (saved_credit_card_id),
                KEY idx_customer (customers_id),
                KEY idx_orders (orders_id),
                KEY idx_status (status),
                KEY idx_profile_id (profile_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::ensureLegacyColumns();
    }

    /**
     * Add legacy columns that may be needed for backward compatibility.
     * 
     * These columns are not included in the base CREATE TABLE statement so that
     * new installations have a minimal schema. Sites upgrading from legacy payment
     * modules get these columns added automatically during the upgrade process.
     * 
     * Legacy columns supported:
     * - domain: Used by some legacy implementations to track subscription domains
     * - comments: Used by legacy admin pages to append payment history notes
     * - billing_*: Billing address fields for subscription independence
     * - shipping_*: Shipping method and cost captured at subscription creation
     */
    private static function ensureLegacyColumns(): void
    {
        global $db;

        $columns = [
            'domain' => "VARCHAR(255) NOT NULL DEFAULT ''",
            'comments' => "TEXT",
            'is_archived' => "TINYINT(1) NOT NULL DEFAULT 0",
            'billing_name' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing contact name'",
            'billing_company' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing company name'",
            'billing_street_address' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing street address'",
            'billing_suburb' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing suburb/address line 2'",
            'billing_city' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing city'",
            'billing_state' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing state/province'",
            'billing_postcode' => "VARCHAR(255) DEFAULT NULL COMMENT 'Billing postal code'",
            'billing_country_id' => "INT(11) DEFAULT NULL COMMENT 'Billing country ID (FK to countries table)'",
            'billing_country_code' => "CHAR(2) DEFAULT NULL COMMENT 'Billing country ISO code (CA, US, etc.)'",
            'shipping_method' => "VARCHAR(255) DEFAULT NULL COMMENT 'Shipping method name'",
            'shipping_cost' => "DECIMAL(15,4) DEFAULT NULL COMMENT 'Shipping cost at time of order'",
        ];

        foreach ($columns as $column => $definition) {
            if (!self::columnExists($column)) {
                $db->Execute(
                    'ALTER TABLE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' ADD ' . $column . ' ' . $definition
                );
            }
        }
    }

    /**
     * Check if a column exists in the saved_credit_cards_recurring table.
     */
    private static function columnExists(string $column): bool
    {
        global $db;

        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " LIKE '" . zen_db_input($column) . "'"
        );

        return ($result instanceof \queryFactoryResult && $result->RecordCount() > 0);
    }
}
