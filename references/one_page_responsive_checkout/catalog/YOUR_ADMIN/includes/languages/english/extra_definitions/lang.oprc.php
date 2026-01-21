<?php
$define = [
  'ENTRY_CHECKBOX' => 'Signature Option:',
  'ENTRY_TELEPHONE_NUMBER_SHIPPING' => 'Shipping Telephone Number:'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}