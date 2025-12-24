<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright Joseph Schilz
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */
$define = [
    'NAVBAR_TITLE_1' => 'Checkout',
    'NAVBAR_TITLE_1_CHECKOUT' => 'Checkout',

    'TABLE_HEADING_BILLING_ADDRESS' => 'Billing Address',
    'TABLE_HEADING_SHIPPING_ADDRESS' => 'Shipping Address',
    'TABLE_HEADING_SHOPPING_CART' => 'Shopping Cart',
    'TABLE_HEADING_PAYMENT_METHOD' => 'Payment Method',
    'HEADING_STEP_3_COMMENTS' => 'Order Comments',
    'TABLE_HEADING_SHIPPING_METHOD' => 'Shipping Method',
    'TABLE_HEADING_SHIPPING_METHOD_NOT_REQUIRED' => 'No Shipping Required',
    'TABLE_HEADING_CONTACT_DETAILS' => 'Contact Details',
    'TABLE_HEADING_CONDITIONS' => '<span class="termsconditions">Terms & Conditions</span>',
    'TABLE_HEADING_COMMENTS' => 'Order Comments',

    'HEADING_TITLE' => 'Secure Checkout',
    'HEADING_NEW_CUSTOMERS' => 'New Customers',
    'HEADING_COWOA' => 'Guest Checkout',
    'HEADING_RETURNING_CUSTOMER' => 'Returning Customers',
    'HEADING_RETURNING_CUSTOMER_SPLIT' => 'Returning Customers',
    'HEADING_CONFIDENCE' => 'Shop With Confidence',

    'TEXT_RATHER_COWOA' => 'For a faster checkout experience, we offer the option to checkout as a guest.<br />',
    'TEXT_COWOA_CHECKED' => 'Checkout as a guest',
    'TEXT_COWOA_UNCHECKED' => 'Check here to register an account.',
    'COWOA_HEADING' => 'Guest Checkout',
    'TEXT_NEW_CUSTOMER_INTRODUCTION' => 'Create an account',
    'TEXT_RETURN_TO_SIGN_IN' => 'return to sign-in',

    'HIDEREGISTRATION_CREATE_ACCOUNT' => '<p>An account allows you to:</p>',
    'HIDEREGISTRATION_COWOA' => '<p>Proceed to checkout, and you will have a chance to create an account in the next step.</p>',

    'TEXT_OPRC_LOGIN_INTRO' => '',
    'TEXT_COWOA_LOGIN' => '<p id="cowoaLogin">Guests who have previously ordered can register an account by clicking the "forgot your password?" link above.</p>',
    'TEXT_ACCOUNT_BENEFITS' => '<ul id="accountBenefits"><li>Checkout faster</li><li>Save shipping addresses</li><li>Track your orders from your account</li><li>Review previous orders</li></ul>',
    'TEXT_COWOA_BENEFITS' => '<ul id="guestBenefits"><li>Never have to remember your password</li><li>Track order status using email address and order number</li><li>Register a full account at any time</li></ul>',
    'REGULAR_HEADING' => 'Register An Account',

    'TEXT_ENTER_SHIPPING_INFORMATION' => 'This is currently the only shipping method available to use on this order.',
    'TEXT_LEGEND_HEAD' => 'Create a New Account',
    'TEXT_SELECT_PAYMENT_METHOD' => 'Select your payment type',
    'TEXT_CHOOSE_SHIPPING_DESTINATION' => '',
    'TEXT_CHOOSE_SHIPPING_METHOD' => 'Please select the preferred shipping method to use on this order.',
    'TEXT_SELECTED_BILLING_DESTINATION' => '',
    'TEXT_PASSWORD_FORGOTTEN' => 'Forgot your password?',
    'TEXT_PASSWORD_FORGOTTEN_PROCESSING' => 'Processing your request…',
    'TEXT_PASSWORD_FORGOTTEN_ERROR' => 'We were unable to process your request. Please try again.',
    'TEXT_CONDITIONS_DESCRIPTION' => '<span class="termsdescription">Please acknowledge the terms and conditions bound to this order by ticking the following box. The terms and conditions can be read <a href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . ' #conditions" target="_blank"><span class="pseudolink">here</span></a>.</span>',
    'TEXT_CONDITIONS_CONFIRM' => '<span class="termsiagree">I have read and agreed to the terms and conditions bound to this order.</span>',
    'TEXT_PRIVACY_CONDITIONS_DESCRIPTION' => 'Please acknowledge you agree with our privacy statement by ticking the following box. The privacy statement can be read <a href="' . zen_href_link(FILENAME_PRIVACY, '', 'SSL') . ' #privacy" target="blank"><span class="pseudolink">here</span></a>.',
    'TITLE_NO_SHIPPING_AVAILABLE' => 'Not Available At This Time',
    'TEXT_NO_SHIPPING_AVAILABLE' =>'<span class="alert">Sorry, we are not shipping to your region at this time.</span><br />Please contact us for alternate arrangements.',
    'TEXT_NO_PAYMENT_OPTIONS_AVAILABLE' => 'Not Available At This Time',
    'TEXT_REQUIRED_INFORMATION_OPRC' => '* required information',
    'TEXT_OPRC_ADDRESS_LOOKUP_BUTTON' => 'Find address',
    'TEXT_OPRC_ADDRESS_LOOKUP_HEADING' => 'Select your address',
    'TEXT_OPRC_ADDRESS_LOOKUP_PLACEHOLDER' => 'Choose an address',
    'TEXT_OPRC_ADDRESS_LOOKUP_LABEL' => 'Select Address:',
    'TEXT_OPRC_ADDRESS_LOOKUP_LOADING' => 'Looking up addresses …',
    'TEXT_OPRC_ADDRESS_LOOKUP_NO_RESULTS' => 'We were unable to find any addresses for that postal code.',
    'TEXT_OPRC_ADDRESS_LOOKUP_ERROR' => 'We were unable to retrieve address suggestions. Please try again.',
    'TEXT_OPRC_ADDRESS_LOOKUP_APPLIED' => 'The selected address has been applied to the form.',
    'TEXT_OPRC_ADDRESS_LOOKUP_PROVIDER' => 'Powered by %s',
    'TEXT_OPRC_ADDRESS_LOOKUP_MISSING_POSTCODE' => 'Enter a postal/zip code to search for your address.',
    'TEXT_OPRC_ADDRESS_LOOKUP_UNAVAILABLE' => 'Address lookup is currently unavailable. Please enter your address manually.',
    'TEXT_NEED_HELP' => 'Need Help?',
    'TEXT_CONTACT_US_AT' => 'Contact us at ',
    'TEXT_ORDER_TOTAL_DISCLAIMER' => 'Shipping and Handling and other charges may apply',
    'TEXT_BACKORDERED' => '* This item is on backorder',
    'TEXT_EXPAND_ALL_PRODUCTS' => 'Expand All Products',
    'TEXT_ITEMS_IN_CART' => 'You have <strong>%s</strong> item(s) in your cart',

    'OPRC_LOGIN_VALIDATION_ERROR_MESSAGE' => 'Please correct the highlighted fields',
    'OPRC_NO_ADDRESS_ERROR_MESSAGE' => 'Please add an address to your order.',
    'OPRC_CONFIRMATION_LOAD_ERROR_MESSAGE' => 'We were unable to load the confirmation step. Please refresh the page and try again.',
    'OPRC_CHANGE_ADDRESS_LOAD_ERROR_MESSAGE' => 'We were unable to load the address form. Please close this window and try again.',

    'ENTRY_EMAIL_ADDRESS' => 'Email:',
    'ENTRY_EMAIL_ADDRESS_CONFIRM' => 'Confirm email:',

    'ENTRY_SECURITY_CHECK' => 'Security Check:',
    'ENTRY_SECURITY_CHECK_ERROR' => 'The Security Check code wasn\'t typed correctly. Try again.',
    'ENTRY_SECURITY_CHECK_RECAPTCHA_REQUIRED' => 'Please verify that you are not a robot by completing the security check.',
    'ENTRY_SECURITY_CHECK_RECAPTCHA_MISCONFIGURED' => 'The security check is temporarily unavailable. Please contact us so we can assist with your order.',
    'ENTRY_SECURITY_CHECK_RECAPTCHA_UNAVAILABLE' => 'We were unable to validate the security check. Please try again.',

    'ERROR_SECURITY_ERROR' => 'There was a security error when trying to login.',

    'ENTRY_AUTOMATIC_LOGIN' => 'Stay signed in',

    'BUTTON_COWOA_ALT' => 'Continue',
    'BUTTON_APPLY_ALT' => 'Apply',
    'BUTTON_EDIT_CART_SMALL_ALT' => 'Edit cart',
    'BUTTON_CHANGE_ADDRESS_ALT' => 'Change address',
    'ENTRY_NEWSLETTER' => 'Subscribe to our newsletter',
    'PLEASE_SELECT' => 'Select',
    'ENTRY_DATE_OF_BIRTH_TEXT' => '*',

    // order steps
    'HEADING_STEP_1' => 'Sign In / Register',
    'HEADING_STEP_1_GUEST' => 'Sign In or Continue as Guest',
    'HEADING_STEP_2' => 'Billing & Shipping Address',
    'HEADING_STEP_2_NO_SHIPPING' => 'Billing Information',
    'HEADING_STEP_3' => 'Shipping & Payment Method',
    'HEADING_STEP_3_NO_SHIPPING' => 'Payment Method',
    'HEADING_STEP_4' => 'Checkout Confirmation',
    'HEADING_WELCOME' => 'Welcome %s!',

    // confirmation
    'TITLE_CONFIRM_CHECKOUT' => '<em>Final Step</em>',
    'TEXT_CONFIRM_CHECKOUT' => '- proceed to processing',
    'TITLE_CONTINUE_CHECKOUT_CONFIRMATION' => 'Continue',
    'TEXT_CONTINUE_CHECKOUT_CONFIRMATION' => '- confirm order.',
    'TEXT_OPRC_COMPLETE_PURCHASE' => 'Complete Purchase',
    'TITLE_CONTINUE_CHECKOUT_PROCEDURE' => '<em>Continue to checkout</em>',
    'TEXT_CONTINUE_CHECKOUT_PROCEDURE' => '- select shipping/payment.',

    'ENTRY_COPYBILLING' => 'Same as billing',
    'ENTRY_COPYBILLING_TEXT' => '',

    // OPRC OPTIONS
    'TABLE_HEADING_DROPDOWN' => 'Drop Down Heading',
    'TABLE_HEADING_GIFT_MESSAGE' => 'Gift Message',
    'TABLE_HEADING_OPRC_CHECKBOX' => 'Gift Receipt',
    'TEXT_FIELD_REQUIRED' => '<span class="fieldRequired">* required</span>',
    'TEXT_DROP_DOWN' => 'Select an option: ',
    'TEXT_OPRC_CHECKBOX' => 'Include gift receipt (prices not displayed)',

    // Maintenance
    'OPRC_DOWN_FOR_MAINTENANCE_TEXT_INFORMATION' => '<p>Our checkout system is currently down for maintenance while we make upgrades.<br />You may continue to browse the site and check back in a few minutes when the maintenance is completed.</p>',
    'OPRC_DOWN_FOR_MAINTENANCE_STATUS_TEXT' => 'Click the button below to check if the maintenance has been completed.',

    'OPRC_OPTIONAL' => '(Optional)',
    'TEXT_ITEM' => 'item',
    'TEXT_ITEMS' => 'items',
    'MODULE_PAYMENT_AUTHORIZENET_AIM_TEXT_POPUP_CVV_LINK' => '<span class="nmx-oprc-cvv">3 digit number on back of card <br>Amex: 4 digit number on front of card</span>',
    'MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK' => '<span class="nmx-oprc-cvv">3 digit number on back of card <br>Amex: 4 digit number on front of card</span>',
    'TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC' => 'By clicking the button below, I acknowledge that I have read and agree to the <a target="_blank" href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . '">terms and conditions</a>.',

    // Cart
    'BUTTON_REMOVE_OPRC_REMOVE_CHECKOUT' => 'Remove'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
// eof

