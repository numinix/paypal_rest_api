<?php
// Update default CSS styles for Braintree Hosted Fields containers and iframes

$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_value = '/* Styles for Braintree Hosted Fields iframes */
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
}'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS' LIMIT 1");
