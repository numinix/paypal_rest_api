<?php
// Include the shared language definitions if available.
$shared_define = [];
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
$sharedFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $languageCode . '/modules/payment/lang.braintree_shared.php';
if (!file_exists($sharedFile)) {
    $sharedFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/payment/lang.braintree_shared.php';
}
if (file_exists($sharedFile)) {
    $shared_define = include $sharedFile;
    if (!is_array($shared_define)) {
        $shared_define = [];
    }
}

// Module-specific language definitions for Google Pay.
$define = [
    // Admin side text for Google Pay module
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE' => (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) ? 'Braintree Google Pay' : 'Google Pay',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_DESCRIPTION' => 'Pay with Google Pay via Braintree<br />(<a href="https://www.braintreepayments.com/" target="_blank">Manage your Braintree account.</a>)',

    // Error and success messages
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ERROR_HEADING' => 'We\'re sorry, but we were unable to process your payment.',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CARD_ERROR' => 'The card information entered contains an error. Please check and try again.',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PAYMENT_FAILED' => 'Payment via Google Pay failed. Please try again.',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PAYMENT_SUCCESS' => 'Payment successfully processed via Google Pay.',

    // Additional info for card details in Admin (Google Pay specifics)
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CREDIT_CARD_FIRSTNAME' => 'Cardholder First Name:',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CREDIT_CARD_LASTNAME' => 'Cardholder Last Name:',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CREDIT_CARD_NUMBER' => 'Card Number:',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_EXPIRATION_DATE' => 'Expiration Date:',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CVC' => 'CVC:',
    'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_CARD_FUNDING_SOURCE' => 'Card Funding Source:'
];

return array_merge($shared_define, $define);
