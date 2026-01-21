<?php

// Added back merged cart message
if ( !defined('SHOW_SHOPPING_CART_COMBINED') ) {
    $db->Execute('INSERT INTO ' . TABLE_CONFIGURATION . ' (configuration_id, configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function) VALUES
                (NULL, "Show Notice of Combining Shopping Cart on Login", "Features", "SHOW_SHOPPING_CART_COMBINED", "1", "When a customer logs in and has a previously stored shopping cart, the products are combined with the existing shopping cart.<br /><br />Do you wish to display a Notice to the customer?<br /><br />0= OFF, do not display a notice<br />1= Yes show notice and go to shopping cart<br />2= Yes show notice, but do not go to shopping cart.",  "' . $configuration_group_id . '", 30, NOW(), NULL, "zen_cfg_select_option(array(\'0\',\'1\',\'2\'),")');
}

