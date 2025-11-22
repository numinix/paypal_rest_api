<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack, $configuration_group_id;

$targetVersion = '1.0.5';
$versionKey = 'NUMINIX_PPCP_VERSION';

$obsoleteKeys = [
    'NUMINIX_PPCP_BACKEND_URL' => 'Removed configurable proxy endpoint; onboarding now targets the hosted Numinix proxy automatically.',
    'NUMINIX_PPCP_PLUGIN_MODE' => 'Removed credential transfer mode; onboarding no longer persists API credentials to remote stores.',
];

foreach ($obsoleteKeys as $configKey => $notice) {
    $existing = $db->Execute(
        "SELECT configuration_id FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . zen_db_input($configKey) . "'"
        . " LIMIT 1"
    );

    if ($existing && !$existing->EOF) {
        $db->Execute(
            "DELETE FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = '" . zen_db_input($configKey) . "'"
            . " LIMIT 1"
        );

        if (isset($messageStack) && is_object($messageStack)) {
            $messageStack->add($notice, 'warning');
        }
    }
}

$versionEntry = $db->Execute(
    "SELECT configuration_id, configuration_group_id"
    . " FROM " . TABLE_CONFIGURATION
    . " WHERE configuration_key = '" . zen_db_input($versionKey) . "'"
    . " LIMIT 1"
);

if ($versionEntry && !$versionEntry->EOF) {
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION
        . " SET configuration_value = '" . zen_db_input($targetVersion) . "',"
        . " last_modified = NOW()"
        . " WHERE configuration_key = '" . zen_db_input($versionKey) . "'"
        . " LIMIT 1"
    );

    if (!isset($configuration_group_id) || (int) $configuration_group_id <= 0) {
        $configuration_group_id = (int) $versionEntry->fields['configuration_group_id'];
    }
} elseif (isset($configuration_group_id) && (int) $configuration_group_id > 0) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION
        . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
        . " VALUES ('Numinix PayPal ISU Version', '" . zen_db_input($versionKey) . "', '" . zen_db_input($targetVersion) . "', 'Installed version of the Numinix PayPal onboarding plugin.', " . (int) $configuration_group_id . ", 0, NOW())"
    );
}

if (isset($messageStack) && is_object($messageStack)) {
    $messageStack->add('Numinix PayPal onboarding updated to version ' . $targetVersion . '.', 'success');
}
