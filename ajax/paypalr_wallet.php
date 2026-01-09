<?php
// Initialize Zen Cart environment
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'paypalr_wallet_ajax'; // or similar unique name
$loaderPrefix = 'paypalr_wallet_ajax';
require('includes/application_top.php');
require_once(DIR_WS_CLASSES . 'currencies.php');
require_once(DIR_WS_CLASSES . 'order.php');
require_once(DIR_WS_CLASSES . 'shipping.php');
require_once(DIR_WS_CLASSES . 'order_total.php');
require_once(DIR_WS_FUNCTIONS . 'paypalr_functions.php');

define('LOG_FILE_PATH', DIR_FS_LOGS . '/paypalr_wallet_handler.log');

/**
 * Get a validated base currency code
 * Ensures the currency exists in the Zen Cart currencies array
 * Falls back to USD or first available currency if needed
 * 
 * Side effects: May log warning or error messages if fallback is needed
 * 
 * @param object $currencies The Zen Cart currencies object with a 'currencies' property
 * @param string|null $preferredCurrency The preferred currency code to use, or null
 * @return string A valid currency code that exists in the currencies array
 */
function get_validated_base_currency($currencies, $preferredCurrency = null) {
    // Try preferred currency first
    if ($preferredCurrency !== null && isset($currencies->currencies[$preferredCurrency])) {
        return $preferredCurrency;
    }
    
    // Try DEFAULT_CURRENCY
    if (defined('DEFAULT_CURRENCY') && isset($currencies->currencies[DEFAULT_CURRENCY])) {
        return DEFAULT_CURRENCY;
    }
    
    // Try USD as fallback
    if (isset($currencies->currencies['USD'])) {
        log_paypalr_wallet_message('WARNING: DEFAULT_CURRENCY not available, falling back to USD');
        return 'USD';
    }
    
    // Last resort: use first available currency
    $availableCurrencies = array_keys($currencies->currencies);
    if (!empty($availableCurrencies)) {
        $fallbackCurrency = $availableCurrencies[0];
        log_paypalr_wallet_message('WARNING: Neither DEFAULT_CURRENCY nor USD available, using ' . $fallbackCurrency);
        return $fallbackCurrency;
    }
    
    // This should never happen in a properly configured Zen Cart
    log_paypalr_wallet_message('ERROR: No currencies available in Zen Cart configuration!');
    return 'USD'; // Return USD as absolute last resort even if not configured
}

header('Content-Type: application/json');

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Basic session validation so we can gracefully fail instead of throwing PHP errors
if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
    http_response_code(440); // login-timeout / session expired
    log_paypalr_wallet_message('Session/cart object missing when accessing ajax/braintree.php');
    echo json_encode(['error' => 'Session expired. Please reload the page and try again.']);
    exit;
}
if (empty($_SESSION['currency'])) {
    $_SESSION['currency'] = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';
}

// Initialize session variables for guest checkout to prevent undefined variable warnings
// These would normally be set for logged-in customers
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['customer_id'] = 0; // Guest customer
}
if (!isset($_SESSION['customers_authorization'])) {
    $_SESSION['customers_authorization'] = 0; // Normal authorization
}

$displayCurrency = $_SESSION['currency'];
$displayCurrencyValue = $currencies->currencies[$displayCurrency]['value'] ?? 1;
log_paypalr_wallet_message('Display currency: ' . $displayCurrency . ', currency value: ' . $displayCurrencyValue);

$module = isset($data['module']) ? $data['module'] : 'braintree_api';
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

log_paypalr_wallet_message('Received data: ' . print_r($data, true), false);

// Dynamically fetch store's ISO country code
$country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
$country_result = $db->Execute($country_query);
$storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';  // Default to US if not found

// Extract shipping address details
$shippingAddress = normalize_braintree_contact($data['shippingAddress'], $module) ?? [];
$countryName = $shippingAddress['country'] ?? '';
$countryCode = $shippingAddress['countryCode'] ?? '';
$postalCode = $shippingAddress['postalCode'] ?? '';
$locality = $shippingAddress['locality'] ?? '';
$administrativeArea = $shippingAddress['administrativeArea'] ?? '';

// Log parsed address info
log_paypalr_wallet_message("Parsed shipping address: countryCode=$countryCode, postalCode=$postalCode, locality=$locality, administrativeArea=$administrativeArea");

// Validate that we have the necessary shipping address data
if (empty($countryCode) || empty($postalCode) || empty($locality)) {
    http_response_code(400);
    $error_message = 'Invalid or incomplete shipping address.';
    log_paypalr_wallet_message('Error: ' . $error_message);
    echo json_encode(['error' => $error_message]);
    exit;
}

// Retrieve country details
$country_query = "SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($countryCode) . "'";
$country = $db->Execute($country_query);
if ($country->RecordCount() == 0) {
    http_response_code(400);
    $error_message = 'Invalid country code.';
    log_paypalr_wallet_message('Error: ' . $error_message);
    echo json_encode(['error' => $error_message]);
    exit;
}

$country_id = $country->fields['countries_id'];

// Determine zone_id from administrativeArea or use fallback if only one zone exists
$zone_id = 0;

// Count number of zones for this country
$zone_count_query = $db->Execute("
    SELECT COUNT(*) AS total
    FROM " . TABLE_ZONES . "
    WHERE zone_country_id = " . (int)$country_id
);
$zone_count = (int)$zone_count_query->fields['total'];

if ($zone_count === 1) {
    // If only one zone exists, return it
    $single_zone_query = $db->Execute("
        SELECT zone_id FROM " . TABLE_ZONES . "
        WHERE zone_country_id = " . (int)$country_id . "
        LIMIT 1
    ");
    $zone_id = (int)$single_zone_query->fields['zone_id'];
    //if ($countryName != '') $administrativeArea = $countryName;
    log_paypalr_wallet_message("Only one zone found for country $countryCode, using zone_id=$zone_id");
} elseif (!empty($administrativeArea)) {
    // Try to match the provided administrativeArea
    $zone_query = $db->Execute("
        SELECT zone_id FROM " . TABLE_ZONES . "
        WHERE zone_country_id = " . (int)$country_id . "
        AND (
            zone_code = '" . zen_db_input($administrativeArea) . "'
            OR zone_name LIKE '%" . zen_db_input($administrativeArea) . "%'
        )
        LIMIT 1
    ");
    if ($zone_query->RecordCount() > 0) {
        $zone_id = (int)$zone_query->fields['zone_id'];
        log_paypalr_wallet_message("Matched zone for $administrativeArea: zone_id=$zone_id");
    } else {
        log_paypalr_wallet_message("No matching zone for administrativeArea=$administrativeArea");
    }
}

// Set session variables for the shipping
$_SESSION['cart_country_id'] = $country_id;
$_SESSION['country_info'] = zen_get_countries($_SESSION['cart_country_id'],true);
$_SESSION['cart_zone'] = $zone_id;
$_SESSION['cart_postcode'] = $_SESSION['cart_zip_code'] = $postalCode;

log_paypalr_wallet_message('Session shipping vars: ' . print_r([
    'cart_country_id' => $_SESSION['cart_country_id'],
    'cart_zone' => $_SESSION['cart_zone'],
    'cart_postcode' => $_SESSION['cart_postcode'],
], true));

// Force Zen Cart to ignore customer's saved address
$_SESSION['sendto'] = false;
$_SESSION['shipping'] = null;
$_SESSION['shipping_weight'] = null;
$_SESSION['shipping_num_boxes'] = null;

// Manually build a minimal $order object so shipping modules can use it
// Keep in DEFAULT_CURRENCY for proper Zen Cart calculations
global $order;
$order = new stdClass();
$order->delivery = array(
    'postcode' => $postalCode,
    'city' => $locality,
    'state' => $administrativeArea,
    'country' => array(
        'id' => $country_id,
        'title' => $_SESSION['country_info']['countries_name'],
        'iso_code_2' => $_SESSION['country_info']['countries_iso_code_2'],
        'iso_code_3' => $_SESSION['country_info']['countries_iso_code_3'],
    ),
    'country_id' => $country_id,
    'zone_id' => $zone_id,
    'format_id' => zen_get_address_format_id($country_id),
);
// Use DEFAULT_CURRENCY for calculations - cart->show_total() returns base currency value
// Get validated base currency (with fallback if DEFAULT_CURRENCY not properly configured)
$baseCurrency = get_validated_base_currency($currencies, defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : null);
$order->info = array(
    'subtotal' => $_SESSION['cart']->show_total(),
    'currency' => $baseCurrency,
    'currency_value' => $currencies->currencies[$baseCurrency]['value'] ?? 1,
);

log_paypalr_wallet_message('Initial order object created in base currency: ' . $baseCurrency . 
    ', subtotal: ' . $order->info['subtotal']);

// Required by shipping quote logic
$total_weight = $_SESSION['cart']->show_weight();
$shipping_estimator_display_weight = $total_weight;
$total_count = $_SESSION['cart']->count_contents();

// Quote shipping options based on delivery
$shipping_modules = new shipping();
$quotes = $shipping_modules->quote();

// Debug: Log raw shipping quotes
log_paypalr_wallet_message('Raw shipping quotes returned: ' . print_r($quotes, true));

if (!function_exists('braintree_calculate_tax_inclusive_amount')) {
    function braintree_calculate_tax_inclusive_amount($baseAmount, $taxRate)
    {
        $baseAmount = (float)$baseAmount;
        $taxRate = (float)$taxRate;

        if ($taxRate == 0.0) {
            return $baseAmount;
        }

        if (function_exists('zen_add_tax')) {
            return (float)zen_add_tax($baseAmount, $taxRate);
        }

        return $baseAmount * (1 + ($taxRate / 100));
    }
}

// Determine selected shipping method
if (!isset($data['selectedShippingOptionId']) || empty($data['selectedShippingOptionId'])) {
    $cheapest = $shipping_modules->cheapest();
    if (!empty($cheapest) && !empty($cheapest['id'])) {
        $selectedShippingOption = $cheapest['id'];
    } elseif (!empty($quotes)) {
        // fallback: pick first valid shipping method as default
        foreach ($quotes as $quote) {
            if (!empty($quote['methods'])) {
                $firstMethod = $quote['methods'][0];
                $selectedShippingOption = "{$quote['id']}_{$firstMethod['id']}";
                break;
            }
        }
    } else {
        $selectedShippingOption = null;
    }
} else {
    $selectedShippingOption = $data['selectedShippingOptionId'];
}

// Populate shipping options and set the selected one
// Shipping costs from modules are in base currency
$shippingOptions = [];
foreach ($quotes as $quote) {
    if (!empty($quote['error']) || empty($quote['methods'])) continue;

    foreach ($quote['methods'] as $method) {
        $optionId = "{$quote['id']}_{$method['id']}";
        $baseCost = (float)($method['cost'] ?? 0);
        $taxRate = (float)($quote['tax'] ?? 0);
        
        // Calculate tax-inclusive cost in base currency
        $taxInclusiveCostBase = (float)braintree_calculate_tax_inclusive_amount($baseCost, $taxRate);
        if (function_exists('zen_round')) {
            $taxInclusiveCostBase = (float)zen_round($taxInclusiveCostBase, 2);
        } else {
            $taxInclusiveCostBase = round($taxInclusiveCostBase, 2);
        }

        // Convert to display currency for showing to customer
        $shippingDisplayAmount = $currencies->value($taxInclusiveCostBase, true, $displayCurrency);
        
        log_paypalr_wallet_message('Shipping option: ' . $optionId . 
            ', base cost: ' . $baseCost . 
            ', tax rate: ' . $taxRate . 
            ', tax-inclusive base: ' . $taxInclusiveCostBase . 
            ', display amount (' . $displayCurrency . '): ' . $shippingDisplayAmount);

        $shippingOptions[] = [
            'id' => $optionId,
            'label' => html_entity_decode(strip_tags("{$quote['module']} ({$displayCurrency} " . number_format($shippingDisplayAmount, 2) . ")")),
            'description' => html_entity_decode(strip_tags($method['title'] ?? '')),
            'price' => number_format($shippingDisplayAmount, 2, '.', '')
        ];

        if ($optionId === $selectedShippingOption) {
            // Store base cost for later calculations
            $_SESSION['shipping'] = [
                'id' => $optionId,
                'title' => $quote['module'],
                'module' => $method['title'],
                'cost' => $baseCost
            ];
            if (array_key_exists('tax', $quote)) {
                $_SESSION['shipping']['tax'] = $quote['tax'];
            }
            if (array_key_exists('tax_id', $quote)) {
                $_SESSION['shipping']['tax_id'] = $quote['tax_id'];
            }
            if (array_key_exists('tax_description', $quote)) {
                $_SESSION['shipping']['tax_description'] = $quote['tax_description'];
            }
            log_paypalr_wallet_message('Selected shipping method: ' . $optionId . 
                ', base cost: ' . $baseCost . 
                ', tax rate: ' . $taxRate . 
                ', tax-inclusive base: ' . $taxInclusiveCostBase .
                ', display amount (' . $displayCurrency . '): ' . $shippingDisplayAmount);
        }
    }
}

// Provide shipping to order total modules
global $shipping;
$shipping = $_SESSION['shipping'];

// Debug: Log the shipping session data used by order totals
log_paypalr_wallet_message('Shipping session data for order totals: ' . print_r($_SESSION['shipping'], true));

log_paypalr_wallet_message('Shipping options returned: ' . print_r($shippingOptions, true));

// Ensure tax calculations use the provided shipping address instead of any
// previously stored customer location. The order class relies on these
// session values when determining tax rates for products.
$_SESSION['customer_country_id'] = $country_id;
$_SESSION['customer_zone_id'] = $zone_id;

// Debug: Log customer session variables used for tax calculation
log_paypalr_wallet_message('Customer session variables for tax: customer_country_id=' . $country_id . ', customer_zone_id=' . $zone_id);

// Unset sendto/billto before creating the order to ensure proper tax calculation.
// When sendto is false (set earlier for shipping quotes), the order class can't properly
// calculate product taxes. By unsetting it, the order class falls back to using the customer
// location session variables (customer_country_id, customer_zone_id) set above. After order
// creation, the delivery/billing addresses are explicitly set, allowing ot_tax to calculate
// taxes correctly on both products and shipping.
if (isset($_SESSION['sendto'])) {
    unset($_SESSION['sendto']);
}
if (isset($_SESSION['billto'])) {
    unset($_SESSION['billto']);
}

// Now rebuild the $order object (with shipping already set)
// IMPORTANT: Keep the order in DEFAULT_CURRENCY for all Zen Cart calculations
// We'll convert to display currency after all tax and total calculations are complete
$order = new order();
// Do NOT override the currency - let Zen Cart use DEFAULT_CURRENCY for calculations
// $order->info['currency'] is already set to DEFAULT_CURRENCY by the order class
$order->info['shipping_cost'] = $_SESSION['shipping']['cost'];
$order->info['shipping_method'] = $_SESSION['shipping']['module'] . " (" . $_SESSION['shipping']['title'] . ")";
$order->info['shipping_module_code'] = $_SESSION['shipping']['id'];

// Initialize fields expected by order processing to prevent undefined key warnings
if (!isset($order->info['payment'])) {
    $order->info['payment'] = '';
}
if (!isset($order->info['applied_stock_reduction'])) {
    $order->info['applied_stock_reduction'] = false;
}

// Get the validated base currency - this will be used throughout order processing
$baseCurrency = get_validated_base_currency($currencies, $order->info['currency'] ?? null);
log_paypalr_wallet_message('Order object created in base currency: ' . $baseCurrency . 
    ' (will convert to display currency ' . $displayCurrency . ' after calculations)');

// Set billing and delivery addresses for tax calculation purposes
// Tax modules need both addresses to properly calculate taxes, including shipping taxes
// Parse name from shipping address
$fullName = trim($shippingAddress['name'] ?? '');
if ($fullName === '') {
    $firstName = '';
    $lastName = '';
} else {
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';
}

// Build the address array once and reuse for both billing and delivery
$addressData = array(
    'firstname' => $firstName,
    'lastname' => $lastName,
    'company' => '',
    'street_address' => $shippingAddress['address1'] ?? '',
    'suburb' => $shippingAddress['address2'] ?? '',
    'postcode' => $postalCode,
    'city' => $locality,
    'state' => $administrativeArea,
    'country' => array(
        'id' => $country_id,
        'title' => $_SESSION['country_info']['countries_name'] ?? '',
        'iso_code_2' => $_SESSION['country_info']['countries_iso_code_2'] ?? '',
        'iso_code_3' => $_SESSION['country_info']['countries_iso_code_3'] ?? '',
    ),
    'country_id' => $country_id,
    'zone_id' => $zone_id,
    'format_id' => zen_get_address_format_id($country_id),
);

$order->billing = $addressData;
$order->delivery = $addressData;

// Manually calculate and apply product taxes since the order class couldn't do it
// (no valid sendto address was available during construction)
if (isset($order->products) && is_array($order->products) && function_exists('zen_get_tax_rate')) {
    log_paypalr_wallet_message('Manually calculating product taxes for ' . count($order->products) . ' product(s)');
    
    // Collect product IDs that need tax class lookup
    $productIdsNeedingLookup = [];
    
    foreach ($order->products as $index => $product) {
        if (!isset($product['tax_class_id'])) {
            // Extract product ID from the format "product_id:attributes_hash"
            $productIdParts = explode(':', $product['id']);
            if (!empty($productIdParts[0])) {
                $productId = (int)$productIdParts[0];
                if ($productId > 0) {
                    $productIdsNeedingLookup[] = $productId;
                }
            }
        }
    }
    
    // Batch query for all tax classes if needed
    $taxClassMap = [];
    if (!empty($productIdsNeedingLookup)) {
        // Sanitize all product IDs to ensure they are integers
        $sanitizedIds = array_map('intval', $productIdsNeedingLookup);
        $productIdsList = implode(',', $sanitizedIds);
        $taxClassQuery = $db->Execute(
            "SELECT products_id, products_tax_class_id FROM " . TABLE_PRODUCTS . 
            " WHERE products_id IN (" . $productIdsList . ")"
        );
        while (!$taxClassQuery->EOF) {
            $taxClassMap[$taxClassQuery->fields['products_id']] = $taxClassQuery->fields['products_tax_class_id'];
            $taxClassQuery->MoveNext();
        }
    }
    
    // Calculate and apply taxes for each product
    foreach ($order->products as $index => $product) {
        // Get the tax class for this product
        $taxClassId = 0;
        if (isset($product['tax_class_id'])) {
            $taxClassId = $product['tax_class_id'];
        } else {
            // Use the batched tax class data
            $productIdParts = explode(':', $product['id']);
            if (!empty($productIdParts[0])) {
                $productId = (int)$productIdParts[0];
                if (isset($taxClassMap[$productId])) {
                    $taxClassId = $taxClassMap[$productId];
                }
            }
        }
        
        // Calculate the tax rate for this product based on the delivery address
        $taxRate = zen_get_tax_rate($taxClassId, $country_id, $zone_id);
        
        // Update the product tax information
        $order->products[$index]['tax'] = $taxRate;
        $order->products[$index]['tax_class_id'] = $taxClassId;
        
        // Get tax description if available
        if (function_exists('zen_get_tax_description')) {
            $order->products[$index]['tax_description'] = zen_get_tax_description($taxClassId, $country_id, $zone_id);
        }
        
        log_paypalr_wallet_message('  Product ' . ($index + 1) . ' (' . ($product['name'] ?? 'N/A') . '): tax_class_id=' . $taxClassId . ', tax_rate=' . $taxRate . '%');
    }
    
    log_paypalr_wallet_message('Product tax calculation complete');
}

// Debug: Log products in the order (after tax calculation)
if (isset($order->products) && is_array($order->products)) {
    log_paypalr_wallet_message('Order contains ' . count($order->products) . ' product(s):');
    foreach ($order->products as $index => $product) {
        log_paypalr_wallet_message('  Product ' . ($index + 1) . ': ' . print_r([
            'id' => $product['id'] ?? 'N/A',
            'name' => $product['name'] ?? 'N/A',
            'qty' => $product['qty'] ?? 'N/A',
            'price' => $product['price'] ?? 'N/A',
            'final_price' => $product['final_price'] ?? 'N/A',
            'tax' => $product['tax'] ?? 'N/A',
            'tax_class_id' => $product['tax_class_id'] ?? 'N/A',
            'tax_description' => $product['tax_description'] ?? 'N/A',
        ], true));
    }
} else {
    log_paypalr_wallet_message('Order products array is empty or not set');
}

// Debug: Log billing and delivery addresses
log_paypalr_wallet_message('Billing address set: ' . print_r([
    'firstname' => $addressData['firstname'] ?? 'N/A',
    'lastname' => $addressData['lastname'] ?? 'N/A',
    'street_address' => $addressData['street_address'] ?? 'N/A',
    'suburb' => $addressData['suburb'] ?? 'N/A',
    'city' => $addressData['city'] ?? 'N/A',
    'state' => $addressData['state'] ?? 'N/A',
    'postcode' => $addressData['postcode'] ?? 'N/A',
    'country_id' => $addressData['country_id'] ?? 'N/A',
    'zone_id' => $addressData['zone_id'] ?? 'N/A',
    'country_iso_code_2' => $addressData['country']['iso_code_2'] ?? 'N/A',
], true));
log_paypalr_wallet_message('Delivery address set: ' . print_r([
    'firstname' => $addressData['firstname'] ?? 'N/A',
    'lastname' => $addressData['lastname'] ?? 'N/A',
    'street_address' => $addressData['street_address'] ?? 'N/A',
    'suburb' => $addressData['suburb'] ?? 'N/A',
    'city' => $addressData['city'] ?? 'N/A',
    'state' => $addressData['state'] ?? 'N/A',
    'postcode' => $addressData['postcode'] ?? 'N/A',
    'country_id' => $addressData['country_id'] ?? 'N/A',
    'zone_id' => $addressData['zone_id'] ?? 'N/A',
    'country_iso_code_2' => $addressData['country']['iso_code_2'] ?? 'N/A',
], true));

// Recalculate order totals
// These totals are calculated in DEFAULT_CURRENCY (Zen Cart base currency)
// Debug: Verify product taxes are set before order_totals processes
if (isset($order->products) && is_array($order->products)) {
    log_paypalr_wallet_message('Product taxes before order_totals->process():');
    foreach ($order->products as $index => $product) {
        log_paypalr_wallet_message('  Product ' . ($index + 1) . ': ' . print_r([
            'tax' => $product['tax'] ?? 'N/A',
            'tax_class_id' => $product['tax_class_id'] ?? 'N/A',
            'final_price' => $product['final_price'] ?? 'N/A',
            'qty' => $product['qty'] ?? 'N/A',
        ], true));
    }
}
// Pre-calculate expected product and shipping taxes in base currency so we can
// ensure ot_tax reflects both components.
$productTaxBase = 0.0;
if (isset($order->products) && is_array($order->products)) {
    foreach ($order->products as $product) {
        $linePrice = isset($product['final_price']) ? (float)$product['final_price'] : (float)($product['price'] ?? 0);
        $lineQty = (float)($product['qty'] ?? 1);
        $taxRate = (float)($product['tax'] ?? 0);
        $productTaxBase += $linePrice * $lineQty * ($taxRate / 100);
    }
}

$shippingTaxBase = 0.0;
if (isset($_SESSION['shipping']['cost'], $_SESSION['shipping']['tax'])) {
    $shippingTaxBase = (float)$_SESSION['shipping']['cost'] * ((float)$_SESSION['shipping']['tax'] / 100);
}

log_paypalr_wallet_message('Order delivery address before order_totals->process(): ' . print_r([
    'country_id' => $order->delivery['country_id'] ?? 'N/A',
    'zone_id' => $order->delivery['zone_id'] ?? 'N/A',
], true));
$order_total_modules = new order_total();
// Call collect_posts() and pre_confirmation_check() before process() to ensure
// all order totals (including shipping) are properly initialized
$order_total_modules->collect_posts();
$order_total_modules->pre_confirmation_check();
$order_totals = $order_total_modules->process();

// If ot_tax only includes shipping tax, add the missing product tax so totals
// are accurate for the payment sheet.
foreach ($order_totals as &$ot) {
    if (($ot['code'] ?? '') !== 'ot_tax') {
        continue;
    }

    $existingTaxBase = (float)($ot['value'] ?? 0);
    $recordedProductTax = max($existingTaxBase - $shippingTaxBase, 0);
    $missingProductTax = $productTaxBase - $recordedProductTax;

    if ($missingProductTax > 0.0001) {
        $ot['value'] = $existingTaxBase + $missingProductTax;
        $order->info['tax'] = ($order->info['tax'] ?? 0) + $missingProductTax;
        log_paypalr_wallet_message('Product tax missing from ot_tax; adding ' . $missingProductTax . ' (base).');
    }
}
unset($ot);

// Debug: Log detailed order totals information
log_paypalr_wallet_message('Order totals calculated in base currency (' . $baseCurrency . '):');
foreach ($order_totals as $index => $ot) {
    log_paypalr_wallet_message('  Order total ' . ($index + 1) . ': ' . print_r([
        'code' => $ot['code'] ?? 'N/A',
        'title' => $ot['title'] ?? 'N/A',
        'text' => $ot['text'] ?? 'N/A',
        'value' => $ot['value'] ?? 'N/A',
        'sort_order' => $ot['sort_order'] ?? 'N/A',
    ], true));
}
log_paypalr_wallet_message('Order info after totals: subtotal=' . $order->info['subtotal'] . ', currency=' . $baseCurrency . ', shipping_cost=' . ($order->info['shipping_cost'] ?? 'N/A') . ', tax=' . ($order->info['tax'] ?? 'N/A') . ', total=' . ($order->info['total'] ?? 'N/A'));

// Extract displayItems and calculate the order total manually so that
// shipping is always included even when discounts/coupons are present
// All these values are in base currency and will be converted to display currency below
$displayItems = [];
$calculatedTotalBase = (float)$order->info['subtotal'];
$shippingAddedToTotal = false;
$totalTaxBase = 0; // Accumulate all tax amounts to create a single TAX display item
log_paypalr_wallet_message('Starting total calculation with subtotal in base currency (' . $baseCurrency . '): ' . $calculatedTotalBase);

foreach ($order_totals as $ot) {
    $type = 'LINE_ITEM';
    $valueBase = $ot['value']; // Value in base currency
    log_paypalr_wallet_message('Processing order total: code=' . $ot['code'] . ', base value=' . $valueBase . ', title=' . $ot['title']);
    
    if ($ot['code'] === 'ot_subtotal') {
        $type = 'SUBTOTAL';
    } elseif ($ot['code'] === 'ot_shipping') {
        $calculatedTotalBase += $valueBase;
        $shippingAddedToTotal = true;
        log_paypalr_wallet_message('Added shipping to total (base): ' . $valueBase . ', new total (base): ' . $calculatedTotalBase);
        // Convert to display currency for the payment gateway
        $valueDisplay = $currencies->value($valueBase, true, $displayCurrency);
        $displayItems[] = [
            'label' => 'Shipping',
            'type' => 'LINE_ITEM',
            'price' => number_format($valueDisplay, 2, '.', ''),
            'status' => ($valueDisplay > 0 ? 'FINAL' : 'PENDING'),
        ];
        continue;
    } elseif ($ot['code'] === 'ot_tax') {
        // Accumulate all tax amounts instead of adding each as a separate display item
        // This ensures payment gateways receive a single combined tax amount
        $totalTaxBase += $valueBase;
        $calculatedTotalBase += $valueBase;
        log_paypalr_wallet_message('Accumulated tax (base): ' . $valueBase . ', total tax so far (base): ' . $totalTaxBase . ', new total (base): ' . $calculatedTotalBase);
        continue; // Skip adding individual tax items to displayItems
    } elseif (!empty($GLOBALS[$ot['code']]->deduction) || !empty($GLOBALS[$ot['code']]->credit_class)) {
        $type = 'DISCOUNT';
        $valueBase = -abs($valueBase);
        $calculatedTotalBase += $valueBase;
        log_paypalr_wallet_message('Added discount to total (base): ' . $valueBase . ', new total (base): ' . $calculatedTotalBase);
    } elseif ($ot['code'] === 'ot_total') {
        // ignore built-in total; we're computing it manually
        log_paypalr_wallet_message('Skipping ot_total (calculated manually)');
        continue;
    } else {
        $calculatedTotalBase += $valueBase;
        log_paypalr_wallet_message('Added other order total to total (base): ' . $valueBase . ', new total (base): ' . $calculatedTotalBase);
    }

    // Convert to display currency for the payment gateway
    $valueDisplay = $currencies->value($valueBase, true, $displayCurrency);
    $displayItems[] = [
        'label' => strip_tags($ot['title']),
        'type' => $type,
        'price' => number_format($valueDisplay, 2, '.', ''),
    ];
}

// Add the accumulated tax as a single TAX display item
if ($totalTaxBase > 0) {
    $totalTaxDisplay = $currencies->value($totalTaxBase, true, $displayCurrency);
    $displayItems[] = [
        'label' => 'Tax',
        'type' => 'TAX',
        'price' => number_format($totalTaxDisplay, 2, '.', ''),
    ];
    log_paypalr_wallet_message('Added combined tax display item (base): ' . $totalTaxBase . ', display: ' . $totalTaxDisplay);
}

if (!$shippingAddedToTotal) {
    $calculatedTotalBase += $_SESSION['shipping']['cost'] ?? 0;
    log_paypalr_wallet_message('Shipping was not in order_totals, adding from session (base): ' . ($_SESSION['shipping']['cost'] ?? 0) . ', new total (base): ' . $calculatedTotalBase);
}

// Now convert the final total from base currency to display currency
$calculatedTotalDisplay = $currencies->value($calculatedTotalBase, true, $displayCurrency);
$finalTotal = number_format($calculatedTotalDisplay, 2, '.', '');

log_paypalr_wallet_message('Currency conversion summary:');
log_paypalr_wallet_message('  Base currency: ' . $baseCurrency);
log_paypalr_wallet_message('  Display currency: ' . $displayCurrency);
log_paypalr_wallet_message('  Base currency rate: ' . ($currencies->currencies[$baseCurrency]['value'] ?? 1));
log_paypalr_wallet_message('  Display currency rate: ' . $displayCurrencyValue);
log_paypalr_wallet_message('  Total in base currency (' . $baseCurrency . '): ' . $calculatedTotalBase);
log_paypalr_wallet_message('  Total in display currency (' . $displayCurrency . '): ' . $calculatedTotalDisplay);
log_paypalr_wallet_message('Final total formatted for payment: ' . $finalTotal);
log_paypalr_wallet_message('Display items: ' . print_r($displayItems, true));


// build final response
switch ($module) {
    case 'paypalr_googlepay':
        // Google Pay expects this exact structure
        $response = [
            'newTransactionInfo' => [
                'displayItems' => $displayItems,
                'countryCode' => $storeCountryCode,
                'currencyCode' => $_SESSION['currency'],
                'totalPriceStatus' => 'FINAL',
                'totalPrice' => $finalTotal,
                'totalPriceLabel' => 'Total',
            ],
            'newShippingOptionParameters' => [
                'shippingOptions' => $shippingOptions,
                'defaultSelectedOptionId' => $selectedShippingOption,
            ],
        ];
        echo json_encode($response);
        break;

    case 'paypalr_applepay':
        // Convert values to Apple Pay format
        // Shipping costs are in base currency, need to convert to display currency
        $appleShippingMethods = [];
        foreach ($quotes as $quote) {
            if (!empty($quote['error']) || empty($quote['methods'])) continue;

            foreach ($quote['methods'] as $method) {
                $optionId = "{$quote['id']}_{$method['id']}";
                $baseCost = (float)($method['cost'] ?? 0);
                $taxRate = (float)($quote['tax'] ?? 0);
                
                // Calculate tax-inclusive cost in base currency
                $taxInclusiveCostBase = (float)braintree_calculate_tax_inclusive_amount($baseCost, $taxRate);
                if (function_exists('zen_round')) {
                    $taxInclusiveCostBase = (float)zen_round($taxInclusiveCostBase, 2);
                } else {
                    $taxInclusiveCostBase = round($taxInclusiveCostBase, 2);
                }

                // Convert to display currency for Apple Pay
                $appleShippingDisplayAmount = $currencies->value($taxInclusiveCostBase, true, $displayCurrency);

                $appleShippingMethods[] = [
                    'label' => "{$quote['module']} - {$method['title']}",
                    'amount' => number_format($appleShippingDisplayAmount, 2, '.', ''),
                    'identifier' => $optionId,
                    'detail' => strip_tags($method['title'])
                ];
            }
        }

        $appleLineItems = array_map(function($item) {
            return [
                'label' => $item['label'],
                'amount' => $item['price']
            ];
        }, $displayItems);

        $appleResponse = [
            'newShippingMethods' => $appleShippingMethods,
            'newTotal' => [
                'label' => 'Total',
                'amount' => $finalTotal
            ],
            'newLineItems' => $appleLineItems
        ];
        echo json_encode($appleResponse);
        break;
    case 'paypalr_venmo':
        echo json_encode([
            'amount' => [
                'currency_code' => $_SESSION['currency'],
                'value' => number_format((float)$finalTotal, 2, '.', '')
            ],
            'shipping_options' => array_map(function ($option) {
                return [
                    'id' => $option['id'],
                    'label' => $option['label'],
                    'type' => 'SHIPPING',
                    'amount' => [
                        'currency_code' => $_SESSION['currency'],
                        'value' => number_format((float)($option['price'] ?? 0), 2, '.', '')
                    ]
                ];
            }, $shippingOptions)
        ]);
        break;
    default:
        // Fallback (same as Google Pay)
        $response = [
            'newTransactionInfo' => [
                'displayItems' => $displayItems,
                'countryCode' => $storeCountryCode,
                'currencyCode' => $_SESSION['currency'],
                'totalPriceStatus' => 'FINAL',
                'totalPrice' => $finalTotal,
                'totalPriceLabel' => 'Total',
            ],
            'newShippingOptionParameters' => [
                'shippingOptions' => $shippingOptions,
                'defaultSelectedOptionId' => $selectedShippingOption,
            ],
        ];
        echo json_encode($response);
        break;
}

exit;
?>
