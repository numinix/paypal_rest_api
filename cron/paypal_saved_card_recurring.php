<?php
require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
\PayPalRestful\Common\LegacySubscriptionMigrator::syncLegacySubscriptions();
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';

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

$_SESSION['in_cron'] = true; //setting to ensure that some functions that should onlt happen for new orders don't happen during cron.

if (!function_exists('recurring_esc_html')) {
    function recurring_esc_html($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        $lines[] = "Paid: $success_count | Failed: $fail_count | Skipped: $skipped_count";
        $lines[] = "Collected: {$currency}" . number_format($total_collected, 2);
        $lines[] = "Report ID $report_id • Generated $generated_at";
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

        return implode("\n", $html_segments);
    }
}

$paypalSavedCardRecurring = new paypalSavedCardRecurring();

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

$extractSubscriptionAttributes = function ($details) use ($paypalSavedCardRecurring) {
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
        $fallback = $paypalSavedCardRecurring->get_attributes($sourceOrdersProductsId);
        if (is_array($fallback)) {
            $attributes = array_merge($fallback, $attributes);
        }
    }

    if (isset($attributes['billingfrequency']) && is_numeric($attributes['billingfrequency'])) {
        $attributes['billingfrequency'] = (int) $attributes['billingfrequency'];
    }

    return $attributes;
};

$normalizePaymentDetails = function ($paymentId, $details) use ($paypalSavedCardRecurring) {
    if (!is_array($details)) {
        return $details;
    }

    $normalized = $paypalSavedCardRecurring->migrate_legacy_subscription_context($paymentId, $details);
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

$determineIntendedBillingDate = function (array $paymentDetails, array $attributes, $numFailedPayments) use ($parseRecurringDate) {
    $scheduledDateString = isset($paymentDetails['date']) ? $paymentDetails['date'] : '';
    $scheduledDate = $parseRecurringDate($scheduledDateString);

    if (isset($attributes['intended_billing_date']) && $attributes['intended_billing_date'] !== '') {
        $intended = $parseRecurringDate($attributes['intended_billing_date']);
        if ($intended instanceof DateTime) {
            return $intended;
        }
    }

    $intended = clone $scheduledDate;
    if (is_numeric($numFailedPayments) && (int) $numFailedPayments > 0) {
        try {
            $intended->sub(new DateInterval('P' . (int) $numFailedPayments . 'D'));
        } catch (Exception $e) {
            // Fallback to original scheduled date if subtraction fails
        }
    }

    return $intended;
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

$todays_payments = $paypalSavedCardRecurring->get_scheduled_payments();

$results = array('success' => array(), 'failed' => array(), 'skipped' => array());
$total_collected = 0.0;
$currency = '$';
$log = '';

foreach ($todays_payments as $payment_id) {
    $order = $zf_insert_id = null; //ensure order is reset.

    $payment_details = $normalizePaymentDetails($payment_id, $paypalSavedCardRecurring->get_payment_details($payment_id));

    // -------------------------------------------------------------------
    // Check if this payment should be skipped
    // -------------------------------------------------------------------
    $skip_payment = isset($payment_details['skip_next_payment']) && (int)$payment_details['skip_next_payment'] === 1;
    
    if ($skip_payment) {
        // Create a $0 order to maintain membership/license validity
        list($order, $order_totals) = $paypalSavedCardRecurring->prepare_order($payment_details, $payment_details['products_id'], $payment_details['original_orders_products_id']);
        
        // Override order total to $0
        foreach ($order_totals as &$ot) {
            if ($ot['code'] == 'ot_total') {
                $ot['value'] = 0;
                $ot['text'] = '$0.00';
            }
            if ($ot['code'] == 'ot_subtotal') {
                $ot['value'] = 0;
                $ot['text'] = '$0.00';
            }
        }
        unset($ot);
        
        // Set payment method to indicate skipped
        $_SESSION['payment'] = 'skipped';
        
        // Create the $0 order
        $order_id = $paypalSavedCardRecurring->create_order($order, $payment_details['saved_credit_card_id']);
        
        // Extract subscription attributes for scheduling
        $attributes = $extractSubscriptionAttributes($payment_details);
        $has_schedule_context = is_array($attributes)
            && isset($attributes['billingperiod']) && $attributes['billingperiod'] !== ''
            && isset($attributes['billingfrequency']) && $attributes['billingfrequency'] !== '';
        
        if ($has_schedule_context) {
            // Calculate next billing date
            $todayDate = new DateTime('today');
            $intendedBillingDate = $determineIntendedBillingDate($payment_details, $attributes, 0);
            
            if ($intendedBillingDate instanceof DateTime) {
                $intendedBillingDate->setTime(0, 0, 0);
                $nextCycleDue = $advanceBillingCycle($intendedBillingDate, $attributes);
                
                if ($nextCycleDue instanceof DateTime) {
                    $nextCycleDue->setTime(0, 0, 0);
                    $scheduledProcessingDate = clone $nextCycleDue;
                    
                    if ($nextCycleDue <= $todayDate) {
                        $scheduledProcessingDate = clone $todayDate;
                        $scheduledProcessingDate->modify('+1 day');
                    }
                    
                    $next_payment = $scheduledProcessingDate->format('Y-m-d');
                    
                    // Build metadata for next payment
                    $metadata = $buildSubscriptionMetadata($payment_details, $payment_details['amount']);
                    if (!isset($metadata['subscription_attributes']) || !is_array($metadata['subscription_attributes'])) {
                        $metadata['subscription_attributes'] = array();
                    }
                    $metadata['subscription_attributes']['intended_billing_date'] = $nextCycleDue->format('Y-m-d');
                    
                    // Schedule next payment
                    $rescheduleOrdersProductsId = $payment_details['original_orders_products_id'] ?? ($payment_details['orders_products_id'] ?? null);
                    $paypalSavedCardRecurring->schedule_payment($payment_details['amount'], $next_payment, $payment_details['saved_credit_card_id'], $rescheduleOrdersProductsId, 'Scheduled after skipped payment.', $metadata);
                    
                    // Add license with next payment date
                    $subscription_domain = '';
                    if (isset($payment_details['domain']) && $payment_details['domain'] !== '') {
                        $subscription_domain = $payment_details['domain'];
                    } elseif (isset($attributes['domain']) && $attributes['domain'] !== '') {
                        $subscription_domain = $attributes['domain'];
                    } else {
                        $subscription_domain = $paypalSavedCardRecurring->get_domain(0, $payment_details);
                    }
                    $paypalSavedCardRecurring->add_licence($order_id, $payment_details['products_id'], $next_payment, $subscription_domain, $payment_details['products_name'], $payment_details['products_model']);
                }
            }
        }
        
        // Maintain group pricing
        $paypalSavedCardRecurring->create_group_pricing($payment_details['products_id'], $payment_details['customers_id']);
        
        // Clear the skip flag and mark as complete
        $updatePayload = array(
            'order_id' => $order_id,
            'date' => date('Y-m-d')
        );
        $paypalSavedCardRecurring->update_payment_info($payment_id, $updatePayload);
        $paypalSavedCardRecurring->update_payment_status($payment_id, 'complete', '  Payment skipped by admin/automation. $0 order created.  ');
        
        // Reset skip flag
        global $db;
        $db->Execute('UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' SET skip_next_payment = 0 WHERE saved_credit_card_recurring_id = ' . (int)$payment_id);
        
        $results['success'][] = array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            'amount' => '0.00 (skipped)',
            'card_brand' => 'N/A',
            'card_last4' => 'N/A',
            'exp_month' => '--',
            'exp_year' => '--',
            'txn_id' => 'SKIPPED',
            'invoice_number' => $order_id,
            'next_charge_date' => $next_payment ?? 'N/A',
            'subscription_url' => 'N/A',
        );
        
        $log .= "\n Skipped payment for subscription $payment_id | {$payment_details['products_name']} | \$0 order #$order_id created";
        continue; // Skip to next subscription
    }

    // -------------------------------------------------------------------
    // If the saved card attached to this payment has expired (or was marked
    // deleted), attempt to swap in another non-expired card.  When no valid
    // card exists for the customer, skip processing this subscription.
    // -------------------------------------------------------------------
    $card_details = $paypalSavedCardRecurring->get_saved_card_details($payment_details['saved_credit_card_id']);

    $expiry = isset($card_details['expiry']) ? $card_details['expiry'] : '';
    $deleted = isset($card_details['is_deleted']) && $card_details['is_deleted'] == '1';
    $expired = false;
    if (strlen($expiry) == 4) { // mmyy format
        $expiry_date = DateTime::createFromFormat('my', $expiry);
        if ($expiry_date instanceof DateTime) {
            $expiry_date->modify('last day of this month');
            $expired = $expiry_date < new DateTime('today');
        }
    }

    if ($deleted || $expired) {
        $replacement = $paypalSavedCardRecurring->get_customers_saved_card($payment_details['customers_id']);

        if ($replacement) {
            // Update to use the new card and reload details
            $paypalSavedCardRecurring->update_payment_info($payment_id, array('saved_credit_card_id' => $replacement));
            $payment_details = $normalizePaymentDetails($payment_id, $paypalSavedCardRecurring->get_payment_details($payment_id));
            $log .= "\n Updated card for subscription $payment_id | {$payment_details['products_name']}";
        } else {
            $results['skipped'][] = array(
                'subscription_id' => $payment_id,
                'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
                'product_name' => $payment_details['products_name'],
                'skip_reason' => 'no valid card',
                'card_brand' => $card_details['card_type'] ?? 'N/A',
                'card_last4_display' => $card_details['last_digits'] ?? 'N/A',
                'customer_portal_url' => 'N/A',
                'add_card_url' => 'N/A',
            );
            $log .= "\n Skipped subscription $payment_id | {$payment_details['products_name']} | no valid card";
            continue; // nothing to bill today
        }
    }

    list($order, $order_totals) = $paypalSavedCardRecurring->prepare_order($payment_details, $payment_details['products_id'], $payment_details['original_orders_products_id']);

    //update price to account for tax and price changes
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

    //if pricing has changed, update the recurring orders table.
    if ($recurring_amount != $payment_details['amount']) {
        $paypalSavedCardRecurring->update_payment_info($payment_id, array('amount' => $recurring_amount, 'comments' => '  Price automatically updated. '));
        $payment_details = $normalizePaymentDetails($payment_id, $paypalSavedCardRecurring->get_payment_details($payment_id));
    }

    if ($total_to_bill > 0) { //if it has been paid for fully in store credit, then don't attempt to process card.
        $payment_result = $paypalSavedCardRecurring->process_payment($payment_id, $total_to_bill); //Process card. This function also updates the status to success or failed
        $success = $payment_result['success'];
        $txn_id = $payment_result['transaction_id'] ?? '';
        $failure_reason = $payment_result['error'] ?? '';
    } else {
        $_SESSION['payment'] = 'storecredit';
        $success = true; //SC can't fail
        $txn_id = '';
        $failure_reason = '';
        $paypalSavedCardRecurring->update_payment_status($payment_id, 'complete', '  Paid with store credit.  ');
    }

    $num_failed_payments = $paypalSavedCardRecurring->count_failed_payments($payment_id, $payment_details);

    $log .= "\n Recurring Payment id " . $payment_id . ' | ' . $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'] . ' |  ' . $payment_details['products_name'] . '| amount: $' . $payment_details['amount'] . ' | ';

    if ($success) {
        $attributes = $extractSubscriptionAttributes($payment_details);
        $next_payment = null;
        $next_payment_display = 'N/A';
        $has_schedule_context = is_array($attributes)
            && isset($attributes['billingperiod']) && $attributes['billingperiod'] !== ''
            && isset($attributes['billingfrequency']) && $attributes['billingfrequency'] !== '';
        $intendedBillingDate = null;
        if (!$has_schedule_context) {
            $cleanupTriggered = $paypalSavedCardRecurring->cancel_subscription_for_missing_source_order($payment_details);
            $log .= $cleanupTriggered ? ' Subscription cancelled due to missing source order. ' : ' Missing schedule metadata detected. ';
        } else {
            $todayDate = new DateTime('today');
            $intendedBillingDate = $determineIntendedBillingDate($payment_details, $attributes, $num_failed_payments);
            if (!($intendedBillingDate instanceof DateTime)) {
                $log .= ' Invalid Date! ';
                continue;
            }

            $intendedBillingDate->setTime(0, 0, 0);
            $intendedBillingDateString = $intendedBillingDate->format('Y-m-d');

            $attributes['intended_billing_date'] = $intendedBillingDateString;
            if (!isset($payment_details['subscription_attributes']) || !is_array($payment_details['subscription_attributes'])) {
                $payment_details['subscription_attributes'] = array();
            }
            $payment_details['subscription_attributes']['intended_billing_date'] = $intendedBillingDateString;

            $nextCycleDue = $advanceBillingCycle($intendedBillingDate, $attributes);
            if (!($nextCycleDue instanceof DateTime)) {
                $log .= ' Invalid Date! ';
                continue;
            }

            $nextCycleDue->setTime(0, 0, 0);

            $scheduledProcessingDate = clone $nextCycleDue;
            if ($nextCycleDue <= $todayDate) {
                $scheduledProcessingDate = clone $todayDate;
                $scheduledProcessingDate->modify('+1 day');
                $log .= ' Catch-up payment scheduled for ' . $scheduledProcessingDate->format('Y-m-d') . ' (covers ' . $nextCycleDue->format('Y-m-d') . '). ';
            }

            $next_payment = $scheduledProcessingDate->format('Y-m-d');
            $next_payment_display = $next_payment;

            $metadata = $buildSubscriptionMetadata($payment_details, $payment_details['amount']);
            if (!isset($metadata['subscription_attributes']) || !is_array($metadata['subscription_attributes'])) {
                $metadata['subscription_attributes'] = array();
            }
            $metadata['subscription_attributes']['intended_billing_date'] = $nextCycleDue->format('Y-m-d');

            $rescheduleOrdersProductsId = $payment_details['original_orders_products_id'] ?? ($payment_details['orders_products_id'] ?? null);
            $paypalSavedCardRecurring->schedule_payment($payment_details['amount'], $next_payment, $payment_details['saved_credit_card_id'], $rescheduleOrdersProductsId, 'Scheduled after previous successful payment.', $metadata);
        }
        $log .= ' Payment successful ';
        $order_id = $paypalSavedCardRecurring->create_order($order, $payment_details['saved_credit_card_id']); //create an order in Zen Cart
        $processedDateString = date('Y-m-d');
        $updatePayload = array('order_id' => $order_id, 'date' => $processedDateString);
        if ($intendedBillingDate instanceof DateTime) {
            $updatePayload['date'] = $intendedBillingDate->format('Y-m-d');
            if ($intendedBillingDate->format('Y-m-d') !== $processedDateString) {
                $updatePayload['comments'] = '  Payment applied to ' . $intendedBillingDate->format('Y-m-d') . ' (processed ' . $processedDateString . '). ';
            }
        }
        if (isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes'])) {
            $updatePayload['subscription_attributes'] = $payment_details['subscription_attributes'];
        }
        $paypalSavedCardRecurring->update_payment_info($payment_id, $updatePayload);
        if ($next_payment !== null) {
            $subscription_domain = '';
            if (isset($payment_details['domain']) && $payment_details['domain'] !== '') {
                $subscription_domain = $payment_details['domain'];
            } elseif (isset($attributes['domain']) && $attributes['domain'] !== '') {
                $subscription_domain = $attributes['domain'];
            } else {
                $subscription_domain = $paypalSavedCardRecurring->get_domain(0, $payment_details);
            }
            $paypalSavedCardRecurring->add_licence($order_id, $payment_details['products_id'], $next_payment, $subscription_domain, $payment_details['products_name'], $payment_details['products_model']);
        }
        // the following function will add the customer to the group pricing as well schedule a cancellation for 5 days after their next payment
        $paypalSavedCardRecurring->create_group_pricing($payment_details['products_id'], $payment_details['customers_id']);
        $results['success'][] = array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            'amount' => number_format($total_to_bill, 2),
            'card_brand' => $card_details['card_type'] ?? 'N/A',
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

        $next_retry_date = 'N/A';
        if ($num_failed_payments >= SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED) {
            $message = sprintf(SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL, $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'], $payment_details['products_name'], $payment_details['last_digits'], $payment_details['products_name']);
            if (!empty($payment_details['customers_email_address'])) {
                zen_mail($payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'], $payment_details['customers_email_address'], SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT, $message, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'recurring_failure');
            }
            $log .= ' User has been notified after ' . $num_failed_payments . ' consecutive failed attempts to process card';
        } else { //try again tomorrow
            $tomorrow = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d") + 1, date("Y")));
            $metadata = $buildSubscriptionMetadata($payment_details, $payment_details['amount']);
            $rescheduleOrdersProductsId = $payment_details['original_orders_products_id'] ?? ($payment_details['orders_products_id'] ?? null);
            $paypalSavedCardRecurring->schedule_payment($payment_details['amount'], $tomorrow, $payment_details['saved_credit_card_id'], $rescheduleOrdersProductsId, 'Recurring payment automatically scheduled after failure.', $metadata); //try again tomorrow
            $message = sprintf(SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL, $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'], $payment_details['products_name'], $payment_details['last_digits'], $payment_details['products_name']);
            zen_mail($payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'], $payment_details['customers_email_address'], SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT, $message, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'recurring_failure');
            $log .= ' Payment failed, rescheduled for tomorrow.  Customer has been notified.';
            $next_retry_date = $tomorrow;
        }
        $results['failed'][] = array(
            'subscription_id' => $payment_id,
            'customer_name' => $payment_details['customers_firstname'] . ' ' . $payment_details['customers_lastname'],
            'product_name' => $payment_details['products_name'],
            'amount' => number_format($total_to_bill, 2),
            'failure_reason' => $failure_reason,
            'gateway_code' => 'N/A',
            'next_retry_date' => $next_retry_date,
            'attempt_number' => $num_failed_payments,
            'max_attempts' => SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED,
            'customer_notified' => 'Yes',
            'subscription_url' => 'N/A',
        );
    }
    sleep(1);
}

$run_date = date('Y-m-d');
$timezone = date_default_timezone_get();
$report_id = uniqid();
$generated_at = date('Y-m-d H:i:s');
$success_count = count($results['success']);
$fail_count = count($results['failed']);
$skipped_count = count($results['skipped']);
$total_processed = $success_count + $fail_count + $skipped_count;

$sections_all = array(
    'success' => true,
    'failed' => true,
    'skipped' => true,
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

print $log;
$_SESSION['in_cron'] = false;

zen_mail(
    MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL,
    MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL,
    'Recurring Payment Log',
    $log,
    STORE_NAME,
    EMAIL_FROM,
    array('EMAIL_MESSAGE_HTML' => $html_email),
    'recurring_log'
);

$additional_failure_recipients = array();
$raw_recipients = '';

if (function_exists('zen_get_configuration_key_value')) {
    $raw_recipients = zen_get_configuration_key_value('SAVED_CREDIT_CARDS_RECURRING_FAILURE_RECIPIENTS');
} else {
    $config_lookup = $db->Execute(
        "SELECT configuration_value"
        . " FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = 'SAVED_CREDIT_CARDS_RECURRING_FAILURE_RECIPIENTS'"
        . " LIMIT 1"
    );

    if ($config_lookup && !$config_lookup->EOF) {
        $raw_recipients = $config_lookup->fields['configuration_value'];
    }
}

if (is_string($raw_recipients) && $raw_recipients !== '') {
    $additional_failure_recipients = array_filter(array_map('trim', explode(',', $raw_recipients)), function ($email) {
        return $email !== '';
    });
}

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
