<?php

$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
                        ('Hide Email Options For All Users', 'Features', 'OPRC_HIDEEMAIL_ALL', '', 'Hide \"HTML/TEXT-Only\". Note: your default setting in Email Settings will be automatically selected', " . $configuration_group_id . ", 50, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),');");