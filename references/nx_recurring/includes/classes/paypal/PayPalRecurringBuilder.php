<?php

class PayPalRecurringBuilder
{
    /**
     * Creates an array of normalized subscription definitions from the current order.
     * Each returned element mirrors the structure previously assembled inline in
     * paypal_wpp_recurring.php, while also including additional metadata used by the
     * REST checkout flow (e.g. converted frequency data and ISO8601 start times).
     *
     * @param order $order
     * @param string|null $currencyCode
     * @param string|null $defaultStartDate
     * @return array
     */
    public static function buildFromOrder($order, $currencyCode = null, $defaultStartDate = null)
    {
        global $db;

        if (!is_object($order) || !isset($order->products) || !is_array($order->products)) {
            return array();
        }

        $subscriptions = array();
        $defaultStartDate = ($defaultStartDate !== null) ? $defaultStartDate : date('Y-m-d');
        $currencyCode = self::resolveCurrencyCode($order, $currencyCode);

        foreach ($order->products as $product) {
            $subscription = array(
                'billingperiod' => null,
                'billingfrequency' => null,
                'totalbillingcycles' => 0,
                'startdate' => $defaultStartDate,
            );

            if (is_array($product['attributes'])) {
                foreach ($product['attributes'] as $attribute) {
                    $options = $db->Execute("SELECT products_options_name FROM " . TABLE_PRODUCTS_OPTIONS . " WHERE products_options_id = " . (int)$attribute['option_id'] . " LIMIT 1;");
                    if ($options->RecordCount() <= 0) {
                        continue;
                    }

                    $optionName = strtolower(str_replace(' ', '', $options->fields['products_options_name']));
                    switch ($optionName) {
                        case 'billingperiod':
                            $billingperiod = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int)$attribute['value_id'] . " LIMIT 1;");
                            if ($billingperiod->RecordCount() > 0) {
                                $subscription['billingperiod'] = self::normalizeBillingPeriod($billingperiod->fields['products_options_values_name']);
                            }
                            break;
                        case 'billingfrequency':
                            $billingfrequency = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int)$attribute['value_id'] . " LIMIT 1;");
                            if ($billingfrequency->RecordCount() > 0 && strpos($billingfrequency->fields['products_options_values_name'], 'Lifetime') === false) {
                                $subscription['billingfrequency'] = (int)$billingfrequency->fields['products_options_values_name'];
                            }
                            break;
                        case 'totalbillingcycles':
                            $totalbillingcycles = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int)$attribute['value_id'] . " LIMIT 1;");
                            if ($totalbillingcycles->RecordCount() > 0) {
                                $value = trim($totalbillingcycles->fields['products_options_values_name']);
                                if ($value === '' || strcasecmp($value, 'Good until cancelled') === 0) {
                                    $subscription['totalbillingcycles'] = 0;
                                } else {
                                    $subscription['totalbillingcycles'] = (int)$value;
                                }
                            }
                            break;
                        case 'startdate':
                            $subscription['startdate'] = $attribute['value'];
                            break;
                    }
                }
            }

            if ($subscription['billingperiod'] !== null && $subscription['billingfrequency'] !== null) {
                $subscriptions[$product['id']] = self::finalizeSubscriptionDefinition($product, $subscription, $currencyCode);
            }
        }

        return $subscriptions;
    }

    protected static function resolveCurrencyCode($order, $currencyCode)
    {
        if ($currencyCode !== null && strlen($currencyCode) > 0) {
            return $currencyCode;
        }
        if (isset($_SESSION['currency']) && strlen($_SESSION['currency']) > 0) {
            return $_SESSION['currency'];
        }
        if (isset($order->info['currency']) && strlen($order->info['currency']) > 0) {
            return $order->info['currency'];
        }
        return 'USD';
    }

    protected static function normalizeBillingPeriod($value)
    {
        switch ($value) {
            case 'Week':
            case 'Weekly':
                return 'Week';
            case 'Month':
            case 'Monthly':
                return 'Month';
            case 'SemiMonth':
            case 'Semi Monthly':
            case 'Semi-Monthly':
            case 'Bi Weekly':
            case 'Bi-Weekly':
                return 'SemiMonth';
            case 'Year':
            case 'Yearly':
                return 'Year';
            case 'Day':
            case 'Daily':
            default:
                return 'Day';
        }
    }

    protected static function finalizeSubscriptionDefinition(array $product, array $subscription, $currencyCode)
    {
        $subscription['amt'] = ($product['final_price'] - $product['onetime_charges']) * $product['qty'];
        $subscription['quantity'] = $product['qty'];
        $subscription['currencycode'] = $currencyCode;
        $subscription['desc'] = $product['name'];
        if (!isset($subscription['taxamt'])) {
            $subscription['taxamt'] = 0;
        }
        if ($product['tax'] > 0) {
            $subscription['taxamt'] = $product['tax'] * $product['qty'];
        }

        if ((int)$subscription['totalbillingcycles'] > 1 || (int)$subscription['totalbillingcycles'] === 0) {
            $schedule = self::calculateScheduleDetails($subscription);
            $subscription = array_merge($subscription, $schedule);
        } else {
            $subscription['profilestartdate'] = '';
            $subscription['expiration_date'] = '';
            $subscription['rest_frequency'] = self::convertRestFrequency($subscription['billingperiod'], (int)$subscription['billingfrequency']);
            $subscription['rest_total_cycles'] = (int)$subscription['totalbillingcycles'];
            $subscription['rest_tax_percentage'] = self::calculateTaxPercentage($subscription['amt'], $subscription['taxamt']);
        }

        return $subscription;
    }

    protected static function calculateScheduleDetails(array $subscription)
    {
        $billingPeriod = $subscription['billingperiod'];
        $billingFrequency = max(1, (int)$subscription['billingfrequency']);
        $totalCycles = (int)$subscription['totalbillingcycles'];
        $startDate = isset($subscription['startdate']) ? $subscription['startdate'] : date('Y-m-d');

        $now = time();
        $calculateStartDate = ($startDate === date('Y-m-d'));
        $startAfterPeriods = $billingFrequency;
        $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime($startDate));
        $endTime = $now;

        switch ($billingPeriod) {
            case 'Day':
                $endTime = strtotime('+' . $totalCycles . ' days', $now);
                if ($calculateStartDate) {
                    $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime($startDate . ' +' . $startAfterPeriods . ' days'));
                }
                break;
            case 'Week':
                $endTime = strtotime('+' . $totalCycles . ' weeks', $now);
                if ($calculateStartDate) {
                    $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime($startDate . ' +' . $startAfterPeriods . ' weeks'));
                }
                break;
            case 'SemiMonth':
                $todays_date = date('j');
                $num_full_months = (int)floor($totalCycles / 2);
                $num_partial_months = $totalCycles % 2;
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('n'), date('Y'));
                switch (true) {
                    case ($todays_date > 15):
                        $days_left_in_month = $days_in_month - $todays_date + ($num_partial_months * 15);
                        $endTime = strtotime('+' . $days_left_in_month . ' day' . ($days_left_in_month !== 1 ? 's' : '') . ($num_full_months > 0 ? $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '') : ''), $now);
                        if ($calculateStartDate) {
                            $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime('first day of next month'));
                        }
                        break;
                    case ($todays_date == 15):
                        $endTime = strtotime('+' . ($num_full_months > 0 ? $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '') : ''), $now);
                        if ($num_partial_months) {
                            $end_month = date('n', $endTime);
                            $end_year = date('Y', $endTime);
                            $days_in_end_month = cal_days_in_month(CAL_GREGORIAN, $end_month, $end_year);
                            $days_til_end_month = ($days_in_end_month - 15) + 1;
                            $endTime = strtotime('+' . $days_til_end_month . ' day' . ($days_til_end_month !== 1 ? 's' : ''), $endTime);
                        }
                        $profileStartDate = date('Y-m-d\T00:00:00\Z', $now);
                        break;
                    case ($todays_date > 1):
                        $days_left_in_period = 15 - $todays_date;
                        $endTime = strtotime('+' . $days_left_in_period . ' day' . ($days_left_in_period !== 1 ? 's' : '') . ($num_full_months > 0 ? $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '') : ''), $now);
                        if ($num_partial_months) {
                            $end_month = date('n', $endTime);
                            $end_year = date('Y', $endTime);
                            $days_in_end_month = cal_days_in_month(CAL_GREGORIAN, $end_month, $end_year);
                            $days_til_end_month = ($days_in_end_month - 15) + 1;
                            $endTime = strtotime('+' . $days_til_end_month . ' day' . ($days_til_end_month !== 1 ? 's' : ''), $endTime);
                        }
                        if ($calculateStartDate) {
                            $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime('+' . $days_left_in_period . ' day' . ($days_left_in_period !== 1 ? 's' : '')));
                        }
                        break;
                    case ($todays_date == 1):
                    default:
                        $endTime = strtotime('+' . ($num_full_months > 0 ? $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '') : '') . ($num_partial_months ? ' +15 days' : ''), $now);
                        $profileStartDate = date('Y-m-d\T00:00:00\Z', $now);
                        break;
                }
                break;
            case 'Month':
                $endTime = strtotime('+' . $totalCycles . ' months', $now);
                if ($calculateStartDate) {
                    $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime($startDate . ' +' . $startAfterPeriods . ' months'));
                }
                break;
            case 'Year':
                $endTime = strtotime('+' . $totalCycles . ' years', $now);
                if ($calculateStartDate) {
                    $profileStartDate = date('Y-m-d\T00:00:00\Z', strtotime($startDate . ' +' . $startAfterPeriods . ' years'));
                }
                break;
        }

        $expirationDate = date('Y-m-d', $endTime);

        return array(
            'profilestartdate' => $profileStartDate,
            'expiration_date' => $expirationDate,
            'rest_frequency' => self::convertRestFrequency($billingPeriod, $billingFrequency),
            'rest_total_cycles' => ($totalCycles > 0) ? $totalCycles : 0,
            'rest_tax_percentage' => self::calculateTaxPercentage($subscription['amt'], $subscription['taxamt'])
        );
    }

    protected static function convertRestFrequency($billingPeriod, $billingFrequency)
    {
        $billingFrequency = max(1, (int)$billingFrequency);
        switch ($billingPeriod) {
            case 'Week':
                return array('interval_unit' => 'WEEK', 'interval_count' => $billingFrequency);
            case 'SemiMonth':
                return array('interval_unit' => 'DAY', 'interval_count' => $billingFrequency * 15);
            case 'Month':
                return array('interval_unit' => 'MONTH', 'interval_count' => $billingFrequency);
            case 'Year':
                return array('interval_unit' => 'YEAR', 'interval_count' => $billingFrequency);
            case 'Day':
            default:
                return array('interval_unit' => 'DAY', 'interval_count' => $billingFrequency);
        }
    }

    protected static function calculateTaxPercentage($amount, $taxAmount)
    {
        $amount = (float)$amount;
        $taxAmount = (float)$taxAmount;
        if ($amount <= 0 || $taxAmount <= 0) {
            return null;
        }
        $percentage = ($taxAmount / $amount) * 100;
        return number_format($percentage, 2, '.', '');
    }

    public static function buildRestProductPayload(array $subscription)
    {
        $name = self::truncate($subscription['desc'], 127);
        return array(
            'name' => $name,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
            'description' => self::truncate($subscription['desc'], 256),
        );
    }

    public static function buildRestPlanPayload(array $subscription, $productId, $planName)
    {
        $frequency = isset($subscription['rest_frequency']) ? $subscription['rest_frequency'] : self::convertRestFrequency($subscription['billingperiod'], $subscription['billingfrequency']);
        $plan = array(
            'product_id' => $productId,
            'name' => self::truncate($planName, 127),
            'description' => self::truncate($subscription['desc'], 256),
            'billing_cycles' => array(
                array(
                    'frequency' => array(
                        'interval_unit' => $frequency['interval_unit'],
                        'interval_count' => $frequency['interval_count'],
                    ),
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => isset($subscription['rest_total_cycles']) ? (int)$subscription['rest_total_cycles'] : (int)$subscription['totalbillingcycles'],
                    'pricing_scheme' => array(
                        'fixed_price' => array(
                            'value' => number_format((float)$subscription['amt'], 2, '.', ''),
                            'currency_code' => $subscription['currencycode'],
                        ),
                    ),
                ),
            ),
            'payment_preferences' => array(
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ),
        );

        if (!empty($subscription['rest_tax_percentage'])) {
            $plan['taxes'] = array(
                'percentage' => $subscription['rest_tax_percentage'],
                'inclusive' => false,
            );
        }

        return $plan;
    }

    public static function buildRestSubscriptionPayload(array $subscription, $planId, $order, $subscriptionId)
    {
        $payload = array(
            'plan_id' => $planId,
            'quantity' => (string)$subscription['quantity'],
            'custom_id' => (string)$subscriptionId,
        );

        if (!empty($subscription['profilestartdate'])) {
            $payload['start_time'] = $subscription['profilestartdate'];
        }

        $subscriber = array();
        if (!empty($order->billing['firstname']) || !empty($order->billing['lastname'])) {
            $subscriber['name'] = array(
                'given_name' => self::truncate(isset($order->billing['firstname']) ? $order->billing['firstname'] : '', 60),
                'surname' => self::truncate(isset($order->billing['lastname']) ? $order->billing['lastname'] : '', 60),
            );
        }
        if (!empty($order->customer['email_address'])) {
            $subscriber['email_address'] = $order->customer['email_address'];
        }

        $address = array();
        if (!empty($order->billing['street_address'])) {
            $address['address_line_1'] = self::truncate($order->billing['street_address'], 200);
        }
        if (!empty($order->billing['suburb'])) {
            $address['address_line_2'] = self::truncate($order->billing['suburb'], 200);
        }
        if (!empty($order->billing['city'])) {
            $address['admin_area_2'] = self::truncate($order->billing['city'], 120);
        }
        if (!empty($order->billing['state'])) {
            $address['admin_area_1'] = self::truncate($order->billing['state'], 120);
        }
        if (!empty($order->billing['postcode'])) {
            $address['postal_code'] = self::truncate($order->billing['postcode'], 60);
        }
        if (!empty($order->billing['country']['iso_code_2'])) {
            $address['country_code'] = strtoupper($order->billing['country']['iso_code_2']);
        }

        if (!empty($address) && !empty($address['country_code'])) {
            $subscriber['shipping_address'] = array(
                'name' => array(
                    'full_name' => trim(self::truncate(isset($order->billing['firstname']) ? $order->billing['firstname'] : '', 60) . ' ' . self::truncate(isset($order->billing['lastname']) ? $order->billing['lastname'] : '', 60))
                ),
                'address' => $address,
            );
        }

        if (!empty($subscriber)) {
            $payload['subscriber'] = $subscriber;
        }

        $applicationContext = array(
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'SUBSCRIBE_NOW',
        );

        if (defined('STORE_NAME') && STORE_NAME !== '') {
            $applicationContext['brand_name'] = self::truncate(STORE_NAME, 127);
        }

        $payload['application_context'] = $applicationContext;

        return $payload;
    }

    protected static function truncate($value, $length)
    {
        $value = (string)$value;
        if (strlen($value) <= $length) {
            return $value;
        }
        return substr($value, 0, $length);
    }
}

