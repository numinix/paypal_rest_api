<?php

$config_check = $db->Execute("SELECT configuration_id, configuration_key, configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'OPRC_EXPAND_GC'");
if ($config_check->EOF) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function, configuration_tab) VALUES
    ('Expand Gift Certificates', 'OPRC_EXPAND_GC', 'true', 'Should Gift Certificates display expanded always when customers have positive balance?', " . $configuration_group_id . ", 30, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),', 'Advanced')");
}