<?php
/**
 * Auto-loader configuration for storefront telemetry logging.
 */

if (!defined('IS_ADMIN_FLAG')) {
    return;
}

$autoLoadConfig[200][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/class.numinix_paypal_signup.php',
    'classPath' => DIR_WS_CLASSES,
];

$autoLoadConfig[201][] = [
    'autoType' => 'classInstantiate',
    'className' => 'numinix_paypal_signup',
    'objectName' => 'numinix_paypal_signup',
];
