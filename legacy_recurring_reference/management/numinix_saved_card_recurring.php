<?php

require('includes/application_top.php');
require_once(DIR_FS_CATALOG .DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
require_once(DIR_FS_CATALOG .DIR_WS_INCLUDES . 'extra_datafiles/saved_credit_cards.php');

$messageStackKey = 'numinix_saved_card_recurring';

$paypalSavedCardRecurring = new paypalSavedCardRecurring();

/*
* Takes an array that has the id as the key and the value as the value, and formats it how the zen_draw_pull_down function accepts
*/
function pull_down_format($input, $include_all_option = true)
{
    $output = array();
    if ($include_all_option) {
        $output[] = array('id' => 0, 'text' => 'All');
    }

    foreach ($input as $key => $value) {
        $output[] = array('id' => $key, 'text' => $value);
    }
    return $output;
}
//Map URL Parameters
$action = (isset($_GET['action']) ? $_GET['action'] : '');
$redirectAfterAction = false;

switch ($action) {

    case 'cancel_scheduled_payment':
        if ($_GET['saved_card_recurring_id'] > 0) {
            $paypalSavedCardRecurring->update_payment_status($_GET['saved_card_recurring_id'], 'cancelled', 'Cancelled by admin');
            $message = 'Subscription #' . $_GET['saved_card_recurring_id'] . ' has been cancelled.';
            $messageStack->add_session($messageStackKey, $message, 'success');

            //cancel group pricing
            $subscription = $paypalSavedCardRecurring->get_payment_details($_GET['saved_card_recurring_id']);
            $paypalSavedCardRecurring->remove_group_pricing($subscription['customers_id'], $subscription['products_id']);
        }
        $redirectAfterAction = true;
        break;

    case 'reactivate_scheduled_payment':
        $paypalSavedCardRecurring->update_payment_status($_GET['saved_card_recurring_id'], 'scheduled', 'Re-activated by admin');
        $message = 'Subscription #' . $_GET['saved_card_recurring_id'] . ' has been re-activated.';
        $messageStack->add_session($messageStackKey, $message, 'success');

        //re-activate group pricing
        $subscription = $paypalSavedCardRecurring->get_payment_details($_GET['saved_card_recurring_id']);
        $paypalSavedCardRecurring->create_group_pricing($subscription['products_id'], $subscription['customers_id']);
        $redirectAfterAction = true;
        break;

    case 'update_credit_card':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], array('saved_credit_card_id' => $_GET['set_card'], 'comments' => '  Credit card updated by admin. '));
        $message = 'Credit card has been updated for subscription #' . $_GET['saved_card_recurring_id'];
        break;

    case 'update_payment_date':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], array('date' => $_GET['set_date'], 'comments' => '  Date updated by admin to  ' . $_GET['set_date'] . '  '));
        $message = 'Date has been updated for subscription #' . $_GET['saved_card_recurring_id'];
        break;

    case 'update_amount_subscription':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], array('amount' => $_GET['set_amount'], 'comments' => '  Amount updated by admin to  ' . $_GET['set_amount'] . '  '));
        $message = 'Amount has been updated for subscription #' . $_GET['saved_card_recurring_id'] .'to' . $_GET['set_amount'];
        break;

    case 'update_product_id':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], array('product' => $_GET['set_products_id'], 'comments' => '  Product updated by admin to  ', 'original_orders_products_id' => $_GET['original_orders_products_id']));
        $message = 'Product has been updated for subscription #' . $_GET['saved_card_recurring_id'] .'to' . $_GET['set_products_id'];
        break;
}

if ($redirectAfterAction) {
    zen_redirect(zen_href_link(
        FILENAME_NUMINIX_SAVED_CARDS_RECURRING,
        zen_get_all_get_params(array('action', 'saved_card_recurring_id'))
    ));
}

//todo: automate this
$attribute_ids = array('period' => '31', 'frequency' => '32', 'cycles' => '33', 'domain' => '26'); //These fields are stored as attributes.

$sql = "SELECT
        sccr.*, 
        scc.type AS card_type,
        scc.last_digits,
        scc.is_deleted,
        scc.customers_id AS saved_card_customer_id,
        c.customers_firstname,
        c.customers_lastname,
        c.customers_project
    FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
    LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS . " scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
    LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
    WHERE 1";
$query_string = '';

if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
    $sql .= ' AND  scc.customers_id = ' . $_GET['customers_id'];
    $query_string .= '&customers_id=' . $_GET['customers_id'];
}
if (isset($_GET['products_id']) && $_GET['products_id'] > 0) {
    $sql .= ' AND sccr.products_id = ' . (int) $_GET['products_id'];
    $query_string .= '&products_id=' . $_GET['products_id'];
}
if (isset($_GET['status']) && strlen($_GET['status']) > 0 && $_GET['status'] != '0') {
    $sql .= ' AND sccr.status = "' . $_GET['status'] . '"';
    $query_string .= '&status=' . $_GET['status'];
} elseif ($_GET['status'] != '0') { //default to showing scheduled payments, unless the user has specified all status'
    $sql .= ' AND sccr.status = "scheduled"';
}

$result = $db->execute($sql);
$subscriptions = array();
$orderIdsByOrderProductId = array();

while (!$result->EOF) {
    $subscriptionId = (int) $result->fields['saved_credit_card_recurring_id'];
    if (!isset($subscriptions[$subscriptionId])) {
        $subscriptions[$subscriptionId] = $result->fields;

        $attributes = array();
        if (!empty($result->fields['subscription_attributes_json'])) {
            $decodedAttributes = json_decode($result->fields['subscription_attributes_json'], true);
            if (is_array($decodedAttributes)) {
                $attributes = $decodedAttributes;
            }
        }

        $subscriptions[$subscriptionId]['period'] = isset($result->fields['billing_period']) && $result->fields['billing_period'] !== ''
            ? $result->fields['billing_period']
            : (isset($attributes['billingperiod']) ? $attributes['billingperiod'] : '');

        $frequencyValue = '';
        if (isset($result->fields['billing_frequency']) && $result->fields['billing_frequency'] !== null) {
            $frequencyValue = $result->fields['billing_frequency'];
        } elseif (isset($attributes['billingfrequency'])) {
            $frequencyValue = $attributes['billingfrequency'];
        }
        $subscriptions[$subscriptionId]['frequency'] = $frequencyValue;

        $cyclesValue = '';
        if (isset($result->fields['total_billing_cycles']) && $result->fields['total_billing_cycles'] !== null) {
            $cyclesValue = $result->fields['total_billing_cycles'];
        } elseif (isset($attributes['totalbillingcycles'])) {
            $cyclesValue = $attributes['totalbillingcycles'];
        }
        $subscriptions[$subscriptionId]['cycles'] = $cyclesValue;

        $domainValue = '';
        if (isset($result->fields['domain']) && $result->fields['domain'] !== '') {
            $domainValue = $result->fields['domain'];
        } elseif (isset($attributes['domain']) && $attributes['domain'] !== '') {
            $domainValue = $attributes['domain'];
        }
        $subscriptions[$subscriptionId]['domain'] = $domainValue;

        $originalOrdersProductsId = isset($result->fields['original_orders_products_id']) ? (int) $result->fields['original_orders_products_id'] : 0;
        if ($originalOrdersProductsId > 0) {
            if (!isset($orderIdsByOrderProductId[$originalOrdersProductsId])) {
                $orderLookup = $db->Execute('SELECT orders_id FROM ' . TABLE_ORDERS_PRODUCTS . ' WHERE orders_products_id = ' . $originalOrdersProductsId . ' LIMIT 1;');
                $orderIdsByOrderProductId[$originalOrdersProductsId] = ($orderLookup->RecordCount() > 0) ? (int) $orderLookup->fields['orders_id'] : null;
            }
            $subscriptions[$subscriptionId]['orders_id'] = $orderIdsByOrderProductId[$originalOrdersProductsId];
        } else {
            $subscriptions[$subscriptionId]['orders_id'] = null;
        }
    }

    $result->MoveNext();
}

/* Get data for popuplating search select menus */
$customers_sql = "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_project, scc.saved_credit_card_id, scc.type, scc.last_digits, scc.is_deleted
    FROM
    " . TABLE_SAVED_CREDIT_CARDS . " scc
    LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr ON scc.saved_credit_card_id = sccr.saved_credit_card_id
    LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
    WHERE c.customers_id > 0
    ORDER BY c.customers_lastname ASC
;";

$result = $db->Execute($customers_sql);

$customers = array();
$customers_cards = array();
while (!$result->EOF) {
    $customers[$result->fields['customers_id']] = $result->fields['customers_lastname'] . ', ' . $result->fields['customers_firstname'] . ' (' . ($result->fields['customers_project'] != '' ? $result->fields['customers_project'] : 'OC') . ')';
    $customers_cards[$result->fields['customers_id']][$result->fields['saved_credit_card_id']] = $result->fields['type'] . ' ****' . $result->fields['last_digits'] . (($result->fields['is_deleted'] == 1) ? ' (deleted) ' : '');
    $result->MoveNext();
}

$products_sql = "SELECT
        sccr.products_id,
        MAX(CASE WHEN sccr.products_name IS NOT NULL AND sccr.products_name <> '' THEN sccr.products_name ELSE NULL END) AS products_name
    FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
    WHERE sccr.products_id IS NOT NULL
    GROUP BY sccr.products_id
    ORDER BY products_name ASC
;";
$result = $db->Execute($products_sql);

$products = array();
while (!$result->EOF) {
    $products[$result->fields['products_id']] = $result->fields['products_name'];
    $result->MoveNext();
}

$allproducts_sql = 'SELECT * FROM ' . TABLE_PRODUCTS . ' p
    LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id';

$result = $db->Execute($allproducts_sql);

$allproducts = array();
while (!$result->EOF) {
    $allproducts[$result->fields['products_id']] = $result->fields['products_name'];
    $result->MoveNext();
}

$statuses_recurring['complete'] = 'complete';
$statuses_recurring['failed'] = 'failed';
$statuses_recurring['scheduled'] = 'scheduled';
$statuses_recurring['cancelled'] = 'cancelled';
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
    <head>
        <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
        <link rel="stylesheet" type="text/css" href="includes/css/numinix_admin.css" />
        <link rel="stylesheet" type="text/css" href="includes/css/numinix_saved_card_recurring.css" />
    </head>
    <body>
        <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
        <div class="nmx-module">
            <div class="nmx-container">
                <div class="nmx-container-header">
                    <h1><?php echo HEADING_TITLE; ?></h1>
                </div>

                <div class="nmx-row">
                    <div class="nmx-col-xs-12">
                        <div class="nmx-panel">
                            <div class="nmx-panel-heading">
                                <div class="nmx-panel-title">Filter Subscriptions</div>
                            </div>
                            <div class="nmx-panel-body">
                                <form name="search_subscriptions" method="get" class="nmx-form nmx-form-inline" id="search_subscriptions">
                                    <?php echo zen_draw_hidden_field('cmd', FILENAME_NUMINIX_SAVED_CARDS_RECURRING); ?>
                                    <div class="nmx-form-group">
                                        <label for="customers_id">Customer</label>
                                        <?php echo zen_draw_pull_down_menu('customers_id', pull_down_format($customers), $_GET['customers_id'], 'id="customers_id" class="nmx-form-control"'); ?>
                                    </div>
                                    <div class="nmx-form-group">
                                        <label for="products_id">Product</label>
                                        <?php echo zen_draw_pull_down_menu('products_id', pull_down_format($products), $_GET['products_id'], 'id="products_id" class="nmx-form-control"'); ?>
                                    </div>
                                    <div class="nmx-form-group">
                                        <label for="status">Status</label>
                                        <?php echo zen_draw_pull_down_menu('status', pull_down_format($statuses_recurring), (isset($_GET['status']) && strlen($_GET['status']) > 0 ? $_GET['status'] : 'scheduled'), 'id="status" class="nmx-form-control"'); ?>
                                    </div>
                                    <div class="nmx-form-actions">
                                        <button type="submit" class="nmx-btn nmx-btn-primary">Search</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nmx-row">
                    <div class="nmx-col-xs-12">
                        <div class="nmx-panel">
                            <div class="nmx-panel-heading">
                                <div class="nmx-panel-title">Subscription Overview</div>
                            </div>
                            <div class="nmx-panel-body" id="subscription_table">
                                <?php
                                if (isset($messageStack) && is_object($messageStack)) {
                                    $stackMessages = '';
                                    if (method_exists($messageStack, 'size') && method_exists($messageStack, 'output')) {
                                        if ($messageStack->size($messageStackKey) > 0) {
                                            $stackMessages = $messageStack->output($messageStackKey);
                                        }
                                    } elseif (method_exists($messageStack, 'output')) {
                                        $stackMessages = $messageStack->output($messageStackKey);
                                    }

                                    if ($stackMessages !== '') {
                                        echo '<div class="nmx-message-stack">' . $stackMessages . '</div>';
                                    }
                                }
                                ?>
                                <?php if (isset($message) && strlen($message) > 0) { ?>
                                    <div class="nmx-alert nmx-alert-success"><?php echo $message; ?></div>
                                <?php } ?>

                                <div class="nmx-table-responsive">
                                    <table id="paymentReport" class="nmx-table nmx-table-bordered nmx-table-striped">
                                        <thead>
                                            <tr>
                                                <th>Recurring ID</th>
                                                <th>Product</th>
                                                <th>Customer</th>
                                                <th>Original Order ID</th>
                                                <th>Domain</th>
                                                <th class="nmx-text-right">Amount</th>
                                                <th>Period</th>
                                                <th>Frequency</th>
                                                <th>Billing Cycles</th>
                                                <th>Date</th>
                                                <th>Payment Method</th>
                                                <th>Comments</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subscriptions as $subscription) { ?>
                                                <tr>
                                                    <td><?php echo $subscription['saved_credit_card_recurring_id']; ?></td>
                                                    <td>
                                                        <span class="nmx-entity-primary"><?php echo $subscription['products_name']; ?></span>
                                                        <span class="show_edit">(Edit)</span>
                                                        <span class="edit_content">
                                                            <?php echo zen_draw_pull_down_menu('set_products_id', pull_down_format($allproducts), $subscription['products_id'], 'class="nmx-form-control"'); ?>
                                                            <input type="hidden" value="<?php echo $subscription['original_orders_products_id']; ?>" name="hidden_original_orders_products_id" class="hidden_original_orders_products_id" />
                                                            <a class="save_product nmx-inline-action" data-saved_card_recurring_id="<?php echo $subscription['saved_credit_card_recurring_id']; ?>">Save</a>
                                                            <a class="cancel_edit nmx-inline-action">Cancel</a>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $subscription['customers_firstname'] . ' ' . $subscription['customers_lastname']; ?></td>
                                                    <td><a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $subscription['orders_id'] . '&action=edit'); ?>"><?php echo $subscription['orders_id']; ?></a></td>
                                                    <td><?php echo $subscription['domain']; ?></td>
                                                    <td class="nmx-text-right">
                                                        $<?php echo $subscription['amount']; ?>
                                                        <span class="show_edit">(Edit)</span>
                                                        <span class="edit_content">
                                                            <input type="text" value="<?php echo $subscription['amount']; ?>" name="set_amount" class="set_amount nmx-form-control" />
                                                            <a class="save_amount nmx-inline-action" data-saved_card_recurring_id="<?php echo $subscription['saved_credit_card_recurring_id']; ?>">Save</a>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $subscription['period']; ?></td>
                                                    <td><?php echo $subscription['frequency']; ?></td>
                                                    <td><?php echo $subscription['cycles']; ?></td>
                                                    <td>
                                                        <?php echo $subscription['date']; ?>
                                                        <?php if ($subscription['status'] == 'scheduled') { ?><span class="show_edit">(Edit)</span> <?php } ?>
                                                        <span class="edit_content">
                                                            <input type="text" value="<?php echo $subscription['date']; ?>" name="set_date" class="set_date nmx-form-control" />
                                                            <a class="save_date nmx-inline-action" data-saved_card_recurring_id="<?php echo $subscription['saved_credit_card_recurring_id']; ?>">Save</a>
                                                            <a class="cancel_edit nmx-inline-action">Cancel</a>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo $subscription['card_type']; ?> ****<?php echo $subscription['last_digits']; ?>
                                                        <?php if ($subscription['status'] == 'scheduled') { ?><span class="show_edit">(Edit)</span><?php } ?>
                                                        <span class="edit_content">
                                                            <?php echo zen_draw_pull_down_menu('set_card', pull_down_format($customers_cards[$subscription['customers_id']], false), $subscription['saved_credit_card_id'], 'class="nmx-form-control"'); ?>
                                                            <a class="save_card nmx-inline-action" data-saved_card_recurring_id="<?php echo $subscription['saved_credit_card_recurring_id']; ?>">Save</a>
                                                            <a class="cancel_edit nmx-inline-action">Cancel</a>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $subscription['comments']; ?></td>
                                                    <td>
                                                        <?php echo $subscription['status']; ?>
                                                        <?php if ($subscription['status'] == 'cancelled') { ?>
                                                            <a class="nmx-inline-action" href="<?php echo zen_href_link(FILENAME_NUMINIX_SAVED_CARDS_RECURRING, zen_get_all_get_params(array('action')) . '&action=reactivate_scheduled_payment&saved_card_recurring_id=' . $subscription['saved_credit_card_recurring_id']); ?>">(Re-Activate)</a>
                                                        <?php } elseif ($subscription['status'] == 'scheduled') { ?>
                                                            <a class="nmx-inline-action" href="<?php echo zen_href_link(FILENAME_NUMINIX_SAVED_CARDS_RECURRING, zen_get_all_get_params(array('action')) . '&action=cancel_scheduled_payment&saved_card_recurring_id=' . $subscription['saved_credit_card_recurring_id']); ?>">(Cancel)</a>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } //end for each subscription ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // require DIR_WS_INCLUDES . 'footer.php'; ?>
        <script>
            window.nmxSavedCardRecurringConfig = {
                savedCardUrl: '<?php echo zen_href_link(FILENAME_NUMINIX_SAVED_CARDS_RECURRING); ?>',
                queryString: '<?php echo $query_string; ?>'
            };
        </script>
        <script src="includes/javascript/numinix_saved_card_recurring.js"></script>
    </body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
