<?php

// Added back merged cart message
if ( !defined('OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT') ) {
    $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_id, configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
                (NULL, "Show paypal option as a button on checkout", "Features", "OPRC_DISPLAY_PAYPAL_BUTTON_ON_CHECKOUT", "false", "Display Paypal payment option as a button if this configuration is set to true. Set One Page Checkout to True under General if this is set to true",  "' . $configuration_group_id . '", 30, NOW(), NULL, "zen_cfg_select_option(array(\'true\', \'false\'),")');
}