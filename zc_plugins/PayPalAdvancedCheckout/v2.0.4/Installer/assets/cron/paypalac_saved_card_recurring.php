<?php
require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
\PayPalAdvancedCheckout\Common\LegacySubscriptionMigrator::syncLegacySubscriptions();
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalacSavedCardRecurring.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'zammad_client.php';

// Load checkout-process language definitions using the active language
$language = basename($_SESSION['language'] ?? (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'english'));
$langDir = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/';
$langFile = $langDir . 'checkout_process.php';
if (!file_exists($langFile)) {
    $langFile = $langDir . 'lang.checkout_process.php';
}
if (file_exists($langFile)) {
    require_once $langFile;
}

// Define email constants if not already defined
if (!defined('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL')) {
    define('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL', 
        'Dear %s,' . "\n\n" .
        'We were unable to process your recurring payment for %s.' . "\n\n" .
        'Card ending in: %s' . "\n\n" .
        'After multiple attempts, we could not complete the transaction. Please update your payment method to continue your subscription for %s.' . "\n\n" .
        'Thank you for your business.'
    );
}

if (!defined('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT')) {
    define('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT', 'Recurring Payment Failed - Action Required');
}

if (!defined('SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL')) {
    define('SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL',
        'Dear %s,' . "\n\n" .
        'We encountered an issue processing your recurring payment for %s.' . "\n\n" .
        'Card ending in: %s' . "\n\n" .
        'We will automatically retry the payment. If the issue persists, please update your payment method for %s.' . "\n\n" .
        'Thank you for your patience.'
    );
}

if (!defined('SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT')) {
    define('SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT', 'Recurring Payment Issue - Will Retry');
}

$_SESSION['in_cron'] = true; //setting to ensure that some functions that should onlt happen for new orders don't happen during cron.

if (!function_exists('recurring_esc_html')) {
    function recurring_esc_html($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('recurring_is_cli')) {
    /**
     * Detect if script is running in CLI or web context
     * @return bool True if running in CLI, false if running in web context
     */
    function recurring_is_cli() {
        return php_sapi_name() === 'cli' || defined('STDIN');
    }
}

if (!function_exists('recurring_format_output')) {
    /**
     * Format text output appropriately for CLI or web context
     * In CLI: returns text as-is with newlines
     * In web: wraps text in <pre> tags for proper line/spacing display
     * @param string $text Text with newline characters
     * @return string Formatted text appropriate for the output context
     */
    function recurring_format_output($text) {
        if (recurring_is_cli()) {
            return $text;
        }
        // In web context, wrap in <pre> tags to preserve formatting
        return '<pre style="font-family: monospace; white-space: pre-wrap; word-wrap: break-word; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; line-height: 1.4;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
}

if (!function_exists('recurring_format_link')) {
    function recurring_format_link($label, $url) {
        $label = recurring_esc_html($label);
        $trimmed = trim((string)$url);

        if ($trimmed !== '' && strtolower($trimmed) !== 'n/a') {
            $escaped_url = recurring_esc_html($trimmed);
            return '<p style="margin: 0 0 4px;">' . $label . ': <a href="' . $escaped_url . '">' . $escaped_url . '</a></p>';
        }

        $display_value = $trimmed === '' ? 'N/A' : $trimmed;

        return '<p style="margin: 0 0 4px;">' . $label . ': ' . recurring_esc_html($display_value) . '</p>';
    }
}

if (!function_exists('recurring_customer_portal_url')) {
    function recurring_customer_portal_url() {
        $base = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');
        $catalog = defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/';
        return rtrim($base, '/') . $catalog . 'index.php?main_page=my_subscriptions';
    }
}

if (!function_exists('recurring_add_card_url')) {
    function recurring_add_card_url() {
        $base = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');
        $catalog = defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/';
        return rtrim($base, '/') . $catalog . 'index.php?main_page=account_saved_credit_cards';
    }
}

if (!function_exists('recurring_build_skip_result')) {
    function recurring_build_skip_result($payment_id, array $payment_details, array $card_status) {
        return array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            'skip_reason' => $card_status['skip_reason'] !== '' ? $card_status['skip_reason'] : 'no valid card',
            'card_brand' => $card_status['card_brand'],
            'card_last4_display' => $card_status['card_last4'],
            'customer_portal_url' => recurring_customer_portal_url(),
            'add_card_url' => recurring_add_card_url(),
        );
    }
}

if (!function_exists('recurring_build_email_text')) {
    function recurring_build_email_text(
        $run_date,
        $timezone,
        $report_id,
        $generated_at,
        $total_processed,
        $success_count,
        $fail_count,
        $skipped_count,
        $total_collected,
        $currency,
        array $results,
        array $sections
    ) {
        $lines = array();
        $lines[] = "Recurring Payments — $run_date ($timezone)";
        $lines[] = "Processed: $total_processed";
        $lines[] = "";
        $lines[] = "Paid: $success_count";
        $lines[] = "";
        $lines[] = "Failed: $fail_count";
        $lines[] = "";
        $lines[] = "Skipped: $skipped_count";
        $lines[] = "";
        $invoiced_count_line = (!empty($sections['invoiced']) && isset($results['invoiced']) && is_array($results['invoiced']))
            ? count($results['invoiced'])
            : 0;
        $lines[] = "Invoiced (offline): $invoiced_count_line";
        $lines[] = "";
        $lines[] = "Collected: {$currency}" . number_format($total_collected, 2);
        $lines[] = "";
        $lines[] = "Report ID: $report_id";
        $lines[] = "";
        $lines[] = "Generated: $generated_at";
        $lines[] = "";

        if (!empty($sections['success'])) {
            $lines[] = "=== Successful ($success_count) ===";
            foreach ($results['success'] as $row) {
                $lines[] = "- Subscr {$row['subscription_id']} | {$row['customer_name']} | {$row['product_name']} | {$currency}{$row['amount']}";
                $lines[] = "  Card: {$row['card_brand']} •••• {$row['card_last4']} exp {$row['exp_month']}/{$row['exp_year']}";
                $lines[] = "  Txn {$row['txn_id']} • Invoice {$row['invoice_number']} • Next {$row['next_charge_date']}";
                $lines[] = "  Link: {$row['subscription_url']}";
                $lines[] = "";
            }
        }

        if (!empty($sections['failed'])) {
            $lines[] = "=== Failed (retry scheduled) ($fail_count) ===";
            foreach ($results['failed'] as $row) {
                $lines[] = "- Subscr {$row['subscription_id']} | {$row['customer_name']} | {$row['product_name']} | {$currency}{$row['amount']}";
                $lines[] = "  Reason: {$row['failure_reason']} ({$row['gateway_code']})";
                $lines[] = "  Next retry: {$row['next_retry_date']} (attempt {$row['attempt_number']}/{$row['max_attempts']}) • Notified: {$row['customer_notified']}";
                $lines[] = "  Link: {$row['subscription_url']}";
                $lines[] = "";
            }
        }

        if (!empty($sections['skipped'])) {
            $lines[] = "=== Skipped (action required) ($skipped_count) ===";
            foreach ($results['skipped'] as $row) {
                $lines[] = "- Subscr {$row['subscription_id']} | {$row['customer_name']} | {$row['product_name']}";
                $lines[] = "  Reason: {$row['skip_reason']} | On-file: {$row['card_brand']} {$row['card_last4_display']}";
                $lines[] = "  Portal: {$row['customer_portal_url']} | Add card: {$row['add_card_url']}";
                $lines[] = "";
            }
        }

        if (!empty($sections['invoiced']) && !empty($results['invoiced'])) {
            $invoiced_count = count($results['invoiced']);
            $lines[] = "=== Invoiced offline ($invoiced_count) ===";
            foreach ($results['invoiced'] as $row) {
                $lines[] = "- Subscr {$row['subscription_id']} | {$row['customer_name']} | {$row['product_name']} | {$currency}{$row['amount']}";
                $lines[] = "  Invoice {$row['invoice_number']} • Next {$row['next_charge_date']}";
                $lines[] = "";
            }
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('recurring_build_email_html')) {
    function recurring_build_email_html(
        $run_date,
        $timezone,
        $report_id,
        $generated_at,
        $total_processed,
        $success_count,
        $fail_count,
        $skipped_count,
        $total_collected,
        $currency,
        array $results,
        array $sections
    ) {
        $html_segments = array();
        $html_segments[] = '<h1 style="margin: 0 0 16px; font-size: 24px; color: #0f172a;">Recurring Payments &mdash; ' . recurring_esc_html($run_date) . ' (' . recurring_esc_html($timezone) . ')</h1>';
        $html_segments[] = '<div style="margin: 0 0 20px; padding: 12px; background-color: #f1f5f9; border-radius: 6px; font-size: 14px; color: #1f2933;">';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Processed:</strong> ' . recurring_esc_html($total_processed) . '</p>';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Paid:</strong> ' . recurring_esc_html($success_count) . '</p>';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Failed:</strong> ' . recurring_esc_html($fail_count) . '</p>';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Skipped:</strong> ' . recurring_esc_html($skipped_count) . '</p>';
        $invoiced_count_line = (!empty($sections['invoiced']) && isset($results['invoiced']) && is_array($results['invoiced']))
            ? count($results['invoiced'])
            : 0;
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Invoiced (offline):</strong> ' . recurring_esc_html($invoiced_count_line) . '</p>';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Collected:</strong> ' . recurring_esc_html($currency) . recurring_esc_html(number_format($total_collected, 2)) . '</p>';
        $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Report ID:</strong> ' . recurring_esc_html($report_id) . '</p>';
        $html_segments[] = '<p style="margin: 0;"><strong>Generated:</strong> ' . recurring_esc_html($generated_at) . '</p>';
        $html_segments[] = '</div>';

        if (!empty($sections['success']) && $success_count > 0) {
            $html_segments[] = '<h2 style="margin: 0 0 8px; font-size: 18px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Successful (' . recurring_esc_html($success_count) . ')</h2>';
            foreach ($results['success'] as $row) {
                $html_segments[] = '<div style="margin: 0 0 16px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px;">';
                $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Subscr ' . recurring_esc_html($row['subscription_id']) . '</strong> | ' . recurring_esc_html($row['customer_name']) . ' | ' . recurring_esc_html($row['product_name']) . ' | ' . recurring_esc_html($currency) . recurring_esc_html($row['amount']) . '</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Card: ' . recurring_esc_html($row['card_brand']) . ' •••• ' . recurring_esc_html($row['card_last4']) . ' exp ' . recurring_esc_html($row['exp_month']) . '/' . recurring_esc_html($row['exp_year']) . '</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Txn ' . recurring_esc_html($row['txn_id']) . ' • Invoice ' . recurring_esc_html($row['invoice_number']) . ' • Next ' . recurring_esc_html($row['next_charge_date']) . '</p>';
                $html_segments[] = recurring_format_link('Link', $row['subscription_url']);
                $html_segments[] = '</div>';
            }
        }

        if (!empty($sections['failed']) && $fail_count > 0) {
            $html_segments[] = '<h2 style="margin: 24px 0 8px; font-size: 18px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Failed (retry scheduled) (' . recurring_esc_html($fail_count) . ')</h2>';
            foreach ($results['failed'] as $row) {
                $html_segments[] = '<div style="margin: 0 0 16px; padding: 12px; border: 1px solid #fca5a5; border-radius: 6px; background-color: #fef2f2;">';
                $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Subscr ' . recurring_esc_html($row['subscription_id']) . '</strong> | ' . recurring_esc_html($row['customer_name']) . ' | ' . recurring_esc_html($row['product_name']) . ' | ' . recurring_esc_html($currency) . recurring_esc_html($row['amount']) . '</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Reason: ' . recurring_esc_html($row['failure_reason']) . ' (' . recurring_esc_html($row['gateway_code']) . ')</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Next retry: ' . recurring_esc_html($row['next_retry_date']) . ' (attempt ' . recurring_esc_html($row['attempt_number']) . '/' . recurring_esc_html($row['max_attempts']) . ') • Notified: ' . recurring_esc_html($row['customer_notified']) . '</p>';
                $html_segments[] = recurring_format_link('Link', $row['subscription_url']);
                $html_segments[] = '</div>';
            }
        }

        if (!empty($sections['skipped']) && $skipped_count > 0) {
            $html_segments[] = '<h2 style="margin: 24px 0 8px; font-size: 18px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Skipped (action required) (' . recurring_esc_html($skipped_count) . ')</h2>';
            foreach ($results['skipped'] as $row) {
                $html_segments[] = '<div style="margin: 0 0 16px; padding: 12px; border: 1px solid #fcd34d; border-radius: 6px; background-color: #fffbeb;">';
                $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Subscr ' . recurring_esc_html($row['subscription_id']) . '</strong> | ' . recurring_esc_html($row['customer_name']) . ' | ' . recurring_esc_html($row['product_name']) . '</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Reason: ' . recurring_esc_html($row['skip_reason']) . ' | On-file: ' . recurring_esc_html($row['card_brand']) . ' ' . recurring_esc_html($row['card_last4_display']) . '</p>';
                $html_segments[] = recurring_format_link('Portal', $row['customer_portal_url']);
                $html_segments[] = recurring_format_link('Add card', $row['add_card_url']);
                $html_segments[] = '</div>';
            }
        }

        if (!empty($sections['invoiced']) && !empty($results['invoiced'])) {
            $invoiced_count = count($results['invoiced']);
            $html_segments[] = '<h2 style="margin: 24px 0 8px; font-size: 18px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">Invoiced offline (' . recurring_esc_html($invoiced_count) . ')</h2>';
            foreach ($results['invoiced'] as $row) {
                $html_segments[] = '<div style="margin: 0 0 16px; padding: 12px; border: 1px solid #93c5fd; border-radius: 6px; background-color: #eff6ff;">';
                $html_segments[] = '<p style="margin: 0 0 4px;"><strong>Subscr ' . recurring_esc_html($row['subscription_id']) . '</strong> | ' . recurring_esc_html($row['customer_name']) . ' | ' . recurring_esc_html($row['product_name']) . ' | ' . recurring_esc_html($currency) . recurring_esc_html($row['amount']) . '</p>';
                $html_segments[] = '<p style="margin: 0 0 4px;">Invoice ' . recurring_esc_html($row['invoice_number']) . ' • Next ' . recurring_esc_html($row['next_charge_date']) . '</p>';
                $html_segments[] = '</div>';
            }
        }

        return implode("\n", $html_segments);
    }
}

$paypalacSavedCardRecurring = new paypalacSavedCardRecurring();

$buildSubscriptionMetadata = function ($details, $amount) {
    $metadata = array(
        'products_id' => isset($details['products_id']) ? $details['products_id'] : null,
        'products_name' => isset($details['products_name']) ? $details['products_name'] : '',
        'products_model' => isset($details['products_model']) ? $details['products_model'] : '',
        'currency_code' => isset($details['currency_code']) && $details['currency_code'] !== '' ? $details['currency_code'] : (isset($details['order_currency_code']) ? $details['order_currency_code'] : ''),
        'billing_period' => isset($details['billing_period']) ? $details['billing_period'] : null,
        'billing_frequency' => isset($details['billing_frequency']) ? $details['billing_frequency'] : null,
        'total_billing_cycles' => isset($details['total_billing_cycles']) ? $details['total_billing_cycles'] : null,
        'domain' => isset($details['domain']) ? $details['domain'] : '',
        'subscription_attributes' => array(),
    );

    if (isset($details['subscription_attributes']) && is_array($details['subscription_attributes'])) {
        $metadata['subscription_attributes'] = $details['subscription_attributes'];
    } elseif (isset($details['subscription_attributes_json']) && $details['subscription_attributes_json'] !== '') {
        $decoded = json_decode($details['subscription_attributes_json'], true);
        if (is_array($decoded)) {
            $metadata['subscription_attributes'] = $decoded;
        }
    }

    if (!isset($metadata['subscription_attributes']['billingperiod']) && $metadata['billing_period'] !== null) {
        $metadata['subscription_attributes']['billingperiod'] = $metadata['billing_period'];
    }
    if (!isset($metadata['subscription_attributes']['billingfrequency']) && $metadata['billing_frequency'] !== null) {
        $metadata['subscription_attributes']['billingfrequency'] = $metadata['billing_frequency'];
    }
    if (!isset($metadata['subscription_attributes']['totalbillingcycles']) && $metadata['total_billing_cycles'] !== null) {
        $metadata['subscription_attributes']['totalbillingcycles'] = $metadata['total_billing_cycles'];
    }
    if (!isset($metadata['subscription_attributes']['domain']) && $metadata['domain'] !== '') {
        $metadata['subscription_attributes']['domain'] = $metadata['domain'];
    }
    if (!isset($metadata['subscription_attributes']['currencycode']) && $metadata['currency_code'] !== '') {
        $metadata['subscription_attributes']['currencycode'] = $metadata['currency_code'];
    }

    $metadata['subscription_attributes']['amount'] = $amount;

    return $metadata;
};

$extractSubscriptionAttributes = function ($details) use ($paypalacSavedCardRecurring) {
    $attributes = array();

    if (isset($details['subscription_attributes']) && is_array($details['subscription_attributes'])) {
        $attributes = $details['subscription_attributes'];
    } elseif (isset($details['subscription_attributes_json']) && $details['subscription_attributes_json'] !== '') {
        $decoded = json_decode($details['subscription_attributes_json'], true);
        if (is_array($decoded)) {
            $attributes = $decoded;
        }
    }

    if (!isset($attributes['billingperiod']) && isset($details['billing_period']) && $details['billing_period'] !== null) {
        $attributes['billingperiod'] = $details['billing_period'];
    }
    if (!isset($attributes['billingfrequency']) && isset($details['billing_frequency']) && $details['billing_frequency'] !== null) {
        $attributes['billingfrequency'] = $details['billing_frequency'];
    }
    if (!isset($attributes['totalbillingcycles']) && isset($details['total_billing_cycles']) && $details['total_billing_cycles'] !== null) {
        $attributes['totalbillingcycles'] = $details['total_billing_cycles'];
    }
    if (!isset($attributes['domain']) && isset($details['domain']) && $details['domain'] !== '') {
        $attributes['domain'] = $details['domain'];
    }

    $sourceOrdersProductsId = null;
    if (isset($details['original_orders_products_id']) && (int) $details['original_orders_products_id'] > 0) {
        $sourceOrdersProductsId = (int) $details['original_orders_products_id'];
    } elseif (isset($details['orders_products_id']) && (int) $details['orders_products_id'] > 0) {
        $sourceOrdersProductsId = (int) $details['orders_products_id'];
    }

    if ((!isset($attributes['billingperiod']) || !isset($attributes['billingfrequency'])) && $sourceOrdersProductsId !== null) {
        $fallback = $paypalacSavedCardRecurring->get_attributes($sourceOrdersProductsId);
        if (is_array($fallback)) {
            $attributes = array_merge($fallback, $attributes);
        }
    }

    if (isset($attributes['billingfrequency']) && is_numeric($attributes['billingfrequency'])) {
        $attributes['billingfrequency'] = (int) $attributes['billingfrequency'];
    }

    return $attributes;
};

$normalizePaymentDetails = function ($paymentId, $details) use ($paypalacSavedCardRecurring) {
    if (!is_array($details)) {
        return $details;
    }

    $normalized = $paypalacSavedCardRecurring->migrate_legacy_subscription_context($paymentId, $details);
    if (!isset($normalized['original_orders_products_id'])) {
        $normalized['original_orders_products_id'] = 0;
    }

    return $normalized;
};

$parseRecurringDate = function ($value) {
    if ($value instanceof DateTime) {
        return clone $value;
    }

    if (is_string($value) && trim($value) !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', trim($value));
        if ($parsed instanceof DateTime) {
            $parsed->setTime(0, 0, 0);
            return $parsed;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $date = new DateTime('today');
            $date->setTimestamp($timestamp);
            $date->setTime(0, 0, 0);
            return $date;
        }
    }

    return new DateTime('today');
};

$advanceBillingCycle = function (DateTime $baseDate, array $attributes) {
    $period = isset($attributes['billingperiod']) ? trim((string) $attributes['billingperiod']) : '';
    $frequency = isset($attributes['billingfrequency']) ? (int) $attributes['billingfrequency'] : 0;

    if ($period === '' || $frequency <= 0) {
        return null;
    }

    $normalizedPeriod = strtolower($period);
    $next = clone $baseDate;

    try {
        switch ($normalizedPeriod) {
            case 'day':
            case 'daily':
                $next->add(new DateInterval('P' . $frequency . 'D'));
                break;
            case 'week':
            case 'weekly':
                $next->add(new DateInterval('P' . $frequency . 'W'));
                break;
            case 'semimonth':
            case 'semi-month':
            case 'semi monthly':
            case 'semi-monthly':
            case 'bi-weekly':
            case 'bi weekly':
                $days = max(1, $frequency * 15);
                $next->add(new DateInterval('P' . $days . 'D'));
                break;
            case 'month':
            case 'monthly':
                $next->add(new DateInterval('P' . $frequency . 'M'));
                break;
            case 'year':
            case 'yearly':
                $next->add(new DateInterval('P' . $frequency . 'Y'));
                break;
            default:
                $next->modify('+' . $frequency . ' ' . $period);
                break;
        }
    } catch (Exception $e) {
        return null;
    }

    return $next;
};

// Debug: Show only due paypalac* subscriptions handled by this cron.
// Join through orders_products_id → orders_products → orders to get the
// payment_module_code.  The orders_id column on the recurring table is not
// reliably populated (it defaults to 0), so we must traverse the FK chain.
$debug_sql = 'SELECT sccr.saved_credit_card_recurring_id, sccr.status, sccr.next_payment_date, sccr.products_name'
    . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
    . ' INNER JOIN ' . TABLE_ORDERS_PRODUCTS . ' op ON op.orders_products_id = sccr.orders_products_id'
    . ' INNER JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = op.orders_id'
    . " WHERE LOWER(TRIM(sccr.status)) = 'scheduled'"
    . " AND sccr.next_payment_date IS NOT NULL"
    . " AND sccr.next_payment_date <> '0000-00-00'"
    . " AND DATE(sccr.next_payment_date) <= '" . date('Y-m-d') . "'"
    . " AND o.payment_module_code LIKE 'paypalac%'"
    . ' ORDER BY sccr.saved_credit_card_recurring_id';
$debug_result = $db->Execute($debug_sql);
$debug_output = "
=== DEBUG: Due REST Subscriptions in Database ===
";
while (!$debug_result->EOF) {
    $debug_output .= sprintf("ID: %d | Status: %s | Next Payment: %s | Product: %s
", 
        $debug_result->fields['saved_credit_card_recurring_id'],
        $debug_result->fields['status'],
        $debug_result->fields['next_payment_date'],
        $debug_result->fields['products_name']
    );
    error_log('PayPal Cron - Due subscription: ' . sprintf('ID: %d, Status: %s, Next Payment: %s, Product: %s', 
        $debug_result->fields['saved_credit_card_recurring_id'],
        $debug_result->fields['status'],
        $debug_result->fields['next_payment_date'],
        $debug_result->fields['products_name']
    ));
    $debug_result->MoveNext();
}
$debug_output .= "=== END DEBUG ===

";
print recurring_format_output($debug_output);

$todays_payments = $paypalacSavedCardRecurring->get_scheduled_payments();

if (count($todays_payments) == 0) {
    $no_payments_msg = "No payments due for processing today (" . date('Y-m-d') . ") or earlier.\n";
    $no_payments_msg .= "Subscriptions must have:\n";
    $no_payments_msg .= "  - status = 'scheduled'\n";
    $no_payments_msg .= "  - next_payment_date <= '" . date('Y-m-d') . "'\n";
    $no_payments_msg .= "Note: Subscriptions in 'failed', 'complete', 'cancelled', or 'paused' status are NOT processed.\n";
    print recurring_format_output($no_payments_msg);
    error_log('PayPal Cron - ' . $no_payments_msg);
}

$results = array('success' => array(), 'failed' => array(), 'skipped' => array(), 'invoiced' => array());
$total_collected = 0.0;
$currency = '$';
$log = '';

foreach ($todays_payments as $payment_id) {
    $order = $zf_insert_id = null; //ensure order is reset.

    $payment_details = $normalizePaymentDetails($payment_id, $paypalacSavedCardRecurring->get_payment_details($payment_id));

    // Offline invoice renewals are handled by cron/offline_invoice_recurring.php
    // (not this PayPal AC card cron).

    // -------------------------------------------------------------------
    // If the saved card attached to this payment has expired (or was marked
    // deleted), attempt to swap in another non-expired card.  When no valid
    // card exists for the customer, skip processing this subscription.
    // -------------------------------------------------------------------
    $card_status = $paypalacSavedCardRecurring->get_saved_card_billing_status(
        $payment_details['saved_credit_card_id'],
        $payment_details['customers_id']
    );
    $card_details = $paypalacSavedCardRecurring->get_saved_card_details($payment_details['saved_credit_card_id']);

    if (!$card_status['billable']) {
        $replacement = $paypalacSavedCardRecurring->get_customers_saved_card($payment_details['customers_id']);

        if ($replacement) {
            // Update to use the new card and reload details
            $paypalacSavedCardRecurring->update_payment_info($payment_id, array('saved_credit_card_id' => $replacement));
            $payment_details = $normalizePaymentDetails($payment_id, $paypalacSavedCardRecurring->get_payment_details($payment_id));
            $log .= "\n Updated card for subscription $payment_id | {$payment_details['products_name']}";
        } else {
            $results['skipped'][] = recurring_build_skip_result($payment_id, $payment_details, $card_status);
            $log .= "\n Skipped subscription $payment_id | {$payment_details['products_name']} | " . $card_status['skip_reason'];
            continue; // nothing to bill today
        }
    }

    list($order, $order_totals) = $paypalacSavedCardRecurring->prepare_order($payment_details, $payment_details['products_id'], $payment_details['original_orders_products_id']);

    //update price to account for tax and price changes
    $recurring_amount = 0;
    $total_to_bill = 0;
    foreach ($order_totals as $ot) {
        if ($ot['code'] == 'ot_subtotal') { //new cost of product
            $recurring_amount = number_format($ot['value'], 2);
            $recurring_amount = preg_replace("/[^0-9\.]/", "", $recurring_amount); //remove any illegal chars from the amount so it stores properly.
        }
        if ($ot['code'] == 'ot_total') { //accounting for tax, sc, etc.
            $total_to_bill = number_format($ot['value'], 2);
            $total_to_bill = preg_replace("/[^0-9\.]/", "", $total_to_bill); //remove any illegal chars from the amount so it stores properly.
        }
    }

    // Apply subscription coupon discount if one is active for this subscription.
    // Zen Cart's normal coupon restrictions (uses_per_coupon, uses_per_user, etc.)
    // are intentionally bypassed here – only the billing-cycle limit applies.
    $subscription_coupon_data = $paypalacSavedCardRecurring->get_subscription_coupon($payment_id);
    $subscription_coupon_discount = 0.0;
    if ($subscription_coupon_data !== null && (float) $recurring_amount > 0) {
        $subscription_coupon_discount = $paypalacSavedCardRecurring->calculate_subscription_coupon_discount(
            $subscription_coupon_data,
            (float) $recurring_amount
        );
        if ($subscription_coupon_discount > 0) {
            // Insert a discount line into the order totals array and reduce ot_total
            $coupon_sort_order = 5; // display after subtotal
            $order_totals[] = [
                'code'       => 'ot_coupon',
                'title'      => 'Subscription Discount:',
                'text'       => '-' . number_format($subscription_coupon_discount, 2),
                'value'      => -$subscription_coupon_discount,
                'sort_order' => $coupon_sort_order,
            ];
            foreach ($order_totals as &$ot_row) {
                if ($ot_row['code'] === 'ot_total') {
                    $ot_row['value'] -= $subscription_coupon_discount;
                    $ot_row['text']   = number_format($ot_row['value'], 2);
                }
            }
            unset($ot_row);
            $discounted_total = max(0, (float) $total_to_bill - $subscription_coupon_discount);
            $total_to_bill = number_format($discounted_total, 2);
            $log .= ' [subscription coupon #' . $subscription_coupon_data['coupon_id'] . ' applied: -$' . number_format($subscription_coupon_discount, 2) . '] ';
        }
    }

    //if pricing has changed, update the recurring orders table.
    if ($recurring_amount != $payment_details['amount']) {
        $paypalacSavedCardRecurring->update_payment_info($payment_id, array('amount' => $recurring_amount, 'comments' => '  Price automatically updated. '));
        $payment_details = $normalizePaymentDetails($payment_id, $paypalacSavedCardRecurring->get_payment_details($payment_id));
    }

    // Read any overdue balance accumulated from previous failed billing cycles.
    $overdue_balance = $paypalacSavedCardRecurring->get_overdue_balance($payment_id);
    $consecutive_failures = $paypalacSavedCardRecurring->get_consecutive_failures($payment_id);

    // Determine if this is a daily retry of a previously-failed billing cycle.
    // When a billing cycle fails we store the actual next scheduled cycle date in
    // subscription_attributes_json under 'overdue_daily_retry_next_cycle' and set
    // next_payment_date to tomorrow so the cron picks the subscription up each day.
    // While today is still before that stored date this is a daily retry: we only
    // charge the accumulated overdue balance (no new regular-cycle amount added).
    $isOverdueRetry      = false;
    $nextCycleDateFromAttr = null;
    $currentAttrs = isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes'])
        ? $payment_details['subscription_attributes']
        : array();
    if (!empty($currentAttrs['overdue_daily_retry_next_cycle'])) {
        $storedNextCycle = $currentAttrs['overdue_daily_retry_next_cycle'];
        if ($storedNextCycle > date('Y-m-d')) {
            $isOverdueRetry        = true;
            $nextCycleDateFromAttr = $storedNextCycle;
        }
    }

    // Store the regular cycle amount before adding overdue.
    // For daily retries the regular amount is 0 — the missed amount is already
    // captured in overdue_balance and must not be double-counted.
    $regular_cycle_amount = $isOverdueRetry ? 0.0 : (float) $total_to_bill;

    // Build the charge amount.
    if ($overdue_balance > 0) {
        if ($isOverdueRetry) {
            // Daily retry: charge only the outstanding overdue balance.
            $total_to_bill = number_format($overdue_balance, 2, '.', '');
            $log .= ' [daily overdue retry: $' . number_format($overdue_balance, 2) . '] ';
        } else {
            // Billing cycle: combine this cycle's regular amount with any overdue.
            $total_to_bill = number_format((float) $total_to_bill + $overdue_balance, 2, '.', '');
            $log .= ' [overdue balance of $' . number_format($overdue_balance, 2) . ' added to charge] ';
        }
    } elseif ($isOverdueRetry && $paypalacSavedCardRecurring->has_overdue_balance_column()) {
        // Daily retry and the overdue balance is confirmed zero (admin forgave the
        // debt). Only treat it as zero when the column actually exists; if the
        // 1_9_0 migration has not yet run, get_overdue_balance() returns 0 even
        // though the real balance is unknown, so we must not skip the charge.
        //
        // Do NOT fall through to the payment / success path — no order should be
        // created and subscription state must not be advanced as if a payment was
        // collected.  Instead, clear the retry marker, restore next_payment_date to
        // the actual next cycle date, and continue to the next subscription.
        $log .= ' [daily overdue retry: overdue forgiven by admin, clearing retry state] ';
        $forgivenPayload = array(
            'date'                 => $nextCycleDateFromAttr !== null ? $nextCycleDateFromAttr : date('Y-m-d'),
            'overdue_balance'      => 0.0,
            'consecutive_failures' => 0,
        );
        if (isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes'])) {
            unset($payment_details['subscription_attributes']['overdue_daily_retry_next_cycle']);
            $forgivenPayload['subscription_attributes'] = $payment_details['subscription_attributes'];
        }
        $paypalacSavedCardRecurring->update_payment_info($payment_id, $forgivenPayload);
        $paypalacSavedCardRecurring->update_payment_status(
            $payment_id,
            'scheduled',
            '  Overdue balance forgiven by admin. Retry cleared. Next billing date: ' . ($nextCycleDateFromAttr ?? 'N/A') . '. '
        );
        $results['skipped'][] = array(
            'subscription_id'    => $payment_id,
            'customer_name'      => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name'       => $payment_details['products_name'],
            'skip_reason'        => 'overdue balance forgiven by admin',
            'card_brand'         => $paypalacSavedCardRecurring->get_saved_card_display_brand($card_details),
            'card_last4_display' => $card_details['last_digits'] ?? 'N/A',
            'customer_portal_url' => recurring_customer_portal_url(),
            'add_card_url'        => recurring_add_card_url(),
        );
        continue;
    }

    // Determine the next scheduled billing cycle date.
    // For daily retries we already have this stored; for normal billing cycles we
    // advance the schedule by one period from the current next_payment_date.
    $attributes = $extractSubscriptionAttributes($payment_details);
    $next_payment = null;
    $next_payment_display = 'N/A';
    $has_schedule_context = is_array($attributes)
        && isset($attributes['billingperiod']) && $attributes['billingperiod'] !== ''
        && isset($attributes['billingfrequency']) && $attributes['billingfrequency'] !== '';

    if ($isOverdueRetry) {
        // Use the stored next cycle date — do not re-compute from today's date.
        $next_payment = $nextCycleDateFromAttr;
        $next_payment_display = $next_payment ?? 'N/A';
    } elseif ($has_schedule_context) {
        $scheduledDate = $parseRecurringDate(isset($payment_details['next_payment_date']) ? $payment_details['next_payment_date'] : '');
        $nextCycleDue = $advanceBillingCycle($scheduledDate, $attributes);
        if ($nextCycleDue instanceof DateTime) {
            $nextCycleDue->setTime(0, 0, 0);
            $next_payment = $nextCycleDue->format('Y-m-d');
            $next_payment_display = $next_payment;
        }
    }

    if ($total_to_bill > 0) { //if it has been paid for fully in store credit, then don't attempt to process card.
        $payment_result = $paypalacSavedCardRecurring->process_payment($payment_id, $total_to_bill); //Process card. This function also updates the status to success or failed
        $success = $payment_result['success'];
        $txn_id = $payment_result['transaction_id'] ?? '';
        $failure_reason = $payment_result['error'] ?? '';
        $payment_intent = $payment_result['intent'] ?? 'CAPTURE';
    } else {
        $_SESSION['payment'] = 'storecredit';
        $success = true; //SC can't fail
        $txn_id = '';
        $failure_reason = '';
        $payment_intent = 'CAPTURE'; // Store credit is always considered "captured"
        $payment_result = array('success' => true, 'intent' => 'CAPTURE');
        $paypalacSavedCardRecurring->add_payment_comment($payment_id, '  Paid with store credit.  ');
    }

    $log .= "\n Recurring Payment id " . $payment_id . ' | ' . $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'] . ' |  ' . $payment_details['products_name'] . '| amount: $' . $total_to_bill . ' | ';

    if ($success) {
        // Determine the correct order status based on payment intent (AUTHORIZE vs CAPTURE)
        $recurring_order_status = $paypalacSavedCardRecurring->get_order_status_for_intent($payment_intent);

        if (!$has_schedule_context) {
            $cleanupTriggered = $paypalacSavedCardRecurring->cancel_subscription_for_missing_source_order($payment_details);
            $log .= $cleanupTriggered ? ' Subscription cancelled due to missing source order. ' : ' Missing schedule metadata detected. ';
        }

        $log .= ' Payment successful ';

        // Build a customer-facing order comment when an overdue balance was included in
        // this charge so the customer is not alarmed by the higher-than-normal amount.
        $orderOverdueComment = '';
        if ($overdue_balance > 0) {
            $orderOverdueComment = 'Your subscription payment of $' . number_format((float) $total_to_bill, 2)
                . ' includes your regular subscription amount of $' . number_format($regular_cycle_amount, 2)
                . ' plus a previously overdue balance of $' . number_format($overdue_balance, 2)
                . ' that could not be collected during a prior billing cycle.';
        }

        $order_id = $paypalacSavedCardRecurring->create_order($order, $payment_details['saved_credit_card_id'], $recurring_order_status); //create an order in Zen Cart

        // Add the overdue explanation comment to the order history so the customer sees it
        if ($order_id > 0 && $orderOverdueComment !== '' && function_exists('zen_update_orders_history')) {
            zen_update_orders_history($order_id, $orderOverdueComment, null, -1, 0);
        }

        // Record the PayPal transaction in the database for admin refund/void functionality
        if ($order_id > 0 && !empty($payment_result['paypal_order_id'])) {
            $paypalacSavedCardRecurring->record_paypal_transaction($order_id, $payment_result, $payment_details);
        }

        // Increment the subscription coupon billing-cycle counter if a discount was applied
        if ($subscription_coupon_data !== null && $subscription_coupon_discount > 0) {
            $paypalacSavedCardRecurring->increment_subscription_coupon_cycles($payment_id);
        }

        // Remove any legacy catch-up keys that may still exist in subscription_attributes_json
        // from before the 1_9_0 migration. They are inert but tidying them avoids confusion.
        // Also clear the daily retry marker now that payment has succeeded.
        if (isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes'])) {
            unset($payment_details['subscription_attributes']['catchup_payments_override']);
            unset($payment_details['subscription_attributes']['intended_billing_date']);
            unset($payment_details['subscription_attributes']['skip_catchup']);
            unset($payment_details['subscription_attributes']['overdue_daily_retry_next_cycle']);
        }

        $updatePayload = array(
            'order_id'             => $order_id,
            'date'                 => $next_payment !== null ? $next_payment : date('Y-m-d'),
            'overdue_balance'      => 0.0,
            'consecutive_failures' => 0,
        );
        if (isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes'])) {
            $updatePayload['subscription_attributes'] = $payment_details['subscription_attributes'];
        }
        $paypalacSavedCardRecurring->update_payment_info($payment_id, $updatePayload);

        if ($next_payment !== null) {
            // More billing cycles remaining - set status to 'scheduled' with next billing date
            $paypalacSavedCardRecurring->update_payment_status($payment_id, 'scheduled', '  Next billing date set to ' . $next_payment . '. ');
        } else {
            // No next payment date means max billing cycles reached - set status to 'complete'
            $paypalacSavedCardRecurring->update_payment_status($payment_id, 'complete', '  Subscription completed - all billing cycles processed. ');
            $log .= ' Subscription COMPLETED (all billing cycles processed). ';
        }
        if ($order_id > 0 && function_exists('zen_update_orders_history')) {
            $historyComment = 'Subscription #' . $payment_id . ' recurring payment.';
            if (!empty($txn_id)) {
                $historyComment .= ' Transaction ID: ' . $txn_id . '.';
            }
            if ($overdue_balance > 0) {
                $historyComment .= ' Overdue balance of $' . number_format($overdue_balance, 2) . ' cleared.';
            }
            zen_update_orders_history($order_id, $historyComment, null, -1, 0);
        }
        if ($next_payment !== null) {
            $subscription_domain = '';
            if (isset($payment_details['domain']) && $payment_details['domain'] !== '') {
                $subscription_domain = $payment_details['domain'];
            } elseif (isset($attributes['domain']) && $attributes['domain'] !== '') {
                $subscription_domain = $attributes['domain'];
            } else {
                $subscription_domain = $paypalacSavedCardRecurring->get_domain(0, $payment_details);
            }
            $paypalacSavedCardRecurring->add_licence($order_id, $payment_details['products_id'], $next_payment, $subscription_domain, $payment_details['products_name'], $payment_details['products_model']);
        }
        // the following function will add the customer to the group pricing as well schedule a cancellation for 5 days after their next payment
        $paypalacSavedCardRecurring->create_group_pricing($payment_details['products_id'], $payment_details['customers_id'], $next_payment ?? 0);
        $results['success'][] = array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            'amount' => number_format($total_to_bill, 2),
            'card_brand' => $paypalacSavedCardRecurring->get_saved_card_display_brand($card_details),
            'card_last4' => $card_details['last_digits'] ?? 'N/A',
            'exp_month' => substr($card_details['expiry'], 0, 2),
            'exp_year' => substr($card_details['expiry'], 2, 2),
            'txn_id' => $txn_id,
            'invoice_number' => $order_id,
            'next_charge_date' => $next_payment_display,
            'subscription_url' => 'N/A',
        );
        $total_collected += (float) $total_to_bill;
    } else {
        // For a daily retry the overdue balance is unchanged — the missed amount was
        // already accumulated when the billing cycle first failed.  Only a new billing
        // cycle failure (isOverdueRetry === false) adds to the overdue balance.
        $new_overdue_balance      = $isOverdueRetry ? $overdue_balance : ($overdue_balance + $regular_cycle_amount);
        $new_consecutive_failures = $consecutive_failures + 1;

        // Register cancellation to ensure discount removal after failure, but only if:
        // 1. Customer has group pricing
        // 2. No existing cancellation scheduled
        // 3. Product is a payment plan (categories 224 or 247)

        // First, confirm the product is a payment plan
        $is_payment_plan = (
            zen_product_in_category((int) $payment_details['products_id'], 224) ||
            zen_product_in_category((int) $payment_details['products_id'], 247)
        );

        if ($is_payment_plan) {
            // Check if customer still has group pricing
            $customer_group = $db->Execute(
                "SELECT customers_group_pricing
             FROM " . TABLE_CUSTOMERS . "
             WHERE customers_id = " . (int) $payment_details['customers_id']
            );

            if ($customer_group->RecordCount() > 0 && (int) $customer_group->fields['customers_group_pricing'] != 0) {
                // Customer has group pricing, now check if cancellation already exists
                $check_cancel = $db->Execute(
                    "SELECT id FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . "
                 WHERE customers_id = " . (int) $payment_details['customers_id']
                );

                if ($check_cancel->RecordCount() == 0) {
                    $expiration_date = date('Y-m-d', strtotime('+5 days'));
                    $db->Execute(
                        "INSERT INTO " . TABLE_SUBSCRIPTION_CANCELLATIONS . "
                     (customers_id, group_name, expiration_date)
                     VALUES (" . (int) $payment_details['customers_id'] . ", '" . $db->prepare_input($payment_details['products_name']) . "', '" . $expiration_date . "')"
                    );
                    $log .= ' | Cancellation scheduled (expiration: ' . $expiration_date . ') ';
                } else {
                    $log .= ' | Cancellation already scheduled, no changes made ';
                }
            } else {
                $log .= ' | No group pricing assigned, cancellation not needed ';
            }
        } else {
            $log .= ' | Product is not a payment plan (skipped cancellation) ';
        }

        // Always set next_payment_date to tomorrow so the overdue balance is retried daily.
        // For a new billing cycle failure also store the scheduled next cycle date so we
        // know when the next regular cycle is due (and can skip re-accumulating overdue on
        // every daily retry until then).
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));

        $failureUpdatePayload = array(
            'overdue_balance'      => $new_overdue_balance,
            'consecutive_failures' => $new_consecutive_failures,
            'date'                 => $tomorrowDate,
        );

        // Persist/update the next billing cycle date in subscription_attributes_json.
        $failureAttrs = $currentAttrs;
        if (!$isOverdueRetry && $next_payment !== null) {
            // New billing cycle failure: record when the NEXT cycle would normally fall.
            $failureAttrs['overdue_daily_retry_next_cycle'] = $next_payment;
        }
        // (For daily retries the stored value is already correct — leave it unchanged.)
        $failureUpdatePayload['subscription_attributes'] = $failureAttrs;

        $paypalacSavedCardRecurring->update_payment_info($payment_id, $failureUpdatePayload);

        // Determine max allowed failures (0 = unlimited retries)
        $max_fails_allowed = defined('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED')
            ? (int) SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED
            : 0;

        $next_retry_date      = $tomorrowDate;
        $has_exceeded_max_attempts = ($max_fails_allowed > 0 && $new_consecutive_failures >= $max_fails_allowed);
        $customer_notified = false;

        if ($has_exceeded_max_attempts) {
            // Max attempts reached - set status to 'failed' and notify customer
            $paypalacSavedCardRecurring->update_payment_status($payment_id, 'failed', ' Max retry attempts (' . $max_fails_allowed . ') exceeded. ');
            $customerName = $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'];
            $message = sprintf(SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL, $customerName, $payment_details['products_name'], $payment_details['last_digits'], $payment_details['products_name']);
            if (!empty($payment_details['customers_email_address'])) {
                zen_mail($customerName, $payment_details['customers_email_address'], SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT, $message, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'recurring_failure');
                ZammadClient::notifyRecurringPaymentFailure(
                    $payment_details['customers_email_address'],
                    $customerName,
                    SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT,
                    $message,
                    array(
                        'subscription_id' => (int) $payment_id,
                        'failure_kind' => 'final',
                    )
                );
                $customer_notified = true;
            }
            $log .= ' Status set to FAILED after ' . $new_consecutive_failures . ' consecutive failed attempts (max: ' . $max_fails_allowed . ').' . ($customer_notified ? ' Customer notified.' : ' (no email on file — not notified).');
        } else {
            // Keep status as 'scheduled' — the overdue balance will be retried tomorrow.
            // process_payment() must not leave status as 'failed' or get_scheduled_payments()
            // will skip this row and daily retries never run.
            $nextCycleNote = $next_payment !== null ? '. Next billing cycle: ' . $next_payment : '';
            $paypalacSavedCardRecurring->update_payment_status(
                $payment_id,
                'scheduled',
                ' Payment attempt failed. Overdue balance $' . number_format($new_overdue_balance, 2) . '. Will retry tomorrow (' . $next_retry_date . ')' . $nextCycleNote . '. '
            );
            // Only send the customer warning email on the initial billing-cycle failure,
            // not on every subsequent daily retry (to avoid spamming the customer).
            if (!$isOverdueRetry && !empty($payment_details['customers_email_address'])) {
                $customerName = $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'];
                $message = sprintf(SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL, $customerName, $payment_details['products_name'], $payment_details['last_digits'], $payment_details['products_name']);
                zen_mail($customerName, $payment_details['customers_email_address'], SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT, $message, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'recurring_failure');
                ZammadClient::notifyRecurringPaymentFailure(
                    $payment_details['customers_email_address'],
                    $customerName,
                    SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT,
                    $message,
                    array(
                        'subscription_id' => (int) $payment_id,
                        'failure_kind' => 'warning',
                    )
                );
                $customer_notified = true;
            }
            if ($max_fails_allowed > 0) {
                $log .= ' Payment failed, overdue balance now $' . number_format($new_overdue_balance, 2) . '. Retrying tomorrow (attempt ' . $new_consecutive_failures . ' of ' . $max_fails_allowed . ')';
            } else {
                $log .= ' Payment failed, overdue balance now $' . number_format($new_overdue_balance, 2) . '. Retrying tomorrow (unlimited retries)';
            }
        }
        $results['failed'][] = array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            // Report the amount actually attempted at the gateway, matching the
            // success row's behaviour.  $regular_cycle_amount is 0 for daily overdue
            // retries (because no new cycle is being added), which made the report
            // show "$0.00" even though the gateway had been charged the overdue
            // balance.  $total_to_bill always reflects the real attempted amount.
            'amount' => number_format((float) $total_to_bill, 2),
            'failure_reason' => $failure_reason,
            'gateway_code' => 'N/A',
            'next_retry_date' => $next_retry_date,
            'attempt_number' => $new_consecutive_failures,
            'max_attempts' => $max_fails_allowed > 0 ? $max_fails_allowed : 'Unlimited',
            'customer_notified' => $customer_notified ? 'Yes' : 'No',
            'subscription_url' => 'N/A',
        );
    }
    sleep(1);
}

// Re-sync legacy subscriptions after processing all payments.
// The initial syncLegacySubscriptions() call at the top of this cron copies
// next_payment_date from TABLE_SAVED_CREDIT_CARDS_RECURRING to TABLE_PAYPAL_SUBSCRIPTIONS
// BEFORE payments are processed.  The payment loop above updates next_payment_date in
// TABLE_SAVED_CREDIT_CARDS_RECURRING only, leaving TABLE_PAYPAL_SUBSCRIPTIONS stale until
// the next cron run.  This second sync copies the freshly-updated dates so the admin UI
// (paypalac_subscriptions.php) reflects the correct next billing date immediately.
\PayPalAdvancedCheckout\Common\LegacySubscriptionMigrator::syncLegacySubscriptions();

$run_date = date('Y-m-d');
$timezone = date_default_timezone_get();
$report_id = uniqid();
$generated_at = date('Y-m-d H:i:s');
$success_count = count($results['success']);
$fail_count = count($results['failed']);
$skipped_count = count($results['skipped']);
$invoiced_count = count($results['invoiced']);
$total_processed = $success_count + $fail_count + $skipped_count + $invoiced_count;

$sections_all = array(
    'success' => true,
    'failed' => true,
    'skipped' => true,
    'invoiced' => true,
);

$log = recurring_build_email_text(
    $run_date,
    $timezone,
    $report_id,
    $generated_at,
    $total_processed,
    $success_count,
    $fail_count,
    $skipped_count,
    $total_collected,
    $currency,
    $results,
    $sections_all
);

$html_email = recurring_build_email_html(
    $run_date,
    $timezone,
    $report_id,
    $generated_at,
    $total_processed,
    $success_count,
    $fail_count,
    $skipped_count,
    $total_collected,
    $currency,
    $results,
    $sections_all
);

print recurring_format_output($log);
$_SESSION['in_cron'] = false;

// Determine email recipients with fallback chain
$notification_candidates = array();

if (defined('MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL')) {
    $configuredRecipients = trim((string) MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL);
    if ($configuredRecipients !== '') {
        $notification_candidates = preg_split('/[;,]+/', $configuredRecipients);
    }
}

if (empty($notification_candidates) && function_exists('zen_get_configuration_key_value')) {
    $configuredRecipients = trim((string) zen_get_configuration_key_value('MODULE_PAYMENT_PAYPALAC_CRON_REPORT_EMAIL'));
    if ($configuredRecipients !== '') {
        $notification_candidates = preg_split('/[;,]+/', $configuredRecipients);
    }
}

if (empty($notification_candidates) && defined('STORE_OWNER_EMAIL_ADDRESS')) {
    $notification_candidates[] = STORE_OWNER_EMAIL_ADDRESS;
}

if (empty($notification_candidates) && defined('EMAIL_FROM')) {
    $notification_candidates[] = EMAIL_FROM;
}

$notification_recipients = array_values(array_unique(array_filter(array_map('trim', $notification_candidates), function ($email) {
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
})));

if (empty($notification_recipients)) {
    error_log('PayPal Cron - No valid cron report email recipient configured.');
} else {
    foreach ($notification_recipients as $recipient) {
        zen_mail(
            $recipient,
            $recipient,
            'PayPal Advanced Checkout Recurring Payment Log',
            $log,
            STORE_NAME,
            EMAIL_FROM,
            array('EMAIL_MESSAGE_HTML' => $html_email),
            'recurring_log'
        );
    }
}

$additional_failure_recipients = ZammadClient::recurringFailureNoticeRecipients();

if ($fail_count > 0 && !empty($additional_failure_recipients)) {
    $failure_sections = array('failed' => true);
    $failure_log = recurring_build_email_text(
        $run_date,
        $timezone,
        $report_id,
        $generated_at,
        $total_processed,
        $success_count,
        $fail_count,
        $skipped_count,
        $total_collected,
        $currency,
        $results,
        $failure_sections
    );

    $failure_html_email = recurring_build_email_html(
        $run_date,
        $timezone,
        $report_id,
        $generated_at,
        $total_processed,
        $success_count,
        $fail_count,
        $skipped_count,
        $total_collected,
        $currency,
        $results,
        $failure_sections
    );

    foreach ($additional_failure_recipients as $recipient) {
        zen_mail(
            $recipient,
            $recipient,
            'Recurring Payment Failures',
            $failure_log,
            STORE_NAME,
            EMAIL_FROM,
            array('EMAIL_MESSAGE_HTML' => $failure_html_email),
            'recurring_log_failures'
        );
    }
}
