<?php
    // get a list of all customer accounts that have a duplicate email address
    $customers = $db->Execute("SELECT customers_id, a.customers_email_address FROM " . TABLE_CUSTOMERS . " a
    INNER JOIN (SELECT customers_email_address FROM " . TABLE_CUSTOMERS . "
    GROUP BY customers_email_address HAVING count(customers_email_address) > 1) dup ON a.customers_email_address = dup.customers_email_address
    ORDER by customers_id ASC;");
    $completed_array = array();
    $count = 0;
    while(!$customers->EOF) {
        if (!in_array($customers->fields['customers_id'], $completed_array)) {
            // get all customers_id with matching email address
            $customers_ids = $db->Execute("SELECT customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . $customers->fields['customers_email_address'] . "' ORDER BY customers_id DESC LIMIT 1;");
            $newest_customer_id = $customers_ids->fields['customers_id'];
            if ($customers->fields['customers_id'] != $newest_customer_id) { // account isn't the latest, so proceed
                // update orders
                $update_orders = "UPDATE " . TABLE_ORDERS . " SET customers_id = " . $newest_customer_id . " WHERE customers_id = " . $customers->fields['customers_id'] . ";";
                $db->Execute($update_orders);
                // delete accounts
                $delete_customers = "DELETE FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $customers->fields['customers_id'] . " LIMIT 1;";
                $db->Execute($delete_customers);
                $count++;
            }
            $completed_array[] = $customers->fields['customers_id'];
        }
        $customers->MoveNext();
    }