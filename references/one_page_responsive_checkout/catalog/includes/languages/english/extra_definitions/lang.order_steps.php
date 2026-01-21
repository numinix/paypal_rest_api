<?php
$define = [
  'TEXT_ORDER_STEPS_GUEST' => 'Sign In or Continue as Guest',
  'TEXT_ORDER_STEPS_NON_GUEST' => 'Sign in or Create an Account',
  'TEXT_ORDER_STEPS_REGISTER' => 'Billing and Shipping',
  'TEXT_ORDER_STEPS_CHECKOUT' => 'Shipping and Payment',
  'TEXT_ORDER_STEPS_CONFIRMATION' => 'Review and Place Order'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}