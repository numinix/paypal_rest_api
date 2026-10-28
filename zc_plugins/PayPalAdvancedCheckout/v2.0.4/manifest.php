<?php
return [
    'pluginKey'         => 'PayPalAdvancedCheckout',
    'pluginVersion'     => 'v2.0.0',
    'pluginName'        => 'PayPal Advanced Checkout',
    'pluginDescription' => 'PayPal REST Advanced Checkout payment modules for Zen Cart (paypalac and companion wallets). Installing removes any prior non-encapsulated overlay copies of this plugin while preserving existing Modules → Payment configuration.',
    'pluginAuthor'      => 'Numinix',
    // Set to 0 until a Zen Cart plugins-library ID exists.
    'pluginId'          => 0,
    'pluginDateAdded'   => '2026-08-27',
    'zcVersions'        => ['v201', 'v210', 'v220'],
    'changelog'         => 'https://github.com/numinix/paypal_rest_api/blob/main/CHANGELOG.md',
    'github_repo'       => 'https://github.com/numinix/paypal_rest_api',
    'pluginGroups'      => [],
    'removesUnencapsulatedVersion' => true,
];
