<?php 

// Configuration for ajax shipping quotes
if ( !defined('OPRC_SHOW_SHIPPING_METHOD_GROUP') ) {
    $db->Execute('INSERT IGNORE INTO ' . TABLE_CONFIGURATION . ' (configuration_id, configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
      (NULL, "Show shipping method in groups", "Features", "OPRC_SHOW_SHIPPING_METHOD_GROUP", "false", "Break shipping method in groups by showing group title instead of listing all options",  "' . $configuration_group_id . '", 100, NOW(), NULL, "zen_cfg_select_option(array(\'true\', \'false\'),")');
}

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'false' WHERE configuration_key = 'OPRC_COLLAPSE_DISCOUNTS'");