<?php
// Initialize Zen Cart environment
// Capture any incoming zen session id so we can keep the same cart even if cookies are blocked (eg, sandboxed iframes).
$incomingSessionParam = null;
foreach (array_keys($_GET) as $paramName) {
    if (stripos($paramName, 'zenid') === 0) {
        $incomingSessionParam = $paramName;
        break;
    }
}
if ($incomingSessionParam !== null && !empty($_GET[$incomingSessionParam])) {
    $incomingSessionId = $_GET[$incomingSessionParam];
    if (!isset($_COOKIE[$incomingSessionParam])) {
        $_COOKIE[$incomingSessionParam] = $incomingSessionId;
    }
    if (!isset($_COOKIE['cookie_test'])) {
        $_COOKIE['cookie_test'] = 'paypalr_wallet_session_bridge';
    }
}

require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$_GET['main_page'] = $current_page_base = 'checkout_process';
$loaderPrefix = 'paypalr_wallet_ajax';
require('includes/application_top.php');
require_once(DIR_WS_FUNCTIONS . 'paypalr_functions.php');
// Note: Customer.php does not exist in Zen Cart 1.5.6c+ and is not needed
// The following classes are loaded via Zen Cart's autoloader or must be explicitly required
if (!class_exists('payment')) {
    require_once(DIR_WS_CLASSES . 'payment.php');
}
if (!class_exists('order')) {
    require_once(DIR_WS_CLASSES . 'order.php');
}
if (!class_exists('shipping')) {
    require_once(DIR_WS_CLASSES . 'shipping.php');
}
if (!class_exists('order_total')) {
    require_once(DIR_WS_CLASSES . 'order_total.php');
}

$isAjaxRequest = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

if (function_exists('zen_load_language')) {
    zen_load_language('checkout_process');
    zen_load_language('email_extras');
} else {
    paypalr_wallet_load_language_file('checkout_process');
    paypalr_wallet_load_language_file('email_extras');
}

define('LOG_FILE_PATH', DIR_FS_LOGS . '/paypalr_wallet_handler.log');

$braintreeCheckoutErrorHandled = false;
$braintreeCheckoutErrorResponder = function ($message) use (&$braintreeCheckoutErrorHandled) {
    if ($braintreeCheckoutErrorHandled) {
        return;
    }
    $braintreeCheckoutErrorHandled = true;
    paypalr_wallet_checkout_log($message);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Checkout error: ' . $message
    ]);
};

set_exception_handler(function ($e) use ($braintreeCheckoutErrorResponder) {
    $braintreeCheckoutErrorResponder('Unhandled exception - ' . $e->getMessage());
    exit;
});

register_shutdown_function(function () use ($braintreeCheckoutErrorResponder) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $braintreeCheckoutErrorResponder('Fatal error - ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    }
});

// Capture the payload
$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload['payment_method_nonce']) || empty($payload['module'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

log_paypalr_wallet_message('Received checkout payload: ' . print_r($payload, true));

// Ensure payload is valid
if (empty($payload['payment_method_nonce']) || empty($payload['total']) || empty($payload['module'])) {
    $response = ['status' => 'error', 'message' => 'Invalid payload'];
    echo json_encode($response);
    exit;
}

$module = $payload['module']; // Payment module (e.g., paypalr_googlepay, paypalr_applepay, etc.)

switch ($module) {
    case 'paypalr_googlepay':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_DEBUGGING';
        break;
    case 'paypalr_applepay':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_PAYPALR_APPLE_PAY_DEBUGGING';
        break;
    case 'paypalr_venmo':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_PAYPALR_VENMO_DEBUGGING';
        break;
    default:
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_PAYPALR_DEBUGGING';
        break;
}

$_SESSION['payment_method_nonce'] = $_POST['payment_method_nonce'] = $payment_method_nonce = $payload['payment_method_nonce'];
$_SESSION['currency'] = $currency = $payload['currency'];
$_SESSION['payment'] = $module;
$total = $payload['total'];
$email = $payload['email'] ?? '';
$shipping_address_raw = $payload['shipping_address'] ?? [];
$billing_address_raw  = $payload['billing_address'] ?? [];

// Log the payload total for debugging potential amount mismatches
log_paypalr_wallet_message("Payload total from client: $total (currency: $currency)");

// Normalize
$shipping_address = normalize_braintree_contact($shipping_address_raw, $module);
$billing_address  = normalize_braintree_contact($billing_address_raw, $module);

// Extract email from billing address if not provided in payload
if (empty($email)) {
    $email = $billing_address['emailAddress'] ?? $billing_address['email'] ?? '';
    log_paypalr_wallet_message("Email extracted from billing address: $email");
}

// Validate that we have an email address and it's in proper format
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    log_paypalr_wallet_message("ERROR: Invalid or missing email address: " . var_export($email, true));
    echo json_encode([
        'status' => 'error',
        'message' => 'A valid email address is required for checkout'
    ]);
    exit;
}

log_paypalr_wallet_message("Processing order for email: $email");

function incomplete_address_fields($addr) {
    $missing = [];
    $name = trim($addr['name'] ?? '');

    if ($name === '')                  $missing[] = 'name';
    if (empty($addr['address1']))      $missing[] = 'address1';
    if (empty($addr['locality']))      $missing[] = 'locality';
    if (empty($addr['countryCode']))   $missing[] = 'countryCode';
    if (empty($addr['postalCode']))    $missing[] = 'postalCode';

    return $missing;
}

function is_complete_address($addr) {
    return count(incomplete_address_fields($addr)) === 0;
}

$shipping_complete = is_complete_address($shipping_address);
$billing_complete  = is_complete_address($billing_address);
$shipping_errors   = incomplete_address_fields($shipping_address);
$billing_errors    = incomplete_address_fields($billing_address);
$shipping_required = requires_shipping_address();

if (!$shipping_required) {
    $shipping_address = $billing_address;
    $shipping_complete = true;
}

// For store pickup, use the billing address as the shipping address
if (!empty($_SESSION['shipping']['id']) && strpos($_SESSION['shipping']['id'], 'storepickup') !== false) {
    $shipping_address = $billing_address;
    $shipping_complete = true;
}

function requires_shipping_address() {
    if (isset($_SESSION['cart']) && method_exists($_SESSION['cart'], 'get_content_type')) {
        if ($_SESSION['cart']->get_content_type() === 'virtual') {
            return false;
        }
    }
    if (!empty($_SESSION['shipping']['id']) && strpos($_SESSION['shipping']['id'], 'storepickup') !== false) {
        return false;
    }
    return true;
}

if ((!$shipping_complete && $shipping_required) || !$billing_complete) {
    global $messageStack;
    log_paypalr_wallet_message('Raw shipping address: ' . print_r($shipping_address_raw, true));
    log_paypalr_wallet_message('Raw billing address: ' . print_r($billing_address_raw, true));
    if (!$shipping_complete && $shipping_required) {
        log_paypalr_wallet_message('Missing shipping fields: ' . implode(', ', $shipping_errors));
    }
    if (!$billing_complete) {
        log_paypalr_wallet_message('Missing billing fields: ' . implode(', ', $billing_errors));
    }
    log_paypalr_wallet_message('Incomplete shipping or billing address. Redirecting to checkout.');
    $messageStack->add_session('header', 'Your payment was not processed. Please complete checkout.', 'error');
    echo json_encode([
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')
    ]);
    exit;
}

if ($shipping_required && empty($_SESSION['shipping']['id'])) {
    global $messageStack;
    log_paypalr_wallet_message('Missing shipping selection. Redirecting to checkout.');
    $messageStack->add_session('header', 'Your payment was not processed. Please complete checkout.', 'error');
    echo json_encode([
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')
    ]);
    exit;
}

// Parse names
$billing_name = trim($billing_address['name']);
$billing_parts = preg_split('/\s+/', $billing_name);

if (count($billing_parts) === 1) {
    $billing_first = $billing_parts[0];
    $billing_last  = '';
} elseif (count($billing_parts) === 2) {
    list($billing_first, $billing_last) = $billing_parts;
} else { // >= 3 parts
    $billing_first = $billing_parts[0] . ' ' . $billing_parts[1];
    $billing_last  = implode(' ', array_slice($billing_parts, 2));
}

$billing_first_name = $_SESSION['customer_first_name'] = $billing_first ?: 'Guest';
$billing_last_name  = $_SESSION['customer_last_name']  = $billing_last;

$shipping_customer_name = trim($shipping_address['name']);  // Use shipping name
$shipping_parts = preg_split('/\s+/', $shipping_customer_name);
if (count($shipping_parts) === 1) {
    $shipping_first_name = $shipping_parts[0];
    $shipping_last_name  = '';
} elseif (count($shipping_parts) === 2) {
    list($shipping_first_name, $shipping_last_name) = $shipping_parts;
} else {
    $shipping_first_name = $shipping_parts[0] . ' ' . $shipping_parts[1];
    $shipping_last_name  = implode(' ', array_slice($shipping_parts, 2));
}

log_paypalr_wallet_message("Using payment nonce: $payment_method_nonce");
log_paypalr_wallet_message("Billing address: " . print_r($billing_address, true));
log_paypalr_wallet_message("Shipping address: " . print_r($shipping_address, true));

// Validate module
$valid_modules = ['braintree_api', 'paypalr_googlepay', 'paypalr_applepay', 'paypalr_venmo'];
if (!in_array($module, $valid_modules)) {
    die('Invalid payment module');
}

// Determine if guest flow should be used
$guestModules = ['paypalr_googlepay', 'paypalr_applepay'];
$isGuestCheckout = in_array($module, $guestModules, true);

// Check if customer exists by email
$customer_query = $db->Execute("SELECT customers_id, customers_firstname, customers_lastname FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . zen_db_input($email) . "'");

if (!isset($_SESSION['customer_id'])) {
    if ($customer_query->RecordCount() > 0) {
        // If customer exists, use existing customer ID and set session variables
        $customer_id = $customer_query->fields['customers_id'];
        $existing_first = $customer_query->fields['customers_firstname'];
        $existing_last = $customer_query->fields['customers_lastname'];
        log_paypalr_wallet_message("Using existing customer ID: $customer_id");
    } else {
        // Create the new customer record
        $db->Execute("INSERT INTO " . TABLE_CUSTOMERS . " (customers_email_address, customers_firstname, customers_lastname)
                      VALUES ('" . zen_db_input($email) . "', '" . zen_db_input($billing_first_name) . "', '" . zen_db_input($billing_last_name) . "')");

        // Get the new customer ID
        $customer_id = $db->Insert_ID();
        log_paypalr_wallet_message("Created new customer ID: $customer_id with email: $email");
        
        $existing_first = $billing_first_name;
        $existing_last = $billing_last_name;

        // If the COWOA_account column exists, set it to 1
        $check_cowoa_account = $db->Execute("SHOW COLUMNS FROM " . TABLE_CUSTOMERS . " LIKE 'COWOA_account'");
        if ($check_cowoa_account->RecordCount() > 0) {
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET COWOA_account = 1 WHERE customers_id = $customer_id");
        }

        // Insert into TABLE_CUSTOMERS_INFO
        $db->Execute("INSERT INTO " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_date_of_last_logon, customers_info_number_of_logons, customers_info_date_account_created, customers_info_date_account_last_modified)
                      VALUES ($customer_id, now(), 1, now(), now())");
    }
    
    // Set all customer session variables consistently
    $_SESSION['customer_id'] = $customer_id;
    $_SESSION['customer_first_name'] = $existing_first;
    $_SESSION['customer_last_name'] = $existing_last;
    $_SESSION['customer_email_address'] = $email;
    // Set session variables based on whether guest checkout is used
    if ($isGuestCheckout) {
        // Treat the Google/Apple Pay flow as a guest checkout but still set the core customer id
        $_SESSION['customer_guest_id'] = $customer_id;
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['COWOA'] = true;
        $_SESSION['customer_loggedin_type'] = 'guest';

        if (in_array($module, ['paypalr_googlepay', 'paypalr_applepay'], true)) {
            $_SESSION['braintree_express_checkout'] = $module;
        }
    } else {
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_loggedin_type'] = "customer";
    }
} else {
    $customer_id = $_SESSION['customer_id'];
}

log_paypalr_wallet_message("Customer ID: $customer_id");

// Check if the shipping address already exists in the database
$address_query = $db->Execute("SELECT address_book_id
                               FROM " . TABLE_ADDRESS_BOOK . "
                               WHERE customers_id = " . (int)$customer_id . "
                               AND entry_street_address = '" . zen_db_input($shipping_address['address1']) . "'
                               AND entry_postcode = '" . zen_db_input($shipping_address['postalCode']) . "'
                               AND entry_city = '" . zen_db_input($shipping_address['locality']) . "'
                               AND entry_suburb = '" . zen_db_input($shipping_address['address2']) . "'
                               AND entry_country_id = (SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($shipping_address['countryCode']) . "')");

// If the address exists, use it. Otherwise, create a new address
if ($address_query->RecordCount() > 0) {
    $address_id = $address_query->fields['address_book_id'];
} else {
    // Address does not exist, create it
    $db->Execute("INSERT INTO " . TABLE_ADDRESS_BOOK . " (customers_id, entry_firstname, entry_lastname, entry_street_address,
                  entry_suburb, entry_postcode, entry_city, entry_country_id, entry_zone_id)
                  VALUES (" . (int)$customer_id . ", '" . zen_db_input($shipping_first_name ?? '') . "', '" . zen_db_input($shipping_last_name ?? '') . "', '" . zen_db_input($shipping_address['address1'] ?? '') . "',
                          '" . zen_db_input($shipping_address['address2'] ?? '') . "', '" . zen_db_input($shipping_address['postalCode'] ?? '') . "', '" . zen_db_input($shipping_address['locality'] ?? '') . "',
                          (SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($shipping_address['countryCode'] ?? '') . "'),
                          " . (int)braintree_lookup_zone_id($shipping_address['administrativeArea'] ?? '', $shipping_address['countryCode'] ?? '', $db) . ")");
    $address_id = $db->Insert_ID();
}

// Check if the billing address already exists in the database
$billing_address_query = $db->Execute("SELECT address_book_id
                                      FROM " . TABLE_ADDRESS_BOOK . "
                                      WHERE customers_id = " . (int)$customer_id . "
                                      AND entry_street_address = '" . zen_db_input($billing_address['address1']) . "'
                                      AND entry_postcode = '" . zen_db_input($billing_address['postalCode']) . "'
                                      AND entry_city = '" . zen_db_input($billing_address['locality']) . "'
                                      AND entry_suburb = '" . zen_db_input($billing_address['address2']) . "'
                                      AND entry_country_id = (SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($billing_address['countryCode']) . "')");

// If the billing address exists, use it. Otherwise, create a new address
if ($billing_address_query->RecordCount() > 0) {
    $billing_address_id = $billing_address_query->fields['address_book_id'];
} else {
    // Billing address does not exist, create it
    $db->Execute("INSERT INTO " . TABLE_ADDRESS_BOOK . " (customers_id, entry_firstname, entry_lastname, entry_street_address,
                  entry_suburb, entry_postcode, entry_city, entry_country_id, entry_zone_id)
                  VALUES (" . (int)$customer_id . ", '" . zen_db_input($billing_first_name ?? '') . "', '" . zen_db_input($billing_last_name ?? '') . "', '" . zen_db_input($billing_address['address1'] ?? '') . "',
                          '" . zen_db_input($billing_address['address2'] ?? '') . "', '" . zen_db_input($billing_address['postalCode'] ?? '') . "', '" . zen_db_input($billing_address['locality'] ?? '') . "',
                          (SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($billing_address['countryCode'] ?? '') . "'),
                          " . (int)braintree_lookup_zone_id($billing_address['administrativeArea'] ?? '', $billing_address['countryCode'] ?? '', $db) . ")");
    $billing_address_id = $db->Insert_ID();
}

log_paypalr_wallet_message("Billing address ID: $billing_address_id");
log_paypalr_wallet_message("Shipping address ID: $address_id");

// Set session variables for the addresses
$_SESSION['sendto'] = $address_id;  // Shipping address
$_SESSION['billto'] = ($address_id == $billing_address_id) ? $address_id : $billing_address_id;  // Billing address (use the same if they match)
$_SESSION['customer_default_address_id'] = $billing_address_id;

// Set the default address IDs for the customer
$db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_default_address_id = $billing_address_id WHERE customers_id = $customer_id");

// If the column 'customers_default_shipping_address_id' exists, set it to the shipping address ID
$check_default_shipping = $db->Execute("SHOW COLUMNS FROM " . TABLE_CUSTOMERS . " LIKE 'customers_default_shipping_address_id'");
if ($check_default_shipping->RecordCount() > 0) {
    $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_default_shipping_address_id = $address_id WHERE customers_id = $customer_id");
}

/*
$language_page_directory = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';
$template_language_file = $language_page_directory . $template_dir . '/' . FILENAME_CHECKOUT_PROCESS . '.php';
$master_language_file   = $language_page_directory . FILENAME_CHECKOUT_PROCESS . '.php';

if (isset($template_dir) && !empty($template_dir) && file_exists($template_language_file)) {
  require_once($template_language_file);
} else {
  require_once($master_language_file);
}
*/

// Allow PayPal redirect-to-checkout flow to store session data and exit early
// not currently used by any braintree module
if (!empty($payload['redirect_to_checkout']) && $payload['redirect_to_checkout']) {
    global $messageStack;
    $messageStack->add_session('header', 'Your payment was not processed. Please complete checkout.', 'error');
    echo json_encode([
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')
    ]);
    exit;
}

// If configured, allow Apple Pay transactions to redirect to checkout confirmation
if (
    $module === 'paypalr_applepay' &&
    defined('MODULE_PAYMENT_PAYPALR_APPLE_PAY_CONFIRM_REDIRECT') &&
    MODULE_PAYMENT_PAYPALR_APPLE_PAY_CONFIRM_REDIRECT === 'True'
) {
    // set payment info for the upcoming confirmation page
    $_SESSION['payment'] = $_POST['payment'] = 'paypalr_applepay';
    $_POST['payment_method_nonce'] = $_SESSION['payment_method_nonce'];
    if (!empty($_SESSION['shipping']['id'])) {
        $_POST['shipping'] = $_SESSION['shipping']['id'];
    }

    echo json_encode([
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL')
    ]);
    exit;
}

if (!isset($credit_covers)) {
    $credit_covers = false;
}

$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEGIN');

$payment_modules = new payment($_SESSION['payment']);
$order = new order();
$shipping_modules = new shipping($_SESSION['shipping'] ?? '');

if (sizeof($order->products) < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
    exit;
}

$order_total_modules = new order_total();

if (isset($_SESSION['cart']->cartID) && $_SESSION['cartID']) {
    if ($_SESSION['cart']->cartID != $_SESSION['cartID']) {
        $payment_modules->clear_payment();
        $order_total_modules->clear_posts();
        unset($_SESSION['payment'], $_SESSION['shipping']);
        echo json_encode(['status' => 'error', 'message' => 'Session verification failed. Please reload and try again.']);
        exit;
    }
}

$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PRE_CONFIRMATION_CHECK');
if (empty($_SESSION['payment']) || strpos($GLOBALS[$_SESSION['payment']]->code ?? '', 'paypal') !== 0) {
    $order_totals = $order_total_modules->pre_confirmation_check();
}

if (method_exists($payment_modules, 'checkCreditCovered')) {
    $payment_modules->checkCreditCovered();
}

if ($credit_covers === true) {
    $order->info['payment_method'] = $order->info['payment_module_code'] = '';
}

$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS');
$order_totals = $order_total_modules->process();
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS');

if (!isset($_SESSION['payment']) && $credit_covers === false) {
    echo json_encode(['status' => 'error', 'message' => 'Payment session expired.']);
    exit;
}

$payment_modules->before_process();
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_BEFOREPROCESS');

$insert_id = $order->create($order_totals);
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE', $insert_id);
$payment_modules->after_order_create($insert_id);
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE', $insert_id);
$order->create_add_products($insert_id);
$_SESSION['order_number_created'] = $insert_id;
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS', $insert_id, $order);
$order->send_order_email($insert_id, 2);
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL', $insert_id, $order);

if (isset($_SESSION['payment_attempt'])) unset($_SESSION['payment_attempt']);

// replicate order-summary data for success page/affiliates
$oshipping = $otax = $ototal = $order_subtotal = $credits_applied = 0;
for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
    if ($order_totals[$i]['code'] === 'ot_subtotal') $order_subtotal = $order_totals[$i]['value'];
    if (!empty(${$order_totals[$i]['code']}->credit_class)) $credits_applied += $order_totals[$i]['value'];
    if ($order_totals[$i]['code'] === 'ot_total') $ototal = $order_totals[$i]['value'];
    if ($order_totals[$i]['code'] === 'ot_tax') $otax = $order_totals[$i]['value'];
    if ($order_totals[$i]['code'] === 'ot_shipping') $oshipping = $order_totals[$i]['value'];
}
$commissionable_order = ($order_subtotal - $credits_applied);
$commissionable_order_formatted = $currencies->format($commissionable_order);
$_SESSION['order_summary']['order_number'] = $insert_id;
$_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
$_SESSION['order_summary']['credits_applied'] = $credits_applied;
$_SESSION['order_summary']['order_total'] = $ototal;
$_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
$_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
$_SESSION['order_summary']['coupon_code'] = urlencode($order->info['coupon_code']);
$_SESSION['order_summary']['currency_code'] = $order->info['currency'];
$_SESSION['order_summary']['currency_value'] = $order->info['currency_value'];
$_SESSION['order_summary']['payment_module_code'] = $order->info['payment_module_code'];
$_SESSION['order_summary']['shipping_method'] = $order->info['shipping_method'];
$_SESSION['order_summary']['order_status'] = $order->info['order_status'];
$_SESSION['order_summary']['orders_status'] = $order->info['order_status'];
$_SESSION['order_summary']['tax'] = $otax;
$_SESSION['order_summary']['shipping'] = $oshipping;
$products_array = array();
foreach ($order->products as $key => $val) {
    $products_array[urlencode($val['id'])] = urlencode($val['model']);
}
$_SESSION['order_summary']['products_ordered_ids'] = implode('|', array_keys($products_array));
$_SESSION['order_summary']['products_ordered_models'] = implode('|', array_values($products_array));
$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES');

// load the after_process function from the payment modules
$payment_modules->after_process();

$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_CART_RESET', $insert_id);
$_SESSION['cart']->reset(true);

if (isset($_SESSION['cart_backup'])) {
    $_SESSION['cart'] = $_SESSION['cart_backup'];
    unset($_SESSION['cart_backup']);
}

// unregister session variables used during checkout
unset($_SESSION['sendto']);
unset($_SESSION['billto']);
unset($_SESSION['shipping']);
unset($_SESSION['payment']);
unset($_SESSION['comments']);
$order_total_modules->clear_posts();//ICW ADDED FOR CREDIT CLASS SYSTEM

// This should be before the zen_redirect:
$zco_notifier->notify('NOTIFY_HEADER_END_CHECKOUT_PROCESS', $insert_id);

// Prepare the response
$response = ['status' => 'success', 'order_id' => $insert_id];
log_paypalr_wallet_message("Order created: $insert_id");

switch ($module) {
    case 'paypalr_venmo':
    case 'braintree_api':
    case 'paypalr_googlepay':
    case 'paypalr_applepay':
        // For Google Pay, include the redirect_url to FILENAME_CHECKOUT_SUCCESS
        $response['redirect_url'] = zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
        log_paypalr_wallet_message("Checkout redirect URL: " . $response['redirect_url']);
        break;
    default:
        $response['status'] = 'error';
        $response['message'] = 'Unsupported payment module';
        log_paypalr_wallet_message("Unsupported module in handler: $module");
        break;
}

// Send response to client
# Ensure non-AJAX requests still end up on checkout_success instead of showing raw JSON.
# Non-AJAX requests expect a normal redirect so they don't see raw JSON.
if (!$isAjaxRequest && isset($response['redirect_url']) && $response['redirect_url'] !== '') {
    zen_redirect($response['redirect_url']);
    exit;
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

echo json_encode($response);
exit;
?>
