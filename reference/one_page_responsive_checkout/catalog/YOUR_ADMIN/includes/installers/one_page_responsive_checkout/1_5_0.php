<?php
// Updated jquery to use minified version of jquery-migrate v1.2.1
// Added backwards compatibility to security patch

// Add configuration for Simplified Header
if (!defined('OPRC_SIMPLIFIED_HEADER_ENABLED')){
	$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function, configuration_tab) VALUES
		('Enable Simplified Header', 'OPRC_SIMPLIFIED_HEADER_ENABLED', 'false', 'If enabled, display only website\' logo in header in checkout.<br>(This configuration needs extra code edit action, see Documentation.)', " . $configuration_group_id . ", 27, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),', 'Layout')");
}
