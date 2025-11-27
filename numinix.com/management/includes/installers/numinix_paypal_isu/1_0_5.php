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

$configuration_group_id = (int) ($configuration_group_id ?? 0);
if ($configuration_group_id <= 0 && $versionEntry && !$versionEntry->EOF) {
    $configuration_group_id = (int) $versionEntry->fields['configuration_group_id'];
}

if ($configuration_group_id > 0) {
    $configurationValues = [
        [
            'key' => 'NUMINIX_PPCP_SANDBOX_PARTNER_REFERRAL_LINK',
            'title' => 'Stored Sandbox Partner Referral Link',
            'value' => '',
            'description' => 'Caches the reusable PayPal onboarding URL generated for sandbox mode.',
            'sort_order' => 95,
        ],
        [
            'key' => 'NUMINIX_PPCP_LIVE_PARTNER_REFERRAL_LINK',
            'title' => 'Stored Live Partner Referral Link',
            'value' => '',
            'description' => 'Caches the reusable PayPal onboarding URL generated for live (production) mode.',
            'sort_order' => 96,
        ],
    ];

    foreach ($configurationValues as $config) {
        $key = (string) $config['key'];
        $check = $db->Execute(
            "SELECT configuration_id"
            . " FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = '" . zen_db_input($key) . "'"
            . " LIMIT 1"
        );

        if ($check && !$check->EOF) {
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION
                . " SET configuration_title = '" . zen_db_input($config['title']) . "',"
                . " configuration_description = '" . zen_db_input($config['description']) . "',"
                . " configuration_group_id = " . (int) $configuration_group_id . ","
                . " sort_order = " . (int) $config['sort_order']
                . " WHERE configuration_key = '" . zen_db_input($key) . "'"
                . " LIMIT 1"
            );
        } else {
            $db->Execute(
                "INSERT INTO " . TABLE_CONFIGURATION
                . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
                . " VALUES ('" . zen_db_input($config['title']) . "', '" . zen_db_input($key) . "', '" . zen_db_input($config['value']) . "', '" . zen_db_input($config['description']) . "', " . (int) $configuration_group_id . ", " . (int) $config['sort_order'] . ", NOW())"
            );
        }
    }
}

if (isset($messageStack) && is_object($messageStack)) {
    $messageStack->add('Numinix PayPal onboarding updated to version ' . $targetVersion . '.', 'success');
}
