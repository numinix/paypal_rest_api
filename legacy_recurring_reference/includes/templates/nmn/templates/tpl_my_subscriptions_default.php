<?php

/**
* @package page template
* @copyright Copyright 2003-2006 Zen Cart Development Team
* @copyright Portions Copyright 2003 osCommerce
* @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
* @version $Id: Define Generator v0.1 $
*/

// THIS FILE IS SAFE TO EDIT! This is the template page for your new page 

?>
<!-- bof tpl_my_subscriptions_default.php -->

<div class="centerColumn ac-main" id="my_subscriptions">
  <?php require($template->get_template_dir('tpl_modules_account_menu.php',DIR_WS_TEMPLATE, $current_page_base,'templates'). '/tpl_modules_account_menu.php'); ?>
  <div class="ac-content-wrapper">
    <?php
      switch ($_GET['action']) {
        case 'cancel_confirm':
          echo zen_draw_form('cancel_confirm', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'get');
          echo zen_draw_hidden_field('action', 'cancel');
          echo zen_draw_hidden_field('profileid', $_GET['profileid']);
          echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
          echo zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS);
          echo '<div class="subscription__action"><p>Are you sure you would like to cancel this subscription?</p>';
          echo '' . zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_CONFIRM_ALT) . '';
          echo '<a href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL') . '">' . BUTTON_BACK_ALT . '</a>';
          echo '</div></form>';
          break;
        case 'cancel_confirm_savedcard':
          echo zen_draw_form('cancel_confirm_savedcard', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'get');
          echo zen_draw_hidden_field('action', 'cancel_savedcard');
          echo zen_draw_hidden_field('saved_card_id', $_GET['saved_card_id']);
          echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
          echo zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS);
          echo '<div class="subscription__action"><p>Are you sure you would like to cancel this subscription?</p>';
          echo '' . zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_CONFIRM_ALT) . '';
          echo '<a class="btn" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL') . '">' . BUTTON_BACK_ALT . '</a>';
          echo '</div></form>';
          break;
        case 'suspend_confirm':
          echo zen_draw_form('suspend_confirm', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'get');
          echo zen_draw_hidden_field('action', 'suspend');
          echo zen_draw_hidden_field('profileid', $_GET['profileid']);
          echo zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS);
          echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
          echo '<div class="subscription__action"><p>Are you sure you would like to suspend this subscription?</p>';
          echo '' . zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_SUSPEND_ALT) . '';
          echo '<a href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL') . '">' . BUTTON_BACK_ALT . '</a>';
          echo '</div></form>';
          break;
        case 'suspend_confirm_savedcard':
          echo zen_draw_form('suspend_confirm_savedcard', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'get');
          echo zen_draw_hidden_field('action', 'suspend_savedcard');
          echo zen_draw_hidden_field('saved_card_id', $_GET['saved_card_id']);
          echo zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS);
          echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
          echo '<div class="subscription__action"><p>Are you sure you would like to suspend this subscription?</p>';
          echo '' . zen_image_submit(BUTTON_IMAGE_SUBMIT, BUTTON_SUSPEND_ALT) . '';
          echo '<a class="btn" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL') . '">' . BUTTON_BACK_ALT . '</a>';
          echo '</div></form>';
          break;
        case 'update_savedcard_form':
          $savedCardRecurringId = isset($_GET['saved_card_id']) ? (int) $_GET['saved_card_id'] : 0;
          $currentSavedCardId = 0;
          $savedCardProductName = '';
          if (isset($subscriptions) && is_array($subscriptions)) {
            foreach ($subscriptions as $subscriptionData) {
              if (isset($subscriptionData['type'])
                && $subscriptionData['type'] === 'saved_card_recurring'
                && isset($subscriptionData['saved_credit_card_recurring_id'])
                && (int) $subscriptionData['saved_credit_card_recurring_id'] === $savedCardRecurringId) {
                if (isset($subscriptionData['saved_credit_card_id'])) {
                  $currentSavedCardId = (int) $subscriptionData['saved_credit_card_id'];
                }
                if (isset($subscriptionData['products_id'])) {
                  $savedCardProductName = zen_get_products_name($subscriptionData['products_id']);
                }
                break;
              }
            }
          }

          $hasSavedCards = isset($saved_credit_cards) && is_array($saved_credit_cards) && count($saved_credit_cards) > 0;

          $addCardQueryParameters = array(
            'main_page' => 'account_saved_credit_cards',
            'action' => 'add'
          );
          $subscriptionCardAddToken = '';
          if ($savedCardRecurringId > 0) {
            if (!isset($_SESSION['saved_card_subscription_tokens']) || !is_array($_SESSION['saved_card_subscription_tokens'])) {
              $_SESSION['saved_card_subscription_tokens'] = array();
            }

            $tokenExpiryThreshold = time() - 3600;
            foreach ($_SESSION['saved_card_subscription_tokens'] as $tokenKey => $tokenData) {
              $tokenCustomerId = isset($tokenData['customer_id']) ? (int) $tokenData['customer_id'] : 0;
              $tokenTimestamp = isset($tokenData['created_at']) ? (int) $tokenData['created_at'] : 0;
              if ($tokenCustomerId !== (int) $_SESSION['customer_id'] || ($tokenTimestamp > 0 && $tokenTimestamp < $tokenExpiryThreshold)) {
                unset($_SESSION['saved_card_subscription_tokens'][$tokenKey]);
              }
            }

            if (function_exists('random_bytes')) {
              try {
                $subscriptionCardAddToken = bin2hex(random_bytes(16));
              } catch (Exception $exception) {
                $subscriptionCardAddToken = md5(uniqid('subscription_card', true));
              }
            }
            if ($subscriptionCardAddToken === '') {
              $subscriptionCardAddToken = md5(uniqid('subscription_card', true));
            }

            $_SESSION['saved_card_subscription_tokens'][$subscriptionCardAddToken] = array(
              'saved_card_recurring_id' => $savedCardRecurringId,
              'created_at' => time(),
              'customer_id' => isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0
            );

            $addCardQueryParameters['subscription_card_id'] = $savedCardRecurringId;
            $addCardQueryParameters['subscription_card_token'] = $subscriptionCardAddToken;
          }

          echo zen_draw_form('update_savedcard', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'post');
          echo zen_draw_hidden_field('action', 'update_savedcard');
          echo zen_draw_hidden_field('saved_card_recurring_id', $savedCardRecurringId);
          echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
          echo '<div class="subscription__action">';
          if (strlen($savedCardProductName) > 0) {
            echo '<p><strong>' . htmlspecialchars($savedCardProductName, ENT_QUOTES, CHARSET) . '</strong></p>';
          }
          if ($hasSavedCards) {
            echo '<p>Select a saved card to use for this subscription.</p>';
            echo '<label for="new_saved_card_id">Saved Card</label>';
            echo '<select id="new_saved_card_id" name="new_saved_card_id">';
            foreach ($saved_credit_cards as $card) {
              $cardId = isset($card['saved_credit_card_id']) ? (int) $card['saved_credit_card_id'] : 0;
              $cardType = isset($card['type']) ? $card['type'] : '';
              $cardDigits = isset($card['last_digits']) ? $card['last_digits'] : '';
              $cardExpiry = isset($card['expiry']) ? $card['expiry'] : '';
              $formattedExpiry = '';
              if (strlen($cardExpiry) === 4) {
                $formattedExpiry = substr($cardExpiry, 0, 2) . '/' . substr($cardExpiry, 2);
              } elseif (strlen($cardExpiry) > 0) {
                $formattedExpiry = $cardExpiry;
              }
              $labelParts = array();
              if (strlen($cardType) > 0) {
                $labelParts[] = $cardType;
              }
              if (strlen($cardDigits) > 0) {
                $labelParts[] = 'ending in ' . $cardDigits;
              }
              if (strlen($formattedExpiry) > 0) {
                $labelParts[] = 'expires ' . $formattedExpiry;
              }
              $optionLabel = trim(implode(' ', $labelParts));
              if ($optionLabel === '') {
                $optionLabel = 'Saved Card #' . $cardId;
              }
              $selectedAttribute = $cardId === $currentSavedCardId ? ' selected="selected"' : '';
              echo '<option value="' . (int) $cardId . '"' . $selectedAttribute . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, CHARSET) . '</option>';
            }
            echo '</select>';
            echo '<div class="ac-buttons">';
            echo '<button class="btn btn-outline" type="submit">' . BUTTON_UPDATE_CREDIT_CARD . '</button>';
            echo '<a class="btn" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL') . '">' . BUTTON_CANCEL_ALT . '</a>';
            echo '</div>';
            $addCardUrl = 'https://www.numinix.com/index.php?' . http_build_query($addCardQueryParameters, '', '&', PHP_QUERY_RFC3986);
            echo '<p class="subscription__add-card"><a class="subscription__add-card-link" href="' . htmlspecialchars($addCardUrl, ENT_QUOTES, CHARSET) . '">Add New Card</a></p>';
          } else {
            echo '<p>No saved cards are available. Please add a saved card first.</p>';
            echo '<a class="btn" href="' . zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL') . '">Manage Saved Cards</a>';
          }
          echo '</div>';
          echo '</form>';
          break;
        default:
          if (sizeof($subscriptions) > 0) {
            if (!function_exists('nmx_subscription_profile_find_allow_empty')) {
              function nmx_subscription_profile_find_allow_empty($profile, array $paths)
              {
                if (!is_array($profile)) {
                  return null;
                }

                foreach ($paths as $path) {
                  $value = zen_paypal_subscription_profile_value($profile, $path);
                  if ($value !== null) {
                    return $value;
                  }
                }

                return null;
              }
            }

            if (!function_exists('nmx_subscription_profile_value_string')) {
              function nmx_subscription_profile_value_string($profile, array $paths)
              {
                $raw = nmx_subscription_profile_find_allow_empty($profile, $paths);
                if ($raw === null) {
                  return null;
                }

                if (is_array($raw)) {
                  if (array_key_exists('value', $raw)) {
                    $raw = $raw['value'];
                  } else {
                    return null;
                  }
                } elseif (is_object($raw)) {
                  return null;
                }

                return trim((string) $raw);
              }
            }

            if (!function_exists('nmx_subscription_parse_expiry_parts')) {
              function nmx_subscription_parse_expiry_parts($raw)
              {
                $result = array('month' => '', 'year' => '');

                if (is_array($raw)) {
                  if (isset($raw['month']) || isset($raw['year'])) {
                    $month = isset($raw['month']) ? trim((string) $raw['month']) : '';
                    $year = isset($raw['year']) ? trim((string) $raw['year']) : '';
                    if ($month !== '') {
                      $result['month'] = str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT);
                    }
                    if ($year !== '') {
                      if (strlen($year) === 2) {
                        $year = '20' . str_pad($year, 2, '0', STR_PAD_LEFT);
                      }
                      $result['year'] = substr($year, 0, 4);
                    }

                    return $result;
                  }

                  if (isset($raw[0]) || isset($raw[1])) {
                    $month = isset($raw[0]) ? trim((string) $raw[0]) : '';
                    $year = isset($raw[1]) ? trim((string) $raw[1]) : '';
                    if ($month !== '') {
                      $result['month'] = str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT);
                    }
                    if ($year !== '') {
                      if (strlen($year) === 2) {
                        $year = '20' . str_pad($year, 2, '0', STR_PAD_LEFT);
                      }
                      $result['year'] = substr($year, 0, 4);
                    }

                    return $result;
                  }
                }

                if (is_string($raw)) {
                  $raw = trim($raw);
                  if ($raw === '') {
                    return $result;
                  }

                  if (strpos($raw, '-') !== false) {
                    $parts = explode('-', $raw);
                    if (count($parts) >= 2) {
                      $first = trim($parts[0]);
                      $second = trim($parts[1]);
                      if (strlen($first) === 4) {
                        $result['year'] = substr($first, 0, 4);
                        $result['month'] = str_pad(substr($second, 0, 2), 2, '0', STR_PAD_LEFT);
                        return $result;
                      }

                      if (strlen($first) <= 2) {
                        $result['month'] = str_pad(substr($first, -2), 2, '0', STR_PAD_LEFT);
                        if (strlen($second) === 2) {
                          $result['year'] = '20' . str_pad($second, 2, '0', STR_PAD_LEFT);
                        } else {
                          $result['year'] = substr($second, 0, 4);
                        }
                        return $result;
                      }
                    }
                  }

                  $digits = preg_replace('/[^0-9]/', '', $raw);
                  if (strlen($digits) >= 4) {
                    $month = substr($digits, 0, 2);
                    $year = substr($digits, 2);
                    if (strlen($year) > 4) {
                      $year = substr($year, 0, 4);
                    }
                    if (strlen($year) === 2) {
                      $year = '20' . str_pad($year, 2, '0', STR_PAD_LEFT);
                    }
                    $result['month'] = str_pad($month, 2, '0', STR_PAD_LEFT);
                    $result['year'] = $year;
                  }
                }

                return $result;
              }
            }

            if (!function_exists('nmx_subscription_profile_expiry_parts')) {
              function nmx_subscription_profile_expiry_parts($profile)
              {
                $result = array('month' => '', 'year' => '');
                if (!is_array($profile)) {
                  return $result;
                }

                $month = nmx_subscription_profile_value_string($profile, array(
                  array('payment_source', 'card', 'expiry', 'month'),
                  array('payment_source', 'card', 'expiry', 'month_value'),
                  array('payment_source', 'card', 'expiry_month'),
                  array('credit_card', 'expire_month')
                ));

                $year = nmx_subscription_profile_value_string($profile, array(
                  array('payment_source', 'card', 'expiry', 'year'),
                  array('payment_source', 'card', 'expiry', 'year_value'),
                  array('payment_source', 'card', 'expiry_year'),
                  array('credit_card', 'expire_year')
                ));

                if ($month !== null && $month !== '' && $year !== null && $year !== '') {
                  $result['month'] = str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT);
                  if (strlen($year) === 2) {
                    $year = '20' . str_pad($year, 2, '0', STR_PAD_LEFT);
                  }
                  $result['year'] = substr($year, 0, 4);
                  return $result;
                }

                $raw = nmx_subscription_profile_find_allow_empty($profile, array(
                  array('EXPDATE'),
                  array('expdate'),
                  array('payment_source', 'card', 'expiry')
                ));

                $parsed = nmx_subscription_parse_expiry_parts($raw);
                if ($parsed['month'] !== '' || $parsed['year'] !== '') {
                  return $parsed;
                }

                return $result;
              }
            }

            if (!function_exists('nmx_subscription_lookup_country')) {
              function nmx_subscription_lookup_country($countryCode, array &$cache)
              {
                $result = array('id' => '', 'name' => '');
                $countryCode = strtoupper(trim((string) $countryCode));
                if ($countryCode === '') {
                  return $result;
                }

                if (isset($cache[$countryCode])) {
                  return $cache[$countryCode];
                }

                global $db;
                $sql = "SELECT countries_id, countries_name FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = :iso LIMIT 1";
                $sql = $db->bindVars($sql, ':iso', $countryCode, 'string');
                $query = $db->Execute($sql);
                if (!$query->EOF) {
                  $result['id'] = (int) $query->fields['countries_id'];
                  $result['name'] = $query->fields['countries_name'];
                }

                $cache[$countryCode] = $result;
                return $result;
              }
            }

            if (!function_exists('nmx_subscription_append_data_attribute')) {
              function nmx_subscription_append_data_attribute(array &$attributes, &$hasEditData, $name, $value, $isNumeric = false)
              {
                if ($value === null) {
                  return;
                }

                if ($isNumeric) {
                  $intValue = (int) $value;
                  if ($intValue <= 0) {
                    return;
                  }
                  $attributes[] = $name . '="' . $intValue . '"';
                } else {
                  $attributes[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, CHARSET) . '"';
                }

                $hasEditData = true;
              }
            }

            $subscriptionCountryCache = array();
            $selectedProfileId = '';
            if (isset($_GET['profileid'])) {
              $selectedProfileId = zen_db_prepare_input($_GET['profileid']);
            }
            $subscriptionProfileIds = array();
          ?>
      
          <?php if ($messageStack->size('my_subscriptions') > 0) echo $messageStack->output('my_subscriptions'); ?>
          <p class="ac-list-title">View your active service plan(s) and product subscription(s) below.</p>
          <?php
            $groupNames = array(
              6 => 'Plans and Services',
              7 => 'Plugins'
            );
            $defaultGroupKey = 'other';
            $defaultGroupTitle = 'Other Subscriptions';
            $groupedSubscriptions = array();

            foreach ($subscriptions as $subscription) {
              $type = isset($subscription['products_type']) ? (int) $subscription['products_type'] : null;
              if (isset($groupNames[$type])) {
                $groupKey = $type;
                $groupTitle = $groupNames[$type];
              } else {
                $groupKey = $defaultGroupKey;
                $groupTitle = $defaultGroupTitle;
              }

              if (!isset($groupedSubscriptions[$groupKey])) {
                $groupedSubscriptions[$groupKey] = array(
                  'title' => $groupTitle,
                  'items' => array()
                );
              }

              $groupedSubscriptions[$groupKey]['items'][] = $subscription;
            }

            if (isset($groupedSubscriptions[$defaultGroupKey]) && sizeof($groupedSubscriptions[$defaultGroupKey]['items']) === 0) {
              unset($groupedSubscriptions[$defaultGroupKey]);
            }

            $statusSortOrder = array(
              'active' => 0,
              'suspended' => 1,
              'cancelled' => 2,
              'canceled' => 2
            );

            foreach ($groupedSubscriptions as &$group) {
              usort($group['items'], function ($a, $b) use ($statusSortOrder) {
                $aStatus = strtolower(isset($a['status']) ? $a['status'] : '');
                $bStatus = strtolower(isset($b['status']) ? $b['status'] : '');

                $aOrder = isset($statusSortOrder[$aStatus]) ? $statusSortOrder[$aStatus] : PHP_INT_MAX;
                $bOrder = isset($statusSortOrder[$bStatus]) ? $statusSortOrder[$bStatus] : PHP_INT_MAX;

                if ($aOrder === $bOrder) {
                  $aName = strtolower(isset($a['products_id']) ? zen_get_products_name($a['products_id']) : '');
                  $bName = strtolower(isset($b['products_id']) ? zen_get_products_name($b['products_id']) : '');

                  $nameComparison = strcasecmp($bName, $aName);
                  if ($nameComparison === 0) {
                    return 0;
                  }

                  return $nameComparison;
                }

                return ($aOrder < $bOrder) ? -1 : 1;
              });
            }
            unset($group);

            $groupOrder = array_keys($groupNames);
            $groupOrder[] = $defaultGroupKey;
            $row_counter = 0;

            foreach ($groupOrder as $groupKey) {
              if (!isset($groupedSubscriptions[$groupKey])) {
                continue;
              }

              $group = $groupedSubscriptions[$groupKey];
          ?>
            <h3 class="ac-list-group-title"><?php echo $group['title']; ?></h3>
            <ul class="ac-list">
              <?php foreach ($group['items'] as $subscription) {
                $billingFrequency = (string) ($subscription['billingfrequency'] ?? '');
                $billingPeriod = strtolower((string) ($subscription['billingperiod'] ?? ''));
                $profileId = '';
                if (isset($subscription['profile']['PROFILEID'])) {
                  $profileId = $subscription['profile']['PROFILEID'];
                } elseif (isset($subscription['profile']['id'])) {
                  $profileId = $subscription['profile']['id'];
                } elseif (isset($subscription['profile']['profile_id'])) {
                  $profileId = $subscription['profile']['profile_id'];
                } elseif (isset($subscription['profile_id'])) {
                  $profileId = $subscription['profile_id'];
                }
                if (is_array($profileId)) {
                  $profileId = '';
                }
                $profileId = trim((string) $profileId);
                if ($profileId !== '') {
                  $subscriptionProfileIds[] = $profileId;
                  if ($selectedProfileId === '') {
                    $selectedProfileId = $profileId;
                  }
                }

                $statusRaw = trim((string) ($subscription['status'] ?? ''));
                $planStatusClass = '';
                if (isset($subscription['plan_status_class']) && $subscription['plan_status_class'] !== '') {
                  $planStatusClass = ' ' . $subscription['plan_status_class'];
                } elseif (isset($subscription['profile']['STATUS']) && strcasecmp($subscription['profile']['STATUS'], 'Cancelled') === 0) {
                  $planStatusClass = ' cancelled_plan';
                } elseif (isset($subscription['profile']['status']) && strcasecmp($subscription['profile']['status'], 'Cancelled') === 0) {
                  $planStatusClass = ' cancelled_plan';
                }

                $dataAttributes = array();
                if ($profileId !== '') {
                  $dataAttributes[] = 'data-subscription-profile-id="' . htmlspecialchars($profileId, ENT_QUOTES, CHARSET) . '"';
                }
                if ($statusRaw !== '') {
                  $dataAttributes[] = 'data-subscription-status="' . htmlspecialchars(strtolower($statusRaw), ENT_QUOTES, CHARSET) . '"';
                }

                $profileSource = '';
                if (isset($subscription['profile_source']) && $subscription['profile_source'] !== '') {
                  $profileSource = strtolower((string) $subscription['profile_source']);
                } elseif (isset($subscription['profile']['profile_source']) && $subscription['profile']['profile_source'] !== '') {
                  $profileSource = strtolower((string) $subscription['profile']['profile_source']);
                }
                $isRestProfile = !empty($subscription['is_rest_profile']);
                if ($profileSource === 'rest') {
                  $isRestProfile = true;
                } elseif ($isRestProfile && $profileSource === '') {
                  $profileSource = 'rest';
                }
                if ($profileSource !== '') {
                  $dataAttributes[] = 'data-subscription-profile-source="' . htmlspecialchars($profileSource, ENT_QUOTES, CHARSET) . '"';
                }
                if ($isRestProfile) {
                  $dataAttributes[] = 'data-subscription-rest-profile="1"';
                }
                $refreshedAtValue = '';
                if (!empty($subscription['refreshed_at'])) {
                  $refreshedAtValue = $subscription['refreshed_at'];
                } elseif (!empty($subscription['classification_refreshed_at'])) {
                  $refreshedAtValue = $subscription['classification_refreshed_at'];
                }
                if ($refreshedAtValue !== '') {
                  $dataAttributes[] = 'data-cache-refreshed-at="' . htmlspecialchars($refreshedAtValue, ENT_QUOTES, CHARSET) . '"';
                }

                $profileData = (isset($subscription['profile']) && is_array($subscription['profile'])) ? $subscription['profile'] : array();
                $hasEditAttributes = false;

                if (!empty($profileData)) {
                  $cardName = nmx_subscription_profile_value_string($profileData, array(
                    array('SUBSCRIBERNAME'),
                    array('subscriber', 'name'),
                    array('subscriber', 'full_name'),
                    array('subscriber_name'),
                    array('payment_source', 'card', 'name')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-name', $cardName);

                  $cardType = nmx_subscription_profile_value_string($profileData, array(
                    array('CREDITCARDTYPE'),
                    array('creditcardtype'),
                    array('payment_source', 'card', 'brand'),
                    array('payment_source', 'card', 'type')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-type', $cardType);

                  $cardNumberRaw = nmx_subscription_profile_value_string($profileData, array(
                    array('ACCT'),
                    array('acct'),
                    array('payment_source', 'card', 'number'),
                    array('payment_source', 'card', 'card_number'),
                    array('payment_source', 'card', 'masked_number'),
                    array('payment_source', 'card', 'last_digits')
                  ));

                  if ($cardNumberRaw !== null) {
                    $cardNumberMask = trim($cardNumberRaw);
                    $cardLastDigits = '';
                    if ($cardNumberMask !== '') {
                      $digitsOnly = preg_replace('/[^0-9]/', '', $cardNumberMask);
                      if ($digitsOnly !== '') {
                        $cardLastDigits = substr($digitsOnly, -4);
                      }

                      if (strlen($cardNumberMask) <= 4 && $cardLastDigits !== '') {
                        $cardNumberMask = '**** ' . $cardLastDigits;
                      }
                    }

                    nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-number-mask', $cardNumberMask);
                    if ($cardLastDigits !== '') {
                      nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-last-digits', $cardLastDigits);
                    }
                  }

                  $expiryParts = nmx_subscription_profile_expiry_parts($profileData);
                  if (!empty($expiryParts['month'])) {
                    nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-exp-month', $expiryParts['month']);
                  }
                  if (!empty($expiryParts['year'])) {
                    nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-card-exp-year', $expiryParts['year']);
                  }

                  $street = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'street'),
                    array('BILLINGADDRESS', 'line1'),
                    array('BILLINGADDRESS', 'street1'),
                    array('BILLTOSTREET'),
                    array('payment_source', 'card', 'billing_address', 'line1'),
                    array('payment_source', 'card', 'billing_address', 'address_line_1'),
                    array('billing_info', 'billing_address', 'address_line_1'),
                    array('subscriber', 'shipping_address', 'address_line_1')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-street', $street);

                  $streetTwo = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'street2'),
                    array('BILLINGADDRESS', 'line2'),
                    array('BILLTOSTREET2'),
                    array('payment_source', 'card', 'billing_address', 'line2'),
                    array('payment_source', 'card', 'billing_address', 'address_line_2'),
                    array('billing_info', 'billing_address', 'address_line_2'),
                    array('subscriber', 'shipping_address', 'address_line_2')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-street2', $streetTwo);

                  $cityValue = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'city'),
                    array('BILLTOCITY'),
                    array('payment_source', 'card', 'billing_address', 'city'),
                    array('payment_source', 'card', 'billing_address', 'admin_area_2'),
                    array('billing_info', 'billing_address', 'city'),
                    array('billing_info', 'billing_address', 'admin_area_2'),
                    array('subscriber', 'shipping_address', 'city'),
                    array('subscriber', 'shipping_address', 'admin_area_2')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-city', $cityValue);

                  $stateValue = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'state'),
                    array('BILLTOSTATE'),
                    array('payment_source', 'card', 'billing_address', 'state'),
                    array('payment_source', 'card', 'billing_address', 'admin_area_1'),
                    array('billing_info', 'billing_address', 'state'),
                    array('billing_info', 'billing_address', 'admin_area_1'),
                    array('subscriber', 'shipping_address', 'state'),
                    array('subscriber', 'shipping_address', 'admin_area_1')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-state', $stateValue);

                  $postcodeValue = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'zip'),
                    array('BILLINGADDRESS', 'postal_code'),
                    array('BILLTOZIP'),
                    array('payment_source', 'card', 'billing_address', 'postal_code'),
                    array('payment_source', 'card', 'billing_address', 'zip'),
                    array('billing_info', 'billing_address', 'postal_code'),
                    array('subscriber', 'shipping_address', 'postal_code')
                  ));
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-postcode', $postcodeValue);

                  $countryCodeValue = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'countrycode'),
                    array('BILLTOCOUNTRY'),
                    array('payment_source', 'card', 'billing_address', 'country_code'),
                    array('billing_info', 'billing_address', 'country_code'),
                    array('subscriber', 'shipping_address', 'country_code')
                  ));

                  $countryNameValue = nmx_subscription_profile_value_string($profileData, array(
                    array('BILLINGADDRESS', 'country'),
                    array('payment_source', 'card', 'billing_address', 'country_name'),
                    array('billing_info', 'billing_address', 'country'),
                    array('billing_info', 'billing_address', 'country_name'),
                    array('subscriber', 'shipping_address', 'country')
                  ));

                  if ($countryCodeValue !== null && $countryCodeValue !== '') {
                    $countryCodeValue = strtoupper($countryCodeValue);
                    nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-country-code', $countryCodeValue);
                    $countryDetails = nmx_subscription_lookup_country($countryCodeValue, $subscriptionCountryCache);
                    if (!empty($countryDetails['id'])) {
                      nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-country-id', $countryDetails['id'], true);
                    }
                    if ($countryNameValue === null || $countryNameValue === '') {
                      if (!empty($countryDetails['name'])) {
                        $countryNameValue = $countryDetails['name'];
                      }
                    }
                  }

                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-address-country-name', $countryNameValue);
                }

                $ordersId = isset($subscription['orders_id']) ? (int) $subscription['orders_id'] : 0;
                if ($ordersId > 0) {
                  nmx_subscription_append_data_attribute($dataAttributes, $hasEditAttributes, 'data-subscription-orders-id', $ordersId, true);
                }

                if ($hasEditAttributes) {
                  $dataAttributes[] = 'data-subscription-has-edit-data="1"';
                }
                $dataAttributesString = empty($dataAttributes) ? '' : ' ' . implode(' ', $dataAttributes);

                $startDateRaw = trim((string) ($subscription['start_date'] ?? ''));
                $nextDateRaw = trim((string) ($subscription['next_date'] ?? ''));
                $paymentMethodRaw = trim((string) ($subscription['payment_method'] ?? ''));
                if ($paymentMethodRaw === '') {
                  $paymentMethodRaw = 'PayPal';
                }
                $statusDisplay = $statusRaw !== '' ? htmlspecialchars($statusRaw, ENT_QUOTES, CHARSET) : 'Unknown';

                $subscriptionCurrencyCode = '';
                if (isset($subscription['currencycode']) && $subscription['currencycode'] !== '') {
                  $subscriptionCurrencyCode = $subscription['currencycode'];
                } elseif (isset($currency)) {
                  $subscriptionCurrencyCode = $currency;
                }
                $subscriptionPrice = isset($subscription['price']) ? $subscription['price'] : '';
                $formattedPrice = $subscriptionPrice !== '' ? $currencies->format($subscriptionPrice, true, $subscriptionCurrencyCode) : $currencies->format(0, true, $subscriptionCurrencyCode);

                $billingCycleDisplay = trim($billingFrequency . ' ' . $billingPeriod);
              ?>
                <li class="ac-item"<?php echo $dataAttributesString; ?>>
                  <ul class="ac-item-info ac-item-details plan-data<?php echo $planStatusClass; ?>">
                      <li class="sb-name"><a href="#"><?php echo zen_get_products_name($subscription['products_id']); ?></a></li>
                      <?php if($profileId !== '') { ?><li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PROFILE_ID; ?>:</strong> <?php echo htmlspecialchars($profileId, ENT_QUOTES, CHARSET); ?></li><?php } ?>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PRICE; ?>:</strong> <span class="js-subscription-price" data-currency-code="<?php echo htmlspecialchars($subscriptionCurrencyCode, ENT_QUOTES, CHARSET); ?>"><?php echo $formattedPrice; ?></span></li>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_CYCLE; ?>:</strong> <span class="js-subscription-cycle"><?php echo $billingCycleDisplay !== '' ? htmlspecialchars($billingCycleDisplay, ENT_QUOTES, CHARSET) : '&mdash;'; ?></span><?php echo $billingCycleDisplay !== '' ? 's' : ''; ?></li>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_START_DATE; ?>:</strong> <span class="js-subscription-start-date"><?php echo $startDateRaw !== '' ? htmlspecialchars($startDateRaw, ENT_QUOTES, CHARSET) : '&mdash;'; ?></span></li>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_NEXT_BILLING_DATE; ?>:</strong> <span class="js-subscription-next-date"><?php echo $nextDateRaw !== '' ? htmlspecialchars($nextDateRaw, ENT_QUOTES, CHARSET) : '&mdash;'; ?></span></li>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_PAYMENT_METHOD; ?>:</strong> <span class="js-subscription-payment-method"><?php echo htmlspecialchars($paymentMethodRaw, ENT_QUOTES, CHARSET); ?></span></li>
                      <li><strong><?php echo TABLE_HEADING_PAYPAL_SUBSCRIPTION_STATUS; ?>:</strong> <span class="js-subscription-status"><?php echo $statusDisplay; ?></span></li>
                  </ul>
                  <?php
                    if ($subscription['type'] == 'paypal_recurring') {
                      $subscriptionStatus = strtolower($statusRaw);
                    $paypalActions = array();

                    switch ($subscriptionStatus) {
                      case 'active':
                        if ($profileId !== '') {
                          $paypalActions[] = '                          <a class="btn btn-outline btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=upgrade&profileid=' . $profileId, 'SSL') . '">' . BUTTON_CHANGE_SUBSCRIPTION . '</a>';
                          $paypalActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=suspend_confirm&profileid=' . $profileId, 'SSL') . '">' . BUTTON_SUSPEND_SUBSCRIPTION . '</a>';
                          $paypalActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId, 'SSL') . '">' . BUTTON_CANCEL_SUBSCRIPTION . '</a>';
                        }
                        break;
                      case 'suspended':
                        if ($profileId !== '') {
                          $paypalActions[] = '                          <a class="btn btn-outline btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=reactivate&profileid=' . $profileId, 'SSL') . '">' . BUTTON_REACTIVATE_SUBSCRIPTION . '</a>';
                          $paypalActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId, 'SSL') . '">' . BUTTON_CANCEL_SUBSCRIPTION . '</a>';
                        }
                        break;
                      case 'cancelled':
                      case 'canceled':
                        if (!empty($subscription['subscription_id'])) {
                          $archiveFormName = 'archive_paypal_' . (int) $subscription['subscription_id'];
                          $archiveFormAttributes = 'class="ac-item-action-form js-archive-form"';
                          ob_start();
                          echo '                          ' . zen_draw_form($archiveFormName, zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'post', $archiveFormAttributes) . "\n";
                          echo '                            ' . zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS) . "\n";
                          echo '                            ' . zen_draw_hidden_field('action', 'archive') . "\n";
                          echo '                            ' . zen_draw_hidden_field('subscription_id', (int) $subscription['subscription_id']) . "\n";
                          echo '                            <button type="submit" class="btn btn-outline btn-sm btn-archive">Archive</button>' . "\n";
                          echo '                          </form>';
                          $paypalActions[] = ob_get_clean();
                        }
                        break;
                      default:
                        if ($profileId !== '') {
                          $paypalActions[] = '                          <a class="btn btn-outline btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=upgrade&profileid=' . $profileId, 'SSL') . '">' . BUTTON_CHANGE_SUBSCRIPTION . '</a>';
                          $paypalActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=suspend_confirm&profileid=' . $profileId, 'SSL') . '">' . BUTTON_SUSPEND_SUBSCRIPTION . '</a>';
                          $paypalActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm&profileid=' . $profileId, 'SSL') . '">' . BUTTON_CANCEL_ALT . '</a>';
                        }
                        break;
                    }

                    if (!empty($paypalActions)) {
                      echo '
                        <div class="ac-item-actions">
' . implode("\n", $paypalActions) . '
                        </div>';
                    }
                    } else { //saved card recurring payment
                      $savedCardStatus = strtolower($statusRaw);
                    $savedCardRecurringId = isset($subscription['saved_credit_card_recurring_id']) ? (int) $subscription['saved_credit_card_recurring_id'] : 0;
                    $hasSavedCards = isset($saved_credit_cards) && is_array($saved_credit_cards) && count($saved_credit_cards) > 0;
                    $savedCardActions = array();
                    $shouldShowUpdate = $hasSavedCards && !in_array($savedCardStatus, array('cancelled', 'canceled'), true);

                    if ($shouldShowUpdate) {
                      $savedCardActions[] = '                          <a class="btn btn-outline btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=update_savedcard_form&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_UPDATE_CREDIT_CARD . '</a>';
                    }

                    switch ($savedCardStatus) {
                      case 'active':
                        $supportsSuspend = true;
                        if (isset($subscription['supports_suspend'])) {
                          $supportsSuspend = (bool) $subscription['supports_suspend'];
                        }

                        if ($supportsSuspend) {
                          $savedCardActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=suspend_confirm_savedcard&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_SUSPEND_SUBSCRIPTION . '</a>';
                        }
                        $savedCardActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm_savedcard&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_CANCEL_SUBSCRIPTION . '</a>';
                        break;
                      case 'suspended':
                      case 'suspend':
                        $savedCardActions[] = '                          <a class="btn btn-outline btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=reactivate_savedcard&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_REACTIVATE_SUBSCRIPTION . '</a>';
                        $savedCardActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm_savedcard&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_CANCEL_SUBSCRIPTION . '</a>';
                        break;
                      case 'cancelled':
                      case 'canceled':
                        if ($savedCardRecurringId > 0) {
                          $archiveFormName = 'archive_savedcard_' . $savedCardRecurringId;
                          $archiveFormAttributes = 'class="ac-item-action-form js-archive-form"';
                          ob_start();
                          echo '                          ' . zen_draw_form($archiveFormName, zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'), 'post', $archiveFormAttributes) . "\n";
                          echo '                            ' . zen_draw_hidden_field('main_page', FILENAME_MY_SUBSCRIPTIONS) . "\n";
                          echo '                            ' . zen_draw_hidden_field('action', 'archive_savedcard') . "\n";
                          echo '                            ' . zen_draw_hidden_field('saved_card_id', $savedCardRecurringId) . "\n";
                          echo '                            <button type="submit" class="btn btn-outline btn-sm btn-archive">Archive</button>' . "\n";
                          echo '                          </form>';
                          $savedCardActions[] = ob_get_clean();
                        }
                        break;
                      default:
                        $savedCardActions[] = '                          <a class="btn btn-outline btn-danger btn-sm" href="' . zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=cancel_confirm_savedcard&saved_card_id=' . $savedCardRecurringId, 'SSL') . '">' . BUTTON_CANCEL_SUBSCRIPTION . '</a>';
                        break;
                    }

                    if (!empty($savedCardActions)) {
                      echo '
                        <div class="ac-item-actions">
' . implode("\n", $savedCardActions) . '
                        </div>';
                    }
                 }
                ?>
                
                <?php if($row_counter == 1) { ?>

                  <!-- payment container -->
                  <div class="js-fancybox-hidden">
                    <div id="js-editpopup" class="nmx-fancybox">
                      <?php echo zen_draw_form('edit_credit_card', zen_href_link(FILENAME_MY_SUBSCRIPTIONS, 'action=update_credit_card', 'SSL'), 'post', 'id="use_saved_card"'); ?>
                        <?php echo zen_draw_hidden_field('action', 'update_credit_card'); ?>
                        <?php
                          $formProfileId = ($selectedProfileId !== '' ? $selectedProfileId : (isset($subscription['profile']['PROFILEID']) ? $subscription['profile']['PROFILEID'] : ''));
                          echo zen_draw_hidden_field('profileid', $formProfileId, 'id="use_saved_card_profile_id"');
                          $formSecurityToken = isset($_SESSION['securityToken']) ? $_SESSION['securityToken'] : '';
                          echo zen_draw_hidden_field('securityToken', $formSecurityToken);
                        ?>
                        <?php echo zen_draw_hidden_field('address_book_id', $entry->fields['address_book_id']); ?>
                          
                          <!-- credit card -->
                          <?php
                            $hasLegacyCardDetails = (isset($subscription['profile']['CREDITCARDTYPE']) && isset($subscription['profile']['ACCT']));
                            $showLegacyCardForm = (!$isRestProfile && $hasLegacyCardDetails);
                          ?>
                          <?php if ($showLegacyCardForm) { ?>
                            <h3><?php echo FORM_HEADING_PAYMENT_INFO ?></h3>
                            <!-- add cart form -->
                            <div class="nmx-fancybox-content">
                              <?php
                                // map between paypals response and our forms vars to pre-populate form
                                $edit_card['name_on_card'] = $subscription['profile']['SUBSCRIBERNAME'];
                                $edit_card['type'] = $subscription['profile']['CREDITCARDTYPE'];
                              ?>
                              <?php require($template->get_template_dir('tpl_modules_account_credit_card_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_account_credit_card_details.php'); ?>
                              <?php require($template->get_template_dir('tpl_modules_address_book_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_address_book_details.php'); ?>
                            </div>
                          <!-- paypal subscription -->
                          <?php } elseif ($isRestProfile) { ?>
                            <h3>Update Payment Source</h3>
                            <div class="nmx-fancybox-content">
                              <p>Provide one of the fields below to replace the payment method on file for this subscription.</p>
                              <label for="rest_payment_source_token_id">Payment Token ID</label>
                              <?php echo zen_draw_input_field('rest_payment_source_token_id', '', 'id="rest_payment_source_token_id"'); ?>
                              <label for="rest_payment_source_vault_id">Vaulted Card ID</label>
                              <?php echo zen_draw_input_field('rest_payment_source_vault_id', '', 'id="rest_payment_source_vault_id"'); ?>
                              <label for="rest_payment_source_paypal_email">PayPal Email</label>
                              <?php echo zen_draw_input_field('rest_payment_source_paypal_email', '', 'id="rest_payment_source_paypal_email"'); ?>
                              <p class="form-hint">Submit only one field at a time. Leave all fields blank to keep your current payment source.</p>
                            </div>
                            <?php require($template->get_template_dir('tpl_modules_address_book_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_address_book_details.php'); ?>
                          <?php } else { ?>
                            <h3><?php echo INTRO_EDIT_PAYPAL_PAYMENT; ?></h3>
                            <?php require($template->get_template_dir('tpl_modules_address_book_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_address_book_details.php'); ?>
                          <?php } ?>

                          <div class="ac-buttons">
                            <button class="btn" type="submit" id="use_saved_card_submit"><?php echo BUTTON_SAVE_CARD_ALT; ?></button>
                            <a class="btn btn-outline" href="<?php echo zen_href_link(FILENAME_MY_SUBSCRIPTIONS, '', 'SSL'); ?>"><?php echo BUTTON_CANCEL_ALT; ?></a>
                          </div>
                      </form>
                    </div>
                  </div>
                  <!-- end/payment container -->
                <?php } //end if first row ?>
              </li>
            <?php
              $row_counter++;
              }
            ?>
            </ul>            <?php
              if (!empty($subscriptionProfileIds)) {
              $fallbackProfileId = $selectedProfileId !== '' ? $selectedProfileId : $subscriptionProfileIds[0];
          ?>
          <script>
            (function() {
              var form = document.getElementById('use_saved_card');
              if (!form) {
                return;
              }

              var profileInput = document.getElementById('use_saved_card_profile_id');
              var fallbackProfileId = <?php echo json_encode($fallbackProfileId); ?>;
              if (profileInput && !profileInput.value && fallbackProfileId) {
                profileInput.value = fallbackProfileId;
              }

              var container = document.getElementById('my_subscriptions');
              var submitButton = document.getElementById('use_saved_card_submit');
              var cardNameInput = form.querySelector('input[name="fullname"]');
              var altCardNameInput = form.querySelector('input[name="name_on_card"]');
              if (!cardNameInput && altCardNameInput) {
                cardNameInput = altCardNameInput;
              }

              var selectors = {
                orderId: form.querySelector('input[name="orders_id"]'),
                cardName: cardNameInput,
                cardNameAlt: (altCardNameInput && altCardNameInput !== cardNameInput) ? altCardNameInput : null,
                cardTypeSelect: form.querySelector('select[name="paymenttype"]'),
                cardTypeInputs: form.querySelectorAll('input[name="paymenttype"]'),
                cardNumber: form.querySelector('input[name="cardnumber"]'),
                cardNumberDisplay: form.querySelector('[data-subscription-card-number]'),
                cvv: form.querySelector('input[name="cvv"]'),
                expiryMonth: form.querySelector('[name="monthexpiry"]'),
                expiryYear: form.querySelector('[name="yearexpiry"]'),
                street: form.querySelector('input[name="street_address"]'),
                street2: form.querySelector('input[name="suburb"]'),
                city: form.querySelector('input[name="city"]'),
                state: form.querySelector('input[name="state"]'),
                postcode: form.querySelector('input[name="postcode"]'),
                countrySelect: form.querySelector('[name="zone_country_id"]'),
                countryCodeInput: form.querySelector('input[name="countrycode"]'),
                countryNameDisplay: form.querySelector('[data-subscription-country-name]')
              };

              function setFieldValue(field, value) {
                if (!field) {
                  return;
                }
                field.value = value || '';
              }

              function setSelectValue(select, value) {
                if (!select) {
                  return;
                }
                var stringValue = (value === undefined || value === null) ? '' : String(value);
                if (select.tagName && select.tagName.toLowerCase() === 'select') {
                  var options = select.options;
                  var matched = false;
                  for (var i = 0; i < options.length; i++) {
                    var optionValue = options[i].value;
                    if (optionValue === stringValue || optionValue.toLowerCase() === stringValue.toLowerCase()) {
                      select.selectedIndex = i;
                      matched = true;
                      break;
                    }
                  }
                  if (!matched) {
                    select.value = stringValue;
                  }
                } else {
                  select.value = stringValue;
                }
              }

              function setPaymentType(typeValue) {
                if (typeValue === undefined || typeValue === null) {
                  return;
                }
                var normalized = String(typeValue);
                if (selectors.cardTypeSelect) {
                  setSelectValue(selectors.cardTypeSelect, normalized);
                }
                if (selectors.cardTypeInputs && selectors.cardTypeInputs.length) {
                  var normalizedLower = normalized.toLowerCase();
                  var hasMatch = false;
                  for (var i = 0; i < selectors.cardTypeInputs.length; i++) {
                    var input = selectors.cardTypeInputs[i];
                    var candidate = String(input.value || '').toLowerCase();
                    if (!candidate && input.hasAttribute('data-card-type')) {
                      candidate = String(input.getAttribute('data-card-type') || '').toLowerCase();
                    }
                    if (candidate === normalizedLower) {
                      input.checked = true;
                      hasMatch = true;
                    } else if (hasMatch) {
                      input.checked = false;
                    } else if (input.checked && candidate !== normalizedLower) {
                      input.checked = false;
                    }
                  }
                }
                var display = form.querySelector('[data-subscription-card-type]');
                if (display) {
                  display.textContent = normalized;
                }
              }

              function updateCardNumberDisplay(dataset) {
                if (!dataset) {
                  return;
                }
                var mask = dataset.subscriptionCardNumberMask || '';
                var lastDigits = dataset.subscriptionCardLastDigits || '';
                var placeholder = mask;
                if (!placeholder && lastDigits) {
                  placeholder = '•••• ' + lastDigits;
                }
                if (selectors.cardNumber) {
                  selectors.cardNumber.value = '';
                  if (placeholder) {
                    selectors.cardNumber.placeholder = placeholder;
                  } else {
                    selectors.cardNumber.removeAttribute('placeholder');
                  }
                  selectors.cardNumber.setAttribute('data-current-card-mask', placeholder);
                }
                if (selectors.cvv) {
                  selectors.cvv.value = '';
                }
                if (selectors.cardNumberDisplay) {
                  selectors.cardNumberDisplay.textContent = placeholder;
                }
              }

              function updateCountryFields(dataset) {
                if (!dataset) {
                  return;
                }
                if (dataset.subscriptionAddressCountryId !== undefined && selectors.countrySelect) {
                  setSelectValue(selectors.countrySelect, dataset.subscriptionAddressCountryId);
                }
                if (dataset.subscriptionAddressCountryCode !== undefined && selectors.countryCodeInput) {
                  selectors.countryCodeInput.value = dataset.subscriptionAddressCountryCode || '';
                }
                if (dataset.subscriptionAddressCountryName !== undefined && selectors.countryNameDisplay) {
                  selectors.countryNameDisplay.textContent = dataset.subscriptionAddressCountryName || '';
                }
              }

              function applySubscriptionData(item) {
                if (!item || !item.dataset) {
                  return;
                }
                var dataset = item.dataset;
                if (dataset.subscriptionProfileId && profileInput) {
                  profileInput.value = dataset.subscriptionProfileId;
                }
                if (dataset.subscriptionHasEditData !== '1') {
                  return;
                }
                if (dataset.subscriptionOrdersId !== undefined && selectors.orderId) {
                  selectors.orderId.value = dataset.subscriptionOrdersId || '';
                }
                if (dataset.subscriptionCardName !== undefined) {
                  setFieldValue(selectors.cardName, dataset.subscriptionCardName);
                  if (selectors.cardNameAlt && selectors.cardNameAlt !== selectors.cardName) {
                    setFieldValue(selectors.cardNameAlt, dataset.subscriptionCardName);
                  }
                }
                if (dataset.subscriptionCardType !== undefined) {
                  setPaymentType(dataset.subscriptionCardType);
                }
                if (dataset.subscriptionCardExpMonth !== undefined && selectors.expiryMonth) {
                  setSelectValue(selectors.expiryMonth, dataset.subscriptionCardExpMonth);
                }
                if (dataset.subscriptionCardExpYear !== undefined && selectors.expiryYear) {
                  setSelectValue(selectors.expiryYear, dataset.subscriptionCardExpYear);
                }
                if (dataset.subscriptionCardNumberMask !== undefined || dataset.subscriptionCardLastDigits !== undefined) {
                  updateCardNumberDisplay(dataset);
                }
                if (dataset.subscriptionAddressStreet !== undefined) {
                  setFieldValue(selectors.street, dataset.subscriptionAddressStreet);
                }
                if (dataset.subscriptionAddressStreet2 !== undefined) {
                  setFieldValue(selectors.street2, dataset.subscriptionAddressStreet2);
                }
                if (dataset.subscriptionAddressCity !== undefined) {
                  setFieldValue(selectors.city, dataset.subscriptionAddressCity);
                }
                if (dataset.subscriptionAddressState !== undefined) {
                  setFieldValue(selectors.state, dataset.subscriptionAddressState);
                }
                if (dataset.subscriptionAddressPostcode !== undefined) {
                  setFieldValue(selectors.postcode, dataset.subscriptionAddressPostcode);
                }
                updateCountryFields(dataset);
              }

              function findSubscriptionItem(profileId) {
                if (!container || !profileId) {
                  return null;
                }
                var nodes = container.querySelectorAll('.ac-item[data-subscription-profile-id]');
                for (var i = 0; i < nodes.length; i++) {
                  if (nodes[i].getAttribute('data-subscription-profile-id') === profileId) {
                    return nodes[i];
                  }
                }
                return null;
              }

              if (container) {
                container.addEventListener('click', function(event) {
                  var item = event.target.closest('.ac-item[data-subscription-profile-id]');
                  if (!item) {
                    return;
                  }
                  var itemProfileId = item.getAttribute('data-subscription-profile-id');
                  if (itemProfileId && profileInput) {
                    profileInput.value = itemProfileId;
                  }
                  if (item.dataset && item.dataset.subscriptionHasEditData === '1') {
                    applySubscriptionData(item);
                  }
                });
              }

              if (submitButton) {
                submitButton.addEventListener('click', function(event) {
                  event.preventDefault();
                  if (profileInput && (!profileInput.value || profileInput.value === '') && fallbackProfileId) {
                    profileInput.value = fallbackProfileId;
                  }
                  form.submit();
                });
              }

              var initialItem = null;
              if (profileInput && profileInput.value) {
                initialItem = findSubscriptionItem(profileInput.value);
              }
              if (!initialItem && fallbackProfileId) {
                initialItem = findSubscriptionItem(fallbackProfileId);
              }
              if (!initialItem && container) {
                initialItem = container.querySelector('.ac-item[data-subscription-has-edit-data="1"]');
              }
              if (initialItem) {
                applySubscriptionData(initialItem);
              }
            })();
          </script>
          <?php
            }
          ?>
          <script>
            (function() {
              if (typeof window.fetch !== 'function' || typeof window.FormData === 'undefined') {
                return;
              }

              var container = document.getElementById('my_subscriptions');
              if (!container) {
                return;
              }

              var messageWrapper = container.querySelector('.ac-content-wrapper');

              container.addEventListener('submit', function(event) {
                if (!event || typeof event.target === 'undefined' || typeof event.target.closest !== 'function') {
                  return;
                }

                var archiveForm = event.target.closest('form.js-archive-form');
                if (!archiveForm || !container.contains(archiveForm)) {
                  return;
                }

                event.preventDefault();

                var requestUrl = archiveForm.getAttribute('action');
                if (!requestUrl) {
                  return;
                }

                var submitButton = archiveForm.querySelector('.btn-archive');
                if (!submitButton) {
                  return;
                }

                if (submitButton.getAttribute('data-archiving') === '1') {
                  return;
                }

                var listItem = archiveForm.closest('.ac-item');
                var originalHtml = submitButton.innerHTML;
                submitButton.setAttribute('data-archiving', '1');
                submitButton.setAttribute('aria-disabled', 'true');
                submitButton.setAttribute('disabled', 'disabled');
                submitButton.classList.add('is-processing');
                submitButton.innerHTML = 'Archiving…';

                var formData = new FormData(archiveForm);
                if (!formData.has('ajax')) {
                  formData.append('ajax', '1');
                }

                fetch(requestUrl, {
                  method: 'POST',
                  headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                  },
                  credentials: 'same-origin',
                  body: formData
                }).then(function(response) {
                  var contentType = response && response.headers ? response.headers.get('content-type') : '';
                  var isJson = contentType && contentType.indexOf('application/json') !== -1;
                  if (!isJson) {
                    throw new Error('Unexpected response');
                  }
                  return response.json().then(function(payload) {
                    return payload || {};
                  });
                }).then(function(payload) {
                  payload = payload || {};
                  if (payload.success) {
                    if (listItem && listItem.parentNode) {
                      var listElement = listItem.parentNode;
                      listItem.parentNode.removeChild(listItem);
                      updateGroupVisibility(listElement);
                    } else {
                      restoreButton();
                    }
                    var successMessage = payload.message || 'Your subscription has been archived.';
                    appendMessage('success', successMessage);
                  } else {
                    var errorMessage = payload.message || payload.error || 'Your subscription could not be archived, please try again.';
                    appendMessage('error', errorMessage);
                    restoreButton();
                  }
                }).catch(function(error) {
                  var message = 'We were unable to archive your subscription.';
                  if (error && error.message) {
                    message += ' ' + error.message;
                  }
                  appendMessage('error', message);
                  restoreButton();
                });

                function restoreButton() {
                  submitButton.removeAttribute('data-archiving');
                  submitButton.removeAttribute('aria-disabled');
                  submitButton.removeAttribute('disabled');
                  submitButton.classList.remove('is-processing');
                  submitButton.innerHTML = originalHtml;
                }
              });

              function updateGroupVisibility(listElement) {
                if (!listElement || typeof listElement.querySelectorAll !== 'function') {
                  return;
                }

                var remainingItems = listElement.querySelectorAll('.ac-item');
                if (remainingItems.length > 0) {
                  return;
                }

                var heading = listElement.previousElementSibling;
                while (heading && heading.classList && !heading.classList.contains('ac-list-group-title')) {
                  heading = heading.previousElementSibling;
                }

                if (heading && heading.parentNode) {
                  heading.parentNode.removeChild(heading);
                }

                if (listElement.parentNode) {
                  listElement.parentNode.removeChild(listElement);
                }
              }

              function appendMessage(level, message) {
                if (!message) {
                  return;
                }

                var target = messageWrapper || container;
                var messageClass = 'messageStackWarning';
                if (level === 'error') {
                  messageClass = 'messageStackError';
                  clearMessages(target, 'messageStackSuccess');
                } else if (level === 'success') {
                  messageClass = 'messageStackSuccess';
                  clearMessages(target, 'messageStackError');
                }

                clearMessages(target, messageClass);

                var messageElement = document.createElement('div');
                messageElement.className = messageClass;
                messageElement.textContent = message;

                if (messageWrapper && messageWrapper.firstChild) {
                  messageWrapper.insertBefore(messageElement, messageWrapper.firstChild);
                } else {
                  target.insertBefore(messageElement, target.firstChild);
                }
              }

              function clearMessages(target, className) {
                if (!target || !className) {
                  return;
                }
                var existing = target.querySelectorAll('.' + className);
                for (var i = 0; i < existing.length; i++) {
                  var node = existing[i];
                  if (node && node.parentNode) {
                    node.parentNode.removeChild(node);
                  }
                }
              }
            })();
          </script>

      <?php
            }
          } else {
            echo '<div class="content nmx-content-center my-acc-subscription-empty">
            <img src="/includes/templates/nmn/images/my_account/subscriptions_emptystate_image.png" alt="">
          <p class="nmx-page-intro">' . TEXT_PAYPAL_SUBSCRIPTION_NOT_FOUND . '</p>
          </div>';
          }
          break;
      }//switch actions
      ?>
      <!-- <ul class="plans-columns" style="display: none;"> 
        <?php
          $base_hourly_rate = zen_get_products_actual_price(782);
          $plans_array = array(
            837 => array('response' => '72'), 
            838 => array('response' => '72'), 
            834 => array('response' => '48'), 
            839 => array('response' => '48'), 
            841 => array('response' => '24')
          );
          $column = 0;
          foreach($plans_array as $products_id => $plan_info) {
            $column++;
            $plans_array[$products_id]['name'] = zen_get_products_name($products_id);
            $plans_array[$products_id]['price'] = zen_get_products_actual_price($products_id);
            $plans_array[$products_id]['url'] = zen_href_link(zen_get_info_page($products_id), 'products_id=' . $products_id);
            $group_price = $db->Execute("SELECT group_percentage FROM " . TABLE_GROUP_PRICING . " WHERE group_name = '" . $plans_array[$products_id]['name'] . "' LIMIT 1;");
            $group_percentage = 0;
            if (!$group_price->EOF && isset($group_price->fields['group_percentage'])) {
              $group_percentage = $group_price->fields['group_percentage'];
            }
            $plans_array[$products_id]['discount'] = number_format((float)$group_percentage, 2);
            $hourly_rate = $base_hourly_rate - ($base_hourly_rate * ($plans_array[$products_id]['discount'] / 100));
            $plans_array[$products_id]['hourly_rate'] = $hourly_rate;
            $plans_array[$products_id]['hours'] = round($plans_array[$products_id]['price'] / $plans_array[$products_id]['hourly_rate'], 0);
          ?>
          <li class="plans-column column<?php echo $column; ?>">
              <h4><?php echo str_replace(array('The', 'Plan'), '', $plans_array[$products_id]['name']); ?></h4>  
              <div class="column-banner">
                  <div class="column-price">
                    <?php 
                     
                      echo $currencies->format($plans_array[$products_id]['price'], true, '', '', false); 
                    ?>
                    <span class="mo">/mo</span>
                  </div>
              </div>                                                                                                                
              <a href="<?php echo $plans_array[$products_id]['url']; ?>"><button class="column-button"><?php echo HIGHLIGHT_START_NOW; ?></button></a>
              <div class="column-text">
                  <?php echo 'Save ' . $plans_array[$products_id]['discount'] . '% Store-Wide'; ?><br />           
                  <?php echo $plans_array[$products_id]['hours'] . ($plans_array[$products_id]['hours'] > 1 ? ' Hours' : ' Hour') . ' Per Month'; ?><br />
                  <?php echo $plans_array[$products_id]['response'] . ' Hours Response Time'; ?>
              </div>
              <div class="column-text-readmore">                                                                                                                  
                  <a href="<?php echo zen_href_link($_GET['main_page']); ?>#features" class="scroll"><?php echo HIGHLIGHT_READ_MORE; ?></a>                  
              </div>
          </li>
        <?php
          }
        ?>    
      </ul> -->
  </div>
</div>
<!-- eof tpl_my_subscriptions_default.php -->