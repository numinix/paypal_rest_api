/**
 * PayPal Google Pay Integration - Native API Implementation
 *
 * This module implements PayPal's native Google Pay integration using:
 * - paypal.Googlepay().config() for payment configuration
 * - google.payments.api.PaymentsClient for Google Pay client
 * - paypal.Googlepay().confirmOrder() for order confirmation
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/googlepay/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        googlepay: null,
        paymentsClient: null
    };

    var sharedSdkLoader = window.paypalrSdkLoaderState || { key: null, promise: null };
    window.paypalrSdkLoaderState = sharedSdkLoader;

    var googlePayJsLoaded = false;
    var googlePayJsPromise = null;

    // -------------------------------------------------------------------------
    // Utility Functions
    // -------------------------------------------------------------------------

    /**
     * Get CSP nonce from existing script tags if available.
     * This helps comply with Content Security Policy when loading external scripts.
     */
    function getCspNonce() {
        var existingScript = document.querySelector('script[nonce]');
        return existingScript ? existingScript.nonce || existingScript.getAttribute('nonce') : '';
    }

    function hasPayloadData(payload) {
        if (!payload) {
            return false;
        }

        if (typeof payload === 'object') {
            return Object.keys(payload).length > 0;
        }

        return true;
    }

    function submitCheckoutForm() {
        var form = document.querySelector('form[name="checkout_payment"]');
        if (!form) {
            return;
        }

        checkoutSubmitting = true;

        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
        }

        var previousAllowState = typeof window.oprcAllowNativeCheckoutSubmit !== 'undefined'
            ? window.oprcAllowNativeCheckoutSubmit
            : false;
        window.oprcAllowNativeCheckoutSubmit = true;

        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else if (typeof form.submit === 'function') {
                form.submit();
            }
        } finally {
            window.oprcAllowNativeCheckoutSubmit = previousAllowState;
        }
    }

    function setGooglePayPayload(payload) {
        var payloadField = document.getElementById('paypalr-googlepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Google Pay payload', error);
                payloadField.value = '';
            }
        }

        var payloadPresent = hasPayloadData(payload);
        var statusField = document.getElementById('paypalr-googlepay-status');
        if (statusField) {
            statusField.value = payloadPresent ? 'approved' : '';
        }

        if (payloadPresent && !checkoutSubmitting) {
            selectGooglePayRadio();
            submitCheckoutForm();
        }

        if (!payloadPresent) {
            checkoutSubmitting = false;
        }
    }

    /**
     * Select the Google Pay module radio button.
     * Called when the user interacts with the Google Pay button.
     */
    function selectGooglePayRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_googlepay');
        if (moduleRadio && moduleRadio.type === 'radio' && !moduleRadio.checked) {
            moduleRadio.checked = true;
            // Trigger change event for any listeners
            if (typeof jQuery !== 'undefined') {
                jQuery(moduleRadio).trigger('change');
            } else {
                moduleRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    /**
     * Hide the module's radio button using CSS.
     * The radio is still functional but visually hidden.
     */
    function hideModuleRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_googlepay');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalr-wallet-radio-hidden');
        }
    }

    /**
     * Hide the entire payment method container when payment is not eligible.
     * This hides the parent element (e.g., paypalr_googlepay-custom-control-container)
     * so the user doesn't see an unavailable payment option.
     */
    function hidePaymentMethodContainer() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            return;
        }

        // Find the parent container that wraps this payment method
        // Common patterns: closest .moduleRow, closest payment container div, or parent with class containing 'container'
        var parentContainer = container.closest('[id*="paypalr_googlepay"][id*="container"]')
            || container.closest('.moduleRow')
            || container.closest('[class*="paypalr_googlepay"]');

        // If we found a specific parent container, hide it
        if (parentContainer) {
            parentContainer.style.display = 'none';
            return;
        }

        // Fallback: traverse up and hide a suitable parent
        // Look for common payment module wrapper patterns
        var parent = container.parentElement;
        var depth = 0;
        var maxDepth = 5;

        while (parent && depth < maxDepth) {
            // Check if parent has an ID or class indicating it's a payment container
            var parentId = (parent.id || '').toLowerCase();
            var parentClass = (parent.className || '').toLowerCase();

            if (parentId.indexOf('paypalr_googlepay') !== -1 ||
                parentClass.indexOf('paypalr_googlepay') !== -1 ||
                parentClass.indexOf('modulerow') !== -1) {
                parent.style.display = 'none';
                return;
            }
            parent = parent.parentElement;
            depth++;
        }

        // Last resort: just hide the button container itself and clear content
        container.style.display = 'none';
    }

    /**
     * Hide the textual label so the PayPal-rendered button (or placeholder)
     * is the only visible call-to-action.
     */
    function hideModuleLabel() {
        var moduleLabel = document.querySelector('label[for="pmt-paypalr_googlepay"]');
        if (moduleLabel) {
            moduleLabel.classList.add('paypalr-wallet-label-hidden');
        }
    }

    function rerenderGooglePayButton() {
        if (typeof window.paypalrGooglePayRender === 'function') {
            window.paypalrGooglePayRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalr:googlepay:rerender'));
        }
    }

    // -------------------------------------------------------------------------
    // API Communication
    // -------------------------------------------------------------------------

    function parseWalletResponse(response) {
        var contentType = (response.headers && response.headers.get('content-type')) || '';

        if (!response.ok) {
            return response.text().then(function (body) {
                var message = 'Wallet endpoint returned HTTP ' + response.status;
                var trimmed = (body || '').trim();

                if (trimmed) {
                    message += ': ' + trimmed;
                }

                throw new Error(message);
            });
        }

        if (contentType.indexOf('application/json') === -1) {
            return response.text().then(function (body) {
                throw new Error('Wallet endpoint did not return JSON: ' + (body || '').trim());
            });
        }

        return response.json();
    }

    /**
     * Fetch SDK configuration only (no order creation).
     * Used during initial button rendering.
     */
    function fetchWalletConfig() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'google_pay', config_only: true })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to load Google Pay configuration', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to load Google Pay configuration' };
        });
    }

    /**
     * Create a PayPal order for Google Pay.
     * Called when user clicks the Google Pay button.
     */
    function fetchWalletOrder() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'google_pay' })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to create Google Pay order', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Google Pay order' };
        });
    }

    // -------------------------------------------------------------------------
    // SDK Loading
    // -------------------------------------------------------------------------

    function buildSdkKey(config) {
        var currency = config.currency || 'USD';
        var googleMerchantId = config.googleMerchantId || config.merchantId || '';
        var environment = config.environment || 'sandbox';
        return [config.clientId, currency, googleMerchantId, environment].join('|');
    }

    /**
     * Load the Google Pay JavaScript library from Google.
     * This is required for native Google Pay integration.
     */
    function loadGooglePayJs() {
        if (googlePayJsLoaded && typeof google !== 'undefined' && google.payments && google.payments.api) {
            return Promise.resolve();
        }

        if (googlePayJsPromise) {
            return googlePayJsPromise;
        }

        var existingScript = document.querySelector('script[src*="pay.google.com/gp/p/js/pay.js"]');
        if (existingScript) {
            if (typeof google !== 'undefined' && google.payments && google.payments.api) {
                googlePayJsLoaded = true;
                return Promise.resolve();
            }

            googlePayJsPromise = new Promise(function (resolve, reject) {
                existingScript.addEventListener('load', function () {
                    googlePayJsLoaded = true;
                    resolve();
                });
                existingScript.addEventListener('error', function (event) {
                    googlePayJsPromise = null;
                    reject(new Error('Failed to load Google Pay JS'));
                });
            });

            return googlePayJsPromise;
        }

        googlePayJsPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://pay.google.com/gp/p/js/pay.js';
            script.async = true;
            
            // Add CSP nonce if available
            var nonce = getCspNonce();
            if (nonce) {
                script.setAttribute('nonce', nonce);
            }
            
            script.onload = function () {
                googlePayJsLoaded = true;
                resolve();
            };
            script.onerror = function () {
                googlePayJsPromise = null;
                reject(new Error('Failed to load Google Pay JS'));
            };
            document.head.appendChild(script);
        });

        return googlePayJsPromise;
    }

    /**
     * Load the PayPal SDK with googlepay component.
     * Uses buyer-country parameter for sandbox mode.
     */
    function loadPayPalSdk(config) {
        if (!config || !config.clientId) {
            return Promise.reject(new Error('Missing clientId for PayPal SDK load'));
        }

        var desiredKey = buildSdkKey(config);
        var googleMerchantId = config.googleMerchantId || config.merchantId || '';
        var existingScript = document.querySelector('script[data-paypal-sdk="true"]');
        var isSandbox = config.environment === 'sandbox';

        if (sharedSdkLoader.promise && sharedSdkLoader.key === desiredKey && window.paypal && typeof window.paypal.Googlepay === 'function') {
            return sharedSdkLoader.promise.then(function () { return window.paypal; });
        }

        if (existingScript) {
            var matchesClient = existingScript.src.indexOf(encodeURIComponent(config.clientId)) !== -1;
            var matchesCurrency = existingScript.src.indexOf('currency=' + encodeURIComponent(config.currency || 'USD')) !== -1;
            var matchesMerchant = !googleMerchantId || existingScript.src.indexOf('google-pay-merchant-id=' + encodeURIComponent(googleMerchantId)) !== -1;

            if (matchesClient && matchesCurrency && matchesMerchant) {
                if (existingScript.dataset.loaded === 'true' && window.paypal && typeof window.paypal.Googlepay === 'function') {
                    sharedSdkLoader.key = desiredKey;
                    sharedSdkLoader.promise = Promise.resolve(window.paypal);
                    return sharedSdkLoader.promise;
                }

                return new Promise(function (resolve, reject) {
                    existingScript.addEventListener('load', function () {
                        existingScript.dataset.loaded = 'true';
                        sharedSdkLoader.key = desiredKey;
                        resolve(window.paypal);
                    });
                    existingScript.addEventListener('error', function (event) {
                        sharedSdkLoader.promise = null;
                        reject(event);
                    });
                });
            }

            existingScript.parentNode.removeChild(existingScript);
        }

        // Build SDK URL with all wallet components to support multiple payment methods
        // Note: The 'intent' parameter is NOT a valid PayPal SDK URL parameter.
        // Intent (capture/authorize) is specified when creating the PayPal order, not when loading the SDK.
        var query = '?client-id=' + encodeURIComponent(config.clientId)
            + '&components=buttons,googlepay,applepay'
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        // Add buyer-country parameter for sandbox mode (required for testing)
        if (isSandbox) {
            query += '&buyer-country=US';
        }

        // Include Google Pay merchant ID when provided to ensure allowedPaymentMethods are returned.
        // Google Merchant IDs are numeric (and sometimes alphanumeric) strings provided by Google.
        var googleMerchantId = config.googleMerchantId || config.merchantId;
        if (googleMerchantId && /^[A-Z0-9-]{5,30}$/i.test(googleMerchantId)) {
            query += '&google-pay-merchant-id=' + encodeURIComponent(googleMerchantId);
        }

        sharedSdkLoader.promise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js' + query;
            script.dataset.paypalSdk = 'true';
            script.dataset.loaded = 'false';
            
            // Add CSP nonce if available
            var nonce = getCspNonce();
            if (nonce) {
                script.setAttribute('nonce', nonce);
            }
            
            script.onload = function () {
                script.dataset.loaded = 'true';
                sharedSdkLoader.key = desiredKey;
                window.paypalrSdkConfig = {
                    clientId: config.clientId,
                    currency: config.currency,
                    merchantId: config.merchantId,
                    googleMerchantId: config.googleMerchantId || config.merchantId,
                    environment: config.environment
                };
                resolve(window.paypal);
            };
            script.onerror = function (event) {
                sharedSdkLoader.promise = null;
                reject(event);
            };
            document.head.appendChild(script);
        });

        return sharedSdkLoader.promise;
    }

    // -------------------------------------------------------------------------
    // Native Google Pay Integration
    // -------------------------------------------------------------------------

    function getAllowedPaymentMethods(config) {
        if (config && Array.isArray(config.allowedPaymentMethods) && config.allowedPaymentMethods.length > 0) {
            return config.allowedPaymentMethods;
        }

        return null;
    }

    /**
     * Handle the Google Pay button click event.
     * Creates a PayPal order and initiates the Google Pay payment flow.
     */
    function onGooglePayButtonClicked() {
        selectGooglePayRadio();

        // Show processing overlay if available
        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
        }

        // Step 1: Create PayPal order
        fetchWalletOrder().then(function (orderConfig) {
            if (!orderConfig || orderConfig.success === false) {
                console.error('Failed to create PayPal order for Google Pay', orderConfig);
                setGooglePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return;
            }

            sdkState.config = orderConfig;
            var orderId = orderConfig.orderID;

            // Step 2: Get Google Pay payment data request from PayPal
            var googlepay = sdkState.googlepay;
            var paymentsClient = sdkState.paymentsClient;

            if (!googlepay || !paymentsClient) {
                console.error('Google Pay not properly initialized');
                setGooglePayPayload({});
                return;
            }

            // Get the payment data request configuration from PayPal
            var paymentDataRequest = googlepay.config();
            var allowedPaymentMethods = getAllowedPaymentMethods(paymentDataRequest);

            if (!allowedPaymentMethods) {
                console.error('Google Pay configuration is missing allowedPaymentMethods');
                setGooglePayPayload({});
                return;
            }

            paymentDataRequest.allowedPaymentMethods = allowedPaymentMethods;

            // Override transaction info with actual order data
            paymentDataRequest.transactionInfo = {
                totalPriceStatus: 'FINAL',
                totalPrice: orderConfig.amount || '0.00',
                currencyCode: orderConfig.currency || 'USD',
                countryCode: 'US'
            };

            // Step 3: Invoke Google Pay payment sheet
            paymentsClient.loadPaymentData(paymentDataRequest)
                .then(function (paymentData) {
                    // Step 4: Confirm the order with PayPal using the Google Pay token
                    var token = paymentData.paymentMethodData.tokenizationData.token;

                    return googlepay.confirmOrder({
                        orderId: orderId,
                        paymentMethodData: paymentData.paymentMethodData
                    });
                })
                .then(function (confirmResult) {
                    // Step 5: Handle successful confirmation
                    if (confirmResult.status === 'APPROVED' || confirmResult.status === 'PAYER_ACTION_REQUIRED') {
                        var payload = {
                            orderID: orderId,
                            confirmResult: confirmResult,
                            wallet: 'google_pay'
                        };
                        setGooglePayPayload(payload);
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: payload }));
                    } else {
                        console.warn('Google Pay confirmation returned unexpected status', confirmResult);
                        setGooglePayPayload({});
                    }
                })
                .catch(function (error) {
                    // Handle errors or user cancellation
                    if (error.statusCode === 'CANCELED') {
                        console.log('Google Pay cancelled by user');
                    } else {
                        console.error('Google Pay error', error);
                    }
                    setGooglePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                });
        }).catch(function (error) {
            console.error('Failed to create PayPal order', error);
            setGooglePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
        });
    }

    /**
     * Render the native Google Pay button using Google's PaymentsClient.
     */
    function renderGooglePayButton() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        // First, fetch only the SDK configuration (no order creation)
        fetchWalletConfig().then(function (config) {
            if (!config || config.success === false) {
                console.warn('Unable to load Google Pay configuration', config);
                hidePaymentMethodContainer();
                return null;
            }

            // Google Pay requires a merchant ID in production mode
            // In sandbox mode, Google Pay can be tested without a merchant ID
            var isSandbox = config.environment === 'sandbox';
            var googleMerchantId = config.googleMerchantId || config.merchantId;
            var hasMerchantId = googleMerchantId && /^[A-Z0-9-]{5,30}$/i.test(googleMerchantId);

            if (!isSandbox && !hasMerchantId) {
                console.warn('Google Pay: Invalid or missing Google Merchant ID (required in production)');
                hidePaymentMethodContainer();
                return null;
            }

            sdkState.config = config;

            // Load both PayPal SDK and Google Pay JS in parallel
            return Promise.all([
                loadPayPalSdk(config),
                loadGooglePayJs()
            ]).then(function (results) {
                var paypal = results[0];

                // Verify PayPal Googlepay API is available
                if (typeof paypal.Googlepay !== 'function') {
                    console.warn('PayPal Googlepay API not available');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Initialize PayPal Google Pay
                var googlepay = paypal.Googlepay({
                    merchantId: hasMerchantId ? googleMerchantId : undefined
                });
                sdkState.googlepay = googlepay;

                // Check eligibility using PayPal's isEligible method
                if (typeof googlepay.isEligible === 'function' && !googlepay.isEligible()) {
                    console.log('Google Pay is not eligible for this user/device');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Create Google Payments Client
                // Use environment from config (stored in sdkState) to determine Google Pay environment
                var googlePayEnvironment = (sdkState.config && sdkState.config.environment === 'sandbox') ? 'TEST' : 'PRODUCTION';
                var paymentsClient = new google.payments.api.PaymentsClient({
                    environment: googlePayEnvironment
                });
                sdkState.paymentsClient = paymentsClient;

                // Get base configuration from PayPal for isReadyToPay check
                var baseConfig = googlepay.config();
                var allowedPaymentMethods = getAllowedPaymentMethods(baseConfig);

                if (!allowedPaymentMethods) {
                    console.error('Google Pay configuration is missing allowedPaymentMethods');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Check if user is ready to pay with Google Pay
                var isReadyToPayRequest = {
                    apiVersion: baseConfig.apiVersion || 2,
                    apiVersionMinor: baseConfig.apiVersionMinor || 0,
                    allowedPaymentMethods: allowedPaymentMethods
                };

                return paymentsClient.isReadyToPay(isReadyToPayRequest)
                    .then(function (response) {
                        if (!response.result) {
                            console.log('Google Pay is not ready to pay on this device');
                            hidePaymentMethodContainer();
                            return null;
                        }

                        // Create and render the Google Pay button
                        var button = paymentsClient.createButton({
                            onClick: onGooglePayButtonClicked,
                            buttonColor: 'black',
                            buttonType: 'pay',
                            buttonRadius: 4,
                            buttonSizeMode: 'fill'
                        });

                        container.appendChild(button);

                        return button;
                    });
            });
        }).catch(function (error) {
            console.error('Failed to render Google Pay button', error);
            hidePaymentMethodContainer();
        });
    }

    // -------------------------------------------------------------------------
    // Order Total Observer
    // -------------------------------------------------------------------------

    function observeOrderTotal() {
        var totalElement = document.getElementById('ottotal');
        if (!totalElement || typeof MutationObserver === 'undefined') {
            return;
        }

        var rerenderTimeout = null;

        var observer = new MutationObserver(function (mutations) {
            var hasRelevantChange = mutations.some(function (mutation) {
                return mutation.type === 'characterData' || mutation.type === 'childList';
            });

            if (!hasRelevantChange) {
                return;
            }

            clearTimeout(rerenderTimeout);
            rerenderTimeout = setTimeout(rerenderGooglePayButton, 50);
        });

        observer.observe(totalElement, { childList: true, subtree: true, characterData: true });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    window.paypalrGooglePaySetPayload = setGooglePayPayload;
    window.paypalrGooglePaySelectRadio = selectGooglePayRadio;

    document.addEventListener('paypalr:googlepay:payload', function (event) {
        setGooglePayPayload(event.detail || {});
    });

    // Hide the radio button on page load
    hideModuleRadio();
    hideModuleLabel();

    // Add click handler to the button container to select the radio
    var container = document.getElementById('paypalr-googlepay-button');
    if (container) {
        container.addEventListener('click', function () {
            selectGooglePayRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-googlepay-placeholder">' + (typeof paypalrGooglePayText !== 'undefined' ? paypalrGooglePayText : 'Google Pay') + '</span>';
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalrGooglePayRender = renderGooglePayButton;
    }

    renderGooglePayButton();

    observeOrderTotal();
})();
