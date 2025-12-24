<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[109][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_oprc_template_guard.php',
];
