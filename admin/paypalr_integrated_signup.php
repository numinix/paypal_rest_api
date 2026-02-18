<?php
/**
 * PayPal Advanced Checkout (paypalr) partner integrated sign-up.
 *
 * Provides an in-admin onboarding experience that launches PayPal signup
 * via the Numinix.com API service without leaving the admin page.
 * Credentials are automatically retrieved and filled into the module config.
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

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

// Check for AJAX requests before redirect - must return JSON, not HTML
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$paypalrAdminLoggedIn) {
    // For AJAX requests, return JSON error instead of HTML redirect
    if ($isAjaxRequest) {
        paypalr_json_error('Session expired. Please refresh the page and log in again.');
    }
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr.php';
if (file_exists($languageFile)) {
    include $languageFile;
}

// Handle AJAX requests for the new in-admin flow
if ($action === 'proxy' && $isAjaxRequest) {
    paypalr_handle_proxy_request();
    exit;
}

if ($action === 'save_credentials' && $isAjaxRequest) {
    paypalr_handle_save_credentials();
    exit;
}

// Handle JavaScript SDK credential save - Phase 2
if ($action === 'save_isu_credentials' && $isAjaxRequest) {
    paypalr_handle_save_isu_credentials();
    exit;
}

// Handle completion flow - receives PayPal redirect with credentials
if ($action === 'complete') {
    paypalr_handle_completion();
    exit;
}

// Handle legacy redirect-based flow for backwards compatibility
if ($action === 'return') {
    paypalr_handle_legacy_return();
    exit;
}

if ($action === 'cancel') {
    paypalr_onboarding_message(
        MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_CANCEL_MESSAGE ?? 'PayPal onboarding was cancelled before completion. You can restart the process at any time.',
        'warning'
    );
    paypalr_redirect_to_modules();
    exit;
}

// Default: Show the in-admin onboarding page
paypalr_render_onboarding_page();
exit;

/**
 * Proxies AJAX requests to the Numinix.com API.
 */
function paypalr_handle_proxy_request(): void
{
    header('Content-Type: application/json');

    $proxyAction = strtolower(trim((string)($_REQUEST['proxy_action'] ?? '')));
    $numinixUrl = paypalr_get_numinix_portal_base();
    $storeEnvironment = paypalr_detect_environment();

    paypalr_log_debug('Handling proxy request', [
        'proxy_action' => $proxyAction,
        'numinix_url' => $numinixUrl,
        'post_keys' => array_keys($_POST),
        'store_environment' => $storeEnvironment,
    ]);
    
    if ($numinixUrl === '') {
        paypalr_json_error('Numinix portal URL not configured.');
        return;
    }
    
    $allowedActions = [
        'start',
        'status',
        'finalize',
        // Marketplace-specific actions
        'create_referral',
        'marketplace_status',
        'managed_integration_status',
    ];
    if (!in_array($proxyAction, $allowedActions, true)) {
        paypalr_json_error('Invalid proxy action.');
        return;
    }
    
    try {
        $response = paypalr_proxy_to_numinix($numinixUrl, $proxyAction, $_POST);
        
        // Log the successful response (redacted)
        $decoded = json_decode($response, true);
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $remoteEnvironment = $data['environment'] ?? null;
        $logContext = [
            'proxy_action' => $proxyAction,
            'success' => $decoded['success'] ?? 'unknown',
            'has_data' => $data !== [],
            'store_environment' => $storeEnvironment,
            'remote_environment' => $remoteEnvironment,
            'environment_mismatch' => $remoteEnvironment !== null && $remoteEnvironment !== $storeEnvironment,
            'step' => $data['step'] ?? ($data['status'] ?? null),
            'tracking_id' => $data['tracking_id'] ?? null,
            'partner_referral_id' => $data['partner_referral_id'] ?? null,
            'has_credentials' => isset($data['credentials']),
            'message' => $decoded['message'] ?? null,
            'data_keys' => array_keys($data),
        ];

        // Store nonce in session for use in completion page
        if ($proxyAction === 'start' && !empty($data['nonce'])) {
            $_SESSION['paypalr_isu_nonce'] = $data['nonce'];
            paypalr_log_debug('Stored nonce in session for completion page', [
                'tracking_id' => $data['tracking_id'] ?? null,
            ]);
        }

        // Store seller_nonce in session for credential exchange (used as code_verifier)
        if ($proxyAction === 'start' && !empty($data['seller_nonce'])) {
            $_SESSION['paypalr_isu_seller_nonce'] = $data['seller_nonce'];
            paypalr_log_debug('Stored seller_nonce in session for credential exchange', [
                'tracking_id' => $data['tracking_id'] ?? null,
            ]);
        }

        // Provide a sanitized view of the full response for debugging when polling/finalizing
        if (in_array($proxyAction, ['status', 'finalize'], true)) {
            $logContext['sanitized_data'] = paypalr_redact_sensitive($data);
        }

        paypalr_log_debug('Proxy request completed', $logContext);
        
        echo $response;
    } catch (Exception $e) {
        paypalr_log_debug('Proxy request failed with exception', [
            'proxy_action' => $proxyAction,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
        paypalr_json_error($e->getMessage());
    }
}

/**
 * Makes an HTTP request to the Numinix.com API.
 */
function paypalr_proxy_to_numinix(string $baseUrl, string $action, array $data): string
{
    $url = rtrim($baseUrl, '/');
    [$environment, $environmentSource] = paypalr_resolve_environment_from_request($data);
    
    // Remove local Zen Cart admin parameters that could confuse the remote Zen Cart.
    // These parameters are used for local session validation and should not be forwarded
    // to numinix.com, where they could trigger security redirects or unexpected behavior.
    $localOnlyParams = ['action', 'securityToken', 'proxy_action'];
    foreach ($localOnlyParams as $param) {
        unset($data[$param]);
    }
    
    // For 'start' action, pass empty nonce; numinix.com will generate one.
    // For 'status' and 'finalize', use the nonce returned from the start call.
    $payload = array_merge($data, [
        'nxp_paypal_action' => $action,
        'env' => $environment,
    ]);
    
    // Build origin header from current request for CORS validation
    $origin = paypalr_get_origin();
    
    $postData = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    
    paypalr_log_debug('Proxy request to Numinix API', [
        'action' => $action,
        'url' => $url,
        'environment' => $environment,
        'environment_source' => $environmentSource,
        'payload_keys' => array_keys($payload),
    ]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
        'Origin: ' . $origin,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($response === false) {
        $errorMsg = sprintf(
            'cURL error %d: %s (URL: %s)',
            $curlErrno,
            $curlError ?: 'Unknown error',
            $url
        );
        paypalr_log_debug('Numinix API request failed', [
            'action' => $action,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'url' => $url,
        ]);
        throw new RuntimeException('Failed to contact Numinix API: ' . $errorMsg);
    }
    
    if ($httpCode !== 200) {
        // Try to decode JSON response for more specific error message
        $decoded = json_decode($response, true);
        $serverMessage = '';
        if (is_array($decoded) && !empty($decoded['message'])) {
            $serverMessage = (string) $decoded['message'];
        }
        
        $errorMsg = sprintf(
            'HTTP %d from Numinix API (action: %s)%s',
            $httpCode,
            $action,
            $serverMessage !== '' ? ': ' . $serverMessage : ''
        );
        
        paypalr_log_debug('Numinix API returned non-200 status', [
            'action' => $action,
            'http_code' => $httpCode,
            'redirect_url' => $redirectUrl,
            'response' => $response,
            'url' => $url,
        ]);
        throw new RuntimeException('Failed to contact Numinix API: ' . $errorMsg);
    }
    
    paypalr_log_debug('Numinix API response received', [
        'action' => $action,
        'http_code' => $httpCode,
        'response_length' => strlen($response),
    ]);
    
    return $response;
}

/**
 * Handles legacy redirect-based return flow.
 */
function paypalr_handle_legacy_return(): void
{
    $client_id = trim((string)($_GET['client_id'] ?? ''));
    $client_secret = trim((string)($_GET['client_secret'] ?? ''));
    $environment = paypalr_detect_environment();
    
    $credentials_saved = false;
    if ($client_id !== '' && $client_secret !== '') {
        $credentials_saved = paypalr_save_credentials($client_id, $client_secret, $environment);
    }
    
    if ($credentials_saved) {
        paypalr_onboarding_message(
            MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_SUCCESS_AUTO ?? 'PayPal onboarding completed successfully! Your API credentials have been automatically configured.',
            'success'
        );
    } else {
        paypalr_onboarding_message(
            MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_RETURN_MESSAGE ?? 'PayPal onboarding completed. Please check your PayPal account to retrieve and enter your Client ID and Secret in the configuration below.',
            'success'
        );
    }
    paypalr_redirect_to_modules();
}

/**
 * Renders the in-admin onboarding page with JavaScript.
 */
function paypalr_render_onboarding_page(): void
{
    $environment = paypalr_detect_environment();
    $modulesPageUrl = paypalr_modules_page_url();
    $proxyUrl = paypalr_self_url(['action' => 'proxy']);
    $saveUrl = paypalr_self_url(['action' => 'save_credentials']);
    $completeUrl = paypalr_self_url(['action' => 'complete']);
    $securityToken = $_SESSION['securityToken'] ?? '';
    
    // Security token should exist if admin is properly logged in
    if ($securityToken === '') {
        paypalr_onboarding_message(
            'Security token missing. Please log in again.',
            'error'
        );
        paypalr_redirect_to_modules();
        return;
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PayPal Integrated Signup</title>
        <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
        <link rel="stylesheet" href="includes/css/paypalr_integrated_signup.css">
    </head>
    <body>
        <div class="nmx-module">
            <div class="nmx-container">
                <div class="nmx-container-header">
                    <h1>PayPal Integrated Signup</h1>
                    <p class="nmx-container-subtitle">Connect your PayPal account to accept payments</p>
                </div>
                
                <div class="nmx-panel">
                    <div class="nmx-panel-heading">
                        <div class="nmx-panel-title">PayPal Onboarding</div>
                    </div>
                    <div class="nmx-panel-body">
                        <p>Click the button below to start the secure PayPal onboarding process. A popup window will guide you through connecting your PayPal account.</p>
                        
                        <div id="status" class="nmx-alert"></div>
                        <div id="credentials-display"></div>

                        <div class="nmx-form-group">
                            <label for="environment">Select onboarding mode</label>
                            <select id="environment" name="environment" class="nmx-form-control">
                                <option value="live">Production</option>
                                <option value="sandbox">Sandbox</option>
                            </select>
                            <p class="nmx-form-help">Choose which PayPal environment to onboard. If you leave this unchanged, the default from your store configuration will be used.</p>
                        </div>

                        <div class="nmx-form-actions">
                            <button id="start-button" type="button" class="nmx-btn nmx-btn-primary">Start PayPal Signup</button>
                            <a href="<?php echo htmlspecialchars($modulesPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nmx-btn nmx-btn-default">Cancel and return to modules</a>
                        </div>
                        
                        <!-- PayPal signup link container for mini-browser flow -->
                        <div id="paypal-signup-container" style="display: none; margin-top: 20px;"></div>
                    </div>
                </div>
                
                <div class="nmx-footer">
                    <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                        <img src="images/numinix_logo.png" alt="Numinix">
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 
            PayPal Partner Script for Mini-Browser/Embedded Signup Flow
            This script enables the embedded mini-browser experience where PayPal
            signup happens in an overlay without leaving the page.
            
            Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns
            an authCode and sharedId to your seller's browser via the callback function."
            See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
        -->
        <!-- PayPal partner.js will be loaded dynamically based on environment -->
        
        <script src="includes/javascript/paypalr_integrated_signup_callback.js"></script>
        
        <script>
            window.paypalrISUConfig = {
                proxyUrl: <?php echo json_encode($proxyUrl); ?>,
                saveUrl: <?php echo json_encode($saveUrl); ?>,
                completeUrl: <?php echo json_encode($completeUrl); ?>,
                environment: <?php echo json_encode($environment); ?>,
                modulesPageUrl: <?php echo json_encode($modulesPageUrl); ?>,
                securityToken: <?php echo json_encode($securityToken); ?>
            };
        </script>
        <script src="includes/javascript/paypalr_integrated_signup.js"></script>
    </body>
    </html>
    <?php
}

function paypalr_json_error(string $message): void
{
    paypalr_log_debug('Returning JSON error to client', ['message' => $message]);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function paypalr_onboarding_message(string $message, string $type = 'warning'): void
{
    global $messageStack;

    if (!isset($messageStack) || !is_object($messageStack)) {
        return;
    }

    $messageStack->add_session($message, $type);
}

function paypalr_redirect_to_modules(): void
{
    zen_redirect(paypalr_modules_page_url());
    exit;
}

function paypalr_modules_page_url(): string
{
    return html_entity_decode(
        zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL', false),
        ENT_QUOTES,
        'UTF-8'
    );
}

function paypalr_get_numinix_portal_base(): string
{
    // Use the standalone API endpoint to avoid Zen Cart's action/securityToken handling
    // which can cause 302 redirects when the remote Zen Cart interprets POST parameters.
    $baseUrl = 'https://www.numinix.com/api/paypal_onboarding.php';
    if (defined('MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL') && MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL !== '') {
        $baseUrl = trim((string)MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL);
    }

    if ($baseUrl === '') {
        return '';
    }

    $parsed = parse_url($baseUrl);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return '';
    }

    // Use the configured path, or default to the API endpoint
    $path = $parsed['path'] ?? '/api/paypal_onboarding.php';
    if ($path === '' || $path === '/') {
        $path = '/api/paypal_onboarding.php';
    }

    $normalized = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $normalized .= ':' . $parsed['port'];
    }
    $normalized .= $path;

    // Preserve any query string from the configured URL
    if (!empty($parsed['query'])) {
        $normalized .= '?' . $parsed['query'];
    }

    if (!empty($parsed['fragment'])) {
        $normalized .= '#' . $parsed['fragment'];
    }

    return $normalized;
}

/**
 * Resolves environment preference using client request when provided.
 *
 * @param array<string, mixed> $data
 * @return array{0: string, 1: string}
 */
function paypalr_resolve_environment_from_request(array $data): array
{
    $allowed = ['sandbox', 'live'];
    $requested = strtolower((string)($data['env'] ?? ''));

    if (in_array($requested, $allowed, true)) {
        return [$requested, 'client'];
    }

    return [paypalr_detect_environment(), 'config'];
}

function paypalr_detect_environment(): string
{
    $allowed = ['sandbox', 'live'];

    if (defined('MODULE_PAYMENT_PAYPALR_SERVER')) {
        $value = strtolower((string) MODULE_PAYMENT_PAYPALR_SERVER);
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

    // Default to sandbox to avoid inadvertently creating live accounts
    return 'sandbox';
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

function paypalr_save_credentials(string $client_id, string $client_secret, string $environment): bool
{
    global $db;
    
    if ($client_id === '' || $client_secret === '') {
        return false;
    }
    
    $client_id = zen_db_input($client_id);
    $client_secret = zen_db_input($client_secret);
    
    if ($environment === 'live') {
        $client_id_key = 'MODULE_PAYMENT_PAYPALR_CLIENTID_L';
        $client_secret_key = 'MODULE_PAYMENT_PAYPALR_SECRET_L';
    } else {
        $client_id_key = 'MODULE_PAYMENT_PAYPALR_CLIENTID_S';
        $client_secret_key = 'MODULE_PAYMENT_PAYPALR_SECRET_S';
    }
    
    try {
        $sql_data_array = [
            'configuration_value' => $client_id,
            'last_modified' => 'now()'
        ];
        zen_db_perform(
            TABLE_CONFIGURATION,
            $sql_data_array,
            'UPDATE',
            "configuration_key = '" . zen_db_input($client_id_key) . "'"
        );
        
        $sql_data_array = [
            'configuration_value' => $client_secret,
            'last_modified' => 'now()'
        ];
        zen_db_perform(
            TABLE_CONFIGURATION,
            $sql_data_array,
            'UPDATE',
            "configuration_key = '" . zen_db_input($client_secret_key) . "'"
        );
        
        return true;
    } catch (Exception $e) {
        trigger_error('Failed to save PayPal credentials: Database error occurred', E_USER_WARNING);
        return false;
    }
}

/**
 * Handles AJAX credential saving from the in-admin flow.
 */
function paypalr_handle_save_credentials(): void
{
    header('Content-Type: application/json');

    $token = (string)($_POST['securityToken'] ?? '');
    if ($token === '' || $token !== (string)($_SESSION['securityToken'] ?? '')) {
        paypalr_json_error('Invalid security token. Please refresh and try again.');
    }

    $clientId = trim((string)($_POST['client_id'] ?? ''));
    $clientSecret = trim((string)($_POST['client_secret'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
        paypalr_json_error('Client ID and Secret are required.');
    }

    // Use environment from the API response if provided, otherwise fall back to local setting
    // Validate against explicit whitelist of allowed environment values
    $allowedEnvironments = ['sandbox', 'live'];
    $requestedEnvironment = trim(strtolower((string)($_POST['environment'] ?? '')));
    if (in_array($requestedEnvironment, $allowedEnvironments, true)) {
        $environment = $requestedEnvironment;
    } else {
        $environment = paypalr_detect_environment();
    }
    
    $saved = paypalr_save_credentials($clientId, $clientSecret, $environment);

    if (!$saved) {
        paypalr_json_error('Unable to save PayPal credentials.');
    }

    paypalr_log_debug('PayPal ISU credentials saved', [
        'environment' => $environment,
        'environment_source' => $requestedEnvironment !== '' ? 'api_response' : 'local_setting',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Credentials saved.',
        'environment' => $environment,
    ]);
    exit;
}

/**
 * Handles JavaScript SDK ISU credential save (Phase 2).
 * Receives authCode, sharedId, and merchantId from PayPal JS SDK callback,
 * proxies to numinix.com finalize API, and saves returned credentials.
 */
function paypalr_handle_save_isu_credentials(): void
{
    header('Content-Type: application/json');

    // Validate security token
    $token = (string)($_POST['securityToken'] ?? '');
    if ($token === '' || $token !== (string)($_SESSION['securityToken'] ?? '')) {
        paypalr_json_error('Invalid security token. Please refresh and try again.');
    }

    // Extract parameters from JavaScript SDK callback
    $authCode = trim((string)($_POST['authCode'] ?? ''));
    $sharedId = trim((string)($_POST['sharedId'] ?? ''));
    $merchantId = trim((string)($_POST['merchantId'] ?? $_POST['merchantIdInPayPal'] ?? ''));
    
    // Validate required parameters
    if ($merchantId === '') {
        paypalr_json_error('Merchant ID is required.');
    }

    // Validate environment
    $allowedEnvironments = ['sandbox', 'live'];
    $requestedEnvironment = trim(strtolower((string)($_POST['environment'] ?? '')));
    if (in_array($requestedEnvironment, $allowedEnvironments, true)) {
        $environment = $requestedEnvironment;
    } else {
        $environment = paypalr_detect_environment();
    }

    paypalr_log_debug('JavaScript SDK credential save initiated', [
        'has_auth_code' => $authCode !== '',
        'has_shared_id' => $sharedId !== '',
        'has_merchant_id' => $merchantId !== '',
        'environment' => $environment,
    ]);

    // Prepare request to numinix.com finalize API
    $numinixUrl = 'https://www.numinix.com/api/paypal_onboarding.php';
    $payload = [
        'nxp_paypal_action' => 'finalize',
        'merchant_id' => $merchantId,
        'env' => $environment,
    ];

    // Include authCode and sharedId if provided
    if ($authCode !== '') {
        $payload['auth_code'] = $authCode;
    }
    if ($sharedId !== '') {
        $payload['shared_id'] = $sharedId;
    }

    paypalr_log_debug('Proxying to Numinix finalize API', [
        'url' => $numinixUrl,
        'environment' => $environment,
        'has_auth_code' => $authCode !== '',
        'has_shared_id' => $sharedId !== '',
    ]);

    // Make request to numinix.com
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $numinixUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: ZenCart-PayPalR-ISU/1.0',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        paypalr_log_debug('Numinix API request failed', [
            'error' => $curlError,
            'http_code' => $httpCode,
        ]);
        paypalr_json_error('Failed to contact Numinix API: ' . $curlError);
    }

    paypalr_log_debug('Numinix API response received', [
        'http_code' => $httpCode,
        'response_length' => strlen((string)$response),
    ]);

    // Parse response
    $data = json_decode((string)$response, true);
    if (!is_array($data)) {
        paypalr_log_debug('Invalid JSON response from Numinix API', [
            'response' => substr((string)$response, 0, 500),
        ]);
        paypalr_json_error('Invalid response from Numinix API.');
    }

    // Check for API errors
    if (!isset($data['success']) || $data['success'] !== true) {
        $errorMessage = $data['message'] ?? 'Unknown error from Numinix API';
        paypalr_log_debug('Numinix API returned error', [
            'message' => $errorMessage,
            'data' => $data,
        ]);
        paypalr_json_error($errorMessage);
    }

    // Extract credentials from response
    $responseData = $data['data'] ?? [];
    $credentials = $responseData['credentials'] ?? [];
    $clientId = trim((string)($credentials['client_id'] ?? ''));
    $clientSecret = trim((string)($credentials['client_secret'] ?? ''));

    if ($clientId === '' || $clientSecret === '') {
        // Credentials not ready yet - this might happen if PayPal provisioning is delayed
        $step = $responseData['step'] ?? 'unknown';
        $statusHint = $responseData['status_hint'] ?? '';
        
        paypalr_log_debug('Credentials not yet available', [
            'step' => $step,
            'status_hint' => $statusHint,
        ]);

        echo json_encode([
            'success' => false,
            'waiting' => true,
            'message' => 'PayPal is still provisioning your account. Please wait a moment.',
            'step' => $step,
            'status_hint' => $statusHint,
        ]);
        exit;
    }

    // Save credentials to database
    $saved = paypalr_save_credentials($clientId, $clientSecret, $environment);

    if (!$saved) {
        paypalr_log_debug('Failed to save credentials to database');
        paypalr_json_error('Unable to save PayPal credentials to database.');
    }

    paypalr_log_debug('JavaScript SDK ISU credentials saved successfully', [
        'environment' => $environment,
        'has_client_id' => $clientId !== '',
        'has_client_secret' => $clientSecret !== '',
    ]);

    // Return success with credentials for display
    echo json_encode([
        'success' => true,
        'message' => 'PayPal credentials saved successfully!',
        'environment' => $environment,
        'credentials' => [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    ]);
    exit;
}

/**
 * Returns the origin (scheme + host) for the current request.
 *
 * @return string
 */
function paypalr_get_origin(): string
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
    } elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] !== '') {
        $scheme = (string) $_SERVER['REQUEST_SCHEME'];
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Logs debug information to the Zen Cart logs directory.
 *
 * @param string               $message
 * @param array<string, mixed> $context
 * @return void
 */
function paypalr_log_debug(string $message, array $context = []): void
{
    $logFile = paypalr_resolve_log_file();
    if ($logFile === null) {
        return;
    }
    
    $timestamp = date('c');
    $logEntry = '[' . $timestamp . '] ' . $message;
    
    if (!empty($context)) {
        // Redact sensitive values
        $sanitized = paypalr_redact_sensitive($context);
        $encoded = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $logEntry .= ' ' . $encoded;
        }
    }
    
    $directory = dirname($logFile);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
    }
    
    @file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Determines the debug log file path within the Zen Cart logs directory.
 *
 * @return string|null
 */
function paypalr_resolve_log_file(): ?string
{
    $baseDir = null;
    
    if (defined('DIR_FS_LOGS') && DIR_FS_LOGS !== '') {
        $baseDir = DIR_FS_LOGS;
    } elseif (defined('DIR_FS_CATALOG') && DIR_FS_CATALOG !== '') {
        $baseDir = rtrim(DIR_FS_CATALOG, '\\/') . '/logs';
    }
    
    if ($baseDir === null) {
        return null;
    }
    
    $baseDir = rtrim($baseDir, '\\/');
    return $baseDir . '/paypalr_isu_debug.log';
}

/**
 * Recursively redacts sensitive values before logging.
 *
 * @param mixed $value
 * @return mixed
 */
function paypalr_redact_sensitive($value)
{
    // Sensitive keys to redact from logs - keep in sync with nxp_paypal_redact_log_context
    static $sensitiveKeys = [
        'client_secret', 'secret', 'access_token', 'refresh_token',
        'authorization', 'password', 'securitytoken', 'nonce', 'credentials'
    ];
    
    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $key => $item) {
            $lowerKey = is_string($key) ? strtolower($key) : '';
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = paypalr_redact_sensitive($item);
        }
        return $redacted;
    }
    
    if (is_scalar($value) || $value === null) {
        return $value;
    }
    
    return (string) $value;
}

/**
 * Handles the completion flow when PayPal redirects back to the client.
 * This displays credentials for copy/paste while attempting auto-save in background.
 */
function paypalr_handle_completion(): void
{
    $proxyUrl = paypalr_self_url(['action' => 'proxy']);
    $saveUrl = paypalr_self_url(['action' => 'save_credentials']);
    $modulesPageUrl = paypalr_modules_page_url();
    $securityToken = $_SESSION['securityToken'] ?? '';
    
    // Extract PayPal return parameters
    $merchantId = trim((string)($_GET['merchantIdInPayPal'] ?? $_GET['merchantId'] ?? $_GET['merchant_id'] ?? ''));
    $authCode = trim((string)($_GET['authCode'] ?? $_GET['auth_code'] ?? ''));
    $sharedId = trim((string)($_GET['sharedId'] ?? $_GET['shared_id'] ?? ''));
    $trackingId = trim((string)($_GET['tracking_id'] ?? $_SESSION['paypalr_isu_tracking_id'] ?? ''));
    $environment = trim((string)($_GET['env'] ?? $_SESSION['paypalr_isu_environment'] ?? paypalr_detect_environment()));
    
    // Validate environment
    $allowedEnvironments = ['sandbox', 'live'];
    if (!in_array($environment, $allowedEnvironments, true)) {
        $environment = paypalr_detect_environment();
    }
    
    paypalr_log_debug('Completion handler called', [
        'has_merchant_id' => $merchantId !== '',
        'has_auth_code' => $authCode !== '',
        'has_shared_id' => $sharedId !== '',
        'has_tracking_id' => $trackingId !== '',
        'environment' => $environment,
        'all_get_params' => array_keys($_GET),
        'merchant_id_value' => $merchantId,
    ]);
    
    // Retrieve nonce from session (stored during start request)
    $nonce = trim((string)($_SESSION['paypalr_isu_nonce'] ?? ''));
    
    // Retrieve seller_nonce from session (needed for code_verifier in credential exchange)
    $sellerNonce = trim((string)($_SESSION['paypalr_isu_seller_nonce'] ?? ''));
    
    // Store in session for retrieval
    if ($merchantId !== '') {
        $_SESSION['paypalr_isu_merchant_id'] = $merchantId;
    }
    if ($authCode !== '') {
        $_SESSION['paypalr_isu_auth_code'] = $authCode;
    }
    if ($sharedId !== '') {
        $_SESSION['paypalr_isu_shared_id'] = $sharedId;
    }
    if ($trackingId !== '') {
        $_SESSION['paypalr_isu_tracking_id'] = $trackingId;
    }
    $_SESSION['paypalr_isu_environment'] = $environment;
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PayPal Setup Complete</title>
        <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
        <link rel="stylesheet" href="includes/css/paypalr_integrated_signup.css">
    </head>
    <body>
        <div class="nmx-module">
            <div class="nmx-container">
                <div class="nmx-container-header">
                    <h1><span class="completion-icon">✓</span> PayPal Setup Complete!</h1>
                </div>
                
                <div class="nmx-panel">
                    <div class="nmx-panel-heading">
                        <div class="nmx-panel-title">Connection Successful</div>
                    </div>
                    <div class="nmx-panel-body">
                        <div class="nmx-alert nmx-alert-info">
                            <p><strong>Your PayPal account has been successfully connected.</strong></p>
                            <p>Your API credentials are shown below. They are being saved automatically, but you can also copy them manually if needed.</p>
                        </div>
                        
                        <div id="auto-save-status" class="hidden" role="status" aria-live="polite"></div>
                        
                        <div id="credentials-display" class="credentials-box">
                            <h2>Retrieving Your PayPal Credentials...</h2>
                            <p><span class="spinner" role="status" aria-label="Loading"></span> Please wait while we fetch your credentials from PayPal...</p>
                        </div>
                        
                        <div class="nmx-form-actions">
                            <a href="<?php echo htmlspecialchars($modulesPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nmx-btn nmx-btn-primary" id="return-btn">Return to PayPal Module</a>
                            <button type="button" onclick="window.close();" class="nmx-btn nmx-btn-default">Close Window</button>
                        </div>
                    </div>
                </div>
                
                <div class="nmx-footer">
                    <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                        <img src="images/numinix_logo.png" alt="Numinix">
                    </a>
                </div>
            </div>
        </div>
        
        <script>
            window.paypalrISUCompleteConfig = {
                proxyUrl: <?php echo json_encode($proxyUrl); ?>,
                saveUrl: <?php echo json_encode($saveUrl); ?>,
                modulesPageUrl: <?php echo json_encode($modulesPageUrl); ?>,
                securityToken: <?php echo json_encode($securityToken); ?>,
                merchantId: <?php echo json_encode($merchantId); ?>,
                authCode: <?php echo json_encode($authCode); ?>,
                sharedId: <?php echo json_encode($sharedId); ?>,
                trackingId: <?php echo json_encode($trackingId); ?>,
                environment: <?php echo json_encode($environment); ?>,
                nonce: <?php echo json_encode($nonce); ?>,
                sellerNonce: <?php echo json_encode($sellerNonce); ?>
            };
        </script>
        <script src="includes/javascript/paypalr_integrated_signup_complete.js"></script>
    </body>
    </html>
    <?php
}
