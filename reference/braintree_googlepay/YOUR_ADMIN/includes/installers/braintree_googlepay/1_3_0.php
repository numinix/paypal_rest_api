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
        use_function,
        date_added
    ) VALUES (
        'Google Pay: Use 3D Secure (3DS)',
        'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS',
        'False',
        'Enable 3D Secure (3DS) verification for Google Pay transactions (recommended).',
        '6',
        '0',
        'zen_cfg_select_option(array(\'True\', \'False\'), ',
        NULL,
        NOW()
    )
");