<?php

$db->Execute("UPDATE ".TABLE_CONFIGURATION." SET configuration_value = 'partially expanded', set_function = 'zen_cfg_select_option(array(\"partially expanded\", \"fully expanded\"),' WHERE configuration_key = 'OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT'");