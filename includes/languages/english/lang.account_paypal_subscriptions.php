<?php
/**
 * Language definitions for the PayPal subscription management page.
 */

$define = [
    'NAVBAR_TITLE_1' => 'My Account',
    'NAVBAR_TITLE_2' => 'Subscriptions',

    'HEADING_TITLE' => 'Manage subscriptions',

    'TEXT_SUBSCRIPTIONS_DISABLED' => 'Subscriptions are currently unavailable. Please contact us if you need assistance with your billing.',
    'TEXT_SUBSCRIPTIONS_INTRO' => 'Review your active subscriptions, update saved payment methods, and pause or cancel billing directly from this page.',
    'TEXT_NO_SUBSCRIPTIONS' => 'You do not have any subscriptions yet. New subscriptions will appear here after checkout.',

    'TEXT_SUBSCRIPTION_STATUS_PENDING' => 'Pending setup',
    'TEXT_SUBSCRIPTION_STATUS_AWAITING_VAULT' => 'Awaiting saved card',
    'TEXT_SUBSCRIPTION_STATUS_SCHEDULED' => 'Scheduled',
    'TEXT_SUBSCRIPTION_STATUS_ACTIVE' => 'Active',
    'TEXT_SUBSCRIPTION_STATUS_PAUSED' => 'Paused',
    'TEXT_SUBSCRIPTION_STATUS_CANCELLED' => 'Cancelled',
    'TEXT_SUBSCRIPTION_STATUS_COMPLETE' => 'Completed',
    'TEXT_SUBSCRIPTION_STATUS_FAILED' => 'Failed',
    'TEXT_SUBSCRIPTION_STATUS_UNKNOWN' => 'Unknown',

    'TEXT_SUBSCRIPTION_PERIOD_DAY' => 'day',
    'TEXT_SUBSCRIPTION_PERIOD_DAY_PLURAL' => 'days',
    'TEXT_SUBSCRIPTION_PERIOD_WEEK' => 'week',
    'TEXT_SUBSCRIPTION_PERIOD_WEEK_PLURAL' => 'weeks',
    'TEXT_SUBSCRIPTION_PERIOD_MONTH' => 'month',
    'TEXT_SUBSCRIPTION_PERIOD_MONTH_PLURAL' => 'months',
    'TEXT_SUBSCRIPTION_PERIOD_YEAR' => 'year',
    'TEXT_SUBSCRIPTION_PERIOD_YEAR_PLURAL' => 'years',
    'TEXT_SUBSCRIPTION_PERIOD_SEMI_MONTH' => 'semi-month',
    'TEXT_SUBSCRIPTION_PERIOD_SEMI_MONTH_PLURAL' => 'semi-months',

    'TEXT_SUBSCRIPTION_INTERVAL_TEMPLATE' => '%1$d %2$s',
    'TEXT_SUBSCRIPTION_SCHEDULE_TEMPLATE' => 'Every %s',
    'TEXT_SUBSCRIPTION_TOTAL_CYCLES_INFINITE' => 'Renews until cancelled',
    'TEXT_SUBSCRIPTION_TOTAL_CYCLES_SINGLE' => 'Ends after 1 payment',
    'TEXT_SUBSCRIPTION_TOTAL_CYCLES_PLURAL' => 'Ends after %d payments',
    'TEXT_SUBSCRIPTION_TRIAL_TEMPLATE' => '%1$s for %2$d payment(s)',
    'TEXT_SUBSCRIPTION_DATETIME_FORMAT' => 'M j, Y g:i a',

    'TEXT_SUBSCRIPTION_VAULT_STATUS_ACTIVE' => 'Active',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_INACTIVE' => 'Inactive',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_CANCELLED' => 'Cancelled',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_EXPIRED' => 'Expired',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_SUSPENDED' => 'Suspended',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_PENDING' => 'Pending',
    'TEXT_SUBSCRIPTION_VAULT_STATUS_UNKNOWN' => 'Status unknown',

    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_NONE_SELECTED' => 'No payment method linked yet.',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_UNKNOWN' => 'Saved payment method',
    'TEXT_SUBSCRIPTION_CARD_ENDING_IN' => 'Ending in %s',
    'TEXT_SUBSCRIPTION_CARD_EXPIRY' => 'Expires %s',

    'TEXT_SUBSCRIPTION_API_GENERIC_ERROR' => 'We were unable to contact PayPal. Please try again or contact us for help.',

    'TEXT_SUBSCRIPTION_NOT_FOUND' => 'The selected subscription could not be found.',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_ERROR' => 'We were unable to update the payment method. Please choose a saved card and try again.',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_UPDATED' => 'The subscription payment method has been updated.',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_UNLINKED' => 'The subscription is no longer linked to a saved payment method.',

    'TEXT_SUBSCRIPTION_CANCEL_NOTE' => 'Cancelled by customer from the storefront.',
    'TEXT_SUBSCRIPTION_CANCEL_ERROR' => 'We could not cancel the subscription: %s',
    'TEXT_SUBSCRIPTION_CANCEL_SUCCESS' => 'The subscription has been cancelled.',

    'TEXT_SUBSCRIPTION_SUSPEND_NOTE' => 'Paused by customer from the storefront.',
    'TEXT_SUBSCRIPTION_SUSPEND_ERROR' => 'We could not pause the subscription: %s',
    'TEXT_SUBSCRIPTION_SUSPEND_SUCCESS' => 'The subscription has been paused.',

    'TEXT_SUBSCRIPTION_RESUME_NOTE' => 'Reactivated by customer from the storefront.',
    'TEXT_SUBSCRIPTION_RESUME_ERROR' => 'We could not resume the subscription: %s',
    'TEXT_SUBSCRIPTION_RESUME_SUCCESS' => 'The subscription has been reactivated.',

    'TEXT_SUBSCRIPTION_NO_REMOTE_ID' => 'This subscription is not linked to a PayPal agreement yet.',
    'TEXT_SUBSCRIPTION_REFRESH_ERROR' => 'We could not refresh the subscription details: %s',
    'TEXT_SUBSCRIPTION_REFRESH_SUCCESS' => 'The subscription details have been refreshed.',

    'TEXT_SUBSCRIPTION_REMOTE_CYCLE_DEFAULT' => 'Billing',
    'TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY' => '%1$s cycle — %2$d of %3$d completed',
    'TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY_OPEN' => '%1$s cycle — %2$d completed',

    'TEXT_SUBSCRIPTION_PLAN_ID' => 'PayPal plan: %s',
    'TEXT_SUBSCRIPTION_AMOUNT_LABEL' => 'Billing amount',
    'TEXT_SUBSCRIPTION_SCHEDULE_LABEL' => 'Billing schedule',
    'TEXT_SUBSCRIPTION_TOTAL_CYCLES_LABEL' => 'Total billing cycles',
    'TEXT_SUBSCRIPTION_TRIAL_LABEL' => 'Trial period',
    'TEXT_SUBSCRIPTION_CREATED_LABEL' => 'Created',
    'TEXT_SUBSCRIPTION_UPDATED_LABEL' => 'Last updated',
    'TEXT_SUBSCRIPTION_ORDER_LABEL' => 'Order reference',
    'TEXT_SUBSCRIPTION_ORDER_VALUE' => '#%d',

    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_HEADING' => 'Payment method',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_SELECT' => 'Choose a saved card',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_UPDATE_BUTTON' => 'Update payment method',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_NO_OPTIONS' => 'You have not saved any cards yet.',
    'TEXT_SUBSCRIPTION_PAYMENT_METHOD_MANAGE_LINK' => 'Manage saved cards',

    'TEXT_SUBSCRIPTION_REMOTE_SECTION_HEADING' => 'PayPal subscription',
    'TEXT_SUBSCRIPTION_REMOTE_ID' => 'Subscription ID',
    'TEXT_SUBSCRIPTION_REMOTE_STATUS' => 'PayPal status',
    'TEXT_SUBSCRIPTION_REMOTE_NEXT_BILLING' => 'Next billing date',
    'TEXT_SUBSCRIPTION_REMOTE_LAST_PAYMENT' => 'Last payment date',
    'TEXT_SUBSCRIPTION_REMOTE_LAST_PAYMENT_AMOUNT' => 'Last payment amount',
    'TEXT_SUBSCRIPTION_REMOTE_CYCLE_SUMMARY_HEADING' => 'Cycle progress',

    'TEXT_SUBSCRIPTION_ACTIONS_HEADING' => 'Actions',
    'TEXT_SUBSCRIPTION_ACTION_PAUSE' => 'Pause billing',
    'TEXT_SUBSCRIPTION_ACTION_CANCEL' => 'Cancel subscription',
    'TEXT_SUBSCRIPTION_ACTION_RESUME' => 'Resume billing',
    'TEXT_SUBSCRIPTION_ACTION_REFRESH' => 'Refresh status',

    'TEXT_SUBSCRIPTION_CONFIRM_SUSPEND' => 'Pause this subscription?',
    'TEXT_SUBSCRIPTION_CONFIRM_CANCEL' => 'Cancel this subscription? This action cannot be undone.',
    'TEXT_SUBSCRIPTION_CONFIRM_RESUME' => 'Resume this subscription?',
];

return $define;
