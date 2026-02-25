<?php
/**
 * PayPal WPP Recurring Reminders Cron
 * 
 * Sends renewal reminders, payment reminders, and expiration notices for subscriptions.
 * Supports both legacy PayPal recurring profiles and REST API subscriptions.
 * 
 * Compatible with:
 * - paypalwpp.php (Website Payments Pro)
 * - paypaldp.php (Direct Payments)
 * - paypalac.php (REST API)
 * - payflow.php (Payflow)
 */

require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once 'includes/application_top.php';

// Load PayPal Advanced Checkout autoloader
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';

// Load saved card recurring class
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php';

// Load PayPalProfileManager if available
if (file_exists(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php')) {
    require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php';
}

// Define default configuration values if not set
if (!defined('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER')) {
    define('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER', 0);
}
if (!defined('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER')) {
    define('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER', 0);
}

// Define email templates if not set
if (!defined('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL')) {
    define('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL', "Dear %s,\n\nThis is a reminder that your subscription for %s is set to expire in %d days on %s.\n\nTo renew your subscription, please visit: %s\n\nThank you for your business.");
}
if (!defined('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_SUBJECT')) {
    define('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_SUBJECT', 'Subscription Renewal Reminder - %s');
}
if (!defined('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT')) {
    define('PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT', "Dear %s,\n\nThis is a reminder that your subscription is set to expire in %d days on %s.\n\nPlease contact us for renewal options.\n\nThank you for your business.");
}
if (!defined('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL')) {
    define('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL', "Dear %s,\n\nThis is a reminder that your subscription payment for %s will be processed in %d days on %s.\n\nTo view your subscription details, please visit: %s\n\nThank you for your business.");
}
if (!defined('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT')) {
    define('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT', 'Upcoming Subscription Payment - %s');
}
if (!defined('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_INVALID_PRODUCT')) {
    define('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_INVALID_PRODUCT', "Dear %s,\n\nThis is a reminder that your subscription payment will be processed in %d days on %s.\n\nPlease contact us for details.\n\nThank you for your business.");
}
if (!defined('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE')) {
    define('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE', "Dear %s,\n\nYour subscription for %s has expired today.\n\nTo renew your subscription, please visit: %s\n\nThank you for your business.");
}
if (!defined('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_EMAIL_SUBJECT')) {
    define('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_EMAIL_SUBJECT', 'Subscription Expired - %s');
}
if (!defined('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_INVALID_PRODUCT')) {
    define('PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_INVALID_PRODUCT', "Dear %s,\n\nYour subscription has expired today.\n\nPlease contact us for renewal options.\n\nThank you for your business.");
}

$log = [];
$remindersProcessed = 0;
$paymentsReminded = 0;
$expiredNotices = 0;

/**
 * Process legacy PayPal recurring subscriptions
 */
if (defined('TABLE_PAYPAL_RECURRING')) {
    $subscription = $db->Execute("SELECT * FROM " . TABLE_PAYPAL_RECURRING . " ORDER BY subscription_id ASC;");
    
    if ($subscription->RecordCount() > 0) {
        // Initialize PayPal API if available
        $PayPal = null;
        $PayPalProfileManager = null;
        
        if (defined('MODULE_PAYMENT_PAYPALWPP_STATUS') && MODULE_PAYMENT_PAYPALWPP_STATUS === 'True') {
            if (file_exists(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php')) {
                require_once DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php';
                $PayPalConfig = array(
                    'Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox'),
                    'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME,
                    'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD,
                    'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE
                );
                if (class_exists('PayPal')) {
                    $PayPal = new PayPal($PayPalConfig);
                }
            }
        }
        
        // Initialize PayPalProfileManager
        if (class_exists('PayPalProfileManager')) {
            $paypalSavedCardRecurring = new paypalSavedCardRecurring();
            $PayPalApiClient = $paypalSavedCardRecurring->get_paypal_api_client();
            $PayPalProfileManager = PayPalProfileManager::create($PayPalApiClient, $PayPal);
        }
        
        while (!$subscription->EOF) {
            $profile = [];
            $currentStatus = '';
            
            // Get profile status from PayPalProfileManager or legacy API
            if ($PayPalProfileManager !== null) {
                $statusResult = $PayPalProfileManager->getProfileStatus($subscription->fields);
                $statusResult = is_array($statusResult) ? $statusResult : [];
                
                if (!empty($statusResult['success']) && isset($statusResult['profile']) && is_array($statusResult['profile'])) {
                    $profile = $statusResult['profile'];
                    $currentStatus = isset($statusResult['status']) ? $statusResult['status'] : '';
                }
            } elseif ($PayPal !== null) {
                $data = ['GRPPDFields' => ['PROFILEID' => $subscription->fields['profile_id']]];
                $legacyProfile = $PayPal->GetRecurringPaymentsProfileDetails($data);
                if (is_array($legacyProfile) && !isset($legacyProfile['ERRORS'])) {
                    $profile = $legacyProfile;
                    $currentStatus = isset($profile['STATUS']) ? $profile['STATUS'] : '';
                }
            }
            
            if ($currentStatus === '') {
                $currentStatus = $subscription->fields['status'];
            }
            
            // Update status if changed
            if ($subscription->fields['status'] != $currentStatus) {
                $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = '" . zen_db_input($currentStatus) . "' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
            }
            
            $normalizedStatus = strtoupper($currentStatus);
            
            // Renewal reminders
            if (PAYPAL_WPP_RECURRING_RENEWAL_REMINDER > 0 && $normalizedStatus == 'ACTIVE') {
                $expiration_date = strtotime($subscription->fields['expiration_date']);
                if ($subscription->fields['expiration_date'] > 0 && 
                    (date('Y-m-d') == date('Y-m-d', strtotime('-' . (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER . ' days', $expiration_date))) && 
                    $subscription->fields['reminded'] != date('Y-m-d')) {
                    
                    $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");
                    if ($customer->RecordCount() > 0) {
                        $products_id = $subscription->fields['products_id'];
                        $products_name = zen_get_products_name($products_id);
                        if ($products_name) {
                            $email_msg = sprintf(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL, 
                                $customer->fields['customers_firstname'], 
                                addslashes($products_name), 
                                (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER, 
                                $subscription->fields['expiration_date'], 
                                zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
                        } else {
                            $email_msg = sprintf(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT, 
                                $customer->fields['customers_firstname'], 
                                (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER, 
                                $subscription->fields['expiration_date']);
                        }
                        $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                        zen_mail(
                            $customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], 
                            $customer->fields['customers_email_address'], 
                            sprintf(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_SUBJECT, $subscription->fields['profile_id']), 
                            $email_msg, 
                            STORE_NAME, 
                            EMAIL_FROM, 
                            $html_msg, 
                            'default'
                        );
                        $remindersProcessed++;
                        $log[] = "Sent renewal reminder for subscription #" . $subscription->fields['subscription_id'];
                    }
                    $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET reminded = now() WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
                }
            }
            
            // Payment reminders
            if (PAYPAL_WPP_RECURRING_PAYMENT_REMINDER > 0 && $normalizedStatus == 'ACTIVE') {
                $nextBillingRaw = '';
                if (isset($profile['NEXTBILLINGDATE'])) {
                    $nextBillingRaw = $profile['NEXTBILLINGDATE'];
                } elseif (isset($profile['billing_info']['next_billing_time'])) {
                    $nextBillingRaw = $profile['billing_info']['next_billing_time'];
                } elseif (isset($profile['billing_info']['cycle_executions'][0]['next_billing_time'])) {
                    $nextBillingRaw = $profile['billing_info']['cycle_executions'][0]['next_billing_time'];
                } elseif (isset($profile['next_billing_time'])) {
                    $nextBillingRaw = $profile['next_billing_time'];
                }
                
                $nextBillingRaw = is_string($nextBillingRaw) ? $nextBillingRaw : '';
                $nextDateParts = strlen($nextBillingRaw) > 0 ? explode('T', str_replace('Z', '', $nextBillingRaw)) : [''];
                $next_date = (strlen($nextDateParts[0]) > 0) ? strtotime($nextDateParts[0]) : false;
                
                if ($next_date && date('Y-m-d') == date('Y-m-d', strtotime('-' . (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER . ' days', $next_date))) {
                    $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");
                    if ($customer->RecordCount() > 0) {
                        $products_id = $subscription->fields['products_id'];
                        $products_name = zen_get_products_name($products_id);
                        if ($products_name) {
                            $email_msg = sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL, 
                                $customer->fields['customers_firstname'], 
                                addslashes($products_name), 
                                (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER, 
                                date('m-d-Y', $next_date), 
                                zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
                        } else {
                            $email_msg = sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_INVALID_PRODUCT, 
                                $customer->fields['customers_firstname'], 
                                (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER, 
                                date('m-d-Y', $next_date));
                        }
                        $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                        zen_mail(
                            $customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], 
                            $customer->fields['customers_email_address'], 
                            sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT, $subscription->fields['profile_id']), 
                            $email_msg, 
                            STORE_NAME, 
                            EMAIL_FROM, 
                            $html_msg, 
                            'default'
                        );
                        $paymentsReminded++;
                        $log[] = "Sent payment reminder for subscription #" . $subscription->fields['subscription_id'];
                    }
                    $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET reminded = now() WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
                }
            }
            
            // Expiration notices
            $expiration_date = strtotime($subscription->fields['expiration_date']);
            if ($expiration_date && date('Y-m-d') == date('Y-m-d', $expiration_date)) {
                $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");
                if ($customer->RecordCount() > 0) {
                    $products_id = $subscription->fields['products_id'];
                    $products_name = zen_get_products_name($products_id);
                    if ($products_name) {
                        $email_msg = sprintf(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE, 
                            $customer->fields['customers_firstname'], 
                            addslashes($products_name), 
                            zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
                    } else {
                        $email_msg = sprintf(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_INVALID_PRODUCT, 
                            $customer->fields['customers_firstname']);
                    }
                    $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                    zen_mail(
                        $customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], 
                        $customer->fields['customers_email_address'], 
                        sprintf(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_EMAIL_SUBJECT, $subscription->fields['profile_id']), 
                        $email_msg, 
                        STORE_NAME, 
                        EMAIL_FROM, 
                        $html_msg, 
                        'default'
                    );
                    $expiredNotices++;
                    $log[] = "Sent expiration notice for subscription #" . $subscription->fields['subscription_id'];
                    
                    // Notify observers
                    if (isset($zco_notifier)) {
                        $zco_notifier->notify('NOTIFY_PAYPAL_WPP_RECURRING_EXPIRED');
                    }
                }
            }
            
            $subscription->MoveNext();
        }
    }
}

/**
 * Process REST API subscriptions (TABLE_PAYPAL_SUBSCRIPTIONS)
 */
if (defined('TABLE_PAYPAL_SUBSCRIPTIONS')) {
    $restSubscriptions = $db->Execute("SELECT ps.*, c.customers_firstname, c.customers_lastname, c.customers_email_address 
        FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " ps 
        LEFT JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = ps.customers_id 
        WHERE ps.status IN ('active', 'scheduled') 
        ORDER BY ps.paypal_subscription_id ASC;");
    
    if ($restSubscriptions->RecordCount() > 0) {
        while (!$restSubscriptions->EOF) {
            $row = $restSubscriptions->fields;
            
            // Check for next billing date from attributes
            $nextBillingDate = null;
            if (!empty($row['attributes'])) {
                $attributes = json_decode($row['attributes'], true);
                if (is_array($attributes) && isset($attributes['next_billing_date'])) {
                    $nextBillingDate = strtotime($attributes['next_billing_date']);
                }
            }
            
            // Payment reminders for REST subscriptions
            if (PAYPAL_WPP_RECURRING_PAYMENT_REMINDER > 0 && $nextBillingDate) {
                if (date('Y-m-d') == date('Y-m-d', strtotime('-' . (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER . ' days', $nextBillingDate))) {
                    if (!empty($row['customers_email_address'])) {
                        $products_name = $row['products_name'];
                        $email_msg = sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL, 
                            $row['customers_firstname'], 
                            addslashes($products_name), 
                            (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER, 
                            date('m-d-Y', $nextBillingDate), 
                            zen_href_link(FILENAME_ACCOUNT));
                        
                        $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
                        zen_mail(
                            $row['customers_firstname'] . ' ' . $row['customers_lastname'], 
                            $row['customers_email_address'], 
                            sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT, 'REST-' . $row['paypal_subscription_id']), 
                            $email_msg, 
                            STORE_NAME, 
                            EMAIL_FROM, 
                            $html_msg, 
                            'default'
                        );
                        $paymentsReminded++;
                        $log[] = "Sent payment reminder for REST subscription #" . $row['paypal_subscription_id'];
                    }
                }
            }
            
            $restSubscriptions->MoveNext();
        }
    }
}

// Output results
echo "PayPal Recurring Payment Reminders Cron Executed Successfully\n";
echo "Renewal reminders sent: " . $remindersProcessed . "\n";
echo "Payment reminders sent: " . $paymentsReminded . "\n";
echo "Expiration notices sent: " . $expiredNotices . "\n";

if (!empty($log)) {
    echo "\nLog:\n";
    foreach ($log as $entry) {
        echo "- " . $entry . "\n";
    }
}

require_once 'includes/application_bottom.php';
