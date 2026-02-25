<?php
/**
 * Active Subscriptions Report
 * 
 * Aggregated subscription report by product showing:
 * - Total subscriptions per product
 * - Annual value calculations
 * - Type and status breakdown
 * - Next billing dates
 * 
 * Compatible with:
 * - paypalwpp.php (Website Payments Pro)
 * - paypal.php (PayPal Standard)
 * - paypaldp.php (Direct Payments)
 * - paypalac.php (REST API)
 * - payflow.php (Payflow)
 */

require 'includes/application_top.php';

// Load PayPal REST API autoloader
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';

define('FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT', basename(__FILE__));

if (!defined('HEADING_TITLE')) {
    define('HEADING_TITLE', 'Active Subscriptions Report');
}

require DIR_WS_CLASSES . 'currencies.php';
$currencies = new currencies();

/**
 * Compute annual value from billing amount, period, and frequency
 */
function asr_compute_annual_value($amount, $period, $frequency)
{
    $amount = (float)$amount;
    $period = is_string($period) ? strtolower(trim($period)) : '';
    $frequency = (int)$frequency;

    if ($amount <= 0 || $period === '') {
        return null;
    }

    // Normalize mismatched period/frequency data
    if ($period === 'year' && $frequency >= 365 && $frequency <= 366) {
        $period = 'day';
    }

    switch ($period) {
        case 'day':
            $periodsPerYear = 365;
            break;
        case 'week':
            $periodsPerYear = 52;
            break;
        case 'semimonth':
        case 'semi-month':
            $periodsPerYear = 24;
            break;
        case 'month':
            $periodsPerYear = 12;
            break;
        case 'year':
            $periodsPerYear = 1;
            break;
        default:
            return null;
    }

    if ($frequency <= 0) {
        $frequency = 1;
    }

    $chargesPerYear = $periodsPerYear / $frequency;
    if ($chargesPerYear <= 0) {
        return null;
    }

    return $amount * $chargesPerYear;
}

/**
 * Format currency amount
 */
function asr_format_currency($currencies, $amount, $currencyCode)
{
    $currencyCode = ($currencyCode !== null && $currencyCode !== '') ? $currencyCode : (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');

    if (!is_object($currencies) || !method_exists($currencies, 'format')) {
        return number_format((float)$amount, 2) . ' ' . $currencyCode;
    }

    return $currencies->format((float)$amount, true, $currencyCode);
}

/**
 * Build sort link URL
 */
function asr_build_sort_link($field, $currentSort, $currentDir)
{
    $direction = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
    $params = zen_get_all_get_params(['sort', 'dir']);
    return zen_href_link(FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT, $params . 'sort=' . $field . '&dir=' . $direction);
}

/**
 * Format billing description
 */
function asr_format_billing_description($frequency, $period)
{
    $period = trim((string)$period);
    $frequency = (int)$frequency;

    if ($period === '') {
        return TEXT_BILLING_DESCRIPTION_UNKNOWN;
    }

    if ($frequency <= 0) {
        $frequency = 1;
    }

    return sprintf(TEXT_BILLING_DESCRIPTION, $frequency, $period);
}

/**
 * Format date value
 */
function asr_format_date_value($dateValue)
{
    if (!is_string($dateValue)) {
        return TEXT_VALUE_NOT_AVAILABLE;
    }

    $trimmed = trim($dateValue);
    if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000-00-00 00:00:00') {
        return TEXT_VALUE_NOT_AVAILABLE;
    }

    if (function_exists('zen_date_short')) {
        return zen_date_short($trimmed);
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        return TEXT_VALUE_NOT_AVAILABLE;
    }

    return date('Y-m-d', $timestamp);
}

/**
 * Sort indicator arrow
 */
function asr_sort_indicator($field, $currentSort, $currentDir)
{
    if ($field !== $currentSort) {
        return '';
    }
    return $currentDir === 'asc' ? ' ▲' : ' ▼';
}

// Parse filters
$statusOptions = ['active', 'suspended'];
$statusFilter = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'active';
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'active';
}

$statusFiltersMap = [
    'active' => [
        'paypal' => ['Active', 'Scheduled'],
        'savedcard' => ['scheduled'],
        'rest' => ['active', 'scheduled'],
    ],
    'suspended' => [
        'paypal' => ['Suspended'],
        'savedcard' => ['suspended'],
        'rest' => ['suspended', 'paused'],
    ],
];

$typeOptions = ['all', 'paypal', 'savedcard', 'rest'];
$typeFilter = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'all';
if (!in_array($typeFilter, $typeOptions, true)) {
    $typeFilter = 'all';
}

$searchTerm = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$searchNeedle = strtolower($searchTerm);

$allowedSorts = [
    'product' => ['field' => 'product_sort', 'type' => 'string'],
    'subscriptions' => ['field' => 'subscription_count', 'type' => 'numeric'],
    'next_billing' => ['field' => 'next_billing_sort', 'type' => 'numeric'],
    'annual_value' => ['field' => 'annual_value_sort', 'type' => 'numeric'],
];
$sortField = isset($_GET['sort']) ? strtolower(trim((string)$_GET['sort'])) : 'subscriptions';
if (!array_key_exists($sortField, $allowedSorts)) {
    $sortField = 'subscriptions';
}
$sortDirection = isset($_GET['dir']) ? strtolower(trim((string)$_GET['dir'])) : 'desc';
if ($sortDirection !== 'desc') {
    $sortDirection = 'asc';
}

$languageId = isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
$defaultCurrency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';

$subscriptionAggregates = [];
$typeCounts = ['paypal' => 0, 'savedcard' => 0, 'rest' => 0];
$annualTotals = [];
$totalSubscriptions = 0;

/**
 * Add subscription record to aggregates
 */
function asr_add_subscription_record(array &$aggregates, array $record)
{
    $productName = isset($record['product_name']) ? trim((string)$record['product_name']) : '';
    $productId = isset($record['product_id']) ? (int)$record['product_id'] : 0;
    $typeKey = isset($record['type_key']) ? trim((string)$record['type_key']) : '';
    $status = isset($record['status']) ? trim((string)$record['status']) : '';
    $amount = isset($record['amount']) ? (float)$record['amount'] : 0.0;
    $currencyCode = isset($record['currency']) ? trim((string)$record['currency']) : '';
    $billingPeriod = isset($record['billing_period']) ? trim((string)$record['billing_period']) : '';
    $billingFrequency = isset($record['billing_frequency']) ? (int)$record['billing_frequency'] : 0;
    $annualValue = array_key_exists('annual_value', $record) ? $record['annual_value'] : null;
    $nextBillingSort = isset($record['next_billing_sort']) ? $record['next_billing_sort'] : null;
    $nextBillingRaw = isset($record['next_billing_raw']) ? $record['next_billing_raw'] : '';

    $key = $productId > 0 ? 'id:' . $productId : 'name:' . strtolower($productName);
    if (!isset($aggregates[$key])) {
        $aggregates[$key] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'product_sort' => strtolower($productName),
            'total_subscriptions' => 0,
            'type_counts' => ['paypal' => 0, 'savedcard' => 0, 'rest' => 0],
            'status_counts' => [],
            'plans' => [],
            'annual_totals' => [],
            'next_billing_sort' => PHP_INT_MAX,
            'next_billing_raw' => '',
        ];
    }

    $aggregate =& $aggregates[$key];

    if ($productName !== '') {
        $aggregate['product_name'] = $productName;
        $aggregate['product_sort'] = strtolower($productName);
    }

    $aggregate['total_subscriptions']++;

    if ($typeKey !== '' && isset($aggregate['type_counts'][$typeKey])) {
        $aggregate['type_counts'][$typeKey]++;
    }

    if ($status !== '') {
        if (!isset($aggregate['status_counts'][$status])) {
            $aggregate['status_counts'][$status] = 0;
        }
        $aggregate['status_counts'][$status]++;
    }

    if ($currencyCode !== '' && $annualValue !== null) {
        if (!isset($aggregate['annual_totals'][$currencyCode])) {
            $aggregate['annual_totals'][$currencyCode] = 0.0;
        }
        $aggregate['annual_totals'][$currencyCode] += (float)$annualValue;
    }

    $planKey = strtolower($currencyCode . '|' . $billingPeriod . '|' . $billingFrequency . '|' . number_format($amount, 4, '.', ''));
    if (!isset($aggregate['plans'][$planKey])) {
        $aggregate['plans'][$planKey] = [
            'currency' => $currencyCode,
            'amount' => $amount,
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'count' => 0,
            'annual_total' => 0.0,
            'has_annual' => false,
        ];
    }

    $aggregate['plans'][$planKey]['count']++;
    if ($annualValue !== null) {
        $aggregate['plans'][$planKey]['annual_total'] += (float)$annualValue;
        $aggregate['plans'][$planKey]['has_annual'] = true;
    }

    if ($nextBillingSort !== null && $nextBillingSort !== '' && $nextBillingSort < $aggregate['next_billing_sort']) {
        $aggregate['next_billing_sort'] = $nextBillingSort;
        $aggregate['next_billing_raw'] = $nextBillingRaw;
    }
}

// Fetch PayPal Legacy subscriptions
if (defined('TABLE_PAYPAL_RECURRING') && ($typeFilter === 'all' || $typeFilter === 'paypal')) {
    $paypalStatuses = $statusFiltersMap[$statusFilter]['paypal'];
    $escapedStatuses = array_map(function ($s) { return "'" . zen_db_input($s) . "'"; }, $paypalStatuses);
    
    $paypalQuery = "SELECT pr.*, pd.products_name AS product_name
        FROM " . TABLE_PAYPAL_RECURRING . " pr
        LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = pr.products_id AND pd.language_id = $languageId
        WHERE pr.status IN (" . implode(',', $escapedStatuses) . ")";
    
    $paypalResults = $db->Execute($paypalQuery);
    
    while (!$paypalResults->EOF) {
        $row = $paypalResults->fields;
        $productName = $row['product_name'] ?? ('Product #' . $row['products_id']);
        $status = $row['status'] ?? '';
        $amount = (float)($row['amount'] ?? 0);
        $currencyCode = $row['currencycode'] ?? $defaultCurrency;
        $billingPeriod = $row['billingperiod'] ?? '';
        $billingFrequency = (int)($row['billingfrequency'] ?? 0);
        $nextBillingRaw = $row['next_payment_date'] ?? '';
        $nextBillingSort = ($nextBillingRaw !== '' && strtotime($nextBillingRaw) !== false) ? strtotime($nextBillingRaw) : null;
        
        $annualValue = asr_compute_annual_value($amount, $billingPeriod, $billingFrequency);
        
        $searchHaystack = strtolower($productName . ' ' . $status . ' paypal');
        if ($searchNeedle !== '' && strpos($searchHaystack, $searchNeedle) === false) {
            $paypalResults->MoveNext();
            continue;
        }
        
        $typeCounts['paypal']++;
        $totalSubscriptions++;
        
        if ($annualValue !== null) {
            if (!isset($annualTotals[$currencyCode])) {
                $annualTotals[$currencyCode] = 0.0;
            }
            $annualTotals[$currencyCode] += $annualValue;
        }
        
        asr_add_subscription_record($subscriptionAggregates, [
            'product_id' => (int)($row['products_id'] ?? 0),
            'product_name' => $productName,
            'type_key' => 'paypal',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currencyCode,
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'annual_value' => $annualValue,
            'next_billing_sort' => $nextBillingSort,
            'next_billing_raw' => $nextBillingRaw,
        ]);
        
        $paypalResults->MoveNext();
    }
}

// Fetch Saved Card subscriptions
if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') && ($typeFilter === 'all' || $typeFilter === 'savedcard')) {
    $savedCardStatuses = $statusFiltersMap[$statusFilter]['savedcard'];
    $escapedStatuses = array_map(function ($s) { return "'" . zen_db_input($s) . "'"; }, $savedCardStatuses);
    
    $savedCardQuery = "SELECT sccr.*, pd.products_name AS product_name
        FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
        LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = sccr.products_id AND pd.language_id = $languageId
        WHERE sccr.status IN (" . implode(',', $escapedStatuses) . ")";
    
    $savedCardResults = $db->Execute($savedCardQuery);
    
    while (!$savedCardResults->EOF) {
        $row = $savedCardResults->fields;
        $productName = $row['products_name'] ?? $row['product_name'] ?? ('Product #' . ($row['products_id'] ?? 0));
        $status = $row['status'] ?? '';
        $amount = (float)($row['amount'] ?? 0);
        $currencyCode = $row['currency_code'] ?? $defaultCurrency;
        $billingPeriod = $row['billing_period'] ?? '';
        $billingFrequency = (int)($row['billing_frequency'] ?? 0);
        $nextBillingRaw = $row['next_payment_date'] ?? $row['date_added'] ?? '';
        $nextBillingSort = ($nextBillingRaw !== '' && strtotime($nextBillingRaw) !== false) ? strtotime($nextBillingRaw) : null;
        
        $annualValue = asr_compute_annual_value($amount, $billingPeriod, $billingFrequency);
        
        $searchHaystack = strtolower($productName . ' ' . $status . ' savedcard');
        if ($searchNeedle !== '' && strpos($searchHaystack, $searchNeedle) === false) {
            $savedCardResults->MoveNext();
            continue;
        }
        
        $typeCounts['savedcard']++;
        $totalSubscriptions++;
        
        if ($annualValue !== null) {
            if (!isset($annualTotals[$currencyCode])) {
                $annualTotals[$currencyCode] = 0.0;
            }
            $annualTotals[$currencyCode] += $annualValue;
        }
        
        asr_add_subscription_record($subscriptionAggregates, [
            'product_id' => (int)($row['products_id'] ?? 0),
            'product_name' => $productName,
            'type_key' => 'savedcard',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currencyCode,
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'annual_value' => $annualValue,
            'next_billing_sort' => $nextBillingSort,
            'next_billing_raw' => $nextBillingRaw,
        ]);
        
        $savedCardResults->MoveNext();
    }
}

// Fetch REST API subscriptions
if (defined('TABLE_PAYPAL_SUBSCRIPTIONS') && ($typeFilter === 'all' || $typeFilter === 'rest')) {
    $restStatuses = $statusFiltersMap[$statusFilter]['rest'];
    $escapedStatuses = array_map(function ($s) { return "'" . zen_db_input($s) . "'"; }, $restStatuses);
    
    $restQuery = "SELECT ps.*, pd.products_name AS product_name_db
        FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " ps
        LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = ps.products_id AND pd.language_id = $languageId
        WHERE ps.status IN (" . implode(',', $escapedStatuses) . ")";
    
    $restResults = $db->Execute($restQuery);
    
    while (!$restResults->EOF) {
        $row = $restResults->fields;
        $productName = $row['products_name'] ?? $row['product_name_db'] ?? ('Product #' . ($row['products_id'] ?? 0));
        $status = $row['status'] ?? '';
        $amount = (float)($row['amount'] ?? 0);
        $currencyCode = $row['currency_code'] ?? $defaultCurrency;
        $billingPeriod = $row['billing_period'] ?? '';
        $billingFrequency = (int)($row['billing_frequency'] ?? 0);
        
        // Try to get next billing from attributes
        $nextBillingRaw = '';
        if (!empty($row['attributes'])) {
            $attrs = json_decode($row['attributes'], true);
            if (is_array($attrs) && isset($attrs['next_billing_date'])) {
                $nextBillingRaw = $attrs['next_billing_date'];
            }
        }
        $nextBillingSort = ($nextBillingRaw !== '' && strtotime($nextBillingRaw) !== false) ? strtotime($nextBillingRaw) : null;
        
        $annualValue = asr_compute_annual_value($amount, $billingPeriod, $billingFrequency);
        
        $searchHaystack = strtolower($productName . ' ' . $status . ' rest');
        if ($searchNeedle !== '' && strpos($searchHaystack, $searchNeedle) === false) {
            $restResults->MoveNext();
            continue;
        }
        
        $typeCounts['rest']++;
        $totalSubscriptions++;
        
        if ($annualValue !== null) {
            if (!isset($annualTotals[$currencyCode])) {
                $annualTotals[$currencyCode] = 0.0;
            }
            $annualTotals[$currencyCode] += $annualValue;
        }
        
        asr_add_subscription_record($subscriptionAggregates, [
            'product_id' => (int)($row['products_id'] ?? 0),
            'product_name' => $productName,
            'type_key' => 'rest',
            'status' => $status,
            'amount' => $amount,
            'currency' => $currencyCode,
            'billing_period' => $billingPeriod,
            'billing_frequency' => $billingFrequency,
            'annual_value' => $annualValue,
            'next_billing_sort' => $nextBillingSort,
            'next_billing_raw' => $nextBillingRaw,
        ]);
        
        $restResults->MoveNext();
    }
}

// Build final subscriptions array for display
$typeLabels = [
    'paypal' => TEXT_TYPE_PAYPAL,
    'savedcard' => TEXT_TYPE_SAVED_CARD,
    'rest' => TEXT_TYPE_REST,
];

$subscriptions = [];
foreach ($subscriptionAggregates as $aggregate) {
    $productName = isset($aggregate['product_name']) ? trim((string)$aggregate['product_name']) : TEXT_VALUE_NOT_AVAILABLE;
    
    $typeList = [];
    foreach ($aggregate['type_counts'] as $typeKey => $count) {
        if ($count <= 0) continue;
        $label = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : ucfirst($typeKey);
        $typeList[] = ['label' => $label, 'count' => (int)$count];
    }
    
    $statusList = [];
    if (!empty($aggregate['status_counts'])) {
        $statusCounts = $aggregate['status_counts'];
        arsort($statusCounts);
        foreach ($statusCounts as $status => $count) {
            $statusList[] = ['label' => $status, 'count' => (int)$count];
        }
    }
    
    $planList = [];
    if (!empty($aggregate['plans'])) {
        $plans = $aggregate['plans'];
        uasort($plans, function ($a, $b) {
            return $a['count'] === $b['count'] ? 0 : ($a['count'] > $b['count'] ? -1 : 1);
        });
        foreach ($plans as $plan) {
            $planList[] = [
                'amount_display' => asr_format_currency($currencies, $plan['amount'], $plan['currency']),
                'billing_display' => asr_format_billing_description($plan['billing_frequency'], $plan['billing_period']),
                'count' => (int)$plan['count'],
                'annual_display' => $plan['has_annual'] ? asr_format_currency($currencies, $plan['annual_total'], $plan['currency']) : null,
            ];
        }
    }
    
    $annualList = [];
    $annualValueSort = 0.0;
    if (!empty($aggregate['annual_totals'])) {
        $totals = $aggregate['annual_totals'];
        ksort($totals);
        foreach ($totals as $currencyCode => $total) {
            $annualList[] = [
                'currency' => $currencyCode,
                'display' => asr_format_currency($currencies, $total, $currencyCode),
            ];
            if ((float)$total > $annualValueSort) {
                $annualValueSort = (float)$total;
            }
        }
    }
    
    $nextBillingSort = isset($aggregate['next_billing_sort']) ? $aggregate['next_billing_sort'] : PHP_INT_MAX;
    $nextBillingDisplay = TEXT_VALUE_NOT_AVAILABLE;
    if ($nextBillingSort !== PHP_INT_MAX && isset($aggregate['next_billing_raw']) && $aggregate['next_billing_raw'] !== '') {
        $nextBillingDisplay = asr_format_date_value($aggregate['next_billing_raw']);
    }
    
    $subscriptions[] = [
        'product' => $productName,
        'product_sort' => isset($aggregate['product_sort']) ? $aggregate['product_sort'] : strtolower($productName),
        'subscription_count' => isset($aggregate['total_subscriptions']) ? (int)$aggregate['total_subscriptions'] : 0,
        'type_list' => $typeList,
        'status_list' => $statusList,
        'plan_list' => $planList,
        'next_billing_sort' => $nextBillingSort,
        'next_billing_display' => $nextBillingDisplay,
        'annual_list' => $annualList,
        'annual_value_sort' => $annualValueSort,
    ];
}

// Sort subscriptions
if (count($subscriptions) > 1) {
    $sortConfig = $allowedSorts[$sortField];
    $sortFieldName = $sortConfig['field'];
    $sortType = $sortConfig['type'];
    
    usort($subscriptions, function ($a, $b) use ($sortFieldName, $sortType, $sortDirection) {
        $valueA = array_key_exists($sortFieldName, $a) ? $a[$sortFieldName] : null;
        $valueB = array_key_exists($sortFieldName, $b) ? $b[$sortFieldName] : null;
        
        if ($valueA === $valueB) return 0;
        if ($valueA === null) return $sortDirection === 'asc' ? 1 : -1;
        if ($valueB === null) return $sortDirection === 'asc' ? -1 : 1;
        
        $comparison = ($sortType === 'numeric') ? ($valueA <=> $valueB) : strcmp((string)$valueA, (string)$valueB);
        return $sortDirection === 'asc' ? $comparison : -$comparison;
    });
}

ksort($annualTotals);

$hasTypeBreakdown = false;
foreach ($typeCounts as $count) {
    if ($count > 0) {
        $hasTypeBreakdown = true;
        break;
    }
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalAdvancedCheckout/numinix_admin.css">
    <title><?php echo HEADING_TITLE; ?></title>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="nmx-module">
    <div class="nmx-container">
        <div class="nmx-container-header">
            <h1><?php echo HEADING_TITLE; ?></h1>
        </div>
        
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title"><?php echo TEXT_PANEL_FILTERS; ?></div>
            </div>
            <div class="nmx-panel-body">
                <?php echo zen_draw_form('paypalac_filter_report', FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT, '', 'get', 'class="nmx-form-inline"'); ?>
                    <div class="nmx-form-group">
                        <label for="status-filter"><?php echo TEXT_FILTER_STATUS; ?></label>
                        <select name="status" id="status-filter" class="nmx-form-control">
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_STATUS_ACTIVE; ?></option>
                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_STATUS_SUSPENDED; ?></option>
                </select>
            </div>
            <div class="nmx-form-group">
                <label for="type-filter"><?php echo TEXT_FILTER_TYPE; ?></label>
                <select name="type" id="type-filter" class="nmx-form-control">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_TYPE_ALL; ?></option>
                    <option value="paypal" <?php echo $typeFilter === 'paypal' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_TYPE_PAYPAL; ?></option>
                    <option value="savedcard" <?php echo $typeFilter === 'savedcard' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_TYPE_SAVED_CARD; ?></option>
                    <option value="rest" <?php echo $typeFilter === 'rest' ? 'selected' : ''; ?>><?php echo TEXT_FILTER_TYPE_REST; ?></option>
                </select>
            </div>
            <div class="nmx-form-group">
                <label for="search-filter"><?php echo TEXT_FILTER_SEARCH; ?></label>
                <input type="text" name="search" id="search-filter" value="<?php echo zen_output_string_protected($searchTerm); ?>" class="nmx-form-control">
            </div>
            <div class="nmx-form-actions">
                <button type="submit" class="nmx-btn nmx-btn-primary"><?php echo TEXT_BUTTON_FILTER; ?></button>
                <a href="<?php echo zen_href_link(FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT); ?>" class="nmx-btn nmx-btn-default"><?php echo TEXT_BUTTON_RESET; ?></a>
            </div>
            <?php echo zen_draw_hidden_field('sort', $sortField); ?>
            <?php echo zen_draw_hidden_field('dir', $sortDirection); ?>
        </form>
    </div>
</div>

<div class="nmx-panel">
    <div class="nmx-panel-heading">
        <div class="nmx-panel-title"><?php echo TEXT_PANEL_SUMMARY; ?></div>
    </div>
    <div class="nmx-panel-body">
        <div style="display: flex; flex-wrap: wrap; gap: 3rem;">
            <div>
                <div style="font-weight: 700; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--nmx-dark); margin-bottom: 8px;"><?php echo TEXT_SUBSCRIPTION_COUNT; ?></div>
                <div style="font-size: 2em; color: var(--nmx-primary); font-weight: 700;"><?php echo number_format($totalSubscriptions); ?></div>
            </div>
            <?php if ($totalSubscriptions > 0) { ?>
                <div>
                    <div style="font-weight: 700; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--nmx-dark); margin-bottom: 8px;"><?php echo TEXT_TOTAL_ANNUAL_VALUE; ?></div>
                    <?php if (!empty($annualTotals)) { ?>
                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: none;">
                            <?php foreach ($annualTotals as $currencyCode => $total) { ?>
                                <li style="margin-bottom: 4px;"><strong><?php echo zen_output_string_protected($currencyCode); ?>:</strong> <span style="color: var(--nmx-secondary); font-weight: 600;"><?php echo asr_format_currency($currencies, $total, $currencyCode); ?></span></li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></div>
                    <?php } ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--nmx-dark); margin-bottom: 8px;"><?php echo TEXT_TYPE_BREAKDOWN; ?></div>
                    <?php if ($hasTypeBreakdown) { ?>
                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: none;">
                            <?php foreach ($typeCounts as $typeKey => $count) {
                                if ($count <= 0) continue;
                                $label = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : ucfirst($typeKey);
                                ?>
                                <li style="margin-bottom: 4px;"><?php echo zen_output_string_protected($label); ?>: <span style="color: var(--nmx-secondary); font-weight: 600;"><?php echo number_format($count); ?></span></li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="nmx-panel">
    <div class="nmx-panel-heading">
        <div class="nmx-panel-title"><?php echo TEXT_PANEL_RESULTS; ?></div>
    </div>
    <div class="nmx-panel-body">
        <?php if ($totalSubscriptions === 0) { ?>
            <p><?php echo TEXT_NO_SUBSCRIPTIONS; ?></p>
        <?php } else { ?>
            <div class="nmx-table-responsive">
                <table class="nmx-table nmx-table-striped">
                    <thead>
                        <tr>
                            <th><a href="<?php echo asr_build_sort_link('product', $sortField, $sortDirection); ?>"><?php echo TABLE_HEADING_PRODUCT . asr_sort_indicator('product', $sortField, $sortDirection); ?></a></th>
                            <th><a href="<?php echo asr_build_sort_link('subscriptions', $sortField, $sortDirection); ?>"><?php echo TABLE_HEADING_SUBSCRIPTION_COUNT . asr_sort_indicator('subscriptions', $sortField, $sortDirection); ?></a></th>
                            <th><?php echo TABLE_HEADING_TYPES; ?></th>
                            <th><?php echo TABLE_HEADING_STATUSES; ?></th>
                            <th><?php echo TABLE_HEADING_BILLING_PROFILES; ?></th>
                            <th><a href="<?php echo asr_build_sort_link('next_billing', $sortField, $sortDirection); ?>"><?php echo TABLE_HEADING_NEXT_BILLING . asr_sort_indicator('next_billing', $sortField, $sortDirection); ?></a></th>
                            <th><a href="<?php echo asr_build_sort_link('annual_value', $sortField, $sortDirection); ?>"><?php echo TABLE_HEADING_ANNUAL_VALUE . asr_sort_indicator('annual_value', $sortField, $sortDirection); ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription) { ?>
                            <tr>
                                <td><strong><?php echo zen_output_string_protected($subscription['product']); ?></strong></td>
                                <td><?php echo number_format((int)$subscription['subscription_count']); ?></td>
                                <td>
                                    <?php if (!empty($subscription['type_list'])) { ?>
                                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: disc; font-size: 0.9em;">
                                            <?php foreach ($subscription['type_list'] as $typeItem) { ?>
                                                <li><?php echo zen_output_string_protected($typeItem['label']); ?>: <?php echo number_format((int)$typeItem['count']); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <?php echo TEXT_VALUE_NOT_AVAILABLE; ?>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($subscription['status_list'])) { ?>
                                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: disc; font-size: 0.9em;">
                                            <?php foreach ($subscription['status_list'] as $statusItem) { ?>
                                                <li><?php echo zen_output_string_protected($statusItem['label']); ?>: <?php echo number_format((int)$statusItem['count']); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <?php echo TEXT_VALUE_NOT_AVAILABLE; ?>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($subscription['plan_list'])) { ?>
                                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: disc; font-size: 0.9em;">
                                            <?php foreach ($subscription['plan_list'] as $planItem) { ?>
                                                <li>
                                                    <strong><?php echo $planItem['amount_display']; ?></strong>
                                                    <span style="color: #666;"><?php echo zen_output_string_protected($planItem['billing_display']); ?></span>
                                                    <span style="color: var(--nmx-secondary);">&times; <?php echo number_format((int)$planItem['count']); ?></span>
                                                    <?php if ($planItem['annual_display'] !== null) { ?>
                                                        <span style="color: #5cb85c; font-size: 0.9em;">(<?php echo sprintf(TEXT_PLAN_ANNUAL_VALUE, $planItem['annual_display']); ?>)</span>
                                                    <?php } ?>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <?php echo TEXT_VALUE_NOT_AVAILABLE; ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo zen_output_string_protected($subscription['next_billing_display']); ?></td>
                                <td>
                                    <?php if (!empty($subscription['annual_list'])) { ?>
                                        <ul style="margin: 0; padding: 0 0 0 1.2rem; list-style: disc; font-size: 0.9em;">
                                            <?php foreach ($subscription['annual_list'] as $annualItem) { ?>
                                                <li><strong><?php echo zen_output_string_protected($annualItem['currency']); ?>:</strong> <?php echo $annualItem['display']; ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <?php echo TEXT_VALUE_NOT_AVAILABLE; ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
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
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
