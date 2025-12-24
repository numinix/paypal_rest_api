<?php
$define = [
    'NAVBAR_TITLE' => 'Order Status',
    'NAVBAR_TITLE_1' => 'My Account',

    'HEADING_TITLE' => 'Lookup Order Information',

    'SUB_HEADING_TITLE' => 'Order Information',

    'HEADING_ORDER_NUMBER' => 'Order #%s',
    'HEADING_ORDER_DATE' => 'Order Date:',
    'HEADING_ORDER_TOTAL' => 'Order Total:',

    'HEADING_PRODUCTS' => 'Products',
    'HEADING_TAX' => 'Tax',
    'HEADING_TOTAL' => 'Total',
    'HEADING_QUANTITY' => 'Qty.',

    'HEADING_SHIPPING_METHOD' => 'Shipping Method',
    'HEADING_PAYMENT_METHOD' => 'Payment Method',

    'HEADING_SHIPPING_ADDRESS' => 'Shipping Address',
    'HEADING_PAYMENT_ADDRESS' => 'Billing Address',

    'HEADING_ORDER_HISTORY' => 'Status History &amp; Comments',
    'TEXT_NO_COMMENTS_AVAILABLE' => 'No comments available.',
    'TABLE_HEADING_STATUS_DATE' => 'Date',
    'TABLE_HEADING_STATUS_ORDER_STATUS' => 'Order Status',
    'TABLE_HEADING_STATUS_COMMENTS' => 'Comments',
    'QUANTITY_SUFFIX' => '&nbsp;x ',
    'ORDER_HEADING_DIVIDER' => '&nbsp;-&nbsp;',
    'TEXT_OPTION_DIVIDER' => '&nbsp;-&nbsp;',

    'ENTRY_EMAIL' => 'E-Mail Address:',
    'ENTRY_ORDER_NUMBER' => 'Order Number:',

    'ERROR_INVALID_EMAIL' => '<strong>Please enter a valid e-mail address.</strong><br /><br />',
    'ERROR_INVALID_ORDER' => '<strong>Please enter a valid order number.</strong><br /><br />',
    'ERROR_NO_MATCH' => '<strong>No match found for your entry.</strong><br /><br />',

    'TEXT_LOOKUP_INSTRUCTIONS' => 'To lookup the status of an order, please enter the order number and the e-mail address with which it was placed.',

    'FOOTER_DOWNLOAD' => 'You can also download your products at a later time at \'%s\'',
    'FOOTER_DOWNLOAD_COWOA' => 'You can download your products using the Order Status page until you reach max downloads or run out of time!',

    'BUTTON_IMAGE_PRINT' => 'button_print.gif',
    'BUTTON_PRINT_ALT' => 'Print'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
//eof
