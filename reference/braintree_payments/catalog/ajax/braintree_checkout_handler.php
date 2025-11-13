<?php
// Initialize Zen Cart environment
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$_GET['main_page'] = $current_page_base = 'checkout_process';
$loaderPrefix = 'braintree_ajax';
require('includes/application_top.php');
require_once(DIR_WS_FUNCTIONS . 'braintree_functions.php');

define('LOG_FILE_PATH', DIR_FS_LOGS . 'braintree_handler.log');

// Capture the payload
$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload['payment_method_nonce']) || empty($payload['module'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

log_braintree_message('Received checkout payload: ' . print_r($payload, true));

// Ensure payload is valid
if (empty($payload['payment_method_nonce']) || empty($payload['total']) || empty($payload['module'])) {
    $response = ['status' => 'error', 'message' => 'Invalid payload'];
    echo json_encode($response);
    exit;
}

$module = $payload['module']; // Payment module (e.g., braintree_googlepay, braintree_applepay, etc.)

switch ($module) {
    case 'braintree_googlepay':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING';
        break;
    case 'braintree_applepay':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING';
        break;
    case 'braintree_paypal':
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING';
        break;
    default:
        $paymentModuleDebugConstant = 'MODULE_PAYMENT_BRAINTREE_DEBUGGING';
        break;
}

$_SESSION['payment_method_nonce'] = $_POST['payment_method_nonce'] = $payment_method_nonce = $payload['payment_method_nonce'];
$_SESSION['currency'] = $currency = $payload['currency'];
$_SESSION['payment'] = $module;
$total = $payload['total'];
$email = $payload['email'];
$shipping_address_raw = $payload['shipping_address'] ?? [];
$billing_address_raw  = $payload['billing_address'] ?? [];

// Normalize
$shipping_address = normalize_braintree_contact($shipping_address_raw, $module);
$billing_address  = normalize_braintree_contact($billing_address_raw, $module);

function incomplete_address_fields($addr) {
    $missing = [];
    $name_parts = array_filter(explode(' ', trim($addr['name'] ?? '')));

    if (count($name_parts) < 2)        $missing[] = 'name';
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
    log_braintree_message('Raw shipping address: ' . print_r($shipping_address_raw, true));
    log_braintree_message('Raw billing address: ' . print_r($billing_address_raw, true));
    if (!$shipping_complete && $shipping_required) {
        log_braintree_message('Missing shipping fields: ' . implode(', ', $shipping_errors));
    }
    if (!$billing_complete) {
        log_braintree_message('Missing billing fields: ' . implode(', ', $billing_errors));
    }
    log_braintree_message('Incomplete shipping or billing address. Redirecting to checkout.');
    $messageStack->add_session('header', 'Your payment was not processed. Please complete checkout.', 'error');
    echo json_encode([
        'status' => 'success',
        'redirect_url' => zen_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL')
    ]);
    exit;
}

if ($shipping_required && empty($_SESSION['shipping']['id'])) {
    global $messageStack;
    log_braintree_message('Missing shipping selection. Redirecting to checkout.');
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

log_braintree_message("Using payment nonce: $payment_method_nonce");
log_braintree_message("Billing address: " . print_r($billing_address, true));
log_braintree_message("Shipping address: " . print_r($shipping_address, true));

// Validate module
$valid_modules = ['braintree_api', 'braintree_googlepay', 'braintree_applepay', 'braintree_paypal'];
if (!in_array($module, $valid_modules)) {
    die('Invalid payment module');
}

// Determine if guest flow should be used
$guestModules = ['braintree_googlepay', 'braintree_applepay'];
$isGuestCheckout = in_array($module, $guestModules, true);

// Check if customer exists by email
$customer_query = $db->Execute("SELECT customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . zen_db_input($email) . "'");

if (!isset($_SESSION['customer_id'])) {
    if ($customer_query->RecordCount() > 0) {
        // If customer exists, use existing customer ID and set session variables for security
        $customer_id = $customer_query->fields['customers_id'];
    } else {
        // Create the new customer record (simplified for example purposes)
        $db->Execute("INSERT INTO " . TABLE_CUSTOMERS . " (customers_email_address, customers_firstname, customers_lastname)
                      VALUES ('" . zen_db_input($email ?? '') . "', '" . zen_db_input($billing_first_name ?? '') . "', '" . zen_db_input($billing_last_name ?? '') . "')");

        // Get the new customer ID
        $customer_id = $db->Insert_ID();

        // If the COWOA_account column exists, set it to 1
        $check_cowoa_account = $db->Execute("SHOW COLUMNS FROM " . TABLE_CUSTOMERS . " LIKE 'COWOA_account'");
        if ($check_cowoa_account->RecordCount() > 0) {
            $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET COWOA_account = 1 WHERE customers_id = $customer_id");
        }

        // Insert into TABLE_CUSTOMERS_INFO
        $db->Execute("INSERT INTO " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_date_of_last_logon, customers_info_number_of_logons, customers_info_date_account_created, customers_info_date_account_last_modified)
                      VALUES ($customer_id, now(), 1, now(), now())");
    }
    // Set session variables based on whether guest checkout is used
    if ($is_guest_checkout) {
        $_SESSION['customer_guest_id'] = $customerId;
        $_SESSION['COWOA'] = true;
        $_SESSION['customer_loggedin_type'] = 'guest';

        if (in_array($module, ['braintree_googlepay', 'braintree_applepay'], true)) {
            $_SESSION['braintree_express_checkout'] = $module;
        }
    } else {
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_loggedin_type'] = "customer";
    }
} else {
    $customer_id = $_SESSION['customer_id'];
}

log_braintree_message("Customer ID: $customer_id");

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

log_braintree_message("Billing address ID: $billing_address_id");
log_braintree_message("Shipping address ID: $address_id");

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
    $module === 'braintree_applepay' &&
    defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_CONFIRM_REDIRECT') &&
    MODULE_PAYMENT_BRAINTREE_APPLE_PAY_CONFIRM_REDIRECT === 'True'
) {
    // set payment info for the upcoming confirmation page
    $_SESSION['payment'] = $_POST['payment'] = 'braintree_applepay';
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

$module_path = DIR_WS_MODULES . $template_dir . '/' . FILENAME_CHECKOUT_PROCESS . '.php';
if (!file_exists($module_path)) {
  $module_path = DIR_WS_MODULES . FILENAME_CHECKOUT_PROCESS . '.php';
}
require($module_path);

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
log_braintree_message("Order created: $insert_id");

switch ($module) {
    case 'braintree_paypal':
    case 'braintree_api':
    case 'braintree_googlepay':
    case 'braintree_applepay':
        // For Google Pay, include the redirect_url to FILENAME_CHECKOUT_SUCCESS
        $response['redirect_url'] = zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
        log_braintree_message("Checkout redirect URL: " . $response['redirect_url']);
        break;
    default:
        $response['status'] = 'error';
        $response['message'] = 'Unsupported payment module';
        log_braintree_message("Unsupported module in handler: $module");
        break;
}

// Send response to client
echo json_encode($response);
exit;
?>