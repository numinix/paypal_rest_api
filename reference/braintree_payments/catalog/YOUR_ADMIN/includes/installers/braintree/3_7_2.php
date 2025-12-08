<?php
// 3.7.2 - Add optional tokenization key configuration support.
$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES
    ('Tokenization Key',
    'MODULE_PAYMENT_BRAINTREE_TOKENIZATION_KEY',
    '',
    'Optional Tokenization Key from your Braintree control panel. Used as a fallback authorization credential for client-side Hosted Fields when a client token is unavailable.',
    '6',
    '0',
    '',
    '',
    now())");
