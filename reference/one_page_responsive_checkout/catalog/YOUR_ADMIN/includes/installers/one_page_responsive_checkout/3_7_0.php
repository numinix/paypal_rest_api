<?php
$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'OPRC_GA_ENABLED' LIMIT 1;");
$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'OPRC_GA_METHOD' LIMIT 1;");
$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'OPRC_SHOW_PRODUCT_IMAGES' LIMIT 1;");
$db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'OPRC_CONTACT_POSITION' LIMIT 1;");
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'true' WHERE configuration_key = 'OPRC_HIDEEMAIL_ALL' LIMIT 1;");
