<?php
$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES
    ('Enable 3D Secure (3DS)?',
    'MODULE_PAYMENT_BRAINTREE_USE_3DS',
    'True',
    'Do you want to enable 3D Secure verification for credit card payments?',
    '6',
    '0',
    'zen_cfg_select_option(array(\'True\', \'False\'), ',
    '',
    now())");

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '{
    \"input\": {
        \"font-size\": \"16px\",
        \"font-family\": \"Arial, sans-serif\",
        \"color\": \"#333\",
        \"background-color\": \"#fff\",
        \"padding\": \"8px\"
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
}' WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE' LIMIT 1;");

$db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
    VALUES
    ('Custom CSS for Hosted Fields',
    'MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS',
    '/* Styles for Braintree Hosted Fields iframes */
#braintree_api-cc-number-hosted iframe,
#braintree_expiry-hosted iframe,
#braintree_api-cc-cvv-hosted iframe {
    float: none !important;
    height: 46px !important;
}

/* Styles for Braintree Hosted Fields containers */
#braintree_api-cc-number-hosted,
#braintree_expiry-hosted,
#braintree_api-cc-cvv-hosted {
    display: inline-block !important;
    margin: 0;
    width: 100% !important;
    background-color: #fff;
    border: 1px solid #d3d3d3;
    height: 50px;
    border-radius: 8px;
    padding: 1px 2px 1px 5px;
    background: #f6f6f7;
}

/* Focus state styling */
#braintree_api-cc-number-hosted.braintree-hosted-fields-focused,
#braintree_api-cc-cvv-hosted.braintree-hosted-fields-focused,
#braintree_expiry-hosted.braintree-hosted-fields-focused {
    border-color: #999;
}

/* Valid state styling */
#braintree_api-cc-number-hosted.braintree-hosted-fields-valid,
#braintree_api-cc-cvv-hosted.braintree-hosted-fields-valid,
#braintree_expiry-hosted.braintree-hosted-fields-valid {
    border-color: green;
}

/* Invalid state styling */
#braintree_api-cc-number-hosted.braintree-hosted-fields-invalid,
#braintree_api-cc-cvv-hosted.braintree-hosted-fields-invalid,
#braintree_expiry-hosted.braintree-hosted-fields-invalid {
    border-color: red;
}',
    'CSS rules applied to the Braintree Hosted Fields iframe wrapper. Use this to control iframe borders, sizes, and error states.',
    '6',
    '0',
    'zen_cfg_textarea(',
    '',
    now())");
