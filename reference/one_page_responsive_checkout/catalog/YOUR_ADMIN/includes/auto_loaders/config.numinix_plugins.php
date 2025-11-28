<?php
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

// use IonCube encoder version 8.3 (encoded for PHP 5.0) for PHP 5.0 up to before 7.0 (maximizing backward compatibility)
if (version_compare(phpversion(), '5.6.0', '<=')) {
    // endocded for PHP versions less than 5.6
    $autoLoadConfig[199][] = array('autoType'=>'class',
        'loadFile'=>'numinix_plugins_5.0.php',
        'classPath'=> DIR_WS_CLASSES
    );
} elseif (version_compare(phpversion(), '7.1.0', '<')) {
    // use IonCube encoder version 9.0 (encoded for PHP 5.6)
    $autoLoadConfig[199][] = array('autoType'=>'class',
       'loadFile'=>'numinix_plugins_5.6.php',
       'classPath'=>DIR_WS_CLASSES
    );
} elseif (version_compare(phpversion(), '8.0.0', '<')) {
    // use IonCube encoder version 10.2 (encoded for PHP 7.1)
    $autoLoadConfig[199][] = array('autoType'=>'class',
       'loadFile'=>'numinix_plugins_7.1.php',
       'classPath'=>DIR_WS_CLASSES
    );
}
