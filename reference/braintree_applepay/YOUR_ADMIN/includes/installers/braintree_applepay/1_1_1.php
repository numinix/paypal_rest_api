<?php
$db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_title = 'Order Totals Selector' WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOTAL_SELECTOR' LIMIT 1;");