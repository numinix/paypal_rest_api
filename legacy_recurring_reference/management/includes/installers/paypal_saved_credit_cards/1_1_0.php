<?php
    global $sniffer;
    if (!$sniffer->field_exists(TABLE_SAVED_CREDIT_CARDS, 'api_type')) {
        $db->Execute('ALTER TABLE ' . TABLE_SAVED_CREDIT_CARDS . ' ADD api_type ENUM(  \'paypalwpp\',  \'payflow\' ) NOT NULL DEFAULT \'paypalwpp\';');
    }