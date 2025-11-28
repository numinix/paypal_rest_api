<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_CONFIGURATION, 'configuration_tab'))  $db->Execute("ALTER TABLE " . TABLE_CONFIGURATION . " ADD configuration_tab varchar(32) NOT NULL DEFAULT 'both';");

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
if ($zc150 && function_exists('zen_page_key_exists') && function_exists('zen_register_admin_page')) // continue Zen Cart 1.5.0
{
  if (!zen_page_key_exists('configDefault')) {
    zen_register_admin_page('configDefault',
                            'BOX_CONFIGURATION_DEFAULT', 
                            'FILENAME_CONFIGURATION_DEFAULT',
                            '', 
                            'configuration', 
                            'N',
                            '99');
      
    $messageStack->add('Enabled Default Configuration Page.', 'success');
  }
}
// delete installer to avoid duplicate installation
unlink(DIR_FS_ADMIN . DIR_WS_INCLUDES . 'init_includes/init_tabbed_configuration.php');