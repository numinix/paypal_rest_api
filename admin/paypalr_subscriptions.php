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
    $subscriptionType = trim((string) ($_POST['subscription_type'] ?? 'rest'));
    $subscriptionId = (int) zen_db_prepare_input($_POST['paypal_subscription_id'] ?? 0);
    $customersId = (int) zen_db_prepare_input($_POST['customers_id'] ?? 0);
    $redirectQuery = trim((string) ($_POST['redirect_query'] ?? ''));
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);

    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_MISSING_IDENTIFIER, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Route to saved card handler for saved card subscriptions
    if ($subscriptionType === 'savedcard' && class_exists('paypalSavedCardRecurring')) {
        $paypalSavedCardRecurring = new paypalSavedCardRecurring();
        
        // Handle status change for saved card
        if (isset($_POST['set_status']) && $_POST['set_status'] !== '') {
            $status = strtolower(trim((string) zen_db_prepare_input($_POST['set_status'])));
            $paypalSavedCardRecurring->update_payment_status($subscriptionId, $status, 'Status updated by admin');
            $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_STATUS_UPDATED, $subscriptionId, $status), 'success');
            zen_redirect($redirectUrl);
        }
        
        // Handle full update for saved card
        $updateData = [];
        if (isset($_POST['amount'])) {
            $updateData['amount'] = (float) zen_db_prepare_input($_POST['amount']);
        }
        if (isset($_POST['next_payment_date']) && $_POST['next_payment_date'] !== '') {
            $updateData['date'] = zen_db_prepare_input($_POST['next_payment_date']);
        }
        if (isset($_POST['products_id'])) {
            $updateData['product'] = (int) zen_db_prepare_input($_POST['products_id']);
        }
        if (isset($_POST['saved_credit_card_id'])) {
            $updateData['saved_credit_card_id'] = (int) zen_db_prepare_input($_POST['saved_credit_card_id']);
        }
        
        // Add billing address fields
        $billingFields = ['billing_name', 'billing_company', 'billing_street_address', 'billing_suburb',
                         'billing_city', 'billing_state', 'billing_postcode', 'billing_country_code'];
        foreach ($billingFields as $field) {
            if (isset($_POST[$field])) {
                $updateData[$field] = zen_db_prepare_input($_POST[$field]);
            }
        }
        
        // Handle billing_country_id from billing_country_code
        if (isset($_POST['billing_country_code']) && $_POST['billing_country_code'] !== '') {
            $countryCode = strtoupper(zen_db_prepare_input($_POST['billing_country_code']));
            $countryQuery = $db->Execute(
                "SELECT countries_id FROM " . TABLE_COUNTRIES . " 
                 WHERE countries_iso_code_2 = '" . zen_db_input($countryCode) . "' LIMIT 1"
            );
            if (!$countryQuery->EOF) {
                $updateData['billing_country_id'] = (int)$countryQuery->fields['countries_id'];
            }
        }
        
        // Add shipping fields
        if (isset($_POST['shipping_method'])) {
            $updateData['shipping_method'] = zen_db_prepare_input($_POST['shipping_method']);
        }
        if (isset($_POST['shipping_cost'])) {
            $updateData['shipping_cost'] = (float) zen_db_prepare_input($_POST['shipping_cost']);
        }
        
        if (!empty($updateData)) {
            $updateData['comments'] = 'Updated by admin via unified subscriptions page.';
            $paypalSavedCardRecurring->update_payment_info($subscriptionId, $updateData);
            $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_UPDATED, $subscriptionId), 'success');
        }
        
        zen_redirect($redirectUrl);
    }

    $planId = substr((string) zen_db_prepare_input($_POST['plan_id'] ?? ''), 0, 64);
    $productsId = (int) zen_db_prepare_input($_POST['products_id'] ?? 0);
    $productsName = (string) zen_db_prepare_input($_POST['products_name'] ?? '');
    $productsQuantity = (float) zen_db_prepare_input($_POST['products_quantity'] ?? 1);
    $billingPeriod = strtoupper(str_replace([' ', "\t"], '_', (string) zen_db_prepare_input($_POST['billing_period'] ?? '')));
    $billingFrequency = (int) zen_db_prepare_input($_POST['billing_frequency'] ?? 0);
    $totalCycles = (int) zen_db_prepare_input($_POST['total_billing_cycles'] ?? 0);
    
    // Validate and sanitize next_payment_date
    $nextPaymentDate = (string) zen_db_prepare_input($_POST['next_payment_date'] ?? '');
    if ($nextPaymentDate !== '') {
        // Validate date format (YYYY-MM-DD)
        $dateValidation = DateTime::createFromFormat('Y-m-d', $nextPaymentDate);
        if (!$dateValidation || $dateValidation->format('Y-m-d') !== $nextPaymentDate) {
            $messageStack->add_session(ERROR_SUBSCRIPTION_INVALID_DATE_FORMAT, 'error');
            zen_redirect($redirectUrl);
        }
    }
    
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
        // For quick status changes, only update the status field without validating other fields
        zen_db_perform(
            TABLE_PAYPAL_SUBSCRIPTIONS,
            ['status' => $status, 'last_modified' => date('Y-m-d H:i:s')],
            'update',
            'paypal_subscription_id = ' . (int) $subscriptionId
        );
        
        $messageStack->add_session(
            sprintf(SUCCESS_SUBSCRIPTION_STATUS_UPDATED, $subscriptionId, $status),
            'success'
        );
        
        zen_redirect($redirectUrl);
    }

    $attributesEncoded = '';
    $rawAttributes = trim((string) ($_POST['attributes'] ?? ''));
    if ($rawAttributes !== '') {
        $decodedAttributes = json_decode($rawAttributes, true);
        if ($decodedAttributes === null && json_last_error() !== JSON_ERROR_NONE) {
            $messageStack->add_session(ERROR_SUBSCRIPTION_INVALID_JSON, 'error');
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
        'next_payment_date' => $nextPaymentDate !== '' ? $nextPaymentDate : null,
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
            $messageStack->add_session(ERROR_SUBSCRIPTION_VAULT_NOT_FOUND, 'error');
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

    // Try to update via PayPal API if plan_id exists
    if (!empty($planId)) {
        $profileManager = paypalr_get_profile_manager();
        if ($profileManager !== null) {
            try {
                $apiUpdateData = [];
                
                // Update billing amount if changed
                if ($amount > 0) {
                    $apiUpdateData['amount'] = $amount;
                    if (!empty($currencyCode)) {
                        $apiUpdateData['currency_code'] = $currencyCode;
                    }
                }
                
                // Update next billing date if provided
                if (!empty($nextPaymentDate)) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $nextPaymentDate);
                    if ($dateObj) {
                        $apiUpdateData['next_billing_date'] = $dateObj->format('Y-m-d\TH:i:s\Z');
                    }
                }
                
                if (!empty($apiUpdateData)) {
                    $apiUpdateData['profile_id'] = $planId;
                    $profileManager->updateProfile($apiUpdateData);
                }
            } catch (Exception $e) {
                // Log but don't fail - local status is already updated
                error_log('Failed to update PayPal subscription: ' . $e->getMessage());
            }
        }
    }

    $messageStack->add_session(
        sprintf(SUCCESS_SUBSCRIPTION_UPDATED, $subscriptionId),
        'success'
    );

    zen_redirect($redirectUrl);
}

// Cancel subscription action
if ($action === 'cancel_subscription') {
    $subscriptionType = trim((string) ($_GET['subscription_type'] ?? 'rest'));
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id', 'subscription_type']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_CANCEL_MISSING_ID, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Route to saved card handler
    if ($subscriptionType === 'savedcard' && class_exists('paypalSavedCardRecurring')) {
        $paypalSavedCardRecurring = new paypalSavedCardRecurring();
        $paypalSavedCardRecurring->update_payment_status($subscriptionId, 'cancelled', 'Cancelled by admin');
        
        $subscription = $paypalSavedCardRecurring->get_payment_details($subscriptionId);
        if ($subscription && method_exists($paypalSavedCardRecurring, 'remove_group_pricing')) {
            $paypalSavedCardRecurring->remove_group_pricing($subscription['customers_id'], $subscription['products_id']);
        }
        
        $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_CANCELLED, $subscriptionId), 'success');
        zen_redirect($redirectUrl);
    }
    
    // Update local status for REST subscriptions
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
    
    $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_CANCELLED, $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Suspend subscription action
if ($action === 'suspend_subscription') {
    $subscriptionType = trim((string) ($_GET['subscription_type'] ?? 'rest'));
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id', 'subscription_type']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_SUSPEND_MISSING_ID, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Route to saved card handler
    if ($subscriptionType === 'savedcard' && class_exists('paypalSavedCardRecurring')) {
        $paypalSavedCardRecurring = new paypalSavedCardRecurring();
        $paypalSavedCardRecurring->update_payment_status($subscriptionId, 'suspended', 'Suspended by admin');
        
        $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_SUSPENDED, $subscriptionId), 'success');
        zen_redirect($redirectUrl);
    }
    
    // Update local status for REST subscriptions
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
    
    $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_SUSPENDED, $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Reactivate subscription action
if ($action === 'reactivate_subscription') {
    $subscriptionType = trim((string) ($_GET['subscription_type'] ?? 'rest'));
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id', 'subscription_type']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_REACTIVATE_MISSING_ID, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Route to saved card handler
    if ($subscriptionType === 'savedcard' && class_exists('paypalSavedCardRecurring')) {
        $paypalSavedCardRecurring = new paypalSavedCardRecurring();
        $paypalSavedCardRecurring->update_payment_status($subscriptionId, 'scheduled', 'Re-activated by admin');
        
        $subscription = $paypalSavedCardRecurring->get_payment_details($subscriptionId);
        if ($subscription && method_exists($paypalSavedCardRecurring, 'create_group_pricing')) {
            $paypalSavedCardRecurring->create_group_pricing($subscription['products_id'], $subscription['customers_id']);
        }
        
        $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_REACTIVATED, $subscriptionId), 'success');
        zen_redirect($redirectUrl);
    }
    
    // Update local status for REST subscriptions
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
    
    $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_REACTIVATED, $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Skip next payment action
if ($action === 'skip_next_payment') {
    $subscriptionType = trim((string) ($_GET['subscription_type'] ?? 'rest'));
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id', 'subscription_type']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session( 'Unable to skip payment. Missing identifier.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Route to saved card handler
    if ($subscriptionType === 'savedcard' && class_exists('paypalSavedCardRecurring')) {
        $paypalSavedCardRecurring = new paypalSavedCardRecurring();
        $success = $paypalSavedCardRecurring->skip_next_payment($subscriptionId);
        if ($success) {
            $messageStack->add_session('Payment skipped for subscription #' . $subscriptionId . '. The next billing date has been calculated and updated.', 'success');
        } else {
            $messageStack->add_session('Failed to skip payment for subscription #' . $subscriptionId . '. Only scheduled subscriptions can be skipped.', 'error');
        }
        zen_redirect($redirectUrl);
    }
    
    // Get current subscription details for REST subscriptions
    $subscription = $db->Execute(
        "SELECT status, billing_period, billing_frequency, next_payment_date, attributes, plan_id 
         FROM " . TABLE_PAYPAL_SUBSCRIPTIONS . " 
         WHERE paypal_subscription_id = " . (int) $subscriptionId
    );
    
    if ($subscription->RecordCount() == 0) {
        $messageStack->add_session( 'Subscription not found.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Only allow skipping active subscriptions
    if ($subscription->fields['status'] !== 'active') {
        $messageStack->add_session( 'Only active subscriptions can be skipped.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Extract billing information
    $billingPeriod = $subscription->fields['billing_period'];
    $billingFrequency = (int)$subscription->fields['billing_frequency'];
    
    // Get attributes if needed
    if ((!$billingPeriod || $billingFrequency <= 0) && !empty($subscription->fields['attributes'])) {
        $attributes = json_decode($subscription->fields['attributes'], true);
        if (is_array($attributes)) {
            if (!$billingPeriod && isset($attributes['billingperiod'])) {
                $billingPeriod = $attributes['billingperiod'];
            }
            if ($billingFrequency <= 0 && isset($attributes['billingfrequency'])) {
                $billingFrequency = (int)$attributes['billingfrequency'];
            }
        }
    }
    
    // Validate we have billing info
    if (!$billingPeriod || $billingFrequency <= 0) {
        $messageStack->add_session( 'Cannot skip payment: missing billing schedule information.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Get current scheduled date
    $currentDate = $subscription->fields['next_payment_date'];
    if (!$currentDate) {
        $currentDate = date('Y-m-d');
    }
    
    $baseDate = DateTime::createFromFormat('Y-m-d', $currentDate);
    if (!$baseDate) {
        $baseDate = new DateTime('today');
    }
    $baseDate->setTime(0, 0, 0);
    
    // Calculate next billing date
    $period = strtolower(trim((string)$billingPeriod));
    $frequency = $billingFrequency;
    
    $nextDate = clone $baseDate;
    try {
        switch ($period) {
            case 'day':
            case 'daily':
                $nextDate->add(new DateInterval('P' . $frequency . 'D'));
                break;
            case 'week':
            case 'weekly':
                $nextDate->add(new DateInterval('P' . $frequency . 'W'));
                break;
            case 'semimonth':
            case 'semi-month':
            case 'semi monthly':
            case 'semi-monthly':
            case 'bi-weekly':
            case 'bi weekly':
                $days = max(1, $frequency * 15);
                $nextDate->add(new DateInterval('P' . $days . 'D'));
                break;
            case 'month':
            case 'monthly':
                $nextDate->add(new DateInterval('P' . $frequency . 'M'));
                break;
            case 'year':
            case 'yearly':
                $nextDate->add(new DateInterval('P' . $frequency . 'Y'));
                break;
            default:
                $nextDate->modify('+' . $frequency . ' ' . $period);
                break;
        }
    } catch (Exception $e) {
        $messageStack->add_session( 'Failed to calculate next payment date.', 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update the next payment date locally
    $newDate = $nextDate->format('Y-m-d');
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['next_payment_date' => $newDate, 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    // Try to update via PayPal API if plan_id exists
    if (!empty($subscription->fields['plan_id'])) {
        $profileManager = paypalr_get_profile_manager();
        if ($profileManager !== null) {
            try {
                // Update the subscription's next billing date via PayPal API
                $profileManager->updateProfile([
                    'profile_id' => $subscription->fields['plan_id'],
                    'next_billing_date' => $nextDate->format('Y-m-d\TH:i:s\Z')
                ]);
            } catch (Exception $e) {
                // Log but don't fail - local status is already updated
                error_log('Failed to update PayPal subscription billing date: ' . $e->getMessage());
            }
        }
    }
    
    $messageStack->add_session( sprintf('Payment skipped for subscription #%d. Next payment date updated to %s.', $subscriptionId, $newDate), 'success');
    zen_redirect($redirectUrl);
}

// Archive subscription action
if ($action === 'archive_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_ARCHIVE_MISSING_ID, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update local record to mark as archived
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['is_archived' => 1, 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_ARCHIVED, $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Unarchive subscription action
if ($action === 'unarchive_subscription') {
    $subscriptionId = (int) zen_db_prepare_input($_GET['subscription_id'] ?? 0);
    $redirectQuery = zen_get_all_get_params(['action', 'subscription_id']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    if ($subscriptionId <= 0) {
        $messageStack->add_session(ERROR_SUBSCRIPTION_UNARCHIVE_MISSING_ID, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Update local record to unarchive
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['is_archived' => 0, 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    $messageStack->add_session(sprintf(SUCCESS_SUBSCRIPTION_UNARCHIVED, $subscriptionId), 'success');
    zen_redirect($redirectUrl);
}

// Bulk archive subscriptions action
if ($action === 'bulk_archive') {
    $subscriptionIds = $_POST['subscription_ids'] ?? [];
    $redirectQuery = zen_get_all_get_params(['action']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    // Validate that subscription_ids is an array
    if (!is_array($subscriptionIds) || empty($subscriptionIds)) {
        $messageStack->add_session(ERROR_BULK_ARCHIVE_NO_SELECTION, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Filter and validate subscription IDs
    $validIds = array_filter(array_map('intval', $subscriptionIds), function($id) {
        return $id > 0;
    });
    
    if (empty($validIds)) {
        $messageStack->add_session(ERROR_BULK_ARCHIVE_NO_SELECTION, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Perform bulk update with a single query for efficiency
    // Note: $validIds contains only validated positive integers (filtered through intval),
    // so concatenation is safe from SQL injection
    $idsList = implode(',', $validIds);
    $sql = "UPDATE " . TABLE_PAYPAL_SUBSCRIPTIONS . "
            SET is_archived = 1, last_modified = '" . date('Y-m-d H:i:s') . "'
            WHERE paypal_subscription_id IN (" . $idsList . ")";
    $db->Execute($sql);
    
    $archived = count($validIds);
    $messageStack->add_session(sprintf(SUCCESS_BULK_ARCHIVED, $archived), 'success');
    zen_redirect($redirectUrl);
}

// Bulk unarchive subscriptions action
if ($action === 'bulk_unarchive') {
    $subscriptionIds = $_POST['subscription_ids'] ?? [];
    $redirectQuery = zen_get_all_get_params(['action']);
    $redirectUrl = zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $redirectQuery);
    
    // Validate that subscription_ids is an array
    if (!is_array($subscriptionIds) || empty($subscriptionIds)) {
        $messageStack->add_session(ERROR_BULK_UNARCHIVE_NO_SELECTION, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Filter and validate subscription IDs
    $validIds = array_filter(array_map('intval', $subscriptionIds), function($id) {
        return $id > 0;
    });
    
    if (empty($validIds)) {
        $messageStack->add_session(ERROR_BULK_UNARCHIVE_NO_SELECTION, 'error');
        zen_redirect($redirectUrl);
    }
    
    // Perform bulk update with a single query for efficiency
    // Note: $validIds contains only validated positive integers (filtered through intval),
    // so concatenation is safe from SQL injection
    $idsList = implode(',', $validIds);
    $sql = "UPDATE " . TABLE_PAYPAL_SUBSCRIPTIONS . "
            SET is_archived = 0, last_modified = '" . date('Y-m-d H:i:s') . "'
            WHERE paypal_subscription_id IN (" . $idsList . ")";
    $db->Execute($sql);
    
    $unarchived = count($validIds);
    $messageStack->add_session(sprintf(SUCCESS_BULK_UNARCHIVED, $unarchived), 'success');
    zen_redirect($redirectUrl);
}

// CSV Export action
if ($action === 'export_csv') {
    $exportFilters = [
        'customers_id' => (int) ($_GET['customers_id'] ?? 0),
        'products_id' => (int) ($_GET['products_id'] ?? 0),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'payment_module' => trim((string) ($_GET['payment_module'] ?? '')),
        'show_archived' => trim((string) ($_GET['show_archived'] ?? '')),
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
    
    // Archive filter - by default, exclude archived subscriptions from export
    if ($exportFilters['show_archived'] === 'only') {
        $exportWhere[] = 'ps.is_archived = 1';
    } elseif ($exportFilters['show_archived'] !== 'all') {
        $exportWhere[] = 'ps.is_archived = 0';
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
    'show_archived' => trim((string) ($_GET['show_archived'] ?? '')),
    'subscription_type' => trim((string) ($_GET['subscription_type'] ?? '')),
];

$queryString = [];
foreach ($filters as $key => $value) {
    if ($value === '' || $value === 0) {
        continue;
    }
    $queryString[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
}
$activeQuery = implode('&', $queryString);

// Get statuses from both tables
$availableStatuses = paypalr_known_status_labels();
$statusRecords = $db->Execute(
    'SELECT DISTINCT status FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ORDER BY status'
);
if ($statusRecords instanceof queryFactoryResult && $statusRecords->RecordCount() > 0) {
    while (!$statusRecords->EOF) {
        $statusValue = (string) $statusRecords->fields['status'];
        if ($statusValue !== '' && !isset($availableStatuses[$statusValue])) {
            $availableStatuses[$statusValue] = ucwords(str_replace('_', ' ', $statusValue));
        }
        $statusRecords->MoveNext();
    }
}
if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
    $savedCardStatusRecords = $db->Execute(
        'SELECT DISTINCT status FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' ORDER BY status'
    );
    if ($savedCardStatusRecords instanceof queryFactoryResult && $savedCardStatusRecords->RecordCount() > 0) {
        while (!$savedCardStatusRecords->EOF) {
            $statusValue = (string) $savedCardStatusRecords->fields['status'];
            if ($statusValue !== '' && !isset($availableStatuses[$statusValue])) {
                $availableStatuses[$statusValue] = ucwords(str_replace('_', ' ', $statusValue));
            }
            $savedCardStatusRecords->MoveNext();
        }
    }
}

// Get customers from both tables
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
if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') && defined('TABLE_SAVED_CREDIT_CARDS')) {
    $savedCardCustomerRecords = $db->Execute(
        'SELECT DISTINCT scc.customers_id, c.customers_firstname, c.customers_lastname'
        . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
        . ' LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id'
        . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = scc.customers_id'
        . ' ORDER BY c.customers_lastname, c.customers_firstname'
    );
    if ($savedCardCustomerRecords instanceof queryFactoryResult) {
        while (!$savedCardCustomerRecords->EOF) {
            $cid = (int) $savedCardCustomerRecords->fields['customers_id'];
            if ($cid > 0 && !isset($customersOptions[$cid])) {
                $customersOptions[$cid] = trim($savedCardCustomerRecords->fields['customers_lastname'] . ', ' . $savedCardCustomerRecords->fields['customers_firstname']);
            }
            $savedCardCustomerRecords->MoveNext();
        }
    }
}

// Get products from both tables
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
if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
    $savedCardProductRecords = $db->Execute(
        'SELECT DISTINCT sccr.products_id, sccr.products_name'
        . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
        . ' ORDER BY sccr.products_name'
    );
    if ($savedCardProductRecords instanceof queryFactoryResult) {
        while (!$savedCardProductRecords->EOF) {
            $pid = (int) $savedCardProductRecords->fields['products_id'];
            if ($pid > 0 && !isset($productOptions[$pid])) {
                $productOptions[$pid] = $savedCardProductRecords->fields['products_name'];
            }
            $savedCardProductRecords->MoveNext();
        }
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

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(10, min(100, (int)$_GET['per_page'])) : 20;

// Defensive type check: ensure $page remains a valid positive integer
$page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
if ($page === false) {
    $page = 1;
}

// Fetch subscriptions from both tables
$allSubscriptions = [];

// Fetch REST API subscriptions
if ($filters['subscription_type'] === '' || $filters['subscription_type'] === 'rest') {
    $restWhereClauses = [];
    
    if ($filters['customers_id'] > 0) {
        $restWhereClauses[] = 'ps.customers_id = ' . (int) $filters['customers_id'];
    }
    if ($filters['products_id'] > 0) {
        $restWhereClauses[] = 'ps.products_id = ' . (int) $filters['products_id'];
    }
    if ($filters['status'] !== '') {
        $restWhereClauses[] = "ps.status = '" . zen_db_input($filters['status']) . "'";
    }
    if ($filters['payment_module'] !== '') {
        $restWhereClauses[] = "o.payment_module_code = '" . zen_db_input($filters['payment_module']) . "'";
    }
    if ($filters['show_archived'] === 'only') {
        $restWhereClauses[] = 'ps.is_archived = 1';
    } elseif ($filters['show_archived'] !== 'all') {
        $restWhereClauses[] = 'ps.is_archived = 0';
    }
    
    $restSql = 'SELECT ps.*, c.customers_firstname, c.customers_lastname, c.customers_email_address,'
        . ' o.payment_module_code, o.payment_method,'
        . ' pv.brand AS vault_brand, pv.last_digits AS vault_last_digits, pv.card_type AS vault_card_type, pv.status AS vault_status, pv.expiry AS vault_expiry'
        . ' FROM ' . TABLE_PAYPAL_SUBSCRIPTIONS . ' ps'
        . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = ps.customers_id'
        . ' LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = ps.orders_id'
        . ' LEFT JOIN ' . TABLE_PAYPAL_VAULT . ' pv ON pv.paypal_vault_id = ps.paypal_vault_id';
    
    if (!empty($restWhereClauses)) {
        $restSql .= ' WHERE ' . implode(' AND ', $restWhereClauses);
    }
    
    $restSubscriptions = $db->Execute($restSql);
    
    if ($restSubscriptions instanceof queryFactoryResult) {
        while (!$restSubscriptions->EOF) {
            $row = $restSubscriptions->fields;
            $row['subscription_type'] = 'rest';
            $row['sort_date'] = strtotime($row['date_added'] ?? 'now');
            $allSubscriptions[] = $row;
            $restSubscriptions->MoveNext();
        }
    }
}

// Fetch Saved Card subscriptions
// Note: Saved cards don't support payment_module or is_archived filters
if (defined('TABLE_SAVED_CREDIT_CARDS_RECURRING') && defined('TABLE_SAVED_CREDIT_CARDS') 
    && ($filters['subscription_type'] === '' || $filters['subscription_type'] === 'savedcard')
    && $filters['show_archived'] !== 'only') {  // Exclude saved cards when filtering for archived only
    $savedCardWhereClauses = [];
    
    if ($filters['customers_id'] > 0) {
        $savedCardWhereClauses[] = 'scc.customers_id = ' . (int) $filters['customers_id'];
    }
    if ($filters['products_id'] > 0) {
        $savedCardWhereClauses[] = 'sccr.products_id = ' . (int) $filters['products_id'];
    }
    if ($filters['status'] !== '') {
        $savedCardWhereClauses[] = "sccr.status = '" . zen_db_input($filters['status']) . "'";
    }
    
    $savedCardSql = 'SELECT sccr.saved_credit_card_recurring_id AS paypal_subscription_id,'
        . ' scc.customers_id, sccr.products_id, sccr.products_name,'
        . ' sccr.amount, sccr.currency_code, sccr.billing_period, sccr.billing_frequency,'
        . ' sccr.total_billing_cycles, sccr.status, sccr.date_added AS date,'
        . ' sccr.next_payment_date, sccr.comments, sccr.domain,'
        . ' c.customers_firstname, c.customers_lastname, c.customers_email_address,'
        . ' scc.type AS vault_card_type, scc.last_digits AS vault_last_digits,'
        . ' sccr.saved_credit_card_id,'
        . ' sccr.billing_name, sccr.billing_company, sccr.billing_street_address,'
        . ' sccr.billing_suburb, sccr.billing_city, sccr.billing_state,'
        . ' sccr.billing_postcode, sccr.billing_country_id, sccr.billing_country_code,'
        . ' sccr.shipping_method, sccr.shipping_cost'
        . ' FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr'
        . ' LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id'
        . ' LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = scc.customers_id';
    
    if (!empty($savedCardWhereClauses)) {
        $savedCardSql .= ' WHERE ' . implode(' AND ', $savedCardWhereClauses);
    }
    
    $savedCardSubscriptions = $db->Execute($savedCardSql);
    
    if ($savedCardSubscriptions instanceof queryFactoryResult) {
        while (!$savedCardSubscriptions->EOF) {
            $row = $savedCardSubscriptions->fields;
            $row['subscription_type'] = 'savedcard';
            // Map saved card fields to match REST subscription structure
            // Note: 'date' field is aliased from 'date_added' for compatibility with code expecting creation date
            $row['date_added'] = $row['date'];
            // Use actual next_payment_date from DB if available, otherwise fall back to date_added
            $row['next_payment_date'] = $row['next_payment_date'] ?? $row['date'];
            $row['sort_date'] = strtotime($row['date'] ?? 'now');
            // Saved card subscriptions don't have quantity field, always 1
            $row['products_quantity'] = 1;
            $allSubscriptions[] = $row;
            $savedCardSubscriptions->MoveNext();
        }
    }
}

// Sort by date descending
// Note: Using in-memory sort for simplicity. For large datasets, consider using database UNION queries
// with ORDER BY and LIMIT/OFFSET for better performance and memory efficiency.
usort($allSubscriptions, function($a, $b) {
    return $b['sort_date'] - $a['sort_date'];
});

// Apply pagination
$totalRecords = count($allSubscriptions);
$totalPages = ($totalRecords > 0) ? ceil($totalRecords / $perPage) : 1;
$offset = ($page - 1) * $perPage;

$subscriptionRows = array_slice($allSubscriptions, $offset, $perPage);

$vaultCache = [];

/**
 * Generate pagination URL with filters preserved
 */
function paypalr_pagination_url($page, $perPage, $activeQuery) {
    $params = [];
    if ($activeQuery !== '') {
        parse_str($activeQuery, $params);
    }
    $params['page'] = $page;
    $params['per_page'] = $perPage;
    $queryStr = http_build_query($params);
    return zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $queryStr);
}

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
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    <style>
        .paypalr-subscriptions-table td textarea {
            width: 100%;
            min-height: 120px;
            font-family: monospace;
        }
        .paypalr-subscriptions-table td input[type="text"],
        .paypalr-subscriptions-table td input[type="number"],
        .paypalr-subscriptions-table td input[type="date"],
        .paypalr-subscriptions-table td select {
            width: 100%;
            box-sizing: border-box;
        }
        .paypalr-subscription-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .paypalr-subscription-meta {
            font-size: 0.9em;
            color: #555;
            margin-top: 8px;
        }
        .paypalr-subscriptions-table label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--nmx-dark);
            display: block;
            margin-bottom: 4px;
            margin-top: 8px;
        }
        .paypalr-subscriptions-table label:first-child {
            margin-top: 0;
        }
        
        /* Expand/Collapse styles */
        .subscription-row-collapsed + .details-row {
            display: none;
        }
        .subscription-summary {
            cursor: pointer;
            user-select: none;
        }
        .toggle-icon {
            display: inline-block;
            margin-right: 8px;
            font-size: 12px;
            transition: transform 0.2s;
        }
        .toggle-icon::before {
            content: '\25BC'; /* Down-pointing triangle */
        }
        .subscription-row-collapsed .toggle-icon::before {
            content: '\25B6'; /* Right-pointing triangle */
        }
        .subscription-summary:hover {
            background-color: rgba(0, 97, 141, 0.05);
        }
        
        /* Pagination styles */
        .pagination-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 16px 0;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .pagination-info {
            font-size: 14px;
            color: #555;
        }
        .pagination-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .pagination-links a,
        .pagination-links span {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            text-decoration: none;
            color: #333;
            border-radius: 3px;
        }
        .pagination-links a:hover {
            background: #00618d;
            color: white;
            border-color: #00618d;
        }
        .pagination-links span.current {
            background: #00618d;
            color: white;
            border-color: #00618d;
        }
        .pagination-links span.disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        .per-page-selector {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .per-page-selector select {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
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
        if (isset($messageStack) && is_object($messageStack) && $messageStack->size > 0) {
            echo $messageStack->output();
        }
        ?>
        </div>
        
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title">Filter Subscriptions</div>
            </div>
            <div class="nmx-panel-body">
                <?php echo zen_draw_form('paypalr_filter', FILENAME_PAYPALR_SUBSCRIPTIONS, '', 'get', 'class="nmx-form-inline"'); ?>
                    <div class="nmx-form-group">
                        <label for="filter-customers">Customer</label>
                        <select name="customers_id" id="filter-customers" class="nmx-form-control">
                            <option value="0">All Customers</option>
                            <?php echo paypalr_render_select_options($customersOptions, $filters['customers_id']); ?>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="filter-products">Product</label>
                        <select name="products_id" id="filter-products" class="nmx-form-control">
                            <option value="0">All Products</option>
                            <?php echo paypalr_render_select_options($productOptions, $filters['products_id']); ?>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="filter-status">Status</label>
                        <select name="status" id="filter-status" class="nmx-form-control">
                            <option value="">All Statuses</option>
                            <?php echo paypalr_render_select_options($availableStatuses, $filters['status']); ?>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="filter-subscription-type">Subscription Type</label>
                        <select name="subscription_type" id="filter-subscription-type" class="nmx-form-control">
                            <option value="">All Types</option>
                            <option value="rest"<?php echo ($filters['subscription_type'] === 'rest' ? ' selected' : ''); ?>>REST API</option>
                            <option value="savedcard"<?php echo ($filters['subscription_type'] === 'savedcard' ? ' selected' : ''); ?>>Saved Card</option>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="filter-payment">Payment Method</label>
                        <select name="payment_module" id="filter-payment" class="nmx-form-control">
                            <option value="">All Methods</option>
                            <?php echo paypalr_render_select_options($paymentModuleOptions, $filters['payment_module']); ?>
                        </select>
                    </div>
                    <div class="nmx-form-group">
                        <label for="filter-archived">Archived</label>
                        <select name="show_archived" id="filter-archived" class="nmx-form-control">
                            <option value="">Active Only</option>
                            <option value="all"<?php echo ($filters['show_archived'] === 'all' ? ' selected' : ''); ?>>Show All</option>
                            <option value="only"<?php echo ($filters['show_archived'] === 'only' ? ' selected' : ''); ?>>Archived Only</option>
                        </select>
                    </div>
                    <div class="nmx-form-actions">
                        <button type="submit" class="nmx-btn nmx-btn-primary">Apply Filters</button>
                        <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, 'action=export_csv' . ($activeQuery !== '' ? '&' . $activeQuery : '')); ?>" class="nmx-btn nmx-btn-info">Export CSV</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php
        // Re-validate $page and $perPage to protect against framework-level variable extraction
        // that may have occurred after the initial validation (e.g., extract($_GET) in included
        // Zen Cart framework files). This ensures both are always valid integers even if
        // overwritten with array values.
        $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        if ($page === false) {
            $page = 1;
        }
        $perPage = filter_var($perPage, FILTER_VALIDATE_INT, ['options' => ['default' => 20, 'min_range' => 10, 'max_range' => 100]]);
        if ($perPage === false) {
            $perPage = 20;
        }
        ?>
        
        <!-- Pagination controls -->
        <div class="pagination-controls">
            <div class="pagination-info">
                Showing <?php echo $totalRecords > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> subscriptions
            </div>
            <div class="per-page-selector">
                <label for="per-page-select">Per page:</label>
                <select id="per-page-select" onchange="changePerPage(this.value)">
                    <option value="10"<?php echo $perPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="20"<?php echo $perPage === 20 ? ' selected' : ''; ?>>20</option>
                    <option value="50"<?php echo $perPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $perPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="<?php echo paypalr_pagination_url(1, $perPage, $activeQuery); ?>">&laquo; First</a>
                    <a href="<?php echo paypalr_pagination_url($page - 1, $perPage, $activeQuery); ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <?php
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    if ($i === $page):
                ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo paypalr_pagination_url($i, $perPage, $activeQuery); ?>"><?php echo $i; ?></a>
                <?php
                    endif;
                endfor;
                ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo paypalr_pagination_url($page + 1, $perPage, $activeQuery); ?>">Next &rsaquo;</a>
                    <a href="<?php echo paypalr_pagination_url($totalPages, $perPage, $activeQuery); ?>">Last &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &rsaquo;</span>
                    <span class="disabled">Last &raquo;</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="nmx-panel">
            <div class="nmx-panel-heading">
                <div class="nmx-panel-title">Vaulted Subscriptions</div>
            </div>
            <div class="nmx-panel-body">
                <!-- Bulk Actions Form -->
                <?php echo zen_draw_form('bulk_actions_form', FILENAME_PAYPALR_SUBSCRIPTIONS, '', 'post', 'id="bulk-actions-form"'); ?>
                    <?php echo zen_draw_hidden_field('action', ''); ?>
                    <div class="bulk-actions-controls" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                        <label for="bulk-action-select" style="margin: 0;">Bulk Actions:</label>
                        <select name="bulk_action" id="bulk-action-select" class="nmx-form-control" style="width: auto;">
                            <option value="">-- Select Action --</option>
                            <option value="bulk_archive">Archive Selected</option>
                            <option value="bulk_unarchive">Unarchive Selected</option>
                        </select>
                        <button type="button" id="apply-bulk-action" class="nmx-btn nmx-btn-primary">Apply</button>
                        <span id="selected-count" style="margin-left: 10px; color: #666;"></span>
                    </div>
                
                <div class="nmx-table-responsive">
                    <table class="nmx-table nmx-table-striped paypalr-subscriptions-table">

                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="select-all-subscriptions" title="Select/Unselect All"></th>
                                <th>Subscription</th>
                                <th>Type</th>
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
                                    <td colspan="9">No subscriptions found for the selected filters.</td>
                                </tr>
                            <?php }
                foreach ($subscriptionRows as $row) {
                    $subscriptionId = (int) ($row['paypal_subscription_id'] ?? 0);
                    $formId = 'subscription-form-' . $subscriptionId;
                    $currentStatus = strtolower($row['status'] ?? '');
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
                    
                    // Prepare saved credit card options for savedcard subscriptions
                    $subscriptionType = $row['subscription_type'] ?? 'rest';
                    $savedCardOptions = ['0' => 'None'];
                    if ($subscriptionType === 'savedcard' && $customersId > 0 && defined('TABLE_SAVED_CREDIT_CARDS')) {
                        $savedCardsQuery = $db->Execute(
                            "SELECT saved_credit_card_id, type, last_digits, holder_name, is_default
                             FROM " . TABLE_SAVED_CREDIT_CARDS . "
                             WHERE customers_id = " . (int)$customersId . "
                               AND is_deleted = 0
                             ORDER BY is_default DESC, saved_credit_card_id DESC"
                        );
                        if ($savedCardsQuery instanceof queryFactoryResult) {
                            while (!$savedCardsQuery->EOF) {
                                $cardId = (int)$savedCardsQuery->fields['saved_credit_card_id'];
                                $label = '#' . $cardId . ' ' . ($savedCardsQuery->fields['type'] ?? 'Card');
                                if (!empty($savedCardsQuery->fields['last_digits'])) {
                                    $label .= ' ••••' . $savedCardsQuery->fields['last_digits'];
                                }
                                if (!empty($savedCardsQuery->fields['holder_name'])) {
                                    $label .= ' - ' . $savedCardsQuery->fields['holder_name'];
                                }
                                if ((int)$savedCardsQuery->fields['is_default'] === 1) {
                                    $label .= ' (Default)';
                                }
                                $savedCardOptions[(string)$cardId] = $label;
                                $savedCardsQuery->MoveNext();
                            }
                        }
                    }

                    ?>
                    <?php echo zen_draw_form($formId, FILENAME_PAYPALR_SUBSCRIPTIONS, '', 'post', 'id="' . $formId . '"'); ?>
                        <?php echo zen_draw_hidden_field('action', 'update_subscription'); ?>
                        <?php echo zen_draw_hidden_field('paypal_subscription_id', $subscriptionId); ?>
                        <?php echo zen_draw_hidden_field('customers_id', $customersId); ?>
                        <?php echo zen_draw_hidden_field('subscription_type', $row['subscription_type'] ?? 'rest'); ?>
                        <?php echo zen_draw_hidden_field('redirect_query', $activeQuery); ?>
                    </form>
                    
                    <!-- Summary row (always visible, clickable to expand/collapse) -->
                    <tr class="subscription-summary subscription-row-collapsed" onclick="toggleSubscription(<?php echo $subscriptionId; ?>, event)" data-subscription-id="<?php echo $subscriptionId; ?>">
                        <td onclick="event.stopPropagation();">
                            <input type="checkbox" name="subscription_ids[]" value="<?php echo $subscriptionId; ?>" class="subscription-checkbox" form="bulk-actions-form">
                        </td>
                        <td>
                            <span class="toggle-icon"></span>
                            <strong>#<?php echo (int) $row['paypal_subscription_id']; ?></strong>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; display: inline-flex; align-items: center; white-space: nowrap; background: <?php
                                echo ($row['subscription_type'] ?? 'rest') === 'rest' ? '#007bff' : '#28a745';
                            ?>; color: white;">
                                <?php echo ($row['subscription_type'] ?? 'rest') === 'rest' ? 'REST API' : 'Saved Card'; ?>
                            </span>
                        </td>
                        <td><?php echo $customerName !== '' ? zen_output_string_protected($customerName) : 'Unknown Customer'; ?></td>
                        <td><?php echo zen_output_string_protected((string) ($row['products_name'] ?? 'N/A')); ?></td>
                        <td>
                            Every <?php echo (int) ($row['billing_frequency'] ?? 0); ?> <?php echo zen_output_string_protected((string) ($row['billing_period'] ?? '')); ?>(s)
                            <?php if (!empty($row['next_payment_date'])) { ?>
                                <br><small>Next: <?php echo zen_date_short($row['next_payment_date']); ?></small>
                            <?php } ?>
                        </td>
                        <td>
                            <?php echo zen_output_string_protected((string) ($row['currency_code'] ?? '')); ?> <?php echo number_format((float) ($row['amount'] ?? 0), 2); ?>
                        </td>
                        <td>
                            <?php if (!empty($row['vault_brand']) || !empty($row['vault_card_type'])) { ?>
                                <?php echo zen_output_string_protected(trim(($row['vault_card_type'] ?? '') . ' ' . ($row['vault_brand'] ?? ''))); ?>
                                <?php if (!empty($row['vault_last_digits'])) { ?>
                                    ••••<?php echo zen_output_string_protected($row['vault_last_digits']); ?>
                                <?php } ?>
                            <?php } else { ?>
                                N/A
                            <?php } ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <span style="padding: 4px 8px; border-radius: 3px; background: <?php
                                if ($currentStatus === 'active') echo '#28a745';
                                elseif ($currentStatus === 'cancelled') echo '#dc3545';
                                elseif ($currentStatus === 'suspended' || $currentStatus === 'paused') echo '#ffc107';
                                else echo '#6c757d';
                            ?>; color: white; font-size: 0.85em;">
                                <?php echo zen_output_string_protected(ucfirst($currentStatus)); ?>
                            </span>
                        </td>
                    </tr>
                    
                    <!-- Details rows (hidden by default) -->
                    <tr class="details-row" data-subscription-id="<?php echo $subscriptionId; ?>">
                        <td colspan="7">
                            <div style="padding: 16px; background: #f9f9f9; border-radius: 4px;">
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                                    <!-- Subscription Details Column -->
                                    <div>
                                        <h4 style="margin-top: 0; color: #00618d;">Subscription Details</h4>
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
                                    </div>
                                    
                                    <!-- Product Details Column -->
                                    <div>
                                        <h4 style="margin-top: 0; color: #00618d;">Product Information</h4>
                                        <label>Product Name</label>
                                        <input type="text" name="products_name" value="<?php echo zen_output_string_protected((string) ($row['products_name'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        <label>Product ID</label>
                                        <input type="number" name="products_id" value="<?php echo (int) ($row['products_id'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        <label>Quantity</label>
                                        <input type="number" step="0.01" name="products_quantity" value="<?php echo (float) ($row['products_quantity'] ?? 1); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                    </div>
                                    
                                    <!-- Vault & Status Column -->
                                    <div>
                                        <h4 style="margin-top: 0; color: #00618d;">Status & <?php echo $subscriptionType === 'savedcard' ? 'Payment Method' : 'Vault'; ?></h4>
                                        <label>Current Status</label>
                                        <select name="status" form="<?php echo $formId; ?>" class="nmx-form-control">
                                            <?php echo paypalr_render_select_options($availableStatuses, $row['status'] ?? ''); ?>
                                        </select>
                                        <?php if ($subscriptionType === 'savedcard') { ?>
                                        <label>Payment Method (Saved Card)</label>
                                        <select name="saved_credit_card_id" form="<?php echo $formId; ?>" class="nmx-form-control">
                                            <?php echo paypalr_render_select_options($savedCardOptions, $row['saved_credit_card_id'] ?? '0'); ?>
                                        </select>
                                        <p style="margin: 4px 0 0 0; font-size: 0.85em; color: #666;">
                                            <em>Change the payment method used for this subscription.</em>
                                        </p>
                                        <?php } else { ?>
                                        <label>Vault Assignment</label>
                                        <select name="paypal_vault_id" form="<?php echo $formId; ?>" class="nmx-form-control">
                                            <?php echo paypalr_render_select_options($vaultOptions, $row['paypal_vault_id'] ?? '0'); ?>
                                        </select>
                                        <label>Vault ID (manual override)</label>
                                        <input type="text" name="vault_id" value="<?php echo zen_output_string_protected((string) ($row['vault_id'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Billing Details (Full Width) -->
                                <div style="margin-top: 20px;">
                                    <h4 style="margin-top: 0; color: #00618d;">Billing Configuration</h4>
                                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                                        <div>
                                            <label>Billing Period</label>
                                            <input type="text" name="billing_period" value="<?php echo zen_output_string_protected((string) ($row['billing_period'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Billing Frequency</label>
                                            <input type="number" name="billing_frequency" value="<?php echo (int) ($row['billing_frequency'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Total Billing Cycles</label>
                                            <input type="number" name="total_billing_cycles" value="<?php echo (int) ($row['total_billing_cycles'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Next Billing Date</label>
                                            <input type="date" name="next_payment_date" value="<?php echo zen_output_string_protected((string) ($row['next_payment_date'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Trial Configuration -->
                                <div style="margin-top: 16px;">
                                    <h4 style="margin-top: 0; color: #00618d;">Trial Configuration</h4>
                                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                                        <div>
                                            <label>Trial Period</label>
                                            <input type="text" name="trial_period" value="<?php echo zen_output_string_protected((string) ($row['trial_period'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Trial Frequency</label>
                                            <input type="number" name="trial_frequency" value="<?php echo (int) ($row['trial_frequency'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Trial Cycles</label>
                                            <input type="number" name="trial_total_cycles" value="<?php echo (int) ($row['trial_total_cycles'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Financial Details -->
                                <div style="margin-top: 16px;">
                                    <h4 style="margin-top: 0; color: #00618d;">Financial Details</h4>
                                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                                        <div>
                                            <label>Setup Fee</label>
                                            <input type="number" step="0.01" name="setup_fee" value="<?php echo (float) ($row['setup_fee'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Amount</label>
                                            <input type="number" step="0.01" name="amount" value="<?php echo (float) ($row['amount'] ?? 0); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Currency Code</label>
                                            <input type="text" maxlength="3" name="currency_code" value="<?php echo zen_output_string_protected((string) ($row['currency_code'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Currency Value</label>
                                            <input type="number" step="0.000001" name="currency_value" value="<?php echo (float) ($row['currency_value'] ?? 1); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Billing Address -->
                                <?php if ($subscriptionType === 'savedcard') { ?>
                                <div style="margin-top: 16px;">
                                    <h4 style="margin-top: 0; color: #00618d;">Billing Address</h4>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                        <div>
                                            <label>Billing Name</label>
                                            <input type="text" name="billing_name" value="<?php echo zen_output_string_protected((string) ($row['billing_name'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Billing Company</label>
                                            <input type="text" name="billing_company" value="<?php echo zen_output_string_protected((string) ($row['billing_company'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Street Address</label>
                                            <input type="text" name="billing_street_address" value="<?php echo zen_output_string_protected((string) ($row['billing_street_address'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Address Line 2 (Suburb)</label>
                                            <input type="text" name="billing_suburb" value="<?php echo zen_output_string_protected((string) ($row['billing_suburb'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>City</label>
                                            <input type="text" name="billing_city" value="<?php echo zen_output_string_protected((string) ($row['billing_city'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>State/Province</label>
                                            <input type="text" name="billing_state" value="<?php echo zen_output_string_protected((string) ($row['billing_state'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Postal Code</label>
                                            <input type="text" name="billing_postcode" value="<?php echo zen_output_string_protected((string) ($row['billing_postcode'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Country Code (2-letter, e.g., CA, US)</label>
                                            <input type="text" maxlength="2" pattern="[A-Z]{2}" name="billing_country_code" value="<?php echo zen_output_string_protected((string) ($row['billing_country_code'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" style="text-transform: uppercase;" placeholder="CA" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Shipping Information -->
                                <div style="margin-top: 16px;">
                                    <h4 style="margin-top: 0; color: #00618d;">Shipping Information</h4>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                        <div>
                                            <label>Shipping Method</label>
                                            <input type="text" name="shipping_method" value="<?php echo zen_output_string_protected((string) ($row['shipping_method'] ?? '')); ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                        <div>
                                            <label>Shipping Cost</label>
                                            <input type="number" step="0.01" name="shipping_cost" value="<?php echo ($row['shipping_cost'] !== null) ? (float) $row['shipping_cost'] : ''; ?>" form="<?php echo $formId; ?>" class="nmx-form-control" />
                                        </div>
                                    </div>
                                    <p style="margin: 8px 0 0 0; font-size: 0.9em; color: #666;">
                                        <em>Note: Shipping rate was locked at subscription creation and will be reused for recurring orders.</em>
                                    </p>
                                </div>
                                <?php } ?>
                                
                                <!-- Attributes -->
                                <div style="margin-top: 16px;">
                                    <label for="attributes-<?php echo $subscriptionId; ?>">Attributes (JSON)</label>
                                    <textarea id="attributes-<?php echo $subscriptionId; ?>" name="attributes" form="<?php echo $formId; ?>" placeholder="{ }" class="nmx-form-control" style="min-height: 100px; font-family: monospace;"><?php echo zen_output_string_protected($attributesPretty); ?></textarea>
                                </div>
                                
                                <!-- Actions -->
                                <div style="margin-top: 20px; padding-top: 16px; border-top: 2px solid #ddd;">
                                    <h4 style="margin-top: 0; color: #00618d;">Actions</h4>
                                    <div class="paypalr-subscription-actions">
                                        <button type="submit" form="<?php echo $formId; ?>" class="nmx-btn nmx-btn-sm nmx-btn-primary">Save Changes</button>
                                        <?php if ($currentStatus !== 'cancelled') { ?>
                                            <button type="submit" name="set_status" value="cancelled" form="<?php echo $formId; ?>" class="nmx-btn nmx-btn-sm nmx-btn-warning">Mark Cancelled</button>
                                        <?php } ?>
                                        <?php if ($currentStatus !== 'active') { ?>
                                            <button type="submit" name="set_status" value="active" form="<?php echo $formId; ?>" class="nmx-btn nmx-btn-sm nmx-btn-success">Mark Active</button>
                                        <?php } ?>
                                        <?php if ($currentStatus !== 'pending') { ?>
                                            <button type="submit" name="set_status" value="pending" form="<?php echo $formId; ?>" class="nmx-btn nmx-btn-sm nmx-btn-secondary">Mark Pending</button>
                                        <?php } ?>
                                    </div>
                                    <div class="paypalr-subscription-actions" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                                        <?php
                                        $actionParams = $activeQuery !== '' ? $activeQuery . '&' : '';
                                        $subscriptionType = $row['subscription_type'] ?? 'rest';
                                        $actionParams .= 'subscription_type=' . urlencode($subscriptionType) . '&';
                                        $isArchived = !empty($row['is_archived']);
                                        ?>
                                        <?php if ($currentStatus === 'active' || $currentStatus === 'scheduled') { ?>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=skip_next_payment&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Skip this payment? The next billing date will be automatically calculated and updated based on the subscription schedule.');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-info">Skip Next</a>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=suspend_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to suspend this subscription?');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-warning">Suspend</a>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=cancel_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to cancel this subscription? This action cannot be undone.');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-danger">Cancel</a>
                                        <?php } elseif ($currentStatus === 'suspended' || $currentStatus === 'paused') { ?>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=reactivate_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to reactivate this subscription?');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-success">Reactivate</a>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=cancel_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to cancel this subscription? This action cannot be undone.');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-danger">Cancel</a>
                                        <?php } ?>
                                        <?php if ($subscriptionType === 'rest') { ?>
                                        <?php if ($isArchived) { ?>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=unarchive_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to unarchive this subscription?');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-info">Unarchive</a>
                                        <?php } else { ?>
                                            <a href="<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, $actionParams . 'action=archive_subscription&subscription_id=' . $subscriptionId); ?>" 
                                               onclick="return confirm('Are you sure you want to archive this subscription? Archived subscriptions are hidden by default.');"
                                               class="nmx-btn nmx-btn-sm nmx-btn-secondary">Archive</a>
                                        <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                        </tbody>
                    </table>
                </div>
                </form>
            </div>
        </div>
        
        <!-- Bottom Pagination controls -->
        <div class="pagination-controls">
            <div class="pagination-info">
                Showing <?php echo $totalRecords > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> subscriptions
            </div>
            <div class="per-page-selector">
                <label for="per-page-select-bottom">Per page:</label>
                <select id="per-page-select-bottom" onchange="changePerPage(this.value)">
                    <option value="10"<?php echo $perPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="20"<?php echo $perPage === 20 ? ' selected' : ''; ?>>20</option>
                    <option value="50"<?php echo $perPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $perPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="<?php echo paypalr_pagination_url(1, $perPage, $activeQuery); ?>">&laquo; First</a>
                    <a href="<?php echo paypalr_pagination_url($page - 1, $perPage, $activeQuery); ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <?php
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    if ($i === $page):
                ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo paypalr_pagination_url($i, $perPage, $activeQuery); ?>"><?php echo $i; ?></a>
                <?php
                    endif;
                endfor;
                ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo paypalr_pagination_url($page + 1, $perPage, $activeQuery); ?>">Next &rsaquo;</a>
                    <a href="<?php echo paypalr_pagination_url($totalPages, $perPage, $activeQuery); ?>">Last &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &rsaquo;</span>
                    <span class="disabled">Last &raquo;</span>
                <?php endif; ?>
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
/**
 * Change items per page
 */
function changePerPage(newPerPage) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', '1'); // Reset to first page when changing per page
    params.set('per_page', newPerPage);
    window.location.href = '<?php echo zen_href_link(FILENAME_PAYPALR_SUBSCRIPTIONS, ''); ?>' + '?' + params.toString();
}

/**
 * Toggle subscription row expand/collapse
 */
function toggleSubscription(subscriptionId, event) {
    const summaryRow = document.querySelector('.subscription-summary[data-subscription-id="' + subscriptionId + '"]');
    const detailsRow = document.querySelector('.details-row[data-subscription-id="' + subscriptionId + '"]');
    
    if (summaryRow && detailsRow) {
        summaryRow.classList.toggle('subscription-row-collapsed');
        
        // Prevent event bubbling to prevent conflicts with form elements
        if (event) {
            event.stopPropagation();
        }
    }
}

// Prevent clicks on form elements from triggering the row toggle
document.addEventListener('DOMContentLoaded', function() {
    const formElements = document.querySelectorAll('.details-row input, .details-row select, .details-row textarea, .details-row button, .details-row a');
    formElements.forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Select All / Unselect All functionality
    const selectAllCheckbox = document.getElementById('select-all-subscriptions');
    const subscriptionCheckboxes = document.querySelectorAll('.subscription-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const applyBulkActionBtn = document.getElementById('apply-bulk-action');
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkActionsForm = document.getElementById('bulk-actions-form');
    
    // Update selected count display
    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.subscription-checkbox:checked').length;
        if (checkedCount > 0) {
            selectedCountSpan.textContent = checkedCount + ' subscription(s) selected';
        } else {
            selectedCountSpan.textContent = '';
        }
    }
    
    // Select all checkbox handler
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            subscriptionCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox handler
    subscriptionCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // Update select all checkbox state
            const allChecked = Array.from(subscriptionCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(subscriptionCheckboxes).some(cb => cb.checked);
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            updateSelectedCount();
        });
    });
    
    // Apply bulk action handler
    if (applyBulkActionBtn) {
        applyBulkActionBtn.addEventListener('click', function() {
            const selectedAction = bulkActionSelect.value;
            const checkedBoxes = document.querySelectorAll('.subscription-checkbox:checked');
            const checkedCount = checkedBoxes.length;
            
            if (!selectedAction) {
                alert('Please select a bulk action.');
                return;
            }
            
            if (checkedCount === 0) {
                alert('Please select at least one subscription.');
                return;
            }
            
            // Confirmation messages
            let confirmMessage = '';
            if (selectedAction === 'bulk_archive') {
                confirmMessage = 'Are you sure you want to archive ' + checkedCount + ' subscription(s)?';
            } else if (selectedAction === 'bulk_unarchive') {
                confirmMessage = 'Are you sure you want to unarchive ' + checkedCount + ' subscription(s)?';
            }
            
            if (confirm(confirmMessage)) {
                // Set the action in the hidden field
                bulkActionsForm.querySelector('input[name="action"]').value = selectedAction;
                // Remove any previously injected subscription ids
                bulkActionsForm.querySelectorAll('input[name="subscription_ids[]"]').forEach(function(input) {
                    input.remove();
                });
                // Inject selected subscription ids into the bulk form payload
                checkedBoxes.forEach(function(checkbox) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'subscription_ids[]';
                    hiddenInput.value = checkbox.value;
                    bulkActionsForm.appendChild(hiddenInput);
                });
                // Submit the form
                bulkActionsForm.submit();
            }
        });
    }
    
    // Initialize count on page load
    updateSelectedCount();
});
</script>

</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php';
