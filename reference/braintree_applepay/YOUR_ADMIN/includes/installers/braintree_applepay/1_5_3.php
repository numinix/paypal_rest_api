<?php
// Braintree Apple Pay v1.5.3 - Add Unpaid Order Status configuration

$config_keys = [
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID' => [
        'Set Unpaid Order Status',
        '1',
        'Set the status for unpaid orders (authorize-only transactions that have not been captured).',
        "zen_cfg_pull_down_order_statuses(",
        "zen_get_order_status_name"
    ]
];

foreach ($config_keys as $key => $value) {
    $check_query = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $key . "'");
    if ($check_query->RecordCount() == 0) {
        $set_function = isset($value[3]) ? addslashes($value[3]) : '';
        $use_function = isset($value[4]) ? addslashes($value[4]) : '';

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                                    VALUES ('" . $value[0] . "', '" . $key . "', '" . $value[1] . "', '" . $value[2] . "', '6', '0', '" . $set_function . "', '" . $use_function . "', now())");
    }
}
