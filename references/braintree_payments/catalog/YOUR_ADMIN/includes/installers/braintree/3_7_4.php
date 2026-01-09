<?php
// Update configuration titles and descriptions to clarify the difference between the two CSS configuration fields

// Update Custom Hosted Field CSS (now: Hosted Fields Input Styling)
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_title = 'Hosted Fields Input Styling (JSON)', 
        configuration_description = 'JSON-formatted CSS rules applied to the input elements inside the Braintree Hosted Fields iframes. Controls the appearance of the actual input fields where customers type their card details (font, color, padding, etc.). Only applied when \"Automate Styling\" is disabled. Example: {\"input\": {\"font-size\": \"16px\", \"color\": \"#333\"}}'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE' LIMIT 1");

// Update Custom CSS for Hosted Fields (now: Hosted Fields Container Styling)
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " 
    SET configuration_title = 'Hosted Fields Container Styling (CSS)', 
        configuration_description = 'Standard CSS rules applied to the wrapper containers around the Braintree Hosted Fields. Use this to style the field borders, dimensions, focus states, and validation states. Target the container divs: #braintree_api-cc-number-hosted, #braintree_api-cc-cvv-hosted, #braintree_expiry-hosted. Use classes .braintree-hosted-fields-focused, .braintree-hosted-fields-valid, .braintree-hosted-fields-invalid for state styling.'
    WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS' LIMIT 1");
