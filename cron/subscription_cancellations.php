<?php
/**
 * Subscription Cancellations Cron
 * 
 * Processes scheduled subscription cancellations by:
 * - Removing group pricing for customers with expired cancellation dates
 * - Deleting processed cancellation records
 * 
 * This ensures that customers who have cancelled their subscriptions 
 * lose their group pricing discounts after the cancellation grace period.
 * 
 * Compatible with all payment modules:
 * - paypalwpp.php (Website Payments Pro)
 * - paypal.php (PayPal Standard)
 * - paypaldp.php (Direct Payments)
 * - paypalr.php (REST API)
 * - payflow.php (Payflow)
 */

require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';

// Load saved card recurring class for group pricing methods
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
}

$customersProcessed = 0;
$cancellationsDeleted = 0;
$log = [];

// Check if the subscription cancellations table exists
if (defined('TABLE_SUBSCRIPTION_CANCELLATIONS')) {
    // Find all cancellations that have reached their expiration date
    $sql = "SELECT sc.id, sc.customers_id, sc.group_name, sc.expiration_date 
            FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . " sc
            LEFT JOIN " . TABLE_CUSTOMERS . " c ON sc.customers_id = c.customers_id
            WHERE sc.expiration_date <= CURDATE()";
    
    $subscription_cancellations = $db->Execute($sql);
    
    if ($subscription_cancellations->RecordCount() > 0) {
        $customer_ids = [];
        $subscription_cancellation_ids = [];
        
        foreach ($subscription_cancellations as $subscription_cancellation) {
            $customer_ids[] = (int) $subscription_cancellation['customers_id'];
            $subscription_cancellation_ids[] = (int) $subscription_cancellation['id'];
            $log[] = "Processing cancellation for customer #" . $subscription_cancellation['customers_id'] . 
                     " (Group: " . $subscription_cancellation['group_name'] . 
                     ", Expired: " . $subscription_cancellation['expiration_date'] . ")";
        }
        
        // Remove unique customer IDs to avoid duplicate processing
        $customer_ids = array_unique($customer_ids);
        
        if (count($customer_ids) > 0) {
            // Remove group pricing from customers
            $sql = "UPDATE " . TABLE_CUSTOMERS . " 
                    SET customers_group_pricing = 0 
                    WHERE customers_id IN (" . implode(',', $customer_ids) . ")";
            $db->Execute($sql);
            $customersProcessed = count($customer_ids);
            $log[] = "Removed group pricing from $customersProcessed customer(s)";
        }
        
        if (count($subscription_cancellation_ids) > 0) {
            // Delete the processed cancellation records
            $sql = "DELETE FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . " 
                    WHERE id IN (" . implode(',', $subscription_cancellation_ids) . ")";
            $db->Execute($sql);
            $cancellationsDeleted = count($subscription_cancellation_ids);
            $log[] = "Deleted $cancellationsDeleted cancellation record(s)";
        }
    } else {
        $log[] = "No cancellations to process";
    }
} else {
    $log[] = "TABLE_SUBSCRIPTION_CANCELLATIONS is not defined - skipping";
}

// Output results
echo "Subscription Cancellations Cron Executed Successfully\n";
echo "Customers processed: $customersProcessed\n";
echo "Cancellation records deleted: $cancellationsDeleted\n";

if (!empty($log)) {
    echo "\nLog:\n";
    foreach ($log as $entry) {
        echo "- " . $entry . "\n";
    }
}

require_once 'includes/application_bottom.php';
