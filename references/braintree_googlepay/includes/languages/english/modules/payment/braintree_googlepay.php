<?php
$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));

$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $languageCode . '/modules/payment/lang.braintree_googlepay.php';
if (!file_exists($languageFile)) {
    $languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/payment/lang.braintree_googlepay.php';
}

$define = [];
if (file_exists($languageFile)) {
    $define = include $languageFile;
    if (!is_array($define)) {
        $define = [];
    }
}

if (!function_exists('nmx_create_defines')) {
    function nmx_create_defines(array $definitions) {
        foreach ($definitions as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

nmx_create_defines($define);

if ($zc158) {
    return $define;
}
