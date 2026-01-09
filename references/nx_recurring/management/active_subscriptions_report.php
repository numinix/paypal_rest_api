<?php
require 'includes/application_top.php';

$languageDirectory = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';
$languageDefines = [];
if (file_exists($languageDirectory . 'lang.active_subscriptions_report.php')) {
    $languageDefines = include $languageDirectory . 'lang.active_subscriptions_report.php';
    if (is_array($languageDefines)) {
        foreach ($languageDefines as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}
if (file_exists($languageDirectory . 'active_subscriptions_report.php')) {
    include $languageDirectory . 'active_subscriptions_report.php';
}

require DIR_WS_CLASSES . 'currencies.php';
$currencies = new currencies();

if (!function_exists('asr_db_escape')) {
    function asr_db_escape($value)
    {
        if (function_exists('zen_db_input')) {
            return zen_db_input($value);
        }

        return addslashes($value);
    }
}

if (!function_exists('asr_table_has_column')) {
    function asr_table_has_column($tableName, $columnName)
    {
        global $db;

        $tableName = trim((string) $tableName);
        $columnName = trim((string) $columnName);

        if ($tableName === '' || $columnName === '' || !is_object($db) || !method_exists($db, 'Execute')) {
            return false;
        }

        $tableLike = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $tableName);
        $tableCheck = $db->Execute(
            "SHOW TABLES LIKE '" . asr_db_escape($tableLike) . "'"
        );

        if (!is_object($tableCheck) || !method_exists($tableCheck, 'RecordCount') || (int) $tableCheck->RecordCount() === 0) {
            return false;
        }

        $columnLike = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $columnName);
        $columnCheck = $db->Execute(
            "SHOW COLUMNS FROM " . $tableName . " LIKE '" . asr_db_escape($columnLike) . "'"
        );

        if (!is_object($columnCheck) || !method_exists($columnCheck, 'RecordCount')) {
            return false;
        }

        return (int) $columnCheck->RecordCount() > 0;
    }
}

if (!function_exists('asr_compute_annual_value')) {
    function asr_compute_annual_value($amount, $period, $frequency)
    {
        $amount = (float) $amount;
        $period = is_string($period) ? strtolower(trim($period)) : '';
        $frequency = (int) $frequency;

        if ($amount <= 0 || $period === '') {
            return null;
        }

        // Normalize common data entry errors where period and frequency appear mismatched
        // If period is "year" but frequency looks like days (365-366), treat as "day" period
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
}

if (!function_exists('asr_format_currency')) {
    function asr_format_currency($currencies, $amount, $currencyCode)
    {
        $currencyCode = ($currencyCode !== null && $currencyCode !== '') ? $currencyCode : (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');

        if (!is_object($currencies) || !method_exists($currencies, 'format')) {
            return number_format((float) $amount, 2) . ' ' . $currencyCode;
        }

        return $currencies->format((float) $amount, true, $currencyCode);
    }
}

if (!function_exists('asr_build_sort_link')) {
    function asr_build_sort_link($field, $currentSort, $currentDir)
    {
        $direction = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = zen_get_all_get_params(['sort', 'dir']);

        return zen_href_link(FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT, $params . 'sort=' . $field . '&dir=' . $direction);
    }
}

if (!function_exists('asr_format_billing_description')) {
    function asr_format_billing_description($frequency, $period)
    {
        $period = trim((string) $period);
        $frequency = (int) $frequency;

        if ($period === '') {
            return TEXT_BILLING_DESCRIPTION_UNKNOWN;
        }

        if ($frequency <= 0) {
            $frequency = 1;
        }

        return sprintf(TEXT_BILLING_DESCRIPTION, $frequency, $period);
    }
}

if (!function_exists('asr_format_date_value')) {
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
}

if (!function_exists('asr_sort_indicator')) {
    function asr_sort_indicator($field, $currentSort, $currentDir)
    {
        if ($field !== $currentSort) {
            return '';
        }

        return $currentDir === 'asc' ? ' ▲' : ' ▼';
    }
}

if (!function_exists('asr_add_subscription_record')) {
    function asr_add_subscription_record(array &$aggregates, array $record)
    {
        $productName = isset($record['product_name']) ? trim((string) $record['product_name']) : '';
        $productId = isset($record['product_id']) ? (int) $record['product_id'] : 0;
        $typeKey = isset($record['type_key']) ? trim((string) $record['type_key']) : '';
        $status = isset($record['status']) ? trim((string) $record['status']) : '';
        $amount = isset($record['amount']) ? (float) $record['amount'] : 0.0;
        $currencyCode = isset($record['currency']) ? trim((string) $record['currency']) : '';
        $billingPeriod = isset($record['billing_period']) ? trim((string) $record['billing_period']) : '';
        $billingFrequency = isset($record['billing_frequency']) ? (int) $record['billing_frequency'] : 0;
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
                'type_counts' => [
                    'paypal' => 0,
                    'savedcard' => 0,
                ],
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

        if ($typeKey !== '') {
            if (!isset($aggregate['type_counts'][$typeKey])) {
                $aggregate['type_counts'][$typeKey] = 0;
            }
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
            $aggregate['annual_totals'][$currencyCode] += (float) $annualValue;
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
            $aggregate['plans'][$planKey]['annual_total'] += (float) $annualValue;
            $aggregate['plans'][$planKey]['has_annual'] = true;
        }

        if ($nextBillingSort !== null && $nextBillingSort !== '' && $nextBillingSort < $aggregate['next_billing_sort']) {
            $aggregate['next_billing_sort'] = $nextBillingSort;
            $aggregate['next_billing_raw'] = $nextBillingRaw;
        }
    }
}

$statusOptions = ['active', 'suspended'];
$statusFilter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'active';
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'active';
}

$statusFiltersMap = [
    'active' => [
        'paypal' => ['Active', 'Scheduled'],
        'savedcard' => ['scheduled'],
    ],
    'suspended' => [
        'paypal' => ['Suspended'],
        'savedcard' => ['suspended'],
    ],
];

$paypalStatusFilter = $statusFiltersMap[$statusFilter]['paypal'];
$savedCardStatusFilter = $statusFiltersMap[$statusFilter]['savedcard'];
$paypalStatusFilterLower = array_map('strtolower', $paypalStatusFilter);
$savedCardStatusFilterLower = array_map('strtolower', $savedCardStatusFilter);

$typeOptions = ['all', 'paypal', 'savedcard'];
$typeFilter = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : 'all';
if (!in_array($typeFilter, $typeOptions, true)) {
    $typeFilter = 'all';
}

$productTypeFilterOptions = [
    ['id' => 'all', 'text' => TEXT_FILTER_PRODUCT_TYPE_ALL],
];
$productTypeIds = [];
if (defined('TABLE_PRODUCT_TYPES') && is_object($db) && method_exists($db, 'Execute')) {
    $productTypeResults = $db->Execute(
        'SELECT type_id, type_name FROM ' . TABLE_PRODUCT_TYPES . ' ORDER BY type_name'
    );

    if ($productTypeResults instanceof queryFactoryResult && $productTypeResults->RecordCount() > 0) {
        while (!$productTypeResults->EOF) {
            $typeId = isset($productTypeResults->fields['type_id']) ? (int) $productTypeResults->fields['type_id'] : 0;
            $typeName = isset($productTypeResults->fields['type_name']) ? trim((string) $productTypeResults->fields['type_name']) : '';

            if ($typeId > 0) {
                $productTypeIds[] = (string) $typeId;
                $productTypeFilterOptions[] = [
                    'id' => (string) $typeId,
                    'text' => $typeName !== '' ? $typeName : sprintf(TEXT_FILTER_PRODUCT_TYPE_FALLBACK, $typeId),
                ];
            }

            $productTypeResults->MoveNext();
        }
    }
}

$productTypeFilter = isset($_GET['product_type']) ? trim((string) $_GET['product_type']) : 'all';
if ($productTypeFilter !== 'all' && !in_array($productTypeFilter, $productTypeIds, true)) {
    $productTypeFilter = 'all';
}
$productTypeFilterValue = $productTypeFilter === 'all' ? null : (int) $productTypeFilter;

$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$searchNeedle = strtolower($searchTerm);

$allowedSorts = [
    'product' => ['field' => 'product_sort', 'type' => 'string'],
    'subscriptions' => ['field' => 'subscription_count', 'type' => 'numeric'],
    'next_billing' => ['field' => 'next_billing_sort', 'type' => 'numeric'],
    'annual_value' => ['field' => 'annual_value_sort', 'type' => 'numeric'],
];
$sortField = isset($_GET['sort']) ? strtolower(trim((string) $_GET['sort'])) : 'subscriptions';
if (!array_key_exists($sortField, $allowedSorts)) {
    $sortField = 'next_billing';
}
$sortDirection = isset($_GET['dir']) ? strtolower(trim((string) $_GET['dir'])) : 'desc';
if ($sortDirection !== 'desc') {
    $sortDirection = 'asc';
}

$languageId = isset($_SESSION['languages_id']) ? (int) $_SESSION['languages_id'] : 0;
if ($languageId <= 0) {
    $languageId = 1;
}

$defaultCurrency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : (isset($_SESSION['currency']) ? $_SESSION['currency'] : 'USD');

$subscriptions = [];
$subscriptionAggregates = [];
$typeCounts = [
    'paypal' => 0,
    'savedcard' => 0,
];
$annualTotals = [];
$totalSubscriptions = 0;

if (defined('TABLE_PAYPAL_RECURRING') && !empty($paypalStatusFilter)) {
    $escapedStatuses = array_map(function ($status) {
        return "'" . asr_db_escape($status) . "'";
    }, $paypalStatusFilter);

    $nextPaymentColumn = 'NULL AS next_payment_date';
    $potentialColumns = ['next_payment_date', 'next_payment_due', 'next_payment_due_date', 'next_billing_date'];

    foreach ($potentialColumns as $candidateColumn) {
        if (asr_table_has_column(TABLE_PAYPAL_RECURRING, $candidateColumn)) {
            if ($candidateColumn === 'next_payment_date') {
                $nextPaymentColumn = 'pr.next_payment_date';
            } else {
                $nextPaymentColumn = 'pr.' . $candidateColumn . ' AS next_payment_date';
            }
            break;
        }
    }

    $selectColumns = [
        'pr.subscription_id',
        'pr.profile_id',
        'pr.customers_id',
        'pr.orders_id',
        'pr.products_id',
        'pr.status',
        'pr.amount',
        'pr.currencycode',
        'pr.billingperiod',
        'pr.billingfrequency',
        $nextPaymentColumn,
        'c.customers_firstname',
        'c.customers_lastname',
        'pd.products_name AS product_name',
        'p.products_type AS product_type_id',
    ];

    $paypalQuery = 'SELECT ' . implode(', ', $selectColumns)
        . " FROM " . TABLE_PAYPAL_RECURRING . " pr"
        . " LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = pr.customers_id"
        . " LEFT JOIN " . TABLE_PRODUCTS . " p ON p.products_id = pr.products_id"
        . " LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = pr.products_id AND pd.language_id = " . (int) $languageId
        . " WHERE pr.status IN (" . implode(',', $escapedStatuses) . ")";

    $paypalResults = $db->Execute($paypalQuery);

    if ($paypalResults instanceof queryFactoryResult && $paypalResults->RecordCount() > 0) {
        while (!$paypalResults->EOF) {
            $row = $paypalResults->fields;

            $productName = isset($row['product_name']) ? trim((string) $row['product_name']) : '';
            if ($productName === '' && isset($row['products_id'])) {
                $productName = 'Product #' . (int) $row['products_id'];
            }

            $status = isset($row['status']) ? trim((string) $row['status']) : '';
            $productTypeId = isset($row['product_type_id']) ? (int) $row['product_type_id'] : 0;
            if ($status === '' || !in_array(strtolower($status), $paypalStatusFilterLower, true)) {
                $paypalResults->MoveNext();
                continue;
            }
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
            $currencyCode = isset($row['currencycode']) && $row['currencycode'] !== '' ? $row['currencycode'] : $defaultCurrency;
            $billingPeriod = isset($row['billingperiod']) ? $row['billingperiod'] : '';
            $billingFrequency = isset($row['billingfrequency']) ? (int) $row['billingfrequency'] : 0;
            $nextBillingRaw = isset($row['next_payment_date']) ? $row['next_payment_date'] : '';
            $nextBillingSort = ($nextBillingRaw !== '' && strtotime($nextBillingRaw) !== false) ? strtotime($nextBillingRaw) : null;

            $annualValue = asr_compute_annual_value($amount, $billingPeriod, $billingFrequency);

            if ($typeFilter !== 'all' && $typeFilter !== 'paypal') {
                $paypalResults->MoveNext();
                continue;
            }

            if ($productTypeFilterValue !== null && $productTypeId !== $productTypeFilterValue) {
                $paypalResults->MoveNext();
                continue;
            }

            $searchHaystack = strtolower($productName . ' ' . $status . ' ' . $currencyCode . ' ' . $billingPeriod . ' ' . $billingFrequency . ' paypal ' . $amount);
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
                'product_id' => isset($row['products_id']) ? (int) $row['products_id'] : 0,
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
                'product_type_id' => $productTypeId,
            ]);

            $paypalResults->MoveNext();
        }
    }
}

if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') && !empty($savedCardStatusFilter)) {
    $savedCardStatuses = array_map(function ($status) {
        return "'" . asr_db_escape($status) . "'";
    }, $savedCardStatusFilter);

    $savedCardQuery = "SELECT sccr.saved_credit_card_recurring_id, sccr.date AS next_payment_date, sccr.amount, sccr.status, sccr.currency_code, sccr.billing_period, sccr.billing_frequency, sccr.products_id, sccr.products_name, sccr.products_model, scc.saved_credit_card_id, scc.customers_id, c.customers_firstname, c.customers_lastname, pd.products_name AS product_name, p.products_type AS product_type_id"
        . " FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr"
        . " LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS . " scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id"
        . " LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id"
        . " LEFT JOIN " . TABLE_PRODUCTS . " p ON p.products_id = sccr.products_id"
        . " LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON pd.products_id = sccr.products_id AND pd.language_id = " . (int) $languageId
        . " WHERE sccr.status IN (" . implode(',', $savedCardStatuses) . ")";

    $savedCardResults = $db->Execute($savedCardQuery);

    if ($savedCardResults instanceof queryFactoryResult && $savedCardResults->RecordCount() > 0) {
        while (!$savedCardResults->EOF) {
            $row = $savedCardResults->fields;

            $productName = isset($row['products_name']) ? trim((string) $row['products_name']) : '';
            if ($productName === '' && isset($row['product_name'])) {
                $productName = trim((string) $row['product_name']);
            }
            if ($productName === '' && isset($row['products_id'])) {
                $productName = 'Product #' . (int) $row['products_id'];
            }

            $productModel = isset($row['products_model']) ? trim((string) $row['products_model']) : '';
            if ($productModel !== '') {
                if ($productName !== '') {
                    $productName = trim($productName . ' (' . $productModel . ')');
                } else {
                    $productName = $productModel;
                }
            }

            $status = isset($row['status']) ? trim((string) $row['status']) : '';
            $productTypeId = isset($row['product_type_id']) ? (int) $row['product_type_id'] : 0;
            if ($status === '' || !in_array(strtolower($status), $savedCardStatusFilterLower, true)) {
                $savedCardResults->MoveNext();
                continue;
            }
            $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
            $currencyCode = isset($row['currency_code']) && $row['currency_code'] !== '' ? $row['currency_code'] : $defaultCurrency;
            $billingPeriod = isset($row['billing_period']) ? $row['billing_period'] : '';
            $billingFrequency = isset($row['billing_frequency']) ? (int) $row['billing_frequency'] : 0;
            $nextBillingRaw = isset($row['next_payment_date']) ? $row['next_payment_date'] : '';
            $nextBillingSort = ($nextBillingRaw !== '' && strtotime($nextBillingRaw) !== false) ? strtotime($nextBillingRaw) : null;

            $annualValue = asr_compute_annual_value($amount, $billingPeriod, $billingFrequency);

            if ($typeFilter !== 'all' && $typeFilter !== 'savedcard') {
                $savedCardResults->MoveNext();
                continue;
            }

            if ($productTypeFilterValue !== null && $productTypeId !== $productTypeFilterValue) {
                $savedCardResults->MoveNext();
                continue;
            }

            $searchHaystack = strtolower($productName . ' ' . $status . ' ' . $currencyCode . ' ' . $billingPeriod . ' ' . $billingFrequency . ' savedcard ' . $amount);
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
                'product_id' => isset($row['products_id']) ? (int) $row['products_id'] : 0,
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
                'product_type_id' => $productTypeId,
            ]);

            $savedCardResults->MoveNext();
        }
    }
}

$typeLabels = [
    'paypal' => TEXT_TYPE_PAYPAL,
    'savedcard' => TEXT_TYPE_SAVED_CARD,
];

foreach ($subscriptionAggregates as $aggregate) {
    $productName = isset($aggregate['product_name']) ? trim((string) $aggregate['product_name']) : '';
    if ($productName === '') {
        $productName = TEXT_VALUE_NOT_AVAILABLE;
    }

    $typeList = [];
    foreach ($aggregate['type_counts'] as $typeKey => $count) {
        if ($count <= 0) {
            continue;
        }

        $label = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : ucfirst($typeKey);
        $typeList[] = [
            'label' => $label,
            'count' => (int) $count,
        ];
    }

    $statusList = [];
    if (!empty($aggregate['status_counts'])) {
        $statusCounts = $aggregate['status_counts'];
        arsort($statusCounts);
        foreach ($statusCounts as $status => $count) {
            $statusList[] = [
                'label' => $status,
                'count' => (int) $count,
            ];
        }
    }

    $planList = [];
    if (!empty($aggregate['plans'])) {
        $plans = $aggregate['plans'];
        uasort($plans, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return 0;
            }

            return ($a['count'] > $b['count']) ? -1 : 1;
        });

        foreach ($plans as $plan) {
            $planList[] = [
                'amount_display' => asr_format_currency($currencies, $plan['amount'], $plan['currency']),
                'billing_display' => asr_format_billing_description($plan['billing_frequency'], $plan['billing_period']),
                'count' => (int) $plan['count'],
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
            if ((float) $total > $annualValueSort) {
                $annualValueSort = (float) $total;
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
        'subscription_count' => isset($aggregate['total_subscriptions']) ? (int) $aggregate['total_subscriptions'] : 0,
        'type_list' => $typeList,
        'status_list' => $statusList,
        'plan_list' => $planList,
        'next_billing_sort' => $nextBillingSort,
        'next_billing_display' => $nextBillingDisplay,
        'annual_list' => $annualList,
        'annual_value_sort' => $annualValueSort,
    ];
}

if (count($subscriptions) > 1) {
    $sortConfig = $allowedSorts[$sortField];
    $sortFieldName = $sortConfig['field'];
    $sortType = $sortConfig['type'];

    usort($subscriptions, function ($a, $b) use ($sortFieldName, $sortType, $sortDirection) {
        $valueA = array_key_exists($sortFieldName, $a) ? $a[$sortFieldName] : null;
        $valueB = array_key_exists($sortFieldName, $b) ? $b[$sortFieldName] : null;

        if ($valueA === $valueB) {
            return 0;
        }

        if ($valueA === null) {
            return $sortDirection === 'asc' ? 1 : -1;
        }
        if ($valueB === null) {
            return $sortDirection === 'asc' ? -1 : 1;
        }

        if ($sortType === 'numeric') {
            $comparison = $valueA <=> $valueB;
        } else {
            $comparison = strcmp((string) $valueA, (string) $valueB);
        }

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
        <title><?php echo HEADING_TITLE; ?></title>
        <link rel="stylesheet" type="text/css" href="includes/css/numinix_admin.css" />
        <link rel="stylesheet" type="text/css" href="includes/css/active_subscriptions_report.css" />
    </head>
    <body>
        <?php require DIR_WS_INCLUDES . 'header.php'; ?>
        <div class="nmx-module" id="active-subscriptions-report">
            <div class="nmx-container">
                <div class="nmx-container-header">
                    <h1><?php echo HEADING_TITLE; ?></h1>
                </div>

                <div class="nmx-row">
                    <div class="nmx-col-xs-12">
                        <div class="nmx-panel">
                            <div class="nmx-panel-heading">
                                <div class="nmx-panel-title"><?php echo TEXT_PANEL_FILTERS; ?></div>
                            </div>
                            <div class="nmx-panel-body">
                                <?php echo zen_draw_form('active_subscriptions_filter', FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT, '', 'get', 'class="nmx-form nmx-form-inline active-subscriptions-filter"'); ?>
                                    <div class="nmx-form-group">
                                        <label for="status-filter"><?php echo TEXT_FILTER_STATUS; ?></label>
                                        <?php
                                        $statusFilterOptions = [
                                            ['id' => 'active', 'text' => TEXT_FILTER_STATUS_ACTIVE],
                                            ['id' => 'suspended', 'text' => TEXT_FILTER_STATUS_SUSPENDED],
                                        ];
                                        echo zen_draw_pull_down_menu('status', $statusFilterOptions, $statusFilter, 'id="status-filter" class="nmx-form-control"');
                                        ?>
                                    </div>
                                    <div class="nmx-form-group">
                                        <label for="type-filter"><?php echo TEXT_FILTER_TYPE; ?></label>
                                        <?php
                                        $typeFilterOptions = [
                                            ['id' => 'all', 'text' => TEXT_FILTER_TYPE_ALL],
                                            ['id' => 'paypal', 'text' => TEXT_FILTER_TYPE_PAYPAL],
                                            ['id' => 'savedcard', 'text' => TEXT_FILTER_TYPE_SAVED_CARD],
                                        ];
                                        echo zen_draw_pull_down_menu('type', $typeFilterOptions, $typeFilter, 'id="type-filter" class="nmx-form-control"');
                                        ?>
                                    </div>
                                    <div class="nmx-form-group">
                                        <label for="product-type-filter"><?php echo TEXT_FILTER_PRODUCT_TYPE; ?></label>
                                        <?php
                                        echo zen_draw_pull_down_menu('product_type', $productTypeFilterOptions, $productTypeFilter, 'id="product-type-filter" class="nmx-form-control"');
                                        ?>
                                    </div>
                                    <div class="nmx-form-group">
                                        <label for="search-filter"><?php echo TEXT_FILTER_SEARCH; ?></label>
                                        <input type="text" name="search" id="search-filter" class="nmx-form-control" value="<?php echo zen_output_string_protected($searchTerm); ?>">
                                    </div>
                                    <div class="nmx-form-actions">
                                        <button type="submit" class="nmx-btn nmx-btn-primary"><?php echo TEXT_BUTTON_FILTER; ?></button>
                                        <a class="nmx-btn nmx-btn-default active-subscriptions-reset" href="<?php echo zen_href_link(FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT); ?>"><?php echo TEXT_BUTTON_RESET; ?></a>
                                    </div>
                                    <?php echo zen_draw_hidden_field('sort', $sortField); ?>
                                    <?php echo zen_draw_hidden_field('dir', $sortDirection); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nmx-row">
                    <div class="nmx-col-xs-12">
                        <div class="nmx-panel">
                            <div class="nmx-panel-heading">
                                <div class="nmx-panel-title"><?php echo TEXT_PANEL_SUMMARY; ?></div>
                            </div>
                            <div class="nmx-panel-body">
                                <div class="active-subscriptions-summary">
                                    <p class="active-subscriptions-stat">
                                        <span class="active-subscriptions-stat-label"><?php echo TEXT_SUBSCRIPTION_COUNT; ?></span>
                                        <span class="active-subscriptions-stat-value"><?php echo number_format($totalSubscriptions); ?></span>
                                    </p>
                                    <?php if ($totalSubscriptions > 0) { ?>
                                        <div class="active-subscriptions-summary-section">
                                            <p class="active-subscriptions-summary-heading"><?php echo TEXT_TOTAL_ANNUAL_VALUE; ?></p>
                                            <?php if (!empty($annualTotals)) { ?>
                                                <p class="active-subscriptions-summary-subheading"><?php echo TEXT_TOTALS_BY_CURRENCY; ?></p>
                                                <ul class="active-subscriptions-summary-list">
                                                    <?php foreach ($annualTotals as $currencyCode => $total) { ?>
                                                        <li><strong><?php echo zen_output_string_protected($currencyCode); ?></strong> <?php echo asr_format_currency($currencies, $total, $currencyCode); ?></li>
                                                    <?php } ?>
                                                </ul>
                                            <?php } else { ?>
                                                <p class="active-subscriptions-summary-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></p>
                                            <?php } ?>
                                        </div>
                                        <div class="active-subscriptions-summary-section">
                                            <p class="active-subscriptions-summary-heading"><?php echo TEXT_TYPE_BREAKDOWN; ?></p>
                                            <?php if ($hasTypeBreakdown) { ?>
                                                <ul class="active-subscriptions-summary-list">
                                                    <?php foreach ($typeCounts as $typeKey => $count) {
                                                        if ($count <= 0) {
                                                            continue;
                                                        }
                                                        $label = isset($typeLabels[$typeKey]) ? $typeLabels[$typeKey] : ucfirst($typeKey);
                                                        ?>
                                                        <li><?php echo zen_output_string_protected($label); ?>: <?php echo number_format($count); ?></li>
                                                    <?php } ?>
                                                </ul>
                                            <?php } else { ?>
                                                <p class="active-subscriptions-summary-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></p>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nmx-row">
                    <div class="nmx-col-xs-12">
                        <div class="nmx-panel">
                            <div class="nmx-panel-heading">
                                <div class="nmx-panel-title"><?php echo TEXT_PANEL_RESULTS; ?></div>
                            </div>
                            <div class="nmx-panel-body active-subscriptions-table">
                                <?php if ($totalSubscriptions === 0) { ?>
                                    <div class="nmx-alert nmx-alert-info"><?php echo TEXT_NO_SUBSCRIPTIONS; ?></div>
                                <?php } else { ?>
                                    <div class="nmx-table-responsive">
                                        <table class="nmx-table nmx-table-bordered nmx-table-striped active-subscriptions-table">
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
                                                        <td><?php echo zen_output_string_protected($subscription['product']); ?></td>
                                                        <td><?php echo number_format((int) $subscription['subscription_count']); ?></td>
                                                        <td>
                                                            <?php if (!empty($subscription['type_list'])) { ?>
                                                                <ul class="active-subscriptions-table-list">
                                                                    <?php foreach ($subscription['type_list'] as $typeItem) { ?>
                                                                        <li><?php echo zen_output_string_protected($typeItem['label']); ?>: <?php echo number_format((int) $typeItem['count']); ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            <?php } else { ?>
                                                                <span class="active-subscriptions-table-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></span>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($subscription['status_list'])) { ?>
                                                                <ul class="active-subscriptions-table-list">
                                                                    <?php foreach ($subscription['status_list'] as $statusItem) { ?>
                                                                        <li><?php echo zen_output_string_protected($statusItem['label']); ?>: <?php echo number_format((int) $statusItem['count']); ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            <?php } else { ?>
                                                                <span class="active-subscriptions-table-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></span>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($subscription['plan_list'])) { ?>
                                                                <ul class="active-subscriptions-table-list">
                                                                    <?php foreach ($subscription['plan_list'] as $planItem) { ?>
                                                                        <li>
                                                                            <span class="active-subscriptions-table-plan-amount"><?php echo $planItem['amount_display']; ?></span>
                                                                            <span class="active-subscriptions-table-plan-billing"><?php echo zen_output_string_protected($planItem['billing_display']); ?></span>
                                                                            <span class="active-subscriptions-table-plan-count">&times; <?php echo number_format((int) $planItem['count']); ?></span>
                                                                            <?php if ($planItem['annual_display'] !== null) { ?>
                                                                                <span class="active-subscriptions-table-plan-annual"><?php echo sprintf(TEXT_PLAN_ANNUAL_VALUE, $planItem['annual_display']); ?></span>
                                                                            <?php } ?>
                                                                        </li>
                                                                    <?php } ?>
                                                                </ul>
                                                            <?php } else { ?>
                                                                <span class="active-subscriptions-table-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></span>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?php echo zen_output_string_protected($subscription['next_billing_display']); ?></td>
                                                        <td>
                                                            <?php if (!empty($subscription['annual_list'])) { ?>
                                                                <ul class="active-subscriptions-table-list">
                                                                    <?php foreach ($subscription['annual_list'] as $annualItem) { ?>
                                                                        <li><strong><?php echo zen_output_string_protected($annualItem['currency']); ?></strong> <?php echo $annualItem['display']; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            <?php } else { ?>
                                                                <span class="active-subscriptions-table-empty"><?php echo TEXT_VALUE_NOT_AVAILABLE; ?></span>
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
                    </div>
                </div>
            </div>
        </div>
        <?php require DIR_WS_INCLUDES . 'footer.php'; ?>
        <script src="includes/javascript/active_subscriptions_report.js"></script>
    </body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
