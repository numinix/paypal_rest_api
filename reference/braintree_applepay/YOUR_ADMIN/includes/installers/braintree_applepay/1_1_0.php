<?php
$db->Execute("
    INSERT INTO " . TABLE_CONFIGURATION . " (
        configuration_title, configuration_key, configuration_value,
        configuration_description, configuration_group_id, sort_order,
        set_function, date_added
    ) VALUES (
        'Enable Apple Pay on Shopping Cart Page',
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SHOPPING_CART',
        'True',
        'Display the Apple Pay button on the shopping cart page for supported devices.',
        '6', '50',
        'zen_cfg_select_option(array(\'True\', \'False\'), ',
        now()
    )
");

$db->Execute("
    INSERT INTO " . TABLE_CONFIGURATION . " (
        configuration_title, configuration_key, configuration_value,
        configuration_description, configuration_group_id, sort_order,
        set_function, date_added
    ) VALUES (
        'Enable Apple Pay on Product Page',
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRODUCT_PAGE',
        'False',
        'Display the Apple Pay button on the product info page for supported devices.',
        '6', '51',
        'zen_cfg_select_option(array(\'True\', \'False\'), ',
        now()
    )
");
