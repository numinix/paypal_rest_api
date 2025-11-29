<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 The zen-cart developers                           |
// |                                                                      |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
//  $Id: store_credit.php 27 2012-07-19 18:40:42Z numinix $
//
  require('includes/application_top.php');
  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $messageStackKey = 'paypal_subscriptions';
  
  if (!defined('TABLE_PAYPAL_RECURRING_ARCHIVE')) {
    $archiveTableName = (defined('DB_PREFIX') ? DB_PREFIX : '') . 'paypal_recurring_archive';
    define('TABLE_PAYPAL_RECURRING_ARCHIVE', $archiveTableName);
  }
  
  $db->Execute(
    'CREATE TABLE IF NOT EXISTS ' . TABLE_PAYPAL_RECURRING_ARCHIVE . ' (
      archive_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      customers_id INT(10) UNSIGNED NOT NULL,
      subscription_id INT(10) UNSIGNED DEFAULT NULL,
      saved_credit_card_recurring_id INT(10) UNSIGNED DEFAULT NULL,
      profile_id VARCHAR(64) DEFAULT NULL,
      archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (archive_id),
      KEY idx_paypal_recurring_archive_customer_subscription (customers_id, subscription_id),
      KEY idx_paypal_recurring_archive_customer_savedcard (customers_id, saved_credit_card_recurring_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
  );

  $catalogBaseDir = defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG : dirname(__DIR__) . '/';
  $mySubscriptionsFunctions = $catalogBaseDir . 'includes/modules/pages/my_subscriptions/functions.php';
  if (!file_exists($mySubscriptionsFunctions)) {
    $mySubscriptionsFunctions = dirname(__DIR__) . '/includes/modules/pages/my_subscriptions/functions.php';
  }
  if (file_exists($mySubscriptionsFunctions)) {
    require_once($mySubscriptionsFunctions);
  }

  if (function_exists('zen_paypal_subscription_cache_cleanup_stale')) {
    zen_paypal_subscription_cache_cleanup_stale();
  }
  
  // get all customers that have at least one recurring profile attached to their account
  $customers_query = "SELECT DISTINCT(pr.customers_id), c.customers_firstname, c.customers_lastname 
                      FROM " . TABLE_PAYPAL_RECURRING . " pr 
                      LEFT JOIN " . TABLE_CUSTOMERS . " c ON (c.customers_id = pr.customers_id) 
                      GROUP BY pr.customers_id 
                      ORDER BY c.customers_lastname ASC, c.customers_firstname ASC;";
  $customers = $db->Execute($customers_query);
  $search_customers = array();
  if ($customers->RecordCount() > 0) {
    $search_customers = array(array('id' => 0, 'text' => 'Please Select'));
    while (!$customers->EOF) {
      $search_customers[] = array('id' => $customers->fields['customers_id'], 'text' => $customers->fields['customers_lastname'] . ', ' . $customers->fields['customers_firstname']);
      $customers->MoveNext();
    }
  }
  // get all products that have subscriptions
  $products_query = "SELECT DISTINCT(pr.products_id), pd.products_name 
                     FROM " . TABLE_PAYPAL_RECURRING . " pr 
                     LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON (pd.products_id = pr.products_id) 
                     GROUP BY pr.products_id 
                     ORDER BY pd.products_name ASC;";
  $products = $db->Execute($products_query);
  $search_products = array();
  if ($products->RecordCount() > 0) {
    $search_products = array(array('id' => 0, 'text' => 'Please Select'));
    while (!$products->EOF) {
      $search_products[] = array('id' => $products->fields['products_id'], 'text' => $products->fields['products_name']);
      $products->MoveNext();
    }
  }
  // get all unique statuses
  $status_query = "SELECT DISTINCT(status) 
                   FROM " . TABLE_PAYPAL_RECURRING . "
                   WHERE status IS NOT NULL
                   GROUP BY status 
                   ORDER BY status ASC;";
  $status = $db->Execute($status_query);
  $search_status = array();
  if ($status->RecordCount() > 0) {
    $search_status = array(array('id' => 0, 'text' => 'Please Select'));
    while (!$status->EOF) {
      $search_status[] = array('id' => $status->fields['status'], 'text' => $status->fields['status']);
      $status->MoveNext();
    }
  }    
  require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
  $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
  $PayPal = new PayPal($PayPalConfig);

  require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
  require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php');
  require_once(DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/debug.php');
  if (function_exists('zen_paypal_subscription_cache_ensure_schema')) {
    zen_paypal_subscription_cache_ensure_schema();
    if (function_exists('zen_paypal_subscription_refresh_events_ensure_schema')) {
      zen_paypal_subscription_refresh_events_ensure_schema();
    }
  }
  $paypalSavedCardRecurring = new paypalSavedCardRecurring();
  $paypalRestClient = $paypalSavedCardRecurring->get_paypal_rest_client();
  $paypalLegacyClient = ($PayPal instanceof PayPal) ? $PayPal : $paypalSavedCardRecurring->get_paypal_legacy_client();
  $PayPalProfileManager = PayPalProfileManager::create($paypalRestClient, $paypalLegacyClient);

  if (isset($_GET['ajax'])) {
    $ajaxAction = $_GET['ajax'];
    if ($ajaxAction === 'refresh_profile') {
      $manualRefreshPost = (isset($_POST['manual_refresh']) && $_POST['manual_refresh'] === '1');
      if (!$manualRefreshPost) {
        header('Content-Type: application/json; charset=utf-8');
      }

      $jsonResponse = array(
        'success' => false,
        'subscription' => null,
      );

      $manualRedirectUrl = zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'SSL');

      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        $jsonResponse['message'] = 'Method not allowed.';
        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED, 'error');
          zen_redirect($manualRedirectUrl);
          exit;
        }
        echo json_encode($jsonResponse);
        exit;
      }

      $requestToken = isset($_POST['securityToken']) ? $_POST['securityToken'] : '';
      if (!isset($_SESSION['securityToken']) || $requestToken !== $_SESSION['securityToken']) {
        http_response_code(403);
        $jsonResponse['message'] = 'Security token validation failed.';
        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED, 'error');
          zen_redirect($manualRedirectUrl);
          exit;
        }
        echo json_encode($jsonResponse);
        exit;
      }

      $rawPayload = file_get_contents('php://input');
      $decodedPayload = json_decode($rawPayload, true);

      $profileId = '';
      if (is_array($decodedPayload) && isset($decodedPayload['profileId'])) {
        $profileId = $decodedPayload['profileId'];
      } elseif (isset($_POST['profileId'])) {
        $profileId = $_POST['profileId'];
      }
      if (!is_string($profileId)) {
        $profileId = '';
      }
      $profileId = trim($profileId);

      $customerId = 0;
      if (is_array($decodedPayload) && isset($decodedPayload['customerId'])) {
        $customerId = (int) $decodedPayload['customerId'];
      } elseif (isset($_POST['customerId'])) {
        $customerId = (int) $_POST['customerId'];
      }

      if ($profileId === '' || $customerId <= 0) {
        http_response_code(400);
        $jsonResponse['message'] = 'A customer ID and profile ID are required.';
        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED, 'error');
          zen_redirect($manualRedirectUrl);
          exit;
        }
        echo json_encode($jsonResponse);
        exit;
      }

      $subscriptionQuery = $db->Execute(
        'SELECT *'
        . ' FROM ' . TABLE_PAYPAL_RECURRING
        . ' WHERE customers_id = ' . (int) $customerId
        . "   AND profile_id = '" . zen_db_input($profileId) . "'"
        . ' LIMIT 1;'
      );

      if ($subscriptionQuery->EOF) {
        http_response_code(404);
        $jsonResponse['message'] = 'Subscription not found.';
        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED, 'error');
          zen_redirect($manualRedirectUrl);
          exit;
        }
        echo json_encode($jsonResponse);
        exit;
      }

      $subscriptionRow = $subscriptionQuery->fields;
      $subscriptionRow['subscription_id'] = isset($subscriptionRow['subscription_id']) ? (int) $subscriptionRow['subscription_id'] : 0;

      $cacheTable = zen_paypal_subscription_cache_table_name();
      $cacheRow = $db->Execute(
        'SELECT status AS cache_status, profile_source AS cache_profile_source, profile_data, refreshed_at AS cache_refreshed_at'
        . ' FROM ' . $cacheTable
        . ' WHERE customers_id = ' . (int) $customerId
        . "   AND profile_id = '" . zen_db_input($profileId) . "'"
        . ' LIMIT 1;'
      );

      if (!$cacheRow->EOF) {
        $subscriptionRow['cache_status'] = $cacheRow->fields['cache_status'];
        $subscriptionRow['cache_profile_source'] = $cacheRow->fields['cache_profile_source'];
        $subscriptionRow['profile_data'] = $cacheRow->fields['profile_data'];
        $subscriptionRow['cache_refreshed_at'] = $cacheRow->fields['cache_refreshed_at'];
      }

      $refreshOutcome = zen_paypal_subscription_refresh_profile_now(
        $customerId,
        $profileId,
        array(
          'subscription' => $subscriptionRow,
          'profile_manager' => $PayPalProfileManager,
        )
      );

      if (is_array($refreshOutcome) && !empty($refreshOutcome['success'])) {
        if (isset($refreshOutcome['classification']) && is_array($refreshOutcome['classification'])) {
          zen_paypal_subscription_apply_classification($subscriptionRow, $refreshOutcome['classification']);
          if (isset($refreshOutcome['classification']['profile']) && is_array($refreshOutcome['classification']['profile'])) {
            $encodedProfile = json_encode($refreshOutcome['classification']['profile']);
            if ($encodedProfile !== false) {
              $subscriptionRow['profile_data'] = $encodedProfile;
            }
          }
          if (isset($refreshOutcome['classification']['status'])) {
            $subscriptionRow['cache_status'] = $refreshOutcome['classification']['status'];
          }
          if (isset($refreshOutcome['classification']['profile_source'])) {
            $subscriptionRow['cache_profile_source'] = $refreshOutcome['classification']['profile_source'];
          }
          if (isset($refreshOutcome['classification']['refreshed_at'])) {
            $subscriptionRow['cache_refreshed_at'] = $refreshOutcome['classification']['refreshed_at'];
          }
        }

        $latestCache = $db->Execute(
          'SELECT status AS cache_status, profile_source AS cache_profile_source, profile_data, refreshed_at AS cache_refreshed_at'
          . ' FROM ' . $cacheTable
          . ' WHERE customers_id = ' . (int) $customerId
          . "   AND profile_id = '" . zen_db_input($profileId) . "'"
          . ' LIMIT 1;'
        );
        if (!$latestCache->EOF) {
          $subscriptionRow['cache_status'] = $latestCache->fields['cache_status'];
          $subscriptionRow['cache_profile_source'] = $latestCache->fields['cache_profile_source'];
          $subscriptionRow['profile_data'] = $latestCache->fields['profile_data'];
          $subscriptionRow['cache_refreshed_at'] = $latestCache->fields['cache_refreshed_at'];
        }

        $snapshot = zen_paypal_subscription_build_snapshot($subscriptionRow);
        $jsonResponse['subscription'] = $snapshot;
        $jsonResponse['success'] = true;
        $jsonResponse['message'] = TEXT_PAYPAL_SUBSCRIPTION_REFRESH_SUCCESS;

        $adminId = 0;
        if (isset($_SESSION['admin_id'])) {
          $adminId = (int) $_SESSION['admin_id'];
        } elseif (isset($_SESSION['login_id'])) {
          $adminId = (int) $_SESSION['login_id'];
        }

        zen_paypal_subscription_refresh_events_log(
          $customerId,
          $profileId,
          array(
            'source' => 'admin_manual',
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'context' => array(
              'page' => 'paypal_subscriptions',
              'subscription_id' => $snapshot['subscription_id'],
              'status' => $snapshot['status'],
              'profile_source' => $snapshot['profile_source'],
            ),
          )
        );

        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_SUCCESS, 'success');
          zen_redirect($manualRedirectUrl);
          exit;
        }
      } else {
        http_response_code(500);
        $jsonResponse['message'] = TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED;
        if ($manualRefreshPost) {
          $messageStack->add_session($messageStackKey, TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED, 'error');
          zen_redirect($manualRedirectUrl);
          exit;
        }
      }

      echo json_encode($jsonResponse);
      exit;
    } elseif ($ajaxAction === 'subscription_snapshot') {
      header('Content-Type: application/json; charset=utf-8');

      $jsonResponse = array(
        'success' => false,
        'subscription' => null,
      );

      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        $jsonResponse['message'] = 'Method not allowed.';
        echo json_encode($jsonResponse);
        exit;
      }

      $requestToken = isset($_POST['securityToken']) ? $_POST['securityToken'] : '';
      if (!isset($_SESSION['securityToken']) || $requestToken !== $_SESSION['securityToken']) {
        http_response_code(403);
        $jsonResponse['message'] = 'Security token validation failed.';
        echo json_encode($jsonResponse);
        exit;
      }

      $rawPayload = file_get_contents('php://input');
      $decodedPayload = json_decode($rawPayload, true);

      $profileId = '';
      if (is_array($decodedPayload) && isset($decodedPayload['profileId'])) {
        $profileId = $decodedPayload['profileId'];
      } elseif (isset($_POST['profileId'])) {
        $profileId = $_POST['profileId'];
      }
      if (!is_string($profileId)) {
        $profileId = '';
      }
      $profileId = trim($profileId);

      $customerId = 0;
      if (is_array($decodedPayload) && isset($decodedPayload['customerId'])) {
        $customerId = (int) $decodedPayload['customerId'];
      } elseif (isset($_POST['customerId'])) {
        $customerId = (int) $_POST['customerId'];
      }

      if ($profileId === '' || $customerId <= 0) {
        http_response_code(400);
        $jsonResponse['message'] = 'A customer ID and profile ID are required.';
        echo json_encode($jsonResponse);
        exit;
      }

      $subscriptionQuery = $db->Execute(
        'SELECT *'
        . ' FROM ' . TABLE_PAYPAL_RECURRING
        . ' WHERE customers_id = ' . (int) $customerId
        . "   AND profile_id = '" . zen_db_input($profileId) . "'"
        . ' LIMIT 1;'
      );

      if ($subscriptionQuery->EOF) {
        http_response_code(404);
        $jsonResponse['message'] = 'Subscription not found.';
        echo json_encode($jsonResponse);
        exit;
      }

      $subscriptionRow = $subscriptionQuery->fields;
      $subscriptionRow['subscription_id'] = isset($subscriptionRow['subscription_id']) ? (int) $subscriptionRow['subscription_id'] : 0;

      $cacheTable = zen_paypal_subscription_cache_table_name();
      $cacheRow = $db->Execute(
        'SELECT status AS cache_status, profile_source AS cache_profile_source, profile_data, refreshed_at AS cache_refreshed_at'
        . ' FROM ' . $cacheTable
        . ' WHERE customers_id = ' . (int) $customerId
        . "   AND profile_id = '" . zen_db_input($profileId) . "'"
        . ' LIMIT 1;'
      );

      if (!$cacheRow->EOF) {
        $subscriptionRow['cache_status'] = $cacheRow->fields['cache_status'];
        $subscriptionRow['cache_profile_source'] = $cacheRow->fields['cache_profile_source'];
        $subscriptionRow['profile_data'] = $cacheRow->fields['profile_data'];
        $subscriptionRow['cache_refreshed_at'] = $cacheRow->fields['cache_refreshed_at'];
      }

      $snapshot = zen_paypal_subscription_build_snapshot($subscriptionRow);
      $jsonResponse['success'] = true;
      $jsonResponse['subscription'] = $snapshot;

      echo json_encode($jsonResponse);
      exit;
    }
  }

  if (!function_exists('zen_paypal_subscription_profile_value')) {
    function zen_paypal_subscription_profile_value($profile, array $path)
    {
      $value = $profile;
      foreach ($path as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
          $value = $value[$segment];
        } else {
          return null;
        }
      }
      return $value;
    }
  }

  if (!function_exists('zen_paypal_subscription_profile_find')) {
    function zen_paypal_subscription_profile_find($profile, array $paths)
    {
      foreach ($paths as $path) {
        $value = zen_paypal_subscription_profile_value($profile, $path);
        if ($value !== null && $value !== '') {
          return $value;
        }
      }
      return null;
    }
  }

  if (!function_exists('zen_paypal_subscription_profile_date')) {
    function zen_paypal_subscription_profile_date($profile, array $paths)
    {
      $raw = zen_paypal_subscription_profile_find($profile, $paths);
      if (!is_string($raw) || strlen($raw) === 0) {
        return '';
      }
      $raw = str_replace('Z', '', $raw);
      $parts = explode('T', $raw);
      return $parts[0];
    }
  }

  if (!function_exists('zen_paypal_subscription_profile_amount')) {
    function zen_paypal_subscription_profile_amount($profile, array $paths)
    {
      $raw = zen_paypal_subscription_profile_find($profile, $paths);
      if (is_array($raw) && array_key_exists('value', $raw)) {
        return $raw['value'];
      }
      if (is_scalar($raw)) {
        return (string) $raw;
      }
      return '';
    }
  }

  if (!function_exists('zen_paypal_subscription_profile_currency')) {
    function zen_paypal_subscription_profile_currency($profile, array $paths = array())
    {
      if (empty($paths)) {
        $paths = array(
          array('CURRENCYCODE'),
          array('currency'),
          array('currency_code'),
          array('currencyCode'),
          array('amount', 'currency_code'),
          array('amount', 'currency'),
          array('billing_info', 'last_payment', 'amount', 'currency_code'),
          array('billing_info', 'outstanding_balance', 'currency_code'),
          array('billing_info', 'cycle_executions', 0, 'total_amount', 'currency_code'),
          array('plan_overview', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'currency_code'),
          array('plan', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'currency_code'),
        );
      }
      $value = zen_paypal_subscription_profile_find($profile, $paths);
      if (is_array($value) && array_key_exists('currency_code', $value)) {
        $value = $value['currency_code'];
      }
      if (!is_string($value) || strlen($value) === 0) {
        return '';
      }
      return strtoupper($value);
    }
  }

  if (!function_exists('zen_paypal_subscription_format_status')) {
    function zen_paypal_subscription_format_status($status)
    {
      if (!is_string($status) || strlen($status) === 0) {
        return '';
      }
      $status = str_replace('_', ' ', $status);
      $status = strtolower($status);
      return ucwords($status);
    }
  }

  if (!function_exists('zen_paypal_subscription_status_context')) {
    function zen_paypal_subscription_status_context($status)
    {
      $normalized = strtoupper(trim((string)$status));
      if ($normalized === '') {
        return 'unknown';
      }
      $activeStates = array('ACTIVE', 'APPROVED', 'APPROVAL_PENDING', 'CREATED');
      $suspendedStates = array('SUSPENDED', 'PAUSED');
      $pendingStates = array('PENDING', 'SUSPENDED_PENDING');
      $cancelledStates = array('CANCELLED', 'CANCELED', 'DEACTIVATED', 'EXPIRED', 'COMPLETED');
      if (in_array($normalized, $activeStates, true)) {
        return 'active';
      }
      if (in_array($normalized, $suspendedStates, true)) {
        return 'suspended';
      }
      if (in_array($normalized, $pendingStates, true)) {
        return 'pending';
      }
      if (in_array($normalized, $cancelledStates, true)) {
        return 'cancelled';
      }
      return 'unknown';
    }
  }

  if (!function_exists('zen_paypal_subscription_admin_format_amount')) {
    function zen_paypal_subscription_admin_format_amount($value, $currencyCode = '')
    {
      global $currencies;

      if ($value === null || $value === '') {
        return '';
      }
      if (!is_object($currencies)) {
        return $value;
      }
      if (strlen($currencyCode) > 0) {
        return $currencies->format($value, true, $currencyCode);
      }
      return $currencies->format($value);
    }
  }

  if (!function_exists('zen_paypal_subscription_admin_fetch_profile')) {
    function zen_paypal_subscription_admin_fetch_profile(array $subscription, PayPalProfileManager $manager, array $options = array())
    {
      $forceRefresh = !empty($options['force_refresh']);
      $cacheOptions = array();
      if ($forceRefresh) {
        $cacheOptions['force_refresh'] = true;
      }
      if (isset($options['cache_ttl'])) {
        $cacheOptions['cache_ttl'] = (int) $options['cache_ttl'];
      }

      $classification = zen_paypal_subscription_classify_profile($subscription, $manager, $cacheOptions);
      if (!is_array($classification)) {
        $classification = array();
      }

      if (empty($classification) && !$forceRefresh) {
        $classification = zen_paypal_subscription_classify_profile($subscription, $manager, array('force_refresh' => true));
        if (!is_array($classification)) {
          $classification = array();
        }
      }

      $profile = array();
      if (isset($classification['profile']) && is_array($classification['profile'])) {
        $profile = $classification['profile'];
      }

      $status = '';
      if (isset($classification['status']) && is_string($classification['status'])) {
        $status = $classification['status'];
      }
      if ($status === '') {
        $status = zen_paypal_subscription_profile_find($profile, array(array('STATUS'), array('status')));
        if ($status === null && isset($subscription['status'])) {
          $status = $subscription['status'];
        }
        if ($status === null) {
          $status = '';
        }
      }

      $profileId = zen_paypal_subscription_profile_find($profile, array(array('PROFILEID'), array('profile_id'), array('id')));
      if ($profileId === null && isset($subscription['profile_id'])) {
        $profileId = $subscription['profile_id'];
      }
      $profileId = is_scalar($profileId) ? (string) $profileId : '';

      $profileSource = '';
      if (isset($classification['profile_source']) && is_string($classification['profile_source'])) {
        $profileSource = strtolower($classification['profile_source']);
      }

      $errors = array();
      if (isset($classification['errors']) && is_array($classification['errors'])) {
        $errors = $classification['errors'];
      } elseif (isset($profile['ERRORS']) && is_array($profile['ERRORS'])) {
        $errors = $profile['ERRORS'];
      }

      return array(
        'raw' => $classification,
        'profile' => $profile,
        'status' => $status,
        'profile_id' => $profileId,
        'profile_source' => $profileSource,
        'errors' => $errors,
      );
    }
  }

  if (!function_exists('zen_paypal_subscription_admin_normalize_profile')) {
    function zen_paypal_subscription_admin_normalize_profile(array $subscription, array $profileData)
    {
      $profile = isset($profileData['profile']) && is_array($profileData['profile']) ? $profileData['profile'] : array();
      $startDate = zen_paypal_subscription_profile_date($profile, array(
        array('PROFILESTARTDATE'),
        array('start_time'),
        array('billing_info', 'cycle_executions', 0, 'time'),
        array('billing_info', 'last_payment', 'time'),
      ));
      $nextDate = zen_paypal_subscription_profile_date($profile, array(
        array('NEXTBILLINGDATE'),
        array('billing_info', 'next_billing_time'),
        array('billing_info', 'cycle_executions', 0, 'next_billing_time'),
        array('next_billing_time'),
      ));
      $currencyCode = zen_paypal_subscription_profile_currency($profile);
      $amount = zen_paypal_subscription_profile_amount($profile, array(
        array('AMT'),
        array('amount', 'value'),
        array('billing_info', 'last_payment', 'amount', 'value'),
        array('billing_info', 'cycle_executions', 0, 'total_amount', 'value'),
        array('plan_overview', 'billing_cycles', 0, 'pricing_scheme', 'fixed_price', 'value'),
      ));
      $outstanding = zen_paypal_subscription_profile_amount($profile, array(
        array('OUTSTANDINGBALANCE'),
        array('billing_info', 'outstanding_balance', 'value'),
      ));
      $paymentsCompleted = zen_paypal_subscription_profile_amount($profile, array(
        array('NUMCYCLESCOMPLETED'),
        array('billing_info', 'cycle_executions', 0, 'cycles_completed'),
      ));
      $paymentsRemaining = zen_paypal_subscription_profile_amount($profile, array(
        array('NUMCYCLESREMAINING'),
        array('billing_info', 'cycle_executions', 0, 'cycles_remaining'),
      ));

      if ($currencyCode === '' && isset($subscription['currency'])) {
        $currencyCode = (string)$subscription['currency'];
      }

      $paymentMethod = 'PayPal';
      if (isset($profile['CREDITCARDTYPE']) && isset($profile['ACCT'])) {
        $paymentMethod = $profile['CREDITCARDTYPE'] . ' ' . $profile['ACCT'];
      } elseif (isset($profile['payment_source']['card']['brand']) && isset($profile['payment_source']['card']['last_digits'])) {
        $paymentMethod = $profile['payment_source']['card']['brand'] . ' ' . $profile['payment_source']['card']['last_digits'];
      }

      $statusRaw = isset($profileData['status']) ? $profileData['status'] : '';
      $statusDisplay = zen_paypal_subscription_format_status($statusRaw);
      if ($statusDisplay === '' && isset($subscription['status']) && is_string($subscription['status'])) {
        $statusDisplay = zen_paypal_subscription_format_status($subscription['status']);
      }
      if ($statusDisplay === '') {
        $statusDisplay = 'Unknown';
      }
      $statusContext = zen_paypal_subscription_status_context($statusRaw);
      $profileId = isset($profileData['profile_id']) ? $profileData['profile_id'] : '';
      $profileSource = isset($profileData['profile_source']) ? $profileData['profile_source'] : '';
      $isRest = ($profileSource === 'rest');
      if (!$isRest && empty($profileSource) && isset($profile['plan_id'])) {
        $isRest = true;
        $profileSource = 'rest';
      }
      $errors = isset($profileData['errors']) && is_array($profileData['errors']) ? $profileData['errors'] : array();

      $refreshedAt = '';
      if (isset($subscription['cache_refreshed_at']) && $subscription['cache_refreshed_at'] !== null && $subscription['cache_refreshed_at'] !== '') {
        $refreshedAt = $subscription['cache_refreshed_at'];
      } elseif (isset($subscription['classification_refreshed_at']) && $subscription['classification_refreshed_at'] !== null && $subscription['classification_refreshed_at'] !== '') {
        $refreshedAt = $subscription['classification_refreshed_at'];
      } elseif (isset($subscription['refreshed_at']) && $subscription['refreshed_at'] !== null && $subscription['refreshed_at'] !== '') {
        $refreshedAt = $subscription['refreshed_at'];
      }

      return array(
        'profile' => $profile,
        'profile_id' => $profileId,
        'currency_code' => $currencyCode,
        'amount' => $amount,
        'amount_formatted' => zen_paypal_subscription_admin_format_amount($amount, $currencyCode),
        'outstanding_balance' => $outstanding,
        'outstanding_formatted' => zen_paypal_subscription_admin_format_amount($outstanding, $currencyCode),
        'payments_completed' => ($paymentsCompleted !== '' ? $paymentsCompleted : '0'),
        'payments_remaining' => ($paymentsRemaining !== '' ? $paymentsRemaining : '0'),
        'start_date' => $startDate,
        'next_date' => $nextDate,
        'expiration_date' => isset($subscription['expiration_date']) ? $subscription['expiration_date'] : '',
        'payment_method' => $paymentMethod,
        'status_raw' => $statusRaw,
        'status_display' => $statusDisplay,
        'status_context' => $statusContext,
        'profile_source' => $profileSource,
        'is_rest' => $isRest,
        'errors' => $errors,
        'can_edit' => (!$isRest && count($errors) === 0),
        'refreshed_at' => $refreshedAt,
      );
    }
  }

  if (!function_exists('zen_paypal_subscription_admin_format_error_details')) {
    function zen_paypal_subscription_admin_format_error_details($details)
    {
      if (!is_array($details) || count($details) === 0) {
        return '';
      }
      $queue = array_values($details);
      $messages = array();
      while (!empty($queue)) {
        $detail = array_shift($queue);
        if (!is_array($detail)) {
          continue;
        }
        if (isset($detail['details']) && is_array($detail['details'])) {
          foreach ($detail['details'] as $nested) {
            $queue[] = $nested;
          }
        }
        $parts = array();
        if (isset($detail['field']) && is_scalar($detail['field']) && $detail['field'] !== '') {
          $parts[] = (string) $detail['field'];
        }
        if (isset($detail['issue']) && is_scalar($detail['issue']) && $detail['issue'] !== '') {
          $parts[] = (string) $detail['issue'];
        }
        if (isset($detail['description']) && is_scalar($detail['description']) && $detail['description'] !== '') {
          $parts[] = (string) $detail['description'];
        }
        if (isset($detail['value']) && is_scalar($detail['value']) && $detail['value'] !== '') {
          $parts[] = 'Value: ' . (string) $detail['value'];
        }
        if (count($parts) > 0) {
          $messages[] = implode(' - ', $parts);
        }
      }
      if (count($messages) === 0) {
        return '';
      }
      $output = '<ul class="paypal-subscription-errors">';
      foreach ($messages as $message) {
        $output .= '<li>' . htmlspecialchars($message) . '</li>';
      }
      $output .= '</ul>';
      return $output;
    }
  }
  
  switch($_GET['action']) {
    case 'cancel_confirm':
    case 'suspend_confirm':
    case 'delete_confirm':
      break;
    case 'cancel':
      if (isset($_GET['profileid']) && isset($_GET['customers_id'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_GET['customers_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          $cancelStart = microtime(true);
          $cancelResult = zen_paypal_subscription_cancel_immediately(
            (int) $_GET['customers_id'],
            $subscription->fields['profile_id'],
            array(
              'note' => 'Cancelled by admin.',
              'source' => 'admin',
              'subscription' => $subscription->fields,
              'profile_manager' => $PayPalProfileManager,
              'saved_card_recurring' => $paypalSavedCardRecurring,
            )
          );
          $cancelElapsed = microtime(true) - $cancelStart;

          if (!empty($cancelResult['success'])) {
            $messageStack->add_session($messageStackKey, 'The subscription has been cancelled.', 'success');
          } else {
            $errorMessage = 'The subscription could not be cancelled.';
            if (!empty($cancelResult['message'])) {
              $errorMessage .= '<br />' . htmlspecialchars($cancelResult['message']);
            } else {
              $errorMessage .= '<br />Please review your PayPal settings and try again.';
            }
            if (!$paypalRestClient) {
              $errorMessage .= '<br />PayPal REST credentials are not configured; attempted to use the legacy API instead.';
            }
            $messageStack->add_session($messageStackKey, $errorMessage, 'error');
          }

        } else {
          $messageStack->add_session($messageStackKey, 'A matching subscription could not be found.', 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . (int)$_GET['customers_id'], 'SSL'));
      } else {
        $messageStack->add_session($messageStackKey, 'Required information missing.', 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
      }
      break;
    case 'suspend':
      if (isset($_GET['profileid']) && isset($_GET['customers_id'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_GET['customers_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          // subscription exists for this customer, suspend it
          $result = $PayPalProfileManager->suspendProfile($subscription->fields, 'Suspended by admin.');
          if (!empty($result['success'])) {
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Suspended' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
            $paypalSavedCardRecurring->remove_group_pricing((int)$_GET['customers_id'], $subscription->fields['products_id']);
            zen_paypal_subscription_cache_invalidate((int) $_GET['customers_id'], $subscription->fields['profile_id']);
            $messageStack->add_session($messageStackKey, 'The subscription has been suspended.', 'success');
          } else {
            $errorMessage = 'The subscription could not be suspended.';
            if (!empty($result['message'])) {
              $errorMessage .= '<br />' . htmlspecialchars($result['message']);
            } else {
              $errorMessage .= '<br />Please review your PayPal settings and try again.';
            }
            if (!$paypalRestClient) {
              $errorMessage .= '<br />PayPal REST credentials are not configured; attempted to use the legacy API instead.';
            }
            $messageStack->add_session($messageStackKey, $errorMessage, 'error');
          }
        } else {
          $messageStack->add_session($messageStackKey, 'A matching subscription could not be found.', 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . (int)$_GET['customers_id'], 'SSL'));
      } else {
        $messageStack->add_session($messageStackKey, 'Required information missing.', 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
      }    
      break;
    case 'reactivate':
      if (isset($_GET['profileid'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_GET['customers_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          // subscription exists for this customer, reactivate it
          $result = $PayPalProfileManager->reactivateProfile($subscription->fields, 'Reactivated by admin.');
          if (!empty($result['success'])) {
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Active' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
            $paypalSavedCardRecurring->create_group_pricing($subscription->fields['products_id'], (int)$_GET['customers_id']);
            zen_paypal_subscription_cache_invalidate((int) $_GET['customers_id'], $subscription->fields['profile_id']);
            $messageStack->add_session($messageStackKey, 'The subscription has been reactivated.', 'success');
          } else {
            $errorMessage = 'The subscription could not be reactivated.';
            if (!empty($result['message'])) {
              $errorMessage .= '<br />' . htmlspecialchars($result['message']);
            } else {
              $errorMessage .= '<br />Please review your PayPal settings and try again.';
            }
            if (!$paypalRestClient) {
              $errorMessage .= '<br />PayPal REST credentials are not configured; attempted to use the legacy API instead.';
            }
            $messageStack->add_session($messageStackKey, $errorMessage, 'error');
          }
        } else {
          $messageStack->add_session($messageStackKey, 'A matching subscription could not be found.', 'error');
        }
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . (int)$_GET['customers_id'], 'SSL'));
      } else {
        $messageStack->add_session($messageStackKey, 'Required information missing.', 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
      }    
      break;
    case 'delete':
      if (isset($_GET['subscription_id']) && isset($_GET['customers_id'])) {
        $subscriptionId = (int) $_GET['subscription_id'];
        $customerId = (int) $_GET['customers_id'];

        $db->Execute("DELETE FROM " . TABLE_PAYPAL_RECURRING . " WHERE subscription_id = " . $subscriptionId . " AND customers_id = " . $customerId . " LIMIT 1;");
        $db->Execute('DELETE FROM ' . TABLE_PAYPAL_RECURRING_ARCHIVE . ' WHERE subscription_id = ' . $subscriptionId . ' AND customers_id = ' . $customerId . ';');

        $messageStack->add_session($messageStackKey, 'Subscription deleted from the database.', 'success');
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . $customerId, 'SSL'));
      } else {
        $messageStack->add_session($messageStackKey, 'Required information missing.', 'error');
        zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'SSL'));
      }       
      break;
    case 'search_products':
      if (isset($_GET['products_id'])) {
        // GET ALL SUBSCRIPTIONS
        $subscriptions_query = "SELECT * 
                                FROM " . TABLE_PAYPAL_RECURRING . " 
                                WHERE products_id = " . (int)$_GET['products_id'] . "
                                ORDER BY subscription_id DESC;";
        $subscriptions = $db->Execute($subscriptions_query);
      }
      break;
    case 'search_status':
      if (isset($_GET['status'])) {
        // GET ALL SUBSCRIPTIONS
        $subscriptions_query = "SELECT * 
                                FROM " . TABLE_PAYPAL_RECURRING . " 
                                WHERE status = '" . $_GET['status'] . "'
                                ORDER BY subscription_id DESC;";
        $subscriptions = $db->Execute($subscriptions_query);        
      }
      break;
    case 'show_report':
      $report_query = "SELECT COUNT(subscription_id) as num_subscriptions, YEAR(expiration_date) AS expiration_year  
                       FROM " . TABLE_PAYPAL_RECURRING . "
                       GROUP BY expiration_year 
                       ORDER BY expiration_year DESC;";
      $report = $db->Execute($report_query);
      break;
    case 'edit':
        // GET SUBSCRIPTION
        $subscriptions_query = "SELECT * 
                                FROM " . TABLE_PAYPAL_RECURRING . " 
                                WHERE customers_id = " . (int)$_GET['customers_id'] . "
                                AND profile_id = '" . $_GET['profileid'] . "'
                                LIMIT 1;";
        $subscriptions = $db->Execute($subscriptions_query);
      break;
    case 'update':
        $subscriptionData = null;
        $profileIdValue = isset($_POST['profileid']) ? zen_db_prepare_input($_POST['profileid']) : '';
        if ($profileIdValue !== '') {
          $subscription = null;
          $profileLookup = "SELECT * FROM " . TABLE_PAYPAL_RECURRING . " WHERE profile_id = :profileId LIMIT 1";
          $profileLookup = $db->bindVars($profileLookup, ':profileId', $profileIdValue, 'string');
          $subscriptionData = $db->Execute($profileLookup);
        }
        if (is_object($subscriptionData) && $subscriptionData->RecordCount() > 0) {
          $subscription = $subscriptionData->fields;
          $profileData = zen_paypal_subscription_admin_fetch_profile($subscription, $PayPalProfileManager, array('force_refresh' => true));
          $normalizedProfile = zen_paypal_subscription_admin_normalize_profile($subscription, $profileData);
          if (!empty($normalizedProfile['is_rest'])) {
            $restCurrency = isset($_POST['rest_currency']) ? zen_db_prepare_input($_POST['rest_currency']) : $normalizedProfile['currency_code'];
            $restCurrency = strtoupper(trim($restCurrency));
            $restAmountRaw = isset($_POST['rest_amount']) ? zen_db_prepare_input($_POST['rest_amount']) : '';
            $restNextBillingRaw = isset($_POST['rest_next_billing_date']) ? zen_db_prepare_input($_POST['rest_next_billing_date']) : '';
            $restTokenId = isset($_POST['rest_payment_source_token_id']) ? zen_db_prepare_input($_POST['rest_payment_source_token_id']) : '';
            $restVaultId = isset($_POST['rest_payment_source_vault_id']) ? zen_db_prepare_input($_POST['rest_payment_source_vault_id']) : '';
            $restPaypalEmail = isset($_POST['rest_payment_source_paypal_email']) ? zen_db_prepare_input($_POST['rest_payment_source_paypal_email']) : '';

            $errors = array();
            $amountValue = null;
            $amountRawTrimmed = trim((string)$restAmountRaw);
            if ($amountRawTrimmed !== '') {
              $normalizedAmount = str_replace(array(',', ' '), '', $amountRawTrimmed);
              if (!is_numeric($normalizedAmount)) {
                $errors[] = 'Please enter a valid amount.';
              } else {
                $amountValue = number_format((float)$normalizedAmount, 2, '.', '');
                if ($restCurrency === '') {
                  $errors[] = 'A currency code is required when updating the amount.';
                }
              }
            }
            if ($restCurrency === '' && $normalizedProfile['currency_code'] !== '') {
              $restCurrency = strtoupper($normalizedProfile['currency_code']);
            }

            $restNextBillingIso = null;
            $nextBillingTrimmed = trim((string)$restNextBillingRaw);
            if ($nextBillingTrimmed !== '') {
              if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $nextBillingTrimmed, $matches)) {
                $restNextBillingIso = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . 'T00:00:00Z';
              } else {
                $timestamp = strtotime($nextBillingTrimmed);
                if ($timestamp === false) {
                  $errors[] = 'Please provide a valid next billing date (YYYY-MM-DD or ISO 8601).';
                } else {
                  $restNextBillingIso = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
                }
              }
            }

            $restTokenId = trim((string)$restTokenId);
            $restVaultId = trim((string)$restVaultId);
            $restPaypalEmail = trim((string)$restPaypalEmail);
            $providedSources = 0;
            if ($restTokenId !== '') {
              $providedSources++;
            }
            if ($restVaultId !== '') {
              $providedSources++;
            }
            if ($restPaypalEmail !== '') {
              if (function_exists('zen_validate_email') && !zen_validate_email($restPaypalEmail)) {
                $errors[] = 'Please provide a valid PayPal email address.';
              }
              $providedSources++;
            }
            if ($providedSources > 1) {
              $errors[] = 'Please update one payment source field at a time.';
            }

            if (count($errors) > 0) {
              foreach ($errors as $errorMessage) {
                $messageStack->add_session($messageStackKey, htmlspecialchars($errorMessage), 'error');
              }
              zen_redirect(zen_href_link(
                FILENAME_PAYPAL_SUBSCRIPTIONS,
                'action=edit&profileid=' . urlencode($profileIdValue) . '&customers_id=' . (int)$_POST['customers_id'],
                'SSL'
              ));
            }

            $billingCyclesPayload = array();
            if ($amountValue !== null) {
              $billingCyclesPayload['pricing_scheme'] = array(
                'currency_code' => $restCurrency,
                'value' => $amountValue
              );
            }
            if ($restNextBillingIso !== null) {
              $billingCyclesPayload['next_billing_time'] = $restNextBillingIso;
            }

            $paymentSourcePayload = array();
            if ($restTokenId !== '') {
              $paymentSourcePayload['payment_source']['token'] = array('id' => $restTokenId);
            } elseif ($restVaultId !== '') {
              $paymentSourcePayload['payment_source']['card'] = array('vault_id' => $restVaultId);
            } elseif ($restPaypalEmail !== '') {
              $paymentSourcePayload['payment_source']['paypal'] = array('email_address' => $restPaypalEmail);
            }

            $updatesRequested = (count($billingCyclesPayload) > 0) || (count($paymentSourcePayload) > 0);
            if (!$updatesRequested) {
              $messageStack->add_session($messageStackKey, 'No changes were submitted for this subscription.', 'warning');
              zen_redirect(zen_href_link(
                FILENAME_PAYPAL_SUBSCRIPTIONS,
                'action=edit&profileid=' . urlencode($profileIdValue) . '&customers_id=' . (int)$_POST['customers_id'],
                'SSL'
              ));
            }

            $billingResult = array('success' => true);
            if (count($billingCyclesPayload) > 0) {
              $billingResult = $PayPalProfileManager->updateBillingCycles($subscriptionData->fields, $billingCyclesPayload);
              if (empty($billingResult['success'])) {
                $errorMessage = !empty($billingResult['message']) ? htmlspecialchars($billingResult['message']) : 'The subscription could not be updated.';
                if (isset($billingResult['details'])) {
                  $detailsHtml = zen_paypal_subscription_admin_format_error_details($billingResult['details']);
                  if ($detailsHtml !== '') {
                    $errorMessage .= '<br />' . $detailsHtml;
                  }
                }
                $messageStack->add_session($messageStackKey, $errorMessage, 'error');
                zen_redirect(zen_href_link(
                  FILENAME_PAYPAL_SUBSCRIPTIONS,
                  'action=edit&profileid=' . urlencode($profileIdValue) . '&customers_id=' . (int)$_POST['customers_id'],
                  'SSL'
                ));
              }
            }

            if (count($paymentSourcePayload) > 0) {
              $paymentResult = $PayPalProfileManager->updatePaymentSource($subscriptionData->fields, $paymentSourcePayload);
              if (empty($paymentResult['success'])) {
                $errorMessage = !empty($paymentResult['message']) ? htmlspecialchars($paymentResult['message']) : 'The subscription payment method could not be updated.';
                if (isset($paymentResult['details'])) {
                  $detailsHtml = zen_paypal_subscription_admin_format_error_details($paymentResult['details']);
                  if ($detailsHtml !== '') {
                    $errorMessage .= '<br />' . $detailsHtml;
                  }
                }
                $messageStack->add_session($messageStackKey, $errorMessage, 'error');
                zen_redirect(zen_href_link(
                  FILENAME_PAYPAL_SUBSCRIPTIONS,
                  'action=edit&profileid=' . urlencode($profileIdValue) . '&customers_id=' . (int)$_POST['customers_id'],
                  'SSL'
                ));
              }
            }

            if ($amountValue !== null) {
              $updateSql = "UPDATE " . TABLE_PAYPAL_RECURRING . " SET amount = :amount WHERE profile_id = :profileId LIMIT 1";
              $updateSql = $db->bindVars($updateSql, ':amount', $amountValue, 'string');
              $updateSql = $db->bindVars($updateSql, ':profileId', $profileIdValue, 'string');
              $db->Execute($updateSql);
            }

            $messageStack->add_session($messageStackKey, 'PayPal REST subscription updated successfully.', 'success');
            zen_redirect(zen_href_link(
              FILENAME_PAYPAL_SUBSCRIPTIONS,
              'action=search_customers&customers_id=' . (int)$_POST['customers_id'],
              'SSL'
            ));
            break;
          }
        }
        $DataArray = array();
        // keys will be made uppercase by AngelEye
        $DataArray['URPPFields']['profileid'] = $_POST['profileid'];
    	$DataArray['URPPFields']['desc'] = $_POST['desc'];
    	$DataArray['URPPFields']['subscribername'] = $_POST['subscribername'];
    	$DataArray['URPPFields']['reference'] = $_POST['reference'];
    	$DataArray['URPPFields']['additionalbillingcycles'] = $_POST['additionalbillingcycles'];
    	$DataArray['URPPFields']['amt'] = $_POST['amt'];
    	$DataArray['URPPFields']['shippingamt'] = $_POST['shippingamt'];
    	$DataArray['URPPFields']['taxamt'] = $_POST['taxamt'];
    	$DataArray['URPPFields']['outstandingamt'] = $_POST['outstandingamt'];
    	$DataArray['URPPFields']['autobilloutamt'] = $_POST['autobilloutamt'];
    	$DataArray['URPPFields']['note'] = $_POST['note'];
    	
    	if ($_POST['acct'] != '' && $_POST['expdate'] != '' && $_POST['cvv2'] != '') {
    		$DataArray['CCDetails']['creditcardtype'] = $_POST['creditcardtype'];
    		$DataArray['CCDetails']['acct'] = $_POST['acct'];
    		$DataArray['CCDetails']['expdate'] = $_POST['expdate'];
    		$DataArray['CCDetails']['cvv2'] = $_POST['cvv2'];
    		if ($_POST['startdate'] != '') $DataArray['CCDetails']['startdate'] = $_POST['startdate'];
    		if ($_POST['issuenumber'] != '') $DataArray['CCDetails']['issuenumber'] = $_POST['issuenumber'];
			}

			if ($_POST['firstname'] != '' && $_POST['lastname'] != '') {
    		$DataArray['PayerInfo']['firstname'] = $_POST['firstname'];
    		$DataArray['PayerInfo']['lastname'] = $_POST['lastname'];
			}
    	
    	if ($_POST['street'] != '' && $_POST['city'] != '' && $_POST['state'] != '' && $_POST['countrycode'] != '') { 
    		$DataArray['BillingAddress']['street'] = $_POST['street'];
    		$DataArray['BillingAddress']['street2'] = $_POST['street2'];
    		$DataArray['BillingAddress']['city'] = $_POST['city'];
    		$DataArray['BillingAddress']['state'] = $_POST['state'];
    		$DataArray['BillingAddress']['countrycode'] = $_POST['countrycode'];
    		$DataArray['BillingAddress']['zip'] = $_POST['zip'];
    		$DataArray['BillingAddress']['shiptophonenum'] = $_POST['shiptophonenum'];
			}	
    	
    	// create the recurring payment      
      $PayPalResult = $PayPal->UpdateRecurringPaymentsProfile($DataArray);
      
      /*
      echo '<pre>';
      print_r($PayPalResult);
      echo '</pre>';
      die();
      */
      
      // now update the recurring profile in Zen Cart
      // calculate new expiration date
      if ((int)$_POST['additionalbillingcycles'] > 0 && is_array($subscription)) {
        $currentExpirationRaw = $subscription['expiration_date'];
        if (!empty($currentExpirationRaw)) {
          $current_expiration_date = strtotime($currentExpirationRaw);
          if ($current_expiration_date !== false) {
            $billingFrequency = (int)$subscription['billingfrequency'];
            if ($billingFrequency <= 0) {
              $billingFrequency = 1;
            }
            $end_after_cycles = (int)$_POST['additionalbillingcycles'] * $billingFrequency;
            $end_time = false;
            switch ($subscription['billingperiod']) {
              case 'Day':
                $end_time = strtotime("+" . $end_after_cycles . " days", $current_expiration_date);
                break;
              case 'Week':
                $end_time = strtotime("+" . $end_after_cycles . " weeks", $current_expiration_date);
                break;
              case 'SemiMonth':
                $todays_date = date('j', $current_expiration_date);
                $num_full_months = floor($end_after_cycles / 2);
                $num_partial_months = $end_after_cycles % 2;
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('n', $current_expiration_date), date('Y', $current_expiration_date));
                switch (true) {
                  case ($todays_date > 15):
                    // start first of next month
                    $days_left_in_month = $days_in_month - $todays_date + ($num_partial_months * 15); // calculate days left in month and then add 15 days for any partial months so that we end on the 15th
                    $relative = '+' . $days_left_in_month . ' day' . ($days_left_in_month > 1 ? 's' : '');
                    if ($num_full_months > 0) {
                      $relative .= ' +' . $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '');
                    }
                    $end_time = strtotime($relative, $current_expiration_date);
                    break;
                  case ($todays_date == 15):
                    // start today
                    // if an odd number of payments is to be made, end on the first of the next month
                    $end_time = $current_expiration_date;
                    if ($num_full_months > 0) {
                      $end_time = strtotime('+' . $num_full_months . ' month' . ($num_full_months > 1 ? 's' : ''), $end_time);
                    }
                    if ($num_partial_months) { // will always be a 0 or 1
                      $end_month = date('n', $end_time);
                      $end_year = date('Y', $end_time);
                      $days_in_end_month = cal_days_in_month(CAL_GREGORIAN, $end_month, $end_year);
                      $days_til_end_month = ($days_in_end_month - 15) + 1;
                      $end_time = strtotime("+" . $days_til_end_month . " day" . ($days_til_end_month > 1 ? "s" : ""), $end_time);
                    }
                    break;
                  case ($todays_date > 1):
                    // start on the 15th
                    $days_left_in_period = 15 - $todays_date; // calculate days left in til the 15th
                    // if an odd number of payments is to be made, add one month and then subtract 14 days so that the expiration date is on the 1st.
                    $relative = '+' . $days_left_in_period . ' day' . ($days_left_in_period > 1 ? 's' : '');
                    if ($num_full_months > 0) {
                      $relative .= ' +' . $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '');
                    }
                    $end_time = strtotime($relative, $current_expiration_date);
                    if ($num_partial_months) { // will always be a 0 or 1
                      $end_month = date('n', $end_time);
                      $end_year = date('Y', $end_time);
                      $days_in_end_month = cal_days_in_month(CAL_GREGORIAN, $end_month, $end_year);
                      $days_til_end_month = ($days_in_end_month - 15) + 1;
                      $end_time = strtotime("+" . $days_til_end_month . " day" . ($days_til_end_month > 1 ? "s" : ""), $end_time);
                    }
                    break;
                  case ($todays_date == 1):
                    //start today
                    $relative = '';
                    if ($num_full_months > 0) {
                      $relative = '+' . $num_full_months . ' month' . ($num_full_months > 1 ? 's' : '');
                    }
                    if ($num_partial_months) {
                      $relative .= ($relative === '' ? '+' : ' +') . '15 days';
                    }
                    $end_time = $current_expiration_date;
                    if ($relative !== '') {
                      $end_time = strtotime($relative, $current_expiration_date);
                    }
                    break;
                }
                break;
              case 'Month':
                $end_time = strtotime("+" . $end_after_cycles . " months", $current_expiration_date);
                break;
              case 'Year':
                $end_time = strtotime("+" . $end_after_cycles . " years", $current_expiration_date);
                break;
            }
            if ($end_time !== false) {
              $end_date = date('Y-m-d', $end_time);
              $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET expiration_date = '" . $end_date . "', totalbillingcycles = totalbillingcycles + " . (int)$_POST['additionalbillingcycles'] . " WHERE profile_id = '" . $_POST['profileid'] . "' LIMIT 1;");
            }
          }
        }
      }
			     	
      $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET amount = '" . $_POST['amt'] . "' WHERE profile_id = '" . $_POST['profileid'] . "' LIMIT 1;"); 
     
     	if (is_array($PayPalResult['ERRORS']) && sizeof($PayPalResult['ERRORS']) > 0) {
     		foreach($PayPalResult['ERRORS'] as $error) {
     			$messageStack->add_session($messageStackKey, $error['L_SHORTMESSAGE'], 'error');
     			zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=edit&customers_id=' . $_POST['customers_id'] . '&profileid=' . $_POST['profileid'], 'SSL'));
				}
			} else {
				$messageStack->add_session($messageStackKey, 'Profile Successfully Updated.', 'success');
				zen_redirect(zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=search_customers&customers_id=' . $_POST['customers_id'], 'SSL'));
			} 
      break;
    case 'export':
        // GET ALL SUBSCRIPTIONS
        $subscriptions_query = "SELECT * 
                                FROM " . TABLE_PAYPAL_RECURRING . " 
                                ORDER BY subscription_id DESC;";
        $subscriptions = $db->Execute($subscriptions_query);
        
        $row[0] = array(
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_PROFILE_ID, 
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_DESCRIPTION, 
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_CUSTOMER_NAME, 
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_ORDERS_ID, 
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_START_DATE, 
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_BILLING_DATE,
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_EXPIRATION_DATE,
        	TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_COMPLETED,
                TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_REMAINING,
                TABLE_HEADING_PAYPAL_SUBSCRIPTION_OVERDUE_BALANCE,
                TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENT_METHOD,
                TABLE_HEADING_PAYPAL_SUBSCRIPTION_LAST_REFRESH,
                TABLE_HEADING_PAYPAL_SUBSCRIPTION_STATUS
        );
        
        $exportRows = array();
        $exportPrefetch = array();

        while (!$subscriptions->EOF) {
          $row = $subscriptions->fields;
          $exportRows[] = $row;

          $profileIdCandidate = isset($row['profile_id']) ? trim((string) $row['profile_id']) : '';
          $customerIdCandidate = isset($row['customers_id']) ? (int) $row['customers_id'] : 0;
          if ($customerIdCandidate > 0 && $profileIdCandidate !== '') {
            if (!isset($exportPrefetch[$customerIdCandidate])) {
              $exportPrefetch[$customerIdCandidate] = array();
            }
            $exportPrefetch[$customerIdCandidate][] = $profileIdCandidate;
          }

          $subscriptions->MoveNext();
        }

        foreach ($exportPrefetch as $prefetchCustomerId => $prefetchProfileIds) {
          zen_paypal_subscription_cache_prefetch($prefetchCustomerId, $prefetchProfileIds);
        }

        foreach ($exportRows as $exportRow) {
          $profileData = zen_paypal_subscription_admin_fetch_profile($exportRow, $PayPalProfileManager);
          $normalizedProfile = zen_paypal_subscription_admin_normalize_profile($exportRow, $profileData);
          $profileErrors = isset($normalizedProfile['errors']) && is_array($normalizedProfile['errors']) ? $normalizedProfile['errors'] : array();
          if (!(count($profileErrors) > 0)) {
            $amountDisplay = $normalizedProfile['amount_formatted'] !== '' ? $normalizedProfile['amount_formatted'] : $normalizedProfile['amount'];
            $outstandingDisplay = $normalizedProfile['outstanding_formatted'] !== '' ? $normalizedProfile['outstanding_formatted'] : $normalizedProfile['outstanding_balance'];
            $paymentsRemaining = $normalizedProfile['payments_remaining'];
            $row[] = array(
                $normalizedProfile['profile_id'],
                zen_customers_name($exportRow['customers_id']),
                zen_get_products_name($exportRow['products_id']) . ($amountDisplay !== '' ? ' - ' . $amountDisplay : ''),
                $exportRow['orders_id'],
                $normalizedProfile['start_date'],
                $normalizedProfile['next_date'],
                $normalizedProfile['expiration_date'],
                $normalizedProfile['payments_completed'],
                $paymentsRemaining,
                $outstandingDisplay,
                $normalizedProfile['payment_method'],
                $normalizedProfile['refreshed_at'],
                $normalizedProfile['status_display']
            );
          }
        }
				$filename = date('m-d-Y') . '.csv';
				$fp = fopen($filename, 'w');
				foreach ($row as $fields) {
					fputcsv($fp, $fields);
				}
				header('Content-Type: text/csv');
				header('Pragma: no-cache');
				header('Content-Disposition: attachment; filename="'.$filename.'"');
		    // make php send the generated csv lines to the browser
		    readfile($filename);
		    exit();				           
    	break;
    default:
    case 'search_customers':
      if (isset($_GET['customers_id'])) {
        // GET ALL SUBSCRIPTIONS
        $subscriptions_query = "SELECT * 
                                FROM " . TABLE_PAYPAL_RECURRING . " 
                                WHERE customers_id = " . (int)$_GET['customers_id'] . "
                                ORDER BY subscription_id DESC;";
        $subscriptions = $db->Execute($subscriptions_query);
        // check for store credit mod
        if (file_exists(DIR_FS_CATALOG . 'includes/classes/store_credit.php')) {
          require_once(DIR_FS_CATALOG . 'includes/classes/store_credit.php');
          $store_credit = new storeCredit();
          $store_credit_balance = $store_credit->retrieve_customer_credit((int)$_GET['customers_id']);         
        }
        // end store credit
      }
      break;          
  }                                       
  $useLegacyAdminHead = !file_exists(DIR_WS_INCLUDES . 'admin_html_head.php');
?>
<!doctype html>
<html <?= HTML_PARAMS ?>>
  <head>
    <?php
    if ($useLegacyAdminHead) {
      ?>
      <meta http-equiv="Content-Type" content="text/html; charset=<?= CHARSET ?>">
      <title><?= TITLE ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
      <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
      <script src="includes/menu.js"></script>
      <script src="includes/general.js"></script>
      <script>
        function init()
        {
          cssjsmenu('navbar');
          if (document.getElementById)
          {
            var kill = document.getElementById('hoverJS');
            kill.disabled = true;
          }
        }
      </script>
      <?php
    } else {
      require DIR_WS_INCLUDES . 'admin_html_head.php';
    }
    ?>
    <link rel="stylesheet" type="text/css" href="includes/css/numinix_admin.css" />
    <link rel="stylesheet" type="text/css" href="includes/css/paypal_subscriptions.css" />
  </head>
  <body<?= $useLegacyAdminHead ? ' onload="init()"' : '' ?>>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<div class="nmx-module">
  <div class="nmx-container">
    <div class="nmx-container-header">
      <h1><?= HEADING_TITLE ?></h1>
    </div>
    <div class="nmx-row">
      <div class="nmx-col-xs-12">
        <div class="nmx-panel">
          <div class="nmx-panel-heading">
            <div class="nmx-panel-title">Filters &amp; Reports</div>
          </div>
          <div class="nmx-panel-body">
            <div id="search_subscriptions" class="paypal-subscriptions-filter">
<?php
  if (is_array($search_customers) && sizeof($search_customers) > 1) {
    echo '<div id="search_customers_form" class="searchForm nmx-form nmx-form-inline paypal-filter-form">' . "\n";
    echo zen_draw_form('search_customers', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
    echo '<label for="customers_id">Search Customers: ' . zen_draw_pull_down_menu('customers_id', $search_customers, $_GET['customers_id'], 'class="nmx-form-control" onchange="this.form.submit();"') . '</label>' . "\n";
    echo zen_draw_hidden_field('action', 'search_customers') . "\n";
    echo '</form>' . "\n";
    echo '</div>' . "\n";
  } 
  if (is_array($search_products) && sizeof($search_products) > 1) {
    echo '<div id="search_products_form" class="searchForm nmx-form nmx-form-inline paypal-filter-form">' . "\n";
    echo zen_draw_form('search_products', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
    echo '<label for="customers_id">Search Products: ' . zen_draw_pull_down_menu('products_id', $search_products, $_GET['products_id'], 'class="nmx-form-control" onchange="this.form.submit();"') . '</label>' . "\n";
    echo zen_draw_hidden_field('action', 'search_products') . "\n";
    echo '</form>' . "\n";
    echo '</div>' . "\n";
  }
  if (is_array($search_status) && sizeof($search_status) > 1) {
    echo '<div id="search_status_form" class="searchForm nmx-form nmx-form-inline paypal-filter-form">' . "\n";
    echo zen_draw_form('search_status', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
    echo '<label for="customers_id">Search Status: ' . zen_draw_pull_down_menu('status', $search_status, $_GET['status'], 'class="nmx-form-control" onchange="this.form.submit();"') . '</label>' . "\n";
    echo zen_draw_hidden_field('action', 'search_status') . "\n";
    echo '</form>' . "\n";
    echo '</div>' . "\n";
  }
  echo '<div class="paypal-filter-action"><a class="nmx-btn nmx-btn-default nmx-btn-sm" href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=show_report') . '">Show Expiration Report</a></div>';
  echo '<div class="paypal-filter-action"><a class="nmx-btn nmx-btn-default nmx-btn-sm" href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=export') . '">Export CSV</a></div>';       
  if (sizeof($search_customers) == 0 && sizeof($search_products) ==  0 && sizeof($search_status) == 0) {
?>
  <p>There are no PayPal subscriptions.  If you believe this is an error, please contact <a href="mailto:support@numinix.com">Numinix&trade;</a> for support of the PayPal Subscriptions and Recurring Payments module.</p>
<?php
  }
?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="nmx-row">
      <div class="nmx-col-xs-12">
        <div class="nmx-panel">
          <div class="nmx-panel-heading">
            <div class="nmx-panel-title">Subscription Management</div>
          </div>
          <div class="nmx-panel-body">
<?php
  $paypalSubscriptionsMessages = '';
  if (isset($messageStack) && is_object($messageStack)) {
    if (method_exists($messageStack, 'size') && method_exists($messageStack, 'output')) {
      if ($messageStack->size($messageStackKey) > 0) {
        $paypalSubscriptionsMessages = $messageStack->output($messageStackKey);
      }
    } elseif (method_exists($messageStack, 'output')) {
      $paypalSubscriptionsMessages = $messageStack->output($messageStackKey);
    }
  }
?>
            <div id="paypal-subscriptions-messages" class="paypal-subscriptions-messages nmx-message-stack"><?= $paypalSubscriptionsMessages ?></div>
<?php
  switch ($_GET['action']) {
    case 'cancel_confirm':
      if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
        echo zen_draw_form('cancel_confirm', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
        echo zen_draw_hidden_field('action', 'cancel') . "\n";
        echo zen_draw_hidden_field('profileid', $_GET['profileid']) . "\n";
        echo zen_draw_hidden_field('customers_id', $_GET['customers_id']) . "\n";
        echo '<p>Are you sure you would like to cancel this subscription?</p>' . "\n";
        echo zen_image_submit('button_confirm.gif', IMAGE_CONFIRM) . "\n";
        echo '<a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . $_GET['customers_id']) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>' . "\n";
        echo '</form>' . "\n";
      }
      break;
    case 'suspend_confirm':
      if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
        echo zen_draw_form('suspend_confirm', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
        echo zen_draw_hidden_field('action', 'suspend') . "\n";
        echo zen_draw_hidden_field('profileid', $_GET['profileid']) . "\n";
        echo zen_draw_hidden_field('customers_id', $_GET['customers_id']) . "\n";
        echo '<p>Are you sure you would like to suspend this subscription?</p>' . "\n";
        echo zen_image_submit('button_confirm.gif', IMAGE_CONFIRM) . "\n";
        echo '<a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . $_GET['customers_id']) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>' . "\n";        
        echo '</form>' . "\n";
      }
      break;
    case 'delete_confirm':
      if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
        echo zen_draw_form('delete_confirm', FILENAME_PAYPAL_SUBSCRIPTIONS, '', 'get') . "\n";
        echo zen_draw_hidden_field('action', 'delete') . "\n";
        echo zen_draw_hidden_field('subscription_id', $_GET['subscription_id']) . "\n";
        echo zen_draw_hidden_field('customers_id', $_GET['customers_id']) . "\n";
        echo '<p>Are you sure you would like to delete this subscription from the database?</p>' . "\n";
        echo zen_image_submit('button_confirm.gif', IMAGE_CONFIRM) . "\n";
        echo '<a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'customers_id=' . $_GET['customers_id']) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>' . "\n";        
        echo '</form>' . "\n";
      }
      break;
    case 'show_report':
      if (is_object($report) && $report->RecordCount() > 0) {
?>
    <div class="nmx-table-responsive">
      <table id="expirationReport" class="nmx-table nmx-table-bordered nmx-table-striped paypal-subscriptions-table" border="0" cellpadding="0" cellspacing="0">
        <tr>
          <th>Expiration Year</th>
          <th>Subscriptions</th>
        </tr>
      <?php
        while (!$report->EOF) {
          echo '<tr>' . "\n";
          echo '  <td>' . ($report->fields['expiration_year'] > 0 ? $report->fields['expiration_year'] : 'Unset') . '</td>' . "\n";
          echo '  <td>' . $report->fields['num_subscriptions'] . '</td>' . "\n";
          echo '</tr>' . "\n";
          $report->MoveNext();
        }
      ?>
      </table>
    </div>
<?php
      }
      break;
    case 'edit':
        echo '<br class="clearBoth" />' . "\n";
        if (is_object($subscriptions) && $subscriptions->RecordCount() > 0) {
          $profileData = zen_paypal_subscription_admin_fetch_profile($subscriptions->fields, $PayPalProfileManager);
          $normalizedProfile = zen_paypal_subscription_admin_normalize_profile($subscriptions->fields, $profileData);
          $profile = $normalizedProfile['profile'];
          $profileErrors = isset($normalizedProfile['errors']) && is_array($normalizedProfile['errors']) ? $normalizedProfile['errors'] : array();
          $isRestProfile = !empty($normalizedProfile['is_rest']);
          $canEditProfile = !empty($normalizedProfile['can_edit']);

          if ($isRestProfile) {
            if (count($profileErrors) > 0) {
              echo '<p>Profile details could not be retrieved for editing. Please try again later.</p>' . "\n";
            } else {
              $currencyCode = $normalizedProfile['currency_code'];
              $amountDisplay = $normalizedProfile['amount_formatted'] !== '' ? $normalizedProfile['amount_formatted'] : $normalizedProfile['amount'];
              $nextBillingDisplay = $normalizedProfile['next_date'];
              $paymentSource = isset($profile['payment_source']) && is_array($profile['payment_source']) ? $profile['payment_source'] : array();
              $currentTokenId = '';
              if (isset($paymentSource['token']['id']) && $paymentSource['token']['id'] !== '') {
                $currentTokenId = $paymentSource['token']['id'];
              }
              $currentVaultId = '';
              if (isset($paymentSource['card']['vault_id']) && $paymentSource['card']['vault_id'] !== '') {
                $currentVaultId = $paymentSource['card']['vault_id'];
              } elseif (isset($paymentSource['card']['id']) && $paymentSource['card']['id'] !== '') {
                $currentVaultId = $paymentSource['card']['id'];
              }
              $currentPaypalEmail = '';
              if (isset($paymentSource['paypal']['email_address']) && $paymentSource['paypal']['email_address'] !== '') {
                $currentPaypalEmail = $paymentSource['paypal']['email_address'];
              }

              echo zen_draw_form('update_rest_profile', FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=update', 'post');
              echo zen_draw_hidden_field('profileid', $_GET['profileid']) . "\n";
              echo zen_draw_hidden_field('customers_id', (int)$_GET['customers_id']) . "\n";
              echo zen_draw_hidden_field('rest_currency', $currencyCode) . "\n";
              echo '<h2>PayPal REST Subscription</h2>' . "\n";
              echo '<p>Update the subscription details below. Leave a field blank to keep the current value.</p>' . "\n";
              echo '<ul class="rest-subscription-summary">' . "\n";
              echo '  <li><strong>Status:</strong> ' . htmlspecialchars($normalizedProfile['status_display']) . '</li>' . "\n";
              if ($amountDisplay !== '') {
                echo '  <li><strong>Amount:</strong> ' . htmlspecialchars($amountDisplay) . '</li>' . "\n";
              }
              if ($nextBillingDisplay !== '') {
                echo '  <li><strong>Next Billing Date:</strong> ' . htmlspecialchars($nextBillingDisplay) . '</li>' . "\n";
              }
              if ($normalizedProfile['payment_method'] !== '') {
                echo '  <li><strong>Payment Method:</strong> ' . htmlspecialchars($normalizedProfile['payment_method']) . '</li>' . "\n";
              }
              echo '</ul>' . "\n";

              $amountPlaceholder = $amountDisplay !== '' ? ' placeholder="Current: ' . htmlspecialchars($amountDisplay) . '"' : '';
              $currencyLabel = $currencyCode !== '' ? ' (' . htmlspecialchars($currencyCode) . ')' : '';
              echo '<label for="rest_amount">Amount' . $currencyLabel . '</label>' . "\n";
              echo zen_draw_input_field('rest_amount', '', 'id="rest_amount"' . $amountPlaceholder) . "\n";
              echo '<br class="clearBoth" />' . "\n";

              $nextPlaceholder = $nextBillingDisplay !== '' ? ' placeholder="Current: ' . htmlspecialchars($nextBillingDisplay) . '"' : '';
              echo '<label for="rest_next_billing_date">Next Billing Date (YYYY-MM-DD)</label>' . "\n";
              echo zen_draw_input_field('rest_next_billing_date', '', 'id="rest_next_billing_date"' . $nextPlaceholder) . "\n";
              echo '<br class="clearBoth" />' . "\n";

              echo '<h3>Update Payment Source</h3>' . "\n";
              echo '<p>Provide a new PayPal token, vaulted card ID, or PayPal email address to change the funding source. Leave these blank to keep the existing payment method.</p>' . "\n";

              $tokenPlaceholder = $currentTokenId !== '' ? ' placeholder="Current: ' . htmlspecialchars($currentTokenId) . '"' : '';
              echo '<label for="rest_payment_source_token_id">Payment Token ID</label>' . "\n";
              echo zen_draw_input_field('rest_payment_source_token_id', '', 'id="rest_payment_source_token_id"' . $tokenPlaceholder) . "\n";
              echo '<br class="clearBoth" />' . "\n";

              $vaultPlaceholder = $currentVaultId !== '' ? ' placeholder="Current: ' . htmlspecialchars($currentVaultId) . '"' : '';
              echo '<label for="rest_payment_source_vault_id">Vaulted Card ID</label>' . "\n";
              echo zen_draw_input_field('rest_payment_source_vault_id', '', 'id="rest_payment_source_vault_id"' . $vaultPlaceholder) . "\n";
              echo '<br class="clearBoth" />' . "\n";

              $paypalPlaceholder = $currentPaypalEmail !== '' ? ' placeholder="Current: ' . htmlspecialchars($currentPaypalEmail) . '"' : '';
              echo '<label for="rest_payment_source_paypal_email">PayPal Email</label>' . "\n";
              echo zen_draw_input_field('rest_payment_source_paypal_email', '', 'id="rest_payment_source_paypal_email"' . $paypalPlaceholder) . "\n";
              echo '<br class="clearBoth" />' . "\n";
              echo '<p><em>Only fill in one payment source field per update.</em></p>' . "\n";

              echo zen_image_submit('button_update.gif', IMAGE_UPDATE) . "\n";
              echo '</form>' . "\n";
            }
          } elseif (count($profileErrors) > 0 || !$canEditProfile) {
            echo '<p>Profile details could not be retrieved for editing. Please try again later.</p>' . "\n";
          } else {
            echo zen_draw_form('update_profile', FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=update', 'post');
            echo zen_draw_hidden_field('profileid', $_GET['profileid']) . "\n";
            echo zen_draw_hidden_field('customers_id', (int)$_GET['customers_id']) . "\n";
?>
                        <h2>Payment Profile</h2>

                        <label for="subscribername">Subscriber's Name</label>
                        <?php echo zen_draw_input_field('subscribername', addslashes(zen_customers_name((int)$_GET['customers_id']))); ?>
                        <br class="clearBoth" />

                        <label for="desc">Description</label>
                        <?php echo zen_draw_input_field('desc', addslashes(zen_get_products_name((int)$subscriptions->fields['products_id']))); ?>
                        <br class="clearBoth" />

                        <label for="reference">Order Number</label>
                        <?php echo zen_draw_input_field('reference', (int)$subscriptions->fields['orders_id']); ?>
                        <br class="clearBoth" />

                        <label for="additionalbillingcycles">Additional Billing Cycles</label>
                        <?php echo zen_draw_input_field('additionalbillingcycles', 0); ?>
                        <br class="clearBoth" />

                        <?php $currencyCode = $normalizedProfile['currency_code']; ?>
                        <?php $currencyLabel = $currencyCode !== '' ? ' (' . htmlspecialchars($currencyCode) . ')' : ''; ?>
                        <label for="amt">Amount<?php echo $currencyLabel; ?></label>
                        <?php echo zen_draw_input_field('amt', isset($profile['AMT']) ? $profile['AMT'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="shippingamt">Shipping Amount<?php echo $currencyLabel; ?></label>
                        <?php echo zen_draw_input_field('shippingamt', isset($profile['SHIPPINGAMT']) ? $profile['SHIPPINGAMT'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="taxamt">Tax Amount<?php echo $currencyLabel; ?></label>
                        <?php echo zen_draw_input_field('taxamt', isset($profile['TAXAMT']) ? $profile['TAXAMT'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="outstandingamt">Outstanding Amount<?php echo $currencyLabel; ?></label>
                        <?php echo zen_draw_input_field('outstandingamt', isset($profile['OUTSTANDINGBALANCE']) ? $profile['OUTSTANDINGBALANCE'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="autobilloutamt">Autobill Outstanding Amount</label>
                        <?php $autobillopts = array(array('id' => 'NoAutoBill', 'text' => 'No Auto Billing'), array('id' => 'AddToNextBilling', 'text' => 'Add to next billing cycle')); ?>
                        <?php echo zen_draw_pull_down_menu('autobilloutamt', $autobillopts, isset($profile['AUTOBILLOUTAMT']) ? $profile['AUTOBILLOUTAMT'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="note">Note:</label>
                        <?php echo zen_draw_textarea_field('note', false, 20, 4, 'Profile Updated by Admin'); ?>
                        <br class="clearBoth" />

                        <h2>Credit Card</h2>

                        <label for="creditcardtype">Credit Card Type</label>
                        <?php $creditcardtypes = array(array('id' => 'Visa', 'text' => 'Visa'), array('id' => 'MasterCard', 'text' => 'MasterCard'), array('id' => 'Discover', 'text' => 'Discover'), array('id' => 'Amex', 'text' => 'American Express'), array('id' => 'Maestro', 'text' => 'Maestro')); ?>
                        <?php echo zen_draw_pull_down_menu('creditcardtype', $creditcardtypes, isset($profile['CREDITCARDTYPE']) ? $profile['CREDITCARDTYPE'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="acct">Credit Card Number:</label>
                        <?php echo zen_draw_input_field('acct', isset($profile['ACCT']) ? $profile['ACCT'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="expdate">Credit Card Expiry (MMYYYY):</label>
                        <?php echo zen_draw_input_field('expdate', isset($profile['EXPDATE']) ? $profile['EXPDATE'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="cvv2">CVV:</label>
                        <?php echo zen_draw_input_field('cvv2', isset($profile['CVV2']) ? $profile['CVV2'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="startdate">Maestro Start Date (MMYYYY):</label>
                        <?php echo zen_draw_input_field('startdate', isset($profile['STARTDATE']) ? $profile['STARTDATE'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="issuenumber">Maestro Issue Number:</label>
                        <?php echo zen_draw_input_field('issuenumber', isset($profile['ISSUENUMBER']) ? $profile['ISSUENUMBER'] : ''); ?>
                        <br class="clearBoth" />

                        <h2>Billing Address</h2>

                        <label for="firstname">First Name:</label>
                        <?php echo zen_draw_input_field('firstname', isset($profile['FIRSTNAME']) ? $profile['FIRSTNAME'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="lastname">Last Name:</label>
                        <?php echo zen_draw_input_field('lastname', isset($profile['LASTNAME']) ? $profile['LASTNAME'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="street">First Street Address:</label>
                        <?php echo zen_draw_input_field('street', isset($profile['STREET']) ? $profile['STREET'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="street2">Second Street Address:</label>
                        <?php echo zen_draw_input_field('street2', isset($profile['STREET2']) ? $profile['STREET2'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="city">City:</label>
                        <?php echo zen_draw_input_field('city', isset($profile['CITY']) ? $profile['CITY'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="state">State:</label>
                        <?php echo zen_draw_input_field('state', isset($profile['STATE']) ? $profile['STATE'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="countrycode">Country:</label>
                        <?php
                $country_codes_sql = $db->Execute("SELECT countries_iso_code_2, countries_name FROM " . TABLE_COUNTRIES . " ORDER BY countries_name ASC;");
                if ($country_codes_sql->RecordCount() > 0) {
                        $country_codes = array();
                        while (!$country_codes_sql->EOF) {
                                $country_codes[] = array('id' => $country_codes_sql->fields['countries_iso_code_2'], 'text' => $country_codes_sql->fields['countries_name']);
                                $country_codes_sql->MoveNext();
                                        }
                                        echo zen_draw_pull_down_menu('countrycode', $country_codes, isset($profile['COUNTRYCODE']) ? $profile['COUNTRYCODE'] : '');
                                } else {
                        ?>
                        <?php echo zen_draw_input_field('countrycode', isset($profile['COUNTRYCODE']) ? $profile['COUNTRYCODE'] : ''); ?>
                        <?php } ?>
                        <br class="clearBoth" />

                        <label for="zip">Zip/Postal Code:</label>
                        <?php echo zen_draw_input_field('zip', isset($profile['ZIP']) ? $profile['ZIP'] : ''); ?>
                        <br class="clearBoth" />

                        <label for="shiptophonenum">Telephone:</label>
                        <?php echo zen_draw_input_field('shiptophonenum', isset($profile['SHIPTOPHONENUM']) ? $profile['SHIPTOPHONENUM'] : ''); ?>
                        <br class="clearBoth" />

                        <?php echo zen_image_submit('button_update.gif', IMAGE_UPDATE) ?>

                </form>
<?php
          }
        } else {
                echo '<p>' . TEXT_PAYPAL_SUBSCRIPTION_NOT_FOUND . '</p>' . "\n";
        }
			break;                
    default: 
      if (is_object($subscriptions) && $subscriptions->RecordCount() > 0) {
        $subscriptionRows = array();

        while (!$subscriptions->EOF) {
          $row = $subscriptions->fields;
          $subscriptionRows[] = $row;

          $subscriptions->MoveNext();
        }

        zen_paypal_subscription_cache_prefetch_subscriptions($subscriptionRows);
  ?>
    <div id="paypal-subscriptions-messages" class="paypal-subscriptions-messages" role="status" aria-live="polite"></div>
    <div class="nmx-table-responsive">
      <table id="paypalSubscriptions" class="nmx-table nmx-table-bordered nmx-table-striped paypal-subscriptions-table" border="0" cellpadding="0" cellspacing="0">
        <tr>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PROFILE_ID; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_DESCRIPTION; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_CUSTOMER_NAME; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_ORDERS_ID; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_START_DATE; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_BILLING_DATE; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_EXPIRATION_DATE; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_COMPLETED; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENTS_REMAINING; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_OVERDUE_BALANCE; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENT_METHOD; ?></th>
          <?php if (isset($store_credit_balance)) echo '<th>' . TABLE_HEADING_PAYPAL_SUBSCRIPTION_STORE_CREDIT . '</th>'; ?>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_LAST_REFRESH; ?></th>
          <th><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_STATUS; ?></th>
        </tr>
      <?php
        $row_counter = 0;
        foreach ($subscriptionRows as $subscriptionRow) {
          $row_counter++;

          $resolvedProfile = array(
            'subscription' => $subscriptionRow,
            'profile_data' => array(
              'profile' => array(),
              'status' => '',
              'profile_source' => '',
              'profile_id' => isset($subscriptionRow['profile_id']) ? $subscriptionRow['profile_id'] : '',
              'errors' => array(),
            ),
            'refresh_pending' => false,
          );
          if (function_exists('zen_paypal_subscription_admin_resolve_cached_profile')) {
            $resolvedProfile = zen_paypal_subscription_admin_resolve_cached_profile(
              $subscriptionRow,
              array(
                'refresh_callback' => 'zen_paypal_subscription_refresh_profile_now',
                'refresh_options' => array(
                  'profile_manager' => $PayPalProfileManager,
                  'subscription' => $subscriptionRow,
                ),
              )
            );
          }

          $subscriptionRow = $resolvedProfile['subscription'];
          $profileData = $resolvedProfile['profile_data'];
          $refreshPending = !empty($resolvedProfile['refresh_pending']);

          if (!isset($profileData['profile_id']) || $profileData['profile_id'] === '') {
            $profileData['profile_id'] = isset($subscriptionRow['profile_id']) ? $subscriptionRow['profile_id'] : '';
          }
          if (!isset($profileData['errors']) || !is_array($profileData['errors'])) {
            $profileData['errors'] = array();
          }

          $normalizedProfile = zen_paypal_subscription_admin_normalize_profile($subscriptionRow, $profileData);
          $profileErrors = isset($normalizedProfile['errors']) && is_array($normalizedProfile['errors']) ? $normalizedProfile['errors'] : array();
          if (!(count($profileErrors) > 0)) {
            $profileId = $normalizedProfile['profile_id'];
            $customerId = (int)$subscriptionRow['customers_id'];
            $amountDisplay = $normalizedProfile['amount_formatted'] !== '' ? $normalizedProfile['amount_formatted'] : $normalizedProfile['amount'];
            $productDisplay = zen_get_products_name($subscriptionRow['products_id']);
            if ($amountDisplay !== '') {
              $productDisplay .= ' - ' . $amountDisplay;
            }
            $outstandingDisplay = $normalizedProfile['outstanding_formatted'] !== '' ? $normalizedProfile['outstanding_formatted'] : $normalizedProfile['outstanding_balance'];
            $paymentsRemaining = $normalizedProfile['payments_remaining'];
            $statusDisplay = $normalizedProfile['status_display'];
            $statusContext = $normalizedProfile['status_context'];
            $isRestProfile = !empty($normalizedProfile['is_rest']);
            $canEditProfile = !empty($normalizedProfile['can_edit']);

            $actions = array();
            $actions[] = '<li>' . htmlspecialchars($statusDisplay) . '</li>';
            $statusRaw = isset($normalizedProfile['status_raw']) ? $normalizedProfile['status_raw'] : (isset($subscriptionRow['status']) ? $subscriptionRow['status'] : '');
            if ($profileId !== '' && !zen_paypal_subscription_is_cancelled_status($statusRaw)) {
              $refreshButtonLabel = htmlspecialchars(BUTTON_PAYPAL_SUBSCRIPTION_REFRESH, ENT_QUOTES, CHARSET);
              $refreshFormName = 'admin_refresh_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $profileId . '_' . $customerId);
              $refreshFormAttributes = 'class="js-admin-refresh-form"';
              $refreshFormAction = zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'ajax=refresh_profile', 'SSL');
              $refreshSecurityToken = isset($_SESSION['securityToken']) ? $_SESSION['securityToken'] : '';
              ob_start();
              echo '<li>';
              echo zen_draw_form($refreshFormName, $refreshFormAction, 'post', $refreshFormAttributes);
              echo zen_draw_hidden_field('securityToken', $refreshSecurityToken);
              echo zen_draw_hidden_field('profileId', $profileId);
              echo zen_draw_hidden_field('customerId', $customerId);
              echo zen_draw_hidden_field('manual_refresh', '1');
              echo '<button type="submit" class="button js-admin-refresh" data-profile-id="' . htmlspecialchars($profileId, ENT_QUOTES, CHARSET) . '" data-customer-id="' . $customerId . '">' . $refreshButtonLabel . '</button>';
              echo '</form>';
              echo '</li>';
              $actions[] = ob_get_clean();
            }
            if ($statusContext === 'active') {
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Cancel</a></li>';
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=suspend_confirm&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Suspend</a></li>';
              if ($canEditProfile) {
                $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=edit&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Edit</a></li>';
              } elseif ($isRestProfile) {
                $actions[] = '<li>Editing not available</li>';
              }
            } elseif ($statusContext === 'suspended') {
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Cancel</a></li>';
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=reactivate&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Reactivate</a></li>';
              if ($canEditProfile) {
                $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=edit&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Edit</a></li>';
              } elseif ($isRestProfile) {
                $actions[] = '<li>Editing not available</li>';
              }
            } elseif ($statusContext === 'pending') {
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Cancel</a></li>';
              if ($canEditProfile) {
                $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=edit&profileid=' . $profileId . '&customers_id=' . $customerId, 'SSL') . '">Edit</a></li>';
              } elseif ($isRestProfile) {
                $actions[] = '<li>Editing not available</li>';
              }
            } else {
              $actions[] = '<li><a href="' . zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'action=delete_confirm&subscription_id=' . $subscriptions->fields['subscription_id'] . '&customers_id=' . $customerId, 'SSL') . '">Delete</a></li>';
            }
            $actionsHtml = '<ul>' . implode("\n", $actions) . '</ul>';
      ?>
      <tr<?php
        $rowAttributes = array();
        $rowClasses = array();
        if ($row_counter % 2 == 0) {
          $rowClasses[] = 'alt';
        }
        if (!empty($refreshPending)) {
          $rowClasses[] = 'is-refresh-pending';
          $rowAttributes[] = 'data-refresh-pending="1"';
        }
        if (!empty($rowClasses)) {
          $rowAttributes[] = 'class="' . implode(' ', $rowClasses) . '"';
        }
        if ($profileId !== '') {
          $rowAttributes[] = 'data-profile-id="' . htmlspecialchars($profileId, ENT_QUOTES, CHARSET) . '"';
        }
        $rowAttributes[] = 'data-customer-id="' . (int) $customerId . '"';
        $statusAttribute = '';
        if (isset($subscriptionRow['status']) && $subscriptionRow['status'] !== '') {
          $statusAttribute = strtolower(trim($subscriptionRow['status']));
        } elseif (isset($normalizedProfile['status_raw']) && $normalizedProfile['status_raw'] !== '') {
          $statusAttribute = strtolower(trim($normalizedProfile['status_raw']));
        }
        if ($statusAttribute !== '') {
          $rowAttributes[] = 'data-status="' . htmlspecialchars($statusAttribute, ENT_QUOTES, CHARSET) . '"';
        }
        if (isset($subscriptionRow['classification_refreshed_at']) && $subscriptionRow['classification_refreshed_at'] !== '') {
          $rowAttributes[] = 'data-refreshed-at="' . htmlspecialchars($subscriptionRow['classification_refreshed_at'], ENT_QUOTES, CHARSET) . '"';
        }
        echo $rowAttributes ? ' ' . implode(' ', $rowAttributes) : '';
      ?>>
        <td><?php echo htmlspecialchars($profileId); ?></td>
        <td><?php echo htmlspecialchars(zen_customers_name($subscriptionRow['customers_id'])); ?></td>
        <td><?php echo htmlspecialchars($productDisplay); ?></td>
        <td><?php echo '<a href="' . zen_href_link(FILENAME_ORDERS, 'oID=' . $subscriptionRow['orders_id'], 'SSL') . '">' . (int)$subscriptionRow['orders_id'] . '</a>'; ?></td>
        <td data-column="start-date"><?php echo htmlspecialchars($normalizedProfile['start_date']); ?></td>
        <td data-column="next-date"><?php echo htmlspecialchars($normalizedProfile['next_date']); ?></td>
        <td><?php echo htmlspecialchars($normalizedProfile['expiration_date']); ?></td>
        <td><?php echo htmlspecialchars($normalizedProfile['payments_completed']); ?></td>
        <td><?php echo htmlspecialchars($paymentsRemaining); ?></td>
        <td><?php echo htmlspecialchars($outstandingDisplay); ?></td>
        <td data-column="payment-method"><?php echo htmlspecialchars($normalizedProfile['payment_method']); ?></td>
        <?php if (isset($store_credit_balance)) echo '<td>' . $currencies->format($store_credit_balance) . '</td>'; ?>
        <td data-column="last-refresh"><?php echo htmlspecialchars($normalizedProfile['refreshed_at']); ?></td>
        <td data-column="status"><?php echo $actionsHtml; ?></td>
      </tr>
      <?php
          }
        }
      ?>
      </table>
    </div>
    <script>
      window.nmxPaypalSubscriptionsConfig = {
        securityToken: <?php echo isset($_SESSION['securityToken']) ? json_encode($_SESSION['securityToken']) : 'null'; ?>,
        refreshUrl: '<?php echo str_replace('&amp;', '&', zen_href_link(FILENAME_PAYPAL_SUBSCRIPTIONS, 'ajax=refresh_profile', 'SSL')); ?>',
        refreshMessages: <?php echo json_encode(array(
          'tokenError' => TEXT_PAYPAL_SUBSCRIPTION_REFRESH_TOKEN_ERROR,
          'failure' => TEXT_PAYPAL_SUBSCRIPTION_REFRESH_FAILED,
          'success' => TEXT_PAYPAL_SUBSCRIPTION_REFRESH_SUCCESS,
          'missingContext' => TEXT_PAYPAL_SUBSCRIPTION_REFRESH_MISSING_CONTEXT,
          'missingIdentifiers' => TEXT_PAYPAL_SUBSCRIPTION_REFRESH_MISSING_IDENTIFIERS,
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
      };
    </script>
    <script src="includes/javascript/paypal_subscriptions.js"></script>
<?php
      } else if (isset($_GET['customers_id']) && $_GET['customers_id'] > 0) {
        echo '<p>' . TEXT_PAYPAL_SUBSCRIPTION_NOT_FOUND . '</p>' . "\n";
      }
      break;
  }
?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- body_eof //-->


<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
  </body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
