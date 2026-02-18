(function() {
    var config = window.paypalrISUCompleteConfig || {};
    var proxyUrl = config.proxyUrl;
    var saveUrl = config.saveUrl;
    var modulesPageUrl = config.modulesPageUrl;
    var securityToken = config.securityToken;
    var merchantId = config.merchantId;
    var authCode = config.authCode;
    var sharedId = config.sharedId;
    var trackingId = config.trackingId;
    var environment = config.environment;
    var nonce = config.nonce;
    var sellerNonce = config.sellerNonce;
    
    var credentialsDisplay = document.getElementById('credentials-display');
    var autoSaveStatus = document.getElementById('auto-save-status');
    var returnBtn = document.getElementById('return-btn');
    
    var retryCount = 0;
    var maxRetries = 60;
    
    function getAlertClass(type) {
        var alertTypes = {
            'success': 'nmx-alert-success',
            'error': 'nmx-alert-error',
            'info': 'nmx-alert-info'
        };
        return alertTypes[type] || 'nmx-alert-info';
    }
    
    function setAutoSaveStatus(message, type) {
        autoSaveStatus.textContent = message;
        autoSaveStatus.className = 'nmx-alert ' + getAlertClass(type);
        autoSaveStatus.classList.remove('hidden');
    }
    
    function displayCredentials(credentials, env) {
        var html = '<h2>Your PayPal API Credentials</h2>';
        html += '<p style="color: #555; margin-bottom: 15px;">Environment: <strong>' + escapeHtml(env) + '</strong></p>';
        
        html += '<div class="credential-row">';
        html += '<label for="client-id">Client ID:</label>';
        html += '<div class="credential-input-group">';
        html += '<input type="text" id="client-id" value="' + escapeHtml(credentials.client_id) + '" readonly>';
        html += '<button class="copy-btn" onclick="copyToClipboard(\'client-id\', this)">Copy</button>';
        html += '</div></div>';
        
        html += '<div class="credential-row">';
        html += '<label for="client-secret">Client Secret:</label>';
        html += '<div class="credential-input-group">';
        html += '<input type="text" id="client-secret" value="' + escapeHtml(credentials.client_secret) + '" readonly>';
        html += '<button class="copy-btn" onclick="copyToClipboard(\'client-secret\', this)">Copy</button>';
        html += '</div></div>';
        
        credentialsDisplay.innerHTML = html;
    }
    
    window.copyToClipboard = function(inputId, button) {
        var input = document.getElementById(inputId);
        if (!input) return;
        
        var textToCopy = input.value;
        var originalText = button.textContent;
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy)
                .then(function() {
                    button.textContent = 'Copied!';
                    button.classList.add('copied');
                    setTimeout(function() {
                        button.textContent = originalText;
                        button.classList.remove('copied');
                    }, 2000);
                })
                .catch(function() {
                    copyToClipboardLegacy(input, button, originalText);
                });
        } else {
            copyToClipboardLegacy(input, button, originalText);
        }
    };
    
    function copyToClipboardLegacy(input, button, originalText) {
        input.select();
        input.setSelectionRange(0, 99999);
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                button.textContent = 'Copied!';
                button.classList.add('copied');
                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            } else {
                alert('Failed to copy. Please select and copy manually.');
            }
        } catch (err) {
            alert('Failed to copy. Please select and copy manually.');
        }
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function fetchCredentials() {
        if (!trackingId) {
            credentialsDisplay.innerHTML = '<h2>Error</h2><p style="color: #cc0000;">Missing tracking ID. Please try the setup process again.</p>';
            return;
        }
        
        var proxyAction = merchantId ? 'finalize' : 'status';
        
        var payload = {
            proxy_action: proxyAction,
            action: 'proxy',
            securityToken: securityToken,
            tracking_id: trackingId,
            merchant_id: merchantId,
            authCode: authCode,
            sharedId: sharedId,
            seller_nonce: sellerNonce,
            env: environment,
            nonce: nonce
        };
        
        fetch(proxyUrl, {
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
                throw new Error(data && data.message || 'Failed to fetch credentials');
            }
            
            var responseData = data.data || {};
            if (responseData.credentials && responseData.credentials.client_id && responseData.credentials.client_secret) {
                displayCredentials(responseData.credentials, responseData.environment || environment);
                attemptAutoSave(responseData.credentials, responseData.environment || environment);
            } else if (responseData.step === 'completed') {
                credentialsDisplay.innerHTML = '<h2>Error</h2><p style="color: #cc0000;">PayPal onboarding completed but credentials were not returned. Please contact support or enter credentials manually.</p>';
                setAutoSaveStatus('Unable to retrieve credentials automatically. Please obtain them manually from PayPal.', 'error');
            } else if (responseData.step === 'waiting' || responseData.status_hint === 'provisioning') {
                retryCount++;
                
                if (retryCount > maxRetries) {
                    credentialsDisplay.innerHTML = '<h2>Timeout</h2><p style="color: #cc0000;">PayPal is taking longer than expected to provision your account. Please try again later or contact support.</p>';
                    setAutoSaveStatus('Timeout waiting for credentials. Please try again later.', 'error');
                    return;
                }
                
                var pollingInterval = responseData.polling_interval || 5000;
                var remainingTime = Math.ceil((maxRetries - retryCount) * pollingInterval / 1000);
                credentialsDisplay.innerHTML = '<h2>Retrieving Credentials...</h2><p>PayPal is provisioning your account. This usually takes just a few seconds.</p><p><span class="spinner" role="status" aria-label="Loading"></span> Checking status... (attempt ' + retryCount + ' of ' + maxRetries + ')</p>';
                setAutoSaveStatus('Waiting for PayPal to provision your account... (' + retryCount + '/' + maxRetries + ' attempts)', 'info');
                
                setTimeout(function() {
                    console.log('Retrying credential fetch after ' + pollingInterval + 'ms (attempt ' + retryCount + ')');
                    fetchCredentials();
                }, pollingInterval);
            } else {
                credentialsDisplay.innerHTML = '<h2>Credentials Not Ready Yet</h2><p>PayPal is still provisioning your account. Please wait a moment and refresh this page, or return to the admin panel.</p>';
                setAutoSaveStatus('Credentials are not yet available. You may need to wait a few moments for PayPal to complete provisioning.', 'info');
            }
        })
        .catch(function(error) {
            credentialsDisplay.innerHTML = '<h2>Error Retrieving Credentials</h2><p style="color: #cc0000;">' + escapeHtml(error.message || 'Failed to fetch credentials') + '</p><p>Please return to the admin panel and check your configuration.</p>';
            setAutoSaveStatus('Failed to retrieve credentials: ' + (error.message || 'Unknown error'), 'error');
        });
    }
    
    function attemptAutoSave(credentials, env) {
        setAutoSaveStatus('Attempting to save credentials automatically...', 'info');
        
        var payload = {
            action: 'save_credentials',
            securityToken: securityToken,
            client_id: credentials.client_id,
            client_secret: credentials.client_secret,
            environment: env
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
            
            setAutoSaveStatus('✓ Credentials saved successfully! You can now return to the PayPal module.', 'success');
            returnBtn.focus();
        })
        .catch(function(error) {
            setAutoSaveStatus('⚠ Auto-save failed: ' + (error.message || 'Unknown error') + '. Please copy the credentials above and enter them manually in the PayPal module configuration.', 'error');
        });
    }
    
    // Send completion message to opener window if it exists
    console.log('[PayPal ISU Completion] Checking for opener window:', {
        hasOpener: !!window.opener,
        openerClosed: window.opener ? window.opener.closed : 'N/A'
    });
    
    if (window.opener && !window.opener.closed) {
        try {
            var targetOrigin = '*';
            try {
                if (window.opener.location && window.opener.location.origin) {
                    targetOrigin = window.opener.location.origin;
                }
            } catch(e) {
                console.log('[PayPal ISU Completion] Could not access opener origin, using *');
            }
            
            var messagePayload = {
                event: 'paypal_onboarding_complete',
                paypalOnboardingComplete: true,
                merchantId: merchantId,
                authCode: authCode,
                sharedId: sharedId,
                trackingId: trackingId,
                environment: environment
            };
            
            console.log('[PayPal ISU Completion] Sending postMessage to opener:', {
                targetOrigin: targetOrigin,
                hasMerchantId: !!merchantId,
                hasAuthCode: !!authCode,
                hasSharedId: !!sharedId,
                hasTrackingId: !!trackingId
            });
            
            window.opener.postMessage(messagePayload, targetOrigin);
            console.log('[PayPal ISU Completion] postMessage sent successfully');
        } catch(e) {
            console.error('[PayPal ISU Completion] Error sending postMessage:', e);
        }
    } else {
        console.log('[PayPal ISU Completion] No opener window available - this may be expected for mini-browser flow');
    }
    
    // Fetch credentials immediately
    fetchCredentials();
})();
