<?php

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack;

$versionKey = 'SAVED_CREDIT_CARDS_RECURRING_VERSION';
$installersPath = realpath(__DIR__ . '/../installers/saved_credit_cards_recurring');

if ($installersPath === false || !is_dir($installersPath)) {
    return;
}

$currentVersion = '0.0.0';
$configurationGroupId = 0;

$versionConfig = $db->Execute(
    "SELECT configuration_value, configuration_group_id"
    . " FROM " . TABLE_CONFIGURATION
    . " WHERE configuration_key = '" . $versionKey . "'"
    . " LIMIT 1"
);

if ($versionConfig && !$versionConfig->EOF) {
    $currentVersion = trim($versionConfig->fields['configuration_value']);
    $configurationGroupId = (int) $versionConfig->fields['configuration_group_id'];
}

if ($currentVersion === '') {
    $currentVersion = '0.0.0';
}

if ($configurationGroupId <= 0) {
    $groupLookup = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_ENABLED'"
        . " LIMIT 1"
    );

    if ($groupLookup && !$groupLookup->EOF) {
        $configurationGroupId = (int) $groupLookup->fields['configuration_group_id'];
    }
}

$installerFiles = glob($installersPath . '/*.php');

if (!is_array($installerFiles) || count($installerFiles) === 0) {
    return;
}

natsort($installerFiles);

foreach ($installerFiles as $installerFile) {
    $installerVersion = str_replace('_', '.', basename($installerFile, '.php'));

    if (version_compare($currentVersion, $installerVersion, '>=')) {
        continue;
    }

    $configuration_group_id = $configurationGroupId;

    include $installerFile;

    $currentVersion = $installerVersion;

    $groupRefresh = $db->Execute(
        "SELECT configuration_value, configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $versionKey . "'"
        . " LIMIT 1"
    );

    if ($groupRefresh && !$groupRefresh->EOF) {
        $configurationGroupId = (int) $groupRefresh->fields['configuration_group_id'];
    } elseif ($configurationGroupId <= 0) {
        $groupRefresh = $db->Execute(
            "SELECT configuration_group_id"
            . " FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_ENABLED'"
            . " LIMIT 1"
        );

        if ($groupRefresh && !$groupRefresh->EOF) {
            $configurationGroupId = (int) $groupRefresh->fields['configuration_group_id'];
        }
    }

    $versionEntry = $db->Execute(
        "SELECT configuration_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $versionKey . "'"
        . " LIMIT 1"
    );

    if ($versionEntry && !$versionEntry->EOF) {
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION
            . " SET configuration_value = '" . $currentVersion . "', last_modified = NOW()"
            . " WHERE configuration_key = '" . $versionKey . "'"
            . " LIMIT 1"
        );
    } elseif ($configurationGroupId > 0) {
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
            . " VALUES ('Saved Credit Cards Recurring Version', '" . $versionKey . "', '" . $currentVersion . "', 'Installed version of the Saved Credit Cards Recurring plugin.', " . (int) $configurationGroupId . ", 0, NOW())"
        );
    }

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Saved Credit Cards Recurring updated to version ' . $currentVersion . '.', 'success');
    }
}
