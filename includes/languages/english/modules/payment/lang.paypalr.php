<?php
$define = [
    'MODULE_PAYMENT_PAYPALR_TEXT_TITLE' => 'PayPal Checkout',
    'MODULE_PAYMENT_PAYPALR_TEXT_TITLE_ADMIN' => 'PayPal Checkout (RESTful)',
    'MODULE_PAYMENT_PAYPALR_TEXT_DESCRIPTION' => '<strong>PayPal</strong>',
    'MODULE_PAYMENT_PAYPALR_TEXT_TYPE' => 'PayPal Checkout',
    'MODULE_PAYMENT_PAYPALR_TEXT_EC_HEADER' => 'Fast, Secure Checkout with PayPal:',    //- not currently used

    // -----
    // Configuration-related errors displayed during the payment module's admin configuration.
    //
    'MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL' => 'CURL not installed, cannot use.',
    'MODULE_PAYMENT_PAYPALR_ERROR_CREDS_NEEDED' => 'The <var>paypalr</var> payment module cannot be enabled until you supply valid credentials for your <b>%s</b> site.',
    'MODULE_PAYMENT_PAYPALR_ERROR_INVALID_CREDS' => 'The <b>%s</b> credentials for the <var>paypalr</var> payment module are invalid.',
    'MODULE_PAYMENT_PAYPALR_AUTO_DISABLED' => ' The payment module has been automatically disabled.',

    // -----
    // Admin alert-email messages.
    //
    'MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT' => 'ALERT: PayPalCheckout Error (%s)',    //- %s is an additional error descriptor, see below
        'MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_CONFIGURATION' => 'Configuration',
        'MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN' => 'Order Requires Attention',

    'MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATION' => 'The status for order #%1$u was forced to "Pending" due to a PayPal response status of \'%2$s\'.',

    // -----
    // Storefront messages.
    //
    'MODULE_PAYMENT_PALPALR_PAYING_WITH_PAYPAL' => 'Paying via PayPal',     //- Used by the confirmation method, when paying via PayPal Checkout (paypal)

    'MODULE_PAYMENT_PAYPALR_INVALID_RESPONSE' => 'We were not able to process your order. Please try again, select an alternate payment method or contact the store owner for assistance.',
    'MODULE_PAYMENT_PAYPALR_TEXT_GEN_ERROR' => 'An error occurred when we tried to contact the payment processor. Please try again, select an alternate payment method or contact the store owner for assistance.',
    'MODULE_PAYMENT_PAYPALR_TEXT_EMAIL_ERROR_MESSAGE' => 'Dear store owner,' . "\n" . 'An error occurred when attempting to initiate a PayPal Checkout transaction. As a courtesy, only the error \'number\' was shown to your customer.  The details of the error are shown below.' . "\n\n",
    'MODULE_PAYMENT_PAYPALR_TEXT_ADDR_ERROR' => 'The address information you entered does not appear to be valid or cannot be matched. Please select or add a different address and try again.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CONFIRMEDADDR_ERROR' => 'The address you selected at PayPal is not a Confirmed address. Please return to PayPal and select or add a confirmed address and try again.',
    'MODULE_PAYMENT_PAYPALR_TEXT_INSUFFICIENT_FUNDS_ERROR' => 'PayPal was unable to successfully fund this transaction. Please choose another payment option or review funding options in your PayPal account before proceeding.',
    'MODULE_PAYMENT_PAYPALR_TEXT_PAYPALR_DECLINED' => 'Sorry. PayPal has declined the transaction and advised us to tell you to contact PayPal Customer Service for more information. To complete your purchase, please select an alternate payment method.',
 
    'MODULE_PAYMENT_PAYPALR_FUNDING_ERROR' => 'Funding source problem; please go to Paypal.com and make payment directly to ' . STORE_OWNER_EMAIL_ADDRESS,
    'MODULE_PAYMENT_PAYPALR_TEXT_INVALID_ZONE_ERROR' => 'We are sorry for the inconvenience; however, at the present time we are unable to use PayPal to process orders from the geographic region you selected as your PayPal address.  Please continue using normal checkout and select from the available payment methods to complete your order.',
    'MODULE_PAYMENT_PAYPALR_TEXT_ORDER_ALREADY_PLACED_ERROR' => 'It appears that your order was submitted twice. Please check the My Account area to see the actual order details.  Please use the Contact Us form if your order does not appear here but is already paid from your PayPal account so that we may check our records and reconcile this with you.',
    'MODULE_PAYMENT_PAYPALR_TEXT_BUTTON_ALTTEXT' => 'Click here to pay via PayPal Express Checkout',
    'MODULE_PAYMENT_PAYPALR_EC_BUTTON_IMG' => 'https://www.paypalobjects.com/webstatic/en_US/btn/btn_checkout_pp_142x27.png',
    'MODULE_PAYMENT_PAYPALR_EC_BUTTON_SM_IMG' => 'https://www.paypalobjects.com/en_US/i/btn/btn_xpressCheckoutsm.gif',
    'MODULE_PAYMENT_PAYPALR_MARK_BUTTON_TXT' => '',
    'MODULE_PAYMENT_PAYPALEC_MARK_BUTTON_IMG' => 'https://www.paypalobjects.com/en_US/i/btn/btn1_for_hub.gif',

    // -----
    // Admin messages, from an order's display, viewing the PayPal transaction history.
    //
    'MODULE_PAYMENT_PAYPALR_TEXT_GETDETAILS_ERROR' => 'There was a problem retrieving PayPal transaction details.',
    'MODULE_PAYMENT_PAYPALR_NO_RECORDS' => 'No \'%1$s\' records were found in the database for order #%2$u.',
    'MODULE_PAYMENT_PAYPALR_TEXT_TRANSSEARCH_ERROR' => 'There was a problem locating transactions matching the criteria you specified. ',
    'MODULE_PAYMENT_PAYPALR_TEXT_VOID_ERROR' => 'There was a problem voiding the transaction. ',
    'MODULE_PAYMENT_PAYPALR_TEXT_REFUND_ERROR' => 'There was a problem refunding the transaction amount specified. ',

    'MODULE_PAYMENT_PAYPALR_TEXT_CAPT_ERROR' => 'There was a problem capturing the transaction. ',
    'MODULE_PAYMENT_PAYPALR_TEXT_REFUNDFULL_ERROR' => 'Your Refund Request was rejected by PayPal.',
    'MODULE_PAYMENT_PAYPALR_TEXT_INVALID_REFUND_AMOUNT' => 'You requested a partial refund but did not specify an amount.',
    'MODULE_PAYMENT_PAYPALR_TEXT_REFUND_FULL_CONFIRM_ERROR' => 'You requested a full refund but did not check the Confirm box to verify your intent.',

    'MODULE_PAYMENT_PAYPALR_TEXT_INVALID_CAPTURE_AMOUNT' => 'You requested a capture but did not specify an amount.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CAPTURE_FULL_CONFIRM_ERROR' => 'You requested funds-Capture but did not check the Confirm box to verify your intent.',
    'MODULE_PAYMENT_PAYPALR_TEXT_GEN_API_ERROR' => 'There was an error in the attempted transaction. Please see the API Reference guide or transaction logs for detailed information.',

    'MODULE_PAYMENT_PAYPALR_TEXT_REFUND_INITIATED' => 'PayPal refund for %s initiated. Transaction ID: %s. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',

    'MODULE_PAYMENT_PAYPALR_TEXT_CAPT_INITIATED' => 'PayPal Capture for %s initiated. Receipt ID: %s. Refresh the screen to see confirmation details updated in the Order Status History/Comments section.',

    'MODULE_PAYMENT_PAYPALR_COMMENTS' => 'System Comments: ',
    'MODULE_PAYMENT_PAYPALR_PROTECTIONELIG' => 'Protection Eligibility:',

    // -----
    // Used during the admin's display of the payment transactions on an
    // order's detailed view.
    //
    'MODULE_PAYMENT_PAYPALR_NO_RECORDS_FOUND' => 'No PayPal transactions are recorded in the database for this order.',

    'MODULE_PAYMENT_PAYPALR_NAME_EMAIL' => 'Payer Name/Email:',
    'MODULE_PAYMENT_PAYPALR_PAYER_ID' => 'Payer ID:',
    'MODULE_PAYMENT_PAYPALR_PAYER_STATUS' => 'Payer Status:',
    'MODULE_PAYMENT_PAYPALR_PAYMENT_TYPE' => 'Payment Type:',
    'MODULE_PAYMENT_PAYPALR_PAYMENT_STATUS' => 'Payment Status:',
    'MODULE_PAYMENT_PAYPALR_PENDING_REASON' => 'Pending Reason:',
    'MODULE_PAYMENT_PAYPALR_INVOICE' => 'Invoice:',
    'MODULE_PAYMENT_PAYPALR_PAYMENT_DATE' => 'Payment Date:',
    'MODULE_PAYMENT_PAYPALR_CURRENCY_HDR' => 'Currency:',
    'MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT' => 'Gross Amount:',
    'MODULE_PAYMENT_PAYPALR_PAYMENT_FEE' => 'Payment Fee:',
    'MODULE_PAYMENT_PAYPALR_EXCHANGE_RATE' => 'Exchange Rate:',

    'MODULE_PAYMENT_PAYPALR_TXN_TYPE' => 'Trans. Type:',
    'MODULE_PAYMENT_PAYPALR_TXN_ID' => 'Trans. ID:',
    'MODULE_PAYMENT_PAYPALR_ACTION' => 'Action',
        'MODULE_PAYMENT_PAYPALR_ACTION_DETAILS' => 'Details',
        'MODULE_PAYMENT_PAYPALR_ACTION_REAUTH' => 'Re-Authorize',
        'MODULE_PAYMENT_PAYPALR_ACTION_VOID' => 'Void',
        'MODULE_PAYMENT_PAYPALR_ACTION_CAPTURE' => 'Capture',
        'MODULE_PAYMENT_PAYPALR_ACTION_REFUND' => 'Refund',

    'MODULE_PAYMENT_PAYPALR_CONFIRM' => 'Confirm',
    'MODULE_PAYMENT_PAYPALR_DAYSTOSETTLE' => 'Days to Settle:',
    'MODULE_PAYMENT_PAYPALR_AMOUNT' => 'Amount:',
    'MODULE_PAYMENT_PAYPALR_DATE_CREATED' => 'Date Created:',

    // -----
    // Constants used in the "Details" modal.
    //
    'MODULE_PAYMENT_PAYPALR_DETAILS_TITLE' => 'PayPal Transaction Details',
    'MODULE_PAYMENT_PAYPALR_BUYER_INFO' => 'Buyer Information',
    'MODULE_PAYMENT_PAYPALR_PAYER_NAME' => 'Payer Name:',
    'MODULE_PAYMENT_PAYPALR_PAYER_EMAIL' => 'Payer Email:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS' => 'Address:',
    'MODULE_PAYMENT_PAYPALR_BUSINESS_NAME' => 'Business Name:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_NAME' => 'Name:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_STREET' => 'Street:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_CITY' => 'City:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_STATE' => 'State:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_ZIP' => 'Zip:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_COUNTRY' => 'Country:',
    'MODULE_PAYMENT_PAYPALR_ADDRESS_STATUS' => 'Status:',
    'MODULE_PAYMENT_PAYPALR_SELLER_INFO' => 'Seller Information',
    'MODULE_PAYMENT_PAYPALR_CART_ITEMS' => 'Cart items:',

    // -----
    // Constants used in the "Refunds" modal.
    //
    'MODULE_PAYMENT_PAYPALR_REFUND_TITLE' => 'Refund Payment',
    'MODULE_PAYMENT_PAYPALR_REFUND_FULL' => 'If you wish to refund this order in its entirety, click here:',
    'MODULE_PAYMENT_PAYPALR_REFUND_BUTTON_TEXT_FULL' => 'Do Full Refund',
    'MODULE_PAYMENT_PAYPALR_REFUND_BUTTON_TEXT_PARTIAL' => 'Do Partial Refund',
    'MODULE_PAYMENT_PAYPALR_REFUND_TEXT_FULL_OR' => '<br>... or enter the partial ',
    'MODULE_PAYMENT_PAYPALR_REFUND_PAYFLOW_TEXT' => 'Enter the ',
    'MODULE_PAYMENT_PAYPALR_REFUND_PARTIAL_TEXT' => 'refund amount here and click on Partial Refund',
    'MODULE_PAYMENT_PAYPALR_REFUND_SUFFIX' => '*A Full refund may not be issued after a Partial refund has been applied.<br>*Multiple Partial refunds are permitted up to the remaining unrefunded balance.',
    'MODULE_PAYMENT_PAYPALR_REFUND_TEXT_COMMENTS' => '<strong>Note to display to customer:</strong>',
    'MODULE_PAYMENT_PAYPALR_REFUND_DEFAULT_MESSAGE' => 'Refunded by store administrator.',
    'MODULE_PAYMENT_PAYPALR_TEXT_REFUND_FULL_CONFIRM_CHECK' => 'Confirm: ',

    // -----
    // Constants used in the "Re-Authorize" modal.
    //
    'MODULE_PAYMENT_PAYPALR_REAUTH_TITLE' => 'Re-Authorize an Order',
    'MODULE_PAYMENT_PAYPALR_REAUTH_ORIGINAL' => 'Original Amount:',
    'MODULE_PAYMENT_PAYPALR_REAUTH_AMOUNT_RANGE' => 'Enter an amount between %1$s 1.00 and %1$s %2$s.',

    'MODULE_PAYMENT_PAYPALR_REAUTH_PARAM_ERROR' => 'Invalid parameters were supplied for a %s re-authorization request; please try again.',
    'MODULE_PAYMENT_PAYPALR_REAUTH_INVALID_AMOUNT' => 'The amount you supplied for a %s re-authorization request was invalid; please try again.',
    'MODULE_PAYMENT_PAYPALR_REAUTH_ERROR' => 'There was a problem authorizing the transaction.',
    'MODULE_PAYMENT_PAYPALR_REAUTH_COMPLETE' => 'A re-authorization in the amount of %s has been completed.',
    'MODULE_PAYMENT_PAYPALR_REAUTH_MEMO' => 'Re-authorized by %1$s for an amount of %2$s.',

    // -----
    // Constants used in the "Capture" modal.
    //
    'MODULE_PAYMENT_PAYPALR_CAPTURE_TITLE' => 'Capture an Authorization',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_FULL' => 'If you wish to capture all or part of the outstanding authorized amounts for this order, enter the Capture Amount and select whether this is the final capture for this order.  Check the confirm box before submitting your Capture request.<br>',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_BUTTON_TEXT_FULL' => 'Do Capture',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_AMOUNT_TEXT' => 'Amount to Capture:',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_FINAL_TEXT' => 'Is this the final capture?',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_SUFFIX' => '',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_TEXT_COMMENTS' => '<strong>Note to display to customer:</strong>',
    'MODULE_PAYMENT_PAYPALR_CAPTURE_DEFAULT_MESSAGE' => 'Thank you for your order.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CAPTURE_FULL_CONFIRM_CHECK' => 'Confirm: ',

    // -----
    // Constants used in the "Void" modal.
    //
    'MODULE_PAYMENT_PAYPALR_VOID_TITLE' => 'Void an Authorization',
    'MODULE_PAYMENT_PAYPALR_VOID_INSTRUCTIONS' => 'To void this transaction, enter/copy the &quot;Authorization ID&quot; into the input field below and click the &quot;Do Void&quot; button.',
    'MODULE_PAYMENT_PAYPALR_VOID_AUTH_ID' => 'Authorization ID:',
    'MODULE_PAYMENT_PAYPALR_VOID_AMOUNT' => 'Amount:',
    'MODULE_PAYMENT_PAYPALR_VOID_CUSTOMOR_NOTE' => 'Customer Note:',
    'MODULE_PAYMENT_PAYPALR_VOID_DEFAULT_MESSAGE' => 'Thank you for your patronage. Please come again.',
    'MODULE_PAYMENT_PAYPALR_VOID_BUTTON_TEXT' => 'Do Void',

    'MODULE_PAYMENT_PAYPALR_VOID_PARAM_ERROR' => 'Invalid parameters were supplied for a % re-authorization request; please try again.',
    'MODULE_PAYMENT_PAYPALR_VOID_ERROR' => 'There was a problem voiding the transaction.',
    'MODULE_PAYMENT_PAYPALR_VOID_MEMO' => 'Transaction voided by %1$s.',
    'MODULE_PAYMENT_PAYPALR_VOID_INVALID_TXN_ID' => 'The transaction ID you entered (%1$s) was not found; please try again.',
    'MODULE_PAYMENT_PAYPALR_VOID_COMPLETE' => 'The authorized payment for order#%u has been voided.',

    /* card-related language constants, future.
    'MODULE_PAYMENT_PAYPALR_TRANSSTATE' => 'Trans. State:',
    'MODULE_PAYMENT_PAYPALR_AUTHCODE' => 'Auth. Code:',
    'MODULE_PAYMENT_PAYPALR_AVSADDR' => 'AVS Address match:',
    'MODULE_PAYMENT_PAYPALR_AVSZIP' => 'AVS ZIP match:',
    'MODULE_PAYMENT_PAYPALR_CVV2MATCH' => 'CVV2 match:',

    'MODULE_PAYMENT_PAYPALR_ERROR_HEADING' => 'We\'re sorry, but we were unable to process your credit card.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CARD_ERROR' => 'The credit card information you entered contains an error.  Please check it and try again.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_FIRSTNAME' => 'Credit Card First Name:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_LASTNAME' => 'Credit Card Last Name:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_OWNER' => 'Cardholder Name:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_TYPE' => 'Credit Card Type:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_NUMBER' => 'Credit Card Number:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_EXPIRES' => 'Credit Card Expiry Date:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_ISSUE' => 'Credit Card Issue Date:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_CVV' => 'CVV Number:',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_CHECKNUMBER_LOCATION' => '(on back of the credit card)',

    'MODULE_PAYMENT_PAYPALR_TEXT_CC_DECLINED' => 'Your credit card was declined. Please try another card or contact your bank for more information.',
    'MODULE_PAYMENT_PAYPALR_TEXT_CC_ERROR' => 'An error occurred when we tried to process your credit card. Please try again, select an alternate payment method or contact the store owner for assistance.',
    'MODULE_PAYMENT_PAYPALR_TEXT_BAD_CARD' => 'We apologize for the inconvenience, but the credit card you entered is not one that we accept. Please use a different credit card.',
    'MODULE_PAYMENT_PAYPALR_TEXT_BAD_LOGIN' => 'There was a problem validating your account. Please try again.',
    'MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_OWNER' => '* The cardholder\'s name must be at least ' . CC_OWNER_MIN_LENGTH . ' characters.\n',
    'MODULE_PAYMENT_PAYPALR_TEXT_JS_CC_NUMBER' => '* The credit card number must be at least ' . CC_NUMBER_MIN_LENGTH . ' characters.\n',
    'MODULE_PAYMENT_PAYPALR_ERROR_AVS_FAILURE_TEXT' => 'ALERT: Address Verification Failure. ',
    'MODULE_PAYMENT_PAYPALR_ERROR_CVV_FAILURE_TEXT' => 'ALERT: Card CVV Code Verification Failure. ',
    'MODULE_PAYMENT_PAYPALR_ERROR_AVSCVV_PROBLEM_TEXT' => ' Order is on hold pending review by Store Owner.',

    'MODULES_PAYMENT_PAYPALDP_TEXT_EMAIL_FMF_SUBJECT' => 'Payment in Fraud Review Status: ',
    'MODULES_PAYMENT_PAYPALDP_TEXT_EMAIL_FMF_INTRO' => 'This is an automated notification to advise you that PayPal flagged the payment for a new order as Requiring Payment Review by their Fraud team. Normally the review is completed within 36 hours. It is STRONGLY ADVISED that you DO NOT SHIP the order until payment review is completed. You can see the latest review status of the order by logging into your PayPal account and reviewing recent transactions.',
    'MODULES_PAYMENT_PAYPALR_TEXT_BLANK_ADDRESS' => 'PROBLEM: We are sorry. PayPal has unexpectedly returned a blank address.<br>In order to complete your purchase, please provide your address by clicking the &quot;Sign Up&quot; button below to create an account in our store. Then you can select PayPal again when you continue with checkout. We apologize for the inconvenience. If you have any trouble with checkout, please click the Contact Us link to explain the details to us so we can help you with your purchase and prevent the problem in the future. Thanks.',
    'MODULES_PAYMENT_PAYPALR_AGGREGATE_CART_CONTENTS' => 'All the items in your shopping basket (see details in the store and on your store receipt).',
*/
];

if (IS_ADMIN_FLAG === true) {
    $define['MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_DESCRIPTION'] = 'Instructions go here. v%s';
/*
        '<strong>PayPal Checkout</strong>%s<br>' .
        '<a href="https://www.paypal.com" rel="noopener" target="_blank">Manage your PayPal account.</a><br><br>' .
        '<span class="text-success">Configuration Instructions:</span><br><span class="alert">1. </span><a href="https://www.zen-cart.com/partners/paypal-ec" rel="noopener" target="_blank">Sign up for your PayPal account - click here.</a><br>' .
        (defined('MODULE_PAYMENT_PAYPALR_STATUS') ?
            '' :
            '... and click &quot;install&quot; above to enable PayPal Checkout support.<br>' .
            '<a href="https://www.zen-cart.com/getpaypal" rel="noopener" target="_blank">For additional detailed help, see this FAQ article</a><br>') .
        ((!isset(define['MODULE_PAYMENT_PAYPALR_APISIGNATURE']) || $define['MODULE_PAYMENT_PAYPALR_APISIGNATURE'] === '') ? '<br><span class="alert">2. </span><strong>API credentials</strong> from the API Credentials option in your PayPal Profile Settings area. (Click <a href="https://www.paypal.com/us/cgi-bin/webscr?cmd=_get-api-signature&generic-flow=true" rel="noopener" target="_blank">here for API credentials</a>.) <br>This module uses the <strong>API Signature</strong> option -- you will need the username, password and signature to enter in the fields below.' : (substr($define['MODULE_PAYMENT_PAYPALR_MODULE_MODE'], 0, 7) == 'Payflow' && (!isset($define['MODULE_PAYMENT_PAYPALR_PFUSER']) || $define['MODULE_PAYMENT_PAYPALR_PFUSER'] == '') ? '<span class="alert">2. </span><strong>PAYFLOW credentials</strong> This module needs your <strong>PAYFLOW Partner+Vendor+User+Password settings</strong> entered in the 4 fields below. These will be used to communicate with the Payflow system and authorize transactions to your account.' : '<span class="alert">2. </span>Ensure you have entered the appropriate security data for username/pwd etc, below.')) .
        ($define['MODULE_PAYMENT_PAYPALR_MODULE_MODE'] == 'PayPal' ? '<br><br><span class="alert">3. </span>In your PayPal account, enable <strong>Instant Payment Notification</strong>:<br>under "Profile", select <em>Instant Payment Notification Preferences</em><ul style="margin-top: 0.5em;"><li>click the checkbox to enable IPN</li><li>if there is not already a URL specified, set the URL to:<br><nobr><pre>' . str_replace('index.php?main_page=index', 'ipn_main_handler.php', zen_catalog_href_link(FILENAME_DEFAULT)) . '</pre></nobr></li></ul>' : '') .
        '<font color="green"><hr><strong>Requirements:</strong></font><br><hr>*<strong>CURL</strong> is used for outbound communication with the gateway over ports 80 and 443, so must be active on your hosting server and able to use SSL.<br><hr>';
*/
}

return $define;
