<?php

// RECURRING PAYMENTS
// check order for recurring payment products
global $db, $zco_notifier, $order;

if (!class_exists('PayPalRecurringBuilder')) {
    require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalRecurringBuilder.php');
}

$_SESSION['paypal_recurring_profile'] = array();

$currencyCode = isset($_SESSION['currency']) ? $_SESSION['currency'] : (isset($order->info['currency']) ? $order->info['currency'] : null);
$subscriptions = PayPalRecurringBuilder::buildFromOrder($order, $currencyCode);

if (sizeof($subscriptions) > 0) {
    $selectedPayment = isset($_SESSION['payment']) ? $_SESSION['payment'] : '';
    $usePaypalr = ($selectedPayment === 'paypalr');
    $PayPalRestful = null;

    if ($usePaypalr) {
        $clientId = defined('MODULE_PAYMENT_PAYPALR_CLIENT_ID') ? MODULE_PAYMENT_PAYPALR_CLIENT_ID : '';
        $clientSecret = defined('MODULE_PAYMENT_PAYPALR_CLIENT_SECRET') ? MODULE_PAYMENT_PAYPALR_CLIENT_SECRET : '';
        $environment = '';
        if (defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
            $environment = MODULE_PAYMENT_PAYPALR_SERVER;
        } elseif (defined('MODULE_PAYMENT_PAYPALR_ENVIRONMENT')) {
            $environment = MODULE_PAYMENT_PAYPALR_ENVIRONMENT;
        } elseif (defined('MODULE_PAYMENT_PAYPALR_MODE')) {
            $environment = MODULE_PAYMENT_PAYPALR_MODE;
        }
        if ($environment === '') {
            $environment = 'sandbox';
        }
        if (strlen($clientId) > 0 && strlen($clientSecret) > 0) {
            require_once(DIR_WS_MODULES . 'payment/paypal/pprAutoload.php');
            try {
                $PayPalRestful = new PayPalRestful\Api\PayPalRestfulApi($environment, $clientId, $clientSecret);
            } catch (Exception $e) {
                $PayPalRestful = null;
            }
        }
        if (!$PayPalRestful instanceof PayPalRestful\Api\PayPalRestfulApi) {
            $usePaypalr = false;
        }
    }

    if ($usePaypalr && $PayPalRestful instanceof PayPalRestful\Api\PayPalRestfulApi) {
        foreach ($subscriptions as $products_id => $subscription) {
            if (!((int)$subscription['totalbillingcycles'] > 1 || (int)$subscription['totalbillingcycles'] === 0)) {
                continue;
            }

            if (!isset($subscription['taxamt'])) {
                $subscription['taxamt'] = 0;
            }

            $sql_data_array = array(
                'customers_id' => $_SESSION['customer_id'],
                'amount' => $subscription['amt'],
                'products_id' => (int)$products_id,
                'quantity' => $subscription['quantity'],
                'billingperiod' => $subscription['billingperiod'],
                'billingfrequency' => (int)$subscription['billingfrequency'],
                'totalbillingcycles' => (int)$subscription['totalbillingcycles'],
                'expiration_date' => $subscription['expiration_date'],
                'currencycode' => $subscription['currencycode']
            );
            zen_db_perform(TABLE_PAYPAL_RECURRING, $sql_data_array);
            $subscription_id = $db->Insert_ID();

            $requestLog = array();
            $productPayload = PayPalRecurringBuilder::buildRestProductPayload($subscription);
            $requestLog['product'] = $productPayload;
            $productResult = $PayPalRestful->createProduct($productPayload);
            $productId = (is_array($productResult) && isset($productResult['id'])) ? $productResult['id'] : '';
            if ($productId === '') {
                $requestLog['error'] = $PayPalRestful->getErrorInfo();
                $email_msg = 'Paypal Recurring subscription failed.  Please see details below:  subscription id: ' . $subscription_id . ' paypal request: ' . json_encode($requestLog);
                $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                zen_mail('Store admin', STORE_OWNER_EMAIL_ADDRESS, 'Paypal Recurring - subscription failed', $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
                $db->Execute("DELETE FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . (int)$subscription_id . " LIMIT 1;");
                continue;
            }

            $planName = $subscription['desc'] . ' Plan #' . $subscription_id;
            $planPayload = PayPalRecurringBuilder::buildRestPlanPayload($subscription, $productId, $planName);
            $requestLog['plan'] = $planPayload;
            $planResult = $PayPalRestful->createPlan($planPayload);
            $planId = (is_array($planResult) && isset($planResult['id'])) ? $planResult['id'] : '';
            if ($planId === '') {
                $requestLog['plan_response'] = $planResult;
                $requestLog['error'] = $PayPalRestful->getErrorInfo();
                $email_msg = 'Paypal Recurring subscription failed.  Please see details below:  subscription id: ' . $subscription_id . ' paypal request: ' . json_encode($requestLog);
                $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                zen_mail('Store admin', STORE_OWNER_EMAIL_ADDRESS, 'Paypal Recurring - subscription failed', $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
                $db->Execute("DELETE FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . (int)$subscription_id . " LIMIT 1;");
                continue;
            }

            if (!isset($planResult['status']) || strtoupper($planResult['status']) !== 'ACTIVE') {
                $PayPalRestful->activatePlan($planId);
            }

            $subscriptionPayload = PayPalRecurringBuilder::buildRestSubscriptionPayload($subscription, $planId, $order, $subscription_id);
            $requestLog['subscription'] = $subscriptionPayload;
            $PayPalResult = $PayPalRestful->createSubscription($subscriptionPayload);
            $profile_id = (is_array($PayPalResult) && isset($PayPalResult['id'])) ? $PayPalResult['id'] : '';
            if ($profile_id && $subscription_id) {
                $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET profile_id = '" . zen_db_input($profile_id) . "' WHERE subscription_id = " . (int)$subscription_id . " LIMIT 1;");
                $db->Execute("UPDATE " . TABLE_ORDERS_PRODUCTS . " SET profile_id = '" . zen_db_input($profile_id) . "' WHERE products_prid = '" . zen_db_input($products_id) . "' LIMIT 1;");
                $_SESSION['paypal_recurring_profile'][] = array('profile_id' => $profile_id, 'products_id' => $products_id);
            } else {
                $requestLog['response'] = $PayPalResult;
                $requestLog['error'] = $PayPalRestful->getErrorInfo();
                $email_msg = 'Paypal Recurring subscription failed.  Please see details below:  subscription id: ' . $subscription_id . ' paypal request: ' . json_encode($requestLog);
                $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                zen_mail('Store admin', STORE_OWNER_EMAIL_ADDRESS, 'Paypal Recurring - subscription failed', $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
                $db->Execute("DELETE FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . (int)$subscription_id . " LIMIT 1;");
            }

            if (isset($_SESSION['cancel_profile'])) {
                $subscription_record = $db->Execute("SELECT profile_id, subscription_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int) $_SESSION['customer_id'] . "
                                      AND profile_id = '" . zen_db_input($_SESSION['cancel_profile']) . "'
                                      LIMIT 1;");
                if ($subscription_record->RecordCount() > 0) {
                    $cancelResult = $PayPalRestful->cancelSubscription($subscription_record->fields['profile_id'], 'Cancelled by customer.');
                    if ($cancelResult !== false) {
                        $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Cancelled' WHERE subscription_id = " . (int) $subscription_record->fields['subscription_id'] . " LIMIT 1;");
                        unset($_SESSION['cancel_profile']);
                    }
                }
            }
            $zco_notifier->notify('NOTIFY_PAYPAL_WPP_RECURRING_CREATED');
        }
    } else {
        require_once(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
        $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
        $PayPal = new PayPal($PayPalConfig);
        if (!$paypalDPRecurring) {
            $CRPPFields = array('token' => $_SESSION['paypal_ec_token']);
        }
        $CCDetails = array('creditcardtype' => $cc_type,
        'acct' => $cc_number,
        'expdate' => $cc_expdate_month . $cc_expdate_year,
        'cvv2' => $cc_checkcode,
        'startdate' => $cc_issuedate_month . $cc_issuedate_year,
        'issuenumber' => $cc_issuenumber
        );
        $PayerInfo = array('email' => $order->customer['email_address'],
        'payerid' => '',
        'payerstatus' => '',
        'countrycode' => $order->billing['country']['iso_code_2'],
        'business' => $order->customer['company']
        );
        $PayerName = array('salutation' => '',
        'firstname' => $_POST['wpp_payer_firstname'],
        'middlename' => '',
        'lastname' => $_POST['wpp_payer_lastname'],
        'suffix' => ''
        );
        $BillingAddress = array('street' => $order->billing['street_address'],
        'street2' => $order->billing['suburb'],
        'city' => $order->billing['city'],
        'state' => $order->billing['state'],
        'countrycode' => $order->billing['country']['iso_code_2'],
        'zip' => $order->billing['postcode'],
        'phonenum' => $order->customer['telephone']
        );
        foreach ($subscriptions as $products_id => $subscription) {
            if ((int) $subscription['totalbillingcycles'] > 1 || (int) $subscription['totalbillingcycles'] === 0) {
                $profilestartdate = $subscription['profilestartdate'];
                if ($profilestartdate === '') {
                    $profilestartdate = date('Y-m-d\T00:00:00\Z');
                }
                $end_date = $subscription['expiration_date'];
                $sql_data_array = array('customers_id' => $_SESSION['customer_id'], 'amount' => $subscription['amt'], 'products_id' => (int)$products_id, 'quantity' => $subscription['quantity'], 'billingperiod' => $subscription['billingperiod'], 'billingfrequency' => (int) $subscription['billingfrequency'], 'totalbillingcycles' => (int) $subscription['totalbillingcycles'], 'expiration_date' => $end_date, 'currencycode' => $subscription['currencycode']);
                zen_db_perform(TABLE_PAYPAL_RECURRING, $sql_data_array);
                $subscription_id = $db->Insert_ID();
                $ScheduleDetails = array('desc' => zen_get_products_name((int) $products_id),
                'maxfailedpayments' => '',
                'autobillamt' => 'AddToNextBilling'
                );
                $ProfileDetails = array('subscribername' => $_POST['wpp_payer_firstname'] . ' ' . $_POST['wpp_payer_lastname'],
                'profilestartdate' => $profilestartdate,
                'profilereference' => $subscription_id
                );
                $BillingPeriod = array('trialbillingperiod' => '', 'trialbillingfrequency' => '', 'trialtotalbillingcycles' => '', 'trialamt' => '', 'billingperiod' => $subscription['billingperiod'],
                'billingfrequency' => (int) $subscription['billingfrequency'],
                'totalbillingcycles' => (((int) $subscription['totalbillingcycles'] > 1) ? (int) $subscription['totalbillingcycles'] - 1 : (int) $subscription['totalbillingcycles']),
                'amt' => $subscription['amt'],
                'currencycode' => $subscription['currencycode'],
                'shippingamt' => '',
                'taxamt' => $subscription['taxamt']
                );
                $PayPalRequestData = array('CRPPFields' => $CRPPFields, 'ProfileDetails' => $ProfileDetails, 'ScheduleDetails' => $ScheduleDetails, 'BillingPeriod' => $BillingPeriod, 'CCDetails' => $CCDetails, 'PayerInfo' => $PayerInfo, 'PayerName' => $PayerName);
                $PayPalResult = $PayPal->CreateRecurringPaymentsProfile($PayPalRequestData);
                $profile_id = $PayPalResult['PROFILEID'];
                if ($profile_id && $subscription_id) {
                    $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET profile_id = '" . $profile_id . "' WHERE subscription_id = " . $subscription_id . " LIMIT 1;");
                    $db->Execute("UPDATE " . TABLE_ORDERS_PRODUCTS . " SET profile_id = '" . $profile_id . "' WHERE products_prid ='" . $products_id . "' LIMIT 1;");
                    $_SESSION['paypal_recurring_profile'][] = array('profile_id' => $profile_id, 'products_id' => $products_id);
                } elseif ($subscription_id) {
                    $temp_request = $PayPalRequestData;
                    $temp_request['CCDetails']['acct'] = '<removed for security>';
                    $email_msg = 'Paypal Recurring subscription failed.  Please see details below:  subscription id: ' . $subscription_id . ' paypal request: ' . json_encode($temp_request) . ' response: ' . json_encode($PayPalResult);
                    $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                    zen_mail('Store admin', STORE_OWNER_EMAIL_ADDRESS, 'Paypal Recurring - subscription failed', $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
                    $db->Execute("DELETE FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . $subscription_id . " LIMIT 1;");
                }
                if (isset($_SESSION['cancel_profile'])) {
                    $subscription = $db->Execute("SELECT profile_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int) $_SESSION['customer_id'] . "
                                      AND profile_id = '" . $_SESSION['cancel_profile'] . "'
                                      LIMIT 1;");
                    if ($subscription->RecordCount() > 0) {
                        $data = array();
                        $data['MRPPSFields'] = array('PROFILEID' => $subscription->fields['profile_id'], 'ACTION' => 'Cancel', 'NOTE' => 'Cancelled by customer.');
                        $retval = $PayPal->ManageRecurringPaymentsProfileStatus($data);
                        if (sizeof($retval['ERRORS']) <= 0) {
                            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Cancelled' WHERE subscription_id = " . (int) $subscription->fields['subscription_id'] . " LIMIT 1;");
                            unset($_SESSION['cancel_profile']);
                        }
                    }
                }
                $zco_notifier->notify('NOTIFY_PAYPAL_WPP_RECURRING_CREATED');
            }
        }
    }
}
// END RECURRING PAYMENTS
