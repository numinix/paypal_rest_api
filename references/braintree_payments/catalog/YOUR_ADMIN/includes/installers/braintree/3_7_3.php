<?php
$configuration_key = 'MODULE_PAYMENT_BRAINTREE_TIMEOUT';
$configuration_title = 'Connection Timeout (seconds)';
$configuration_description = 'Number of seconds to wait when connecting to the Braintree API before timing out. Leave blank to use the SDK\'s default. Minimum values under 10 seconds will be enforced automatically.';
$configuration_default = '10';

$check_query = $db->Execute("SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . zen_db_input($configuration_key) . "' LIMIT 1");
if ($check_query->RecordCount() == 0) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('" . zen_db_input($configuration_title) . "', '" . zen_db_input($configuration_key) . "', '" . zen_db_input($configuration_default) . "', '" . zen_db_input($configuration_description) . "', 6, 0, NULL, NULL, NOW())");
}
