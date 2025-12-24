<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: oprc_confirmation.php 3 2012-07-08 21:11:34Z numinix $
 */
$define = [
    'NAVBAR_TITLE_1' => 'Checkout',
    'NAVBAR_TITLE_2' => 'Confirmation',

    'HEADING_TITLE' => 'Checkout',

    'HEADING_BILLING_ADDRESS' => 'Billing Information',
    'HEADING_DELIVERY_ADDRESS' => 'Shipping Information',
    'HEADING_SHIPPING_METHOD' => 'Shipping Method:',
    'HEADING_PAYMENT_METHOD' => 'Payment Method:',
    'HEADING_PRODUCTS' => 'Shopping Cart Contents',
    'HEADING_TAX' => 'Tax',
    'HEADING_ORDER_COMMENTS' => 'Order Comments',
    // no comments entered
    'NO_COMMENTS_TEXT' => 'None',
    'TITLE_CONTINUE_CHECKOUT_PROCEDURE' => '<em>Final Step</em>',
    'TEXT_CONTINUE_CHECKOUT_PROCEDURE' => '- continue to confirm your order. Thank you!',
    'TEXT_STEP_THREE' => ' Step 3',
    'TEXT_STEP_FOUR' => ' Step 4',

    'OUT_OF_STOCK_CAN_CHECKOUT' => 'Products marked with ' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . ' are out of stock.<br />Items not in stock will be placed on backorder.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
// eof