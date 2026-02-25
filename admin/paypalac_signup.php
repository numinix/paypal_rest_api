<?php
/**
 * PayPal Simplified Signup Page for Admin
 *
 * This page follows the same approach as numinix.com's paypal_signup page:
 * 1. Opens PayPal in a popup window (not mini-browser)
 * 2. The same page serves as both the main page AND the popup return handler
 * 3. When loaded in popup with PayPal params, sends postMessage to parent and closes
 * 4. Parent receives credentials and saves them to configuration table
 */

// Start output buffering to prevent any accidental output before JSON response
ob_start();

$autoloaderPath = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

// Check admin login
if (function_exists('zen_admin_check_login')) {
    $isLoggedIn = zen_admin_check_login();
} else {
    $isLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;
}

$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isLoggedIn) {
    if ($isAjaxRequest) {
        ob_end_clean(); // Clear any buffered output
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and log in.']);
        exit;
    }
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

// Load language file if exists
$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalac.php';
if (file_exists($languageFile)) {
    include $languageFile;
}

$action = strtolower(trim($_REQUEST['action'] ?? ''));

// CSRF token validation function for AJAX POST requests
function validateSecurityToken(): bool
{
    $sessionToken = $_SESSION['securityToken'] ?? '';
    $requestToken = $_POST['securityToken'] ?? '';
    
    if (empty($sessionToken) || empty($requestToken)) {
        return false;
    }
    
    return hash_equals($sessionToken, $requestToken);
}

// Handle AJAX actions - use action=ajax to avoid Zen Cart's action handling conflicts
// The actual action is in ajax_action parameter
if ($isAjaxRequest && $action === 'ajax') {
    ob_end_clean(); // Clear any buffered output before JSON response
    header('Content-Type: application/json');
    
    // Validate CSRF token for all AJAX actions
    if (!validateSecurityToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    $ajaxAction = strtolower(trim($_POST['ajax_action'] ?? ''));
    
    if ($ajaxAction === 'start') {
        handleStartAction();
        exit;
    }
    
    if ($ajaxAction === 'finalize') {
        handleFinalizeAction();
        exit;
    }
    
    if ($ajaxAction === 'status') {
        handleStatusAction();
        exit;
    }
    
    if ($ajaxAction === 'save_credentials') {
        handleSaveCredentials();
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown ajax action: ' . $ajaxAction]);
    exit;
}

// For page rendering, end output buffering and render page
ob_end_clean();

// Render the page - zen_draw_form handles securityToken automatically
renderSignupPage();
exit;

/**
 * Handle the start action - initiates onboarding with numinix.com
 */
function handleStartAction(): void
{
    $env = strtolower(trim($_POST['env'] ?? 'sandbox'));
    if ($env !== 'live' && $env !== 'sandbox') {
        $env = 'sandbox';
    }
    
    // Build return URL - this same page will receive the PayPal redirect
    $returnUrl = getSignupPageUrl(['env' => $env]);
    
    $payload = [
        'nxp_paypal_action' => 'start',
        'env' => $env,
        'client_return_url' => $returnUrl,
    ];
    
    $response = callNuminixApi($payload);
    
    if (!$response['success']) {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to start onboarding']);
        return;
    }
    
    $data = $response['data'] ?? [];
    
    // Store tracking data in session
    if (!empty($data['tracking_id'])) {
        $_SESSION['paypalac_signup'] = [
            'tracking_id' => $data['tracking_id'],
            'seller_nonce' => $data['seller_nonce'] ?? '',
            'nonce' => $data['nonce'] ?? '',  // Session nonce for API validation
            'env' => $env,
            'started_at' => time(),
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'tracking_id' => $data['tracking_id'] ?? '',
            'redirect_url' => $data['redirect_url'] ?? $data['action_url'] ?? '',
            'seller_nonce' => $data['seller_nonce'] ?? '',
            'nonce' => $data['nonce'] ?? '',  // Session nonce for subsequent API calls
        ],
    ]);
}

/**
 * Handle the finalize action - exchanges authCode/sharedId for credentials
 */
function handleFinalizeAction(): void
{
    $trackingId = trim($_POST['tracking_id'] ?? '');
    $merchantId = trim($_POST['merchant_id'] ?? '');
    $authCode = trim($_POST['authCode'] ?? $_POST['auth_code'] ?? '');
    $sharedId = trim($_POST['sharedId'] ?? $_POST['shared_id'] ?? '');
    $env = strtolower(trim($_POST['env'] ?? 'sandbox'));
    $nonce = trim($_POST['nonce'] ?? '');  // Session nonce from client
    
    // Get nonce from session if not provided by client
    if (empty($nonce) && !empty($_SESSION['paypalac_signup']['nonce'])) {
        $nonce = $_SESSION['paypalac_signup']['nonce'];
    }
    
    // Get seller_nonce from session if available
    $sellerNonce = '';
    if (!empty($_SESSION['paypalac_signup']['seller_nonce'])) {
        $sellerNonce = $_SESSION['paypalac_signup']['seller_nonce'];
    }
    
    $payload = [
        'nxp_paypal_action' => 'finalize',
        'tracking_id' => $trackingId,
        'merchant_id' => $merchantId,
        'authCode' => $authCode,
        'sharedId' => $sharedId,
        'seller_nonce' => $sellerNonce,
        'nonce' => $nonce,  // Session nonce for API validation
        'env' => $env,
    ];
    
    $response = callNuminixApi($payload);
    
    echo json_encode($response);
}

/**
 * Handle the status action - polls for credentials
 */
function handleStatusAction(): void
{
    $trackingId = trim($_POST['tracking_id'] ?? '');
    $merchantId = trim($_POST['merchant_id'] ?? '');
    $authCode = trim($_POST['authCode'] ?? $_POST['auth_code'] ?? '');
    $sharedId = trim($_POST['sharedId'] ?? $_POST['shared_id'] ?? '');
    $env = strtolower(trim($_POST['env'] ?? 'sandbox'));
    $nonce = trim($_POST['nonce'] ?? '');  // Session nonce from client
    
    // Get nonce from session if not provided by client
    if (empty($nonce) && !empty($_SESSION['paypalac_signup']['nonce'])) {
        $nonce = $_SESSION['paypalac_signup']['nonce'];
    }
    
    // Get seller_nonce from session if available
    $sellerNonce = '';
    if (!empty($_SESSION['paypalac_signup']['seller_nonce'])) {
        $sellerNonce = $_SESSION['paypalac_signup']['seller_nonce'];
    }
    
    $payload = [
        'nxp_paypal_action' => 'status',
        'tracking_id' => $trackingId,
        'merchant_id' => $merchantId,
        'authCode' => $authCode,
        'sharedId' => $sharedId,
        'seller_nonce' => $sellerNonce,
        'nonce' => $nonce,  // Session nonce for API validation
        'env' => $env,
    ];
    
    $response = callNuminixApi($payload);
    
    echo json_encode($response);
}

/**
 * Handle saving credentials to configuration
 */
function handleSaveCredentials(): void
{
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $env = strtolower(trim($_POST['environment'] ?? 'sandbox'));
    
    if (empty($clientId) || empty($clientSecret)) {
        echo json_encode(['success' => false, 'message' => 'Missing credentials']);
        return;
    }
    
    // Validate credential format (basic sanity check)
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $clientId)) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID format']);
        return;
    }
    
    // Determine which config keys to update based on environment
    // Use the correct key names: _L for live, _S for sandbox
    if ($env === 'live') {
        $clientIdKey = 'MODULE_PAYMENT_PAYPALAC_CLIENTID_L';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALAC_SECRET_L';
    } else {
        $clientIdKey = 'MODULE_PAYMENT_PAYPALAC_CLIENTID_S';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALAC_SECRET_S';
    }
    
    // Sanitize values for database using zen_db_input if available
    if (function_exists('zen_db_input')) {
        $clientId = zen_db_input($clientId);
        $clientSecret = zen_db_input($clientSecret);
        $clientIdKey = zen_db_input($clientIdKey);
        $clientSecretKey = zen_db_input($clientSecretKey);
    }
    
    // Update configuration values
    global $db;
    
    try {
        // Update client ID
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $clientId . "', last_modified = NOW() WHERE configuration_key = '" . $clientIdKey . "'"
        );
        
        // Update client secret
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $clientSecret . "', last_modified = NOW() WHERE configuration_key = '" . $clientSecretKey . "'"
        );
        
        // Clear session data
        unset($_SESSION['paypalac_signup']);
        
        echo json_encode(['success' => true, 'message' => 'Credentials saved successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save credentials: ' . $e->getMessage()]);
    }
}

/**
 * Call the Numinix API
 */
function callNuminixApi(array $payload): array
{
    $apiUrl = 'https://www.numinix.com/api/paypal_onboarding.php';
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'API request failed: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'API returned HTTP ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'message' => 'Invalid API response'];
    }
    
    return $data;
}

/**
 * Get the URL to this signup page
 */
function getSignupPageUrl(array $params = []): string
{
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
        . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    
    if (!empty($params)) {
        $baseUrl .= '?' . http_build_query($params);
    }
    
    return $baseUrl;
}

/**
 * Render the signup page
 */
function renderSignupPage(): void
{
    // Check if this is a popup return from PayPal
    $merchantId = $_GET['merchantIdInPayPal'] ?? $_GET['merchantId'] ?? '';
    $trackingId = $_GET['tracking_id'] ?? '';
    $env = $_GET['env'] ?? 'sandbox';
    
    // Detect if we're in a popup (will be checked by JavaScript)
    $isPopupReturn = !empty($merchantId) || !empty($trackingId);
    
    $modulesPageUrl = zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalac', 'SSL');
    
    ?>
<!DOCTYPE html>
<html <?php echo defined('HTML_PARAMS') ? HTML_PARAMS : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Signup</title>
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    <link rel="stylesheet" href="includes/css/paypalac_signup.css">
</head>
<body>
<div class="nmx-module">
    <div class="nmx-container">
        <?php 
        // Use zen_draw_form to create a form with automatic securityToken hidden input
        echo zen_draw_form('paypal_signup', basename($_SERVER['SCRIPT_NAME']), '', 'post', 'id="paypal-signup-form"');
        ?>
        <div id="main-content">
            <div class="nmx-container-header">
                <h1>PayPal Account Setup</h1>
                <p class="nmx-container-subtitle">Connect your PayPal account to accept payments</p>
            </div>
            
            <div id="status" class="status nmx-alert"></div>
            
            <div id="start-section">
                <div class="nmx-panel">
                    <div class="nmx-panel-heading">
                        <div class="nmx-panel-title">Environment Selection</div>
                    </div>
                    <div class="nmx-panel-body">
                        <div class="nmx-form-group">
                            <label for="environment">Environment</label>
                            <select id="environment" name="environment" class="nmx-form-control">
                                <option value="live">Production (Live)</option>
                                <option value="sandbox">Sandbox (Testing)</option>
                            </select>
                        </div>
                        
                        <div class="nmx-form-actions">
                            <button type="button" id="start-btn" class="nmx-btn nmx-btn-primary">Start PayPal Setup</button>
                            <a href="<?php echo $modulesPageUrl; ?>" class="nmx-btn nmx-btn-default">← Back to Modules</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="credentials-section" style="display: none;">
                <div class="nmx-panel">
                    <div class="nmx-panel-heading">
                        <div class="nmx-panel-title">✓ PayPal Account Connected</div>
                    </div>
                    <div class="nmx-panel-body">
                        <div class="credentials">
                            <h3>API Credentials</h3>
                            <div class="cred-row">
                                <span class="cred-label">Environment</span>
                                <div class="cred-value"><span class="cred-text" id="cred-env"></span></div>
                            </div>
                            <div class="cred-row" id="cred-client-id-row" data-value="">
                                <span class="cred-label">Client ID</span>
                                <div class="cred-value">
                                    <span class="cred-text" id="cred-client-id"></span>
                                    <div class="cred-actions">
                                        <button type="button" class="cred-btn" data-action="copy" data-target="cred-client-id" title="Copy Client ID">
                                            <svg viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.25 6.25h-5a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5.75 11.75H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v.54" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </button>
                                    </div>
                                    <span class="cred-feedback" id="cred-client-id-feedback"></span>
                                </div>
                            </div>
                            <div class="cred-row" id="cred-client-secret-row" data-value="">
                                <span class="cred-label">Client Secret</span>
                                <div class="cred-value">
                                    <span class="cred-text" id="cred-client-secret" data-masked="true"></span>
                                    <div class="cred-actions">
                                        <button type="button" class="cred-btn" data-action="reveal" data-target="cred-client-secret" title="Show/Hide Secret">
                                            <svg viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 9s2.25-4.5 7.5-4.5S16.5 9 16.5 9s-2.25 4.5-7.5 4.5S1.5 9 1.5 9Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11.25 9a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </button>
                                        <button type="button" class="cred-btn" data-action="copy" data-target="cred-client-secret" title="Copy Secret">
                                            <svg viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.25 6.25h-5a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5.75 11.75H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v.54" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </button>
                                    </div>
                                    <span class="cred-feedback" id="cred-client-secret-feedback"></span>
                                </div>
                            </div>
                            <div class="cred-note">
                                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 1.33334L1.33334 14.6667H14.6667L8 1.33334Z" stroke="#92400e" stroke-width="1.5" stroke-linejoin="round"></path>
                                    <path d="M8 6.5V9.83334" stroke="#92400e" stroke-width="1.5" stroke-linecap="round"></path>
                                    <circle cx="8" cy="11.8333" r="0.666667" fill="#92400e"></circle>
                                </svg>
                                <span>Store these credentials securely. Do not share them publicly.</span>
                            </div>
                        </div>
                        
                        <div class="nmx-form-actions">
                            <button type="button" id="save-btn" class="nmx-btn nmx-btn-success">Save to Configuration</button>
                            <a href="<?php echo $modulesPageUrl; ?>" class="nmx-btn nmx-btn-default">Return to Modules</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="popup-content" style="display: none;">
            <div class="popup-message">
                <h2>PayPal Setup Complete</h2>
                <p>Processing your account details...</p>
                <p style="color: #666; font-size: 14px;">This window will close automatically.</p>
            </div>
        </div>
        </form>
        
        <div class="nmx-footer">
            <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                <img src="images/numinix_logo.png" alt="Numinix">
            </a>
        </div>
    </div>
</div>
    
    <script src="includes/javascript/paypalac_signup.js"></script>
</body>
</html>
    <?php
}
