(function() {
    var config = window.paypalrISUConfig || {};
    var proxyUrl = config.proxyUrl;
    var saveUrl = config.saveUrl;
    var completeUrl = config.completeUrl;
    var environment = config.environment;
    var modulesPageUrl = config.modulesPageUrl;
    var securityToken = config.securityToken;

    var environmentSelect = document.getElementById('environment');
    var allowedEnvironments = ['sandbox', 'live'];

    var state = {
        trackingId: null,
        partnerReferralId: null,
        merchantId: null,
        authCode: null,
        sharedId: null,
        sellerNonce: null,
        nonce: null,
        popup: null,
        pollTimer: null,
        pollInterval: 4000,
        pollAttempts: 0,
        maxCredentialPolls: 15,
        environment: null,
        prepareEnvironment: null,
        prepareVersion: 0,
        preparePromise: null,
        signupUrl: null,
        signupLinkElement: null
    };

    var startButton = document.getElementById('start-button');
    var statusDiv = document.getElementById('status');
    var credentialsDiv = document.getElementById('credentials-display');
    
    function getAlertClass(type) {
        var alertTypes = {
            'success': 'nmx-alert-success',
            'error': 'nmx-alert-error',
            'info': 'nmx-alert-info'
        };
        return alertTypes[type] || 'nmx-alert-info';
    }
    
    function setStatus(message, type) {
        statusDiv.textContent = message;
        statusDiv.className = 'nmx-alert ' + getAlertClass(type);
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

    function resetPreparationState() {
        state.signupUrl = null;
        state.trackingId = null;
        state.partnerReferralId = null;
        state.merchantId = null;
        state.authCode = null;
        state.sharedId = null;
        state.sellerNonce = null;
        state.pollAttempts = 0;
        if (state.pollTimer) {
            clearTimeout(state.pollTimer);
            state.pollTimer = null;
        }
    }
    
    function proxyRequest(action, data) {
        state.environment = resolveEnvironmentSelection();

        var payload = Object.assign({}, data || {}, {
            proxy_action: action,
            action: 'proxy',
            securityToken: securityToken,
            env: state.environment
        });
        
        // For 'start' action, include the client return URL
        if (action === 'start') {
            // Build return URL with tracking parameters
            var returnUrl = completeUrl;
            if (state.trackingId) {
                returnUrl += (returnUrl.indexOf('?') === -1 ? '?' : '&') + 'tracking_id=' + encodeURIComponent(state.trackingId);
            }
            returnUrl += (returnUrl.indexOf('?') === -1 ? '?' : '&') + 'env=' + encodeURIComponent(state.environment);
            payload.client_return_url = returnUrl;
        }
        
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
        console.log('[PayPal ISU] Opening PayPal signup in new tab:', {
            url: url,
            tracking_id: state.trackingId,
            environment: state.environment
        });
        
        state.popup = window.open(url, '_blank');
        
        if (!state.popup || state.popup.closed) {
            setStatus('Please allow popups for this site to continue.', 'error');
            startButton.disabled = false;
            console.error('[PayPal ISU] Failed to open PayPal signup window - popup blocked');
            return false;
        }
        
        try {
            state.popup.focus();
        } catch(e) {
            console.warn('[PayPal ISU] Could not focus popup window:', e);
        }
        
        console.log('[PayPal ISU] PayPal signup window opened successfully');
        monitorPopup();
        return true;
    }

    function monitorPopup() {
        var checkInterval = setInterval(function() {
            if (!state.popup || state.popup.closed) {
                clearInterval(checkInterval);
                state.popup = null;
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
        console.log('[PayPal ISU] handlePopupMessage called:', {
            hasEvent: !!event,
            eventOrigin: event ? event.origin : 'N/A',
            hasData: !!(event && event.data),
            hasPopup: !!state.popup,
            dataType: event && event.data ? typeof event.data : 'N/A'
        });
        
        function isPayPalDomain(origin) {
            if (!origin) return false;
            try {
                var url = new URL(origin);
                var hostname = url.hostname.toLowerCase();
                return hostname === 'paypal.com' || hostname.endsWith('.paypal.com') ||
                       hostname === 'paypalobjects.com' || hostname.endsWith('.paypalobjects.com');
            } catch (e) {
                return false;
            }
        }
        
        var isFromOurPopup = state.popup && event && event.source && event.source === state.popup;
        var isFromSameOrigin = event && event.origin === window.location.origin;
        var isFromPayPal = event && isPayPalDomain(event.origin);
        
        if (state.popup && !isFromOurPopup) {
            console.log('[PayPal ISU] Ignoring message - not from our popup');
            return;
        }
        if (!state.popup && !isFromSameOrigin && !isFromPayPal) {
            console.log('[PayPal ISU] Ignoring message - not from same origin or PayPal');
            return;
        }

        var payload = event && event.data;
        if (!payload) {
            console.log('[PayPal ISU] Ignoring message - no data');
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

        console.log('[PayPal ISU] Received completion message from popup:', {
            event: eventName,
            has_merchant_id: !!(payload.merchantId || payload.merchant_id || payload.merchantIdInPayPal),
            has_auth_code: !!(payload.authCode || payload.auth_code),
            has_shared_id: !!(payload.sharedId || payload.shared_id),
            has_tracking_id: !!payload.trackingId
        });

        if (payload.merchantId) {
            state.merchantId = payload.merchantId;
        } else if (payload.merchant_id) {
            state.merchantId = payload.merchant_id;
        } else if (payload.merchantIdInPayPal) {
            state.merchantId = payload.merchantIdInPayPal;
        }

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
                setStatus('We couldn\'t retrieve your PayPal API credentials automatically. Please try again later or contact support.', 'error');
                startButton.disabled = false;
                return;
            }

            proxyRequest('status', {
                tracking_id: state.trackingId,
                partner_referral_id: state.partnerReferralId || '',
                merchant_id: state.merchantId || '',
                authCode: state.authCode || '',
                sharedId: state.sharedId || '',
                auth_code: state.authCode || '',
                shared_id: state.sharedId || '',
                seller_nonce: state.sellerNonce || '',
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
            setStatus('Your PayPal connection is being provisioned. We\'ll save your credentials as soon as they\'re ready.', 'info');
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
    
    function loadPartnerJs(env) {
        return new Promise(function(resolve, reject) {
            var existing = document.getElementById('paypal-partner-js');
            if (existing && existing.getAttribute('data-paypal-env') === env) {
                resolve();
                return;
            }
            if (existing) existing.remove();

            var s = document.createElement('script');
            s.id = 'paypal-partner-js';
            s.async = true;
            s.setAttribute('data-paypal-env', env);
            s.src = (env === 'sandbox'
                ? 'https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js'
                : 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js'
            );

            s.onload = function() {
                console.log('[PayPal ISU] partner.js loaded for', env);
                resolve();
            };
            s.onerror = function() {
                console.error('[PayPal ISU] Failed to load partner.js for', env);
                reject(new Error('Failed to load PayPal partner.js'));
            };
            document.head.appendChild(s);
        });
    }
    
    function getSignupRedirectUrl(data) {
        var redirectUrl = data.redirect_url || data.action_url;
        if (!redirectUrl && data.links) {
            for (var i = 0; i < data.links.length; i++) {
                if (data.links[i].rel === 'action_url') {
                    redirectUrl = data.links[i].href;
                    break;
                }
            }
        }

        return redirectUrl;
    }

    function ensurePayPalSignupLink(signupUrl) {
        var url = signupUrl;
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'displayMode=minibrowser';

        if (!state.signupLinkElement) {
            state.signupLinkElement = document.createElement('a');
            state.signupLinkElement.id = 'paypal-signup-link';
            state.signupLinkElement.style.position = 'absolute';
            state.signupLinkElement.style.left = '-9999px';
            state.signupLinkElement.style.top = 'auto';
            state.signupLinkElement.style.display = 'block';
            document.body.appendChild(state.signupLinkElement);
        } else if (!document.body.contains(state.signupLinkElement)) {
            document.body.appendChild(state.signupLinkElement);
        }

        state.signupLinkElement.href = url;
        state.signupLinkElement.setAttribute('data-paypal-onboard-complete', 'paypalOnboardedCallback');
        state.signupLinkElement.setAttribute('data-paypal-button', 'true');

        return state.signupLinkElement;
    }

    function prepareOnboarding() {
        var prepareEnvironment = resolveEnvironmentSelection();
        if (state.preparePromise && state.prepareEnvironment === prepareEnvironment) {
            return state.preparePromise;
        }

        state.prepareVersion += 1;
        var prepareVersion = state.prepareVersion;
        state.prepareEnvironment = prepareEnvironment;

        resetPreparationState();
        state.environment = prepareEnvironment;
        startButton.disabled = true;
        setStatus('Preparing your PayPal signup link...', 'info');

        state.preparePromise = loadPartnerJs(prepareEnvironment)
            .then(function() {
                return proxyRequest('start', {nonce: state.nonce || ''});
            })
            .then(function(response) {
                if (state.prepareEnvironment !== prepareEnvironment || state.prepareVersion !== prepareVersion) {
                    return prepareOnboarding();
                }

                var data = response.data || {};
                state.trackingId = data.tracking_id;
                state.partnerReferralId = data.partner_referral_id || null;
                state.merchantId = data.merchant_id || null;
                state.sellerNonce = data.seller_nonce || null;
                if (data.environment && allowedEnvironments.indexOf(data.environment.toLowerCase()) !== -1) {
                    state.environment = data.environment.toLowerCase();
                    if (environmentSelect) {
                        environmentSelect.value = state.environment;
                    }
                }

                state.nonce = data.nonce;
                if (!state.nonce) {
                    throw new Error('Session error. Please refresh and try again.');
                }

                var redirectUrl = getSignupRedirectUrl(data);
                if (!redirectUrl) {
                    throw new Error('No PayPal signup URL received');
                }

                state.signupUrl = redirectUrl;
                state.environment = state.environment || prepareEnvironment;
                startButton.disabled = false;
                setStatus('Ready. Click Start PayPal Signup to open PayPal.', 'info');
                state.preparePromise = null;
                state.prepareEnvironment = null;
                return state.signupUrl;
            })
            .catch(function(error) {
                if (state.prepareEnvironment !== prepareEnvironment || state.prepareVersion !== prepareVersion) {
                    return prepareOnboarding();
                }

                state.preparePromise = null;
                state.prepareEnvironment = null;
                startButton.disabled = false;
                setStatus(error.message || 'Failed to prepare onboarding', 'error');
                throw error;
            });

        return state.preparePromise;
    }

    function ensureSignupPrepared() {
        if (state.signupUrl && !state.preparePromise) {
            return Promise.resolve(state.signupUrl);
        }

        return prepareOnboarding();
    }

    function startOnboarding() {
        startButton.disabled = true;
        state.pollAttempts = 0;
        ensureSignupPrepared()
            .then(function(signupUrl) {
                if (!signupUrl) {
                    throw new Error('PayPal signup link is not ready yet. Please try again in a moment.');
                }

                var signupLink = ensurePayPalSignupLink(signupUrl);

                if (window.PAYPAL && PAYPAL.apps && PAYPAL.apps.Signup) {
                    PAYPAL.apps.Signup.render();
                    console.log('[PayPal ISU] Called PAYPAL.apps.Signup.render()');
                } else {
                    throw new Error('PayPal Signup SDK not available after loading partner.js');
                }

                setStatus('Opening PayPal signup...', 'info');
                signupLink.click();
                pollStatus();
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
            prepareOnboarding().catch(function() {
                // Errors are surfaced via setStatus; swallow to avoid unhandled promise rejection.
            });
        });
    }
    window.addEventListener('message', handlePopupMessage);
    console.log('[PayPal ISU] Message event listener attached - ready to receive postMessage from popup/mini-browser');

    prepareOnboarding().catch(function(error) {
        console.error('[PayPal ISU] Failed to prepare onboarding on load', error);
    });
    
    window.addEventListener('paypalOnboardingComplete', function(event) {
        var detail = event.detail || {};
        
        console.log('[PayPal ISU] paypalOnboardingComplete event received:', {
            hasAuthCode: !!detail.authCode,
            hasSharedId: !!detail.sharedId,
            source: detail.source || 'unknown',
            trackingId: state.trackingId
        });
        
        if (detail.authCode) {
            state.authCode = detail.authCode;
            console.log('[PayPal ISU] Captured authCode from callback');
        }
        if (detail.sharedId) {
            state.sharedId = detail.sharedId;
            console.log('[PayPal ISU] Captured sharedId from callback');
        }
        
        if (state.authCode && state.sharedId) {
            console.log('[PayPal ISU] Have both authCode and sharedId - calling pollStatus');
            setStatus('Processing your PayPal account details…', 'info');
            pollStatus(true);
        } else {
            console.log('[PayPal ISU] Missing authCode or sharedId - not calling pollStatus yet');
        }
    });
})();
