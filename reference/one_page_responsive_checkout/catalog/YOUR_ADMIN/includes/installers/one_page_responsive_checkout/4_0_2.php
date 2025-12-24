<?php
/**
 * Installer for version 4.0.2 - Add debug mode configuration
 */
global $db, $sniffer, $configuration_group_id;

$groupId = isset($configuration_group_id) ? (int)$configuration_group_id : 0;
if ($groupId <= 0) {
    $groupLookup = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'One Page Responsive Checkout' LIMIT 1");
    if (!$groupLookup->EOF) {
        $groupId = (int)$groupLookup->fields['configuration_group_id'];
    }
}

if ($groupId > 0) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
        ('Debug Mode', 'Advanced', 'OPRC_DEBUG_MODE', 'false', 'Enable debug logging for One Page Responsive Checkout? When enabled, detailed checkout process logs will be written to the error log.', " . $groupId . ", 100, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')
    ");
}
