<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack;

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
if (!$zc150) {
    return;
}

$configurationGroupTitle = 'Numinix PayPal Integrated Sign-up';
$configurationGroupDescription = 'Settings that control the Numinix PayPal onboarding experience.';

if (!isset($configuration_group_id) || (int) $configuration_group_id <= 0) {
    $groupQuery = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION_GROUP
        . " WHERE configuration_group_title = '" . zen_db_input($configurationGroupTitle) . "'"
        . " LIMIT 1"
    );

    if ($groupQuery && !$groupQuery->EOF) {
        $configuration_group_id = (int) $groupQuery->fields['configuration_group_id'];
    }
}

if (!isset($configuration_group_id) || (int) $configuration_group_id <= 0) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION_GROUP
        . " (configuration_group_title, configuration_group_description, sort_order, visible)"
        . " VALUES ('" . zen_db_input($configurationGroupTitle) . "', '" . zen_db_input($configurationGroupDescription) . "', 1, 1)"
    );

    $groupQuery = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION_GROUP
        . " WHERE configuration_group_title = '" . zen_db_input($configurationGroupTitle) . "'"
        . " LIMIT 1"
    );

    if ($groupQuery && !$groupQuery->EOF) {
        $configuration_group_id = (int) $groupQuery->fields['configuration_group_id'];
    }
}

if (!isset($configuration_group_id) || (int) $configuration_group_id <= 0) {
    return;
}

$db->Execute(
    "UPDATE " . TABLE_CONFIGURATION_GROUP
    . " SET sort_order = " . (int) $configuration_group_id
    . " WHERE configuration_group_id = " . (int) $configuration_group_id
    . " AND sort_order = 1"
);

$adminPageKey = 'configNuminixPayPalIsu';

if (!zen_page_key_exists($adminPageKey)) {
    zen_register_admin_page(
        $adminPageKey,
        'BOX_CONFIGURATION_NUMINIX_PAYPAL_ISU',
        'FILENAME_CONFIGURATION',
        'gID=' . (int) $configuration_group_id,
        'configuration',
        'Y',
        (int) $configuration_group_id,
        1
    );

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Enabled Numinix PayPal configuration menu.', 'success');
    }
}

if (!zen_page_key_exists('customerNuminixPaypalIsuSaveCreds')) {
    zen_register_admin_page(
        'customerNuminixPaypalIsuSaveCreds',
        'BOX_NUMINIX_PAYPAL_SAVE_CREDS',
        'FILENAME_DEFAULT',
        'cmd=numinix_paypalr_save_creds',
        'tools',
        'N',
        999,
        1
    );
}

if (!zen_page_key_exists('toolsNuminixPaypalIsuSignupLink')) {
    zen_register_admin_page(
        'toolsNuminixPaypalIsuSignupLink',
        'BOX_NUMINIX_PAYPAL_SIGNUP_LINK',
        'FILENAME_DEFAULT',
        'cmd=numinix_paypalr_request_signup_link',
        'tools',
        'N',
        998,
        1
    );
}

$configurationValues = [
    [
        'key' => 'NUMINIX_PPCP_ENVIRONMENT',
        'title' => 'Numinix PPCP Environment',
        'value' => 'sandbox',
        'description' => 'Determines whether the onboarding flow targets the PayPal sandbox or live environment.',
        'set_function' => "zen_cfg_select_option(array('sandbox','live'),",
        'sort_order' => 10,
    ],
    [
        'key' => 'NUMINIX_PPCP_BACKEND_URL',
        'title' => 'Numinix PPCP Backend URL',
        'value' => 'https://proxy.numinix.com/paypal',
        'description' => 'Base URL for communicating with the Numinix onboarding proxy service.',
        'sort_order' => 20,
    ],
    [
        'key' => 'NUMINIX_PPCP_FORCE_SSL',
        'title' => 'Numinix PPCP Force SSL',
        'value' => 'true',
        'description' => 'When enabled all onboarding routes require HTTPS and reject insecure AJAX calls.',
        'set_function' => "zen_cfg_select_option(array('true','false'),",
        'sort_order' => 30,
    ],
    [
        'key' => 'NUMINIX_PPCP_PLUGIN_MODE',
        'title' => 'Numinix PPCP Mode',
        'value' => 'plugin',
        'description' => 'Controls whether onboarding persists credentials automatically (plugin) or instructs manual retrieval (standalone).',
        'set_function' => "zen_cfg_select_option(array('plugin','standalone'),",
        'sort_order' => 40,
    ],
    [
        'key' => 'NUMINIX_PPCP_VERSION',
        'title' => 'Numinix PayPal ISU Version',
        'value' => '1.0.0',
        'description' => 'Tracks the installed version of the Numinix PayPal onboarding plugin.',
        'sort_order' => 50,
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
            . " sort_order = " . (int) $config['sort_order'] . ","
            . " set_function = '" . zen_db_input($config['set_function'] ?? '') . "'"
            . " WHERE configuration_key = '" . zen_db_input($key) . "'"
            . " LIMIT 1"
        );
    } else {
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, set_function)"
            . " VALUES ('" . zen_db_input($config['title']) . "', '" . zen_db_input($key) . "', '" . zen_db_input($config['value']) . "', '" . zen_db_input($config['description']) . "', " . (int) $configuration_group_id . ", " . (int) $config['sort_order'] . ", NOW(), '" . zen_db_input($config['set_function'] ?? '') . "')"
        );
    }
}
