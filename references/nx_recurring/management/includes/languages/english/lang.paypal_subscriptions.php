<?php
/**
 * Language definitions for the PayPal Subscriptions / Recurring Orders management page.
 */

$define = [
    'HEADING_TITLE' => 'PayPal Subscriptions / Recurring Orders',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_PROFILE_ID' => 'Profile ID',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_CUSTOMER_NAME' => 'Name',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_DESCRIPTION' => 'Description',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_ORDERS_ID' => 'Orders ID',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_START_DATE' => 'Start Date',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_BILLING_DATE' => 'Next Billing Date',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_EXPIRATION_DATE' => 'Expiration Date',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_COMPLETED' => 'Payments Completed',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_REMAINING' => 'Payments Remaining',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_OVERDUE_BALANCE' => 'Overdue Balance',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENT_METHOD' => 'Payment Method',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_STORE_CREDIT' => 'Store Credit Balance',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_LAST_REFRESH' => 'Last Refresh',
    'TABLE_HEADING_PAYPAL_SUBSCRIPTION_STATUS' => 'Status',
    'BUTTON_PAYPAL_SUBSCRIPTION_REFRESH' => 'Update status',
    'TEXT_PAYPAL_SUBSCRIPTION_REFRESH_SUCCESS' => 'Subscription details have been updated.',
    'TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED' => 'We were unable to refresh this subscription.',
    'TEXT_PAYPAL_SUBSCRIPTION_REFRESH_TOKEN_ERROR' => 'Unable to refresh without a valid security token.',
    'TEXT_PAYPAL_SUBSCRIPTION_REFRESH_MISSING_CONTEXT' => 'Unable to determine which subscription to refresh.',
    'TEXT_PAYPAL_SUBSCRIPTION_REFRESH_MISSING_IDENTIFIERS' => 'Subscription identifiers are missing for this row.',
    'TEXT_PAYPAL_SUBSCRIPTION_NOT_FOUND' => 'This customer does not have any active subscriptions',
];

return $define;
