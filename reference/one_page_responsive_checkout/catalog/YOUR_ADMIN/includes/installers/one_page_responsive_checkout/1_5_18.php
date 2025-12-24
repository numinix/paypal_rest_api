<?php

$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
            ('Hide Welcome Message on Checkout', 'Features', 'OPRC_HIDE_WELCOME', 'false', 'Hide the welcome message on the checkout screen?', " . $configuration_group_id . ", 26, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),');");

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " set configuration_title = 'Enable Welcome Email' where configuration_key = 'OPRC_WELCOME_MESSAGE' LIMIT 1;");