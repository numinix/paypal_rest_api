<?php
/**
 * Upgrade script to add product metadata and subscription context to saved recurring credit cards.
 */

global $db;

$tableName = 'numinix_saved_credit_cards_recurring';

$columnsToAdd = [
    'products_id' => [
        'definition' => 'INT(11) NULL DEFAULT NULL',
        'position' => 'AFTER saved_credit_card_id',
    ],
    'products_name' => [
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL',
        'position' => 'AFTER products_id',
    ],
    'products_model' => [
        'definition' => 'VARCHAR(64) NULL DEFAULT NULL',
        'position' => 'AFTER products_name',
    ],
    'currency_code' => [
        'definition' => "CHAR(3) NULL DEFAULT NULL",
        'position' => 'AFTER products_model',
    ],
    'billing_period' => [
        'definition' => 'VARCHAR(32) NULL DEFAULT NULL',
        'position' => 'AFTER currency_code',
    ],
    'billing_frequency' => [
        'definition' => 'INT(11) NULL DEFAULT NULL',
        'position' => 'AFTER billing_period',
    ],
    'total_billing_cycles' => [
        'definition' => 'INT(11) NULL DEFAULT NULL',
        'position' => 'AFTER billing_frequency',
    ],
    'domain' => [
        'definition' => 'VARCHAR(255) NULL DEFAULT NULL',
        'position' => 'AFTER total_billing_cycles',
    ],
    'subscription_attributes_json' => [
        'definition' => 'LONGTEXT NULL',
        'position' => 'AFTER domain',
    ],
];

foreach ($columnsToAdd as $column => $columnConfig) {
    $columnCheck = $db->Execute("SHOW COLUMNS FROM {$tableName} LIKE '" . $column . "'");

    if (!$columnCheck || $columnCheck->EOF) {
        $db->Execute(
            'ALTER TABLE ' . $tableName
            . ' ADD ' . $column . ' ' . $columnConfig['definition']
            . ' ' . $columnConfig['position']
        );
    }
}

$originalOrdersProductsColumn = $db->Execute("SHOW COLUMNS FROM {$tableName} LIKE 'original_orders_products_id'");

if ($originalOrdersProductsColumn && !$originalOrdersProductsColumn->EOF) {
    $allowsNull = (isset($originalOrdersProductsColumn->fields['Null']) && strtolower($originalOrdersProductsColumn->fields['Null']) === 'yes');

    if (!$allowsNull) {
        $db->Execute('ALTER TABLE ' . $tableName . ' MODIFY original_orders_products_id INT(11) NULL DEFAULT NULL');
    }
}

$indexesToAdd = [
    'idx_nscr_products_id' => [
        'columns' => 'products_id',
        'type' => 'INDEX',
    ],
    'idx_nscr_domain' => [
        'columns' => 'domain',
        'type' => 'INDEX',
    ],
    'idx_nscr_subscription_attributes_json' => [
        'columns' => 'subscription_attributes_json',
        'type' => 'FULLTEXT',
    ],
];

foreach ($indexesToAdd as $indexName => $indexConfig) {
    $indexCheck = $db->Execute('SHOW INDEX FROM ' . $tableName . " WHERE Key_name = '" . $indexName . "'");

    if (!$indexCheck || $indexCheck->EOF) {
        $db->Execute(
            'ALTER TABLE ' . $tableName
            . ' ADD ' . $indexConfig['type']
            . ' ' . $indexName . ' (' . $indexConfig['columns'] . ')'
        );
    }
}
