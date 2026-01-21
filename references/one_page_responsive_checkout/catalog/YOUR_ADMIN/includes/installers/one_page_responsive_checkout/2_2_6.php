<?php
if ( !defined('OPRC_DEFAULT_BILLING_FOR_SHIPPING') ) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function, configuration_tab) VALUES
        ('Always Default to Billing Address for Shipping', 'OPRC_DEFAULT_BILLING_FOR_SHIPPING', 'false', 'If true, when starting new checkout it will use the default billing address for billing and shipping addresses', " . $configuration_group_id . ", 40, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),', 'Features');");
}

