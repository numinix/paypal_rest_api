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

if (!class_exists('notifier')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/LegacyNotifier.php';
}

if (!class_exists('zcDate')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/ZcDate.php';
}

if (!class_exists('sniffer')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/Sniffer.php';
}

if (!class_exists('shoppingCart')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/ShoppingCart.php';
}
if (!class_exists('cache')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/Cache.php';
}
$paypalRestCacheClass = class_exists('cache') ? 'cache' : 'PayPalRestCache';
if (!class_exists('currencies')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/Currencies.php';
}
if (!class_exists('template_func')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/TemplateFunc.php';
}
if (!class_exists('messageStack')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/MessageStack.php';
}

$autoLoadConfig[0][] = [
    'autoType' => 'include',
    'loadFile' => DIR_WS_INCLUDES . 'version.php',
];
//- notifier class loaded via psr4Autoload.php
$autoLoadConfig[0][] = [
    'autoType' => 'classInstantiate',
    'className' => 'notifier',
    'objectName' => 'zco_notifier',
];
$autoLoadConfig[0][] = [
    'autoType' => 'class',
    'loadFile' => 'class.phpmailer.php',
];
/**
 * Breakpoint 5.
 *
 * $zcDate = new zcDate(); ... will be re-initialized when/if the require_languages.php module is run.
 *
 */
//- zcDate class loaded via psr4Autoload.php
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
    'className' => $paypalRestCacheClass,
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
$autoLoadConfig[45][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_non_db_settings.php',
];
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
//- shoppingCart class loaded via psr4Autoload.php
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
//- currencies class loaded via psr4Autoload.php
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
 */
//- template_func class loaded via psr4Autoload.php
$autoLoadConfig[100][] = [
    'autoType' => 'classInstantiate',
    'className' => 'template_func',
    'objectName' => 'template',
];
/**
 * Breakpoint 110.
 *
 */
$autoLoadConfig[110][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_templates.php',
];
/**
 * Breakpoint 120.
 *
 */
$autoLoadConfig[120][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_currencies.php',
];
/**
 * Breakpoint 130.
 *
 * messageStack = new messageStack();
 *
 */
//- messageStack class loaded via psr4Autoload.php
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
