<?php
/**
 * supplies javascript to dynamically update the states/provinces list when the country is changed
 * TABLES: zones
 *
 * return string
 */
  function zen_oprc_js_zone_list_shipping($country, $form, $field) {
    global $db;
    $countries = $db->Execute("select distinct zone_country_id
                               from " . TABLE_ZONES . "
                               order by zone_country_id");
    $num_country = 1;
    $output_string = '';
    while (!$countries->EOF) {
      if ($num_country == 1) {
        $output_string .= '  if (' . $country . ' == "' . $countries->fields['zone_country_id'] . '") {' . "\n";
      } else {
        $output_string .= '  } else if (' . $country . ' == "' . $countries->fields['zone_country_id'] . '") {' . "\n";
      }

      $states = $db->Execute("select zone_name, zone_id
                              from " . TABLE_ZONES . "
                              where zone_country_id = '" . $countries->fields['zone_country_id'] . "'
                              order by zone_name");
      $num_state = 1;
      while (!$states->EOF) {
        if ($num_state == '1') $output_string .= '    ' . $form . '.' . $field . '.options[0] = new Option("' . PLEASE_SELECT . '", "");' . "\n";
        $output_string .= '    ' . $form . '.' . $field . '.options[' . $num_state . '] = new Option("' . $states->fields['zone_name'] . '", "' . $states->fields['zone_id'] . '");' . "\n";
        $num_state++;
        $states->MoveNext();
      }
      $num_country++;
      $countries->MoveNext();
      $output_string .= '    hideStateFieldShipping(' . $form . ');' . "\n" ;
    }
    $output_string .= '  } else {' . "\n" .
                      '    ' . $form . '.' . $field . '.options[0] = new Option("' . TYPE_BELOW . '", "");' . "\n" .
                      '    showStateFieldShipping(' . $form . ');' . "\n" .
                      '  }' . "\n";
    return $output_string;
  }
 
  function enable_shippingAddressCheckbox() {
    if ($_SESSION['cart']->get_content_type() == 'virtual') {
      return false;
    }
    if (OPRC_SHIPPING_ADDRESS != 'true') {
      return false;
    }
    return true;  
  }
  
  function enable_shippingAddress() {
    if (isset($_POST['shippingAddress']) && $_POST['shippingAddress'] == '1') { 
      return false;
    }
    if ($_SESSION['cart']->get_content_type() == 'virtual') {
      return false;
    }
    if (OPRC_SHIPPING_ADDRESS != 'true' || OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'true') {
      return false;
    }
    return true;  
  }

  if (!function_exists('zen_output_string_protected')) {
      function zen_output_string_protected($str) {
          return zen_db_prepare_input($str);
       }
  }   

/*
  * Validation that the user owns the address that they are tring to use
  * (sometimes problems with SESSION will cause the an invalid address_id)
  */
  
  function user_owns_address($address_book_id) {
    global $db;
    $check_address_query = "SELECT count(*) AS total
                            FROM " . TABLE_ADDRESS_BOOK . "
                            WHERE customers_id = :customersID
                            AND address_book_id = :addressBookID";
  
     $check_address_query = $db->bindVars($check_address_query, ':customersID', $_SESSION['customer_id'], 'integer');
     $check_address_query = $db->bindVars($check_address_query, ':addressBookID', $address_book_id, 'integer');
     $check_address = $db->Execute($check_address_query);
  
        if ($check_address->fields['total'] == '1') {
          return true;
        }
        else {
          return false;
        }
  }

  /**
   * Requeue message stack in SESSION for redirect
   * @param  array $messageStack
   * @return array $messageStack
   */
  function requeue_messageStack_for_redirect ( $messageStack ) {
    // rebuild messageStack
    if (sizeof($messageStack->messages) > 0) {
      $messageStackNew = new messageStack();
      for ($i=0, $n=sizeof($messageStack->messages); $i<$n; $i++) {
        if (strpos($messageStack->messages[$i]['params'], 'messageStackWarning') !== false) {
          $messageStack->messages[$i]['type'] = 'warning';
        }
        if (strpos($messageStack->messages[$i]['params'], 'messageStackSuccess') !== false) {
          $messageStack->messages[$i]['type'] = 'success';
        }
        if (strpos($messageStack->messages[$i]['params'], 'messageStackCaution') !== false) {
          $messageStack->messages[$i]['type'] = 'caution';
        }
        $messageStackNew->add_session($messageStack->messages[$i]['class'], strip_tags($messageStack->messages[$i]['text']), $messageStack->messages[$i]['type']);
      }
      $messageStack->reset();
      $messageStack = $messageStackNew;
    }
    return $messageStack;
  }

// eof