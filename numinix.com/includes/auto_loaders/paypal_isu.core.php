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

// Use Zen Cart's version to determine which autoload configuration to run.
$zcVersionMajor = defined('PROJECT_VERSION_MAJOR') ? PROJECT_VERSION_MAJOR : '2';
$zcVersionMinor = defined('PROJECT_VERSION_MINOR') ? PROJECT_VERSION_MINOR : '0';

if (version_compare($zcVersionMajor . '.' . $zcVersionMinor, '2.0.0', '>=')) {
    // Zen Cart v2.0+ trimmed configuration.
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
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.phpmailer.php')) {
        $autoLoadConfig[0][] = [
            'autoType' => 'class',
            'loadFile' => 'class.phpmailer.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'language.php')) {
        $autoLoadConfig[0][] = [
            'autoType' => 'class',
            'loadFile' => 'language.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'shopping_cart.php')) {
        $autoLoadConfig[0][] = [
            'autoType' => 'class',
            'loadFile' => 'shopping_cart.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php')) {
        $autoLoadConfig[0][] = [
            'autoType' => 'class',
            'loadFile' => 'currencies.php',
        ];
    }
    // NOTE: messageStack is intentionally not loaded here. The standalone API endpoint
    // (paypal_onboarding.php) does not use messageStack, and loading it causes a fatal
    // error because messageStack depends on $template->get_template_dir() which is not
    // initialized in this minimal bootstrap context.
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'zcDate.php')) {
        $autoLoadConfig[0][] = [
            'autoType' => 'class',
            'loadFile' => 'zcDate.php',
        ];
    }

    $autoLoadConfig[5][] = [
        'autoType' => 'classInstantiate',
        'className' => 'zcDate',
        'objectName' => 'zcDate',
    ];

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_db_config_read.php')) {
        $autoLoadConfig[40][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_db_config_read.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_non_db_settings.php')) {
        $autoLoadConfig[45][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_non_db_settings.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_general_funcs.php')) {
        $autoLoadConfig[60][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_general_funcs.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sessions.php')) {
        $autoLoadConfig[70][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_sessions.php',
        ];
    }
    $autoLoadConfig[80][] = [
        'autoType' => 'classInstantiate',
        'className' => 'shoppingCart',
        'objectName' => 'cart',
        'checkInstantiated' => true,
        'classSession' => true,
    ];
    $autoLoadConfig[90][] = [
        'autoType' => 'classInstantiate',
        'className' => 'currencies',
        'objectName' => 'currencies',
    ];
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sanitize.php')) {
        $autoLoadConfig[96][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_sanitize.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_languages.php')) {
        $autoLoadConfig[110][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_languages.php',
        ];
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_currencies.php')) {
        $autoLoadConfig[120][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_currencies.php',
        ];
    }
    // NOTE: messageStack instantiation is intentionally omitted here. See comment above
    // regarding messageStack class loading - it's not needed by the API endpoint.
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_customer_auth.php')) {
        $autoLoadConfig[135][] = [
            'autoType' => 'init_script',
            'loadFile' => 'init_customer_auth.php',
        ];
    }

    // Additional PayPal loaders may be appended here.
} else {
    // Zen Cart 1.5.x (through 1.5.8) - retain legacy configuration.
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.base.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'class.base.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.notifier.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'class.notifier.php');
    }
    $autoLoadConfig[0][] = array('autoType' => 'classInstantiate',
        'className' => 'notifier',
        'objectName' => 'zco_notifier'); // enables notification/observer hooks
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.phpmailer.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'class.phpmailer.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'template_func.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'template_func.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'language.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'language.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'cache.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'cache.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'sniffer.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'sniffer.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'shopping_cart.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'shopping_cart.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'navigation_history.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'navigation_history.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'currencies.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'message_stack.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'message_stack.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'breadcrumb.php')) {
        $autoLoadConfig[0][] = array('autoType' => 'class',
            'loadFile' => 'breadcrumb.php');
    }
    /**
     * Breakpoint 10.
     *
     * require('includes/init_includes/init_file_db_names.php');
     * require('includes/init_includes/init_database.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_file_db_names.php')) {
        $autoLoadConfig[10][] = array('autoType' => 'init_script',
            'loadFile' => 'init_file_db_names.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_database.php')) {
        $autoLoadConfig[10][] = array('autoType' => 'init_script',
            'loadFile' => 'init_database.php');
    }
    /**
     * Breakpoint 30.
     *
     * $zc_cache = new cache();
     *
     */
    $autoLoadConfig[30][] = array('autoType' => 'classInstantiate',
        'className' => 'cache',
        'objectName' => 'zc_cache');
    /**
     * Breakpoint 40.
     *
     * require('includes/init_includes/init_db_config_read.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_db_config_read.php')) {
        $autoLoadConfig[40][] = array('autoType' => 'init_script',
            'loadFile' => 'init_db_config_read.php');
    }
    /**
     * Breakpoint 50.
     *
     * $sniffer = new sniffer();
     * require('includes/init_includes/init_sefu.php');
     */
    $autoLoadConfig[50][] = array('autoType' => 'classInstantiate',
        'className' => 'sniffer',
        'objectName' => 'sniffer');

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sefu.php')) {
        $autoLoadConfig[50][] = array('autoType' => 'init_script',
            'loadFile' => 'init_sefu.php');
    }
    /**
     * Breakpoint 60.
     *
     * require('includes/init_includes/init_general_funcs.php');
     * require('includes/init_includes/init_tlds.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_general_funcs.php')) {
        $autoLoadConfig[60][] = array('autoType' => 'init_script',
            'loadFile' => 'init_general_funcs.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_tlds.php')) {
        $autoLoadConfig[60][] = array('autoType' => 'init_script',
            'loadFile' => 'init_tlds.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_non_db_settings.php')) {
        $autoLoadConfig[60][] = array('autoType' => 'init_script',
            'loadFile' => 'init_non_db_settings.php');
    }

    /**
     * Breakpoint 70.
     *
     * require('includes/init_includes/init_sessions.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sessions.php')) {
        $autoLoadConfig[70][] = array('autoType' => 'init_script',
            'loadFile' => 'init_sessions.php');
    }
    /**
     * Breakpoint 80.
     *
     * if(!$_SESSION['cart']) $_SESSION['cart'] = new shoppingCart();
     *
     */
    $autoLoadConfig[80][] = array('autoType' => 'classInstantiate',
        'className' => 'shoppingCart',
        'objectName' => 'cart',
        'checkInstantiated' => true,
        'classSession' => true);
    /**
     * Breakpoint 90.
     *
     * currencies = new currencies();
     *
     */
    $autoLoadConfig[90][] = array('autoType' => 'classInstantiate',
        'className' => 'currencies',
        'objectName' => 'currencies');
    /**
     * Breakpoint 100.
     *
     * require('includes/init_includes/init_sanitize.php');
     * $template = new template_func();
     *
     */
    $autoLoadConfig[100][] = array('autoType' => 'classInstantiate',
        'className' => 'template_func',
        'objectName' => 'template');
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_sanitize.php')) {
        $autoLoadConfig[100][] = array('autoType' => 'init_script',
            'loadFile' => 'init_sanitize.php');
    }
    /**
     * Breakpoint 110.
     *
     * require('includes/init_includes/init_languages.php');
     * require('includes/init_includes/init_templates.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_languages.php')) {
        $autoLoadConfig[110][] = array('autoType' => 'init_script',
            'loadFile' => 'init_languages.php');
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_templates.php')) {
        $autoLoadConfig[110][] = array('autoType' => 'init_script',
            'loadFile' => 'init_templates.php');
    }
    /**
     * Breakpoint 120.
     *
     * require('includes/init_includes/init_currencies.php');
     *
     */
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_currencies.php')) {
        $autoLoadConfig[120][] = array('autoType' => 'init_script',
            'loadFile' => 'init_currencies.php');
    }
    /**
     * Breakpoint 130.
     *
     * messageStack = new messageStack();
     *
     */
    $autoLoadConfig[130][] = array('autoType' => 'classInstantiate',
        'className' => 'messageStack',
        'objectName' => 'messageStack');

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_customer_auth.php')) {
        $autoLoadConfig[130][] = array('autoType' => 'init_script',
            'loadFile' => 'init_customer_auth.php');
    }

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_cart_handler.php')) {
        $autoLoadConfig[140][] = array('autoType' => 'init_script',
            'loadFile' => 'init_cart_handler.php');
    }

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_special_funcs.php')) {
        $autoLoadConfig[150][] = array('autoType' => 'init_script',
            'loadFile' => 'init_special_funcs.php');
    }

    $autoLoadConfig[160][] = array('autoType' => 'classInstantiate',
        'className' => 'breadcrumb',
        'objectName' => 'breadcrumb');
    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_category_path.php')) {
        $autoLoadConfig[160][] = array('autoType' => 'init_script',
            'loadFile' => 'init_category_path.php');
    }

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_add_crumbs.php')) {
        $autoLoadConfig[170][] = array('autoType' => 'init_script',
            'loadFile' => 'init_add_crumbs.php');
    }

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_observers.php')) {
        $autoLoadConfig[175][] = array('autoType' => 'init_script',
            'loadFile' => 'init_observers.php');
    }

    if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_header.php')) {
        $autoLoadConfig[180][] = array('autoType' => 'init_script',
            'loadFile' => 'init_header.php');
    }
}