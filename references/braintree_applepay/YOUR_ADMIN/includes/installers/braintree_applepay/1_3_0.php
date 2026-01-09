<?php
$db->Execute("
    INSERT INTO " . TABLE_CONFIGURATION . " (
        configuration_title,
        configuration_key,
        configuration_value,
        configuration_description,
        configuration_group_id,
        sort_order,
        set_function,
        date_added
    ) VALUES (
        'Enable 3D Secure for Apple Pay',
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_USE_3DS',
        'False',
        'Enable 3D Secure (3DS) authentication for Apple Pay transactions.',
        6,
        100,
        'zen_cfg_select_option(array(\'True\', \'False\'), ',
        now()
    )
");