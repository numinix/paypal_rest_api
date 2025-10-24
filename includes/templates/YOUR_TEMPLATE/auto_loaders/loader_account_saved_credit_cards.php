<?php
$cssFiles = [
    'account_saved_credit_cards.css' => 99,
];

$overrideRelativePath = 'css/ascc_overrides.css';
if (file_exists(DIR_FS_CATALOG . DIR_WS_TEMPLATE . $overrideRelativePath)) {
    $cssFiles[$overrideRelativePath] = 100;
}

$loaders[] = [
    'conditions' => [
        'pages' => [FILENAME_ACCOUNT_SAVED_CREDIT_CARDS],
    ],
    'jscript_files' => [
        'jquery/jquery-3.7.1.min.js' => 1,
        'jquery/jquery-migrate-3.4.1.min.js' => 2,
        'jquery/jq-saved.cards.js' => 3,
    ],
    'css_files' => $cssFiles,
];
