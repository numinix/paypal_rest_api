<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once('includes/application_top.php');

$sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . " 
        SET is_deleted = '1'
        WHERE is_deleted = '0' 
        AND expiry IS NOT NULL
        AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) < CURDATE()";

$db->Execute($sql);

echo "Expired cards cleanup completed\n";
