<?php
// Initialize Zen Cart environment
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'braintree_ajax'; // or similar unique name
$loaderPrefix = 'braintree_ajax';
require('includes/application_top.php');
require_once(DIR_WS_CLASSES . 'currencies.php');
require_once(DIR_WS_CLASSES . 'order.php');
require_once(DIR_WS_CLASSES . 'shipping.php');
require_once(DIR_WS_CLASSES . 'order_total.php');
require_once(DIR_WS_FUNCTIONS . 'braintree_functions.php');

define('LOG_FILE_PATH', DIR_FS_LOGS . 'braintree_handler.log');

header('Content-Type: application/json');

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$module = isset($data['module']) ? $data['module'] : 'braintree_api';
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

log_braintree_message('Received data: ' . print_r($data, true), false);

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
log_braintree_message("Parsed shipping address: countryCode=$countryCode, postalCode=$postalCode, locality=$locality, administrativeArea=$administrativeArea");

// Validate that we have the necessary shipping address data
if (empty($countryCode) || empty($postalCode) || empty($locality)) {
    http_response_code(400);
    $error_message = 'Invalid or incomplete shipping address.';
    log_braintree_message('Error: ' . $error_message);
    echo json_encode(['error' => $error_message]);
    exit;
}

// Retrieve country details
$country_query = "SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($countryCode) . "'";
$country = $db->Execute($country_query);
if ($country->RecordCount() == 0) {
    http_response_code(400);
    $error_message = 'Invalid country code.';
    log_braintree_message('Error: ' . $error_message);
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
    log_braintree_message("Only one zone found for country $countryCode, using zone_id=$zone_id");
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
        log_braintree_message("Matched zone for $administrativeArea: zone_id=$zone_id");
    } else {
        log_braintree_message("No matching zone for administrativeArea=$administrativeArea");
    }
}

// Set session variables for the shipping
$_SESSION['cart_country_id'] = $country_id;
$_SESSION['country_info'] = zen_get_countries($_SESSION['cart_country_id'],true);
$_SESSION['cart_zone'] = $zone_id;
$_SESSION['cart_postcode'] = $_SESSION['cart_zip_code'] = $postalCode;

log_braintree_message('Session shipping vars: ' . print_r([
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
$order->info = array(
    'subtotal' => $_SESSION['cart']->show_total(),
    'currency' => $_SESSION['currency'],
    'currency_value' => $currencies->currencies[$_SESSION['currency']]['value'] ?? 1,
);

// Required by shipping quote logic
$total_weight = $_SESSION['cart']->show_weight();
$shipping_estimator_display_weight = $total_weight;
$total_count = $_SESSION['cart']->count_contents();

// Quote shipping options based on delivery
$shipping_modules = new shipping();
$quotes = $shipping_modules->quote();

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
$shippingOptions = [];
foreach ($quotes as $quote) {
    if (!empty($quote['error']) || empty($quote['methods'])) continue;

    foreach ($quote['methods'] as $method) {
        $optionId = "{$quote['id']}_{$method['id']}";
        $baseCost = (float)($method['cost'] ?? 0);
        $taxRate = (float)($quote['tax'] ?? 0);
        $taxInclusiveCost = (float)braintree_calculate_tax_inclusive_amount($baseCost, $taxRate);
        if (function_exists('zen_round')) {
            $taxInclusiveCost = (float)zen_round($taxInclusiveCost, 2);
        } else {
            $taxInclusiveCost = round($taxInclusiveCost, 2);
        }

        $shippingOptions[] = [
            'id' => $optionId,
            'label' => html_entity_decode(strip_tags("{$quote['module']} ({$_SESSION['currency']} " . number_format($currencies->value($taxInclusiveCost), 2) . ")")),
            'description' => html_entity_decode(strip_tags($method['title'] ?? '')),
            'price' => number_format($taxInclusiveCost, 2, '.', '')
        ];

        if ($optionId === $selectedShippingOption) {
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
        }
    }
}

// Provide shipping to order total modules
global $shipping;
$shipping = $_SESSION['shipping'];

log_braintree_message('Shipping options returned: ' . print_r($shippingOptions, true));

// Now rebuild the $order object (with shipping already set)
$order = new order();
$order->info['shipping_cost'] = $_SESSION['shipping']['cost'];
$order->info['shipping_method'] = $_SESSION['shipping']['module'] . " (" . $_SESSION['shipping']['title'] . ")";
$order->info['shipping_module_code'] = $_SESSION['shipping']['id'];

// Recalculate order totals
$order_total_modules = new order_total();
$order_totals = $order_total_modules->process();

// Extract displayItems and calculate the order total manually so that
// shipping is always included even when discounts/coupons are present
$displayItems = [];
$calculatedTotal = (float)$order->info['subtotal'];
$shippingAddedToTotal = false;

foreach ($order_totals as $ot) {
    $type = 'LINE_ITEM';
    $value = $ot['value'];
    if ($ot['code'] === 'ot_subtotal') {
        $type = 'SUBTOTAL';
    } elseif ($ot['code'] === 'ot_shipping') {
        $calculatedTotal += $value;
        $shippingAddedToTotal = true;
        $displayItems[] = [
            'label' => 'Shipping',
            'type' => 'LINE_ITEM',
            'price' => number_format($currencies->value($value), 2, '.', ''),
            'status' => ($value > 0 ? 'FINAL' : 'PENDING'),
        ];
        continue;
    } elseif ($ot['code'] === 'ot_tax') {
        $type = 'TAX';
        $calculatedTotal += $value;
    } elseif (!empty($GLOBALS[$ot['code']]->deduction) || !empty($GLOBALS[$ot['code']]->credit_class)) {
        $type = 'DISCOUNT';
        $value = -abs($value);
        $calculatedTotal += $value;
    } elseif ($ot['code'] === 'ot_total') {
        // ignore built-in total; we're computing it manually
        continue;
    } else {
        $calculatedTotal += $value;
    }

    $displayItems[] = [
        'label' => strip_tags($ot['title']),
        'type' => $type,
        'price' => number_format($currencies->value($value), 2, '.', ''),
    ];
}

if (!$shippingAddedToTotal) {
    $calculatedTotal += $_SESSION['shipping']['cost'] ?? 0;
}

// Final total for Google Pay
$finalTotal = number_format($currencies->value($calculatedTotal), 2, '.', '');


// build final response
switch ($module) {
    case 'braintree_googlepay':
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

    case 'braintree_applepay':
        // Convert values to Apple Pay format
        $appleShippingMethods = [];
        foreach ($quotes as $quote) {
            if (!empty($quote['error']) || empty($quote['methods'])) continue;

            foreach ($quote['methods'] as $method) {
                $optionId = "{$quote['id']}_{$method['id']}";
                $baseCost = (float)($method['cost'] ?? 0);
                $taxRate = (float)($quote['tax'] ?? 0);
                $taxInclusiveCost = (float)braintree_calculate_tax_inclusive_amount($baseCost, $taxRate);
                if (function_exists('zen_round')) {
                    $taxInclusiveCost = (float)zen_round($taxInclusiveCost, 2);
                } else {
                    $taxInclusiveCost = round($taxInclusiveCost, 2);
                }

                $appleShippingMethods[] = [
                    'label' => "{$quote['module']} - {$method['title']}",
                    'amount' => number_format($taxInclusiveCost, 2, '.', ''),
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
    case 'braintree_paypal':
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