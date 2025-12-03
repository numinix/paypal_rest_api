<?php
// Braintree configuration keys
$config_keys = [
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER' => [
                'Braintree Gateway Mode',
                'sandbox',
                'Set the Braintree environment (sandbox or production)',
                "zen_cfg_select_option(array('sandbox', 'production'), ",
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_MERCHANT_KEY' => [
                'Braintree Merchant ID',
                '',
                'Set your Braintree Merchant ID',
                '',
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PUBLIC_KEY' => [
                'Braintree Public Key',
                '',
                'Set your Braintree Public Key',
                '',
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRIVATE_KEY' => [
                'Braintree Private Key',
                '',
                'Set your Braintree Private Key',
                '',
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT' => [
                'Authorize and Capture',
                'true',
                'Automatically submit for settlement? (true = Authorize and Capture, false = Authorize Only)',
                "zen_cfg_select_option(array('true', 'false'), ",
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOKENIZATION_KEY' => [
                'Braintree Tokenization Key',
                '',
                'Enter your Braintree Tokenization Key here. Found in Braintree API settings.',
                '',
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS' => [
                'Enable Apple Pay Module',
                'True',
                'Enable Braintree Apple Pay module?',
                "zen_cfg_select_option(array('True', 'False'), ",
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ENVIRONMENT' => [
                'Apple Pay Environment',
                'TEST',
                'Set your Apple Pay environment (TEST or PRODUCTION).',
                "zen_cfg_select_option(array('TEST', 'PRODUCTION'), ",
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING' => [
                'Apple Pay Debug Mode',
                'Alerts Only',
                'Enable debug mode? Options: Alerts Only, Log File, Log and Email.',
                "zen_cfg_select_option(array('Alerts Only', 'Log File', 'Log and Email'), ",
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SORT_ORDER' => [
                'Sort Order',
                '0',
                'Sort order of display.',
                '',
                ''
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ZONE' => [
                'Payment Zone',
                '0',
                'If a zone is selected, only enable this payment method for that zone.',
                "zen_cfg_pull_down_zone_classes(",
                "zen_get_zone_class_title"
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID' => [
                'Order Status After Payment',
                '0',
                'Set the order status when a payment is completed.',
                "zen_cfg_pull_down_order_statuses(",
                "zen_get_order_status_name"
        ],
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_REFUNDED_STATUS_ID' => [
                'Set Refund Order Status',
                '1',
                'Set the status for refunded orders.',
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