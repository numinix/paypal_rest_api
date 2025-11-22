<?php
/**
 * PayPal Advanced Checkout (paypalr) partner integrated sign-up bridge.
 *
 * Routes the "Complete PayPal setup" button to the Numinix onboarding
 * experience hosted on numinix.com. The helper collects basic storefront
 * metadata, builds a tracking reference and then redirects the
 * administrator to the external flow. When PayPal (or the merchant)
 * returns control to this script, the administrator is routed back to the
 * payment module configuration page with an appropriate status message.
 */

$autoloaderPath = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

if (function_exists('zen_admin_check_login')) {
    $paypalrAdminLoggedIn = zen_admin_check_login();
} else {
    $paypalrAdminLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;
}

if (!$paypalrAdminLoggedIn) {
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr.php';
if (file_exists($languageFile)) {
    include $languageFile;
}

$action = strtolower(trim((string)($_GET['action'] ?? 'start')));

switch ($action) {
    case 'return':
        // Check if credentials were passed back from the onboarding process
        $client_id = trim((string)($_GET['client_id'] ?? ''));
        $client_secret = trim((string)($_GET['client_secret'] ?? ''));
        $environment = paypalr_detect_environment();
        
        $credentials_saved = false;
        if ($client_id !== '' && $client_secret !== '') {
            // Save the credentials to the configuration
            $credentials_saved = paypalr_save_credentials($client_id, $client_secret, $environment);
        }
        
        if ($credentials_saved) {
            paypalr_onboarding_message(
                MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_SUCCESS_AUTO ?? 'PayPal onboarding completed successfully! Your API credentials have been automatically configured.',
                'success'
            );
        } else {
            // Credentials weren't passed or couldn't be saved - show manual instructions
            paypalr_onboarding_message(
                MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_RETURN_MESSAGE ?? 'PayPal onboarding completed. Please check your PayPal account to retrieve and enter your Client ID and Secret in the configuration below.',
                'success'
            );
        }
        paypalr_redirect_to_modules();
        break;

    case 'cancel':
        paypalr_onboarding_message(
            MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_CANCEL_MESSAGE ?? 'PayPal onboarding was cancelled before completion. You can restart the process at any time.',
            'warning'
        );
        paypalr_redirect_to_modules();
        break;

    case 'start':
    default:
        $redirectUrl = paypalr_build_numinix_signup_url();
        if ($redirectUrl === '') {
            paypalr_onboarding_message(
                MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_ERROR_MESSAGE ?? 'Unable to launch the Numinix onboarding portal. Please verify your store URL and try again.',
                'error'
            );
            paypalr_redirect_to_modules();
            break;
        }

        zen_redirect($redirectUrl);
        exit;
        break;
}

function paypalr_onboarding_message(string $message, string $type = 'warning'): void
{
    global $messageStack;

    if (!isset($messageStack) || !is_object($messageStack)) {
        return;
    }

    $messageStack->add_session('main', $message, $type);
}

function paypalr_redirect_to_modules(): void
{
    zen_redirect(paypalr_modules_page_url());
    exit;
}

function paypalr_modules_page_url(): string
{
    return zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL', false);
}

function paypalr_build_numinix_signup_url(): string
{
    $baseUrl = paypalr_get_numinix_portal_base();
    if ($baseUrl === '') {
        return '';
    }

    $trackingId = paypalr_generate_tracking_id();
    $_SESSION['paypalr_isu_tracking_id'] = $trackingId;

    $query = [
        'main_page' => 'paypal_signup',
        'mode' => 'standalone',
        'env' => paypalr_detect_environment(),
        'tracking_id' => $trackingId,
        'redirect_url' => paypalr_modules_page_url(),
        'source' => 'paypalr',
    ];

    $metadata = paypalr_collect_store_metadata();
    $encodedMetadata = paypalr_encode_metadata($metadata);
    if ($encodedMetadata !== '') {
        $query['metadata'] = $encodedMetadata;
    }

    $returnUrl = paypalr_self_url(['action' => 'return']);
    if ($returnUrl !== '') {
        $query['return_url'] = $returnUrl;
    }

    $cancelUrl = paypalr_self_url(['action' => 'cancel']);
    if ($cancelUrl !== '') {
        $query['cancel_url'] = $cancelUrl;
    }

    return $baseUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function paypalr_get_numinix_portal_base(): string
{
    $baseUrl = 'https://www.numinix.com/index.php';
    if (defined('MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL') && MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL !== '') {
        $baseUrl = trim((string)MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL);
    }

    if ($baseUrl === '') {
        return '';
    }

    return $baseUrl;
}

function paypalr_detect_environment(): string
{
    $environment = 'live';
    if (defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
        $value = strtolower((string)MODULE_PAYMENT_PAYPALR_SERVER);
        if ($value === 'sandbox') {
            $environment = 'sandbox';
        }
    }

    return $environment;
}

function paypalr_collect_store_metadata(): array
{
    $storeUrl = paypalr_guess_storefront_url();

    return paypalr_filter_empty_values([
        'store_name' => defined('STORE_NAME') ? (string)STORE_NAME : '',
        'store_owner' => defined('STORE_OWNER') ? (string)STORE_OWNER : '',
        'store_email' => defined('STORE_OWNER_EMAIL_ADDRESS') ? (string)STORE_OWNER_EMAIL_ADDRESS : '',
        'store_url' => $storeUrl,
        'zen_cart_version' => paypalr_detect_zen_cart_version(),
        'module_version' => defined('MODULE_PAYMENT_PAYPALR_VERSION') ? (string)MODULE_PAYMENT_PAYPALR_VERSION : '',
        'php_version' => PHP_VERSION,
        'timestamp' => time(),
    ]);
}

function paypalr_detect_zen_cart_version(): string
{
    if (defined('PROJECT_VERSION_MAJOR')) {
        $minor = defined('PROJECT_VERSION_MINOR') ? (string)PROJECT_VERSION_MINOR : '';
        $patch = defined('PROJECT_VERSION_PATCH') ? (string)PROJECT_VERSION_PATCH : '';
        $version = (string)PROJECT_VERSION_MAJOR;
        if ($minor !== '') {
            $version .= '.' . $minor;
        }
        if ($patch !== '') {
            $version .= '.' . $patch;
        }

        return $version;
    }

    return '';
}

function paypalr_guess_storefront_url(): string
{
    $catalogDir = defined('DIR_WS_CATALOG') ? (string)DIR_WS_CATALOG : '';
    $catalogDir = trim($catalogDir);

    $httpsServer = defined('HTTPS_SERVER') ? trim((string)HTTPS_SERVER) : '';
    $httpServer = defined('HTTP_SERVER') ? trim((string)HTTP_SERVER) : '';

    $server = $httpsServer !== '' ? $httpsServer : $httpServer;
    if ($server === '') {
        return '';
    }

    $server = rtrim($server, '/');
    if ($catalogDir !== '') {
        $catalogDir = '/' . ltrim($catalogDir, '/');
        $server .= rtrim($catalogDir, '/');
    }

    return $server;
}

function paypalr_encode_metadata(array $metadata): string
{
    if ($metadata === []) {
        return '';
    }

    $json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return '';
    }

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

function paypalr_filter_empty_values(array $data): array
{
    foreach ($data as $key => $value) {
        if ($value === null) {
            unset($data[$key]);
            continue;
        }
        if (is_string($value) && trim($value) === '') {
            unset($data[$key]);
        }
    }

    return $data;
}

function paypalr_generate_tracking_id(): string
{
    try {
        $random = bin2hex(random_bytes(8));
    } catch (Exception $exception) {
        $random = (string) uniqid('', true);
        $random = preg_replace('/[^a-z0-9]/i', '', $random);
        if ($random === null || $random === '') {
            $random = (string) time();
        }
    }

    return 'paypalr-' . substr($random, 0, 16);
}

function paypalr_self_url(array $params = []): string
{
    if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] === '') {
        return '';
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    } elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] !== '') {
        $scheme = (string) $_SERVER['REQUEST_SCHEME'];
    }

    $host = (string) $_SERVER['HTTP_HOST'];
    $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

    if ($path === '') {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    }

    if ($path === '') {
        return '';
    }

    $url = $scheme . '://' . $host . $path;

    if ($params !== []) {
        $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

/**
 * Saves PayPal API credentials to the configuration database.
 *
 * @param string $client_id The PayPal Client ID
 * @param string $client_secret The PayPal Client Secret
 * @param string $environment Either 'live' or 'sandbox'
 * @return bool True if credentials were saved successfully, false otherwise
 */
function paypalr_save_credentials(string $client_id, string $client_secret, string $environment): bool
{
    global $db;
    
    if ($client_id === '' || $client_secret === '') {
        return false;
    }
    
    // Determine which configuration keys to update based on environment
    if ($environment === 'live') {
        $client_id_key = 'MODULE_PAYMENT_PAYPALR_CLIENTID_L';
        $client_secret_key = 'MODULE_PAYMENT_PAYPALR_SECRET_L';
    } else {
        $client_id_key = 'MODULE_PAYMENT_PAYPALR_CLIENTID_S';
        $client_secret_key = 'MODULE_PAYMENT_PAYPALR_SECRET_S';
    }
    
    try {
        // Update Client ID
        $sql_data_array = [
            'configuration_value' => $client_id,
            'last_modified' => 'now()'
        ];
        zen_db_perform(
            TABLE_CONFIGURATION,
            $sql_data_array,
            'update',
            "configuration_key = '" . zen_db_input($client_id_key) . "'"
        );
        
        // Update Client Secret
        $sql_data_array = [
            'configuration_value' => $client_secret,
            'last_modified' => 'now()'
        ];
        zen_db_perform(
            TABLE_CONFIGURATION,
            $sql_data_array,
            'update',
            "configuration_key = '" . zen_db_input($client_secret_key) . "'"
        );
        
        return true;
    } catch (Exception $e) {
        // Log error but don't expose details to user
        trigger_error('Failed to save PayPal credentials: ' . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}
