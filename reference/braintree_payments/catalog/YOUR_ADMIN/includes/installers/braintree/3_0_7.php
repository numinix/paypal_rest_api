<?php
$db->Execute("
    INSERT IGNORE INTO " . TABLE_CONFIGURATION . " (
        configuration_title, configuration_key, configuration_value,
        configuration_description, configuration_group_id,
        sort_order, date_added, set_function
    ) VALUES (
        'Automate Styling',
        'MODULE_PAYMENT_BRAINTREE_AUTOMATE_STYLING',
        'True',
        'Automatically apply your template\'s input styling to the Braintree Hosted Fields.',
        '" . $configuration_group_id . "',
        '0',
        NOW(),
        'zen_cfg_select_option(array(\'True\', \'False\'),'
    )
");

$db->Execute("
    INSERT IGNORE INTO " . TABLE_CONFIGURATION . " (
        configuration_title, configuration_key, configuration_value,
        configuration_description, configuration_group_id,
        sort_order, date_added, set_function
    ) VALUES (
        'Custom Hosted Field CSS',
        'MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE',
        '{
            \"input\": {
                \"font-size\": \"16px\",
                \"font-family\": \"Arial, sans-serif\",
                \"color\": \"#333\",
                \"background-color\": \"#fff\",
                \"border\": \"1px solid #ccc\",
                \"padding\": \"8px\",
                \"margin\": \"4px 0\",
                \"box-shadow\": \"none\",
                \"line-height\": \"1.4\",
                \"letter-spacing\": \"normal\"
            },
            \":focus\": {
                \"color\": \"#000\"
            },
            \".invalid\": {
                \"color\": \"red\"
            },
            \".valid\": {
                \"color\": \"green\"
            }
        }',
        'Custom CSS rules (in JSON format) to apply to Hosted Fields if Automate Styling is disabled.',
        '" . $configuration_group_id . "',
        '0',
        NOW(),
        'zen_cfg_textarea('
    )
");

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET set_function = '' WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_VERSION' LIMIT 1;");