<?php
/**
 * @package languageDefines
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
$define = [
    'NAVBAR_TITLE_1' => 'Sign In / Register',
    'NAVBAR_TITLE_2' => 'Reset Password',
    'HEADING_TITLE' => 'Forgot your password?',
    'TEXT_MAIN' => 'Enter the email address associated with your account and we\'ll email you a link to reset your password.',
    'TEXT_PASSWORD_FORGOTTEN_HEADING' => 'Reset your password',
    'TEXT_PASSWORD_FORGOTTEN_INSTRUCTIONS' => 'Enter the email address linked to your account and we\'ll send reset instructions to your inbox.',
    'TEXT_PASSWORD_FORGOTTEN_SUBMIT' => 'Submit',
    'TEXT_PASSWORD_FORGOTTEN_RETURN' => 'Back to sign in',
    'TEXT_PASSWORD_FORGOTTEN_PROCESSING' => 'Processing your request…',
    'TEXT_PASSWORD_FORGOTTEN_ERROR' => 'We were unable to process your request. Please try again.',
    'SUCCESS_PASSWORD_SENT' => 'If the email address you entered matches an account, we\'ll email you a link to reset your password.'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
// eof
