<?php
// Add MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID if it doesn't exist
$check_query = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID'");
if ($check_query->RecordCount() == 0) {
    $db->Execute("
        INSERT INTO " . TABLE_CONFIGURATION . " (
            configuration_title,
            configuration_key,
            configuration_value,
            configuration_description,
            configuration_group_id,
            sort_order,
            set_function,
            use_function,
            date_added
        ) VALUES (
            'Set Unpaid Order Status',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID',
            '1',
            'Set the status of orders that are authorized but not yet captured (unpaid).',
            '6',
            '0',
            'zen_cfg_pull_down_order_statuses(',
            'zen_get_order_status_name',
            NOW()
        )
    ");
}
