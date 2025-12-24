<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */

// this is used to display the text link in the "information" or other sidebox
$define = [
    'DATE_FORMAT' => 'm/d/Y',
    'DATE_FORMAT_DATE_PICKER' => 'yy-mm-dd',
    'DATE_FORMAT_LONG' => '%A %d %B, %Y',
    'DATE_FORMAT_SHORT' => '%m/%d/%Y',
    'DATE_FORMAT_SPIFFYCAL' => 'MM/dd/yyyy',
    'DATE_TIME_FORMAT' => '%%DATE_FORMAT_SHORT%%' . ' %H:%M:%S',
    'EMAIL_TEXT_SUBJECT' => 'Order Confirmation',
    'EMAIL_TEXT_HEADER' => 'Order Confirmation',
    'EMAIL_TEXT_FROM' => ' from ',
    'EMAIL_THANKS_FOR_SHOPPING' => 'Thanks for shopping with us today!',
    'EMAIL_DETAILS_FOLLOW' => 'The following are the details of your order.',
    'EMAIL_TEXT_ORDER_NUMBER' => 'Order Number:',
    'EMAIL_TEXT_INVOICE_URL' => 'Order Details:',
    'EMAIL_TEXT_INVOICE_URL_CLICK' => 'Click here for Order Details',
    'EMAIL_TEXT_DATE_ORDERED' => 'Date Ordered:',
    'EMAIL_TEXT_PRODUCTS' => 'Products',
    'EMAIL_TEXT_DELIVERY_ADDRESS' => 'Delivery Address',
    'EMAIL_TEXT_BILLING_ADDRESS' => 'Billing Address',
    'EMAIL_TEXT_PAYMENT_METHOD' => 'Payment Method',
    'EMAIL_SEPARATOR' => '------------------------------------------------------',
    'EMAIL_ORDER_NUMBER_SUBJECT' => ' No: ',
    'TEXT_OPRC_CHECKOUT_PROCESS_REDIRECTING' => 'Redirecting to the selected payment provider…',
    'TEXT_OPRC_CHECKOUT_PROCESS_SUBMIT' => 'Click the button below to continue.',
    'TEXT_OPRC_CHECKOUT_PROCESS_CONTINUE' => 'Continue',
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
//eof