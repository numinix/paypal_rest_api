<?php
/**
 * autoloader array for PayPal ISU
 * filename: paypal_isu.core.php
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (!defined('USE_PCONNECT')) {
    define('USE_PCONNECT', 'false');
}

// Ensure the Zen Cart version file is loaded before checking constants used for branching.
$versionFile = dirname(__DIR__) . '/version.php';
if (file_exists($versionFile)) {
    require_once $versionFile;
}

// Zen Cart v2.0+ minimal configuration for PayPal ISU API.
// This configuration loads only what's strictly required by the standalone
// API endpoint (paypal_onboarding.php). The following are intentionally omitted:
//
// Classes not needed:
//   - language.php, shopping_cart.php, currencies.php, zcDate.php - not used by API
//   - message_stack.php - causes fatal error (requires $template->get_template_dir())
//
// Init scripts not needed:
//   - init_non_db_settings.php, init_sanitize.php - not required for API
//   - init_languages.php, init_currencies.php - not used by API
//   - init_customer_auth.php - not required for this API endpoint
//
// Class instantiations not needed:
//   - zcDate, shoppingCart, currencies, messageStack - not used by API

// Load notifier class for event notifications ($zco_notifier->notify())
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.notifier.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'class.notifier.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'classInstantiate',
    'className' => 'notifier',
    'objectName' => 'zco_notifier',
];

// Load database configuration (required for $db access and zen_get_configuration_key_value)
if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_db_config_read.php')) {
    $autoLoadConfig[40][] = [
        'autoType' => 'init_script',
        'loadFile' => 'init_db_config_read.php',
    ];
}

// Load general functions (provides zen_get_configuration_key_value, zen_mail, etc.)
if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_general_funcs.php')) {
    $autoLoadConfig[60][] = [
        'autoType' => 'init_script',
        'loadFile' => 'init_general_funcs.php',
    ];
}

// Load session handling (required for $_SESSION state management)
if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sessions.php')) {
    $autoLoadConfig[70][] = [
        'autoType' => 'init_script',
        'loadFile' => 'init_sessions.php',
    ];
}