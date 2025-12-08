<?php
$db->Execute(
    "INSERT INTO " . TABLE_CONFIGURATION . " (
        configuration_title,
        configuration_key,
        configuration_value,
        configuration_description,
        configuration_group_id,
        sort_order,
        set_function,
        date_added
    ) VALUES (
        'Redirect Apple Pay to Confirmation',
        'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_CONFIRM_REDIRECT',
        'False',
        'After a successful Apple Pay authorization on the cart or product page, redirect the shopper to the confirmation page.',
        6,
        52,
        'zen_cfg_select_option(array(\'True\', \'False\'), ',
        now()
    )"
);

