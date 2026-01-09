<?php
// use $configuration_group_id where needed

// For Admin Pages
//todo: is this running every time an admin page loads, even after install is complete?

$zc150 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5));
if ($zc150) { // continue Zen Cart 1.5.0
    $admin_page = 'configSavedCreditCardsRecurring';
    $configuration = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_VERSION' LIMIT 1;");
    $configuration_group_id = $configuration->fields['configuration_group_id'];

  // delete configuration menu
 // $db->Execute("DELETE FROM ".TABLE_ADMIN_PAGES." WHERE page_key = '".$admin_page."' LIMIT 1;");
  // add configuration menu
    if ((int)$configuration_group_id > 0) {

      if (!zen_page_key_exists($admin_page)) {
        zen_register_admin_page($admin_page,
                              'BOX_SAVED_CREDIT_CARDS_RECURRING', 
                              'FILENAME_CONFIGURATION',
                              'gID=' . $configuration_group_id, 
                              'configuration', 
                              'Y',
                              $configuration_group_id);
        
        $messageStack->add('Enabled Saved Credit Card Recurring Configuration Menu.', 'success');
      }

       $db->Execute("INSERT IGNORE INTO " . TABLE_CONFIGURATION . " (configuration_group_id, configuration_key, configuration_title, configuration_value, configuration_description, sort_order, set_function) VALUES
(" . (int) $configuration_group_id . ", 'SAVED_CREDIT_CARDS_RECURRING_ENABLED', 'Enabled', 'true', 'Enable to trigger a subscription to be created when the customer has a saved credit card and purchases a product that has a billingperiod attribute', 1, 'zen_cfg_select_option(array(\'true\', \'false\'),');");
  }
  
  if (!zen_page_key_exists('customerSavedCreditCardsRecurring')) {
    zen_register_admin_page('customerSavedCreditCardsRecurring', 'BOX_SAVED_CREDIT_CARDS_RECURRING_CUSTOMERS', 'FILENAME_NUMINIX_SAVED_CARDS_RECURRING', '', 'customers', 'Y', (int) $configuration_group_id);
    $messageStack->add('Enabled Saved Credit Card Recurring Configuration Menu.', 'success');
   }
}
  
 
/* If your checking for a field
 * global $sniffer;
 * if (!$sniffer->field_exists(TABLE_SOMETHING, 'column'))  $db->Execute("ALTER TABLE " . TABLE_SOMETHING . " ADD column varchar(32) NOT NULL DEFAULT 'both';");
 */

$db->Execute('
CREATE TABLE IF NOT EXISTS numinix_saved_credit_cards_recurring (
  saved_credit_card_recurring_id int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  amount float NOT NULL,
  `status` set(\'complete\',\'failed\',\'scheduled\',\'cancelled\') COLLATE utf8_unicode_ci NOT NULL,
  original_orders_products_id int(11) NOT NULL,
  recurring_orders_id int(11) DEFAULT NULL,
  saved_credit_card_id int(11) NOT NULL,
  comments text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (saved_credit_card_recurring_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=35 ; 
');
