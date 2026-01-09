<?php
// use $configuration_group_id where needed

// This module does not need to add an admin page because it has a congifuration page at Admin -> Modules -> Payment -> Paypal Saved Credit Cards

/*
$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
if ($zc150) { // continue Zen Cart 1.5.0
    $admin_page = 'configPaypalSavedCreditCards';
  // delete configuration menu
  $db->Execute("DELETE FROM ".TABLE_ADMIN_PAGES." WHERE page_key = '".$admin_page."' LIMIT 1;");
  // add configuration menu
  if (!zen_page_key_exists($admin_page)) {
    if ((int)$configuration_group_id > 0) {
      zen_register_admin_page($admin_page,
                              'BOX_MODULE', 
                              'FILENAME_CONFIGURATION',
                              'gID=' . $configuration_group_id, 
                              'configuration', 
                              'Y',
                              $configuration_group_id);
        
      $messageStack->add('Enabled Paypal Saved Credit Cards Configuration Menu.', 'success');
    }
  }
}
  */

  global $sniffer;
  if (!$sniffer->table_exists(TABLE_SAVED_CREDIT_CARDS, 'column')) {
      $db->Execute("CREATE TABLE IF NOT EXISTS " . TABLE_SAVED_CREDIT_CARDS . " (
  saved_credit_card_id int(11) NOT NULL AUTO_INCREMENT,
  customers_id int(11) NOT NULL,
  `type` varchar(12) NOT NULL,
  last_digits varchar(4) NOT NULL,
  name_on_card varchar(255) NOT NULL,
  paypal_transaction_id varchar(255) NOT NULL,
  is_primary tinyint(1) NOT NULL,
  address_id int(11) DEFAULT NULL,
  is_deleted int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (saved_credit_card_id)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=136 ;");

  } 
/*
 // For adding a configuration value
  $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_group_id, configuration_key, configuration_title, configuration_value, configuration_description, sort_order, set_function) VALUES (" . (int) $configuration_group_id . ", 'CONFIGURATION_KEY', 'This a configuration value name', 'true', 'This is the description of the configuration value', 1, 'zen_cfg_select_option(array(\'true\', \'false\'),');");
 */
