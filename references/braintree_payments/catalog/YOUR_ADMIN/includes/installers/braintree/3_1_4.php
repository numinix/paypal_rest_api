<?php

// Add missing columns if they don't exist
$expectedColumns = [
    'module_name' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN module_name varchar(256) NOT NULL DEFAULT ''",
    'first_name' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN first_name varchar(256) NOT NULL DEFAULT ''",
    'last_name' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN last_name varchar(256) NOT NULL DEFAULT ''",
    'payer_business_name' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN payer_business_name varchar(256) NOT NULL DEFAULT ''",
    'address_name' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_name varchar(256) NOT NULL DEFAULT ''",
    'address_street' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_street varchar(256) NOT NULL DEFAULT ''",
    'address_city' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_city varchar(256) NOT NULL DEFAULT ''",
    'address_state' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_state varchar(256) NOT NULL DEFAULT ''",
    'address_zip' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_zip varchar(256) NOT NULL DEFAULT ''",
    'address_country' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN address_country varchar(256) NOT NULL DEFAULT ''",
    'payer_email' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN payer_email varchar(256) NOT NULL DEFAULT ''",
    'payment_date' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN payment_date date NOT NULL DEFAULT '0000-00-00'",
    'settle_amount' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN settle_amount decimal(15,4) NOT NULL DEFAULT 0",
    'settle_currency' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN settle_currency varchar(10) NOT NULL DEFAULT ''",
    'module_mode' => "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN module_mode varchar(256) NOT NULL DEFAULT ''"
];

// Check existing columns
$existingColsQuery = $db->Execute("SHOW COLUMNS FROM " . TABLE_BRAINTREE);
$existingCols = [];
while (!$existingColsQuery->EOF) {
    $existingCols[] = $existingColsQuery->fields['Field'];
    $existingColsQuery->MoveNext();
}

// Add missing columns
foreach ($expectedColumns as $col => $alterSql) {
    if (!in_array($col, $existingCols)) {
        $db->Execute($alterSql);
    }
}

// Allow NULL on unused legacy columns if they exist
$legacyColumnsAllowNull = [
    'reason_code'      => "ALTER TABLE " . TABLE_BRAINTREE . " MODIFY COLUMN reason_code TEXT NULL DEFAULT NULL",
    'parent_txn_id'    => "ALTER TABLE " . TABLE_BRAINTREE . " MODIFY COLUMN parent_txn_id VARCHAR(256) NULL DEFAULT NULL",
    'exchange_rate'    => "ALTER TABLE " . TABLE_BRAINTREE . " MODIFY COLUMN exchange_rate DECIMAL(15,6) NULL DEFAULT NULL",
    'pending_reason'   => "ALTER TABLE " . TABLE_BRAINTREE . " MODIFY COLUMN pending_reason VARCHAR(256) NULL DEFAULT NULL",
    'num_cart_items'   => "ALTER TABLE " . TABLE_BRAINTREE . " MODIFY COLUMN num_cart_items INT(11) NULL DEFAULT NULL"
];

// Check existing columns
$existingColsQuery = $db->Execute("SHOW COLUMNS FROM " . TABLE_BRAINTREE);
$existingCols = [];
while (!$existingColsQuery->EOF) {
    $existingCols[] = $existingColsQuery->fields['Field'];
    $existingColsQuery->MoveNext();
}

// Modify legacy columns
foreach ($legacyColumnsAllowNull as $col => $alterSql) {
    if (in_array($col, $existingCols)) {
        $db->Execute($alterSql);
    }
}