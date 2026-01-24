<?php
/**
 * Admin report for managing vaulted subscriptions created via PayPal Advanced Checkout.
 *
 * Lists the normalized subscription records captured by the recurring observer and
 * lets administrators adjust billing metadata, update vault assignments, and manage
 * statuses for any saved payment instrument (cards, wallets, etc.).
 * 
 * Compatible with:
 * - paypalwpp.php (Website Payments Pro)
 * - paypal.php (PayPal Standard)
 * - paypaldp.php (Direct Payments)
 * - paypalr.php (REST API)
 * - payflow.php (Payflow)
 */

require 'includes/application_top.php';

$autoloaderPath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

// Load PayPalProfileManager for legacy profile operations
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php';
}

// Load saved card recurring class
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';
}

use PayPalRestful\Common\SubscriptionManager;
use PayPalRestful\Common\VaultManager;

SubscriptionManager::ensureSchema();
VaultManager::ensureSchema();

define('FILENAME_PAYPALR_SUBSCRIPTIONS', basename(__FILE__));

if (!defined('HEADING_TITLE')) {
    define('HEADING_TITLE', 'Vaulted Subscriptions');
}

$messageStackKey = 'paypalr_subscriptions';

/**
 * @return array<string,string>
 */
function paypalr_known_status_labels()
{
    return [
        'pending' => 'Pending',
        'awaiting_vault' => 'Awaiting Vault',
        'scheduled' => 'Scheduled',
        'active' => 'Active',
        'paused' => 'Paused',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
        'complete' => 'Complete',
        'failed' => 'Failed',
    ];
}

/**
 * Get PayPalProfileManager instance for API operations
 * @return PayPalProfileManager|null
 */
function paypalr_get_profile_manager()
{
    static $profileManager = null;
    static $initialized = false;
    
    if ($initialized) {
        return $profileManager;
    }
    $initialized = true;
    
    if (!class_exists('PayPalProfileManager')) {
        return null;
    }
    
    try {
        $PayPal = null;
        
        // Initialize legacy PayPal API if available
        if (defined('MODULE_PAYMENT_PAYPALWPP_STATUS') && MODULE_PAYMENT_PAYPALWPP_STATUS === 'True') {
            if (file_exists(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php')) {
                require_once DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php';
                $PayPalConfig = [
                    'Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox'),
                    'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME,
                    'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD,
                    'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE
                ];
                if (class_exists('PayPal')) {
                    $PayPal = new PayPal($PayPalConfig);
                }
            }
        }
        
        $PayPalRestClient = null;
        if (class_exists('paypalSavedCardRecurring')) {
            $paypalSavedCardRecurring = new paypalSavedCardRecurring();
            $PayPalRestClient = $paypalSavedCardRecurring->get_paypal_rest_client();
        }
        
        $profileManager = PayPalProfileManager::create($PayPalRestClient, $PayPal);
    } catch (Exception $e) {
        error_log('PayPalProfileManager initialization failed: ' . $e->getMessage());
    }
    
    return $profileManager;
}

$action = strtolower(trim((string) ($_POST['action'] ?? $_GET['action'] ?? '')));

if ($action === 'update_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_POST['paypal_subscription_id'] ?? 0);
    $customersId = (int) zen_db_prepare_input($_POST['customers_id'] ?? 0);
    $redirectQuery = trim((string) ($_POST['redirect_query'] ?? ''));
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);

    if ($subscriptionId <= 0) {
        $messageStack->add_session($messageStackKey, 'Unable to update the subscription. Missing identifier.', 'error');
        zen_redirect($redirectUrl);
    }

    $planId = substr((string) zen_db_prepare_input($_POST['plan_id'] ?? ''), 0, 64);
    $productsId = (int) zen_db_prepare_input($_POST['products_id'] ?? 0);
    $productsName = (string) zen_db_prepare_input($_POST['products_name'] ?? '');
    $productsQuantity = (float) zen_db_prepare_input($_POST['products_quantity'] ?? 1);
    $billingPeriod = strtoupper(str_replace([' ', "\t"], '_', (string) zen_db_prepare_input($_POST['billing_period'] ?? '')));
    $billingFrequency = (int) zen_db_prepare_input($_POST['billing_frequency'] ?? 0);
    $totalCycles = (int) zen_db_prepare_input($_POST['total_billing_cycles'] ?? 0);
    $trialPeriod = strtoupper(str_replace([' ', "\t"], '_', (string) zen_db_prepare_input($_POST['trial_period'] ?? '')));
    $trialFrequency = (int) zen_db_prepare_input($_POST['trial_frequency'] ?? 0);
    $trialTotalCycles = (int) zen_db_prepare_input($_POST['trial_total_cycles'] ?? 0);
    $setupFee = (float) zen_db_prepare_input($_POST['setup_fee'] ?? 0);
    $amount = (float) zen_db_prepare_input($_POST['amount'] ?? 0);
    $currencyCode = substr(strtoupper((string) zen_db_prepare_input($_POST['currency_code'] ?? '')), 0, 3);
    $currencyValue = (float) zen_db_prepare_input($_POST['currency_value'] ?? 1);
    $status = strtolower(trim((string) zen_db_prepare_input($_POST['status'] ?? '')));
    $manualVaultId = substr((string) zen_db_prepare_input($_POST['vault_id'] ?? ''), 0, 64);
    $selectedVaultId = (int) zen_db_prepare_input($_POST['paypal_vault_id'] ?? 0);

    if (isset($_POST['set_status']) && $_POST['set_status'] !== '') {
        $status = strtolower(trim((string) zen_db_prepare_input($_POST['set_status'])));
    }

    $attributesEncoded = '';
    $rawAttributes = trim((string) ($_POST['attributes'] ?? ''));
    if ($rawAttributes !== '') {
        $decodedAttributes = json_decode($rawAttributes, true);
        if ($decodedAttributes === null && json_last_error() !== JSON_ERROR_NONE) {
            $messageStack->add_session($messageStackKey, 'The attributes JSON is invalid and was not saved.', 'error');
            zen_redirect($redirectUrl);
        }

        $encoded = json_encode($decodedAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $attributesEncoded = $encoded;
        }
    }

    $updateData = [
        'plan_id' => $planId,
        'products_id' => $productsId,
        'products_name' => $productsName,
        'products_quantity' => $productsQuantity,
        'billing_period' => $billingPeriod,
        'billing_frequency' => $billingFrequency,
        'total_billing_cycles' => $totalCycles,
        'trial_period' => $trialPeriod,
        'trial_frequency' => $trialFrequency,
        'trial_total_cycles' => $trialTotalCycles,
        'setup_fee' => $setupFee,
        'amount' => $amount,
        'currency_code' => $currencyCode,
        'currency_value' => $currencyValue,
        'status' => $status,
        'last_modified' => date('Y-m-d H:i:s'),
    ];

    if ($attributesEncoded !== '') {
        $updateData['attributes'] = $attributesEncoded;
    } else {
        $updateData['attributes'] = '';
    }

    if ($selectedVaultId > 0) {
        $vaultRecord = VaultManager::getCustomerVaultCard($customersId, $selectedVaultId);
        if ($vaultRecord === null) {
            $messageStack->add_session($messageStackKey, 'Unable to link the selected vaulted instrument. Please verify it still exists.', 'error');
            zen_redirect($redirectUrl);
        }

        $updateData['paypal_vault_id'] = (int) $vaultRecord['paypal_vault_id'];
        $updateData['vault_id'] = substr((string) ($vaultRecord['vault_id'] ?? ''), 0, 64);
    } else {
        $updateData['paypal_vault_id'] = 0;
        $updateData['vault_id'] = $manualVaultId;
    }

    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        $updateData,
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );

    $messageStack->add_session(
        $messageStackKey,
        sprintf('Subscription #%d has been updated.', $subscriptionId),
        'success'
    );

    zen_redirect($redirectUrl);
}

// Cancel subscription action
if ($action === 'cancel_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session($messageStackKey, 'Unable to cancel subscription. Missing identifier.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update local status
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['status' => 'cancelled', 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    // Try to cancel via PayPal API if profile_id exists
    $subscription = $db->Execute(
        "SELECT plan_id FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE paypal_subscription_id = " . (int) $subscriptionId
    );
    
    if ($subscription->RecordCount() > 0 && !empty($subscription->fields['plan_id'])) {
        $profileManager = paypalr_get_profile_manager();
        if ($profileManager !== null) {
            try {
                $profileManager->cancelProfile(['profile_id' => $subscription->fields['plan_id']]);
            } catch (Exception $e) {
                // Log but don't fail - local status is already updated
                error_log('Failed to cancel PayPal profile: ' . $e->getMessage());
            }
        }
    }
    
    $messageStack->add_session($messageStackKey, sprintf('Subscription #%d has been cancelled.', $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Suspend subscription action
if ($action === 'suspend_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session($messageStackKey, 'Unable to suspend subscription. Missing identifier.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update local status
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['status' => 'suspended', 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    // Try to suspend via PayPal API if profile_id exists
    $subscription = $db->Execute(
        "SELECT plan_id FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE paypal_subscription_id = " . (int) $subscriptionId
    );
    
    if ($subscription->RecordCount() > 0 && !empty($subscription->fields['plan_id'])) {
        $profileManager = paypalr_get_profile_manager();
        if ($profileManager !== null) {
            try {
                $profileManager->suspendProfile(['profile_id' => $subscription->fields['plan_id']]);
            } catch (Exception $e) {
                error_log('Failed to suspend PayPal profile: ' . $e->getMessage());
            }
        }
    }
    
    $messageStack->add_session($messageStackKey, sprintf('Subscription #%d has been suspended.', $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Reactivate subscription action
if ($action === 'reactivate_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session($messageStackKey, 'Unable to reactivate subscription. Missing identifier.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update local status
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['status' => 'active', 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    // Try to reactivate via PayPal API if profile_id exists
    $subscription = $db->Execute(
        "SELECT plan_id FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " WHERE paypal_subscription_id = " . (int) $subscriptionId
    );
    
    if ($subscription->RecordCount() > 0 && !empty($subscription->fields['plan_id'])) {
        $profileManager = paypalr_get_profile_manager();
        if ($profileManager !== null) {
            try {
                $profileManager->reactivateProfile(['profile_id' => $subscription->fields['plan_id']]);
            } catch (Exception $e) {
                error_log('Failed to reactivate PayPal profile: ' . $e->getMessage());
            }
        }
    }
    
    $messageStack->add_session($messageStackKey, sprintf('Subscription #%d has been reactivated.', $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// CSV Export action
if ($action === 'export_csv') {
    $exportFilters = [
        'customers_id' => (int) ($_GET['customers_id'] ?? 0),
        'products_id' => (int) ($_GET['products_id'] ?? 0),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'payment_module' => trim((string) ($_GET['payment_module'] ?? '')),
    ];
    
    $exportWhere = [];
    if ($exportFilters['customers_id'] > 0) {
        $exportWhere[] = 'ps.customers_id = ' . (int) $exportFilters['customers_id'];
    }
    if ($exportFilters['products_id'] > 0) {
        $exportWhere[] = 'ps.products_id = ' . (int) $exportFilters['products_id'];
    }
    if ($exportFilters['status'] !== '') {
        $exportWhere[] = "ps.status = '" . zen_db_input($exportFilters['status']) . "'";
    }
    if ($exportFilters['payment_module'] !== '') {
        $exportWhere[] = "o.payment_module_code = '" . zen_db_input($exportFilters['payment_module']) . "'";
    }
    
    $exportSql = 'SELECT ps.*, c.customers_firstname, c.customers_lastname, c.customers_email_address,'
        . ' o.payment_module_code, o.payment_method,'
        . ' pv.brand AS vault_brand, pv.last_digits AS vault_last_digits, pv.card_type AS vault_card_type'
        . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
        . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = ps.customers_id'
        . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = ps.orders_id'
        . ' LEFT JOIN ' . TABLE_PAYPAL_VAULT . ' pv ON pv.paypal_vault_id = ps.paypal_vault_id';
    
    if (!empty($exportWhere)) {
        $exportSql .= ' WHERE ' . implode(' AND ', $exportWhere);
    }
    
    $exportSql .= ' ORDER BY ps.date_added DESC';
    
    $exportResults = $db->Execute($exportSql);
    
    // Generate CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=subscriptions_export_' . date('Y-m-d_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Subscription ID',
        'Customer ID',
        'Customer Name',
        'Customer Email',
        'Order ID',
        'Product ID',
        'Product Name',
        'Quantity',
        'Amount',
        'Currency',
        'Billing Period',
        'Billing Frequency',
        'Total Cycles',
        'Status',
        'Payment Method',
        'Vault Card Type',
        'Vault Last 4',
        'Date Added',
        'Last Modified'
    ]);
    
    if ($exportResults instanceof queryFactoryResult && $exportResults->RecordCount() > 0) {
        while (!$exportResults->EOF) {
            $row = $exportResults->fields;
            fputcsv($output, [
                $row['paypal_subscription_id'],
                $row['customers_id'],
                trim(($row['customers_firstname'] ?? '') . ' ' . ($row['customers_lastname'] ?? '')),
                $row['customers_email_address'] ?? '',
                $row['orders_id'] ?? '',
                $row['products_id'] ?? '',
                $row['products_name'] ?? '',
                $row['products_quantity'] ?? 1,
                $row['amount'] ?? 0,
                $row['currency_code'] ?? '',
                $row['billing_period'] ?? '',
                $row['billing_frequency'] ?? '',
                $row['total_billing_cycles'] ?? '',
                $row['status'] ?? '',
                trim(($row['payment_module_code'] ?? '') . ' ' . ($row['payment_method'] ?? '')),
                trim(($row['vault_card_type'] ?? '') . ' ' . ($row['vault_brand'] ?? '')),
                $row['vault_last_digits'] ?? '',
                $row['date_added'] ?? '',
                $row['last_modified'] ?? ''
            ]);
            $exportResults->MoveNext();
        }
    }
    
    fclose($output);
    exit;
}

$filters = [
    'customers_id' => (int) ($_GET['customers_id'] ?? 0),
    'products_id' => (int) ($_GET['products_id'] ?? 0),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'payment_module' => trim((string) ($_GET['payment_module'] ?? '')),
];

$whereClauses = [];

if ($filters['customers_id'] > 0) {
    $whereClauses[] = 'ps.customers_id = ' . (int) $filters['customers_id'];
}
if ($filters['products_id'] > 0) {
    $whereClauses[] = 'ps.products_id = ' . (int) $filters['products_id'];
}
if ($filters['status'] !== '') {
    $whereClauses[] = "ps.status = '" . zen_db_input($filters['status']) . "'";
}
if ($filters['payment_module'] !== '') {
    $whereClauses[] = "o.payment_module_code = '" . zen_db_input($filters['payment_module']) . "'";
}

$queryString = [];
foreach ($filters as $key => $value) {
    if ($value === '' || $value === 0) {
        continue;
    }
    $queryString[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
}
$activeQuery = implode('&', $queryString);

$statusRecords = $db->Execute(
    'SELECT DISTINCT status FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ORDER BY status'
);

$availableStatuses = paypalr_known_status_labels();
if ($statusRecords instanceof queryFactoryResult && $statusRecords->RecordCount() > 0) {
    while (!$statusRecords->EOF) {
        $statusValue = (string) $statusRecords->fields['status'];
        if ($statusValue !== '' && !isset($availableStatuses[$statusValue])) {
            $availableStatuses[$statusValue] = ucwords(str_replace('_', ' ', $statusValue));
        }
        $statusRecords->MoveNext();
    }
}

$customersOptions = [];
$customerRecords = $db->Execute(
    'SELECT DISTINCT ps.customers_id, c.customers_firstname, c.customers_lastname'
    . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
    . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = ps.customers_id'
    . ' ORDER BY c.customers_lastname, c.customers_firstname'
);
if ($customerRecords instanceof queryFactoryResult) {
    while (!$customerRecords->EOF) {
        $cid = (int) $customerRecords->fields['customers_id'];
        if ($cid > 0) {
            $customersOptions[$cid] = trim($customerRecords->fields['customers_lastname'] . ', ' . $customerRecords->fields['customers_firstname']);
        }
        $customerRecords->MoveNext();
    }
}

$productOptions = [];
$productRecords = $db->Execute(
    'SELECT DISTINCT ps.products_id, ps.products_name'
    . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
    . ' ORDER BY ps.products_name'
);
if ($productRecords instanceof queryFactoryResult) {
    while (!$productRecords->EOF) {
        $pid = (int) $productRecords->fields['products_id'];
        if ($pid > 0) {
            $productOptions[$pid] = $productRecords->fields['products_name'];
        }
        $productRecords->MoveNext();
    }
}

$paymentModuleOptions = [];
$paymentRecords = $db->Execute(
    'SELECT DISTINCT o.payment_module_code, o.payment_method'
    . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
    . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = ps.orders_id'
    . ' WHERE o.payment_module_code IS NOT NULL AND o.payment_module_code <> ""'
    . ' ORDER BY o.payment_module_code'
);
if ($paymentRecords instanceof queryFactoryResult) {
    while (!$paymentRecords->EOF) {
        $code = (string) $paymentRecords->fields['payment_module_code'];
        if ($code !== '') {
            $label = $code;
            $method = (string) $paymentRecords->fields['payment_method'];
            if ($method !== '') {
                $label .= ' - ' . $method;
            }
            $paymentModuleOptions[$code] = $label;
        }
        $paymentRecords->MoveNext();
    }
}

$sql = 'SELECT ps.*, c.customers_firstname, c.customers_lastname, c.customers_email_address,'
    . ' o.payment_module_code, o.payment_method,'
    . ' pv.brand AS vault_brand, pv.last_digits AS vault_last_digits, pv.card_type AS vault_card_type, pv.status AS vault_status, pv.expiry AS vault_expiry'
    . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
    . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = ps.customers_id'
    . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = ps.orders_id'
    . ' LEFT JOIN ' . TABLE_PAYPAL_VAULT . ' pv ON pv.paypal_vault_id = ps.paypal_vault_id';

if (!empty($whereClauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$sql .= ' ORDER BY ps.date_added DESC, ps.paypal_subscription_id DESC';

$subscriptions = $db->Execute($sql);

$subscriptionRows = [];
if ($subscriptions instanceof queryFactoryResult && $subscriptions->RecordCount() > 0) {
    while (!$subscriptions->EOF) {
        $subscriptionRows[] = $subscriptions->fields;
        $subscriptions->MoveNext();
    }
}

$vaultCache = [];

function paypalr_render_select_options(array $options, $selectedValue): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $isSelected = ((string) $value === (string) $selectedValue) ? ' selected="selected"' : '';
        $html .= '<option value="' . zen_output_string_protected((string) $value) . '"' . $isSelected . '>'
            . zen_output_string_protected((string) $label) . '</option>';
    }
    return $html;
}

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <style>
        .paypalr-subscriptions-container {
            padding: 1.5rem;
        }
        .paypalr-subscriptions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .paypalr-subscriptions-table th,
        .paypalr-subscriptions-table td {
            border: 1px solid #ccc;
            padding: 0.75rem;
            vertical-align: top;
        }
        .paypalr-subscriptions-table th {
            background: #f8f8f8;
            text-align: left;
        }
        .paypalr-subscriptions-table td textarea {
            width: 100%;
            min-height: 120px;
            font-family: monospace;
        }
        .paypalr-subscriptions-table td input[type="text"],
        .paypalr-subscriptions-table td input[type="number"],
        .paypalr-subscriptions-table td select {
            width: 100%;
            box-sizing: border-box;
        }
        .paypalr-subscription-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .paypalr-filter-form {
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .paypalr-filter-form .form-group {
            min-width: 200px;
        }
        .paypalr-filter-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        .paypalr-subscription-meta {
            font-size: 0.9em;
            color: #555;
        }
    </style>
</head>
<body>
    <?php require DIR_WS_INCLUDES . 'header.php'; ?>
    <div class="paypalr-subscriptions-container">
        <h1><?php echo HEADING_TITLE; ?></h1>

        <?php
        if (isset($messageStack) && is_object($messageStack)) {
            if (method_exists($messageStack, 'size')) {
                if ($messageStack->size($messageStackKey) > 0) {
                    echo $messageStack->output($messageStackKey);
                }
            } else {
                // Fallback for messageStack implementations without size() method
                // Check if there are messages in the stack before outputting
                $hasMessages = false;
                if (isset($messageStack->messages) && is_array($messageStack->messages)) {
                    $hasMessages = isset($messageStack->messages[$messageStackKey]) && 
                                  is_array($messageStack->messages[$messageStackKey]) && 
                                  count($messageStack->messages[$messageStackKey]) > 0;
                }
                if ($hasMessages) {
                    echo $messageStack->output($messageStackKey);
                }
            }
        }
        ?>

        <form method="get" class="paypalr-filter-form" action="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS); ?>">
            <div class="form-group">
                <label for="filter-customers">Customer</label>
                <select name="customers_id" id="filter-customers">
                    <option value="0">All Customers</option>
                    <?php echo paypalr_render_select_options($customersOptions, $filters['customers_id']); ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter-products">Product</label>
                <select name="products_id" id="filter-products">
                    <option value="0">All Products</option>
                    <?php echo paypalr_render_select_options($productOptions, $filters['products_id']); ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter-status">Status</label>
                <select name="status" id="filter-status">
                    <option value="">All Statuses</option>
                    <?php echo paypalr_render_select_options($availableStatuses, $filters['status']); ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter-payment">Payment Method</label>
                <select name="payment_module" id="filter-payment">
                    <option value="">All Methods</option>
                    <?php echo paypalr_render_select_options($paymentModuleOptions, $filters['payment_module']); ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit">Apply Filters</button>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, 'action=export_csv' . ($activeQuery !== '' ? '&' . $activeQuery : '')); ?>" class="btn btn-default" style="display: inline-block; padding: 5px 15px; background: #f0f0f0; border: 1px solid #ccc; text-decoration: none; color: #333;">Export CSV</a>
            </div>
        </form>

        <table class="paypalr-subscriptions-table">
            <thead>
                <tr>
                    <th>Subscription</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Billing Details</th>
                    <th>Financials</th>
                    <th>Vault Instrument</th>
                    <th>Status &amp; Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptionRows)) { ?>
                    <tr>
                        <td colspan="7">No subscriptions found for the selected filters.</td>
                    </tr>
                <?php }
                foreach ($subscriptionRows as $row) {
                    $subscriptionId = (int) ($row['paypal_subscription_id'] ?? 0);
                    $formId = 'subscription-form-' . $subscriptionId;
                    $customerName = trim(($row['customers_firstname'] ?? '') . ' ' . ($row['customers_lastname'] ?? ''));
                    $paymentSummary = trim(($row['payment_module_code'] ?? '') . ' ' . ($row['payment_method'] ?? ''));
                    $attributes = [];
                    if (!empty($row['attributes'])) {
                        $decoded = json_decode((string) $row['attributes'], true);
                        if (is_array($decoded)) {
                            $attributes = $decoded;
                        }
                    }
                    $attributesPretty = $attributes ? json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

                    $customersId = (int) ($row['customers_id'] ?? 0);
                    if ($customersId > 0 && !array_key_exists($customersId, $vaultCache)) {
                        $vaultCache[$customersId] = VaultManager::getCustomerVaultedCards($customersId, false);
                    }

                    $vaultOptions = ['0' => 'None'];
                    if (!empty($vaultCache[$customersId])) {
                        foreach ($vaultCache[$customersId] as $vaultCard) {
                            $label = '#'.$vaultCard['paypal_vault_id'] . ' ' . ($vaultCard['card_type'] ?? $vaultCard['brand'] ?? '');
                            if (!empty($vaultCard['last_digits'])) {
                                $label .= ' ••••' . $vaultCard['last_digits'];
                            }
                            if (!empty($vaultCard['status'])) {
                                $label .= ' (' . $vaultCard['status'] . ')';
                            }
                            $vaultOptions[(string) $vaultCard['paypal_vault_id']] = $label;
                        }
                    }

                    ?>
                    <form id="<?php echo $formId; ?>" method="post" action="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS); ?>">
                        <?php echo zen_draw_hidden_field('action', 'update_subscription'); ?>
                        <?php echo zen_draw_hidden_field('paypal_subscription_id', $subscriptionId); ?>
                        <?php echo zen_draw_hidden_field('customers_id', $customersId); ?>
                        <?php echo zen_draw_hidden_field('redirect_query', $activeQuery); ?>
                    </form>
                    <tr>
                        <td>
                            <strong>#<?php echo (int) $row['paypal_subscription_id']; ?></strong>
                            <div class="paypalr-subscription-meta">
                                <?php if (!empty($row['orders_id'])) { ?>
                                    Order: <a href="<?php echo zen_href_link(FILENAME_ORDERS, 'oID=' . (int) $row['orders_id'] . '&action=edit'); ?>">#<?php echo (int) $row['orders_id']; ?></a><br />
                                <?php } ?>
                                <?php if (!empty($row['orders_products_id'])) { ?>
                                    Order Item ID: <?php echo (int) $row['orders_products_id']; ?><br />
                                <?php } ?>
                                <?php if (!empty($row['plan_id'])) { ?>
                                    Plan: <?php echo zen_output_string_protected($row['plan_id']); ?><br />
                                <?php } ?>
                                Added: <?php echo zen_date_short($row['date_added'] ?? ''); ?><br />
                                Updated: <?php echo zen_date_short($row['last_modified'] ?? ''); ?><br />
                                <?php if ($paymentSummary !== '') { ?>
                                    Paid with: <?php echo zen_output_string_protected($paymentSummary); ?><br />
                                <?php } ?>
                                Customer Email: <?php echo zen_output_string_protected($row['customers_email_address'] ?? ''); ?>
                            </div>
                        </td>
                        <td>
                            <?php echo $customerName !== '' ? zen_output_string_protected($customerName) : 'Unknown Customer'; ?>
                        </td>
                        <td>
                            <label>Product Name</label>
                            <input type="text" name="products_name" value="<?php echo zen_output_string_protected((string) ($row['products_name'] ?? '')); ?>" form="<?php echo $formId; ?>" />
                            <label>Product ID</label>
                            <input type="number" name="products_id" value="<?php echo (int) ($row['products_id'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Quantity</label>
                            <input type="number" step="0.01" name="products_quantity" value="<?php echo (float) ($row['products_quantity'] ?? 1); ?>" form="<?php echo $formId; ?>" />
                        </td>
                        <td>
                            <label>Billing Period</label>
                            <input type="text" name="billing_period" value="<?php echo zen_output_string_protected((string) ($row['billing_period'] ?? '')); ?>" form="<?php echo $formId; ?>" />
                            <label>Billing Frequency</label>
                            <input type="number" name="billing_frequency" value="<?php echo (int) ($row['billing_frequency'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Total Billing Cycles</label>
                            <input type="number" name="total_billing_cycles" value="<?php echo (int) ($row['total_billing_cycles'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Trial Period</label>
                            <input type="text" name="trial_period" value="<?php echo zen_output_string_protected((string) ($row['trial_period'] ?? '')); ?>" form="<?php echo $formId; ?>" />
                            <label>Trial Frequency</label>
                            <input type="number" name="trial_frequency" value="<?php echo (int) ($row['trial_frequency'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Trial Cycles</label>
                            <input type="number" name="trial_total_cycles" value="<?php echo (int) ($row['trial_total_cycles'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                        </td>
                        <td>
                            <label>Setup Fee</label>
                            <input type="number" step="0.01" name="setup_fee" value="<?php echo (float) ($row['setup_fee'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Amount</label>
                            <input type="number" step="0.01" name="amount" value="<?php echo (float) ($row['amount'] ?? 0); ?>" form="<?php echo $formId; ?>" />
                            <label>Currency Code</label>
                            <input type="text" maxlength="3" name="currency_code" value="<?php echo zen_output_string_protected((string) ($row['currency_code'] ?? '')); ?>" form="<?php echo $formId; ?>" />
                            <label>Currency Value</label>
                            <input type="number" step="0.000001" name="currency_value" value="<?php echo (float) ($row['currency_value'] ?? 1); ?>" form="<?php echo $formId; ?>" />
                        </td>
                        <td>
                            <label>Vault Assignment</label>
                            <select name="paypal_vault_id" form="<?php echo $formId; ?>">
                                <?php echo paypalr_render_select_options($vaultOptions, $row['paypal_vault_id'] ?? '0'); ?>
                            </select>
                            <label>Vault ID (manual override)</label>
                            <input type="text" name="vault_id" value="<?php echo zen_output_string_protected((string) ($row['vault_id'] ?? '')); ?>" form="<?php echo $formId; ?>" />
                            <?php if (!empty($row['vault_brand']) || !empty($row['vault_card_type'])) { ?>
                                <div class="paypalr-subscription-meta">
                                    Stored: <?php echo zen_output_string_protected(trim(($row['vault_card_type'] ?? '') . ' ' . ($row['vault_brand'] ?? ''))); ?><br />
                                    <?php if (!empty($row['vault_last_digits'])) { ?>
                                        ••••<?php echo zen_output_string_protected($row['vault_last_digits']); ?><br />
                                    <?php } ?>
                                    <?php if (!empty($row['vault_expiry'])) { ?>
                                        Expires: <?php echo zen_output_string_protected($row['vault_expiry']); ?><br />
                                    <?php } ?>
                                    <?php if (!empty($row['vault_status'])) { ?>
                                        Status: <?php echo zen_output_string_protected($row['vault_status']); ?>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <label>Current Status</label>
                            <select name="status" form="<?php echo $formId; ?>">
                                <?php echo paypalr_render_select_options($availableStatuses, $row['status'] ?? ''); ?>
                            </select>
                            <div class="paypalr-subscription-actions">
                                <button type="submit" form="<?php echo $formId; ?>">Save Changes</button>
                                <button type="submit" name="set_status" value="cancelled" form="<?php echo $formId; ?>">Mark Cancelled</button>
                                <button type="submit" name="set_status" value="active" form="<?php echo $formId; ?>">Mark Active</button>
                                <button type="submit" name="set_status" value="pending" form="<?php echo $formId; ?>">Mark Pending</button>
                            </div>
                            <div class="paypalr-subscription-actions" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                                <?php 
                                $currentStatus = strtolower($row['status'] ?? '');
                                $actionParams = $activeQuery !== '' ? $activeQuery . '&' : '';
                                ?>
                                <?php if ($currentStatus === 'active' || $currentStatus === 'scheduled') { ?>
                                    <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=suspend_subscription&subscription_id=' . $subscriptionId); ?>" 
                                       onclick="return confirm('Are you sure you want to suspend this subscription?');"
                                       style="padding: 3px 8px; background: #f0ad4e; color: #fff; text-decoration: none; border-radius: 3px; font-size: 12px;">Suspend</a>
                                    <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=cancel_subscription&subscription_id=' . $subscriptionId); ?>" 
                                       onclick="return confirm('Are you sure you want to cancel this subscription? This action cannot be undone.');"
                                       style="padding: 3px 8px; background: #d9534f; color: #fff; text-decoration: none; border-radius: 3px; font-size: 12px;">Cancel</a>
                                <?php } elseif ($currentStatus === 'suspended' || $currentStatus === 'paused') { ?>
                                    <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=reactivate_subscription&subscription_id=' . $subscriptionId); ?>" 
                                       onclick="return confirm('Are you sure you want to reactivate this subscription?');"
                                       style="padding: 3px 8px; background: #5cb85c; color: #fff; text-decoration: none; border-radius: 3px; font-size: 12px;">Reactivate</a>
                                    <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=cancel_subscription&subscription_id=' . $subscriptionId); ?>" 
                                       onclick="return confirm('Are you sure you want to cancel this subscription? This action cannot be undone.');"
                                       style="padding: 3px 8px; background: #d9534f; color: #fff; text-decoration: none; border-radius: 3px; font-size: 12px;">Cancel</a>
                                <?php } elseif ($currentStatus === 'cancelled') { ?>
                                    <span style="color: #999; font-size: 12px;">Subscription cancelled</span>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7">
                            <label for="attributes-<?php echo $subscriptionId; ?>">Attributes (JSON)</label>
                            <textarea id="attributes-<?php echo $subscriptionId; ?>" name="attributes" form="<?php echo $formId; ?>" placeholder="{ }"><?php echo zen_output_string_protected($attributesPretty); ?></textarea>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
