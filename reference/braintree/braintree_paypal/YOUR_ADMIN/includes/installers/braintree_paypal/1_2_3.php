<?php
// Add the new Unpaid Order Status configuration
$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES ('Set Unpaid Order Status', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_UNPAID_STATUS_ID', '1', 'Set the status for orders that have been authorized but not yet captured (unpaid).', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
