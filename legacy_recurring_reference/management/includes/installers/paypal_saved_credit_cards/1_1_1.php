<?php
global $sniffer;
if (!$sniffer->field_exists(TABLE_SAVED_CREDIT_CARDS, 'expiry')) {
    $db->Execute('ALTER TABLE ' . TABLE_SAVED_CREDIT_CARDS . ' ADD expiry varchar(4) NULL DEFAULT NULL AFTER last_digits;');
}

if (!$sniffer->field_exists(TABLE_SAVED_CREDIT_CARDS, 'is_visible')) {
    $db->Execute('ALTER TABLE ' . TABLE_SAVED_CREDIT_CARDS . ' ADD is_visible tinyint(1) NOT NULL DEFAULT 0;');
}