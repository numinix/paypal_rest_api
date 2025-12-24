<?php
// default
  $zco_notifier->notify('NOTIFY_HEADER_START_CHECKOUT');

  // set template style
  if (defined('OPRC_SPLIT_CHECKOUT') and OPRC_SPLIT_CHECKOUT == 'true' and $credit_covers == false) {
    $checkoutStyle = 'split';
  }

  //if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($_SESSION['cart']->count_contents() <= 0) {
    zen_redirect(zen_href_link(FILENAME_TIME_OUT));
  }


  if (zen_get_customer_validate_session($_SESSION['customer_id']) == false) {
    $_SESSION['navigation']->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_ONE_PAGE_CHECKOUT));
    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
  }


  // Validate Cart for checkout
  $_SESSION['valid_to_checkout'] = true;
  $_SESSION['cart']->get_products(true);
  if ($_SESSION['valid_to_checkout'] == false) {
    //$messageStack->add_session('header', ERROR_CART_UPDATE, 'error');
    zen_redirect(zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL', false));
  }

  // Stock Check
  if ( (STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true') ) {
    $products = $_SESSION['cart']->get_products();
    $p_count = count($products);
    for ($i=0, $n=$p_count; $i<$n; $i++) {

      // Added to allow individual stock of different attributes
      unset($attributes);
      if(is_array($products[$i]['attributes'])) {
        $attributes = $products[$i]['attributes'];
      } else  {
        $attributes = '';
      }
      // End change

      $stockUpdate = zen_get_products_stock($products[$i]['id'], $products[$i]['attributes']);
      $stockAvailable = is_array($stockUpdate) ? $stockUpdate['quantity'] : $stockUpdate;
      if (($stockAvailable - $products[$i]['quantity']) < 0) {
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL', false));
        break;
      }
    }
  }


  // register a random ID in the session to check throughout the checkout procedure
  // against alterations in the shopping cart contents
  if (isset($_SESSION['cart']->cartID)) {
    if (!isset($_SESSION['cartID']) || $_SESSION['cart']->cartID != $_SESSION['cartID']) {
      $_SESSION['cartID'] = $_SESSION['cart']->cartID;
    }
  } else {
    $_SESSION['cart']->cartID = $_SESSION['cart']->generate_cart_id();
    //zen_redirect(zen_href_link(FILENAME_TIME_OUT));
  }


    // fix bug where customers_default_address_id does not exist in address book table
   if(!($_SESSION['customer_default_address_id'] > 0)) {
        $address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " ab JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = ab.customers_id AND ab.address_book_id = c.customers_default_address_id WHERE c.customers_id = " . (int)$_SESSION['customer_id']. " LIMIT 1;");
        if ($address_book->RecordCount() <= 0) {
          // address book doesn't exist, so get newest for customer
          $new_address_book = $db->Execute("SELECT address_book_id FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " ORDER BY address_book_id DESC LIMIT 1;");
          if ($new_address_book->fields['address_book_id'] > 0) {
            // update the customers table
            $_SESSION['customer_default_address_id'] =  $new_address_book->fields['address_book_id'];
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_default_address_id = " . $new_address_book->fields['address_book_id'] . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " LIMIT 1;");
          }
        }
   }

  if(($_SESSION['customer_default_address_id'] == 0 || !user_owns_address($_SESSION['customer_default_address_id']))&& isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) {
    $messageStack->add('checkout_address', OPRC_NO_ADDRESS_ERROR_MESSAGE, 'error');
  }


  // set the shipping address default if it's not already set
  if (!isset($_SESSION['customers_default_shipping_address_id']) || !$_SESSION['customers_default_shipping_address_id']) $_SESSION['customers_default_shipping_address_id'] = $_SESSION['customer_default_address_id'];

  // if no shipping destination address was selected, use the customers own address as default
  if (!isset($_SESSION['sendto']) || !$_SESSION['sendto']) {
    $_SESSION['sendto'] = $_SESSION['customers_default_shipping_address_id'];
  } else {
  // verify the selected shipping address
    $check_address_query = "SELECT count(*) AS total
                            FROM   " . TABLE_ADDRESS_BOOK . "
                            WHERE  customers_id = :customersID
                            AND    address_book_id = :addressBookID";

    $check_address_query = $db->bindVars($check_address_query, ':customersID', $_SESSION['customer_id'], 'integer');
    $check_address_query = $db->bindVars($check_address_query, ':addressBookID', $_SESSION['sendto'], 'integer');
    $check_address = $db->Execute($check_address_query);

    if ($check_address->fields['total'] != '1') {
      $_SESSION['sendto'] = $_SESSION['customers_default_shipping_address_id'];
      $_SESSION['shipping'] = '';
    }
  }

  if (OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'true' && $_SESSION['cart']->get_content_type() != 'virtual') {
    $_SESSION['billto'] = $_SESSION['sendto'];
  }

  // if no billing destination address was selected, use the customers own address as default
  if (!isset($_SESSION['billto']) || !$_SESSION['billto']) {
    $_SESSION['billto'] = $_SESSION['customer_default_address_id'];
  } else {
    // verify the selected billing address
    $check_address_query = "SELECT count(*) AS total FROM " . TABLE_ADDRESS_BOOK . "
                            WHERE customers_id = :customersID
                            AND address_book_id = :addressBookID";

    $check_address_query = $db->bindVars($check_address_query, ':customersID', $_SESSION['customer_id'], 'integer');
    $check_address_query = $db->bindVars($check_address_query, ':addressBookID', $_SESSION['billto'], 'integer');
    $check_address = $db->Execute($check_address_query);

    if ($check_address->fields['total'] != '1') {
      $_SESSION['billto'] = $_SESSION['customer_default_address_id'];
      $_SESSION['payment'] = '';
    }
  }

  require_once(DIR_WS_CLASSES . 'order.php');
  $order = new order();

  // load all enabled payment modules
  require_once(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment;

  // load all enabled shipping modules
  require_once(DIR_WS_CLASSES . 'shipping.php');
  $shipping_modules = new shipping();

  // BEGIN REWARDS POINTS
  // if credit does not cover order total or isn't selected
  if (!isset($_SESSION['credit_covers']) || $_SESSION['credit_covers'] != true) {
    // check that a gift voucher isn't being used that is larger than the order
    if ((isset($_SESSION['cot_gv']) && $_SESSION['cot_gv'] <= $order->info['total']) || !isset($_SESSION['cot_gv'])) {
      $credit_covers = false;
    }
  } else {
    $credit_covers = true;
  }
  // END REWARDS POINTS
  if ($credit_covers) {
    unset($_SESSION['payment']);
  }

  if (isset($_GET['payment_error']) && is_object(${$_GET['payment_error']}) && ($error = ${$_GET['payment_error']}->get_error())) {
    $messageStack->add('checkout_payment', $error['error'], 'error');
    unset($_SESSION['payment']);
  }
  require_once(DIR_WS_CLASSES . 'order_total.php');
  $order_total_modules = new order_total();
  $order_total_modules->collect_posts();
  $order_total_modules->pre_confirmation_check();
  $order_totals = $order_total_modules->process();

  // avoid entire page reloading
  //if ((OPRC_AJAX_CONFIRMATION_STATUS == 'true') && ($messageStack->size('redemptions') > 0 || $messageStack->size('checkout_payment') > 0)) {
    //$_REQUEST['request'] = 'ajax';
  //} else {
    //unset($_SESSION['request']);
  //}

  // get coupon code
  if (isset($_SESSION['cc_id']) && $_SESSION['cc_id']) {
    $discount_coupon_query = "SELECT coupon_code
                              FROM " . TABLE_COUPONS . "
                              WHERE coupon_id = :couponID";

    $discount_coupon_query = $db->bindVars($discount_coupon_query, ':couponID', $_SESSION['cc_id'], 'integer');
    $discount_coupon = $db->Execute($discount_coupon_query);
  }

  // Should address-edit button be offered?
  $change_address_button = BUTTON_CHANGE_ADDRESS_ALT;

  // if shipping-edit button should be overridden, do so
  $editShippingButtonLink = zen_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL', false);
$payment_module = $_SESSION['payment'] ?? null;
$payment_object = isset($payment_module) && isset($$payment_module) ? $$payment_module : null;

if (is_object($payment_object) && method_exists($payment_object, 'alterShippingEditButton')) {
    $theLink = ${$_SESSION['payment']}->alterShippingEditButton();
    if ($theLink) {
      $editShippingButtonLink = $theLink;
      $displayAddressEdit = true;
    }
  }

  $comments = isset($_SESSION['comments']) ? $_SESSION['comments'] : null;
  $flagOnSubmit = sizeof($payment_modules->selection());

  if (isset($_POST['payment'])) $_SESSION['payment'] = $_POST['payment'];
  if (isset($_POST['comments'])) $_SESSION['comments'] = zen_db_prepare_input($_POST['comments']);

  // initialize modules
  $oprc_init_dir_full = DIR_FS_CATALOG . DIR_WS_MODULES . 'oprc_init/';
  $oprc_init_dir = DIR_WS_MODULES . 'oprc_init/';
  if ($dir = @dir($oprc_init_dir_full)) {
    while ($file = $dir->read()) {
      if (!is_dir($oprc_init_dir_full . $file)) {
        if (preg_match('/\.php$/', $file) > 0) {
          //include init file
          include($oprc_init_dir . $file);
        }
      }
    }
    $dir->close();
  }

  if ((isset($_GET['oprcaction']) && $_GET['oprcaction'] == 'process') || (isset($_POST['oprcaction']) && $_POST['oprcaction'] == 'process')) {
    if (!isset($update_check) || !$update_check) {
      if(!isset($oprc_update)) {
        $oprc_update = '&oprcaction=null';
      } else {
        $oprc_update .= '&oprcaction=null';
      }
    }
    //$debug_logger->log_event (__FILE__, __LINE__, $oprc_update);
    $bool = true; //tell a freand
    $form_action_url = zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false);
    if (zen_not_null($_POST['comments'])) {
      $_SESSION['comments'] = zen_db_prepare_input($_POST['comments']);
    }
    $comments = isset($_SESSION['comments']) ? $_SESSION['comments'] : null;

    // process modules
    $oprc_process_dir_full = DIR_FS_CATALOG . DIR_WS_MODULES . 'one_page_checkout_process/';
    $oprc_process_dir = DIR_WS_MODULES . 'one_page_checkout_process/';
    if ($dir = @dir($oprc_process_dir_full)) {
      while ($file = $dir->read()) {
        if (!is_dir($oprc_process_dir_full . $file)) {
          if (preg_match('/\.php$/', $file) > 0) {
            //include init file
            include($oprc_process_dir . $file);
          }
        }
      }
      $dir->close();
    }
  }

  if ((isset($_POST['shipping']) && (strpos($_POST['shipping'], '_'))) || OPRC_AJAX_SHIPPING_QUOTES != 'true' || !isset($_SESSION['shipping_quotes'])) {
    require(DIR_WS_MODULES . 'oprc_update_shipping.php');
  }

  // new order total
  //$order_total_modules = new order_total();

  $breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
  // last line of script
  $zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT');
