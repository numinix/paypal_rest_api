<?php
        $config_keys = [
            'MODULE_PAYMENT_BRAINTREE_STATUS' => [
                'Enable this Payment Module',
                'False',
                'Do you want to enable this payment module?',
                "zen_cfg_select_option(array('True', 'False'), ",
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_MERCHANTID' => [
                'Merchant Key',
                '',
                'Your Merchant ID provided under the API Keys section.',
                '',
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_PUBLICKEY' => [
                'Public Key',
                '',
                'Your Public Key provided under the API Keys section.',
                '',
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_PRIVATEKEY' => [
                'Private Key',
                '',
                'Your Private Key provided under the API Keys section.',
                '',
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_CURRENCY' => [
                'Merchant Account Default Currency',
                'USD',
                'Your Merchant Account Settlement Currency, must match your Merchant Account Name currency.',
                '',
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_SORT_ORDER' => [
                'Sort Order',
                '0',
                'Sort order of display. Lowest is displayed first.',
                '',
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_ZONE' => [
                'Payment Zone',
                '0',
                'If a zone is selected, only enable this payment method for that zone.',
                'zen_cfg_pull_down_zone_classes(',
                "zen_get_zone_class_title"
            ],
            'MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID' => [
                'Set Order Status',
                '2',
                'Set the status of orders paid with this payment module. (Recommended: Processing[2])',
                'zen_cfg_pull_down_order_statuses(',
                'zen_get_order_status_name'
            ],
            'MODULE_PAYMENT_BRAINTREE_ORDER_PENDING_STATUS_ID' => [
                'Set Unpaid Order Status',
                '1',
                'Set the status of unpaid orders made with this payment module. (Recommended: Pending[1])',
                'zen_cfg_pull_down_order_statuses(',
                'zen_get_order_status_name'
            ],
            'MODULE_PAYMENT_BRAINTREE_REFUNDED_STATUS_ID' => [
                'Set Refund Order Status',
                '1',
                'Set the status of refunded orders. (Recommended: Pending[1])',
                'zen_cfg_pull_down_order_statuses(',
                'zen_get_order_status_name'
            ],
            'MODULE_PAYMENT_BRAINTREE_SERVER' => [
                'Production or Sandbox',
                'sandbox',
                'Used to process transactions (sandbox for testing, production for live).',
                "zen_cfg_select_option(array('production', 'sandbox'), ",
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_DEBUGGING' => [
                'Debug Mode',
                'Alerts Only',
                'Enable debug mode? Detailed logs of failed transactions will be emailed if "Log and Email" is selected.',
                "zen_cfg_select_option(array('Alerts Only', 'Log File', 'Log and Email'), ",
                ''
            ],
            'MODULE_PAYMENT_BRAINTREE_SETTLEMENT' => [
                'Authorize and Capture',
                'true',
                'Set to "true" to automatically capture funds (Authorize and Capture) or "false" to only authorize the payment (Authorize Only).',
                "zen_cfg_select_option(array('true', 'false'), ",
                ''
            ]
        ];

        foreach ($config_keys as $key => $value) {
            $check_query = $db->Execute("SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $key . "'");
            if ($check_query->RecordCount() == 0) {
                $set_function = isset($value[3]) ? addslashes($value[3]) : '';
                $use_function = isset($value[4]) ? addslashes($value[4]) : '';
                $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
                    VALUES ('" . $value[0] . "', '" . $key . "', '" . $value[1] . "', '" . $value[2] . "', '6', '0', '" . $set_function . "', '" . $use_function . "', now())");
            }
        }