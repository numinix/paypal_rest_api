<?php
/**
 * Autoloader configuration for the login check AJAX endpoint.
 *
 * These init scripts mirror the pieces of application_top.php we rely on here.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$versionFile = dirname(__DIR__) . '/version.php';
if (file_exists($versionFile)) {
    require_once $versionFile;
}

$zcVersionMajor = defined('PROJECT_VERSION_MAJOR') ? PROJECT_VERSION_MAJOR : '2';
$zcVersionMinor = defined('PROJECT_VERSION_MINOR') ? PROJECT_VERSION_MINOR : '0';

if (!function_exists('oprc_login_check_add_init_script')) {
    /**
     * Adds an init script to the autoload configuration if the target file exists.
     *
     * @param array  $config    The autoload configuration array passed by reference.
     * @param int    $sortOrder The autoloader sort order.
     * @param string $fileName  The init script to register.
     */
    function oprc_login_check_add_init_script(array &$config, $sortOrder, $fileName)
    {
        $initPath = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'init_includes/' . $fileName;
        if (file_exists($initPath)) {
            $config[$sortOrder][] = [
                'autoType' => 'init_script',
                'loadFile' => $fileName,
            ];
        }
    }
}

$autoloaderStack = [];

if (version_compare($zcVersionMajor . '.' . $zcVersionMinor, '2.0.0', '>=')) {
    $autoloaderStack = [
        10 => 'init_file_db_names.php',
        20 => 'init_database.php',
        30 => 'init_db_config_read.php',
        55 => 'init_general_funcs.php',
        70 => 'init_sessions.php',
    ];
} else {
    $autoloaderStack = [
        10 => 'init_file_db_names.php',
        20 => 'init_database.php',
        30 => 'init_db_config_read.php',
        55 => 'init_general_funcs.php',
        70 => 'init_sessions.php',
    ];
}

$autoLoadConfig[0][] = [
    'autoType' => 'classInstantiate',
    'className' => 'sniffer',
    'objectName' => 'sniffer',
];
$autoLoadConfig[50][] = array('autoType' => 'class',
    'loadFile' => 'sniffer.php');

foreach ($autoloaderStack as $sortOrder => $fileName) {
    oprc_login_check_add_init_script($autoLoadConfig, $sortOrder, $fileName);
}

// eof
