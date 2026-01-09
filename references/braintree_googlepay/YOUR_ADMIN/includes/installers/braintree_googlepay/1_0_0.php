<?php
$config_keys = [
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS' => [
        'Enable Google Pay', 'True',
        'Enable or disable Google Pay',
        'zen_cfg_select_option(array("True", "False"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SERVER' => [
        'Braintree Gateway Mode',
        'sandbox',
        'Set Braintree environment',
        'zen_cfg_select_option(array("sandbox", "production"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_KEY' => [
        'Braintree Merchant ID',
        '',
        'Set your Braintree Merchant ID',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PUBLIC_KEY' => [
        'Braintree Public Key',
        '',
        'Set your Braintree Public Key',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRIVATE_KEY' => [
        'Braintree Private Key',
        '',
        'Set your Braintree Private Key',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SORT_ORDER' => [
        'Sort Order',
        '0',
        'Sort order of display.',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SETTLEMENT' => [
        'Authorize and Capture',
        'true',
        'Set to "true" to automatically capture funds (Authorize and Capture) or "false" for Authorize Only.',
        'zen_cfg_select_option(array("true", "false"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOKENIZATION_KEY' => [
        'Braintree Tokenization Key',
        '',
        'Enter your Braintree Tokenization Key here. Found in Braintree API settings.',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID' => [
        'Google Merchant ID',
        '',
        'Set your Google Merchant ID from Google Pay Console',
        '',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT' => [
        'Google Pay Environment',
        'TEST',
        'Set your Google Pay environment (TEST or PRODUCTION)',
        'zen_cfg_select_option(array("TEST", "PRODUCTION"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ZONE' => [
        'Payment Zone',
        '0',
        'If a zone is selected, only enable this payment method for that zone.',
        'zen_cfg_pull_down_zone_classes(',
        'zen_get_zone_class_title'
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS' => [
        'Order Status After Payment',
        '0',
        'Set the order status when a payment is completed.',
        'zen_cfg_pull_down_order_statuses(',
        'zen_get_order_status_name'
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_REFUNDED_STATUS_ID' => [
        'Set Refund Order Status',
        '1',
        'Set the status of refunded orders to this value.',
        'zen_cfg_pull_down_order_statuses(',
        'zen_get_order_status_name'
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID' => [
        'Set Unpaid Order Status',
        '1',
        'Set the status of orders that are authorized but not yet captured (unpaid).',
        'zen_cfg_pull_down_order_statuses(',
        'zen_get_order_status_name'
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRODUCT_PAGE' => [
        'Enable Google Pay on Product Page',
        'True',
        'Enable or disable the Google Pay button on product pages.',
        'zen_cfg_select_option(array("True", "False"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SHOPPING_CART' => [
        'Enable Google Pay on Shopping Cart Page',
        'True',
        'Enable or disable the Google Pay button on shopping cart pages.',
        'zen_cfg_select_option(array("True", "False"), ',
        ''
    ],
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING' => [
        'Google Pay Debug Mode',
        'Alerts Only',
        'Enable debug mode? Options: Alerts Only, Log File, Log and Email.',
        'zen_cfg_select_option(array("Alerts Only", "Log File", "Log and Email"), ',
        ''
    ],
];

foreach ($config_keys as $key => $value) {
    $check_query = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $key . "'");
    if ($check_query->RecordCount() == 0) {
        $set_function = isset($value[3]) ? $value[3] : '';
        $use_function = isset($value[4]) ? $value[4] : '';
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
            VALUES ('" . $value[0] . "', '" . $key . "', '" . $value[1] . "', '" . $value[2] . "', '6', '0', '" . $set_function . "', '" . $use_function . "', now())");
    }
}
