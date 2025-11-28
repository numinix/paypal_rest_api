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
            .environment-select {
                margin: 20px 0;
            }
            .environment-select label {
                display: block;
                font-weight: bold;
                margin-bottom: 8px;
            }
            .environment-select select {
                padding: 8px 10px;
                font-size: 14px;
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

            <div class="environment-select">
                <label for="environment">Select onboarding mode</label>
                <select id="environment" name="environment">
                    <option value="live">Production</option>
                    <option value="sandbox">Sandbox</option>
                </select>
                <p style="margin: 8px 0 0 0; color: #555;">Choose which PayPal environment to onboard. If you leave this unchanged, the default from your store configuration will be used.</p>
            </div>

            <div class="actions">
                <button id="start-button" type="button">Start PayPal Signup</button>
                <a href="<?php echo htmlspecialchars($modulesPageUrl, ENT_QUOTES, 'UTF-8'); ?>">Cancel and return to modules</a>
            </div>
        </div>
        
        <script>
            (function() {
                var proxyUrl = <?php echo json_encode($proxyUrl); ?>;
                var saveUrl = <?php echo json_encode($saveUrl); ?>;
                var environment = <?php echo json_encode($environment); ?>;
                var modulesPageUrl = <?php echo json_encode($modulesPageUrl); ?>;
                var securityToken = <?php echo json_encode($securityToken); ?>;

                var environmentSelect = document.getElementById('environment');
                var allowedEnvironments = ['sandbox', 'live'];

                var state = {
                    trackingId: null,
                    partnerReferralId: null,
                    merchantId: null,
                    authCode: null,
                    sharedId: null,
                    nonce: null,
                    popup: null,
                    pollTimer: null,
                    pollInterval: 4000,
                    pollAttempts: 0,
                    maxCredentialPolls: 15,
                    environment: null
                };

                var startButton = document.getElementById('start-button');
                var statusDiv = document.getElementById('status');
                var credentialsDiv = document.getElementById('credentials-display');
                
                function setStatus(message, type) {
                    statusDiv.textContent = message;
                    statusDiv.className = 'status ' + (type || 'info');
                    statusDiv.style.display = message ? 'block' : 'none';
                }

                function resolveEnvironmentSelection() {
                    var selected = (environmentSelect && environmentSelect.value || '').toLowerCase();
                    if (allowedEnvironments.indexOf(selected) !== -1) {
                        return selected;
                    }

                    if (allowedEnvironments.indexOf(environment) !== -1) {
                        return environment;
                    }

                    return 'sandbox';
                }

                // Initialize select with current environment
                state.environment = resolveEnvironmentSelection();
                if (environmentSelect) {
                    environmentSelect.value = state.environment;
                }
                
                function proxyRequest(action, data) {
                    state.environment = resolveEnvironmentSelection();

                    var payload = Object.assign({}, data || {}, {
                        proxy_action: action,
                        action: 'proxy',
                        securityToken: securityToken,
                        env: state.environment
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
                                if (state.trackingId) {
                                    pollStatus(true);
                                }
                                startButton.disabled = false;
                            }
                        }
                    }, 1000);
                }

                function handlePopupMessage(event) {
                    if (!state.popup || (event && event.source && event.source !== state.popup)) {
                        return;
                    }

                    var payload = event && event.data;
                    if (!payload) {
                        return;
                    }

                    if (typeof payload === 'string') {
                        try {
                            payload = JSON.parse(payload);
                        } catch (e) {
                            payload = { event: payload };
                        }
                    }

                    if (!payload || typeof payload !== 'object') {
                        return;
                    }

                    var eventName = '';
                    if (typeof payload.event === 'string') {
                        eventName = payload.event;
                    } else if (typeof payload.type === 'string') {
                        eventName = payload.type;
                    }

                    var normalized = eventName.toLowerCase();
                    var completionEvent = normalized === 'paypal_onboarding_complete'
                        || normalized === 'paypal_partner_onboarding_complete'
                        || payload.paypal_onboarding_complete === true
                        || payload.paypalOnboardingComplete === true;

                    if (!completionEvent) {
                        return;
                    }

                    // Extract merchant_id from the completion message if provided
                    // This is critical for credential retrieval - PayPal returns this
                    // in the redirect URL and the completion page forwards it via postMessage
                    if (payload.merchantId) {
                        state.merchantId = payload.merchantId;
                    } else if (payload.merchant_id) {
                        state.merchantId = payload.merchant_id;
                    } else if (payload.merchantIdInPayPal) {
                        state.merchantId = payload.merchantIdInPayPal;
                    }

                    // Extract authCode and sharedId from the completion message
                    // Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns
                    // an authCode and sharedId to your seller's browser. Use the authCode and sharedId
                    // to get the seller's access token."
                    // See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
                    if (payload.authCode) {
                        state.authCode = payload.authCode;
                    } else if (payload.auth_code) {
                        state.authCode = payload.auth_code;
                    }
                    if (payload.sharedId) {
                        state.sharedId = payload.sharedId;
                    } else if (payload.shared_id) {
                        state.sharedId = payload.shared_id;
                    }

                    setStatus('Processing your PayPal account details…', 'info');
                    pollStatus(true);
                }

                function pollStatus(immediate) {
                    if (!state.trackingId) return;

                    if (state.pollTimer) {
                        clearTimeout(state.pollTimer);
                    }

                    var performPoll = function() {
                        state.pollAttempts += 1;

                        if (state.pollAttempts > state.maxCredentialPolls) {
                            setStatus('We couldn’t retrieve your PayPal API credentials automatically. Please try again later or contact support.', 'error');
                            startButton.disabled = false;
                            return;
                        }

                        proxyRequest('status', {
                            tracking_id: state.trackingId,
                            partner_referral_id: state.partnerReferralId || '',
                            merchant_id: state.merchantId || '',
                            authCode: state.authCode || '',
                            sharedId: state.sharedId || '',
                            nonce: state.nonce
                        })
                        .then(function(response) {
                            handleStatusResponse(response.data || {});
                        })
                        .catch(function(error) {
                            setStatus(error.message || 'Failed to check status', 'error');
                            startButton.disabled = false;
                        });
                    };

                    if (immediate) {
                        performPoll();
                    } else {
                        state.pollTimer = setTimeout(performPoll, state.pollInterval);
                    }
                }

                function handleStatusResponse(data) {
                    var step = (data.step || '').toLowerCase();
                    var hasCredentials = data.credentials && data.credentials.client_id && data.credentials.client_secret;
                    var statusHint = (data.status_hint || '').toLowerCase();
                    var statusText = (data.status || '').toLowerCase();
                    var completedStep = step === 'completed' || step === 'ready' || step === 'active';
                    if (!hasCredentials && data.client_id && data.client_secret) {
                        data.credentials = {
                            client_id: data.client_id,
                            client_secret: data.client_secret
                        };
                        hasCredentials = true;
                    }
                    // Use environment from API response if available, fallback to local setting
                    var remoteEnvironment = data.environment || state.environment || environment;

                    if (data.environment && allowedEnvironments.indexOf(data.environment.toLowerCase()) !== -1) {
                        state.environment = data.environment.toLowerCase();
                        if (environmentSelect) {
                            environmentSelect.value = state.environment;
                        }
                    }

                    if (data.partner_referral_id) {
                        state.partnerReferralId = data.partner_referral_id;
                    }

                    if (data.merchant_id) {
                        state.merchantId = data.merchant_id;
                    }

                    if (data.polling_interval) {
                        state.pollInterval = Math.max(data.polling_interval, 2000);
                    }

                    if (completedStep) {
                        if (hasCredentials) {
                            // Credentials are now available: show them, save them, and stop polling.
                            state.pollAttempts = 0;
                            setStatus('PayPal account connected. Saving your API credentials…', 'success');
                            displayCredentials(data.credentials, remoteEnvironment);
                            autoSaveCredentials(data.credentials, remoteEnvironment);
                        } else {
                            setStatus('PayPal returned an incomplete credential payload. Please try again or contact support.', 'error');
                            startButton.disabled = false;
                        }
                    } else if (step === 'cancelled' || step === 'declined') {
                        setStatus(data.message || 'Onboarding was cancelled.', 'error');
                        startButton.disabled = false;
                    } else if (step === 'finalized') {
                        setStatus('Your PayPal connection is being provisioned. We’ll save your credentials as soon as they’re ready.', 'info');
                        pollStatus();
                    } else {
                        var waitingMessage = data.message
                            || (statusHint === 'provisioning' ? 'PayPal is provisioning your merchant account. This may take a moment…' : '')
                            || (statusText ? 'PayPal status: ' + statusText : 'Waiting for PayPal to complete setup...');
                        setStatus(waitingMessage, 'info');
                        pollStatus();
                    }
                }
                
                function displayCredentials(credentials, credentialEnvironment) {
                    var html = '<div class="credentials">';
                    html += '<h3>✓ Credentials Retrieved Successfully</h3>';
                    html += '<p>Your PayPal API credentials have been retrieved and saved:</p>';
                    html += '<dl>';
                    html += '<dt>Environment:</dt><dd>' + escapeHtml(credentialEnvironment) + '</dd>';
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
                
                function autoSaveCredentials(credentials, credentialEnvironment) {
                    setStatus('Saving credentials...', 'info');

                    var payload = {
                        action: 'save_credentials',
                        securityToken: securityToken,
                        client_id: credentials.client_id,
                        client_secret: credentials.client_secret,
                        environment: credentialEnvironment
                    };

                    fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams(payload).toString()
                    })
                    .then(function(response) {
                        if (!response.ok) throw new Error('Network error while saving credentials');
                        return response.json();
                    })
                    .then(function(data) {
                        if (!data || !data.success) {
                            throw new Error(data && data.message || 'Failed to save credentials');
                        }

                        setStatus('Credentials saved! Redirecting...', 'success');
                        setTimeout(function() {
                            window.location.href = modulesPageUrl;
                        }, 1500);
                    })
                    .catch(function(error) {
                        setStatus(error.message || 'Failed to save credentials', 'error');
                        startButton.disabled = false;
                    });
                }
                
                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                function startOnboarding() {
                    startButton.disabled = true;
                    state.pollAttempts = 0;
                    state.environment = resolveEnvironmentSelection();
                    state.partnerReferralId = null;
                    state.merchantId = null;
                    setStatus('Starting PayPal signup...', 'info');

                    proxyRequest('start', {nonce: state.nonce || ''})
                        .then(function(response) {
                            var data = response.data || {};
                            state.trackingId = data.tracking_id;
                            state.partnerReferralId = data.partner_referral_id || null;
                            state.merchantId = data.merchant_id || null;
                            if (data.environment && allowedEnvironments.indexOf(data.environment.toLowerCase()) !== -1) {
                                state.environment = data.environment.toLowerCase();
                                if (environmentSelect) {
                                    environmentSelect.value = state.environment;
                                }
                            }
                            // Use nonce from server response, or generate a cryptographically secure one
                            state.nonce = data.nonce;
                            if (!state.nonce) {
                                setStatus('Session error. Please refresh and try again.', 'error');
                                startButton.disabled = false;
                                return;
                            }
                            
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
                if (environmentSelect) {
                    environmentSelect.addEventListener('change', function() {
                        state.environment = resolveEnvironmentSelection();
                    });
                }
                window.addEventListener('message', handlePopupMessage);
            })();
        </script>
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

    $messageStack->add_session('main', $message, $type);
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

    // Prefer the ISU configuration toggle when available
    if (defined('NUMINIX_PPCP_ENVIRONMENT')) {
        $value = strtolower((string) NUMINIX_PPCP_ENVIRONMENT);
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

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
