<?php

if (!defined('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS')) {
    define('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS', 'account_paypal_subscriptions');
}

$loaders[] = [
    'conditions' => [
        'pages' => [FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS],
    ],
    'css_files' => [
        'account_paypal_subscriptions.css' => 99,
    ],
];
