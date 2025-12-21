(function (window, document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function isValidMerchantId(value) {
        return typeof value === 'string' && /^[A-Za-z0-9]{10,20}$/.test(value);
    }

    function toNonEmptyString(value) {
        if (value === undefined || value === null) {
            return '';
        }
        if (typeof value === 'object' || typeof value === 'function') {
            return '';
        }
        var str = String(value).trim();
        return str === '' ? '' : str;
    }

    function ensureMiniBrowserDisplayMode(url) {
        if (!url || typeof url !== 'string') {
            return url;
        }
        if (/([?&])displayMode=/.test(url)) {
            return url;
        }
        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'displayMode=minibrowser';
    }

    function getFirstMatchingValue(source, keyVariants) {
        if (!source || !keyVariants || !keyVariants.length) {
            return undefined;
        }

        var queue = [source];
        var visited = [];
        var childKeys = ['data', 'detail', 'payload', 'body', 'response', 'params'];

        while (queue.length) {
            var current = queue.shift();
            if (!current || typeof current !== 'object') {
                continue;
            }
            if (visited.indexOf(current) !== -1) {
                continue;
            }
            visited.push(current);

            for (var i = 0; i < keyVariants.length; i++) {
                var key = keyVariants[i];
                if (Object.prototype.hasOwnProperty.call(current, key) && current[key] !== undefined && current[key] !== null) {
                    return current[key];
                }
            }

            if (Array.isArray(current)) {
                current.forEach(function (item) {
                    if (item && typeof item === 'object') {
                        queue.push(item);
                    }
                });
            }

            childKeys.forEach(function (childKey) {
                if (current[childKey] && typeof current[childKey] === 'object') {
                    queue.push(current[childKey]);
                }
            });
        }

        return undefined;
    }

    function smoothScrollTo(target) {
        if (!target) {
            return;
        }

        var behavior = prefersReducedMotion() ? 'auto' : 'smooth';
        try {
            target.scrollIntoView({ behavior: behavior, block: 'start' });
        } catch (error) {
            target.scrollIntoView(true);
        }
    }

    function emitAnalytics(eventName, payload) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(Object.assign({ event: eventName }, payload || {}));
    }

    /**
     * Handle PayPal return URL when page opens in popup after signup completion.
     * 
     * When PayPal redirects back to this page in the popup window, we need to:
     * 1. Extract authCode/sharedId from URL parameters
     * 2. Send postMessage to parent window with the credentials
     * 3. Close the popup
     * 
     * This enables the parent window to receive the credentials and finalize onboarding.
     */
    function handlePopupReturn() {
        // Only run if this page is loaded in a popup (has window.opener)
        if (!window.opener || window.opener.closed) {
            return false;
        }

        var urlParams;
        try {
            urlParams = new URLSearchParams(window.location.search);
        } catch (error) {
            return false;
        }

        // Check for PayPal return parameters
        var authCode = urlParams.get('authCode') || urlParams.get('auth_code');
        var sharedId = urlParams.get('sharedId') || urlParams.get('shared_id');
        var merchantId = urlParams.get('merchantId') || urlParams.get('merchantIdInPayPal');
        if (!isValidMerchantId(merchantId)) {
            merchantId = null;
        }
        var trackingId = urlParams.get('tracking_id');

        // If we have any PayPal parameters, this is a return from PayPal
        if (authCode || sharedId || merchantId || (trackingId && urlParams.get('env'))) {
            try {
                // Determine target origin for postMessage
                // Use wildcard '*' to avoid cross-origin access errors when reading window.opener.location.origin
                var targetOrigin = '*';  // Less secure but necessary for cross-origin popup communication
                
                // Send postMessage to parent window with all parameters
                window.opener.postMessage({
                    event: 'paypal_onboarding_complete',
                    paypalOnboardingComplete: true,
                    authCode: authCode || undefined,
                    sharedId: sharedId || undefined,
                    merchantId: merchantId || undefined,
                    tracking_id: trackingId || undefined,
                    env: urlParams.get('env') || undefined,
                    source: 'popup_return_url'
                }, targetOrigin);

                // Show brief message before closing
                document.body.innerHTML = '<div style="padding: 40px; text-align: center; font-family: system-ui, sans-serif;">' +
                    '<h2>PayPal Setup Complete</h2>' +
                    '<p>Processing your account details...</p>' +
                    '<p style="color: #666; font-size: 14px;">This window will close automatically.</p>' +
                    '</div>';

                // Close popup after brief delay to ensure postMessage is received
                setTimeout(function () {
                    window.close();
                }, 1000);

                return true;
            } catch (error) {
                console.error('[PayPal Return] Failed to send postMessage:', error);
                return false;
            }
        }

        return false;
    }

    // Try to handle popup return immediately (before heavy page load)
    if (handlePopupReturn()) {
        // Stop further page initialization if we're handling popup return
        return;
    }

    ready(function () {
        var page = document.querySelector('.nxp-ps-page');
        if (!page) {
            return;
        }

        emitAnalytics('view_item', {
            item_id: 'paypal_signup',
            item_name: 'PayPal Setup for Zen Cart',
            page_location: window.location.href
        });

        emitAnalytics('heatmap_enable', {
            page: 'paypal_signup',
            retention_days: 14
        });

        var stickyHeader = document.querySelector('.nxp-ps-header');
        function updateHeaderState() {
            if (!stickyHeader) {
                return;
            }
            var threshold = window.scrollY || window.pageYOffset || 0;
            if (threshold > 32) {
                stickyHeader.classList.add('is-condensed');
            } else {
                stickyHeader.classList.remove('is-condensed');
            }
        }
        window.addEventListener('scroll', updateHeaderState, { passive: true });
        updateHeaderState();

        document.querySelectorAll('[data-scroll-target]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                var selector = trigger.getAttribute('data-scroll-target');
                if (!selector) {
                    return;
                }
                var target = document.querySelector(selector);
                if (!target) {
                    return;
                }
                event.preventDefault();
                smoothScrollTo(target);
            });
        });

        if (window.location.hash) {
            try {
                var hashTarget = document.querySelector(window.location.hash);
                if (hashTarget) {
                    window.setTimeout(function () {
                        smoothScrollTo(hashTarget);
                    }, 0);
                }
            } catch (error) {
                // Ignore invalid selectors from hash
            }
        }

        var urlParams;
        try {
            urlParams = new URLSearchParams(window.location.search);
        } catch (error) {
            urlParams = null;
        }
        if (urlParams) {
            var variantKey = urlParams.get('v');
            var variants = {
                alt: {
                    headline: 'Fast, Compliant PayPal Setup for Zen Cart Stores.',
                    subhead: 'Reduce disputes, launch faster, and sync PayPal with Zen Cart without the guesswork.',
                    'primary-cta': 'Get My Setup Plan'
                }
            };
            if (variantKey && variants[variantKey]) {
                var variant = variants[variantKey];
                Object.keys(variant).forEach(function (key) {
                    var node = document.querySelector('[data-variant="' + key + '"]');
                    if (node) {
                        node.textContent = variant[key];
                    }
                    if (key === 'primary-cta') {
                        var stickyCta = document.querySelector('.nxp-ps-header [data-scroll-target]');
                        if (stickyCta) {
                            stickyCta.textContent = variant[key];
                        }
                    }
                });
            }
        }

        document.querySelectorAll('[data-analytics-id]').forEach(function (element) {
            element.addEventListener('click', function () {
                var id = element.getAttribute('data-analytics-id');
                emitAnalytics('cta_click', {
                    id: id,
                    cta_text: (element.textContent || '').trim(),
                    page_location: window.location.href
                });
            });
        });

        var toggleButtons = document.querySelectorAll('.nxp-ps-toggle__button');
        var comparisonPanels = document.querySelectorAll('.nxp-ps-comparison__panel');
        var MAX_POLL_ATTEMPTS = 30;
        var MAX_POLL_DURATION_MS = 2 * 60 * 1000; // 2 minutes
        function activateComparison(view) {
            toggleButtons.forEach(function (button) {
                var isActive = button.getAttribute('data-view') === view;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            comparisonPanels.forEach(function (panel) {
                var show = panel.getAttribute('data-view') === view;
                panel.classList.toggle('is-active', show);
                panel.setAttribute('aria-hidden', show ? 'false' : 'true');
            });
        }
        toggleButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activateComparison(button.getAttribute('data-view'));
            });
        });
        activateComparison('numinix');

        document.querySelectorAll('[data-component="cases-carousel"], [data-component="carousel"]').forEach(function (carousel) {
            var viewportSelector = carousel.getAttribute('data-carousel-viewport') || '.nxp-ps-cases__viewport';
            var trackSelector = carousel.getAttribute('data-carousel-track') || '.nxp-ps-cases__track';
            var controlSelector = carousel.getAttribute('data-carousel-control') || '.nxp-ps-cases__control';

            var viewport = viewportSelector ? carousel.querySelector(viewportSelector) : null;
            var track = trackSelector ? carousel.querySelector(trackSelector) : null;
            if (!viewport || !track) {
                return;
            }

            var controls = controlSelector
                ? Array.prototype.slice.call(carousel.querySelectorAll(controlSelector))
                : [];
            var items = Array.prototype.slice.call(track.children);

            function parseGap() {
                var style = window.getComputedStyle(track);
                var gapValue = style.columnGap || style.gap || '0';
                var parsed = parseFloat(gapValue);
                return Number.isNaN(parsed) ? 0 : parsed;
            }

            function getScrollAmount() {
                var firstItem = items[0];
                if (!firstItem) {
                    return viewport.clientWidth;
                }
                return firstItem.getBoundingClientRect().width + parseGap();
            }

            function updateControls() {
                var maxScroll = Math.max(track.scrollWidth - viewport.clientWidth, 0);
                var position = viewport.scrollLeft;
                controls.forEach(function (control) {
                    if (!control) {
                        return;
                    }
                    var direction = control.getAttribute('data-direction');
                    if (direction === 'prev') {
                        control.disabled = position <= 4;
                    } else if (direction === 'next') {
                        control.disabled = position >= (maxScroll - 4);
                    }
                });
            }

            controls.forEach(function (control) {
                if (!control) {
                    return;
                }
                control.addEventListener('click', function () {
                    var direction = control.getAttribute('data-direction') === 'prev' ? -1 : 1;
                    var distance = getScrollAmount() * direction;
                    viewport.scrollBy({
                        left: distance,
                        behavior: prefersReducedMotion() ? 'auto' : 'smooth'
                    });
                    scheduleUpdate();
                });
            });

            var scrollHandlerId = null;
            function scheduleUpdate() {
                if (scrollHandlerId) {
                    window.cancelAnimationFrame(scrollHandlerId);
                }
                scrollHandlerId = window.requestAnimationFrame(updateControls);
            }

            viewport.addEventListener('scroll', scheduleUpdate, { passive: true });
            window.addEventListener('resize', scheduleUpdate);
            updateControls();
        });

        document.querySelectorAll('.nxp-ps-accordion__trigger').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var expanded = trigger.getAttribute('aria-expanded') === 'true';
                var panelId = trigger.getAttribute('aria-controls');
                var panel = panelId ? document.getElementById(panelId) : null;
                if (!panel) {
                    return;
                }
                trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                if (expanded) {
                    panel.hidden = true;
                } else {
                    panel.hidden = false;
                }
            });
        });

        var onboardingRoot = document.querySelector('[data-component="onboarding"]');
        if (!onboardingRoot) {
            return;
        }

        var actionUrl = page.getAttribute('data-action-url') || window.location.href;
        var sessionNode = document.getElementById('nxp-paypal-session');
        var initialSession = {};
        if (sessionNode && sessionNode.textContent) {
            try {
                initialSession = JSON.parse(sessionNode.textContent);
            } catch (error) {
                initialSession = {};
            }
        }

        if (!initialSession || typeof initialSession !== 'object') {
            initialSession = {};
        }

        var sessionDefaults = {
            env: 'sandbox',
            nonce: '',
            tracking_id: '',
            step: 'start',
            code: '',
            merchant_id: '',
            authCode: '',
            sharedId: ''
        };

        var session = Object.assign({}, sessionDefaults, initialSession);

        var state = {
            session: session,
            loading: false,
            popup: null,
            popupMonitor: null,
            pollTimer: null,
            pollInterval: 4000,
            finalizing: false,
            pollAttempts: 0,
            pollStartTime: null
        };

        var startButtons = document.querySelectorAll('[data-onboarding-start]');
        var statusNode = onboardingRoot.querySelector('[data-onboarding-status]');

        function disableStartButtons(disabled) {
            startButtons.forEach(function (button) {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = disabled;
                } else if (button instanceof HTMLAnchorElement) {
                    if (disabled) {
                        button.classList.add('is-disabled');
                        button.setAttribute('aria-disabled', 'true');
                    } else {
                        button.classList.remove('is-disabled');
                        button.removeAttribute('aria-disabled');
                    }
                }
            });
        }

        function setStatus(text, tone) {
            if (!statusNode) {
                return;
            }
            statusNode.textContent = text || '';
            if (tone) {
                statusNode.setAttribute('data-tone', tone);
            } else {
                statusNode.removeAttribute('data-tone');
            }
        }

        function postAction(action, extra) {
            var payload = new URLSearchParams();
            payload.append('nxp_paypal_action', action);
            payload.append('nonce', state.session.nonce || '');
            if (extra && typeof extra === 'object') {
                Object.keys(extra).forEach(function (key) {
                    var value = extra[key];
                    if (value === undefined || value === null || value === '') {
                        return;
                    }
                    payload.append(key, value);
                });
            }

            return fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString(),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Network error');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data || typeof data !== 'object') {
                        throw new Error('Invalid response from server.');
                    }
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to complete the request.');
                    }
                    return data;
                });
        }

        function sendTelemetry(event, context) {
            var payload = new URLSearchParams();
            payload.append('nxp_paypal_action', 'telemetry');
            payload.append('nonce', state.session.nonce || '');
            payload.append('event', event);
            if (context && typeof context === 'object') {
                try {
                    payload.append('context', JSON.stringify(context));
                } catch (error) {
                    // Ignore serialization errors.
                }
            }

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString(),
                credentials: 'same-origin'
            }).catch(function () {
                // Telemetry failures should not surface to the UI.
            });
        }

        function formatEnvironment(value) {
            var normalized = String(value || '').toLowerCase();
            if (normalized === 'live') {
                return 'Live';
            }
            if (normalized === 'sandbox') {
                return 'Sandbox';
            }
            return value || '';
        }

        function cleanUrl() {
            if (!window.history || !window.history.replaceState) {
                return;
            }
            try {
                var url = new URL(window.location.href);
                ['code', 'step', 'tracking_id'].forEach(function (key) {
                    url.searchParams.delete(key);
                });
                window.history.replaceState({}, document.title, url.toString());
            } catch (error) {
                // Ignore malformed URLs.
            }
        }

        var completionSteps = ['finalized', 'completed', 'ready', 'active', 'linked'];

        function isCompletedStep(step) {
            var normalized = String(step || '').toLowerCase();
            return completionSteps.indexOf(normalized) !== -1;
        }

        function openOnboardingWindow(url) {
            url = ensureMiniBrowserDisplayMode(url);

            if (!url) {
                setStatus('PayPal did not return a signup link. Please try again.', 'error');
                disableStartButtons(false);
                return;
            }

            var width = 960;
            var height = 720;
            var left = window.screenX + Math.max((window.outerWidth - width) / 2, 0);
            var top = window.screenY + Math.max((window.outerHeight - height) / 2, 0);
            var features = [
                'width=' + width,
                'height=' + height,
                'left=' + left,
                'top=' + top,
                'resizable=yes',
                'scrollbars=yes'
            ].join(',');

            state.popup = window.open(url, 'paypalOnboarding', features);

            if (!state.popup || state.popup.closed) {
                setStatus('Allow popups for this site to continue with PayPal onboarding.', 'error');
                sendTelemetry('popup_blocked', {
                    tracking_id: state.session.tracking_id || undefined
                });
                disableStartButtons(false);
                return;
            }

            try {
                state.popup.focus();
            } catch (error) {
                // Ignore focus errors.
            }

            sendTelemetry('popup_opened', {
                tracking_id: state.session.tracking_id || undefined,
                method: 'window.open'
            });

            if (state.popupMonitor) {
                window.clearInterval(state.popupMonitor);
            }
            state.popupMonitor = window.setInterval(function () {
                if (!state.popup || state.popup.closed) {
                    window.clearInterval(state.popupMonitor);
                    state.popupMonitor = null;
                    state.popup = null;
                    if (state.session.step === 'waiting' && state.session.tracking_id) {
                        setStatus('Processing your PayPal account details…', 'info');
                        finalizeOnboarding();
                    } else {
                        setStatus('The PayPal window closed before finishing. Start PayPal Signup again when you are ready.', 'error');
                        sendTelemetry('cancelled', {
                            tracking_id: state.session.tracking_id || undefined,
                            reason: 'popup_closed'
                        });
                        disableStartButtons(false);
                    }
                }
            }, 1000);

            setStatus('Follow the steps in the PayPal window to finish connecting your account.', 'info');
        }

        function handleFinalizeResponse(data) {
            if (data.environment) {
                state.session.env = data.environment;
            }
            if (data.step) {
                state.session.step = data.step;
            }
            if (data.merchant_id && isValidMerchantId(data.merchant_id)) {
                state.session.merchant_id = data.merchant_id;
            }
            if (data.auth_code || data.authCode) {
                state.session.authCode = data.auth_code || data.authCode;
            }
            if (data.shared_id || data.sharedId) {
                state.session.sharedId = data.shared_id || data.sharedId;
            }
            if (typeof data.polling_interval === 'number' && !Number.isNaN(data.polling_interval)) {
                state.pollInterval = Math.max(data.polling_interval, 2000);
            }

            if (isCompletedStep(state.session.step)) {
                displayCredentialsIfAvailable(data);
                disableStartButtons(false);
                cleanUrl();
                return;
            }

            setStatus('Processing your PayPal account details…', 'info');
            pollStatus(state.pollInterval);
        }

        function handleStatusResponse(data) {
            if (data.environment) {
                state.session.env = data.environment;
            }
            if (data.step) {
                state.session.step = data.step;
            }
            if (data.merchant_id && isValidMerchantId(data.merchant_id)) {
                state.session.merchant_id = data.merchant_id;
            }
            if (data.auth_code || data.authCode) {
                state.session.authCode = data.auth_code || data.authCode;
            }
            if (data.shared_id || data.sharedId) {
                state.session.sharedId = data.shared_id || data.sharedId;
            }
            if (!state.pollStartTime) {
                state.pollStartTime = Date.now();
            }
            if (typeof data.polling_interval === 'number' && !Number.isNaN(data.polling_interval)) {
                state.pollInterval = Math.max(data.polling_interval, 2000);
            }

            if (isCompletedStep(state.session.step)) {
                displayCredentialsIfAvailable(data);
                disableStartButtons(false);
                cleanUrl();
                if (state.pollTimer) {
                    window.clearTimeout(state.pollTimer);
                    state.pollTimer = null;
                }
                state.pollAttempts = 0;
                state.pollStartTime = null;
                return;
            }

            state.pollAttempts += 1;
            var elapsed = Date.now() - state.pollStartTime;
            var exceededAttempts = state.pollAttempts >= MAX_POLL_ATTEMPTS;
            var exceededDuration = elapsed >= MAX_POLL_DURATION_MS;

            if (exceededAttempts || exceededDuration) {
                if (state.pollTimer) {
                    window.clearTimeout(state.pollTimer);
                    state.pollTimer = null;
                }
                var timeoutMessage = 'We were unable to finish connecting to PayPal. Please close this window and start the signup again.';
                setStatus(timeoutMessage, 'error');
                disableStartButtons(false);
                sendTelemetry('status_timeout', {
                    tracking_id: state.session.tracking_id || undefined,
                    attempts: state.pollAttempts,
                    elapsed_ms: elapsed,
                    last_step: state.session.step
                });
                return;
            }

            setStatus('Still waiting on PayPal…', 'info');
            pollStatus(state.pollInterval);
        }

        function displayCredentialsIfAvailable(data) {
            console.log('[CALLBACK TEST - Numinix] displayCredentialsIfAvailable called with data:', {
                hasCredentials: !!(data && data.credentials),
                hasClientId: !!(data && data.credentials && data.credentials.client_id),
                hasClientSecret: !!(data && data.credentials && data.credentials.client_secret),
                credentials: data && data.credentials ? data.credentials : null
            });
            
            if (data.credentials && data.credentials.client_id && data.credentials.client_secret) {
                var env = formatEnvironment(data.environment || (state.session && state.session.env) || 'sandbox');
                
                // Create credentials display in the status area
                if (statusNode) {
                    var credentialsHtml = '<div class="nxp-ps-credentials">';
                    credentialsHtml += '<h3>✓ PayPal Onboarding Complete</h3>';
                    credentialsHtml += '<p class="nxp-ps-credentials__intro">Save these credentials in your PayPal module configuration:</p>';
                    credentialsHtml += '<dl class="nxp-ps-credentials__list">';
                    credentialsHtml += '<dt>Environment:</dt><dd>' + env + '</dd>';
                    credentialsHtml += '<dt>Client ID:</dt><dd><code>' + htmlEscape(data.credentials.client_id) + '</code></dd>';
                    credentialsHtml += '<dt>Client Secret:</dt><dd><code>' + htmlEscape(data.credentials.client_secret) + '</code></dd>';
                    credentialsHtml += '</dl>';
                    credentialsHtml += '<p class="nxp-ps-credentials__warning">⚠️ Store these credentials securely. Do not share them publicly.</p>';
                    credentialsHtml += '</div>';
                    statusNode.innerHTML = credentialsHtml;
                    statusNode.setAttribute('data-tone', 'success');
                    console.log('[CALLBACK TEST - Numinix] Credentials displayed successfully');
                }
                
                // Close the popup window after credentials are displayed
                // This allows the parent page to continue with the flow
                if (state.popup && !state.popup.closed) {
                    console.log('[CALLBACK TEST - Numinix] Attempting to close popup window');
                    try {
                        state.popup.close();
                    } catch (e) {
                        console.log('[CALLBACK TEST - Numinix] Could not close popup:', e);
                    }
                    state.popup = null;
                }
                
                // Also clear the popup monitor if it's running
                if (state.popupMonitor) {
                    window.clearInterval(state.popupMonitor);
                    state.popupMonitor = null;
                }
            } else {
                setStatus('PayPal account connected. You\'re all set.', 'success');
                console.log('[CALLBACK TEST - Numinix] No credentials to display, showing success message');
            }
        }

        function htmlEscape(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function pollStatus(delay) {
            if (state.pollTimer) {
                window.clearTimeout(state.pollTimer);
            }
            var wait = Math.max(delay || state.pollInterval, 2000);
            state.pollTimer = window.setTimeout(function () {
                postAction('status', {
                    tracking_id: state.session.tracking_id || '',
                    merchant_id: state.session.merchant_id || '',
                    authCode: state.session.authCode || '',
                    sharedId: state.session.sharedId || ''
                })
                    .then(function (response) {
                        handleStatusResponse(response.data || {});
                        sendTelemetry('status_success', {
                            tracking_id: state.session.tracking_id || undefined,
                            step: state.session.step,
                            polling_interval: state.pollInterval
                        });
                    })
                    .catch(function (error) {
                        setStatus(error && error.message ? error.message : 'We could not check your PayPal status. Please try again.', 'error');
                        disableStartButtons(false);
                        sendTelemetry('status_failed', {
                            tracking_id: state.session.tracking_id || undefined,
                            error: error && error.message ? error.message : 'unknown'
                        });
                    });
            }, wait);
        }

        function startOnboarding(event) {
            if (event) {
                event.preventDefault();
            }
            if (state.loading) {
                return;
            }
            state.pollAttempts = 0;
            state.pollStartTime = null;
            state.loading = true;
            disableStartButtons(true);
            setStatus('Starting secure PayPal signup…', 'info');

            sendTelemetry('start', {
                tracking_id: state.session.tracking_id || undefined
            });

            postAction('start')
                .then(function (response) {
                    var data = response.data || {};
                    if (data.tracking_id) {
                        state.session.tracking_id = data.tracking_id;
                    }
                    if (data.step) {
                        state.session.step = data.step;
                    }
                    state.session.code = '';
                    state.session.merchant_id = '';
                    state.session.authCode = '';
                    state.session.sharedId = '';

                    sendTelemetry('start_success', {
                        tracking_id: state.session.tracking_id || undefined,
                        redirect_url: data.redirect_url || data.action_url,
                        step: state.session.step
                    });

                    var redirectUrl = data.redirect_url || data.action_url || '';
                    if (!redirectUrl && Array.isArray(data.links)) {
                        data.links.forEach(function (link) {
                            if (link && link.rel === 'action_url' && link.href) {
                                redirectUrl = link.href;
                            }
                        });
                    }

                    state.loading = false;
                    openOnboardingWindow(redirectUrl);
                })
                .catch(function (error) {
                    state.loading = false;
                    setStatus(error && error.message ? error.message : 'We could not start the PayPal signup. Please try again.', 'error');
                    disableStartButtons(false);
                    sendTelemetry('start_failed', {
                        tracking_id: state.session.tracking_id || undefined,
                        error: error && error.message ? error.message : 'unknown'
                    });
                });
        }

        function finalizeOnboarding() {
            if (state.finalizing) {
                return;
            }
            var payload = {};
            if (state.session.code) {
                payload.code = state.session.code;
            }
            if (state.session.tracking_id) {
                payload.tracking_id = state.session.tracking_id;
            }
            if (state.session.merchant_id) {
                payload.merchant_id = state.session.merchant_id;
            }
            if (state.session.authCode) {
                payload.authCode = state.session.authCode;
            }
            if (state.session.sharedId) {
                payload.sharedId = state.session.sharedId;
            }

            console.log('[CALLBACK TEST - Numinix] Finalize payload being sent:', payload);

            if (!payload.code && !payload.tracking_id) {
                return;
            }

            state.finalizing = true;
            postAction('finalize', payload)
                .then(function (response) {
                    console.log('[CALLBACK TEST - Numinix] Finalize response received:', response);
                    handleFinalizeResponse(response.data || {});
                    sendTelemetry('finalize_success', {
                        tracking_id: state.session.tracking_id || undefined,
                        step: state.session.step,
                        polling_interval: state.pollInterval
                    });
                })
                .catch(function (error) {
                    console.log('[CALLBACK TEST - Numinix] Finalize error:', error);
                    setStatus(error && error.message ? error.message : 'We could not finalize your PayPal signup. Please try again.', 'error');
                    disableStartButtons(false);
                    sendTelemetry('finalize_failed', {
                        tracking_id: state.session.tracking_id || undefined,
                        error: error && error.message ? error.message : 'unknown'
                    });
                })
                .finally(function () {
                    state.session.code = '';
                    state.finalizing = false;
                });
        }

        function handlePopupMessage(event) {
            console.log('[CALLBACK TEST - Numinix] handlePopupMessage called', {
                hasPopup: !!state.popup,
                hasEvent: !!event,
                hasEventSource: !!(event && event.source),
                eventOrigin: event ? event.origin : 'N/A',
                sourcesMatch: !!(event && event.source && state.popup && event.source === state.popup),
                currentLocation: window.location.origin
            });

            // Temporarily relaxed: Accept messages from PayPal domains or same origin
            var isFromPayPal = event && event.origin && (
                event.origin.indexOf('paypal.com') !== -1 ||
                event.origin.indexOf('paypalobjects.com') !== -1
            );
            var isFromSameOrigin = event && event.origin === window.location.origin;
            var isValidSource = event && (isFromPayPal || isFromSameOrigin);

            if (!isValidSource) {
                console.log('[CALLBACK TEST - Numinix] Ignoring message - not from PayPal or same origin. Origin:', event ? event.origin : 'N/A');
                return;
            }
            
            // Additional check: If we have a popup reference, verify it matches (but don't reject if popup is null)
            if (state.popup && event && event.source && event.source !== state.popup) {
                console.log('[CALLBACK TEST - Numinix] Warning: Message source does not match our popup reference, but accepting anyway');
            }

            var rawData = event && event.data;
            if (!rawData) {
                console.log('[CALLBACK TEST - Numinix] No data in message');
                return;
            }

            var payload = rawData;
            if (typeof rawData === 'string') {
                try {
                    payload = JSON.parse(rawData);
                } catch (error) {
                    payload = { event: rawData };
                }
            }

            if (!payload || typeof payload !== 'object') {
                console.log('[CALLBACK TEST - Numinix] Payload is not an object');
                return;
            }

            console.log('[CALLBACK TEST - Numinix] Received postMessage payload:', payload);

            var eventName = toNonEmptyString(getFirstMatchingValue(payload, ['event', 'type']));

            var normalized = eventName.toLowerCase();
            
            // PayPal returns authCode as 'onboardedCompleteToken' in their callback
            var authCode = toNonEmptyString(getFirstMatchingValue(payload, [
                'authCode',
                'auth_code',
                'authcode',
                'onboardedCompleteToken',
                'onboarding_complete_token'
            ]));
            var sharedId = toNonEmptyString(getFirstMatchingValue(payload, [
                'sharedId',
                'sharedID',
                'shared_id',
                'sharedid'
            ]));
            var merchantId = toNonEmptyString(getFirstMatchingValue(payload, [
                'merchantId',
                'merchantID',
                'merchant_id',
                'merchantIdInPayPal'
            ]));
            var trackingId = toNonEmptyString(getFirstMatchingValue(payload, [
                'tracking_id',
                'trackingId'
            ]));
            var completionFlagValue = getFirstMatchingValue(payload, [
                'paypal_onboarding_complete',
                'paypalOnboardingComplete'
            ]);
            var completionFlag = completionFlagValue === true || completionFlagValue === 'true';
            var environment = toNonEmptyString(getFirstMatchingValue(payload, [
                'env',
                'environment'
            ]));
            
            // Check if this is a completion event from PayPal
            // PayPal's direct callback includes onboardedCompleteToken and sharedId (no event property)
            var completionEvent = normalized === 'paypal_onboarding_complete'
                || normalized === 'paypal_partner_onboarding_complete'
                || completionFlag
                || (authCode && sharedId); // PayPal's direct callback format

            console.log('[CALLBACK TEST - Numinix] Event analysis:', {
                eventName: eventName,
                normalized: normalized,
                isCompletionEvent: completionEvent,
                hasTrackingId: !!state.session.tracking_id,
                authCode: authCode,
                sharedId: sharedId,
                merchantId: merchantId,
                trackingId: trackingId,
                hasOnboardedCompleteToken: !!toNonEmptyString(getFirstMatchingValue(payload, ['onboardedCompleteToken', 'onboarding_complete_token']))
            });

            if (!state.session.tracking_id && trackingId) {
                state.session.tracking_id = trackingId;
                console.log('[CALLBACK TEST - Numinix] Backfilled tracking_id from payload:', trackingId);
            }

            if (!completionEvent || !state.session.tracking_id) {
                console.log('[CALLBACK TEST - Numinix] Not a completion event or missing tracking_id - ignoring');
                return;
            }

            // Capture authCode/sharedId/merchantId from PayPal postMessage for credential exchange
            if (merchantId && isValidMerchantId(merchantId)) {
                state.session.merchant_id = merchantId;
            }
            if (authCode) {
                state.session.authCode = authCode;
                console.log('[CALLBACK TEST - Numinix] Captured authCode:', authCode);
            }
            if (sharedId) {
                state.session.sharedId = sharedId;
                console.log('[CALLBACK TEST - Numinix] Captured sharedId:', sharedId);
            }
            if (trackingId && !state.session.tracking_id) {
                state.session.tracking_id = trackingId;
                console.log('[CALLBACK TEST - Numinix] Captured tracking_id from payload:', trackingId);
            }
            if (environment) {
                state.session.env = environment;
            }

            console.log('[CALLBACK TEST - Numinix] Processing PayPal completion - calling finalizeOnboarding');
            console.log('[CALLBACK TEST - Numinix] Session state before finalize:', {
                tracking_id: state.session.tracking_id,
                authCode: state.session.authCode,
                sharedId: state.session.sharedId,
                merchant_id: state.session.merchant_id
            });
            setStatus('Processing your PayPal account details…', 'info');
            finalizeOnboarding();
        }

        startButtons.forEach(function (button) {
            button.addEventListener('click', startOnboarding);
        });

        window.addEventListener('message', handlePopupMessage);
        console.log('[CALLBACK TEST - Numinix] Message event listener attached - ready to receive postMessage');

        disableStartButtons(false);

        if (isCompletedStep(state.session.step)) {
            setStatus('PayPal account connected. You\'re all set.', 'success');
        } else if (state.session.step && state.session.step !== 'start') {
            if (state.session.step === 'waiting') {
                setStatus('Resume your PayPal signup to finish connecting your account.', 'info');
            } else if (state.session.step === 'cancelled') {
                setStatus('You left before finishing. Start PayPal Signup to continue.', 'info');
            } else {
                setStatus('Start PayPal Signup to continue your onboarding.', 'info');
            }
        } else {
            setStatus('Start PayPal Signup to connect your PayPal account.', 'info');
        }

        if (state.session.code) {
            sendTelemetry('returned_from_paypal', {
                tracking_id: state.session.tracking_id || undefined,
                method: 'redirect'
            });
            finalizeOnboarding();
        }
    });
})(window, document);
