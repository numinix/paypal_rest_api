/**
 * PayPal Apple Pay Integration - Native API Implementation
 *
 * This module implements PayPal's native Apple Pay integration using:
 * - paypal.Applepay().config() for payment configuration
 * - ApplePaySession for native Apple Pay button and payment sheet
 * - paypal.Applepay().confirmOrder() for order confirmation
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/applepay/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    // PayPal order status constants
    var PAYPAL_STATUS = {
        APPROVED: 'APPROVED',
        PAYER_ACTION_REQUIRED: 'PAYER_ACTION_REQUIRED'
    };

    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        applepay: null
    };

    var sharedSdkLoader = window.paypalrSdkLoaderState || { key: null, promise: null };
    window.paypalrSdkLoaderState = sharedSdkLoader;

    // -------------------------------------------------------------------------
    // Utility Functions
    // -------------------------------------------------------------------------

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

    function setApplePayPayload(payload) {
        var payloadField = document.getElementById('paypalr-applepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Apple Pay payload', error);
                payloadField.value = '';
            }
        }

        var payloadPresent = hasPayloadData(payload);
        var statusField = document.getElementById('paypalr-applepay-status');
        if (statusField) {
            statusField.value = payloadPresent ? 'approved' : '';
        }

        if (payloadPresent && !checkoutSubmitting) {
            selectApplePayRadio();
            submitCheckoutForm();
        }

        if (!payloadPresent) {
            checkoutSubmitting = false;
        }
    }

    /**
     * Select the Apple Pay module radio button.
     * Called when the user interacts with the Apple Pay button.
     */
    function selectApplePayRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_applepay');
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
        var moduleRadio = document.getElementById('pmt-paypalr_applepay');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalr-wallet-radio-hidden');
        }
    }

    /**
     * Hide the entire payment method container when payment is not eligible.
     * This hides the parent element (e.g., paypalr_applepay-custom-control-container)
     * so the user doesn't see an unavailable payment option.
     */
    function hidePaymentMethodContainer() {
        var container = document.getElementById('paypalr-applepay-button');
        if (!container) {
            return;
        }

        // Find the parent container that wraps this payment method
        // Common patterns: closest .moduleRow, closest payment container div, or parent with class containing 'container'
        var parentContainer = container.closest('[id*="paypalr_applepay"][id*="container"]')
            || container.closest('.moduleRow')
            || container.closest('[class*="paypalr_applepay"]');

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

            if (parentId.indexOf('paypalr_applepay') !== -1 ||
                parentClass.indexOf('paypalr_applepay') !== -1 ||
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
        var moduleLabel = document.querySelector('label[for="pmt-paypalr_applepay"]');
        if (moduleLabel) {
            moduleLabel.classList.add('paypalr-wallet-label-hidden');
        }
    }

    function rerenderApplePayButton() {
        if (typeof window.paypalrApplePayRender === 'function') {
            window.paypalrApplePayRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalr:applepay:rerender'));
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
            body: JSON.stringify({ wallet: 'apple_pay', config_only: true })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to load Apple Pay configuration', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to load Apple Pay configuration' };
        });
    }

    /**
     * Create a PayPal order for Apple Pay.
     * Called when user clicks the Apple Pay button.
     */
    function fetchWalletOrder() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'apple_pay' })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to create Apple Pay order', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Apple Pay order' };
        });
    }

    // -------------------------------------------------------------------------
    // SDK Loading
    // -------------------------------------------------------------------------

    function buildSdkKey(config) {
        var currency = config.currency || 'USD';
        var merchantId = config.merchantId || '';
        var environment = config.environment || 'sandbox';
        var clientId = config.clientId || '';
        return [clientId, currency, merchantId, environment].join('|');
    }

    /**
     * Load the PayPal SDK with applepay component.
     * Uses buyer-country parameter for sandbox mode.
     */
    function loadPayPalSdk(config) {
        if (!config || !config.clientId) {
            return Promise.reject(new Error('Missing clientId for PayPal SDK load'));
        }

        var desiredKey = buildSdkKey(config);
        var existingScript = document.querySelector('script[data-paypal-sdk="true"]');
        var isSandbox = config.environment === 'sandbox';

        if (sharedSdkLoader.promise && sharedSdkLoader.key === desiredKey && window.paypal && typeof window.paypal.Applepay === 'function') {
            return sharedSdkLoader.promise.then(function () { return window.paypal; });
        }

        if (existingScript) {
            var matchesClient = existingScript.src.indexOf(encodeURIComponent(config.clientId)) !== -1;
            var matchesCurrency = existingScript.src.indexOf('currency=' + encodeURIComponent(config.currency || 'USD')) !== -1;
            var matchesMerchant = !config.merchantId || existingScript.src.indexOf('merchant-id=' + encodeURIComponent(config.merchantId)) !== -1;

            if (matchesClient && matchesCurrency && matchesMerchant) {
                if (existingScript.dataset.loaded === 'true' && window.paypal && typeof window.paypal.Applepay === 'function') {
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

        // Build SDK URL with applepay component
        // Note: The 'intent' parameter is NOT a valid PayPal SDK URL parameter.
        // Intent (capture/authorize) is specified when creating the PayPal order, not when loading the SDK.
        var query = '?client-id=' + encodeURIComponent(config.clientId)
            + '&components=applepay'
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        // Add buyer-country parameter for sandbox mode (required for testing)
        if (isSandbox) {
            query += '&buyer-country=US';
        }

        // Only include merchant-id if it's a valid PayPal merchant ID (alphanumeric, typically 13 chars).
        // Do NOT include language label strings like "Merchant ID:" or placeholder values like "*".
        if (config.merchantId && /^[A-Z0-9]{5,20}$/i.test(config.merchantId)) {
            query += '&merchant-id=' + encodeURIComponent(config.merchantId);
        }

        sharedSdkLoader.promise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js' + query;
            script.dataset.paypalSdk = 'true';
            script.dataset.loaded = 'false';
            script.onload = function () {
                script.dataset.loaded = 'true';
                sharedSdkLoader.key = desiredKey;
                window.paypalrSdkConfig = {
                    clientId: config.clientId,
                    currency: config.currency,
                    merchantId: config.merchantId,
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
    // Native Apple Pay Integration
    // -------------------------------------------------------------------------

    /**
     * Handle the Apple Pay button click event.
     * Creates a PayPal order and initiates the Apple Pay payment flow using ApplePaySession.
     */
    function onApplePayButtonClicked() {
        selectApplePayRadio();

        // Show processing overlay if available
        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
        }

        // Step 1: Create PayPal order
        fetchWalletOrder().then(function (orderConfig) {
            if (!orderConfig || orderConfig.success === false) {
                console.error('Failed to create PayPal order for Apple Pay', orderConfig);
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return;
            }

            sdkState.config = orderConfig;
            var orderId = orderConfig.orderID;

            // Step 2: Get Apple Pay payment request from PayPal
            var applepay = sdkState.applepay;

            if (!applepay) {
                console.error('Apple Pay not properly initialized');
                setApplePayPayload({});
                return;
            }

            // Get the Apple Pay configuration from PayPal
            var applePayConfig = applepay.config();

            // Validate that PayPal returned proper Apple Pay configuration
            if (!applePayConfig || typeof applePayConfig !== 'object') {
                console.error('Apple Pay configuration is invalid or missing');
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return;
            }

            // Create Apple Pay payment request using PayPal config with safe fallbacks
            var paymentRequest = {
                countryCode: applePayConfig.countryCode || 'US',
                currencyCode: orderConfig.currency || applePayConfig.currencyCode || 'USD',
                merchantCapabilities: applePayConfig.merchantCapabilities || ['supports3DS'],
                supportedNetworks: applePayConfig.supportedNetworks || ['visa', 'masterCard', 'amex', 'discover'],
                total: {
                    label: applePayConfig.merchantName || 'Total',
                    amount: orderConfig.amount || '0.00',
                    type: 'final'
                }
            };

            // Step 3: Create ApplePaySession (version 4 is widely supported)
            var session;
            try {
                session = new ApplePaySession(4, paymentRequest);
            } catch (e) {
                console.error('Failed to create ApplePaySession', e);
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return;
            }

            // Step 4: Handle merchant validation
            session.onvalidatemerchant = function (event) {
                applepay.validateMerchant({
                    validationUrl: event.validationURL
                }).then(function (merchantSession) {
                    session.completeMerchantValidation(merchantSession);
                }).catch(function (error) {
                    console.error('Merchant validation failed', error);
                    session.abort();
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                });
            };

            // Step 5: Handle payment authorization
            session.onpaymentauthorized = function (event) {
                // Confirm the order with PayPal using the Apple Pay token
                applepay.confirmOrder({
                    orderId: orderId,
                    token: event.payment.token,
                    billingContact: event.payment.billingContact
                }).then(function (confirmResult) {
                    // Step 6: Handle successful confirmation
                    if (confirmResult.status === PAYPAL_STATUS.APPROVED || confirmResult.status === PAYPAL_STATUS.PAYER_ACTION_REQUIRED) {
                        // Complete the Apple Pay session with success
                        session.completePayment(ApplePaySession.STATUS_SUCCESS);

                        var payload = {
                            orderID: orderId,
                            confirmResult: confirmResult,
                            wallet: 'apple_pay'
                        };
                        setApplePayPayload(payload);
                        document.dispatchEvent(new CustomEvent('paypalr:applepay:payload', { detail: payload }));
                    } else {
                        console.warn('Apple Pay confirmation returned unexpected status', confirmResult);
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        setApplePayPayload({});
                    }
                }).catch(function (error) {
                    console.error('Apple Pay confirmOrder failed', error);
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                });
            };

            // Step 7: Handle cancellation
            session.oncancel = function () {
                console.log('Apple Pay cancelled by user');
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
            };

            // Start the Apple Pay session
            session.begin();

        }).catch(function (error) {
            console.error('Failed to create PayPal order', error);
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
        });
    }

    /**
     * Create a native Apple Pay button element.
     */
    function createApplePayButton() {
        var button = document.createElement('apple-pay-button');
        button.setAttribute('buttonstyle', 'black');
        button.setAttribute('type', 'pay');
        button.setAttribute('locale', 'en-US');
        button.style.cssText = '--apple-pay-button-width: 100%; --apple-pay-button-height: 40px; --apple-pay-button-border-radius: 4px;';
        button.addEventListener('click', onApplePayButtonClicked);
        return button;
    }

    /**
     * Render the native Apple Pay button.
     */
    function renderApplePayButton() {
        var container = document.getElementById('paypalr-applepay-button');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        // Check if Apple Pay is available on this device/browser
        if (typeof window.ApplePaySession === 'undefined' || !ApplePaySession.canMakePayments()) {
            console.log('Apple Pay is not available on this device/browser');
            hidePaymentMethodContainer();
            return;
        }

        // First, fetch only the SDK configuration (no order creation)
        fetchWalletConfig().then(function (config) {
            if (!config || config.success === false) {
                console.warn('Unable to load Apple Pay configuration', config);
                hidePaymentMethodContainer();
                return null;
            }

            sdkState.config = config;

            // Load PayPal SDK with applepay component
            return loadPayPalSdk(config).then(function (paypal) {
                // Verify PayPal Applepay API is available
                if (typeof paypal.Applepay !== 'function') {
                    console.warn('PayPal Applepay API not available');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Initialize PayPal Apple Pay
                var applepay = paypal.Applepay();
                sdkState.applepay = applepay;

                // Check eligibility using PayPal's isEligible method
                if (typeof applepay.isEligible === 'function' && !applepay.isEligible()) {
                    console.log('Apple Pay is not eligible for this user/device');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Get Apple Pay configuration to verify merchant capabilities
                var applePayConfig = applepay.config();

                // Verify the merchant can make payments with Apple Pay
                if (typeof ApplePaySession.canMakePaymentsWithActiveCard === 'function') {
                    var merchantIdentifier = applePayConfig.merchantId || '';
                    if (merchantIdentifier) {
                        return ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier)
                            .then(function (canMakePayments) {
                                if (!canMakePayments) {
                                    console.log('Apple Pay: User cannot make payments with active card');
                                    // Still show button - user might add a card
                                }
                                // Create and render the Apple Pay button
                                var button = createApplePayButton();
                                container.appendChild(button);
                                return button;
                            });
                    }
                }

                // Create and render the Apple Pay button
                var button = createApplePayButton();
                container.appendChild(button);
                return button;
            });
        }).catch(function (error) {
            console.error('Failed to render Apple Pay button', error);
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
            rerenderTimeout = setTimeout(rerenderApplePayButton, 50);
        });

        observer.observe(totalElement, { childList: true, subtree: true, characterData: true });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    window.paypalrApplePaySetPayload = setApplePayPayload;
    window.paypalrApplePaySelectRadio = selectApplePayRadio;

    document.addEventListener('paypalr:applepay:payload', function (event) {
        setApplePayPayload(event.detail || {});
    });

    // Hide the radio button on page load
    hideModuleRadio();
    hideModuleLabel();

    // Add click handler to the button container to select the radio
    var container = document.getElementById('paypalr-applepay-button');
    if (container) {
        container.addEventListener('click', function () {
            selectApplePayRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-applepay-placeholder">' + (typeof paypalrApplePayText !== 'undefined' ? paypalrApplePayText : 'Apple Pay') + '</span>';
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalrApplePayRender = renderApplePayButton;
    }

    renderApplePayButton();

    observeOrderTotal();
})();
