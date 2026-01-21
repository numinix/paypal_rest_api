<?php
$define = [
    'VIRTUAL_PRODUCT_GUEST_ERROR' => 'Guest accounts are not eligible to purchase virtual products. Please sign-out and perform a password reset on your account for full registered access.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}