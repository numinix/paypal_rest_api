<?php
/**
 * Ensure the PayPal recurring profile cache table exists and has the expected schema.
 */

global $db;

if (!function_exists('sccr_installer_escape_value')) {
    function sccr_installer_escape_value($value)
    {
        if (function_exists('zen_db_input')) {
            return zen_db_input($value);
        }

        return addslashes($value);
    }
}

if (!function_exists('sccr_installer_quote_identifier')) {
    function sccr_installer_quote_identifier($identifier)
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('sccr_installer_like_escape')) {
    function sccr_installer_like_escape($value)
    {
        return addcslashes($value, "\\_%");
    }
}

$tableName = (defined('DB_PREFIX') ? DB_PREFIX : '') . 'paypal_recurring_profile_cache';
$quotedTable = sccr_installer_quote_identifier($tableName);

$likePattern = sccr_installer_escape_value(sccr_installer_like_escape($tableName));
$tableExists = false;
$tableCheck = $db->Execute("SHOW TABLES LIKE '" . $likePattern . "'");
if ($tableCheck && !$tableCheck->EOF) {
    $tableExists = true;
}

if (!$tableExists) {
    $db->Execute(
        'CREATE TABLE IF NOT EXISTS ' . $quotedTable . ' (
      `cache_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `customers_id` INT(10) UNSIGNED NOT NULL,
      `profile_id` VARCHAR(64) NOT NULL,
      `status` VARCHAR(64) DEFAULT NULL,
      `profile_source` VARCHAR(16) DEFAULT NULL,
      `preferred_gateway` VARCHAR(32) DEFAULT NULL,
      `profile_data` MEDIUMTEXT,
      `refreshed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`cache_id`),
      UNIQUE KEY `idx_paypal_profile_cache_customer` (`customers_id`, `profile_id`),
      KEY `idx_paypal_profile_cache_refreshed` (`refreshed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
} else {
    $columnsToEnsure = array(
        'status' => array(
            'definition' => 'VARCHAR(64) DEFAULT NULL',
            'position' => 'AFTER `profile_id`',
        ),
        'profile_source' => array(
            'definition' => 'VARCHAR(16) DEFAULT NULL',
            'position' => 'AFTER `status`',
        ),
        'preferred_gateway' => array(
            'definition' => 'VARCHAR(32) DEFAULT NULL',
            'position' => 'AFTER `profile_source`',
        ),
        'profile_data' => array(
            'definition' => 'MEDIUMTEXT',
            'position' => 'AFTER `preferred_gateway`',
        ),
        'refreshed_at' => array(
            'definition' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'position' => 'AFTER `profile_data`',
        ),
    );

    foreach ($columnsToEnsure as $column => $columnConfig) {
        $columnCheck = $db->Execute(
            'SHOW COLUMNS FROM ' . $quotedTable .
            " WHERE Field = '" . sccr_installer_escape_value($column) . "'"
        );

        if (!$columnCheck || $columnCheck->EOF) {
            $db->Execute(
                'ALTER TABLE ' . $quotedTable
                . ' ADD COLUMN ' . sccr_installer_quote_identifier($column)
                . ' ' . $columnConfig['definition']
                . ' ' . $columnConfig['position']
            );
        }
    }

    $indexesToEnsure = array(
        'idx_paypal_profile_cache_customer' => array(
            'definition' => 'UNIQUE KEY `idx_paypal_profile_cache_customer` (`customers_id`, `profile_id`)',
        ),
        'idx_paypal_profile_cache_refreshed' => array(
            'definition' => 'KEY `idx_paypal_profile_cache_refreshed` (`refreshed_at`)',
        ),
    );

    foreach ($indexesToEnsure as $indexName => $indexConfig) {
        $indexCheck = $db->Execute(
            'SHOW INDEX FROM ' . $quotedTable . " WHERE Key_name = '" . sccr_installer_escape_value($indexName) . "'"
        );

        if (!$indexCheck || $indexCheck->EOF) {
            $db->Execute('ALTER TABLE ' . $quotedTable . ' ADD ' . $indexConfig['definition']);
        }
    }
}
