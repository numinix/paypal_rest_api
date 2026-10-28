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
