<?php
$configuration_group_id = 0;
$lookupKeys = array(
    'SAVED_CREDIT_CARDS_RECURRING_VERSION',
    'SAVED_CREDIT_CARDS_RECURRING_ENABLED'
);

foreach ($lookupKeys as $configKey) {
    $configuration = $db->Execute(
        "SELECT configuration_group_id"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . $configKey . "'"
        . " LIMIT 1"
    );

    if ($configuration && isset($configuration->fields['configuration_group_id'])) {
        $configuration_group_id = (int) $configuration->fields['configuration_group_id'];
    }

    if ($configuration_group_id > 0) {
        break;
    }
}

if ($configuration_group_id > 0) {
    $db->Execute(
        "INSERT IGNORE INTO " . TABLE_CONFIGURATION
        . " (configuration_group_id, configuration_key, configuration_title, configuration_value, configuration_description, sort_order, set_function)"
        . " VALUES ("
        . (int) $configuration_group_id
        . ", 'MY_SUBSCRIPTIONS_DEBUG', 'My Subscriptions Debug Logging', 'false', 'Enable verbose logging for the My Subscriptions customer page to troubleshoot slow loads or timeouts. Logs are written to DIR_FS_LOGS/my_subscriptions_debug.log when available (falling back to includes/modules/pages/my_subscriptions/my_subscriptions_debug.log).', 2, 'zen_cfg_select_option(array(\\'true\\', \\'false\\'),');"
    );
}
