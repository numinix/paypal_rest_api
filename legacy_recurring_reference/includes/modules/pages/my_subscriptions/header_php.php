<?php
/**
* @package page
* @copyright Copyright 2003-2006 Zen Cart Development Team
* @copyright Portions Copyright 2003 osCommerce
* @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
* @version $Id: Define Generator v0.1 $
*/

// DEFINTELY DON'T EDIT THIS FILE UNLESS YOU KNOW WHAT YOU ARE DOING!

  //$_SESSION['navigation']->remove_current_page();
  require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));
  require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
  require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php');
  require_once(__DIR__ . '/debug.php');
  require_once(DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/functions.php');

  $action = '';
  if (isset($_POST['action'])) {
    $action = (string) $_POST['action'];
  } elseif (isset($_GET['action'])) {
    $action = (string) $_GET['action'];
  }
  $action = trim($action);

  zen_my_subscriptions_debug('my-subscriptions:header:start', array(
    'action' => ($action !== '' ? $action : 'default')
  ));

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

  $profileCacheTable = zen_paypal_subscription_cache_table_name();
  $db->Execute(
    'CREATE TABLE IF NOT EXISTS ' . $profileCacheTable . ' (
      cache_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      customers_id INT(10) UNSIGNED NOT NULL,
      profile_id VARCHAR(64) NOT NULL,
      status VARCHAR(64) DEFAULT NULL,
      profile_source VARCHAR(16) DEFAULT NULL,
      preferred_gateway VARCHAR(32) DEFAULT NULL,
      profile_data MEDIUMTEXT,
      refreshed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (cache_id),
      UNIQUE KEY idx_paypal_profile_cache_customer (customers_id, profile_id),
      KEY idx_paypal_profile_cache_refreshed (refreshed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
  );

  zen_paypal_subscription_cache_ensure_schema();

  zen_paypal_subscription_cache_cleanup_stale(isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null);

  $paypalSavedCardRecurring = new paypalSavedCardRecurring();
  
  if (!$_SESSION['customer_id']) {
        $_SESSION['navigation']->set_snapshot();
        zen_my_subscriptions_debug('my-subscriptions:redirect', array(
          'target' => 'login',
          'reason' => 'customer_not_authenticated'
        ));
        zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

  // include template specific file name defines
  $define_page = zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] . '/html_includes/', FILENAME_DEFINE_MY_SUBSCRIPTIONS, 'false');

  $breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2);
  
  require_once(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
  $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
  $PayPal = new PayPal($PayPalConfig);
  $PayPalRestClient = $paypalSavedCardRecurring->get_paypal_rest_client();
  $PayPalProfileManager = PayPalProfileManager::create($PayPalRestClient, $PayPal);
  
  switch($action) {
  	case 'upgrade':
  	case 'downgrade':
                if (isset($_GET['profileid'])) {
                        $_SESSION['cancel_profile'] = $_GET['profileid'];
                        $messageStack->add_session('header', 'Select a new subscription plan to replace your existing plan', 'caution');
                        zen_my_subscriptions_debug('my-subscriptions:redirect', array(
                          'action' => $action,
                          'target' => 'plan_comparison'
                        ));
                        zen_redirect(zen_href_link(FILENAME_PLAN_COMPARISON));
                }
                break;
    case 'cancel_confirm':
    case 'cancel_confirm_savedcard':
    case 'suspend_confirm':
      break;
    case 'cancel':
      $cancellationHandled = false;
      if (isset($_GET['profileid'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          $cancellationHandled = true;
          // subscription exists for this customer, cancel it
          $profileIdForLog = isset($subscription->fields['profile_id']) ? substr(hash('sha256', $subscription->fields['profile_id']), 0, 16) : '';
          $cancelStart = microtime(true);
          zen_my_subscriptions_debug('paypal-profile:cancel:start', array(
            'profile_id_hash' => $profileIdForLog
          ));

          $cancelResult = zen_paypal_subscription_cancel_immediately(
            (int) $_SESSION['customer_id'],
            $subscription->fields['profile_id'],
            array(
              'note' => 'Cancelled by customer.',
              'source' => 'customer',
              'subscription' => $subscription->fields,
              'profile_manager' => $PayPalProfileManager,
              'saved_card_recurring' => $paypalSavedCardRecurring,
            )
          );

          $cancelElapsed = microtime(true) - $cancelStart;
          zen_my_subscriptions_debug('paypal-profile:cancel:result', array(
            'profile_id_hash' => $profileIdForLog,
            'elapsed' => $cancelElapsed,
            'success' => !empty($cancelResult['success']),
            'message' => isset($cancelResult['message']) ? $cancelResult['message'] : null
          ));

          if (!empty($cancelResult['success'])) {
            $messageStack->add_session('my_subscriptions', 'Your subscription has been cancelled.', 'success');
          } else {
            $errorMessage = isset($cancelResult['message']) && $cancelResult['message'] !== ''
              ? $cancelResult['message']
              : 'An unknown error occurred.';
            $messageStack->add_session('my_subscriptions', 'Your subscription could not be cancelled:<br />' . $errorMessage, 'error');
          }
        }

      } elseif (isset($_GET['saved_card_id'])) {
        $cancellationHandled = true;
        zen_my_subscriptions_debug('savedcard:cancel:invoke', array(
          'saved_recurring_id' => (int) $_GET['saved_card_id']
        ));
        zen_paypal_cancel_savedcard_subscription($paypalSavedCardRecurring, $_GET['saved_card_id'], (int)$_SESSION['customer_id']);
      }

      if (!$cancellationHandled) {
        $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with cancelling your subscription.', 'error');
      }

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'cancel',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'cancel_savedcard':
      $savedCardId = isset($_GET['saved_card_id']) ? $_GET['saved_card_id'] : 0;
      zen_my_subscriptions_debug('savedcard:cancel:invoke', array(
        'saved_recurring_id' => (int) $savedCardId,
        'action' => 'cancel_savedcard'
      ));
      zen_paypal_cancel_savedcard_subscription($paypalSavedCardRecurring, $savedCardId, (int)$_SESSION['customer_id']);

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'cancel_savedcard',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'suspend_savedcard':
      $savedCardId = isset($_GET['saved_card_id']) ? (int) $_GET['saved_card_id'] : 0;
      zen_my_subscriptions_debug('savedcard:suspend:invoke', array(
        'saved_recurring_id' => $savedCardId
      ));
      zen_paypal_update_savedcard_subscription_status(
        $paypalSavedCardRecurring,
        $savedCardId,
        'suspended',
        'Your subscription has been suspended.',
        'suspending',
        (int) $_SESSION['customer_id'],
        'Suspended by customer.'
      );

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'suspend_savedcard',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'update_savedcard':
      $updateError = '';
      $updateSuccess = false;

      $postedToken = isset($_POST['securityToken']) ? $_POST['securityToken'] : '';
      if (!isset($_SESSION['securityToken']) || $postedToken !== $_SESSION['securityToken']) {
        $updateError = 'Your request could not be validated. Please try again.';
      } else {
        $savedCardRecurringId = isset($_POST['saved_card_recurring_id']) ? (int) $_POST['saved_card_recurring_id'] : 0;
        $newSavedCardId = isset($_POST['new_saved_card_id']) ? (int) $_POST['new_saved_card_id'] : 0;

        if ($savedCardRecurringId > 0 && $newSavedCardId > 0) {
          $lookupStart = microtime(true);
          zen_my_subscriptions_debug('savedcard:lookup:start', array(
            'saved_recurring_id' => $savedCardRecurringId,
            'context' => 'update_savedcard'
          ));
          $subscription = $paypalSavedCardRecurring->get_payment_details($savedCardRecurringId);
          zen_my_subscriptions_debug('savedcard:lookup:result', array(
            'saved_recurring_id' => $savedCardRecurringId,
            'context' => 'update_savedcard',
            'elapsed' => microtime(true) - $lookupStart,
            'found' => is_array($subscription)
          ));
          $subscriptionOwnerId = 0;
          if (is_array($subscription)) {
            if (isset($subscription['saved_card_customer_id']) && (int) $subscription['saved_card_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscription['saved_card_customer_id'];
            } elseif (isset($subscription['subscription_customer_id']) && (int) $subscription['subscription_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscription['subscription_customer_id'];
            } elseif (isset($subscription['customers_id'])) {
              $subscriptionOwnerId = (int) $subscription['customers_id'];
            }
          }

          if (!is_array($subscription) || $subscriptionOwnerId <= 0 || $subscriptionOwnerId !== (int) $_SESSION['customer_id']) {
            $updateError = 'A matching subscription could not be found, please contact us for assistance with updating your subscription payment method.';
          } else {
            $currentCardId = isset($subscription['saved_credit_card_id']) ? (int) $subscription['saved_credit_card_id'] : 0;

            if ($newSavedCardId === $currentCardId) {
              $updateSuccess = true;
            } else {
              $cardQuery = $db->Execute(
                'SELECT saved_credit_card_id'
                . ' FROM ' . TABLE_SAVED_CREDIT_CARDS
                . ' WHERE saved_credit_card_id = ' . (int) $newSavedCardId
                . '   AND customers_id = ' . (int) $_SESSION['customer_id']
                . "   AND is_deleted = '0'"
              );

              if ($cardQuery->RecordCount() === 0) {
                $updateError = 'The selected saved card could not be found. Please choose a different card.';
              } else {
                $updateInfoStart = microtime(true);
                zen_my_subscriptions_debug('savedcard:update-payment-info:start', array(
                  'saved_recurring_id' => $savedCardRecurringId,
                  'new_saved_id' => $newSavedCardId
                ));
                $paypalSavedCardRecurring->update_payment_info(
                  $savedCardRecurringId,
                  array(
                    'saved_credit_card_id' => $newSavedCardId,
                    'comments' => '  Card updated by customer.  '
                  )
                );
                zen_my_subscriptions_debug('savedcard:update-payment-info:complete', array(
                  'saved_recurring_id' => $savedCardRecurringId,
                  'new_saved_id' => $newSavedCardId,
                  'elapsed' => microtime(true) - $updateInfoStart
                ));
                $updateSuccess = true;
              }
            }
          }
        } else {
          $updateError = 'Your request could not be completed. Please choose a saved card and try again.';
        }
      }

      if ($updateSuccess) {
        $messageStack->add_session('my_subscriptions', 'Your subscription payment method has been updated.', 'success');
      } elseif ($updateError !== '') {
        $messageStack->add_session('my_subscriptions', $updateError, 'error');
      } else {
        $messageStack->add_session('my_subscriptions', 'Your subscription payment method could not be updated, please try again.', 'error');
      }

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'update_savedcard',
        'target' => 'my_subscriptions',
        'update_success' => $updateSuccess,
        'update_error_present' => ($updateError !== '')
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'reactivate_savedcard':
      $reactivateId = isset($_GET['saved_card_id']) ? (int) $_GET['saved_card_id'] : 0;
      $reactivateStart = microtime(true);
      zen_my_subscriptions_debug('savedcard:reactivate:start', array(
        'saved_recurring_id' => $reactivateId
      ));
      $paypalSavedCardRecurring->update_payment_status($reactivateId, 'scheduled', 'Re-Activated by customer.', (int)$_SESSION['customer_id']); //security check is done in this function
      zen_my_subscriptions_debug('savedcard:reactivate:updated', array(
        'saved_recurring_id' => $reactivateId,
        'elapsed' => microtime(true) - $reactivateStart
      ));

      $lookupStart = microtime(true);
      $subscription = $paypalSavedCardRecurring->get_payment_details($reactivateId);
      zen_my_subscriptions_debug('savedcard:lookup:result', array(
        'saved_recurring_id' => $reactivateId,
        'context' => 'reactivate_savedcard',
        'elapsed' => microtime(true) - $lookupStart,
        'found' => is_array($subscription)
      ));
      $subscriptionCustomerId = 0;
      if (is_array($subscription)) {
        if (isset($subscription['saved_card_customer_id']) && (int) $subscription['saved_card_customer_id'] > 0) {
          $subscriptionCustomerId = (int) $subscription['saved_card_customer_id'];
        } elseif (isset($subscription['subscription_customer_id']) && (int) $subscription['subscription_customer_id'] > 0) {
          $subscriptionCustomerId = (int) $subscription['subscription_customer_id'];
        } elseif (isset($subscription['customers_id'])) {
          $subscriptionCustomerId = (int) $subscription['customers_id'];
        }
      }

      //BOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation
      if ($subscriptionCustomerId > 0) {
        $paypalSavedCardRecurring->remove_subscription_cancellation($subscriptionCustomerId, $subscription['date'], $subscription['products_id']);
      }
      //EOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'reactivate_savedcard',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'archive':
      $archiveSuccess = false;
      $archiveError = '';
      $archiveTimer = microtime(true);
      $subscriptionId = 0;
      if (isset($_POST['subscription_id'])) {
        $subscriptionId = (int) $_POST['subscription_id'];
      } elseif (isset($_GET['subscription_id'])) {
        $subscriptionId = (int) $_GET['subscription_id'];
      }
      $archiveSecurityToken = '';
      if (isset($_POST['securityToken'])) {
        $archiveSecurityToken = (string) $_POST['securityToken'];
      } elseif (isset($_GET['securityToken'])) {
        $archiveSecurityToken = (string) $_GET['securityToken'];
      }
      $isAjaxRequest = zen_my_subscriptions_is_ajax_request();
      zen_my_subscriptions_debug('archive:action:start', array(
        'type' => 'paypal',
        'subscription_id' => $subscriptionId
      ));

      if ($archiveSecurityToken === '' || !isset($_SESSION['securityToken']) || $archiveSecurityToken !== $_SESSION['securityToken']) {
        $archiveError = 'Your request could not be validated. Please try again.';
        zen_my_subscriptions_debug('archive:action:error', array(
          'type' => 'paypal',
          'reason' => 'invalid_token'
        ));
      } else {
        if ($subscriptionId > 0) {
          $subscriptionLookupStart = microtime(true);
          $subscription = $db->Execute(
            'SELECT subscription_id, customers_id, profile_id, status
               FROM ' . TABLE_PAYPAL_RECURRING . '
              WHERE subscription_id = ' . (int) $subscriptionId . '
              LIMIT 1;'
          );
          zen_my_subscriptions_debug('archive:lookup', array(
            'type' => 'paypal',
            'subscription_id' => $subscriptionId,
            'elapsed' => microtime(true) - $subscriptionLookupStart,
            'found' => ($subscription->RecordCount() > 0)
          ));

          if ($subscription->RecordCount() > 0 && (int) $subscription->fields['customers_id'] === (int) $_SESSION['customer_id']) {
            $status = strtolower($subscription->fields['status']);
            if ($status === 'cancelled' || $status === 'canceled') {
              $existingCheckStart = microtime(true);
              $existingArchive = $db->Execute(
                'SELECT archive_id
                   FROM ' . TABLE_PAYPAL_RECURRING_ARCHIVE . '
                  WHERE customers_id = ' . (int) $_SESSION['customer_id'] . '
                    AND subscription_id = ' . (int) $subscriptionId . '
                  LIMIT 1;'
              );
              zen_my_subscriptions_debug('archive:existing-check', array(
                'type' => 'paypal',
                'subscription_id' => $subscriptionId,
                'elapsed' => microtime(true) - $existingCheckStart,
                'found' => ($existingArchive->RecordCount() > 0)
              ));

              if ($existingArchive->RecordCount() === 0) {
                $profileIdValue = isset($subscription->fields['profile_id']) && $subscription->fields['profile_id'] !== ''
                  ? "'" . zen_db_input($subscription->fields['profile_id']) . "'"
                  : 'NULL';

                $insertStart = microtime(true);
                $db->Execute(
                  'INSERT INTO ' . TABLE_PAYPAL_RECURRING_ARCHIVE . ' (customers_id, subscription_id, profile_id, archived_at)
                   VALUES (' . (int) $_SESSION['customer_id'] . ', ' . (int) $subscriptionId . ', ' . $profileIdValue . ', NOW());'
                );
                zen_my_subscriptions_debug('archive:insert', array(
                  'type' => 'paypal',
                  'subscription_id' => $subscriptionId,
                  'elapsed' => microtime(true) - $insertStart
                ));
              }

              $archiveSuccess = true;
            } else {
              $archiveError = 'Only cancelled subscriptions can be archived.';
              zen_my_subscriptions_debug('archive:action:error', array(
                'type' => 'paypal',
                'reason' => 'status_not_cancelled',
                'status' => $status
              ));
            }
          } else {
            $archiveError = 'A matching subscription could not be found, please contact us for assistance with archiving your subscription.';
            zen_my_subscriptions_debug('archive:action:error', array(
              'type' => 'paypal',
              'reason' => 'not_found'
            ));
          }
        } else {
          $archiveError = 'A matching subscription could not be found, please contact us for assistance with archiving your subscription.';
          zen_my_subscriptions_debug('archive:action:error', array(
            'type' => 'paypal',
            'reason' => 'missing_subscription_id'
          ));
        }
      }

      $archiveMessage = 'Your subscription could not be archived, please try again.';
      if ($archiveSuccess) {
        $archiveMessage = 'Your subscription has been archived.';
      } elseif ($archiveError !== '') {
        $archiveMessage = $archiveError;
      }

      zen_my_subscriptions_debug('archive:action:complete', array(
        'type' => 'paypal',
        'success' => $archiveSuccess,
        'error_present' => ($archiveError !== ''),
        'elapsed' => microtime(true) - $archiveTimer
      ));

      if ($isAjaxRequest) {
        zen_my_subscriptions_debug('my-subscriptions:ajax-response', array(
          'action' => 'archive',
          'success' => $archiveSuccess
        ));

        $responsePayload = array(
          'success' => $archiveSuccess,
          'message' => $archiveMessage,
          'type' => 'paypal',
          'subscription_id' => $subscriptionId,
          'error' => $archiveError
        );

        zen_my_subscriptions_send_json_response($responsePayload);
        exit;
      }

      if ($archiveSuccess) {
        $messageStack->add_session('my_subscriptions', $archiveMessage, 'success');
      } else {
        $messageStack->add_session('my_subscriptions', $archiveMessage, 'error');
      }

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'archive',
        'target' => 'my_subscriptions',
        'success' => $archiveSuccess
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'archive_savedcard':
      $archiveSuccess = false;
      $archiveError = '';
      $archiveTimer = microtime(true);
      $savedCardRecurringId = 0;
      if (isset($_POST['saved_card_id'])) {
        $savedCardRecurringId = (int) $_POST['saved_card_id'];
      } elseif (isset($_GET['saved_card_id'])) {
        $savedCardRecurringId = (int) $_GET['saved_card_id'];
      }
      $savedCardSecurityToken = '';
      if (isset($_POST['securityToken'])) {
        $savedCardSecurityToken = (string) $_POST['securityToken'];
      } elseif (isset($_GET['securityToken'])) {
        $savedCardSecurityToken = (string) $_GET['securityToken'];
      }
      $isAjaxRequest = zen_my_subscriptions_is_ajax_request();
      zen_my_subscriptions_debug('archive:action:start', array(
        'type' => 'saved_card',
        'saved_recurring_id' => $savedCardRecurringId
      ));

      if ($savedCardSecurityToken === '' || !isset($_SESSION['securityToken']) || $savedCardSecurityToken !== $_SESSION['securityToken']) {
        $archiveError = 'Your request could not be validated. Please try again.';
        zen_my_subscriptions_debug('archive:action:error', array(
          'type' => 'saved_card',
          'reason' => 'invalid_token'
        ));
      } else {
        if ($savedCardRecurringId > 0) {
          $lookupStart = microtime(true);
          $subscription = $paypalSavedCardRecurring->get_payment_details($savedCardRecurringId);
          zen_my_subscriptions_debug('savedcard:lookup:result', array(
            'saved_recurring_id' => $savedCardRecurringId,
            'context' => 'archive_savedcard',
            'elapsed' => microtime(true) - $lookupStart,
            'found' => is_array($subscription)
          ));
          $subscriptionOwnerId = 0;

          if (is_array($subscription)) {
            if (isset($subscription['saved_card_customer_id']) && (int) $subscription['saved_card_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscription['saved_card_customer_id'];
            } elseif (isset($subscription['subscription_customer_id']) && (int) $subscription['subscription_customer_id'] > 0) {
              $subscriptionOwnerId = (int) $subscription['subscription_customer_id'];
            } elseif (isset($subscription['customers_id'])) {
              $subscriptionOwnerId = (int) $subscription['customers_id'];
            }
          }

          if (!is_array($subscription) || $subscriptionOwnerId <= 0 || $subscriptionOwnerId !== (int) $_SESSION['customer_id']) {
            $archiveError = 'A matching subscription could not be found, please contact us for assistance with archiving your subscription.';
            zen_my_subscriptions_debug('archive:action:error', array(
              'type' => 'saved_card',
              'reason' => 'ownership_mismatch'
            ));
          } else {
            $status = isset($subscription['status']) ? strtolower(trim($subscription['status'])) : '';

            if ($status === 'cancelled' || $status === 'canceled') {
              $existingCheckStart = microtime(true);
              $existingArchive = $db->Execute(
                'SELECT archive_id'
                   . ' FROM ' . TABLE_PAYPAL_RECURRING_ARCHIVE
                   . ' WHERE customers_id = ' . (int) $_SESSION['customer_id']
                   . '   AND saved_credit_card_recurring_id = ' . (int) $savedCardRecurringId
                   . ' LIMIT 1;'
              );
              zen_my_subscriptions_debug('archive:existing-check', array(
                'type' => 'saved_card',
                'saved_recurring_id' => $savedCardRecurringId,
                'elapsed' => microtime(true) - $existingCheckStart,
                'found' => ($existingArchive->RecordCount() > 0)
              ));

              if ($existingArchive->RecordCount() === 0) {
                $insertStart = microtime(true);
                $db->Execute(
                  'INSERT INTO ' . TABLE_PAYPAL_RECURRING_ARCHIVE . ' (customers_id, saved_credit_card_recurring_id, archived_at)'
                   . ' VALUES (' . (int) $_SESSION['customer_id'] . ', ' . (int) $savedCardRecurringId . ', NOW())'
                );
                zen_my_subscriptions_debug('archive:insert', array(
                  'type' => 'saved_card',
                  'saved_recurring_id' => $savedCardRecurringId,
                  'elapsed' => microtime(true) - $insertStart
                ));
              }

              $archiveSuccess = true;
            } else {
              $archiveError = 'Only cancelled subscriptions can be archived.';
              zen_my_subscriptions_debug('archive:action:error', array(
                'type' => 'saved_card',
                'reason' => 'status_not_cancelled',
                'status' => $status
              ));
            }
          }
        } else {
          $archiveError = 'A matching subscription could not be found, please contact us for assistance with archiving your subscription.';
          zen_my_subscriptions_debug('archive:action:error', array(
            'type' => 'saved_card',
            'reason' => 'missing_subscription_id'
          ));
        }
      }

      $archiveMessage = 'Your subscription could not be archived, please try again.';
      if ($archiveSuccess) {
        $archiveMessage = 'Your subscription has been archived.';
      } elseif ($archiveError !== '') {
        $archiveMessage = $archiveError;
      }

      zen_my_subscriptions_debug('archive:action:complete', array(
        'type' => 'saved_card',
        'success' => $archiveSuccess,
        'error_present' => ($archiveError !== ''),
        'elapsed' => microtime(true) - $archiveTimer
      ));

      if ($isAjaxRequest) {
        zen_my_subscriptions_debug('my-subscriptions:ajax-response', array(
          'action' => 'archive_savedcard',
          'success' => $archiveSuccess
        ));

        $responsePayload = array(
          'success' => $archiveSuccess,
          'message' => $archiveMessage,
          'type' => 'saved_card',
          'saved_card_id' => $savedCardRecurringId,
          'error' => $archiveError
        );

        zen_my_subscriptions_send_json_response($responsePayload);
        exit;
      }

      if ($archiveSuccess) {
        $messageStack->add_session('my_subscriptions', $archiveMessage, 'success');
      } else {
        $messageStack->add_session('my_subscriptions', $archiveMessage, 'error');
      }

      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'archive_savedcard',
        'target' => 'my_subscriptions',
        'success' => $archiveSuccess
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'suspend':
      if (isset($_GET['profileid'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          // subscription exists for this customer, suspend it
          $profileIdForLog = isset($subscription->fields['profile_id']) ? substr(hash('sha256', $subscription->fields['profile_id']), 0, 16) : '';
          $suspendStart = microtime(true);
          zen_my_subscriptions_debug('paypal-profile:suspend:start', array(
            'profile_id_hash' => $profileIdForLog
          ));
          $result = $PayPalProfileManager->suspendProfile($subscription->fields, 'Suspended by customer.');
          $suspendElapsed = microtime(true) - $suspendStart;
          zen_my_subscriptions_debug('paypal-profile:suspend:result', array(
            'profile_id_hash' => $profileIdForLog,
            'elapsed' => $suspendElapsed,
            'success' => !empty($result['success']),
            'message' => isset($result['message']) ? $result['message'] : null
          ));
          if ($result['success']) {
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Suspended' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
            $paypalSavedCardRecurring->remove_group_pricing((int)$_SESSION['customer_id'], $subscription->fields['products_id']);

            zen_paypal_subscription_cache_invalidate((int) $_SESSION['customer_id'], $subscription->fields['profile_id']);

            $messageStack->add_session('my_subscriptions', 'Your subscription has been suspended.', 'success');
          } else {
            $error_message = isset($result['message']) && strlen($result['message']) > 0 ? $result['message'] : 'An unknown error occurred.';
            $messageStack->add_session('my_subscriptions', 'Your subscription has not been suspended.<br />' . $error_message, 'error');
          }
        } else {
          $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with suspending your subscription.', 'error');
        }
      }
      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'suspend',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'reactivate':
      if (isset($_GET['profileid'])) {
        // confirm the subscription exists
        $subscription = $db->Execute("SELECT profile_id, subscription_id, products_id
                                      FROM " . TABLE_PAYPAL_RECURRING . "
                                      WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                                      AND profile_id = '" . $_GET['profileid'] . "'
                                      LIMIT 1;");
        if ($subscription->RecordCount() > 0) {
          // subscription exists for this customer, reactivate it
          $profileIdForLog = isset($subscription->fields['profile_id']) ? substr(hash('sha256', $subscription->fields['profile_id']), 0, 16) : '';
          $reactivateStart = microtime(true);
          zen_my_subscriptions_debug('paypal-profile:reactivate:start', array(
            'profile_id_hash' => $profileIdForLog
          ));
          $result = $PayPalProfileManager->reactivateProfile($subscription->fields, 'Reactivated by customer.');
          $reactivateElapsed = microtime(true) - $reactivateStart;
          zen_my_subscriptions_debug('paypal-profile:reactivate:result', array(
            'profile_id_hash' => $profileIdForLog,
            'elapsed' => $reactivateElapsed,
            'success' => !empty($result['success']),
            'message' => isset($result['message']) ? $result['message'] : null
          ));
          if ($result['success']) {
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = 'Active' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
            $paypalSavedCardRecurring->create_group_pricing($subscription->fields['products_id'], (int)$_SESSION['customer_id']);
            zen_paypal_subscription_cache_invalidate((int) $_SESSION['customer_id'], $subscription->fields['profile_id']);
            $messageStack->add_session('my_subscriptions', 'Your subscription has been reactivated.', 'success');
          } else {
            $error_message = isset($result['message']) && strlen($result['message']) > 0 ? $result['message'] : 'An unknown error occurred.';
            $messageStack->add_session('my_subscriptions', 'Your subscription could not be reactivated:<br />' . $error_message, 'error');
          }
        } else {
          $messageStack->add_session('my_subscriptions', 'A matching subscription could not be found, please contact us for assistance with reactivating your subscription.', 'error');
        }
      }
      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'reactivate',
        'target' => 'my_subscriptions'
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
      break;
    case 'update_credit_card':
      $postedToken = isset($_POST['securityToken']) ? $_POST['securityToken'] : '';
      if (!isset($_SESSION['securityToken']) || $postedToken !== $_SESSION['securityToken']) {
        $messageStack->add_session('header', 'Your request could not be validated. Please try again.', 'error');
        zen_my_subscriptions_debug('my-subscriptions:redirect', array(
          'action' => 'update_credit_card',
          'target' => 'my_subscriptions',
          'reason' => 'invalid_token'
        ));
        zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
        break;
      }
      $profileIdValue = isset($_POST['profileid']) ? zen_db_prepare_input($_POST['profileid']) : '';
      $subscriptionRecord = array();

      if ($profileIdValue !== '' && isset($_SESSION['customer_id']) && (int) $_SESSION['customer_id'] > 0) {
        $lookupStart = microtime(true);
        $subscriptionLookup = $db->Execute(
          "SELECT *"
          . " FROM " . TABLE_PAYPAL_RECURRING
          . " WHERE customers_id = " . (int) $_SESSION['customer_id']
          . "   AND profile_id = '" . zen_db_input($profileIdValue) . "'"
          . " LIMIT 1;"
        );
        $profileIdHash = substr(hash('sha256', $profileIdValue), 0, 16);
        zen_my_subscriptions_debug('update-credit-card:subscription-lookup', array(
          'profile_id_hash' => $profileIdHash,
          'elapsed' => microtime(true) - $lookupStart,
          'found' => !$subscriptionLookup->EOF
        ));

        if (!$subscriptionLookup->EOF) {
          $subscriptionRecord = $subscriptionLookup->fields;
        }
      }

      if (empty($subscriptionRecord)) {
        $messageStack->add_session('header', 'A matching subscription could not be found, please try again.', 'error');
        zen_my_subscriptions_debug('my-subscriptions:redirect', array(
          'action' => 'update_credit_card',
          'target' => 'my_subscriptions',
          'reason' => 'subscription_not_found'
        ));
        zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
        break;
      }

      $profileClassification = zen_paypal_subscription_classify_profile($subscriptionRecord, $PayPalProfileManager);
      $isRestProfile = !empty($profileClassification['is_rest']);

      if ($isRestProfile) {
        $restTokenId = isset($_POST['rest_payment_source_token_id']) ? zen_db_prepare_input($_POST['rest_payment_source_token_id']) : '';
        $restVaultId = isset($_POST['rest_payment_source_vault_id']) ? zen_db_prepare_input($_POST['rest_payment_source_vault_id']) : '';
        $restPaypalEmail = isset($_POST['rest_payment_source_paypal_email']) ? zen_db_prepare_input($_POST['rest_payment_source_paypal_email']) : '';

        $restTokenId = trim((string) $restTokenId);
        $restVaultId = trim((string) $restVaultId);
        $restPaypalEmail = trim((string) $restPaypalEmail);

        $errors = array();
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

        if ($providedSources === 0) {
          $errors[] = 'Please provide a token, vaulted card ID, or PayPal email address to update your subscription payment method.';
        } elseif ($providedSources > 1) {
          $errors[] = 'Please update one payment source field at a time.';
        }

        if (!empty($errors)) {
          foreach ($errors as $errorMessage) {
            $messageStack->add_session('header', $errorMessage, 'error');
          }
          zen_my_subscriptions_debug('my-subscriptions:redirect', array(
            'action' => 'update_credit_card',
            'target' => 'my_subscriptions',
            'reason' => 'rest_input_error',
            'error_count' => count($errors)
          ));
          zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
          break;
        }

        $paymentSourcePayload = array();
        if ($restTokenId !== '') {
          $paymentSourcePayload['payment_source']['token'] = array('id' => $restTokenId);
        } elseif ($restVaultId !== '') {
          $paymentSourcePayload['payment_source']['card'] = array('vault_id' => $restVaultId);
        } elseif ($restPaypalEmail !== '') {
          $paymentSourcePayload['payment_source']['paypal'] = array('email_address' => $restPaypalEmail);
        }

        $restUpdateStart = microtime(true);
        $profileIdHash = substr(hash('sha256', $profileIdValue), 0, 16);
        zen_my_subscriptions_debug('paypal-profile:update-payment-source:start', array(
          'profile_id_hash' => $profileIdHash,
          'payload_keys' => array_keys($paymentSourcePayload)
        ));
        $updateResult = $PayPalProfileManager->updatePaymentSource($subscriptionRecord, $paymentSourcePayload);
        $restUpdateElapsed = microtime(true) - $restUpdateStart;
        $updateResultLog = is_array($updateResult) ? $updateResult : array();
        zen_my_subscriptions_debug('paypal-profile:update-payment-source:result', array(
          'profile_id_hash' => $profileIdHash,
          'elapsed' => $restUpdateElapsed,
          'success' => isset($updateResultLog['success']) ? $updateResultLog['success'] : null,
          'message' => isset($updateResultLog['message']) ? $updateResultLog['message'] : null
        ));
        if (empty($updateResult['success'])) {
          $errorMessage = !empty($updateResult['message']) ? $updateResult['message'] : 'Your subscription payment method could not be updated.';
          if (isset($updateResult['details'])) {
            if (is_array($updateResult['details'])) {
              $detailsText = zen_paypal_subscription_format_error_details_text($updateResult['details']);
              if ($detailsText !== '') {
                $errorMessage .= ' ' . $detailsText;
              }
            } elseif (is_string($updateResult['details']) && trim($updateResult['details']) !== '') {
              $errorMessage .= ' ' . trim($updateResult['details']);
            }
          }
          $messageStack->add_session('header', $errorMessage, 'error');
        } else {
          zen_paypal_subscription_cache_invalidate((int) $_SESSION['customer_id'], $profileIdValue);
          $messageStack->add_session('header', 'Your subscription payment method has been updated.', 'success');
        }

        zen_my_subscriptions_debug('my-subscriptions:redirect', array(
          'action' => 'update_credit_card',
          'target' => 'my_subscriptions',
          'rest_update' => true,
          'success' => !empty($updateResult['success'])
        ));
        zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));
        break;
      }

      $expiry_date = zen_db_prepare_input($_POST['monthexpiry']) . zen_db_prepare_input($_POST['yearexpiry']);

      //country is stored differently in orders table, the input form, and paypal.  Find different formats here.
      $countryValue = zen_db_prepare_input($_POST['zone_country_id']);
      $country_name = '';
      $country_code = '';
      if (is_numeric($countryValue)) {
        $country_name = zen_get_country_name($countryValue);
        $country_info = zen_get_countries($countryValue);
        if (is_array($country_info) && isset($country_info['countries_iso_code_2'])) {
          $country_code = $country_info['countries_iso_code_2'];
        }
      } else {
        $isoCandidate = strtoupper(trim($countryValue));
        $nameCandidate = trim($countryValue);
        if (strlen($isoCandidate) === 2) {
          $countryLookup = "SELECT countries_id, countries_name FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = :isoCode LIMIT 1";
          $countryLookup = $db->bindVars($countryLookup, ':isoCode', $isoCandidate, 'string');
          $countryResult = $db->Execute($countryLookup);
          if (!$countryResult->EOF) {
            $country_code = $isoCandidate;
            $country_name = $countryResult->fields['countries_name'];
          }
        } elseif (strlen($nameCandidate) > 0) {
          $countryLookup = "SELECT countries_name, countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_name = :countryName LIMIT 1";
          $countryLookup = $db->bindVars($countryLookup, ':countryName', $nameCandidate, 'string');
          $countryResult = $db->Execute($countryLookup);
          if (!$countryResult->EOF) {
            $country_code = $countryResult->fields['countries_iso_code_2'];
            $country_name = $countryResult->fields['countries_name'];
          }
        }
      }

      if ($country_code === '' && isset($_POST['countrycode'])) {
        $postedCountryCode = strtoupper(trim(zen_db_prepare_input($_POST['countrycode'])));
        if (strlen($postedCountryCode) === 2) {
          $country_code = $postedCountryCode;
          if ($country_name === '') {
            $countryLookup = "SELECT countries_name FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = :postedIso LIMIT 1";
            $countryLookup = $db->bindVars($countryLookup, ':postedIso', $postedCountryCode, 'string');
            $countryResult = $db->Execute($countryLookup);
            if (!$countryResult->EOF) {
              $country_name = $countryResult->fields['countries_name'];
            }
          }
        }
      }

      if ($country_code === '') {
        $defaultCountry = zen_get_countries(STORE_COUNTRY);
        if (is_array($defaultCountry) && isset($defaultCountry['countries_iso_code_2'])) {
          $country_code = $defaultCountry['countries_iso_code_2'];
          if ($country_name === '' && isset($defaultCountry['countries_name'])) {
            $country_name = $defaultCountry['countries_name'];
          }
        }
      }

      $order_address_update = array(
        'billing_street_address' => zen_db_prepare_input($_POST['street_address']),
        'billing_suburb' => zen_db_prepare_input($_POST['suburb']),
        'billing_city' => zen_db_prepare_input($_POST['city']),
        'billing_postcode' => zen_db_prepare_input($_POST['postcode']),
        'billing_state' => zen_db_prepare_input($_POST['state']),
        'billing_country' => $country_name
      );

      if (!class_exists('cc_validation')) {
        include(DIR_WS_CLASSES . 'cc_validation.php');
      }
      if (is_null($cc_validation)) {
        $cc_validation = new cc_validation();
      }

      $cc_valid = $cc_validation->validate($_POST['cardnumber'], $_POST['monthexpiry'], $_POST['yearexpiry']);

      if ($cc_valid && $_POST['cvv'] != '') {
        $DataArray = array();
        // keys will be made uppercase by AngelEye
        $DataArray['URPPFields']['profileid'] = $profileIdValue;
        $DataArray['CCDetails']['creditcardtype'] = zen_db_prepare_input($_POST['paymenttype']);
        $DataArray['CCDetails']['acct'] = $cc_validation->cc_number;
        $DataArray['CCDetails']['expdate'] = $cc_validation->cc_expiry_month . $cc_validation->cc_expiry_year;
        $DataArray['CCDetails']['cvv2'] = zen_db_prepare_input($_POST['cvv']);
        if ($_POST['startdate'] != '') {
          $DataArray['CCDetails']['startdate'] = zen_db_prepare_input($_POST['startdate']);
        }
        if ($_POST['issuenumber'] != '') {
          $DataArray['CCDetails']['issuenumber'] = zen_db_prepare_input($_POST['issuenumber']);
        }
        $DataArray['BillingAddress']['street'] = $order_address_update['billing_street_address'];
        $DataArray['BillingAddress']['street2'] = $order_address_update['billing_suburb'];
        $DataArray['BillingAddress']['city'] = $order_address_update['billing_city'];
        $DataArray['BillingAddress']['zip'] = $order_address_update['billing_postcode'];
        $DataArray['BillingAddress']['state'] = $order_address_update['billing_state'];
        $DataArray['BillingAddress']['countrycode'] = $country_code;

        $profileIdHash = substr(hash('sha256', $profileIdValue), 0, 16);
        $legacyUpdateStart = microtime(true);
        zen_my_subscriptions_debug('paypal-profile:update-card:start', array(
          'profile_id_hash' => $profileIdHash
        ));
        $PayPalResult = $PayPal->UpdateRecurringPaymentsProfile($DataArray);
        $legacyUpdateElapsed = microtime(true) - $legacyUpdateStart;
        $errorCount = (is_array($PayPalResult['ERRORS']) ? count($PayPalResult['ERRORS']) : 0);
        zen_my_subscriptions_debug('paypal-profile:update-card:result', array(
          'profile_id_hash' => $profileIdHash,
          'elapsed' => $legacyUpdateElapsed,
          'error_count' => $errorCount
        ));

        if (is_array($PayPalResult['ERRORS']) && sizeof($PayPalResult['ERRORS']) > 0) {
          foreach ($PayPalResult['ERRORS'] as $error) {
            $messageStack->add_session('header', $error['L_SHORTMESSAGE'], 'error');
          }
        } else {
          //BOF saved credit card modification
          //Save card in the customers account if saved cards are enabled. Since the before_process succeeded.
          if (!class_exists('paypalsavedcard')) {
            include(DIR_FS_CATALOG . DIR_WS_MODULES . '/payment/paypalsavedcard.php');
          }
          $paypalsavedcard = new paypalsavedcard();
          $paypalsavedcard->add_saved_card($cc_validation->cc_number, zen_db_prepare_input($_POST['cvv']), $expiry_date, zen_db_prepare_input($_POST['fullname']), zen_db_prepare_input($_POST['paymenttype']));
          //EOF saved creditcard modification

          //update address
          $_POST['action'] = 'update_address'; //also update the default address
          $orders_id = $_POST['orders_id'];
          $page = 'header';

          if ($orders_id > 0) {
            $sql_data_array = array(
              array('fieldName' => 'billing_street_address', 'value' => $order_address_update['billing_street_address'], 'type' => 'string'),
              array('fieldName' => 'billing_suburb', 'value' => $order_address_update['billing_suburb'], 'type' => 'string'),
              array('fieldName' => 'billing_city', 'value' => $order_address_update['billing_city'], 'type' => 'string'),
              array('fieldName' => 'billing_postcode', 'value' => $order_address_update['billing_postcode'], 'type' => 'string'),
              array('fieldName' => 'billing_state', 'value' => $order_address_update['billing_state'], 'type' => 'string'),
              array('fieldName' => 'billing_country', 'value' => $country_name, 'type' => 'string')
            );

            $where = 'orders_id = ' . $orders_id . ' AND customers_id = ' . (int) $_SESSION['customer_id'];

            //include(DIR_FS_CATALOG . DIR_WS_MODULES . '/numinix/save_addresses.php'); // this updates the customers default address
            $db->perform(TABLE_ORDERS, $sql_data_array, 'update', $where);
          }
          $messageStack->add_session('header', 'Profile Successfully Updated.', 'success');
        }
      } else {
        $messageStack->add_session('header', 'Please enter your complete credit card and billing address information', 'error');
      }

      $legacyErrors = null;
      if (isset($PayPalResult) && is_array($PayPalResult) && isset($PayPalResult['ERRORS']) && is_array($PayPalResult['ERRORS'])) {
        $legacyErrors = count($PayPalResult['ERRORS']);
      }
      zen_my_subscriptions_debug('my-subscriptions:redirect', array(
        'action' => 'update_credit_card',
        'target' => 'my_subscriptions',
        'rest_update' => false,
        'legacy_error_count' => $legacyErrors,
        'cc_valid' => isset($cc_valid) ? (bool) $cc_valid : null
      ));
      zen_redirect(zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'));

      break;

    default:
      /*
       * Get all Paypal subscriptions
       */

      $archivedPaypalSubscriptionIds = array();
      $archivedSavedCardRecurringIds = array();

      $defaultArchiveQueryStart = microtime(true);
      zen_my_subscriptions_debug('archived-subscriptions-query:start', array(
        'context' => 'default'
      ));
      $archivedSubscriptions = $db->Execute(
        'SELECT subscription_id, saved_credit_card_recurring_id
           FROM ' . TABLE_PAYPAL_RECURRING_ARCHIVE . '
          WHERE customers_id = ' . (int) $_SESSION['customer_id'] . ';'
      );

      while (!$archivedSubscriptions->EOF) {
        if (!empty($archivedSubscriptions->fields['subscription_id'])) {
          $archivedPaypalSubscriptionIds[] = (int) $archivedSubscriptions->fields['subscription_id'];
        }

        if (!empty($archivedSubscriptions->fields['saved_credit_card_recurring_id'])) {
          $archivedSavedCardRecurringIds[] = (int) $archivedSubscriptions->fields['saved_credit_card_recurring_id'];
        }

        $archivedSubscriptions->moveNext();
      }

      $archivedPaypalSubscriptionIds = array_unique($archivedPaypalSubscriptionIds);
      $archivedSavedCardRecurringIds = array_unique($archivedSavedCardRecurringIds);
      zen_my_subscriptions_debug('archived-subscriptions-query:end', array(
        'context' => 'default',
        'elapsed' => microtime(true) - $defaultArchiveQueryStart,
        'paypal_subscription_id_count' => count($archivedPaypalSubscriptionIds),
        'saved_card_recurring_id_count' => count($archivedSavedCardRecurringIds)
      ));

      $excludedPaypalSubscriptionClause = '';
      if (!empty($archivedPaypalSubscriptionIds)) {
        $excludedPaypalSubscriptionClause = ' AND pr.subscription_id NOT IN (' . implode(',', $archivedPaypalSubscriptionIds) . ')';
      }

      $subscriptions_query = "SELECT pr.*, (CASE WHEN pr.status LIKE 'Active' THEN '3' WHEN pr.status LIKE 'Suspended' THEN '2' ELSE '0' END) AS status_sort
                              FROM " . TABLE_PAYPAL_RECURRING . " pr
                              WHERE pr.customers_id = " . (int)$_SESSION['customer_id'] .
                              $excludedPaypalSubscriptionClause .
                              " ORDER BY status_sort DESC, pr.subscription_id DESC;";
      //sort by active and then suspended and then newest
//print $subscriptions_query;
      $defaultSubscriptionsQueryStart = microtime(true);
      zen_my_subscriptions_debug('default:subscriptions-query:start', array());
      $subscriptions_result = $db->Execute($subscriptions_query);
   
      if (!isset($row_counter)) {
        $row_counter = 0;
      }

      $subscriptions = array();
      $paypalSubscriptionCount = 0;
      $paypalSubscriptionRows = array();

      while (!$subscriptions_result->EOF) {
        $row = $subscriptions_result->fields;
        $paypalSubscriptionRows[] = $row;

        $subscriptions_result->moveNext();
      }

      zen_paypal_subscription_cache_prefetch_subscriptions($paypalSubscriptionRows);

      foreach ($paypalSubscriptionRows as $subscriptionFields) {
        $order_id = $subscriptionFields['orders_id'];
        $subscriptions[$order_id] = $subscriptionFields;
        $subscriptions[$order_id]['type'] = 'paypal_recurring';
        $subscriptions[$order_id]['subscription_id'] = isset($subscriptionFields['subscription_id']) ? (int) $subscriptionFields['subscription_id'] : 0;
        $paypalSubscriptionCount++;

        $profileId = isset($subscriptionFields['profile_id']) ? trim($subscriptionFields['profile_id']) : '';
        $subscriptions[$order_id]['profile_id'] = $profileId;
        $subscriptions[$order_id]['profile'] = array();
        if ($profileId !== '') {
          $subscriptions[$order_id]['profile']['PROFILEID'] = $profileId;
          $subscriptions[$order_id]['profile']['id'] = $profileId;
        }

        $status = isset($subscriptionFields['status']) ? $subscriptionFields['status'] : '';
        $subscriptions[$order_id]['status'] = $status;
        if ($profileId !== '' && $status !== '') {
          $subscriptions[$order_id]['profile']['STATUS'] = $status;
          $subscriptions[$order_id]['profile']['status'] = $status;
        }

        $subscriptions[$order_id]['profile_source'] = '';
        $subscriptions[$order_id]['is_rest_profile'] = false;
        if ($profileId !== '') {
          zen_paypal_subscription_consume_cache_for_subscription(
            $subscriptions[$order_id]
          );

          if (isset($subscriptions[$order_id]['status'])) {
            $status = $subscriptions[$order_id]['status'];
          }
        }

        $currencyCode = '';
        if (isset($subscriptionFields['currencycode']) && $subscriptionFields['currencycode'] !== '') {
          $currencyCode = $subscriptionFields['currencycode'];
        } elseif (isset($currency)) {
          $currencyCode = $currency;
        }
        $subscriptions[$order_id]['currencycode'] = $currencyCode;

        $price = isset($subscriptionFields['amount']) ? $subscriptionFields['amount'] : '';
        $subscriptions[$order_id]['price'] = ($price !== '' ? $price : '0.00');

        $subscriptions[$order_id]['billingfrequency'] = isset($subscriptionFields['billingfrequency']) ? (string) $subscriptionFields['billingfrequency'] : '';
        $subscriptions[$order_id]['billingperiod'] = isset($subscriptionFields['billingperiod']) ? $subscriptionFields['billingperiod'] : '';

        $startDate = '';
        if (isset($subscriptionFields['profilestartdate'])) {
          $startDate = zen_paypal_normalize_subscription_date($subscriptionFields['profilestartdate']);
        } elseif (isset($subscriptionFields['date_added'])) {
          $startDate = zen_paypal_normalize_subscription_date($subscriptionFields['date_added']);
        }
        $subscriptions[$order_id]['start_date'] = $startDate;

        $nextDate = '';
        if (isset($subscriptionFields['next_payment_date'])) {
          $nextDate = zen_paypal_normalize_subscription_date($subscriptionFields['next_payment_date']);
        }
        $subscriptions[$order_id]['next_date'] = $nextDate;

        $paymentMethod = '';
        if (isset($subscriptionFields['payment_method']) && trim($subscriptionFields['payment_method']) !== '') {
          $paymentMethod = trim($subscriptionFields['payment_method']);
        }
        if ($paymentMethod === 'PayPal' && !empty($subscriptions[$order_id]['profile'])) {
          $profileData = $subscriptions[$order_id]['profile'];
          if (isset($profileData['CREDITCARDTYPE']) && isset($profileData['ACCT'])) {
            $paymentMethod = $profileData['CREDITCARDTYPE'] . ' ' . $profileData['ACCT'];
          } elseif (isset($profileData['payment_source']['card']['brand']) && isset($profileData['payment_source']['card']['last_digits'])) {
            $paymentMethod = $profileData['payment_source']['card']['brand'] . ' ' . $profileData['payment_source']['card']['last_digits'];
          }
        }
        $subscriptions[$order_id]['payment_method'] = ($paymentMethod !== '' ? $paymentMethod : 'PayPal');

        $subscriptions[$order_id]['refresh_eligible'] = ($profileId !== '' && !zen_paypal_subscription_is_cancelled_status($status));
      }


      zen_my_subscriptions_debug('default:subscriptions-query:end', array(
        'elapsed' => microtime(true) - $defaultSubscriptionsQueryStart,
        'subscription_count' => $paypalSubscriptionCount
      ));

      /*
       *  Get all saved credit card subscriptions
       */

        $savedCardLookupStart = microtime(true);
        zen_my_subscriptions_debug('savedcard:lookup:start', array(
          'context' => 'default_list'
        ));
        $savedcard_subscriptions = $paypalSavedCardRecurring->get_customer_subscriptions((int)$_SESSION['customer_id']);
        $savedcardInitialCount = is_array($savedcard_subscriptions) ? count($savedcard_subscriptions) : 0;
        $savedCardStatsCount = 0;
        $savedCardStatsElapsed = 0.0;
        zen_my_subscriptions_debug('savedcard:lookup:result', array(
          'context' => 'default_list',
          'elapsed' => microtime(true) - $savedCardLookupStart,
          'count' => $savedcardInitialCount
        ));
        $savedCardRecurringIds = array();
        if (is_array($savedcard_subscriptions)) {
          foreach ($savedcard_subscriptions as $subscriptionRow) {
            if (isset($subscriptionRow['saved_credit_card_recurring_id'])) {
              $recurringId = (int) $subscriptionRow['saved_credit_card_recurring_id'];
              if ($recurringId > 0) {
                $savedCardRecurringIds[$recurringId] = $recurringId;
              }
            }
          }
        }

        $savedCardDetailsById = array();
        $savedCardOrderStats = array();
        $savedCardDetailsElapsed = 0.0;

        if (!empty($savedCardRecurringIds)) {
          $detailsStart = microtime(true);
          $savedCardIdList = implode(',', array_map('intval', array_values($savedCardRecurringIds)));
          if ($savedCardIdList !== '') {
            $detailsSql = '
         SELECT
           sccr.*,
           sccr.products_id AS sccr_products_id,
           sccr.products_name AS sccr_products_name,
           sccr.products_model AS sccr_products_model,
           sccr.currency_code AS sccr_currency_code,
           sccr.billing_period AS sccr_billing_period,
           sccr.billing_frequency AS sccr_billing_frequency,
           sccr.total_billing_cycles AS sccr_total_billing_cycles,
           sccr.domain AS sccr_domain,
           sccr.subscription_attributes_json AS sccr_subscription_attributes_json,
           COALESCE(scc.customers_id, c.customers_id) AS subscription_customer_id,
           scc.*,
           c.*,
           op.orders_id AS original_orders_id,
           o.currency AS order_currency_code,
           o.date_purchased AS order_date_purchased,
           scc.customers_id AS saved_card_customer_id
         FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr
         LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
         INNER JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = scc.customers_id
         LEFT JOIN ' . TABLE_ORDERS_PRODUCTS . ' op ON op.orders_products_id = sccr.original_orders_products_id
         LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = op.orders_id
         WHERE sccr.saved_credit_card_recurring_id IN (' . $savedCardIdList . ')';
            $detailsResult = $db->Execute($detailsSql);
            while (!$detailsResult->EOF) {
              $recurringId = isset($detailsResult->fields['saved_credit_card_recurring_id']) ? (int) $detailsResult->fields['saved_credit_card_recurring_id'] : 0;
              if ($recurringId > 0) {
                $normalizedDetails = $paypalSavedCardRecurring->get_payment_details($recurringId, $detailsResult->fields);
                if (is_array($normalizedDetails)) {
                  $savedCardDetailsById[$recurringId] = $normalizedDetails;
                  $originalOrdersProductsId = isset($normalizedDetails['original_orders_products_id']) ? (int) $normalizedDetails['original_orders_products_id'] : 0;
                  if ($originalOrdersProductsId > 0) {
                    if (!isset($savedCardOrderStats[$originalOrdersProductsId])) {
                      $savedCardOrderStats[$originalOrdersProductsId] = array();
                    }
                    $startDateCandidate = '';
                    if (isset($normalizedDetails['order_date_purchased']) && $normalizedDetails['order_date_purchased'] !== '') {
                      $startDateCandidate = $normalizedDetails['order_date_purchased'];
                    } elseif (isset($normalizedDetails['date_purchased']) && $normalizedDetails['date_purchased'] !== '') {
                      $startDateCandidate = $normalizedDetails['date_purchased'];
                    }
                    if ($startDateCandidate !== '') {
                      $timestamp = strtotime($startDateCandidate);
                      if ($timestamp !== false) {
                        $savedCardOrderStats[$originalOrdersProductsId]['start_date'] = date('Y-m-d', $timestamp);
                      }
                    }
                  }
                }
              }
              $detailsResult->MoveNext();
            }
          }
          $savedCardDetailsElapsed = microtime(true) - $detailsStart;

          $orderProductIds = array();
          foreach ($savedCardDetailsById as $detailsRow) {
            $orderProductId = isset($detailsRow['original_orders_products_id']) ? (int) $detailsRow['original_orders_products_id'] : 0;
            if ($orderProductId > 0) {
              $orderProductIds[$orderProductId] = $orderProductId;
            }
          }
          if (!empty($orderProductIds)) {
            $orderProductIdList = implode(',', array_map('intval', array_values($orderProductIds)));
            if ($orderProductIdList !== '') {
              $completedSql = "SELECT original_orders_products_id, COUNT(*) AS num_payments FROM "
                . TABLE_SAVED_CREDIT_CARDS_RECURRING
                . " WHERE original_orders_products_id IN ("
                . $orderProductIdList
                . ") AND status = 'complete' GROUP BY original_orders_products_id";
              $completedResult = $db->Execute($completedSql);
              while (!$completedResult->EOF) {
                $orderProductId = (int) $completedResult->fields['original_orders_products_id'];
                $completedCount = (int) $completedResult->fields['num_payments'];
                if (!isset($savedCardOrderStats[$orderProductId])) {
                  $savedCardOrderStats[$orderProductId] = array();
                }
                $savedCardOrderStats[$orderProductId]['completed_payments'] = $completedCount;
                $completedResult->MoveNext();
              }
            }
          }
        }

        $savedCardStatsSkipped = 0;
        foreach ($savedcard_subscriptions as $op_id => $sub) {
          if (!empty($archivedSavedCardRecurringIds) && in_array((int) $sub['saved_credit_card_recurring_id'], $archivedSavedCardRecurringIds, true)) {
            unset($savedcard_subscriptions[$op_id]);
            continue;
          }

          $recurringId = isset($sub['saved_credit_card_recurring_id']) ? (int) $sub['saved_credit_card_recurring_id'] : 0;
          $preloadedDetails = isset($savedCardDetailsById[$recurringId]) ? $savedCardDetailsById[$recurringId] : null;
          $rawStatus = isset($sub['status']) ? strtolower($sub['status']) : '';
          $shouldComputeStats = ($recurringId > 0) && !in_array($rawStatus, array('cancelled', 'canceled'), true);

          if ($shouldComputeStats) {
            $statsStart = microtime(true);
            $subscriptionStats = $paypalSavedCardRecurring->subscription_stats($recurringId, $preloadedDetails, $savedCardOrderStats);
            $statsElapsed = microtime(true) - $statsStart;
            $savedCardStatsCount++;
            $savedCardStatsElapsed += $statsElapsed;
            zen_my_subscriptions_debug('savedcard:subscription-stats', array(
              'recurring_id' => $recurringId > 0 ? $recurringId : null,
              'elapsed' => $statsElapsed
            ));
          } else {
            $subscriptionStats = array(
              'start_date' => '',
              'payments_completed' => 0,
              'next_date' => '',
              'payments_remaining' => 0,
              'overdue_balance' => 0,
              'missing_source_order' => false,
            );
            $savedCardStatsSkipped++;
            zen_my_subscriptions_debug('savedcard:subscription-stats', array(
              'recurring_id' => $recurringId > 0 ? $recurringId : null,
              'elapsed' => 0,
              'skipped' => true
            ));
          }

          $originalOrdersProductsId = 0;
          if (is_array($preloadedDetails) && isset($preloadedDetails['original_orders_products_id'])) {
            $originalOrdersProductsId = (int) $preloadedDetails['original_orders_products_id'];
          } elseif (isset($sub['original_orders_products_id'])) {
            $originalOrdersProductsId = (int) $sub['original_orders_products_id'];
          }

          if ($originalOrdersProductsId > 0 && (!isset($subscriptionStats['start_date']) || $subscriptionStats['start_date'] === '')) {
            if (isset($savedCardOrderStats[$originalOrdersProductsId]['start_date'])) {
              $subscriptionStats['start_date'] = $savedCardOrderStats[$originalOrdersProductsId]['start_date'];
            }
          }

          $savedcard_subscriptions[$op_id] = $subscriptionStats;
          $savedcard_subscriptions[$op_id]['products_id'] = isset($sub['products_id']) ? $sub['products_id'] : null;
          $savedcard_subscriptions[$op_id]['saved_credit_card_recurring_id'] = $recurringId;
          $savedcard_subscriptions[$op_id]['saved_credit_card_id'] = isset($sub['saved_credit_card_id']) ? (int) $sub['saved_credit_card_id'] : 0;
          $savedcard_subscriptions[$op_id]['price'] = $sub['amount'];
          $savedcard_subscriptions[$op_id]['next_date'] = $sub['date'];
          $savedcard_subscriptions[$op_id]['payment_method'] = $sub['type'] . ' ' . $sub['last_digits'];
          switch ($rawStatus) {
            case 'scheduled':
              $displayStatus = 'Active';
              break;
            case 'cancelled':
            case 'canceled':
              $displayStatus = 'Cancelled';
              break;
            case 'suspended':
            case 'suspend':
              $displayStatus = 'Suspended';
              break;
            default:
              $displayStatus = strlen($rawStatus) > 0 ? ucwords($rawStatus) : 'Suspended';
              break;
          }
          $savedcard_subscriptions[$op_id]['status'] = $displayStatus;
          $savedcard_subscriptions[$op_id]['raw_status'] = $rawStatus;
          $savedcard_subscriptions[$op_id]['type'] = 'saved_card_recurring';
        }
        zen_my_subscriptions_debug('savedcard:lookup:complete', array(
          'context' => 'default_list',
          'initial_count' => $savedcardInitialCount,
          'active_count' => is_array($savedcard_subscriptions) ? count($savedcard_subscriptions) : 0,
          'stats_calls' => $savedCardStatsCount,
          'stats_elapsed' => $savedCardStatsElapsed,
          'stats_skipped' => $savedCardStatsSkipped,
          'details_elapsed' => $savedCardDetailsElapsed,
          'details_loaded' => count($savedCardDetailsById)
        ));

        $subscriptions = array_merge($subscriptions, $savedcard_subscriptions);

        zen_my_subscriptions_debug('default:subscriptions:merged', array(
          'total_count' => count($subscriptions)
        ));

      $subscriptionProductIds = array();
      foreach ($subscriptions as $subscriptionData) {
        if (!empty($subscriptionData['products_id'])) {
          $subscriptionProductIds[(int) $subscriptionData['products_id']] = true;
        }
      }

      $productTypesById = array();
      if (!empty($subscriptionProductIds)) {
        $productsTypeQuery = $db->Execute(
          "SELECT products_id, products_type FROM " . TABLE_PRODUCTS . " WHERE products_id IN (" . implode(',', array_keys($subscriptionProductIds)) . ")"
        );
        while (!$productsTypeQuery->EOF) {
          $productTypesById[(int) $productsTypeQuery->fields['products_id']] = (int) $productsTypeQuery->fields['products_type'];
          $productsTypeQuery->moveNext();
        }
      }

      foreach ($subscriptions as $subscriptionIndex => $subscriptionData) {
        $productId = isset($subscriptionData['products_id']) ? (int) $subscriptionData['products_id'] : 0;
        $subscriptions[$subscriptionIndex]['products_type'] = isset($productTypesById[$productId]) ? $productTypesById[$productId] : null;
      }

       /*
        *  Get address and saved cards for populating selects
        */

      //get all credit cards
      $sql = "SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " AND is_deleted='0'";
      $result = $db->Execute($sql);
      $saved_credit_cards = array();

      while(!$result->EOF) {
        $saved_credit_cards[] = $result->fields;
        $result->moveNext();
      }

      //get default address
      $addresses_query = "SELECT 
                     o.orders_id,
                     billing_street_address AS entry_street_address,
                     billing_suburb AS entry_suburb,
                     billing_city AS entry_city,
                     billing_postcode AS entry_postcode,
                     billing_state AS entry_state,
                     billing_country AS entry_country,
                     c.countries_id AS entry_country_id
                    FROM   " . TABLE_PAYPAL_RECURRING . " p
                    JOIN " . TABLE_ORDERS . " o ON p.orders_id = o.orders_id
                    LEFT JOIN " . TABLE_COUNTRIES . " c ON c.countries_name = o.billing_country 
                    WHERE  p.customers_id = :customersID 
                      AND p.status like 'Active'
                   ORDER BY subscription_id
                   LIMIT 1
";            

       $addresses_query = $db->bindVars($addresses_query, ':customersID', $_SESSION['customer_id'], 'integer');
       $entry = $db->Execute($addresses_query);
      break;
  }
