<?php
// Include the shared language definitions.
$shared_define = include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/lang.braintree_shared.php');

// Determine whether we are running in the admin or storefront context.
$isAdminContext = (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true);

// Module-specific language definitions for Apple Pay.
$define = [
    // Admin side text for Apple Pay module
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE' => $isAdminContext ? 'Braintree Apple Pay' : 'Apple Pay',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION' => 'Pay with Apple Pay via Braintree<br />(<a href="https://www.braintreepayments.com/" target="_blank">Manage your Braintree account.</a>)',

    // Error and success messages
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ERROR_HEADING' => 'We\'re sorry, but we were unable to process your payment.',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_CARD_ERROR' => 'The card information entered contains an error. Please check and try again.',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PAYMENT_FAILED' => 'Payment via Apple Pay failed. Please try again.',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PAYMENT_SUCCESS' => 'Payment successfully processed via Apple Pay.',

    // Additional info for card details in Admin (Apple Pay specifics)
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_CREDIT_CARD_FIRSTNAME' => 'Cardholder First Name:',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_CREDIT_CARD_LASTNAME' => 'Cardholder Last Name:',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_CREDIT_CARD_NUMBER' => 'Card Number:',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_EXPIRATION_DATE' => 'Expiration Date:',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_CVC' => 'CVC:',
    'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_CONFIRM_REDIRECT' => 'Redirect Apple Pay purchases to the confirmation page'
];

$define = array_merge($shared_define, $define);

return $define;
