<?php
/**
 * Database upgrade: Add billing address fields to saved_credit_cards_recurring table
 *
 * This init script runs once to add billing address columns to the subscription table,
 * making subscriptions independent with their own stored billing address.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Only run this upgrade once
$upgrade_check_query = "SHOW COLUMNS FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " LIKE 'billing_country_code'";
$check = $db->Execute($upgrade_check_query);

if ($check->RecordCount() == 0) {
    // Columns don't exist yet, add them
    $messageStack->add('Adding subscription billing address columns...', 'success');
    
    $alterQueries = array(
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_name VARCHAR(255) DEFAULT NULL COMMENT 'Billing contact name'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_company VARCHAR(255) DEFAULT NULL COMMENT 'Billing company name'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_street_address VARCHAR(255) DEFAULT NULL COMMENT 'Billing street address'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_suburb VARCHAR(255) DEFAULT NULL COMMENT 'Billing suburb/address line 2'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_city VARCHAR(255) DEFAULT NULL COMMENT 'Billing city'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_state VARCHAR(255) DEFAULT NULL COMMENT 'Billing state/province'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_postcode VARCHAR(255) DEFAULT NULL COMMENT 'Billing postal code'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_country_id INT(11) DEFAULT NULL COMMENT 'Billing country ID'",
        "ALTER TABLE " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " ADD COLUMN billing_country_code CHAR(2) DEFAULT NULL COMMENT 'Billing country ISO code'"
    );
    
    foreach ($alterQueries as $sql) {
        $db->Execute($sql);
    }
    
    $messageStack->add('✓ Subscription billing address columns added successfully', 'success');
}
