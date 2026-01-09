<?php
// Add configuration option for wallet user account type (Guest vs Registered)
$config_key = 'MODULE_PAYMENT_BRAINTREE_WALLET_USER_ACCOUNT_TYPE';
$check_query = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $config_key . "'");
if ($check_query->RecordCount() == 0) {
    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
        (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
        VALUES ('Wallet User Account Type', '" . $config_key . "', 'Guest', 'Treat users that register via Google, Apple or PayPal wallet buttons as guests or registered users?', '6', '0', 'zen_cfg_select_option(array(\'Guest\', \'Registered\'), ', '', now())");
}
