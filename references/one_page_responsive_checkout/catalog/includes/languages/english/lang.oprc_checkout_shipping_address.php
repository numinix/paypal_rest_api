<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2005 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: checkout_shipping_address.php 2315 2005-11-07 08:41:46Z drbyte $
 */
$define = [
    'NAVBAR_TITLE' => 'Change Shipping Address',
    'NAVBAR_TITLE_1' => 'Checkout',
    'NAVBAR_TITLE_2' => 'Change Shipping Address',

    'HEADING_TITLE' => 'Change the Shipping Address',

    'TABLE_HEADING_SHIPPING_ADDRESS' => 'Shipping Address',
    'TITLE_SHIPPING_ADDRESS' => 'Current Shipping Address',

    'TABLE_HEADING_ADDRESS_BOOK_ENTRIES' => 'Address Book',
    'TITLE_PLEASE_SELECT' => 'Change the Shipping Address for This Order',
    'OPRC_OPTIONAL' => 'Optional',

    'TABLE_HEADING_NEW_SHIPPING_ADDRESS' => 'New Shipping Address',
    'TEXT_CREATE_NEW_SHIPPING_ADDRESS' => 'Please use the following form to create a new shipping address for use with this order.',
    'TEXT_SELECT_OTHER_SHIPPING_DESTINATION' => 'Please select the preferred shipping address if this order is to be delivered elsewhere.',

    'TITLE_CONTINUE_CHECKOUT_PROCEDURE' => '<strong>Continue</strong>',
    'TEXT_CONTINUE_CHECKOUT_PROCEDURE' => '- to shipping method.',

    'TEXT_OPRC_ADDRESS_LOOKUP_BUTTON' => 'Find address',

    'SET_AS_PRIMARY' => 'Set as Primary Address',
    'NEW_ADDRESS_TITLE' => 'Enter new address'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}