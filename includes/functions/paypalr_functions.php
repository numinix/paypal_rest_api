<?php

if (!function_exists('paypalr_wallet_load_language_file')) {
    function paypalr_wallet_load_language_file($basename)
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

if (!function_exists('paypalr_wallet_checkout_log')) {
    function paypalr_wallet_checkout_log($message, $logFilePath = null)
    {
        $targetFile = $logFilePath ?: (defined('LOG_FILE_PATH') ? LOG_FILE_PATH : DIR_FS_LOGS . '/paypalr_wallet_handler.log');
        if (!is_dir(dirname($targetFile))) {
            @mkdir(dirname($targetFile), 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($targetFile, $line, FILE_APPEND);
    }
}

function paypalr_wallet_lookup_zone_id($zone_name, $country_code, $db) {
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

function log_paypalr_wallet_message($message, $append = true) {
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

function paypalr_wallet_normalize_contact_value($value) {
    if (!is_string($value)) {
        return $value;
    }

    $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    // Normalize any escaped apostrophes and curly quotes to straight apostrophes.
    $decoded = str_replace("\\'", "'", $decoded);
    $decoded = str_replace(['’', '‘'], "'", $decoded);

    return trim($decoded);
}

function paypalr_wallet_sanitize_contact_array(&$contact) {
    if (!is_array($contact)) {
        return;
    }

    array_walk_recursive($contact, function (&$value) {
        if (is_string($value)) {
            $value = paypalr_wallet_normalize_contact_value($value);
        }
    });
}

function normalize_paypalr_wallet_contact($contact, $module = '') {
    if (!is_array($contact)) return $contact;

    paypalr_wallet_sanitize_contact_array($contact);

    switch ($module) {
        case 'paypalr_wallet_applepay':
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
            
        case 'paypalr_wallet_paypal':
        case 'paypalr_wallet_venmo':
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

        case 'paypalr_wallet_googlepay':
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

    paypalr_wallet_sanitize_contact_array($contact);

    return $contact;
}

function paypalr_wallet_get_common_instance() {
    static $instance = null;
    static $attempted = false;

    if ($instance instanceof PayPalWalletCommon) {
        return $instance;
    }

    if ($attempted) {
        return null;
    }

    $attempted = true;

    if (!defined('MODULE_PAYMENT_PAYPALR_SERVER')
        || !defined('MODULE_PAYMENT_PAYPALR_MERCHANTID')
        || !defined('MODULE_PAYMENT_PAYPALR_PUBLICKEY')
        || !defined('MODULE_PAYMENT_PAYPALR_PRIVATEKEY')) {
        return null;
    }

    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/PayPalWallet.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/paypalr_wallet_common.php');

    $config = [
        'environment' => MODULE_PAYMENT_PAYPALR_SERVER,
        'merchant_id' => MODULE_PAYMENT_PAYPALR_MERCHANTID,
        'public_key'  => MODULE_PAYMENT_PAYPALR_PUBLICKEY,
        'private_key' => MODULE_PAYMENT_PAYPALR_PRIVATEKEY,
        'debug_logging' => defined('MODULE_PAYMENT_PAYPALR_DEBUGGING')
            && MODULE_PAYMENT_PAYPALR_DEBUGGING !== 'Alerts Only',
    ];

    try {
        $instance = new PayPalWalletCommon($config);
    } catch (\Throwable $exception) {
        error_log('PayPal Wallet configuration error: ' . $exception->getMessage());
        $instance = null;
    }

    return $instance;
}

function paypalr_wallet_generate_client_token($currency = null) {
    static $cachedTokens = [];

    $currency = $currency ?: ($_SESSION['currency'] ?? null);

    if ($currency && isset($cachedTokens[$currency])) {
        return $cachedTokens[$currency];
    }

    $common = paypalr_wallet_get_common_instance();
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

function paypalr_wallet_get_tokenization_key() {
    $common = paypalr_wallet_get_common_instance();
    if (!$common || !method_exists($common, 'get_tokenization_key')) {
        return '';
    }

    return (string) $common->get_tokenization_key();
}
