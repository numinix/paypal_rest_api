<?php
/**
 * Auto-loader configuration for the Numinix PayPal Signup utilities.
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[90][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_numinix_paypal_isu.php',
];
