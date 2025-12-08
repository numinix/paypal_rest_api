<?php
if (!defined('TABLE_BRAINTREE') || !isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
    return;
}

try {
    $column = $db->Execute("SHOW COLUMNS FROM " . TABLE_BRAINTREE . " LIKE 'pending_reason'");
    $exists = false;
    if (is_object($column)) {
        if (method_exists($column, 'RecordCount')) {
            $exists = ($column->RecordCount() > 0);
        } elseif (isset($column->fields) && is_array($column->fields) && count($column->fields) > 0) {
            $exists = true;
        }
    }
    if (!$exists) {
        $db->Execute("ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN pending_reason VARCHAR(255) NOT NULL DEFAULT ''");
    }
} catch (Exception $e) {
    if (function_exists('error_log')) {
        error_log('Braintree Google Pay installer: Unable to ensure pending_reason column - ' . $e->getMessage());
    }
}
