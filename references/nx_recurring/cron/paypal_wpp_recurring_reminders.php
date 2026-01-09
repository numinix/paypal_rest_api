<?php
  require('../includes/configure.php');
  ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
  chdir(DIR_FS_CATALOG);
  require_once('includes/application_top.php');
  $subscription = $db->Execute("SELECT * FROM " . TABLE_PAYPAL_RECURRING . " ORDER BY subscription_id ASC;");
  if ($subscription->RecordCount() > 0) {
    require_once(DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
    require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
    require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypal/PayPalProfileManager.php');
    $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
    $PayPal = new PayPal($PayPalConfig);
    $paypalSavedCardRecurring = new paypalSavedCardRecurring();
    $PayPalRestClient = $paypalSavedCardRecurring->get_paypal_rest_client();
    $PayPalProfileManager = PayPalProfileManager::create($PayPalRestClient, $PayPal);
    while (!$subscription->EOF) {
      $statusResult = $PayPalProfileManager->getProfileStatus($subscription->fields);
      $statusResult = is_array($statusResult) ? $statusResult : array();
      $profile = array();
      $useLegacyProfile = true;
      if (!empty($statusResult['success']) && isset($statusResult['profile']) && is_array($statusResult['profile'])) {
        $profile = $statusResult['profile'];
        $useLegacyProfile = false;
      } elseif (isset($statusResult['retry']) && empty($statusResult['retry'])) {
        if (!empty($statusResult['message'])) {
          error_log('PayPal REST profile lookup failed for cron subscription ' . $subscription->fields['profile_id'] . ': ' . $statusResult['message']);
        }
        $useLegacyProfile = false;
      }
      if ($useLegacyProfile) {
        $data = array();
        $data['GRPPDFields'] = array('PROFILEID' => $subscription->fields['profile_id']);
        $legacyProfile = $PayPal->GetRecurringPaymentsProfileDetails($data);
        if (is_array($legacyProfile)) {
          $profile = $legacyProfile;
        } else {
          $profile = array();
        }
      }
      $profileErrors = (isset($profile['ERRORS']) && is_array($profile['ERRORS'])) ? $profile['ERRORS'] : array();
      if (!sizeof($profileErrors) > 0) {
        $currentStatus = '';
        if ($statusResult['success'] && isset($statusResult['status']) && strlen($statusResult['status']) > 0) {
          $currentStatus = $statusResult['status'];
        } elseif (isset($profile['STATUS'])) {
          $currentStatus = $profile['STATUS'];
        } elseif (isset($profile['status'])) {
          $currentStatus = $profile['status'];
        }
        if (strlen($currentStatus) == 0) {
          $currentStatus = $subscription->fields['status'];
        }
        if ($subscription->fields['status'] != $currentStatus) {
          // update the status
          $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET status = '" . zen_db_input($currentStatus) . "' WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");
        }
        $normalizedStatus = strtoupper($currentStatus);
        // renewal reminders
        if (PAYPAL_WPP_RECURRING_RENEWAL_REMINDER > 0 && $normalizedStatus == 'ACTIVE') {

          $expiration_date = strtotime($subscription->fields['expiration_date']);
          if ($subscription->fields['expiration_date'] > 0 && (date('Y-m-d') == date('Y-m-d', strtotime('-' . (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER . ' days', $expiration_date))) && $subscription->fields['reminded'] != date('Y-m-d')) { // mutiply the number of seconds in a day by the number of days to remind
            // build the reminder email
            $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");
            if ($customer->RecordCount() > 0) {
              $products_id = $subscription->fields['products_id'];
              $products_name = zen_get_products_name($products_id);
              if ($products_name) {
                $email_msg = sprintf(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL, $customer->fields['customers_firstname'], addslashes($products_name), (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER, $subscription->fields['expiration_date'], zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
              } else {
                $email_msg = sprintr(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT, $customer->fields['customers_firstname'], (int)PAYPAL_WPP_RECURRING_RENEWAL_REMINDER, $subscription->fields['expiration_date']);
              } 
              $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
              // send the reminder email
              zen_mail($customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], $customer->fields['customers_email_address'], sprintf(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_SUBJECT, $subscription->fields['profile_id']), $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');            
            }
            // mark as reminded regardless of whether customer existed to avoid unnecessary server load on future executions
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET reminded = now() WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");            
          }
        }
        // payment reminders
        if ((defined('PAYPAL_WPP_RECURRING_PAYMENT_REMINDER') && PAYPAL_WPP_RECURRING_PAYMENT_REMINDER > 0) && $normalizedStatus == 'ACTIVE') {
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
                $nextDateParts = strlen($nextBillingRaw) > 0 ? explode('T', str_replace('Z', '', $nextBillingRaw)) : array('');
                $next_date = (strlen($nextDateParts[0]) > 0) ? strtotime($nextDateParts[0]) : false;
                if ($next_date && date('Y-m-d') == date('Y-m-d', strtotime('-' . (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER . ' days', $next_date))) {
                // build the reminder email
            $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");
            if ($customer->RecordCount() > 0) {
              $products_id = $subscription->fields['products_id'];
              $products_name = zen_get_products_name($products_id);
              if ($products_name) {
                $email_msg = sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL, $customer->fields['customers_firstname'], addslashes($products_name), (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER, date('m-d-Y', $next_date), zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
              } else {
                $email_msg = sprintr(PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT, $customer->fields['customers_firstname'], (int)PAYPAL_WPP_RECURRING_PAYMENT_REMINDER, date('m-d-Y', $next_date));
              } 
              $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
              // send the reminder email
              zen_mail($customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], $customer->fields['customers_email_address'], sprintf(PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT, $subscription->fields['profile_id']), $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');            
            }
            // mark as reminded regardless of whether customer existed to avoid unnecessary server load on future executions
            $db->Execute("UPDATE " . TABLE_PAYPAL_RECURRING . " SET reminded = now() WHERE subscription_id = " . (int)$subscription->fields['subscription_id'] . " LIMIT 1;");              
					}        	
				}
				// expired notices
        $expiration_date = strtotime($subscription->fields['expiration_date']);
        if (date('Y-m-d') == date('Y-m-d', $expiration_date)) {
					// send expiration notice
          $customer = $db->Execute("SELECT customers_firstname, customers_lastname, customers_email_address FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$subscription->fields['customers_id'] . " LIMIT 1;");            
          if ($customer->RecordCount() > 0) {
            $products_id = $subscription->fields['products_id'];
            $products_name = zen_get_products_name($products_id);
            if ($products_name) {
              $email_msg = sprintf(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE, $customer->fields['customers_firstname'], addslashes($products_name), zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id));
            } else {
              $email_msg = sprintr(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_INVALID_PRODUCT, $customer->fields['customers_firstname']);
            } 
            $html_msg['EMAIL_MESSAGE_HTML'] = nl2br($email_msg);
            // send the reminder email
            zen_mail($customer->fields['customers_firstname'] . ' ' . $customer->fields['customers_lastname'], $customer->fields['customers_email_address'], sprintf(PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_EMAIL_SUBJECT, $subscription->fields['profile_id']), $email_msg, STORE_NAME, EMAIL_FROM, $html_msg, 'default');
            $zco_notifier->notify('NOTIFY_PAYPAL_WPP_RECURRING_EXPIRED');            
          }					
				}  
      }      
      $subscription->MoveNext();
    }
  }
  echo '<p>PayPal Recurring Payment Reminders Cron Executed Successfully.</p>';
  require_once('includes/application_bottom.php');
?>