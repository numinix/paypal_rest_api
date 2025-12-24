<?php
// Fix to bug where addresses without phone numbers would use the phone number from the address before it.
// Used require_once for core classes
// Fix to bug where customer does not have default address
// Fix bug deleting address lets customers delete primary address
// Fix shipping session assignment for virtual products in oprc_updates
// Fix space between ID and Class for HTML validation
// Add Google Analytics universal tracking method

$db->Execute("update " . TABLE_CONFIGURATION . " set set_function = 'zen_cfg_select_option(array(\"default\", \"asynchronous\", \"universal\"),' where configuration_key = 'OPRC_GA_METHOD';");
