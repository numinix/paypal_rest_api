<?php

// ADDITIONAL texts for COWOA admin

// DASHBOARD - HOME PAGE OF ADMIN - CUSTOMERS section
$define = [
    'BOX_TITLE_CUSTOMERS' => 'Customers',
    'BOX_ENTRY_CUSTOMERS_NORMAL' => '- Full Accounts :',
    'BOX_ENTRY_CUSTOMERS_COWOA' => '- Guest Checkout :',
    'BOX_ENTRY_CUSTOMERS_TOTAL' => 'Total Customer Accounts :',
    'BOX_ENTRY_CUSTOMERS_TOTAL_DISTINCT' => 'Total Distinct Customers :',
    'BOX_ENTRY_CUSTOMERS_COWOA_DISTINCT' => '- Without Accounts :',

    // DASHBOARD - new orders section
    'COWOA_WITHOUT_ACCOUNT' => '(Without Account)',

    // CUSTOMERS
    // Title of column in customer overview admin page
    'TABLE_HEADING_COWOA' => 'Guest Checkout',

    // Title of section in customer details admin page
    'COWOA_SECTION_HEADING' => 'Account Status',

    // Detail output on customer details page 
    'COWOA_STATUS_TRUE' => 'Guest Checkout',
    'COWOA_STATUS_FALSE' => 'Full Account Created'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
