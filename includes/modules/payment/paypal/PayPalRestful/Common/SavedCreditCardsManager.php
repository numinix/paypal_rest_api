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

namespace PayPalRestful\Common;

use function defined;

class SavedCreditCardsManager
{
    /**
     * Ensure the saved credit cards tables exist for backward compatibility.
     * 
     * These tables are used by legacy admin pages (paypalr_saved_card_recurring.php)
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
        
        self::ensureSkipNextPaymentColumn();
    }
    
    /**
     * Add skip_next_payment column if it doesn't exist.
     */
    private static function ensureSkipNextPaymentColumn(): void
    {
        global $db;
        
        // Check if column exists
        $result = $db->Execute(
            "SHOW COLUMNS FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " LIKE 'skip_next_payment'"
        );
        
        if ($result->RecordCount() == 0) {
            $db->Execute(
                "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " 
                ADD COLUMN skip_next_payment TINYINT(1) NOT NULL DEFAULT 0 
                AFTER status"
            );
        }
    }
}
