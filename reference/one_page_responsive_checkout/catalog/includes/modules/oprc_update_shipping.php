<?php

require_once(DIR_WS_CLASSES . 'http_client.php');
require_once(DIR_WS_FUNCTIONS . 'extra_functions/oprc_shipping_cache.php');

// if the order contains only virtual products, hide shipping input information
// a shipping address is not needed
if ($order->content_type == 'virtual') {
    $_SESSION['shipping'] = array();
    $_SESSION['shipping']['cost'] = 0;
    $_SESSION['shipping']['id'] = 'free_free';
    $_SESSION['shipping']['title'] = 'free_free';
    if (OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false') {
        $_SESSION['sendto'] = false;
    }
    if ((isset($_SESSION['shipping']) && $_SESSION['shipping'] != 'free_free' && !isset($_SESSION['shipping']['id']))
      || (isset($_SESSION['shipping']['id']) && $_SESSION['shipping']['id'] != 'free_free')) {
        if (!($messageStack->size('checkout_payment') > 0) && !($messageStack->size('checkout_shipping') > 0) && !($messageStack->size('redemptions') > 0)) {
            zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
        }
    } else {
        $_POST['shipping'] = 'free_free';
    }
}

$shipping_weight = $total_weight = $_SESSION['cart']->show_weight();
$total_count = $_SESSION['cart']->count_contents();
$recalculate_shipping_cost = false;

$oprc_action = '';
if (isset($_POST['oprcaction'])) {
    $oprc_action = $_POST['oprcaction'];
} elseif (isset($_GET['oprcaction'])) {
    $oprc_action = $_GET['oprcaction'];
}

// test for weight or quantity errors due to redirects
if (isset($_SESSION['total_weight']) || isset($_SESSION['total_count'])) {
    if ((round((float)$_SESSION['total_weight'], 2) != round((float)$total_weight, 2)) || (round((float)$_SESSION['total_count'], 2) != round((float)$total_count, 2))) {
        if (isset($_SESSION['shipping'])) {
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
if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (strtolower(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING) == 'true')) {
    $pass = false;

    switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
        case 'national':
            if ($order->delivery['country_id'] == STORE_COUNTRY) {
                $pass = true;
            }
            break;
        case 'international':
            if ($order->delivery['country_id'] != STORE_COUNTRY) {
                $pass = true;
            }
            break;
        case 'both':
            $pass = true;
            break;
    }

    $free_shipping = false;
    if (($pass == true) && (($_SESSION['cart']->show_total() >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER))) {
        $free_shipping = true;
    }
} else {
    $free_shipping = false;
}

// get all available shipping quotes
$quotes = [];
$should_get_quotes = ($oprc_action == 'process');

if ((!isset($_POST['oprcaction']) || $_POST['oprcaction'] != 'process') && (!isset($_GET['oprcaction']) || $_GET['oprcaction'] != 'process')) {
    $should_get_quotes = true;
}

if (!isset($_SESSION['shipping']) || $recalculate_shipping_cost) {
    $should_get_quotes = true;
}

if ($should_get_quotes) {
    $quotes = oprc_get_shipping_quotes($shipping_modules);
}

// cart contents have changed. Keep selected shipping method but update the cost
if ($recalculate_shipping_cost && isset($_SESSION['shipping']) && isset($_SESSION['shipping']['id'])) {
    $found_match = false;
    foreach ($quotes as $shipping_module) { //find matching method in new quotes
        if (is_array($shipping_module['methods'])) {
            foreach ($shipping_module['methods'] as $shipping_method) {
                if (isset($shipping_module['id']) && ($shipping_module['id'] . '_' . $shipping_method['id'] == $_SESSION['shipping']['id'])) {
                    $_SESSION['shipping']['cost'] = $shipping_method['cost'];
                    $_SESSION['shipping']['title'] = $shipping_module['module'] . ' (' . $shipping_method['title'] . ')';
                    $found_match = true;
                }
            }
        }
    }

    if (!$found_match) { //the selected method is not available for the new cart contents.
        unset($_SESSION['shipping']);
    }
}

// if no shipping method has been selected, automatically select the cheapest method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
if (
    !isset($_POST['shipping']) &&
    (
        !isset($_SESSION['shipping']) ||
        (
            isset($_SESSION['shipping']) &&
            (
                $_SESSION['shipping'] == false ||
                $_SESSION['shipping'] == "" ||
                empty($_SESSION['shipping'])
            ) &&
            (zen_count_shipping_modules() > 1)
        )
    )
) {
    $cheapest = $shipping_modules->cheapest();
    $_POST['shipping'] = $cheapest != false ? $cheapest['id'] : '';
    $_SESSION['shipping'] = $cheapest != false ? $cheapest : [];
    $_GET['oprcaction'] = 'process';
    $update_check = true;
}

$oprc_update = "";
if (isset($_REQUEST['request']) && $_REQUEST['request'] == 'ajax') {
    $oprc_update = "request=ajax";
}
if ((isset($_GET['oprcaction']) && $_GET['oprcaction'] == 'process') || (isset($_POST['oprcaction']) && $_POST['oprcaction'] == 'process')) {

    $selectedQuote = null;
    $selectedMethod = null;
    $quoteError = null;
    if ((zen_count_shipping_modules() > 0) || ($free_shipping == true)) {
        if ((isset($_POST['shipping'])) && (strpos($_POST['shipping'], '_'))) {
            /**
             * check to be sure submitted data hasn't been tampered with
             */
            if ($_SESSION['customer_default_address_id'] == 0 || !user_owns_address($_SESSION['customer_default_address_id'])) {
                $quoteError = OPRC_NO_ADDRESS_ERROR_MESSAGE;
            } elseif ($_POST['shipping'] == 'free_free' && ($order->content_type != 'virtual' && !$pass)) {
                $quoteError = 'Invalid input. Please make another selection.';
            } else {
                if ($_SESSION['shipping'] == '') {
                    unset($_SESSION['shipping']);
                }
                $_SESSION['shipping']['id'] = $_POST['shipping'];
            }

            if (!isset($quoteError)) {
                list($module, $method) = explode('_', $_SESSION['shipping']['id']);

                if ($_SESSION['shipping']['id'] == 'free_free') {
                    $selectedMethod = [
                        'id' => 'free_free',
                        'title' => FREE_SHIPPING_TITLE,
                        'cost' => '0',
                    ];
                    $selectedQuote = [
                        'id' => 'free_free',
                        'module' => FREE_SHIPPING_TITLE,
                        'methods' => [$selectedMethod],
                    ];
                } else {
                    if (!is_array($quotes)) {
                        $quotes = [];
                    }

                    foreach ($quotes as $quoteEntry) {
                        if (!is_array($quoteEntry)) {
                            continue;
                        }

                        if (!isset($quoteEntry['id']) || $quoteEntry['id'] !== $module) {
                            continue;
                        }

                        $selectedQuote = $quoteEntry;

                        if (isset($quoteEntry['error'])) {
                            $quoteError = $quoteEntry['error'];
                            break;
                        }

                        if (isset($quoteEntry['methods']) && is_array($quoteEntry['methods'])) {
                            foreach ($quoteEntry['methods'] as $methodEntry) {
                                if (!is_array($methodEntry) || !isset($methodEntry['id'])) {
                                    continue;
                                }

                                if ($methodEntry['id'] === $method) {
                                    $selectedMethod = $methodEntry;
                                    break;
                                }
                            }

                            if ($selectedMethod === null && isset($quoteEntry['methods'][0]) && is_array($quoteEntry['methods'][0])) {
                                $selectedMethod = $quoteEntry['methods'][0];
                            }
                        }

                        break;
                    }

                    if ($selectedQuote === null) {
                        $quoteError = 'Invalid input. Please make another selection.';
                    } elseif ($selectedMethod === null && $quoteError === null) {
                        $quoteError = 'Invalid input. Please make another selection.';
                    }
                }
            }

            if (isset($quoteError)) {
                unset($_SESSION['shipping']);
            } elseif ($selectedQuote !== null && $selectedMethod !== null && isset($selectedMethod['title']) && isset($selectedMethod['cost'])) {
                $moduleTitle = '';
                if (isset($selectedQuote['module'])) {
                    $moduleTitle = trim($selectedQuote['module']);
                }

                if ($free_shipping == true) {
                    $title = $selectedMethod['title'];
                } elseif ($moduleTitle === '') {
                    $title = $selectedMethod['title'];
                } else {
                    $title = $moduleTitle . ' (' . $selectedMethod['title'] . ')';
                }

                $_SESSION['shipping'] = array(
                    'id' => $_SESSION['shipping']['id'],
                    'title' => $title,
                    'cost' => $selectedMethod['cost']
                );
                // rebuild messageStack
                if (sizeof($messageStack->messages) > 0) {
                    $messageStack = requeue_messageStack_for_redirect($messageStack);

                    // add address book error again
                    if ($_SESSION['customer_default_address_id'] == 0 && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) {
                        $messageStack->add('checkout_address', OPRC_NO_ADDRESS_ERROR_MESSAGE, 'error');
                    }
                }
                // end rebuild messageStack
                if (!isset($ajax_request)) {
                    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
                }
            } else {
                unset($_SESSION['shipping']);
                if (!isset($ajax_request)) {
                    zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL', false));
                }
            }
        }
    } else {
        unset($_SESSION['shipping']);
        // redirect will cause infinite loop, do nothing
        if ($oprc_update != '') {
            // rebuild messageStack
            if (sizeof($messageStack->messages) > 0) {
                $messageStack = requeue_messageStack_for_redirect($messageStack);

                //add address book error again
                if ($_SESSION['customer_default_address_id'] == 0 && isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) {
                    $messageStack->add('checkout_address', OPRC_NO_ADDRESS_ERROR_MESSAGE, 'error');
                }
            }
            // end rebuild messageStack

            if (!isset($ajax_request)) {
                zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, $oprc_update, 'SSL', false));
            }
        }
    }
}
