<?php
// braintree.php
$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return;
} else {
    include_once(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/extra_definitions/lang.braintree.php');
    nmx_create_defines($define);
}