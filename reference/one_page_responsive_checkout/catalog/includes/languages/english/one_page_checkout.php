<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright Joseph Schilz
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */

define('NAVBAR_TITLE_1', 'Checkout');
define('NAVBAR_TITLE_1_CHECKOUT', 'Checkout');

define('TABLE_HEADING_BILLING_ADDRESS', 'Billing Address');
define('TABLE_HEADING_SHIPPING_ADDRESS', 'Shipping Address');
define('TABLE_HEADING_SHOPPING_CART', 'Shopping Cart');
define('TABLE_HEADING_PAYMENT_METHOD', 'Payment Method');
define('HEADING_STEP_3_COMMENTS', 'Order Comments');
define('TABLE_HEADING_SHIPPING_METHOD', 'Shipping Method');
define('TABLE_HEADING_SHIPPING_METHOD_NOT_REQUIRED', 'No Shipping Required');
define('TABLE_HEADING_CONTACT_DETAILS', 'Contact Details');
define('TABLE_HEADING_CONDITIONS', '<span class="termsconditions">Terms & Conditions</span>');
define('TABLE_HEADING_COMMENTS', 'Order Comments'); 

define('HEADING_TITLE', 'Secure Checkout');
define('HEADING_NEW_CUSTOMERS', 'New Customers');
define('HEADING_COWOA', 'Guest Checkout');
define('HEADING_RETURNING_CUSTOMER', 'Returning Customers'); 
define('HEADING_RETURNING_CUSTOMER_SPLIT', 'Returning Customers');
define('HEADING_CONFIDENCE', 'Shop With Confidence');

define('TEXT_RATHER_COWOA', 'For a faster checkout experience, we offer the option to checkout as a guest.<br />');
define('TEXT_COWOA_UNCHECKED', 'Check here to register an account.');
define('COWOA_HEADING', 'Guest Checkout');
define('TEXT_NEW_CUSTOMER_INTRODUCTION', 'Create an account');

define('HIDEREGISTRATION_CREATE_ACCOUNT', '<p>An account allows you to:</p>');
define('HIDEREGISTRATION_COWOA', '<p>Proceed to checkout, and you will have a chance to create an account in the next step.</p>');

define('TEXT_OPRC_LOGIN_INTRO', '');
define('TEXT_COWOA_LOGIN', '<p id="cowoaLogin">Guests who have previously ordered can register an account by clicking the "forgot your password?" link above.</p>');
define('TEXT_ACCOUNT_BENEFITS', '<ul id="accountBenefits"><li>Checkout faster</li><li>Save shipping addresses</li><li>Track your orders from your account</li><li>Review previous orders</li></ul>');
define('TEXT_COWOA_BENEFITS', '<ul id="guestBenefits"><li>Never have to remember your password</li><li>Track order status using email address and order number</li><li>Register a full account at any time</li></ul>');
define('REGULAR_HEADING', 'Register An Account'); 

define('TEXT_ENTER_SHIPPING_INFORMATION', 'This is currently the only shipping method available to use on this order.');  
define('TEXT_LEGEND_HEAD', 'Create a New Account');
define('TEXT_SELECT_PAYMENT_METHOD', 'Select your payment type');
define('TEXT_CHOOSE_SHIPPING_DESTINATION', ''); 
define('TEXT_CHOOSE_SHIPPING_METHOD', 'Please select the preferred shipping method to use on this order.'); 
define('TEXT_SELECTED_BILLING_DESTINATION', ''); 
define('TEXT_PASSWORD_FORGOTTEN', 'Forgot your password?');
define('TEXT_CONDITIONS_DESCRIPTION', '<span class="termsdescription">Please acknowledge the terms and conditions bound to this order by ticking the following box. The terms and conditions can be read <a href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . ' #conditions" target="_blank"><span class="pseudolink">here</span></a>.</span>');
define('TEXT_CONDITIONS_CONFIRM', '<span class="termsiagree">I have read and agreed to the terms and conditions bound to this order.</span>');
define('TEXT_PRIVACY_CONDITIONS_DESCRIPTION', 'Please acknowledge you agree with our privacy statement by ticking the following box. The privacy statement can be read <a href="' . zen_href_link(FILENAME_PRIVACY, '', 'SSL') . ' #privacy" target="blank"><span class="pseudolink">here</span></a>.'); 
define('TITLE_NO_SHIPPING_AVAILABLE', 'Not Available At This Time');
define('TEXT_NO_SHIPPING_AVAILABLE','<span class="alert">Sorry, we are not shipping to your region at this time.</span><br />Please contact us for alternate arrangements.');
define('TEXT_NO_PAYMENT_OPTIONS_AVAILABLE', 'Not Available At This Time');
define('TEXT_REQUIRED_INFORMATION_OPRC', '* required information');
define('TEXT_NEED_HELP', 'Need Help?');
define('TEXT_CONTACT_US_AT', 'Contact us at ');
define('TEXT_ORDER_TOTAL_DISCLAIMER', 'Shipping and Handling and other charges may apply');
define('TEXT_BACKORDERED', '* This item is on backorder');
define('TEXT_EXPAND_ALL_PRODUCTS', 'Expand All Products');
define('TEXT_ITEMS_IN_CART', 'You have <strong>%s</strong> item(s) in your cart');

define('OPRC_LOGIN_VALIDATION_ERROR_MESSAGE', 'Please correct the highlighted fields');
define('OPRC_NO_ADDRESS_ERROR_MESSAGE', 'Please add an address to your order.');

define('ENTRY_EMAIL_ADDRESS', 'Email:');
define('ENTRY_EMAIL_ADDRESS_CONFIRM', 'Confirm email:');

define('ENTRY_SECURITY_CHECK', 'Security Check:');
define('ENTRY_SECURITY_CHECK_ERROR', 'The Security Check code wasn\'t typed correctly. Try again.');

define('ERROR_SECURITY_ERROR', 'There was a security error when trying to login.');

define('ENTRY_AUTOMATIC_LOGIN', 'Stay signed in'); 

define('BUTTON_COWOA_ALT', 'Continue');
define('BUTTON_APPLY_ALT', 'Apply');
define('BUTTON_EDIT_CART_SMALL_ALT', 'Edit cart');
define('BUTTON_CHANGE_ADDRESS_ALT', 'Change address');
define('ENTRY_NEWSLETTER', 'Subscribe to our newsletter');
define('PLEASE_SELECT', 'Select');
define('ENTRY_DATE_OF_BIRTH_TEXT', '*');

// order steps
define('HEADING_STEP_1', 'Sign In / Register');
define('HEADING_STEP_1_GUEST', 'Sign In or Continue as Guest');
define('HEADING_STEP_2', 'Billing & Shipping Address');
define('HEADING_STEP_2_NO_SHIPPING', 'Billing Information'); 
define('HEADING_STEP_3', 'Shipping & Payment Method');
define('HEADING_STEP_3_NO_SHIPPING', 'Payment Method');
define('HEADING_STEP_4', 'Checkout Confirmation');
define('HEADING_WELCOME', 'Welcome %s!');
define('HEADING_SHIPPING_INFO', 'Shipping Info');

// confirmation
define('TITLE_CONFIRM_CHECKOUT', '<em>Final Step</em>');  
define('TEXT_CONFIRM_CHECKOUT', '- proceed to processing');
define('TITLE_CONTINUE_CHECKOUT_CONFIRMATION', 'Continue');
define('TEXT_CONTINUE_CHECKOUT_CONFIRMATION', '- confirm order.'); 
define('TITLE_CONTINUE_CHECKOUT_PROCEDURE', '<em>Continue to checkout</em>');
define('TEXT_CONTINUE_CHECKOUT_PROCEDURE', '- select shipping/payment.');

define('ENTRY_COPYBILLING', 'Same as billing');
define('ENTRY_COPYBILLING_TEXT', '');

// OPRC OPTIONS
define('TABLE_HEADING_DROPDOWN', 'Drop Down Heading');
define('TABLE_HEADING_GIFT_MESSAGE', 'Gift Message');
define('TABLE_HEADING_OPRC_CHECKBOX', 'Gift Receipt');
define('TEXT_FIELD_REQUIRED', '<span class="fieldRequired">* required</span>');
define('TEXT_DROP_DOWN', 'Select an option: ');
define('TEXT_OPRC_CHECKBOX', 'Include gift receipt (prices not displayed)');

// Maintenance
define('OPRC_DOWN_FOR_MAINTENANCE_TEXT_INFORMATION', '<p>Our checkout system is currently down for maintenance while we make upgrades.<br />You may continue to browse the site and check back in a few minutes when the maintenance is completed.</p>');
define('OPRC_DOWN_FOR_MAINTENANCE_STATUS_TEXT', 'Click the button below to check if the maintenance has been completed.');

define('OPRC_OPTIONAL', '(Optional)');
define('TEXT_ITEM', 'item');
define('TEXT_ITEMS', 'items');
define('MODULE_PAYMENT_AUTHORIZENET_AIM_TEXT_POPUP_CVV_LINK', '<span class="nmx-oprc-cvv">3 digit number on back of card <br>Amex: 4 digit number on front of card</span>');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK', MODULE_PAYMENT_AUTHORIZENET_AIM_TEXT_POPUP_CVV_LINK);
define('TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC', 'By clicking the button below, I acknowledge that I have read and agree to the <a target="_blank" href="' . zen_href_link(FILENAME_CONDITIONS, '', 'SSL') . '">terms and conditions</a>.');

// Cart
define('BUTTON_REMOVE_OPRC_REMOVE_CHECKOUT', 'Remove');

// eof

