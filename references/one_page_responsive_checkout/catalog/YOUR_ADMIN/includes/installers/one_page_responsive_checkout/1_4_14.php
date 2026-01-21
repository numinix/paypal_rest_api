<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_COUNTRIES, 'phone_format')) $db->Execute('ALTER TABLE ' . TABLE_COUNTRIES . ' ADD phone_format VARCHAR( 50 ) NOT NULL ;');
$db->Execute('UPDATE ' . TABLE_COUNTRIES . '  SET  phone_format =  \'(999) 999-9999\' WHERE countries_iso_code_2 IN(\'CA\',\'US\') ');
