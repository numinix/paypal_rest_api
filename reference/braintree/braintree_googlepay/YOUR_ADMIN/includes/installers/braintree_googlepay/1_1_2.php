<?php
$db->Execute("
    INSERT INTO " . TABLE_CONFIGURATION . " (
        configuration_title,
        configuration_key,
        configuration_value,
        configuration_description,
        configuration_group_id,
        sort_order,
        date_added
    ) VALUES (
        'Google Pay: Order Totals Selector',
        'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOTAL_SELECTOR',
        '#orderTotal',
        'CSS selector for the order totals container used detect changes to the order total.',
        '6',
        '0',
        NOW()
    )
");