<?php
/**
 * Admin page for managing saved card recurring payments.
 * 
 * Features:
 * - Filter by customer, product, status
 * - Cancel/re-activate scheduled payments
 * - Update credit card on subscription
 * - Update payment date, amount, product assignment
 * 
 * Compatible with:
 * - paypalwpp.php (Website Payments Pro)
 * - paypaldp.php (Direct Payments)
 * - paypalr.php (REST API)
 * - payflow.php (Payflow)
 */

require 'includes/application_top.php';

// Load PayPal autoloader to access schema managers
$autoloaderPath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
}

// Ensure legacy saved credit cards tables exist for backward compatibility
if (class_exists('PayPalRestful\\Common\\SavedCreditCardsManager')) {
    \PayPalRestful\Common\SavedCreditCardsManager::ensureSchema();
}

// Load saved card recurring class
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';

// Load extra datafiles if available
if (file_exists(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'extra_datafiles/saved_credit_cards.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_INCLUDES . 'extra_datafiles/saved_credit_cards.php';
}

define('FILENAME_PAYPALR_SAVED_CARD_RECURRING', basename(__FILE__));

if (!defined('HEADING_TITLE')) {
    define('HEADING_TITLE', 'Saved Card Subscriptions');
}



$paypalSavedCardRecurring = new paypalSavedCardRecurring();

/**
 * Format array for zen_draw_pull_down_menu
 */
function pull_down_format($input, $include_all_option = true)
{
    $output = [];
    if ($include_all_option) {
        $output[] = ['id' => 0, 'text' => 'All'];
    }
    foreach ($input as $key => $value) {
        $output[] = ['id' => $key, 'text' => $value];
    }
    return $output;
}

// Map URL Parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$redirectAfterAction = false;

switch ($action) {
    case 'cancel_scheduled_payment':
        if ($_GET['saved_card_recurring_id'] > 0) {
            $paypalSavedCardRecurring->update_payment_status($_GET['saved_card_recurring_id'], 'cancelled', 'Cancelled by admin');
            $messageStack->add_session('header', 'Subscription #' . $_GET['saved_card_recurring_id'] . ' has been cancelled.', 'success');
            
            // Cancel group pricing
            $subscription = $paypalSavedCardRecurring->get_payment_details($_GET['saved_card_recurring_id']);
            if ($subscription && method_exists($paypalSavedCardRecurring, 'remove_group_pricing')) {
                $paypalSavedCardRecurring->remove_group_pricing($subscription['customers_id'], $subscription['products_id']);
            }
        }
        $redirectAfterAction = true;
        break;

    case 'reactivate_scheduled_payment':
        $paypalSavedCardRecurring->update_payment_status($_GET['saved_card_recurring_id'], 'scheduled', 'Re-activated by admin');
        $messageStack->add_session('header', 'Subscription #' . $_GET['saved_card_recurring_id'] . ' has been re-activated.', 'success');
        
        // Re-activate group pricing
        $subscription = $paypalSavedCardRecurring->get_payment_details($_GET['saved_card_recurring_id']);
        if ($subscription && method_exists($paypalSavedCardRecurring, 'create_group_pricing')) {
            $paypalSavedCardRecurring->create_group_pricing($subscription['products_id'], $subscription['customers_id']);
        }
        $redirectAfterAction = true;
        break;

    case 'update_credit_card':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], [
            'saved_credit_card_id' => $_GET['set_card'],
            'comments' => '  Credit card updated by admin. '
        ]);
        $messageStack->add_session('header', 'Credit card has been updated for subscription #' . $_GET['saved_card_recurring_id'], 'success');
        $redirectAfterAction = true;
        break;

    case 'update_payment_date':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], [
            'date' => $_GET['set_date'],
            'comments' => '  Date updated by admin to ' . $_GET['set_date'] . '  '
        ]);
        $messageStack->add_session('header', 'Date has been updated for subscription #' . $_GET['saved_card_recurring_id'], 'success');
        $redirectAfterAction = true;
        break;

    case 'update_amount_subscription':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], [
            'amount' => $_GET['set_amount'],
            'comments' => '  Amount updated by admin to ' . $_GET['set_amount'] . '  '
        ]);
        $messageStack->add_session('header', 'Amount has been updated for subscription #' . $_GET['saved_card_recurring_id'] . ' to ' . $_GET['set_amount'], 'success');
        $redirectAfterAction = true;
        break;

    case 'update_product_id':
        $paypalSavedCardRecurring->update_payment_info($_GET['saved_card_recurring_id'], [
            'product' => $_GET['set_products_id'],
            'comments' => '  Product updated by admin  ',
            'original_orders_products_id' => $_GET['original_orders_products_id']
        ]);
        $messageStack->add_session('header', 'Product has been updated for subscription #' . $_GET['saved_card_recurring_id'], 'success');
        $redirectAfterAction = true;
        break;
        
    case 'export_csv':
        // Build query with filters
        $exportSql = "SELECT sccr.*, scc.type AS card_type, scc.last_digits, scc.is_deleted,
                c.customers_firstname, c.customers_lastname, c.customers_email_address
            FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
            LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS . " scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
            LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
            WHERE 1";
        
        if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
            $exportSql .= ' AND scc.customers_id = ' . (int)$_GET['customers_id'];
        }
        if (isset($_GET['products_id']) && $_GET['products_id'] > 0) {
            $exportSql .= ' AND sccr.products_id = ' . (int)$_GET['products_id'];
        }
        if (isset($_GET['status']) && strlen($_GET['status']) > 0 && $_GET['status'] != '0') {
            $exportSql .= ' AND sccr.status = "' . zen_db_input($_GET['status']) . '"';
        }
        
        $exportSql .= ' ORDER BY sccr.saved_credit_card_recurring_id DESC';
        $exportResults = $db->Execute($exportSql);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=saved_card_subscriptions_' . date('Y-m-d_His') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Subscription ID', 'Customer ID', 'Customer Name', 'Email', 'Product ID', 'Product Name',
            'Amount', 'Currency', 'Billing Period', 'Billing Frequency', 'Total Cycles',
            'Domain', 'Next Payment Date', 'Card Type', 'Card Last 4', 'Status', 'Comments'
        ]);
        
        if ($exportResults->RecordCount() > 0) {
            while (!$exportResults->EOF) {
                $row = $exportResults->fields;
                fputcsv($output, [
                    $row['saved_credit_card_recurring_id'],
                    $row['customers_id'] ?? '',
                    trim(($row['customers_firstname'] ?? '') . ' ' . ($row['customers_lastname'] ?? '')),
                    $row['customers_email_address'] ?? '',
                    $row['products_id'] ?? '',
                    $row['products_name'] ?? '',
                    $row['amount'] ?? '',
                    $row['currency_code'] ?? '',
                    $row['billing_period'] ?? '',
                    $row['billing_frequency'] ?? '',
                    $row['total_billing_cycles'] ?? '',
                    $row['domain'] ?? '',
                    $row['date'] ?? '',
                    $row['card_type'] ?? '',
                    $row['last_digits'] ?? '',
                    $row['status'] ?? '',
                    $row['comments'] ?? ''
                ]);
                $exportResults->MoveNext();
            }
        }
        fclose($output);
        exit;
}

if ($redirectAfterAction) {
    zen_redirect(zen_href_link(
        FILENAME_PAYPALR_SAVED_CARD_RECURRING,
        zen_get_all_get_params(['action', 'saved_card_recurring_id', 'set_card', 'set_date', 'set_amount', 'set_products_id', 'original_orders_products_id'])
    ));
}

// Build subscriptions query
$sql = "SELECT sccr.*, scc.type AS card_type, scc.last_digits, scc.is_deleted,
        scc.customers_id AS saved_card_customer_id,
        c.customers_firstname, c.customers_lastname
    FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
    LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS . " scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
    LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
    WHERE 1";

$query_string = '';

if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
    $sql .= ' AND scc.customers_id = ' . (int)$_GET['customers_id'];
    $query_string .= '&customers_id=' . (int)$_GET['customers_id'];
}
if (isset($_GET['products_id']) && $_GET['products_id'] > 0) {
    $sql .= ' AND sccr.products_id = ' . (int)$_GET['products_id'];
    $query_string .= '&products_id=' . (int)$_GET['products_id'];
}
if (isset($_GET['status']) && strlen($_GET['status']) > 0 && $_GET['status'] != '0') {
    $sql .= ' AND sccr.status = "' . zen_db_input($_GET['status']) . '"';
    $query_string .= '&status=' . urlencode($_GET['status']);
} elseif (!isset($_GET['status']) || $_GET['status'] != '0') {
    $sql .= ' AND sccr.status = "scheduled"';
}

$sql .= ' ORDER BY sccr.saved_credit_card_recurring_id DESC';

$result = $db->Execute($sql);
$subscriptions = [];
$orderIdsByOrderProductId = [];

while (!$result->EOF) {
    $subscriptionId = (int)$result->fields['saved_credit_card_recurring_id'];
    if (!isset($subscriptions[$subscriptionId])) {
        $subscriptions[$subscriptionId] = $result->fields;
        
        $attributes = [];
        if (!empty($result->fields['subscription_attributes_json'])) {
            $decodedAttributes = json_decode($result->fields['subscription_attributes_json'], true);
            if (is_array($decodedAttributes)) {
                $attributes = $decodedAttributes;
            }
        }
        
        // Get billing details from columns or attributes
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
        
        // Look up original order ID
        $originalOrdersProductsId = isset($result->fields['original_orders_products_id']) ? (int)$result->fields['original_orders_products_id'] : 0;
        if ($originalOrdersProductsId > 0) {
            if (!isset($orderIdsByOrderProductId[$originalOrdersProductsId])) {
                $orderLookup = $db->Execute('SELECT orders_id FROM ' . TABLE_ORDERS_PRODUCTS . ' WHERE orders_products_id = ' . $originalOrdersProductsId . ' LIMIT 1;');
                $orderIdsByOrderProductId[$originalOrdersProductsId] = ($orderLookup->RecordCount() > 0) ? (int)$orderLookup->fields['orders_id'] : null;
            }
            $subscriptions[$subscriptionId]['orders_id'] = $orderIdsByOrderProductId[$originalOrdersProductsId];
        } else {
            $subscriptions[$subscriptionId]['orders_id'] = null;
        }
    }
    $result->MoveNext();
}

// Get data for search select menus
$customers_sql = "SELECT c.customers_id, c.customers_firstname, c.customers_lastname, 
        scc.saved_credit_card_id, scc.type, scc.last_digits, scc.is_deleted
    FROM " . TABLE_SAVED_CREDIT_CARDS . " scc
    LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr ON scc.saved_credit_card_id = sccr.saved_credit_card_id
    LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
    WHERE c.customers_id > 0
    ORDER BY c.customers_lastname ASC";

$result = $db->Execute($customers_sql);

$customers = [];
$customers_cards = [];
while (!$result->EOF) {
    $customers[$result->fields['customers_id']] = $result->fields['customers_lastname'] . ', ' . $result->fields['customers_firstname'];
    $customers_cards[$result->fields['customers_id']][$result->fields['saved_credit_card_id']] = 
        $result->fields['type'] . ' ****' . $result->fields['last_digits'] . 
        (($result->fields['is_deleted'] == 1) ? ' (deleted)' : '');
    $result->MoveNext();
}

$products_sql = "SELECT sccr.products_id,
        MAX(CASE WHEN sccr.products_name IS NOT NULL AND sccr.products_name <> '' THEN sccr.products_name ELSE NULL END) AS products_name
    FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
    WHERE sccr.products_id IS NOT NULL
    GROUP BY sccr.products_id
    ORDER BY products_name ASC";
$result = $db->Execute($products_sql);

$products = [];
while (!$result->EOF) {
    $products[$result->fields['products_id']] = $result->fields['products_name'];
    $result->MoveNext();
}

// Get all products for dropdown
$allproducts_sql = 'SELECT p.products_id, pd.products_name FROM ' . TABLE_PRODUCTS . ' p
    LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id
    WHERE pd.language_id = ' . (int)$_SESSION['languages_id'] . '
    ORDER BY pd.products_name';
$result = $db->Execute($allproducts_sql);

$allproducts = [];
while (!$result->EOF) {
    $allproducts[$result->fields['products_id']] = $result->fields['products_name'];
    $result->MoveNext();
}

$statuses_recurring = [
    'complete' => 'Complete',
    'failed' => 'Failed',
    'scheduled' => 'Scheduled',
    'cancelled' => 'Cancelled'
];
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    <style>
        .edit-content { display: none; }
        .edit-content.active { display: block; margin-top: 8px; padding: 8px; background: rgba(0, 97, 141, 0.05); border-radius: 8px; }
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="nmx-module">
    <div class="nmx-container">
        <div class="nmx-container-header">
            <h1><?php echo HEADING_TITLE; ?></h1>
        </div>
    
        <div class="nmx-message-stack">
        <?php
        if (isset($messageStack) && is_object($messageStack) && $messageStack->size() > 0) {
            echo $messageStack->output();
        }
        ?>
        </div>
        
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title">Filter Subscriptions</div>
            </div>
            <div class="nmx-panel-body">
                <?php echo zen_draw_form('search_subscriptions', FILENAME_PAYPALR_SAVED_CARD_RECURRING, '', 'get', 'class="nmx-form-inline"'); ?>
                    <div class="nmx-form-group">
                        <label for="customers_id">Customer</label>
                        <?php echo zen_draw_pull_down_menu('customers_id', pull_down_format($customers), $_GET['customers_id'] ?? '', 'id="customers_id" class="nmx-form-control"'); ?>
                    </div>
                    <div class="nmx-form-group">
                        <label for="products_id">Product</label>
                        <?php echo zen_draw_pull_down_menu('products_id', pull_down_format($products), $_GET['products_id'] ?? '', 'id="products_id" class="nmx-form-control"'); ?>
                    </div>
                    <div class="nmx-form-group">
                        <label for="status">Status</label>
                        <?php echo zen_draw_pull_down_menu('status', pull_down_format($statuses_recurring), (isset($_GET['status']) && strlen($_GET['status']) > 0 ? $_GET['status'] : 'scheduled'), 'id="status" class="nmx-form-control"'); ?>
                    </div>
                    <div class="nmx-form-actions">
                        <button type="submit" class="nmx-btn nmx-btn-primary">Search</button>
                        <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SAVED_CARD_RECURRING, 'action=export_csv' . $query_string); ?>" class="nmx-btn nmx-btn-info">Export CSV</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title">Saved Card Subscriptions</div>
            </div>
            <div class="nmx-panel-body">
                <div class="nmx-table-responsive">
                    <table class="nmx-table nmx-table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Order ID</th>
                                <th>Domain</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Frequency</th>
                                <th>Cycles</th>
                                <th>Next Date</th>
                                <th>Card</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $subscription) { ?>
                                <tr>
                                    <td><?php echo $subscription['saved_credit_card_recurring_id']; ?></td>
                                    <td>
                                        <strong><?php echo zen_output_string_protected($subscription['products_name']); ?></strong>
                                        <span class="nmx-inline-action" onclick="toggleEdit(this)">(Edit)</span>
                                        <div class="edit-content">
                                            <?php echo zen_draw_pull_down_menu('set_products_id_' . $subscription['saved_credit_card_recurring_id'], pull_down_format($allproducts, false), $subscription['products_id'], 'class="nmx-form-control"'); ?>
                                            <a class="nmx-inline-action" href="javascript:void(0);" onclick="updateProduct(<?php echo $subscription['saved_credit_card_recurring_id']; ?>, <?php echo $subscription['original_orders_products_id'] ?? 0; ?>)">Save</a>
                                            <a class="nmx-inline-action" onclick="toggleEdit(this.parentNode.previousElementSibling)">Cancel</a>
                                        </div>
                                    </td>
                                    <td><?php echo zen_output_string_protected($subscription['customers_firstname'] . ' ' . $subscription['customers_lastname']); ?></td>
                                    <td>
                                        <?php if ($subscription['orders_id']) { ?>
                                            <a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . $subscription['orders_id'] . '&action=edit'); ?>"><?php echo $subscription['orders_id']; ?></a>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td><?php echo zen_output_string_protected($subscription['domain']); ?></td>
                                    <td>
                                        $<?php echo number_format((float)$subscription['amount'], 2); ?>
                                        <span class="nmx-inline-action" onclick="toggleEdit(this)">(Edit)</span>
                                        <div class="edit-content">
                                            <input type="text" id="set_amount_<?php echo $subscription['saved_credit_card_recurring_id']; ?>" value="<?php echo $subscription['amount']; ?>" size="8" class="nmx-form-control" />
                                            <a class="nmx-inline-action" href="javascript:void(0);" onclick="updateAmount(<?php echo $subscription['saved_credit_card_recurring_id']; ?>)">Save</a>
                                        </div>
                                    </td>
                                    <td><?php echo zen_output_string_protected($subscription['period']); ?></td>
                                    <td><?php echo zen_output_string_protected($subscription['frequency']); ?></td>
                                    <td><?php echo zen_output_string_protected($subscription['cycles']); ?></td>
                                    <td>
                                        <?php echo $subscription['date']; ?>
                                        <?php if ($subscription['status'] == 'scheduled') { ?>
                                            <span class="nmx-inline-action" onclick="toggleEdit(this)">(Edit)</span>
                                            <div class="edit-content">
                                                <input type="date" id="set_date_<?php echo $subscription['saved_credit_card_recurring_id']; ?>" value="<?php echo $subscription['date']; ?>" class="nmx-form-control" />
                                                <a class="nmx-inline-action" href="javascript:void(0);" onclick="updateDate(<?php echo $subscription['saved_credit_card_recurring_id']; ?>)">Save</a>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php echo zen_output_string_protected($subscription['card_type'] . ' ****' . $subscription['last_digits']); ?>
                                        <?php if ($subscription['status'] == 'scheduled' && !empty($customers_cards[$subscription['customers_id']])) { ?>
                                            <span class="nmx-inline-action" onclick="toggleEdit(this)">(Edit)</span>
                                            <div class="edit-content">
                                                <?php echo zen_draw_pull_down_menu('set_card_' . $subscription['saved_credit_card_recurring_id'], pull_down_format($customers_cards[$subscription['customers_id']], false), $subscription['saved_credit_card_id'], 'class="nmx-form-control"'); ?>
                                                <a class="nmx-inline-action" href="javascript:void(0);" onclick="updateCard(<?php echo $subscription['saved_credit_card_recurring_id']; ?>)">Save</a>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo zen_output_string_protected($subscription['status']); ?></td>
                                    <td>
                                        <?php if ($subscription['status'] == 'cancelled') { ?>
                                            <a class="nmx-btn nmx-btn-sm nmx-btn-success" href="<?php echo zen_href_link(FILENAME_PAYPALR_SAVED_CARD_RECURRING, $query_string . '&action=reactivate_scheduled_payment&saved_card_recurring_id=' . $subscription['saved_credit_card_recurring_id']); ?>">Reactivate</a>
                                        <?php } elseif ($subscription['status'] == 'scheduled') { ?>
                                            <a class="nmx-btn nmx-btn-sm nmx-btn-danger" href="<?php echo zen_href_link(FILENAME_PAYPALR_SAVED_CARD_RECURRING, $query_string . '&action=cancel_scheduled_payment&saved_card_recurring_id=' . $subscription['saved_credit_card_recurring_id']); ?>" onclick="return confirm('Cancel this subscription?');">Cancel</a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($subscriptions)) { ?>
                                <tr><td colspan="13">No subscriptions found for the selected filters.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="nmx-footer">
            <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                <img src="images/numinix_logo.png" alt="Numinix">
            </a>
        </div>
    </div>
</div>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>

<script>
var baseUrl = <?php echo json_encode(zen_href_link(FILENAME_PAYPALR_SAVED_CARD_RECURRING)); ?>;
var queryString = <?php echo json_encode($query_string); ?>;

function toggleEdit(el) {
    var parent = el.parentNode;
    var editContent = parent.querySelector('.edit-content');
    if (editContent) {
        editContent.classList.toggle('active');
    }
}

function updateProduct(id, originalOrdersProductsId) {
    var select = document.querySelector('select[name="set_products_id_' + id + '"]');
    var productsId = select ? select.value : '';
    window.location.href = baseUrl + '?action=update_product_id&saved_card_recurring_id=' + id + 
        '&set_products_id=' + productsId + '&original_orders_products_id=' + originalOrdersProductsId + queryString;
}

function updateAmount(id) {
    var input = document.getElementById('set_amount_' + id);
    var amount = input ? input.value : '';
    window.location.href = baseUrl + '?action=update_amount_subscription&saved_card_recurring_id=' + id + 
        '&set_amount=' + encodeURIComponent(amount) + queryString;
}

function updateDate(id) {
    var input = document.getElementById('set_date_' + id);
    var date = input ? input.value : '';
    window.location.href = baseUrl + '?action=update_payment_date&saved_card_recurring_id=' + id + 
        '&set_date=' + encodeURIComponent(date) + queryString;
}

function updateCard(id) {
    var select = document.querySelector('select[name="set_card_' + id + '"]');
    var cardId = select ? select.value : '';
    window.location.href = baseUrl + '?action=update_credit_card&saved_card_recurring_id=' + id + 
        '&set_card=' + cardId + queryString;
}
</script>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
