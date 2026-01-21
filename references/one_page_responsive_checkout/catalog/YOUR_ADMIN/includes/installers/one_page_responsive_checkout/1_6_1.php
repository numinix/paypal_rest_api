<?php
// Configuration for ajax shipping quotes
if ( !defined('OPRC_AJAX_SHIPPING_QUOTES') ) {
  $db->Execute('INSERT IGNORE INTO ' . TABLE_CONFIGURATION . ' (configuration_id, configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
    (NULL, "Ajax Load Shipping Quotes", "Features", "OPRC_AJAX_SHIPPING_QUOTES", "false", "Ajax load shipping quotes after page load for faster page speed.<br>Enable this configuration when shipping quotes from external sources slow down the page (e.g. FedEx, USPS ...).",  "' . $configuration_group_id . '", 41, NOW(), NULL, "zen_cfg_select_option(array(\'true\', \'false\'),")');
}

// Configuration for force billing address to be the same as shipping address
if ( !defined('OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING') ) {
  $db->Execute('INSERT IGNORE INTO ' . TABLE_CONFIGURATION . ' (configuration_id, configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
    (NULL, "Force Shipping Address To Billing", "Features", "OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING", "false", "Force billing address to be the same as shipping address.<br>(To prevent credit card fraud; not the best way, but better than none)",  "' . $configuration_group_id . '", 40, NOW(), NULL, "zen_cfg_select_option(array(\'true\', \'false\'),")');
}
