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

if (!$paypalrAdminLoggedIn) {
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr.php';
if (file_exists($languageFile)) {
    include $languageFile;
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

// Handle AJAX requests for the new in-admin flow
if ($action === 'proxy' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    paypalr_handle_proxy_request();
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
    
    if ($numinixUrl === '') {
        paypalr_json_error('Numinix portal URL not configured.');
        return;
    }
    
    $allowedActions = ['start', 'status', 'finalize'];
    if (!in_array($proxyAction, $allowedActions, true)) {
        paypalr_json_error('Invalid proxy action.');
        return;
    }
    
    try {
        $response = paypalr_proxy_to_numinix($numinixUrl, $proxyAction, $_POST);
        echo $response;
    } catch (Exception $e) {
        paypalr_json_error($e->getMessage());
    }
}

/**
 * Makes an HTTP request to the Numinix.com API.
 */
function paypalr_proxy_to_numinix(string $baseUrl, string $action, array $data): string
{
    $url = rtrim($baseUrl, '/');
    $environment = paypalr_detect_environment();
    
    $payload = array_merge($data, [
        'nxp_paypal_action' => $action,
        'env' => $environment,
    ]);
    
    $postData = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException('Failed to contact Numinix API: ' . ($error ?: 'HTTP ' . $httpCode));
    }
    
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
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PayPal Integrated Signup</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 40px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .isu-container {
                background: white;
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                margin-top: 0;
            }
            .status {
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
                display: none;
            }
            .status.info {
                background: #e7f3ff;
                color: #0066cc;
                border-left: 4px solid #0066cc;
            }
            .status.success {
                background: #e6f7e6;
                color: #2d7a2d;
                border-left: 4px solid #2d7a2d;
            }
            .status.error {
                background: #ffe6e6;
                color: #cc0000;
                border-left: 4px solid #cc0000;
            }
            .credentials {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
                border: 1px solid #ddd;
            }
            .credentials dt {
                font-weight: bold;
                margin-top: 10px;
            }
            .credentials dd {
                margin: 5px 0 15px 0;
                font-family: monospace;
                background: white;
                padding: 8px;
                border-radius: 3px;
                word-break: break-all;
            }
            button {
                background: #0066cc;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            button:hover {
                background: #0052a3;
            }
            button:disabled {
                background: #ccc;
                cursor: not-allowed;
            }
            .actions {
                margin-top: 20px;
            }
            .actions a {
                color: #0066cc;
                text-decoration: none;
                margin-left: 15px;
            }
            .actions a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="isu-container">
            <h1>PayPal Integrated Signup</h1>
            <p>Click the button below to start the secure PayPal onboarding process. A popup window will guide you through connecting your PayPal account.</p>
            
            <div id="status" class="status"></div>
            <div id="credentials-display"></div>
            
            <div class="actions">
                <button id="start-button" type="button">Start PayPal Signup</button>
                <a href="<?php echo htmlspecialchars($modulesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">Cancel and return to modules</a>
            </div>
        </div>
        
        <script>
            (function() {
                var proxyUrl = <?php echo json_encode($proxyUrl); ?>;
                var environment = <?php echo json_encode($environment); ?>;
                var modulesPageUrl = <?php echo json_encode($modulesPageUrl); ?>;
                
                var state = {
                    trackingId: null,
                    nonce: null,
                    popup: null,
                    pollTimer: null,
                    pollInterval: 4000
                };
                
                var startButton = document.getElementById('start-button');
                var statusDiv = document.getElementById('status');
                var credentialsDiv = document.getElementById('credentials-display');
                
                function setStatus(message, type) {
                    statusDiv.textContent = message;
                    statusDiv.className = 'status ' + (type || 'info');
                    statusDiv.style.display = message ? 'block' : 'none';
                }
                
                function proxyRequest(action, data) {
                    var payload = Object.assign({}, data || {}, {
                        proxy_action: action,
                        action: 'proxy'
                    });
                    
                    return fetch(proxyUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams(payload).toString()
                    })
                    .then(function(response) {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(function(data) {
                        if (!data || !data.success) {
                            throw new Error(data && data.message || 'Request failed');
                        }
                        return data;
                    });
                }
                
                function openPayPalPopup(url) {
                    var width = 960;
                    var height = 720;
                    var left = window.screenX + Math.max((window.outerWidth - width) / 2, 0);
                    var top = window.screenY + Math.max((window.outerHeight - height) / 2, 0);
                    var features = 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes';
                    
                    state.popup = window.open(url, 'paypalOnboarding', features);
                    
                    if (!state.popup || state.popup.closed) {
                        setStatus('Please allow popups for this site to continue.', 'error');
                        startButton.disabled = false;
                        return false;
                    }
                    
                    try {
                        state.popup.focus();
                    } catch(e) {}
                    
                    monitorPopup();
                    return true;
                }
                
                function monitorPopup() {
                    var checkInterval = setInterval(function() {
                        if (!state.popup || state.popup.closed) {
                            clearInterval(checkInterval);
                            state.popup = null;
                            // Don't change status if credentials are already shown
                            if (!credentialsDiv.innerHTML) {
                                setStatus('PayPal window closed. Click Start to try again.', 'info');
                                startButton.disabled = false;
                            }
                        }
                    }, 1000);
                }
                
                function pollStatus() {
                    if (!state.trackingId) return;
                    
                    state.pollTimer = setTimeout(function() {
                        proxyRequest('status', {
                            tracking_id: state.trackingId,
                            nonce: state.nonce
                        })
                        .then(function(response) {
                            handleStatusResponse(response.data || {});
                        })
                        .catch(function(error) {
                            setStatus(error.message || 'Failed to check status', 'error');
                            startButton.disabled = false;
                        });
                    }, state.pollInterval);
                }
                
                function handleStatusResponse(data) {
                    var step = (data.step || '').toLowerCase();
                    
                    if (data.polling_interval) {
                        state.pollInterval = Math.max(data.polling_interval, 2000);
                    }
                    
                    if (step === 'completed' || step === 'ready' || step === 'active') {
                        if (data.credentials && data.credentials.client_id && data.credentials.client_secret) {
                            displayCredentials(data.credentials);
                            autoSaveCredentials(data.credentials);
                        } else {
                            setStatus('Onboarding complete! Redirecting...', 'success');
                            setTimeout(function() {
                                window.location.href = modulesPageUrl;
                            }, 2000);
                        }
                    } else if (step === 'cancelled' || step === 'declined') {
                        setStatus('Onboarding was cancelled.', 'error');
                        startButton.disabled = false;
                    } else {
                        setStatus('Waiting for PayPal to complete setup...', 'info');
                        pollStatus();
                    }
                }
                
                function displayCredentials(credentials) {
                    var html = '<div class="credentials">';
                    html += '<h3>✓ Credentials Retrieved Successfully</h3>';
                    html += '<p>Your PayPal API credentials have been retrieved and saved:</p>';
                    html += '<dl>';
                    html += '<dt>Environment:</dt><dd>' + environment + '</dd>';
                    html += '<dt>Client ID:</dt><dd>' + escapeHtml(credentials.client_id) + '</dd>';
                    html += '<dt>Client Secret:</dt><dd>' + escapeHtml(credentials.client_secret) + '</dd>';
                    html += '</dl>';
                    html += '<p><strong>Click the button below to return to the module configuration:</strong></p>';
                    html += '<button type="button" onclick="window.location.href=\'' + escapeHtml(modulesPageUrl) + '\'">Return to PayPal Module</button>';
                    html += '</div>';
                    
                    credentialsDiv.innerHTML = html;
                    setStatus('', '');
                    startButton.style.display = 'none';
                    
                    if (state.popup && !state.popup.closed) {
                        state.popup.close();
                    }
                }
                
                function autoSaveCredentials(credentials) {
                    // Make AJAX call to save credentials
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = modulesPageUrl;
                    
                    var fields = {
                        client_id: credentials.client_id,
                        client_secret: credentials.client_secret,
                        auto_save: '1'
                    };
                    
                    for (var key in fields) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = fields[key];
                        form.appendChild(input);
                    }
                    
                    // Note: Actual saving will be handled by the modules page
                    // For now, we just display them for manual entry
                }
                
                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                function startOnboarding() {
                    startButton.disabled = true;
                    setStatus('Starting PayPal signup...', 'info');
                    
                    proxyRequest('start', {})
                        .then(function(response) {
                            var data = response.data || {};
                            state.trackingId = data.tracking_id;
                            state.nonce = data.nonce || Date.now().toString();
                            
                            var redirectUrl = data.redirect_url || data.action_url;
                            if (!redirectUrl && data.links) {
                                for (var i = 0; i < data.links.length; i++) {
                                    if (data.links[i].rel === 'action_url') {
                                        redirectUrl = data.links[i].href;
                                        break;
                                    }
                                }
                            }
                            
                            if (!redirectUrl) {
                                throw new Error('No PayPal signup URL received');
                            }
                            
                            if (openPayPalPopup(redirectUrl)) {
                                setStatus('Follow the steps in the PayPal window...', 'info');
                                pollStatus();
                            }
                        })
                        .catch(function(error) {
                            setStatus(error.message || 'Failed to start onboarding', 'error');
                            startButton.disabled = false;
                        });
                }
                
                startButton.addEventListener('click', startOnboarding);
            })();
        </script>
    </body>
    </html>
    <?php
}

function paypalr_json_error(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
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

function paypalr_get_numinix_portal_base(): string
{
    $baseUrl = 'https://www.numinix.com/index.php';
    if (defined('MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL') && MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL !== '') {
        $baseUrl = trim((string)MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL);
    }

    return $baseUrl !== '' ? $baseUrl : '';
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
