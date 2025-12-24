<?php
  require_once(DIR_WS_CLASSES . 'http_client.php');

  // if the order contains only virtual products, hide shipping input information
  // a shipping address is not needed
  if ( $order->content_type == 'virtual' ) {
    $_SESSION['shipping'] = array();
    $_SESSION['shipping']['cost'] = 0;
    $_SESSION['shipping']['id'] = 'free_free';
    $_SESSION['shipping']['title'] = 'free_free';
    if (OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false') $_SESSION['sendto'] = false;
    if ( (isset($_SESSION['shipping']) && $_SESSION['shipping'] != 'free_free' && !isset($_SESSION['shipping']['id']))
      || (isset($_SESSION['shipping']['id']) && $_SESSION['shipping']['id'] != 'free_free') ) {
      if ( !($messageStack->size('checkout_payment') > 0) && !($messageStack->size('checkout_shipping') > 0) && !($messageStack->size('redemptions') > 0) ) {
        //zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'request=' . $_REQUEST['request'], 'SSL'));
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
      }
    } else {
      $_POST['shipping'] = 'free_free';
    }
  }

  $shipping_weight = $total_weight = $_SESSION['cart']->show_weight();
  $total_count = $_SESSION['cart']->count_contents();
  $recalculate_shipping_cost = false;

  // test for weight or quantity errors due to redirects
  if ( isset($_SESSION['total_weight']) || isset($_SESSION['total_count']) ) {
    if ( (round((float)$_SESSION['total_weight'], 2) != round((float)$total_weight, 2)) || (round((float)$_SESSION['total_count'], 2) != round((float)$total_count, 2)) ) {
      if ( isset($_SESSION['shipping']) ) {
        // shipping is inccorect, therefore unset
        $recalculate_shipping_cost = true;
        //unset($_SESSION['shipping']);
      }
    }
  }

  // set the sessions for total weight and total count to be used during redirects
  $_SESSION['total_weight'] = $total_weight;
  $_SESSION['total_count'] = $total_count;

  $pass = true;
  if ( defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (strtolower(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING) == 'true') ) {
    $pass = false;

    switch ( MODULE_ORDER_TOTAL_SHIPPING_DESTINATION ) {
      case 'national':
        if ( $order->delivery['country_id'] == STORE_COUNTRY ) {
          $pass = true;
        }
        break;
      case 'international':
        if ( $order->delivery['country_id'] != STORE_COUNTRY ) {
          $pass = true;
        }
        break;
      case 'both':
        $pass = true;
        break;
    }

    $free_shipping = false;
    if ( ($pass == true) && (($_SESSION['cart']->show_total() >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) ) {
      $free_shipping = true;
    }
  } else {
    $free_shipping = false;
  }

  // get all available shipping quotes
  if ( ( ($_POST['oprcaction'] != 'process') && ($_GET['oprcaction'] != 'process') ) || !isset($_SESSION['shipping']) || $recalculate_shipping_cost ) {
    $quotes = $shipping_modules->quote();
  }

  // cart contents have changed. Keep selected shipping method but update the cost
  if ( $recalculate_shipping_cost && isset($_SESSION['shipping']) && isset($_SESSION['shipping']['id']) ) {
    $found_match = false;
    foreach ( $quotes as $shipping_module ) { //find matching method in new quotes
        if ( is_array($shipping_module['methods']) ) {
        foreach ( $shipping_module['methods'] as $shipping_method ) {
          if ( isset($shipping_module['id']) && ($shipping_module['id'] . '_' . $shipping_method['id'] == $_SESSION['shipping']['id']) ) {
            $_SESSION['shipping']['cost'] = $shipping_method['cost'];
            $_SESSION['shipping']['title'] = $shipping_module['module'] . ' (' . $shipping_method['title'] . ')';
            $found_match = true;
          }
        }
      }
    }

    if ( !$found_match ) { //the selected method is not available for the new cart contents.
      unset($_SESSION['shipping']);
    }
  }

  // if no shipping method has been selected, automatically select the cheapest method.
  // if the modules status was changed when none were available, to save on implementing
  // a javascript force-selection method, also automatically select the cheapest shipping
  // method if more than one module is now enabled
  if ( !isset($_POST['shipping']) &&
    (!$_SESSION['shipping'] || ( $_SESSION['shipping'] && ($_SESSION['shipping'] == false) && (zen_count_shipping_modules() > 1) )) ) {

    $cheapest = $shipping_modules->cheapest();
    $_POST['shipping'] = $cheapest['id'];
    $_SESSION['shipping'] = $cheapest;
    $_GET['oprcaction'] = 'process';
    $update_check = true;
  }

  $oprc_update = "";
  if ( $_REQUEST['request'] == 'ajax' ) $oprc_update = "request=ajax";
  if ( $_GET['oprcaction'] == 'process' || $_POST['oprcaction'] == 'process' ) {

    $quote = array();
    if ( (zen_count_shipping_modules() > 0) || ($free_shipping == true) ) {

      if ( (isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_')) ) {
        /**
         * check to be sure submitted data hasn't been tampered with
         */
        if ( $_SESSION['customer_default_address_id'] == 0 || !user_owns_address($_SESSION['customer_default_address_id']) ) {
          $quote['error'] = OPRC_NO_ADDRESS_ERROR_MESSAGE;
        }
        else if ( $_POST['shipping'] == 'free_free' && ($order->content_type != 'virtual' && !$pass) ) {
          $quote['error'] = 'Invalid input. Please make another selection.';
        } else {
          $_SESSION['shipping']['id'] = $_POST['shipping'];
        }
        list($module, $method) = explode('_', $_SESSION['shipping']['id']);
        if ( is_object(${$module}) || ($_SESSION['shipping']['id'] == 'free_free') ) {
          if ( $_SESSION['shipping']['id'] == 'free_free' ) {
            $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
            $quote[0]['methods'][0]['cost'] = '0';
          } else {
            // avoid incorrect calculations during redirect
            $shipping_modules = new shipping();
            $error = $quote['error']; // keep error, to prevent infinate redirect
            $quote = $shipping_modules->quote($method, $module);
            $quote['error'] = $error;
          }
          if ( isset($quote['error']) ) {
            unset($_SESSION['shipping']);
          } else {
            if ( (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost'])) ) {
              $_SESSION['shipping'] = array('id' => $_SESSION['shipping']['id'],
                                            'title' => (($free_shipping == true) ?  $quote[0]['methods'][0]['title'] : $quote[0]['module'] . ' (' . $quote[0]['methods'][0]['title'] . ')'),
                                            'cost' => $quote[0]['methods'][0]['cost']);
              $shipping_modules = new shipping();
              $quotes = $shipping_modules->quote();
              // rebuild messageStack
              if ( sizeof($messageStack->messages) > 0 ) {
                $messageStack = requeue_messageStack_for_redirect($messageStack);

                // add address book error again
                if ( $_SESSION['customer_default_address_id'] == 0 && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0 ) {
                  $messageStack->add('checkout_address', OPRC_NO_ADDRESS_ERROR_MESSAGE, 'error');
                }
              }
              // end rebuild messageStack
              if (!isset($ajax_request)) zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
            } else {
              unset($_SESSION['shipping']);
              if (!isset($ajax_request)) zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
            }
          }
        } else {
          unset($_SESSION['shipping']);
        }
      }
    } else {
      unset($_SESSION['shipping']);
      // redirect will cause infinite loop, do nothing
      if ( $oprc_update != '' ) {
        // rebuild messageStack
        if ( sizeof($messageStack->messages) > 0 ) {
          $messageStack = requeue_messageStack_for_redirect($messageStack);

          //add address book error again
          if ( $_SESSION['customer_default_address_id'] == 0 && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0 ) {
            $messageStack->add('checkout_address', OPRC_NO_ADDRESS_ERROR_MESSAGE, 'error');
          }
        }
        // end rebuild messageStack

        if (!isset($ajax_request)) zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, $oprc_update, 'SSL', false));
      }
    }
  }
