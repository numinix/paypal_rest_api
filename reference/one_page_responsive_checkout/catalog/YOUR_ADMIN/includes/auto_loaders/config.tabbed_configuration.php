<?php
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
} 

if (file_exists(DIR_FS_ADMIN . DIR_WS_INCLUDES . 'init_includes/init_tabbed_configuration.php')) {
	$autoLoadConfig[998][] = array(
	  'autoType' => 'init_script',
	  'loadFile' => 'init_tabbed_configuration.php'
	);
}