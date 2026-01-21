<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2005 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: checkout_payment_address.php 2315 2005-11-07 08:41:46Z drbyte $
 */

$define = [
    'NAVBAR_TITLE_1' => 'Checkout',
    'NAVBAR_TITLE_2' => 'Change Billing Address',

    'HEADING_TITLE' => 'Change the Billing Information',

    'TABLE_HEADING_PAYMENT_ADDRESS' => 'Billing Address',
    'TEXT_SELECTED_PAYMENT_DESTINATION' => 'This is the current billing address. Please make sure it matches the information on your credit card statement or correct it using the form below.',
    'TITLE_PAYMENT_ADDRESS' => 'Billing Address',
    'OPRC_OPTIONAL' => 'Optional',

    'TEXT_SELECT_OTHER_PAYMENT_DESTINATION' => 'Please select the preferred billing address if the invoice to this order is to be delivered elsewhere.',
    'TITLE_PLEASE_SELECT' => 'Change the Billing Address for This Order',

    'TABLE_HEADING_NEW_PAYMENT_ADDRESS' => 'Address Book',

    'TEXT_OPRC_ADDRESS_LOOKUP_BUTTON' => 'Find address',

    'TITLE_CONTINUE_CHECKOUT_PROCEDURE' => '<strong>Continue</strong>',
    'TEXT_CONTINUE_CHECKOUT_PROCEDURE' => '- to payment method.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}