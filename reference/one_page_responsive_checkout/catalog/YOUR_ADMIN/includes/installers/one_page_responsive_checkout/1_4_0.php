<?php

$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
                        ('reCAPTCHA Site key', 'Features', 'OPRC_RECAPTCHA_KEY', '', 'Enter the code provided from the Google reCAPTCHA website', " . $configuration_group_id . ", 46, NOW(), NOW(), NULL, NULL);"); 

$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
                        ('reCAPTCHA Secret key', 'Features', 'OPRC_RECAPTCHA_SECRET', '', 'Enter the code provided from the Google reCAPTCHA website', " . $configuration_group_id . ", 46, NOW(), NOW(), NULL, NULL);"); 

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'light', set_function = 'zen_cfg_select_option(array(\"light\", \"dark\"),' WHERE configuration_key = 'OPRC_RECAPTCHA_THEME';");
