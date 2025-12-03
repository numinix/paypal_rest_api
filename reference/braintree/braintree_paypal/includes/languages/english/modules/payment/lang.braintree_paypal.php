<?php
// Include the shared language definitions.
$shared_define = include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/lang.braintree_shared.php');

// Module-specific language definitions for PayPal.
$define = [
    // Admin side text for PayPal module
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_TITLE' => (IS_ADMIN_FLAG === true) ? 'Braintree PayPal' : 'PayPal',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_DESCRIPTION' => 'Pay with PayPal via Braintree<br />(<a href="https://www.braintreepayments.com/" target="_blank">Manage your Braintree account.</a>)',

    // Error and success messages
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_ERROR_HEADING' => 'We\'re sorry, but we were unable to process your payment.',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_CARD_ERROR' => 'The card information entered contains an error. Please check and try again.',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED' => 'Payment via PayPal failed. Please try again.',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_SUCCESS' => 'Payment successfully processed via PayPal.',

    // Additional info for card details in Admin (PayPal specifics)
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_CREDIT_CARD_FIRSTNAME' => 'Cardholder First Name:',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_CREDIT_CARD_LASTNAME' => 'Cardholder Last Name:',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_CREDIT_CARD_NUMBER' => 'Card Number:',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_EXPIRATION_DATE' => 'Expiration Date:',
    'MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_CVC' => 'CVC:'
];

$define = array_merge($shared_define, $define);

return $define;