<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once('includes/application_top.php');
require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');

//BOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation
$sql = "SELECT sc.id,sc.customers_id FROM ".TABLE_SUBSCRIPTION_CANCELLATIONS ." sc
        left join ". TABLE_CUSTOMERS." c on sc.customers_id = c.customers_id
        WHERE sc.expiration_date <= CURDATE() ";
$subscription_cancellations = $db->Execute($sql);

if($subscription_cancellations->RecordCount() > 0 )
{

    $customer_ids = [];
    $subscription_cancellation_ids = [];
    foreach($subscription_cancellations as $subscription_cancellation)
    {
        $customer_ids[] = $subscription_cancellation['customers_id'] ;
        $subscription_cancellation_ids[] = $subscription_cancellation['id'];
    }

    if(count($customer_ids) > 0)
    {
        //Remove Pricing
        $sql = "UPDATE  " .TABLE_CUSTOMERS. " SET customers_group_pricing=0 WHERE customers_id  IN (" . implode(',', $customer_ids) . ")";
        $db->Execute($sql);
    }
    if(count($subscription_cancellation_ids) > 0)
    {
        //Delete schedule cancellation
        $sql = "DELETE FROM " .TABLE_SUBSCRIPTION_CANCELLATIONS. " WHERE id IN (" . implode(',', $subscription_cancellation_ids) . ")";
        $db->Execute($sql);
    }
}

//EOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation