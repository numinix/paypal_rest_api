<?php
// Include the shared language definitions.
$shared_define = include (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/lang.braintree_shared.php');

$define = [
    'MODULE_PAYMENT_BRAINTREE_TEXT_TITLE' => (IS_ADMIN_FLAG === true) ? 'Braintree Credit Cards' : 'Credit Card',
    'MODULE_PAYMENT_BRAINTREE_TEXT_ADMIN_TITLE' => 'Braintree Credit Card Payments',
    'MODULE_PAYMENT_BRAINTREE_TEXT_ADMIN_DESCRIPTION' => 'Accept credit cards supported by your Merchant Account via Braintree<br />(<a href="https://www.braintreepayments.com/" target="_blank">Manage your Braintree account.</a>)',
    'MODULE_PAYMENT_BRAINTREE_CURRENCY_MISMATCH_WARNING' => '<div class="messageStackWarning braintree-currency-warning">Note: Your selected currency (%s) differs from the settlement currency (%s) for this payment method. You will be charged in %s.</div>',
    'MODULE_PAYMENT_BRAINTREE_SIGNUP_HEADLINE' => 'Need a Braintree account?',
    'MODULE_PAYMENT_BRAINTREE_SIGNUP_DESCRIPTION' => 'Use our partner sign-up link to create your Braintree account and collect the API keys required below.',
    'MODULE_PAYMENT_BRAINTREE_SIGNUP_LINK_TEXT' => 'Apply for Braintree',
    'MODULE_PAYMENT_BRAINTREE_SIGNUP_URL' => 'https://apply.braintreegateway.com/?partner_source=Numinix',
    'MODULE_PAYMENT_BRAINTREE_UPGRADE_AVAILABLE' => 'A new Braintree Payments update (v%s) is ready to install.',
    'MODULE_PAYMENT_BRAINTREE_UPGRADE_BUTTON_TEXT' => 'Upgrade to v%s',
    'MODULE_PAYMENT_BRAINTREE_TEXT_INVALID_REFUND_CHECK' => 'You requested a full refund but did not check the Confirm box to verify your intent.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_DESCRIPTION' => 'Braintree',
    'MODULE_PAYMENT_BRAINTREE_TEXT_INVALID_AUTH_AMOUNT' => 'You requested an authorization but did not specify an amount.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_INVALID_CAPTURE_AMOUNT' => 'You requested a capture but did not specify an amount.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_VOID_CONFIRM_CHECK' => 'Confirm',
    'MODULE_PAYMENT_BRAINTREE_TEXT_VOID_CONFIRM_ERROR' => 'You requested to void a transaction but did not check the Confirm box to verify your intent.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_AUTH_FULL_CONFIRM_CHECK' => 'Confirm',
    'MODULE_PAYMENT_BRAINTREE_TEXT_AUTH_CONFIRM_ERROR' => 'You requested an authorization but did not check the Confirm box to verify your intent.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_CAPTURE_FULL_CONFIRM_ERROR' => 'You requested funds-Capture but did not check the Confirm box to verify your intent.',

    'MODULE_PAYMENT_BRAINTREE_TEXT_REFUND_INITIATED' => 'Braintree refund for %s initiated. Transaction ID: %s. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_AUTH_INITIATED' => 'Braintree Authorization for %s initiated. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_CAPT_INITIATED' => 'Braintree Capture for %s initiated. Receipt ID: %s. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_VOID_INITIATED' => 'Braintree Void request initiated. Transaction ID: %s. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_GEN_API_ERROR' => 'There was an error in the attempted transaction. Please see the API Reference guide or transaction logs for detailed information.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_INVALID_ZONE_ERROR' => 'We are sorry for the inconvenience; however, at the present time we are unable to use this method to process orders from the geographic region you selected as your account address.  Please continue using normal checkout and select from the available payment methods to complete your order.',

    // API-specific keys
    'MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_NUMBER'    => 'Credit Card Number:',
    'MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_EXPIRES'     => 'Expiration Date:',
    'MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_CHECKNUMBER' => 'Card Verification Number (CVV):',

    'MODULE_PAYMENT_BRAINTREE_ENTRY_AUTH_TITLE' => '<strong>Order Authorizations</strong>',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AUTH_PARTIAL_TEXT' => 'If you wish to authorize part of this order, enter the amount  here:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AUTH_BUTTON_TEXT_PARTIAL' => 'Do Authorization',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AUTH_SUFFIX' => '',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_TITLE' => '<strong>Capturing Authorizations</strong>',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_FULL' => 'If you wish to capture all or part of the outstanding authorized amounts for this order, enter the Capture Amount and select whether this is the final capture for this order.  Check the confirm box before submitting your Capture request.<br />',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_BUTTON_TEXT_FULL' => 'Do Capture',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_AMOUNT_TEXT' => 'Amount to Capture:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_FINAL_TEXT' => 'Is this the final capture?',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_SUFFIX' => '',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_TEXT_COMMENTS' => '<strong>Note to display to customer:</strong>',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CAPTURE_DEFAULT_MESSAGE' => 'Thank you for your order.',
    'MODULE_PAYMENT_BRAINTREE_TEXT_CAPTURE_FULL_CONFIRM_CHECK' => 'Confirm: ',

    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID_TITLE' => '<strong>Voiding Order Authorizations</strong>',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID' => 'If you wish to void an authorization, enter the authorization ID here, and confirm:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID_TEXT_COMMENTS' => '<strong>Note to display to customer:</strong>',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID_DEFAULT_MESSAGE' => 'Thank you for your patronage. Please come again.',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID_BUTTON_TEXT_FULL' => 'Do Void',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_VOID_SUFFIX' => '',

    'MODULE_PAYMENT_BRAINTREE_ENTRY_TRANSSTATE' => 'Trans. State:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AUTHCODE' => 'Auth. Code:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AVSADDR' => 'AVS Address match:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_AVSZIP' => 'AVS ZIP match:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_CVV2MATCH' => 'CVV2 match:',
    'MODULE_PAYMENT_BRAINTREE_ENTRY_DAYSTOSETTLE' => 'Days to Settle:',

    'MODULES_PAYMENT_BRAINTREE_LINEITEM_TEXT_ONETIME_CHARGES_PREFIX' => 'One-Time Charges related to ',
    'MODULES_PAYMENT_BRAINTREE_LINEITEM_TEXT_SURCHARGES_SHORT' => 'Surcharges',
    'MODULES_PAYMENT_BRAINTREE_LINEITEM_TEXT_SURCHARGES_LONG' => 'Handling charges and other applicable fees',
    'MODULES_PAYMENT_BRAINTREE_LINEITEM_TEXT_DISCOUNTS_SHORT' => 'Discounts',
    'MODULES_PAYMENT_BRAINTREE_LINEITEM_TEXT_DISCOUNTS_LONG' => 'Credits applied, including discount coupons, gift certificates, etc',

    'MODULES_PAYMENT_BRAINTREE_TEXT_EMAIL_FMF_SUBJECT' => 'Payment in Fraud Review Status: ',
    'MODULES_PAYMENT_BRAINTREE_TEXT_EMAIL_FMF_INTRO' => 'This is an automated notification to advise you that Braintree flagged the payment for a new order as Requiring Payment Review by their Fraud team. Normally the review is completed within 36 hours. It is STRONGLY ADVISED that you DO NOT SHIP the order until payment review is completed. You can see the latest review status of the order by logging into your Braintree account and reviewing recent transactions.',

    'MODULES_PAYMENT_BRAINTREE_AGGREGATE_CART_CONTENTS' => 'All the items in your shopping basket (see details in the store and on your store receipt).',

    'CENTINEL_AUTHENTICATION_ERROR' => 'Authentication Failed - Your financial institution has indicated that it could not successfully authenticate this transaction. To protect against unauthorized use, this card cannot be used to complete your purchase. You may complete the purchase by selecting another form of payment.',
    'CENTINEL_PROCESSING_ERROR' => 'There was a problem obtaining authorization for your transaction. Please re-enter your payment information, and/or choose an alternate form of payment.',
    "CENTINEL_ERROR_CODE_8000" => "8000",
    "CENTINEL_ERROR_CODE_8000_DESC" => "Protocol Not Recognized, must be http:// or https://",
    "CENTINEL_ERROR_CODE_8010" => "8010",
    "CENTINEL_ERROR_CODE_8010_DESC" => "Unable to Communicate with MAPS Server",
    "CENTINEL_ERROR_CODE_8020" => "8020",
    "CENTINEL_ERROR_CODE_8020_DESC" => "Error Parsing XML Response",
    "CENTINEL_ERROR_CODE_8030" => "8030",
    "CENTINEL_ERROR_CODE_8030_DESC" => "Communication Timeout Encountered",
    "CENTINEL_ERROR_CODE_1001" => "1001",
    "CENTINEL_ERROR_CODE_1001_DESC" => "Account Configuration Problem with Cardinal Centinel. Please contact your Cardinal representative immediately on implement@cardinalcommerce.com. Your transactions will not be protected by chargeback liability until this problem is resolved.\n\n" . 'There are 3 steps to configuring your Cardinal 3D-Secure service properly: ' . "\n1-Login to the Cardinal Merchant Admin URL supplied in your welcome package (NOT the test URL), and accept the license agreement.\2-Set a transaction password.\n3-Copy your Cardinal Merchant ID and Cardinal Transaction Password into your ZC Braintree module.",
    "CENTINEL_ERROR_CODE_4243" => "4243",
    "CENTINEL_ERROR_CODE_4243_DESC" => "Account Configuration Problem with Cardinal Centinel. Please contact your Cardinal representative immediately on implement@cardinalcommerce.com and inform them that you are getting Error Number 4243 when attempting to use 3D Secure with your Zen Cart site and Braintree account and that you need to have the Processor Module enabled in your account. Your transactions will not be protected by chargeback liability until this problem is resolved.",

    'TEXT_CCVAL_ERROR_INVALID_MONTH_EXPIRY' => 'Invalid Credit Card Expiry Month: %s',
    'TEXT_CCVAL_ERROR_INVALID_YEAR_EXPIRY' => 'Invalid Credit Card Expiry Year: %s'
];

$define = array_merge($shared_define, $define);

return $define;
