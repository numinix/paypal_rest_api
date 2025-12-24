<?php
$define = [
  'TEXT_LOGIN_ERROR' => 'Error: Sorry, there is no match for that email address and/or password.',
  'TEXT_LOGIN_ERROR_GUEST' => 'You cannot login using a guest account.  Please register a full account.',
  'ENTRY_EMAIL_ADDRESS_ERROR_GUEST' => 'A guest account exists for this email address. To access the full account features, please perform a password reset to your email address.',
  'ENTRY_EMAIL_ADDRESS_ERROR_GUEST_FULL' => 'Permanent acccount is not allowed to guest checkout. Please login to your account or perform a password reset.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
