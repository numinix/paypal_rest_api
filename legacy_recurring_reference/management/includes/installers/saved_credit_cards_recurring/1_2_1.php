<?php
$configuration_group_id = 0;
$lookupKeys = array(
    'SAVED_CREDIT_CARDS_RECURRING_VERSION',
    'SAVED_CREDIT_CARDS_RECURRING_ENABLED'
);

foreach ($lookupKeys as $configKey) {
    $configuration = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $configKey . "'"
        . " LIMIT 1"
    );

    if ($configuration && isset($configuration->fields['configuration_group_id'])) {
        $configuration_group_id = (int) $configuration->fields['configuration_group_id'];
    }

    if ($configuration_group_id > 0) {
        break;
    }
}

$configKey = 'SAVED_CREDIT_CARDS_RECURRING_FAILURE_RECIPIENTS';
$legacyKey = 'MODULE_PAYMENT_PAYPALSAVEDCARD_FAILURE_EMAILS';

$currentConfig = $db->Execute(
    "SELECT configuration_id, configuration_value, configuration_group_id"
    . " FROM " . TABLE_CONFIGURATION
    . " WHERE configuration_key = '" . $configKey . "'"
    . " LIMIT 1"
);

if ($currentConfig && !$currentConfig->EOF) {
    if ($configuration_group_id <= 0 && isset($currentConfig->fields['configuration_group_id'])) {
        $configuration_group_id = (int) $currentConfig->fields['configuration_group_id'];
    }
} else {
    $legacyConfig = $db->Execute(
        "SELECT configuration_id, configuration_value, configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $legacyKey . "'"
        . " LIMIT 1"
    );

    $legacyValue = '';
    if ($legacyConfig && !$legacyConfig->EOF) {
        $legacyValue = trim($legacyConfig->fields['configuration_value']);
        if ($configuration_group_id <= 0 && isset($legacyConfig->fields['configuration_group_id'])) {
            $configuration_group_id = (int) $legacyConfig->fields['configuration_group_id'];
        }
    }

    if ($configuration_group_id > 0) {
        $escapedValue = $legacyValue;
        if (function_exists('zen_db_input')) {
            $escapedValue = zen_db_input($escapedValue);
        } else {
            $escapedValue = addslashes($escapedValue);
        }

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_group_id, configuration_key, configuration_title, configuration_value, configuration_description, sort_order, date_added)"
            . " VALUES ("
            . (int) $configuration_group_id
            . ", '" . $configKey . "', 'Failure Report Recipients', '" . $escapedValue . "', 'Comma separated list of email addresses that should receive the recurring payment failure-only report.', 20, NOW())"
        );
    }

    if (isset($legacyConfig) && $legacyConfig && !$legacyConfig->EOF) {
        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = '" . $legacyKey . "'"
            . " LIMIT 1"
        );
    }
}
