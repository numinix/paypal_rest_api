<?php
/**
 * Executes versioned installers for the Numinix PayPal signup utilities.
 */
if (!defined('IS_ADMIN_FLAG')) {
    return;
}

global $db, $messageStack;

$versionKey = 'NUMINIX_PPCP_VERSION';

// Use Zen Cart's admin directory constant to locate installers
if (defined('DIR_FS_ADMIN')) {
    $installersPath = DIR_FS_ADMIN . 'includes/installers/numinix_paypal_isu';
} else {
    // Fallback for environments where DIR_FS_ADMIN is not defined
    $installersPath = __DIR__ . '/../installers/numinix_paypal_isu';
}

if (!is_dir($installersPath)) {
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
    $currentVersion = trim((string) $versionConfig->fields['configuration_value']);
    $configurationGroupId = (int) $versionConfig->fields['configuration_group_id'];
}

if ($currentVersion === '') {
    $currentVersion = '0.0.0';
}

if ($configurationGroupId <= 0) {
    $groupLookup = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = 'NUMINIX_PPCP_ENVIRONMENT'"
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
            . " WHERE configuration_key = 'NUMINIX_PPCP_ENVIRONMENT'"
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
            . " SET configuration_value = '" . zen_db_input($currentVersion) . "', last_modified = NOW()"
            . " WHERE configuration_key = '" . $versionKey . "'"
            . " LIMIT 1"
        );
    } elseif ($configurationGroupId > 0) {
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
            . " VALUES ('Numinix PayPal ISU Version', '" . $versionKey . "', '" . zen_db_input($currentVersion) . "', 'Installed version of the Numinix PayPal onboarding plugin.', " . (int) $configurationGroupId . ", 0, NOW())"
        );
    }

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Numinix PayPal onboarding updated to version ' . $currentVersion . '.', 'success');
    }
}
