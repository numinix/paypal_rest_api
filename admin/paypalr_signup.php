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
$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr.php';
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
        $_SESSION['paypalr_signup'] = [
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
    if (empty($nonce) && !empty($_SESSION['paypalr_signup']['nonce'])) {
        $nonce = $_SESSION['paypalr_signup']['nonce'];
    }
    
    // Get seller_nonce from session if available
    $sellerNonce = '';
    if (!empty($_SESSION['paypalr_signup']['seller_nonce'])) {
        $sellerNonce = $_SESSION['paypalr_signup']['seller_nonce'];
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
    if (empty($nonce) && !empty($_SESSION['paypalr_signup']['nonce'])) {
        $nonce = $_SESSION['paypalr_signup']['nonce'];
    }
    
    // Get seller_nonce from session if available
    $sellerNonce = '';
    if (!empty($_SESSION['paypalr_signup']['seller_nonce'])) {
        $sellerNonce = $_SESSION['paypalr_signup']['seller_nonce'];
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
        $clientIdKey = 'MODULE_PAYMENT_PAYPALR_CLIENTID_L';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALR_SECRET_L';
    } else {
        $clientIdKey = 'MODULE_PAYMENT_PAYPALR_CLIENTID_S';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALR_SECRET_S';
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
        unset($_SESSION['paypalr_signup']);
        
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
    
    $modulesPageUrl = zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL');
    
    ?>
<!DOCTYPE html>
<html <?php echo defined('HTML_PARAMS') ? HTML_PARAMS : 'lang="en"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Signup</title>
    <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .status {
            display: none;
        }
        .status.show { display: block; }
        .credentials {
            background: rgba(4, 191, 191, 0.06);
            border: 1px solid var(--nmx-border);
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
        }
        .credentials h3 {
            margin: 0 0 18px 0;
            color: var(--nmx-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .cred-row {
            margin-bottom: 16px;
        }
        .cred-label {
            font-weight: 600;
            color: var(--nmx-dark);
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .cred-value {
            font-family: 'Monaco', 'Menlo', monospace;
            background: var(--nmx-surface);
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--nmx-border);
            word-break: break-all;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cred-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cred-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        .cred-btn {
            background: var(--nmx-surface);
            border: 1px solid var(--nmx-border);
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            color: var(--nmx-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease-in-out;
        }
        .cred-btn:hover {
            background: var(--nmx-secondary);
            color: #ffffff;
            border-color: var(--nmx-secondary);
        }
        .cred-btn svg {
            width: 16px;
            height: 16px;
        }
        .cred-feedback {
            font-size: 11px;
            color: var(--nmx-secondary);
            margin-left: 8px;
            font-weight: 600;
        }
        .cred-note {
            background: rgba(255, 193, 7, 0.12);
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 14px;
            margin-top: 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
            color: #92400e;
        }
        .cred-note svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }
        .popup-message {
            text-align: center;
            padding: 48px;
        }
        .popup-message h2 {
            color: var(--nmx-secondary);
            margin-bottom: 18px;
            font-weight: 700;
        }
    </style>
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
    
    <script>
    (function() {
        'use strict';
        
        // Get security token from hidden form input (added by zen_draw_form)
        var securityTokenInput = document.querySelector('input[name="securityToken"]');
        var securityToken = securityTokenInput ? securityTokenInput.value : '';
        
        // Check if this is a popup return from PayPal
        var urlParams = new URLSearchParams(window.location.search);
        var merchantId = urlParams.get('merchantIdInPayPal') || urlParams.get('merchantId');
        var trackingId = urlParams.get('tracking_id');
        var authCode = urlParams.get('authCode') || urlParams.get('auth_code');
        var sharedId = urlParams.get('sharedId') || urlParams.get('shared_id');
        var env = urlParams.get('env') || 'sandbox';
        
        // If we have PayPal return params AND we're in a popup, send message to parent
        if (window.opener && !window.opener.closed && (merchantId || trackingId)) {
            console.log('[PayPal Signup] Popup return detected, sending message to parent');
            
            document.getElementById('main-content').style.display = 'none';
            document.getElementById('popup-content').style.display = 'block';
            
            try {
                window.opener.postMessage({
                    event: 'paypal_signup_complete',
                    merchantId: merchantId || '',
                    trackingId: trackingId || '',
                    authCode: authCode || '',
                    sharedId: sharedId || '',
                    env: env
                }, '*');
                
                setTimeout(function() {
                    window.close();
                }, 1500);
            } catch (e) {
                console.error('[PayPal Signup] Failed to send message:', e);
            }
            return;
        }
        
        // Main page logic
        var state = {
            trackingId: '',
            sellerNonce: '',
            nonce: '',  // Session nonce for API calls
            merchantId: '',
            authCode: '',
            sharedId: '',
            env: 'live',
            popup: null,
            credentials: null
        };
        
        var startBtn = document.getElementById('start-btn');
        var saveBtn = document.getElementById('save-btn');
        var envSelect = document.getElementById('environment');
        var statusEl = document.getElementById('status');
        
        function setStatus(text, type) {
            statusEl.textContent = text;
            var alertClass = 'nmx-alert-info';
            if (type === 'success') alertClass = 'nmx-alert-success';
            if (type === 'error') alertClass = 'nmx-alert-error';
            if (type === 'warning') alertClass = 'nmx-alert-warning';
            statusEl.className = 'status nmx-alert ' + alertClass + ' show';
        }
        
        function clearStatus() {
            statusEl.className = 'status nmx-alert';
        }
        
        function maskSecret(value) {
            var length = (value || '').length || 8;
            var maskLength = Math.min(Math.max(length, 12), 28);
            return Array(maskLength + 1).join('•');
        }
        
        function copyToClipboard(value) {
            if (!value) {
                return Promise.reject(new Error('Nothing to copy'));
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(value);
            }
            return new Promise(function (resolve, reject) {
                var textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    var successful = document.execCommand('copy');
                    document.body.removeChild(textarea);
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error('Unable to copy'));
                    }
                } catch (error) {
                    document.body.removeChild(textarea);
                    reject(error);
                }
            });
        }
        
        function showCredentials(credentials, env) {
            document.getElementById('start-section').style.display = 'none';
            document.getElementById('credentials-section').style.display = 'block';
            document.getElementById('cred-env').textContent = env === 'live' ? 'Production' : 'Sandbox';
            document.getElementById('cred-client-id').textContent = credentials.client_id;
            
            // Store actual values in data attributes for copy/reveal
            document.getElementById('cred-client-id-row').setAttribute('data-value', credentials.client_id);
            document.getElementById('cred-client-secret-row').setAttribute('data-value', credentials.client_secret);
            
            // Show masked secret initially
            var secretEl = document.getElementById('cred-client-secret');
            secretEl.textContent = maskSecret(credentials.client_secret);
            secretEl.setAttribute('data-masked', 'true');
            
            state.credentials = credentials;
            state.env = env;
            
            // Close popup if still open
            if (state.popup && !state.popup.closed) {
                console.log('[PayPal Signup] Closing popup after credentials received');
                try {
                    state.popup.close();
                } catch (e) {
                    console.log('[PayPal Signup] Could not close popup:', e);
                }
                state.popup = null;
            }
            
            // Auto-save credentials to configuration
            console.log('[PayPal Signup] Auto-saving credentials to configuration...');
            saveCredentials();
        }
        
        // Handle reveal/copy button clicks
        document.addEventListener('click', function(event) {
            var btn = event.target.closest('[data-action]');
            if (!btn) return;
            
            var action = btn.getAttribute('data-action');
            var target = btn.getAttribute('data-target');
            if (!target) return;
            
            var row = document.getElementById(target + '-row');
            var textEl = document.getElementById(target);
            var feedbackEl = document.getElementById(target + '-feedback');
            var value = row ? row.getAttribute('data-value') : '';
            
            if (action === 'copy') {
                copyToClipboard(value)
                    .then(function() {
                        if (feedbackEl) {
                            feedbackEl.textContent = 'Copied!';
                            setTimeout(function() {
                                feedbackEl.textContent = '';
                            }, 1500);
                        }
                    })
                    .catch(function() {
                        if (feedbackEl) {
                            feedbackEl.textContent = 'Copy failed';
                            setTimeout(function() {
                                feedbackEl.textContent = '';
                            }, 1500);
                        }
                    });
            } else if (action === 'reveal') {
                var isMasked = textEl.getAttribute('data-masked') === 'true';
                if (isMasked) {
                    textEl.textContent = value;
                    textEl.setAttribute('data-masked', 'false');
                } else {
                    textEl.textContent = maskSecret(value);
                    textEl.setAttribute('data-masked', 'true');
                }
            }
        });
        
        // Get the form's action URL - this is the safest way to POST back to this page
        var signupForm = document.getElementById('paypal-signup-form');
        var formActionUrl = signupForm ? signupForm.action : window.location.href;
        
        function apiCall(ajaxAction, data) {
            var formData = new FormData();
            formData.append('action', 'ajax'); // Use 'ajax' to avoid Zen Cart action conflicts
            formData.append('ajax_action', ajaxAction); // The actual action
            formData.append('securityToken', securityToken); // CSRF protection
            Object.keys(data).forEach(function(key) {
                formData.append(key, data[key]);
            });
            
            return fetch(formActionUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(formData)
            }).then(function(r) { return r.json(); });
        }
        
        function startOnboarding() {
            startBtn.disabled = true;
            state.env = envSelect.value;
            setStatus('Starting PayPal setup...', 'info');
            
            apiCall('start', { env: state.env })
                .then(function(response) {
                    if (!response.success) {
                        throw new Error(response.message || 'Failed to start');
                    }
                    
                    var data = response.data || {};
                    state.trackingId = data.tracking_id || '';
                    state.sellerNonce = data.seller_nonce || '';
                    state.nonce = data.nonce || '';  // Session nonce for subsequent API calls
                    
                    console.log('[PayPal Signup] Start response - tracking_id:', state.trackingId, 'has nonce:', !!state.nonce);
                    
                    var redirectUrl = data.redirect_url;
                    if (!redirectUrl) {
                        throw new Error('No redirect URL received');
                    }
                    
                    // Add displayMode=minibrowser to URL
                    redirectUrl += (redirectUrl.indexOf('?') === -1 ? '?' : '&') + 'displayMode=minibrowser';
                    
                    // Open popup
                    openPopup(redirectUrl);
                })
                .catch(function(error) {
                    setStatus(error.message || 'Failed to start onboarding', 'error');
                    startBtn.disabled = false;
                });
        }
        
        function openPopup(url) {
            var width = 960;
            var height = 720;
            var left = window.screenX + Math.max((window.outerWidth - width) / 2, 0);
            var top = window.screenY + Math.max((window.outerHeight - height) / 2, 0);
            var features = 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes';
            
            state.popup = window.open(url, 'paypalSignup', features);
            
            if (!state.popup || state.popup.closed) {
                setStatus('Please allow popups for this site to continue.', 'error');
                startBtn.disabled = false;
                return;
            }
            
            setStatus('Complete the PayPal setup in the popup window...', 'info');
            
            // Monitor popup
            var checkInterval = setInterval(function() {
                if (!state.popup || state.popup.closed) {
                    clearInterval(checkInterval);
                    state.popup = null;
                    
                    if (state.credentials) {
                        // Already have credentials
                        return;
                    }
                    
                    if (state.merchantId || state.trackingId) {
                        setStatus('Processing your account...', 'info');
                        pollForCredentials();
                    } else {
                        setStatus('Popup closed. Click Start to try again.', 'info');
                        startBtn.disabled = false;
                    }
                }
            }, 1000);
        }
        
        function pollForCredentials(attempts) {
            attempts = attempts || 0;
            var maxAttempts = 20;
            
            if (attempts >= maxAttempts) {
                setStatus('Could not retrieve credentials. Please try again.', 'error');
                startBtn.disabled = false;
                return;
            }
            
            console.log('[PayPal Signup] Polling for credentials, attempt:', attempts + 1, 'state:', {
                tracking_id: state.trackingId,
                merchant_id: state.merchantId,
                authCode: state.authCode ? '(present)' : '(empty)',
                sharedId: state.sharedId ? '(present)' : '(empty)',
                nonce: state.nonce ? '(present)' : '(empty)'
            });
            
            apiCall('finalize', {
                tracking_id: state.trackingId,
                merchant_id: state.merchantId,
                authCode: state.authCode,
                sharedId: state.sharedId,
                nonce: state.nonce,  // Session nonce required for API validation
                env: state.env
            })
            .then(function(response) {
                console.log('[PayPal Signup] Finalize response:', response);
                
                if (!response.success) {
                    throw new Error(response.message || 'Failed to finalize');
                }
                
                var data = response.data || {};
                
                if (data.credentials && data.credentials.client_id) {
                    setStatus('Credentials received!', 'success');
                    showCredentials(data.credentials, data.environment || state.env);
                    return;
                }
                
                // Still waiting
                setStatus('Waiting for PayPal... (attempt ' + (attempts + 1) + '/' + maxAttempts + ')', 'info');
                setTimeout(function() {
                    pollForCredentials(attempts + 1);
                }, 3000);
            })
            .catch(function(error) {
                console.log('[PayPal Signup] Finalize error:', error.message);
                setStatus('Error: ' + error.message + '. Retrying...', 'info');
                setTimeout(function() {
                    pollForCredentials(attempts + 1);
                }, 3000);
            });
        }
        
        function saveCredentials() {
            if (!state.credentials) {
                setStatus('No credentials to save', 'error');
                return;
            }
            
            saveBtn.disabled = true;
            setStatus('Saving credentials...', 'info');
            
            apiCall('save_credentials', {
                client_id: state.credentials.client_id,
                client_secret: state.credentials.client_secret,
                environment: state.env
            })
            .then(function(response) {
                if (!response.success) {
                    throw new Error(response.message || 'Failed to save');
                }
                
                setStatus('Credentials saved successfully! You can now return to the modules page.', 'success');
            })
            .catch(function(error) {
                setStatus('Failed to save: ' + error.message, 'error');
                saveBtn.disabled = false;
            });
        }
        
        // Helper function to extract value from payload with multiple possible key names
        function getPayloadValue(payload, keys) {
            for (var i = 0; i < keys.length; i++) {
                if (payload[keys[i]] !== undefined && payload[keys[i]] !== null && payload[keys[i]] !== '') {
                    return payload[keys[i]];
                }
            }
            return null;
        }
        
        // Listen for messages from popup and PayPal
        window.addEventListener('message', function(event) {
            console.log('[PayPal Signup] Received message:', JSON.stringify(event.data));
            
            if (!event.data) {
                return;
            }
            
            // Parse JSON string if event.data is a string (PayPal sends JSON-stringified data)
            var payload = event.data;
            if (typeof payload === 'string') {
                try {
                    payload = JSON.parse(payload);
                    console.log('[PayPal Signup] Parsed string payload to object:', payload);
                } catch (e) {
                    console.log('[PayPal Signup] Could not parse message as JSON, ignoring');
                    return;
                }
            }
            
            if (typeof payload !== 'object' || payload === null) {
                console.log('[PayPal Signup] Payload is not an object, ignoring');
                return;
            }
            
            // Extract values using multiple possible key names (PayPal uses various formats)
            var authCode = getPayloadValue(payload, ['authCode', 'auth_code', 'onboardedCompleteToken', 'onboarding_complete_token']);
            var sharedId = getPayloadValue(payload, ['sharedId', 'shared_id', 'sharedID']);
            var merchantId = getPayloadValue(payload, ['merchantId', 'merchantID', 'merchant_id', 'merchantIdInPayPal']);
            var trackingId = getPayloadValue(payload, ['tracking_id', 'trackingId']);
            
            // Check if this is our custom event from popup return
            var isOurEvent = payload.event === 'paypal_signup_complete';
            
            // Check if this is PayPal's direct callback (has onboardedCompleteToken and sharedId)
            var isPayPalCallback = !!(authCode && sharedId);
            
            // Check if this is PayPal's updateParent message (contains returnUrl)
            var isUpdateParent = payload.updateParent === true;
            
            console.log('[PayPal Signup] Message analysis:', {
                isOurEvent: isOurEvent,
                isPayPalCallback: isPayPalCallback,
                isUpdateParent: isUpdateParent,
                hasAuthCode: !!authCode,
                hasSharedId: !!sharedId,
                hasMerchantId: !!merchantId,
                hasTrackingId: !!trackingId
            });
            
            // If we have authCode and sharedId from PayPal's direct callback, capture them
            if (authCode) {
                state.authCode = authCode;
                console.log('[PayPal Signup] Captured authCode from PayPal callback');
            }
            if (sharedId) {
                state.sharedId = sharedId;
                console.log('[PayPal Signup] Captured sharedId from PayPal callback');
            }
            if (merchantId) {
                state.merchantId = merchantId;
            }
            if (trackingId && !state.trackingId) {
                state.trackingId = trackingId;
            }
            
            // Handle updateParent message - PayPal signals user clicked "Return to Merchant"
            // This means the mini-browser should close
            if (isUpdateParent) {
                console.log('[PayPal Signup] updateParent message received - closing popup');
                if (state.popup && !state.popup.closed) {
                    try {
                        state.popup.close();
                    } catch (e) {
                        console.log('[PayPal Signup] Could not close popup:', e);
                    }
                    state.popup = null;
                }
                // If we already have credentials, we're done
                if (state.credentials) {
                    return;
                }
                // Otherwise, continue polling if we have tracking data
                if (state.trackingId && !state.credentials) {
                    setStatus('Processing your account...', 'info');
                    pollForCredentials();
                }
                return;
            }
            
            // If this is a completion event (either our custom event or PayPal's direct callback with credentials)
            if (isOurEvent || isPayPalCallback) {
                console.log('[PayPal Signup] Completion event detected, state:', {
                    tracking_id: state.trackingId,
                    merchant_id: state.merchantId,
                    authCode: state.authCode ? '(present)' : '(empty)',
                    sharedId: state.sharedId ? '(present)' : '(empty)'
                });
                
                setStatus('PayPal setup complete. Retrieving credentials...', 'info');
                pollForCredentials();
            }
        });
        
        // Event listeners
        startBtn.addEventListener('click', startOnboarding);
        saveBtn.addEventListener('click', saveCredentials);
        
    })();
    </script>
</body>
</html>
    <?php
}
