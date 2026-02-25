<?php

if (!function_exists('paypalac_wallet_load_language_file')) {
    function paypalac_wallet_load_language_file($basename)
    {
        $language = $_SESSION['language'] ?? 'english';
        $modernPath = DIR_WS_LANGUAGES . $language . '/lang.' . $basename . '.php';
        $legacyPath = DIR_WS_LANGUAGES . $language . '/' . $basename . '.php';

        if (file_exists($modernPath)) {
            $defines = include $modernPath;
            if (is_array($defines)) {
                foreach ($defines as $key => $value) {
                    if (!defined($key)) {
                        define($key, $value);
                    }
                }
            }
            return;
        }

        if (file_exists($legacyPath)) {
            include $legacyPath;
        }
    }
}

if (!function_exists('paypalac_wallet_checkout_log')) {
    function paypalac_wallet_checkout_log($message, $logFilePath = null)
    {
        $targetFile = $logFilePath ?: (defined('LOG_FILE_PATH') ? LOG_FILE_PATH : DIR_FS_LOGS . '/paypalac_wallet_handler.log');
        if (!is_dir(dirname($targetFile))) {
            @mkdir(dirname($targetFile), 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($targetFile, $line, FILE_APPEND);
    }
}

function paypalac_wallet_lookup_zone_id($zone_name, $country_code, $db) {
    $zone_name = trim($zone_name);
    $zone_id = 0;

    // Get country ID
    $country_query = $db->Execute("SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . zen_db_input($country_code) . "'");
    if ($country_query->RecordCount() == 0) return 0;
    $country_id = (int)$country_query->fields['countries_id'];

    // Count zones for this country
    $zone_count_query = $db->Execute("
        SELECT COUNT(*) AS total
        FROM " . TABLE_ZONES . "
        WHERE zone_country_id = " . $country_id
    );
    $zone_count = (int)$zone_count_query->fields['total'];

    if ($zone_count === 1) {
        // If only one zone, return it
        $single_zone_query = $db->Execute("
            SELECT zone_id FROM " . TABLE_ZONES . "
            WHERE zone_country_id = " . $country_id . "
            LIMIT 1
        ");
        $zone_id = (int)$single_zone_query->fields['zone_id'];
    } elseif ($zone_name !== '') {
        // Attempt match by zone code or partial zone name
        $zone_query = $db->Execute("
            SELECT zone_id FROM " . TABLE_ZONES . "
            WHERE zone_country_id = " . $country_id . "
            AND (
                zone_code = '" . zen_db_input($zone_name) . "'
                OR zone_name LIKE '%" . zen_db_input($zone_name) . "%'
            )
            LIMIT 1
        ");
        if ($zone_query->RecordCount() > 0) {
            $zone_id = (int)$zone_query->fields['zone_id'];
        }
    }

    return $zone_id;
}

function log_paypalac_wallet_message($message, $append = true) {
    global $paymentModuleDebugConstant;

    // Check the appropriate debug constant for this payment module
    if (!(isset($paymentModuleDebugConstant) &&
         defined($paymentModuleDebugConstant) &&
         (constant($paymentModuleDebugConstant) === 'Log File' || constant($paymentModuleDebugConstant) === 'Log and Email'))) {
        return;
    }

    if (!file_exists(DIR_FS_LOGS)) {
        mkdir(DIR_FS_LOGS, 0755, true);
    }

    $formatted_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $flags = $append ? FILE_APPEND : 0;
    file_put_contents(LOG_FILE_PATH, $formatted_message, $flags);
}

function paypalac_wallet_normalize_contact_value($value) {
    if (!is_string($value)) {
        return $value;
    }

    $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    // Normalize any escaped apostrophes and curly quotes to straight apostrophes.
    $decoded = str_replace("\\'", "'", $decoded);
    $decoded = str_replace(['’', '‘'], "'", $decoded);

    return trim($decoded);
}

function paypalac_wallet_sanitize_contact_array(&$contact) {
    if (!is_array($contact)) {
        return;
    }

    array_walk_recursive($contact, function (&$value) {
        if (is_string($value)) {
            $value = paypalac_wallet_normalize_contact_value($value);
        }
    });
}

function normalize_paypalac_wallet_contact($contact, $module = '') {
    if (!is_array($contact)) return $contact;

    paypalac_wallet_sanitize_contact_array($contact);

    switch ($module) {
        case 'paypalac_wallet_applepay':
            $given  = trim($contact['givenName']  ?? '');
            $family = trim($contact['familyName'] ?? '');

            if ($given === '' && $family === '') {
                // Some Apple Pay payloads provide only a "name" field. Use it if
                // givenName/familyName are missing.
                $contact['name'] = trim($contact['name'] ?? '');
            } else {
                $contact['name'] = trim($given . ' ' . $family);
            }

            if (isset($contact['addressLines']) && is_array($contact['addressLines'])) {
                $contact['address1'] = trim($contact['addressLines'][0] ?? '');
                $contact['address2'] = trim($contact['addressLines'][1] ?? '');
            } else {
                // Preserve any existing address fields provided by the payload
                $contact['address1'] = trim($contact['address1'] ?? '');
                $contact['address2'] = trim($contact['address2'] ?? '');
            }

            $contact['postalCode'] = trim($contact['postalCode'] ?? '');
            $contact['locality'] = trim($contact['locality'] ?? '');
            $contact['administrativeArea'] = trim($contact['administrativeArea'] ?? '');
            $contact['countryCode'] = trim($contact['countryCode'] ?? '');

            $contact['emailAddress'] = trim($contact['emailAddress'] ?? $contact['email'] ?? '');
            $contact['phoneNumber'] = trim($contact['phoneNumber'] ?? $contact['phone'] ?? '');
            break;
            
        case 'paypalac_wallet_paypal':
        case 'paypalac_wallet_venmo':
            $contact['name'] = trim($contact['name'] ?? $contact['recipientName'] ?? '');
            $contact['address1'] = trim($contact['address1'] ?? $contact['line1'] ?? '');
            $contact['address2'] = trim($contact['address2'] ?? $contact['line2'] ?? '');

            $contact['postalCode'] = trim($contact['postalCode'] ?? $contact['postal_code'] ?? '');
            $contact['locality'] = trim($contact['locality'] ?? $contact['city'] ?? '');
            $contact['administrativeArea'] = trim($contact['administrativeArea'] ?? $contact['state'] ?? '');
            $contact['countryCode'] = trim($contact['countryCode'] ?? $contact['country_code'] ?? '');

            $contact['emailAddress'] = trim($contact['emailAddress'] ?? $contact['email'] ?? '');
            $contact['phoneNumber'] = trim($contact['phoneNumber'] ?? $contact['phone'] ?? '');
            break;

        case 'paypalac_wallet_googlepay':
            $given  = trim($contact['givenName'] ?? $contact['firstName'] ?? '');
            $family = trim($contact['familyName'] ?? $contact['lastName'] ?? '');
            $recipient = trim($contact['recipientName'] ?? '');
            $name = trim($contact['name'] ?? '');

            if ($name === '' && ($given !== '' || $family !== '')) {
                $name = trim($given . ' ' . $family);
            }
            if ($name === '' && $recipient !== '') {
                $name = $recipient;
            }
            if ($name === '' && !empty($contact['companyName'])) {
                $name = trim($contact['companyName']);
            }
            if ($name === '' && !empty($contact['emailAddress'])) {
                $name = trim(strtok($contact['emailAddress'], '@'));
            }
            if ($name === '' && !empty($contact['email'])) {
                $name = trim(strtok($contact['email'], '@'));
            }
            if ($name === '') {
                $name = 'Google Pay Customer';
            }

            $contact['name'] = $name;

            if (empty($contact['emailAddress']) && !empty($contact['email'])) {
                $contact['emailAddress'] = trim($contact['email']);
            }
            break;
    }

    paypalac_wallet_sanitize_contact_array($contact);

    return $contact;
}

function paypalac_wallet_get_common_instance() {
    static $instance = null;
    static $attempted = false;

    if ($instance instanceof \PayPalWalletCommon) {
        return $instance;
    }

    if ($attempted) {
        return null;
    }

    $attempted = true;

    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')
        || !defined('MODULE_PAYMENT_PAYPALAC_MERCHANTID')
        || !defined('MODULE_PAYMENT_PAYPALAC_PUBLICKEY')
        || !defined('MODULE_PAYMENT_PAYPALAC_PRIVATEKEY')) {
        return null;
    }

    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/PayPalWallet.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/paypalac_wallet_common.php');

    $config = [
        'environment' => MODULE_PAYMENT_PAYPALAC_SERVER,
        'merchant_id' => MODULE_PAYMENT_PAYPALAC_MERCHANTID,
        'public_key'  => MODULE_PAYMENT_PAYPALAC_PUBLICKEY,
        'private_key' => MODULE_PAYMENT_PAYPALAC_PRIVATEKEY,
        'debug_logging' => defined('MODULE_PAYMENT_PAYPALAC_DEBUGGING')
            && MODULE_PAYMENT_PAYPALAC_DEBUGGING !== 'Alerts Only',
    ];

    try {
        $instance = new \PayPalWalletCommon($config);
    } catch (\Throwable $exception) {
        error_log('PayPal Wallet configuration error: ' . $exception->getMessage());
        $instance = null;
    }

    return $instance;
}

/**
 * Alias for normalize_paypalac_wallet_contact for backward compatibility with Braintree code.
 * This function normalizes contact/address data from wallet payment sources.
 * 
 * @param array $contact The contact/address data to normalize
 * @param string $module The payment module name (e.g., 'paypalac_googlepay', 'paypalac_applepay')
 * @return array The normalized contact data
 */
function normalize_braintree_contact($contact, $module = '') {
    // Map module names to the expected format for normalize_paypalac_wallet_contact
    $moduleMap = [
        'paypalac_googlepay' => 'paypalac_wallet_googlepay',
        'paypalac_applepay' => 'paypalac_wallet_applepay',
        'paypalac_venmo' => 'paypalac_wallet_venmo',
        'paypalac_paylater' => 'paypalac_wallet_paypal',
    ];
    
    $mappedModule = isset($moduleMap[$module]) ? $moduleMap[$module] : $module;
    
    return normalize_paypalac_wallet_contact($contact, $mappedModule);
}

/**
 * Get zone name from zone ID.
 * 
 * @param int $zone_id The zone ID to look up
 * @param object $db The database connection object
 * @return string The zone name, or empty string if not found
 */
function braintree_get_zone_name($zone_id, $db) {
    if ($zone_id <= 0) {
        return '';
    }
    
    $zone_query = $db->Execute("
        SELECT zone_name FROM " . TABLE_ZONES . "
        WHERE zone_id = " . (int)$zone_id . "
        LIMIT 1
    ");
    
    if ($zone_query->RecordCount() > 0) {
        return $zone_query->fields['zone_name'];
    }
    
    return '';
}

/**
 * Alias for braintree_lookup_zone_id for backward compatibility.
 * This function looks up the zone ID for a given zone name and country code.
 * 
 * @param string $zone_name The zone/state name
 * @param string $country_code The country ISO code
 * @param object $db The database object
 * @return int The zone ID
 */
function braintree_lookup_zone_id($zone_name, $country_code, $db) {
    return paypalac_wallet_lookup_zone_id($zone_name, $country_code, $db);
}

function paypalac_wallet_generate_client_token($currency = null) {
    static $cachedTokens = [];

    $currency = $currency ?: ($_SESSION['currency'] ?? null);

    if ($currency && isset($cachedTokens[$currency])) {
        return $cachedTokens[$currency];
    }

    $common = paypalac_wallet_get_common_instance();
    if (!$common) {
        return '';
    }

    $merchantAccountId = $currency ? $common->get_merchant_account_id($currency) : null;
    $token = $common->generate_client_token($merchantAccountId);

    if ($currency) {
        $cachedTokens[$currency] = $token ?: '';
    }

    return $token ?: '';
}

function paypalac_wallet_get_tokenization_key() {
    $common = paypalac_wallet_get_common_instance();
    if (!$common || !method_exists($common, 'get_tokenization_key')) {
        return '';
    }

    return (string) $common->get_tokenization_key();
}

/**
 * Fallback implementation of zen_update_orders_history for when it's not available
 * This is typically provided by Zen Cart core, but we provide a stub for wallet checkout contexts
 * 
 * @param int $order_id The order ID
 * @param string $message The status message to add
 * @param string|null $updated_by Who updated the order (e.g., 'webhook', null for admin)
 * @param int $status The new order status ID (-1 means no change)
 * @param int $customer_notified Whether customer was notified (0=no, 1=yes, -1=hidden, -2=admin only)
 * @return void
 */
if (!function_exists('zen_update_orders_history')) {
    function zen_update_orders_history($order_id, $message, $updated_by = null, $status = -1, $customer_notified = 0) {
        global $db;
        
        // If database is not available, log and return
        if (!isset($db) || !is_object($db)) {
            error_log("zen_update_orders_history: Database not available for order $order_id");
            return;
        }
        
        // If order ID is not valid, return
        if (empty($order_id) || !is_numeric($order_id)) {
            error_log("zen_update_orders_history: Invalid order ID: " . var_export($order_id, true));
            return;
        }
        
        $order_id = (int)$order_id;
        
        // If status is provided and valid, update the order status
        if ($status > 0) {
            try {
                // Check if TABLE_ORDERS is defined
                if (!defined('TABLE_ORDERS')) {
                    error_log("zen_update_orders_history: TABLE_ORDERS constant not defined");
                    return;
                }
                
                $db->Execute("UPDATE " . TABLE_ORDERS . " 
                             SET orders_status = " . (int)$status . ",
                                 last_modified = now()
                             WHERE orders_id = " . $order_id);
            } catch (Exception $e) {
                error_log("zen_update_orders_history: Failed to update order status: " . $e->getMessage());
            }
        }
        
        // Insert the order status history record
        try {
            // Check if required constants are defined
            if (!defined('TABLE_ORDERS_STATUS_HISTORY')) {
                error_log("zen_update_orders_history: TABLE_ORDERS_STATUS_HISTORY constant not defined");
                return;
            }
            
            // Use a default status if DEFAULT_ORDERS_STATUS_ID is not defined
            $default_status = defined('DEFAULT_ORDERS_STATUS_ID') ? (int)DEFAULT_ORDERS_STATUS_ID : 1;
            $status_id = ($status > 0) ? (int)$status : $default_status;
            
            // Use zen_db_input if available, otherwise use basic escaping
            if (function_exists('zen_db_input')) {
                $escaped_message = zen_db_input($message);
            } else {
                // WARNING: Fallback to basic escaping - less secure than zen_db_input
                // In production, zen_db_input should always be available since this function
                // is only called when the Zen Cart environment is loaded.
                // Prepared statements would be more secure but are not available in Zen Cart's queryFactory.
                $escaped_message = $db->prepareInput($message);
            }
            
            // Build the SQL INSERT statement with properly escaped values
            $sql = "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " 
                    (orders_id, orders_status_id, date_added, customer_notified, comments) 
                    VALUES (" . (int)$order_id . ", " . (int)$status_id . ", now(), " . (int)$customer_notified . ", '" . $escaped_message . "')";
            
            $db->Execute($sql);
        } catch (Exception $e) {
            error_log("zen_update_orders_history: Failed to insert order history: " . $e->getMessage());
        }
    }
}
