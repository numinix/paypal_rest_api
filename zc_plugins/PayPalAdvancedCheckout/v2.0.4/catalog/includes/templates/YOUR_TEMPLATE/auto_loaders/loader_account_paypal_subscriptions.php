<?php

if (!defined('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS')) {
    define('FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS', 'account_paypal_subscriptions');
}

$cssFiles = [
    'account_paypal_subscriptions.css' => 99,
];

$overrideRelativePath = 'css/auto_loaders/account_paypal_subscriptions_overrides.css';
if (file_exists(DIR_FS_CATALOG . DIR_WS_TEMPLATE . $overrideRelativePath)) {
    $cssFiles[$overrideRelativePath] = 100;
}

$loaders[] = [
    'conditions' => [
        'pages' => [FILENAME_ACCOUNT_PAYPAL_SUBSCRIPTIONS],
    ],
    'css_files' => $cssFiles,
];
