<?php

/**
 * autoloader array for webhooks
 *
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version  New in v2.2.0 $
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// This autoloader is intended only for webhook bootstrap requests.
// On storefront pages (e.g. checkout_shipping), loading webhook compatibility
// classes can conflict with core class loading and trigger redeclaration fatals.
if (($loaderPrefix ?? '') !== 'webhook') {
    return;
}

/**
 * Path to compatibility shims used as fallbacks when core class files are
 * unavailable.  Each shim contains a class_exists() guard so it is safe to
 * include even when the real class was already loaded by an earlier entry.
 */
$webhookCompatDir = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/';

/**
 * Breakpoint 0.
 *
 * Load class files and foundational helpers.
 *
 * require DIR_WS_INCLUDES . 'version.php';
 * require DIR_WS_CLASSES . 'class.base.php';       (1.5.x only)
 * require DIR_WS_CLASSES . 'class.notifier.php';
 * $zco_notifier = new notifier();
 * require DIR_WS_CLASSES . 'class.phpmailer.php';
 * require DIR_WS_CLASSES . 'shopping_cart.php';
 * require DIR_WS_CLASSES . 'currencies.php';
 * require DIR_WS_CLASSES . 'message_stack.php';
 * require DIR_WS_CLASSES . 'zcDate.php';
 * require DIR_WS_CLASSES . 'sniffer.php';
 * require DIR_WS_CLASSES . 'cache.php';
 * require DIR_WS_CLASSES . 'template_func.php';
 *
 * Compatibility shims are loaded after each core class so that the shim's
 * class_exists() guard can detect whether the core class was loaded
 * successfully. If the core file is missing the shim defines the class.
 */
$autoLoadConfig[0][] = [
    'autoType' => 'include',
    'loadFile' => DIR_WS_INCLUDES . 'version.php',
];

// --- base class (required by most Zen Cart 1.5.x core classes) ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.base.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'class.base.php',
    ];
}

// --- notifier ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.notifier.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'class.notifier.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'LegacyNotifier.php',
    'classPath' => $webhookCompatDir,
];
$autoLoadConfig[0][] = [
    'autoType' => 'classInstantiate',
    'className' => 'notifier',
    'objectName' => 'zco_notifier',
];

// --- phpmailer ---
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'class.phpmailer.php',
];

// --- zcDate ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'zcDate.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'zcDate.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'ZcDate.php',
    'classPath' => $webhookCompatDir,
];

// --- sniffer ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'sniffer.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'sniffer.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'Sniffer.php',
    'classPath' => $webhookCompatDir,
];

// --- shopping_cart ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'shopping_cart.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'shopping_cart.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'ShoppingCart.php',
    'classPath' => $webhookCompatDir,
];

// --- order ---
// Only load the compatibility shim; the core order class is loaded by the
// storefront bootstrap separately and pre-loading it here can cause class
// redeclaration fatals.
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'Order.php',
    'classPath' => $webhookCompatDir,
];

// --- cache ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'cache.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'cache.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'Cache.php',
    'classPath' => $webhookCompatDir,
];

// --- currencies ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'currencies.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'currencies.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'Currencies.php',
    'classPath' => $webhookCompatDir,
];

// --- message_stack ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'message_stack.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'message_stack.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'MessageStack.php',
    'classPath' => $webhookCompatDir,
];

// --- template_func ---
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'template_func.php')) {
    $autoLoadConfig[0][] = [
        'autoType' => 'class',
        'loadFile' => 'template_func.php',
    ];
}
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'TemplateFunc.php',
    'classPath' => $webhookCompatDir,
];

// Determine the cache class name to use for instantiation at breakpoint 30.
// The core cache class or the compatibility PayPalRestCache class — whichever
// was successfully loaded above — will be used.
$webhookCacheClassName = file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'cache.php') ? 'cache' : 'PayPalRestCache';

/**
 * Breakpoint 5.
 *
 * $zcDate = new zcDate(); ... will be re-initialized when/if the require_languages.php module is run.
 *
 */
$autoLoadConfig[5][] = [
    'autoType' => 'classInstantiate',
    'className' => 'zcDate',
    'objectName' => 'zcDate',
];
/**
 * Breakpoint 30.
 *
 * $zc_cache = new cache();
 *
 */
$autoLoadConfig[30][] = [
    'autoType' => 'classInstantiate',
    'className' => $webhookCacheClassName,
    'objectName' => 'zc_cache',
];
/**
 * Breakpoint 40.
 *
 * require 'includes/init_includes/init_db_config_read.php';
 *
 */
$autoLoadConfig[40][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_db_config_read.php',
];
/**
 * Breakpoint 45.
 *
 * require 'includes/init_includes/init_non_db_settings.php';
 *
 */
if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/init_non_db_settings.php')) {
    $autoLoadConfig[45][] = [
        'autoType' => 'init_script',
        'loadFile' => 'init_non_db_settings.php',
    ];
}
/**
 * Breakpoint 50.
 *
 * $sniffer = new sniffer();
 * require 'includes/init_includes/init_sefu.php';
 */
$autoLoadConfig[50][] = [
    'autoType' => 'classInstantiate',
    'className' => 'sniffer',
    'objectName' => 'sniffer',
];
$autoLoadConfig[50][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_sefu.php',
];
/**
 * Breakpoint 60.
 *
 * require 'includes/init_includes/init_general_funcs.php';
 * require 'includes/init_includes/init_tlds.php';
 *
 */
$autoLoadConfig[60][] = [
    'autoType' => 'require',
    'loadFile' => DIR_WS_FUNCTIONS . 'functions_osh_update.php',
];
$autoLoadConfig[60][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_general_funcs.php',
];
$autoLoadConfig[60][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_tlds.php',
];
/**
 * Breakpoint 70.
 *
 * require 'includes/init_includes/init_sessions.php';
 *
 */
$autoLoadConfig[70][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_sessions.php',
];
/**
 * Breakpoint 80.
 *
 * if (!$_SESSION['cart']) $_SESSION['cart'] = new shoppingCart();
 *
 */
$autoLoadConfig[80][] = [
    'autoType' => 'classInstantiate',
    'className' => 'shoppingCart',
    'objectName' => 'cart',
    'checkInstantiated' => true,
    'classSession' => true,
];
/**
 * Breakpoint 90.
 *
 * currencies = new currencies();
 *
 */
$autoLoadConfig[90][] = [
    'autoType' => 'classInstantiate',
    'className' => 'currencies',
    'objectName' => 'currencies',
];
/**
 * Breakpoints 95,96.
 *
 * require 'includes/init_includes/init_languages.php';
 * require 'includes/init_includes/init_sanitize.php';
 *
 */
$autoLoadConfig[95][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_languages.php',
];
$autoLoadConfig[96][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_sanitize.php',
];
/**
 * Breakpoint 100.
 *
 * $template = new template_func();
 *
 */
$autoLoadConfig[100][] = [
    'autoType' => 'classInstantiate',
    'className' => 'template_func',
    'objectName' => 'template',
];
/**
 * Breakpoint 110.
 *
 * require 'includes/init_includes/init_templates.php';
 *
 */
$autoLoadConfig[110][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_templates.php',
];
/**
 * Breakpoint 120.
 *
 * require 'includes/init_includes/init_currencies.php';
 *
 */
$autoLoadConfig[120][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_currencies.php',
];
/**
 * Breakpoint 130.
 *
 * $messageStack = new messageStack();
 *
 */
$autoLoadConfig[130][] = [
    'autoType' => 'classInstantiate',
    'className' => 'messageStack',
    'objectName' => 'messageStack',
];
/**
 * Breakpoint 175.
 *
 * require 'includes/init_includes/init_observers.php';
 *
 */
$autoLoadConfig[175][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_observers.php',
];
