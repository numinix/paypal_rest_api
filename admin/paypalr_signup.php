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
    if ($env === 'live') {
        $clientIdKey = 'MODULE_PAYMENT_PAYPALR_CLIENTID_LIVE';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALR_SECRET_LIVE';
    } else {
        $clientIdKey = 'MODULE_PAYMENT_PAYPALR_CLIENTID_SANDBOX';
        $clientSecretKey = 'MODULE_PAYMENT_PAYPALR_SECRET_SANDBOX';
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Signup</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f2f5;
            line-height: 1.5;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        h1 {
            color: #1a1a2e;
            margin: 0 0 8px 0;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            margin: 0 0 24px 0;
        }
        .status {
            padding: 14px 16px;
            margin: 16px 0;
            border-radius: 8px;
            display: none;
        }
        .status.info { background: #e3f2fd; color: #1565c0; }
        .status.success { background: #e8f5e9; color: #2e7d32; }
        .status.error { background: #ffebee; color: #c62828; }
        .status.show { display: block; }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            font-size: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .btn {
            background: #0070ba;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        .btn:hover { background: #005ea6; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            margin-left: 12px;
        }
        .btn-secondary:hover { background: #e0e0e0; }
        .credentials {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials h3 {
            margin: 0 0 16px 0;
            color: #2e7d32;
        }
        .cred-row {
            margin-bottom: 12px;
        }
        .cred-label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 4px;
        }
        .cred-value {
            font-family: 'Monaco', 'Menlo', monospace;
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            word-break: break-all;
            font-size: 13px;
        }
        .actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .back-link {
            color: #0070ba;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .popup-message {
            text-align: center;
            padding: 40px;
        }
        .popup-message h2 {
            color: #2e7d32;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php 
        // Use zen_draw_form to create a form with automatic securityToken hidden input
        echo zen_draw_form('paypal_signup', basename($_SERVER['SCRIPT_NAME']), '', 'post', 'id="paypal-signup-form"');
        ?>
        <div id="main-content">
            <h1>PayPal Account Setup</h1>
            <p class="subtitle">Connect your PayPal account to accept payments</p>
            
            <div id="status" class="status"></div>
            
            <div id="start-section">
                <div class="form-group">
                    <label for="environment">Environment</label>
                    <select id="environment" name="environment">
                        <option value="live">Production (Live)</option>
                        <option value="sandbox">Sandbox (Testing)</option>
                    </select>
                </div>
                
                <div class="actions">
                    <button type="button" id="start-btn" class="btn">Start PayPal Setup</button>
                    <a href="<?php echo $modulesPageUrl; ?>" class="back-link">← Back to Modules</a>
                </div>
            </div>
            
            <div id="credentials-section" style="display: none;">
                <div class="credentials">
                    <h3>✓ PayPal Account Connected</h3>
                    <div class="cred-row">
                        <span class="cred-label">Environment</span>
                        <div class="cred-value" id="cred-env"></div>
                    </div>
                    <div class="cred-row">
                        <span class="cred-label">Client ID</span>
                        <div class="cred-value" id="cred-client-id"></div>
                    </div>
                    <div class="cred-row">
                        <span class="cred-label">Client Secret</span>
                        <div class="cred-value" id="cred-client-secret"></div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="button" id="save-btn" class="btn">Save to Configuration</button>
                    <a href="<?php echo $modulesPageUrl; ?>" class="back-link">Return to Modules</a>
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
            statusEl.className = 'status ' + (type || 'info') + ' show';
        }
        
        function clearStatus() {
            statusEl.className = 'status';
        }
        
        function showCredentials(credentials, env) {
            document.getElementById('start-section').style.display = 'none';
            document.getElementById('credentials-section').style.display = 'block';
            document.getElementById('cred-env').textContent = env === 'live' ? 'Production' : 'Sandbox';
            document.getElementById('cred-client-id').textContent = credentials.client_id;
            document.getElementById('cred-client-secret').textContent = credentials.client_secret;
            state.credentials = credentials;
            state.env = env;
        }
        
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
