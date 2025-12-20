<?php
/**
 * Numinix PayPal ISU Installer - Version 1.0.8
 *
 * Adds configuration entries for the PayPal partner merchant IDs (sandbox and live).
 * These identifiers are required when calling PayPal's credentials endpoint to
 * retrieve seller REST API credentials during the ISU flow.
 *
 * Note: Version number updates are handled automatically by init_numinix_paypal_isu.php
 * after each installer file runs.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack, $configuration_group_id;

$configuration_group_id = (int) ($configuration_group_id ?? 0);
if ($configuration_group_id <= 0) {
    $groupLookup = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . zen_db_input('NUMINIX_PPCP_ENVIRONMENT') . "'"
        . " LIMIT 1"
    );

    if ($groupLookup && !$groupLookup->EOF) {
        $configuration_group_id = (int) $groupLookup->fields['configuration_group_id'];
    }
}

if ($configuration_group_id > 0) {
    $configurationValues = [
        [
            'key' => 'NUMINIX_PPCP_SANDBOX_PARTNER_MERCHANT_ID',
            'title' => 'Sandbox Partner Merchant ID',
            'value' => '',
            'description' => 'PayPal sandbox partner merchant ID (Payer ID) used to fetch seller API credentials.',
            'sort_order' => 65,
        ],
        [
            'key' => 'NUMINIX_PPCP_LIVE_PARTNER_MERCHANT_ID',
            'title' => 'Live Partner Merchant ID',
            'value' => '',
            'description' => 'PayPal live partner merchant ID (Payer ID) used to fetch seller API credentials.',
            'sort_order' => 85,
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
            $updateFields = "configuration_title = '" . zen_db_input($config['title']) . "',"
                . " configuration_description = '" . zen_db_input($config['description']) . "',"
                . " configuration_group_id = " . (int) $configuration_group_id . ","
                . " sort_order = " . (int) $config['sort_order']
                . ", set_function = '" . zen_db_input($config['set_function'] ?? '') . "'";

            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION
                . " SET " . $updateFields
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
}
