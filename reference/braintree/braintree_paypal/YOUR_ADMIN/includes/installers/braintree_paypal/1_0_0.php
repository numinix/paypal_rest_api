<?php
$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('Enable Braintree PayPal Module', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS', 'True', 'Enable Braintree PayPal module?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Sort Order', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
    VALUES ('Payment Zone', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('Braintree Gateway Mode', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_SERVER', 'sandbox', 'Set the Braintree environment (sandbox or production)', '6', '0', 'zen_cfg_select_option(array(\'sandbox\', \'production\'), ', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Braintree Merchant ID', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_MERCHANT_KEY', '', 'Set your Braintree Merchant ID', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Braintree Public Key', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_PUBLIC_KEY', '', 'Set your Braintree Public Key', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Braintree Private Key', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_PRIVATE_KEY', '', 'Set your Braintree Private Key', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Payment Failed Message', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED', 'Payment processing failed. Please try again.', 'Message displayed when payment fails.', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('Authorize and Capture', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT', 'true', 'Set to \"true\" to automatically capture funds (Authorize and Capture) or \"false\" to only authorize the payment (Authorize Only).', '6', '0', 'zen_cfg_select_option(array(\'true\', \'false\'), ', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES ('Order Status After Payment', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS', '0', 'Set the order status when a payment is completed.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES ('Set Refund Order Status', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_REFUNDED_STATUS_ID', '1', 'Set the status for refunded orders.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES ('Set Pending Payment Status', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_PENDING_STATUS_ID', '0', 'Set the status for orders that are pending payment.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('PayPal Debug Mode', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING', 'Alerts Only', 'Enable debug mode? Options: Alerts Only, Log File, Log and Email.', '6', '0', 'zen_cfg_select_option(array(\'Alerts Only\', \'Log File\', \'Log and Email\'), ', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
    VALUES ('Google Pay: Order Totals Selector', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR', '#orderTotal', 'CSS selector for the order totals container used detect changes to the order total.', '6', '0', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('Display PayPal Button on Shopping Cart Page', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_SHOPPING_CART', 'True', 'Show the PayPal button on the shopping cart page?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
    VALUES ('Display PayPal Button on Product Info Page', 'MODULE_PAYMENT_BRAINTREE_PAYPAL_PRODUCT_PAGE', 'False', 'Show the PayPal button on the product info (product details) page?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");