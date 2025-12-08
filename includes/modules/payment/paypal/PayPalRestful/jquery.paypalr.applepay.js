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

    // Apple Pay JS SDK loader state
    var appleSdkLoader = {
        promise: null
    };

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

    /**
     * Extract the order total amount from the page.
     * Defaults to checking #ottotal element, but can be configured via data attribute.
     * @returns {Object} Object with amount (string) and currency (string) properties
     */
    function getOrderTotalFromPage() {
        // Get the element selector from configuration or use default
        var container = document.getElementById('paypalr-applepay-button');
        var totalSelector = container && container.dataset.totalSelector 
            ? container.dataset.totalSelector 
            : 'ottotal';
        
        var totalElement = document.getElementById(totalSelector);
        if (!totalElement) {
            console.warn('Order total element not found: #' + totalSelector);
            return { amount: '0.00', currency: 'USD' };
        }

        // Extract text content and parse the amount
        var totalText = totalElement.textContent || totalElement.innerText || '';
        
        // Remove currency symbols and extract numeric value
        // Handles formats like: $123.45, USD 123.45, 123.45 USD, €123,45, 1,234.56
        var numericMatch = totalText.match(/\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+(?:\.\d{2})?/);
        if (!numericMatch) {
            console.warn('Could not extract numeric amount from: ' + totalText);
            return { amount: '0.00', currency: 'USD' };
        }

        // Remove commas and validate as a number
        var amount = numericMatch[0].replace(/,/g, '');
        var parsedAmount = parseFloat(amount);
        
        if (isNaN(parsedAmount) || parsedAmount < 0) {
            console.warn('Invalid amount extracted: ' + amount);
            return { amount: '0.00', currency: 'USD' };
        }
        
        // Format to 2 decimal places
        amount = parsedAmount.toFixed(2);
        
        // Detect currency from text (defaults to USD)
        var currency = 'USD';
        if (totalText.includes('€') || totalText.toUpperCase().includes('EUR')) {
            currency = 'EUR';
        } else if (totalText.includes('£') || totalText.toUpperCase().includes('GBP')) {
            currency = 'GBP';
        } else if (totalText.toUpperCase().includes('CAD')) {
            currency = 'CAD';
        } else if (totalText.toUpperCase().includes('AUD')) {
            currency = 'AUD';
        }

        return {
            amount: amount,
            currency: currency
        };
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
            moduleRadio.style.display = 'none';
            moduleRadio.setAttribute('aria-hidden', 'true');
            moduleRadio.tabIndex = -1;
        }
    }

    function getApplePayButton() {
        var container = document.getElementById('paypalr-applepay-button');
        if (!container) {
            return null;
        }

        return container.querySelector('apple-pay-button') || container.querySelector('button');
    }

    function triggerApplePayButtonClick() {
        var button = getApplePayButton();

        if (button) {
            button.click();
            return true;
        }

        return false;
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

    /**
     * Load Apple's Apple Pay JS SDK.
     * Required for the <apple-pay-button> WebKit custom element to render properly.
     * This SDK must be loaded before the button element is appended to the DOM.
     *
     * @returns {Promise} Resolves when the SDK is loaded
     */
    function loadApplePayJsSdk() {
        if (appleSdkLoader.promise) {
            return appleSdkLoader.promise;
        }

        appleSdkLoader.promise = new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-apple-pay-sdk="true"]');
            
            // If script exists and has already loaded, resolve immediately
            if (existing) {
                // Check if script has finished loading
                if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                    return resolve();
                }
                
                // Script exists but hasn't loaded yet, wait for it
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function (e) { reject(e); });
                return;
            }

            // Create and load the script
            var script = document.createElement('script');
            script.src = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';
            script.dataset.applePaySdk = 'true';
            
            // Add CSP nonce if available
            var nonce = getCspNonce();
            if (nonce) {
                script.setAttribute('nonce', nonce);
            }
            
            script.onload = function () { resolve(); };
            script.onerror = function (e) { reject(e); };
            document.head.appendChild(script);
        });

        return appleSdkLoader.promise;
    }

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
     * 
     * IMPORTANT: ApplePaySession must be created synchronously within the user gesture handler
     * to avoid "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler."
     * 
     * To balance this requirement with the need to show actual order amounts (not $0.00):
     * 1. We extract the order total from the page (e.g., #ottotal element)
     * 2. We create the ApplePaySession synchronously with this amount
     * 3. We create the PayPal order in onvalidatemerchant callback
     * 4. The order total observer ensures the button is re-rendered when amounts change
     */
    function onApplePayButtonClicked(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        selectApplePayRadio();

        // Show processing overlay if available
        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
        }

        // Get Apple Pay SDK reference
        var applepay = sdkState.applepay;
        if (!applepay) {
            console.error('Apple Pay not properly initialized');
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
            return;
        }

        // Get the Apple Pay configuration from PayPal SDK
        var applePayConfig = applepay.config();
        if (!applePayConfig || typeof applePayConfig !== 'object') {
            console.error('Apple Pay configuration is invalid or missing');
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
            return;
        }

        // Step 1: Get the order total from the page
        // This allows us to show the actual amount in the Apple Pay sheet
        var orderTotal = getOrderTotalFromPage();

        // Step 2: Start fetching the PayPal order (returns immediately with a Promise)
        // The order will be created on the server and validated in onvalidatemerchant
        var orderPromise = fetchWalletOrder();
        var orderId = null;
        var orderConfig = null;

        // Create payment request with the actual order amount from the page
        var paymentRequest = {
            countryCode: applePayConfig.countryCode || 'US',
            currencyCode: orderTotal.currency || applePayConfig.currencyCode || 'USD',
            merchantCapabilities: applePayConfig.merchantCapabilities || ['supports3DS'],
            supportedNetworks: applePayConfig.supportedNetworks || ['visa', 'masterCard', 'amex', 'discover'],
            total: {
                label: applePayConfig.merchantName || 'Total',
                amount: orderTotal.amount,
                type: 'final'
            }
        };

        // Step 3: Create ApplePaySession synchronously in the click handler
        // This MUST happen synchronously to maintain user gesture context
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
        // Wait for order creation here and validate the merchant
        session.onvalidatemerchant = function (event) {
            // Wait for the order to be created before validating merchant
            orderPromise.then(function (config) {
                if (!config || config.success === false) {
                    console.error('Failed to create PayPal order for Apple Pay', config);
                    session.abort();
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return;
                }

                // Validate amount is present and not an empty string
                // Note: We allow '0' or '0.00' as valid amounts (e.g., for free orders with coupons)
                if (config.amount === undefined || config.amount === null || config.amount === '') {
                    console.error('Order created but amount is missing or empty', config);
                    session.abort();
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return;
                }

                // Store order data for use in payment authorization
                orderConfig = config;
                orderId = config.orderID;
                sdkState.config = config;

                // Validate merchant with Apple
                return applepay.validateMerchant({
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
            }).catch(function (error) {
                console.error('Order creation or merchant validation failed', error);
                session.abort();
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
            });
        };

        // Step 5: Handle payment authorization
        session.onpaymentauthorized = function (event) {
            // Verify order was created before attempting confirmation
            if (!orderId) {
                console.error('Payment authorized but orderId is not available');
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return;
            }
            
            // Confirm the order with PayPal using the Apple Pay token
            applepay.confirmOrder({
                orderId: orderId,
                token: event.payment.token,
                billingContact: event.payment.billingContact
            }).then(function (confirmResult) {
                // Handle successful confirmation
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

        // Step 6: Handle cancellation
        session.oncancel = function () {
            console.log('Apple Pay cancelled by user');
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
        };

        // Step 7: Start the Apple Pay session synchronously
        // This must be called synchronously in the click handler
        session.begin();
    }

    /**
     * Create a native Apple Pay button element.
     * Note: Sizing via CSS custom properties (--apple-pay-button-width, etc.) is handled in paypalr.css
     * to ensure proper rendering on iOS Safari. Do not set these as inline styles.
     */
    function createApplePayButton() {
        var button = document.createElement('apple-pay-button');
        button.setAttribute('buttonstyle', 'black');
        button.setAttribute('type', 'pay');
        button.setAttribute('locale', 'en-US');
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
                                // Load Apple Pay JS SDK and then create/render the button
                                return loadApplePayJsSdk().then(function () {
                                    var button = createApplePayButton();
                                    container.appendChild(button);
                                    return button;
                                });
                            });
                    }
                }

                // Load Apple Pay JS SDK and then create/render the button
                return loadApplePayJsSdk().then(function () {
                    var button = createApplePayButton();
                    container.appendChild(button);
                    return button;
                });
            });
        }).catch(function (error) {
            console.error('Failed to render Apple Pay button', error);
            hidePaymentMethodContainer();
        });
    }

    // -------------------------------------------------------------------------
    // Order Total Observer
    // -------------------------------------------------------------------------

    /**
     * Observe the order total element for changes and re-render the Apple Pay button.
     * The element ID can be configured via data-total-selector attribute on the button container.
     * Default: 'ottotal' (standard Zen Cart order total element)
     * 
     * To customize: <div id="paypalr-applepay-button" data-total-selector="custom-total-id"></div>
     */
    function observeOrderTotal() {
        // Get the element selector from configuration or use default
        var container = document.getElementById('paypalr-applepay-button');
        var totalSelector = container && container.dataset.totalSelector 
            ? container.dataset.totalSelector 
            : 'ottotal';
        
        var totalElement = document.getElementById(totalSelector);
        if (!totalElement || typeof MutationObserver === 'undefined') {
            if (!totalElement) {
                console.warn('Apple Pay: Order total element not found: #' + totalSelector);
            }
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

    // If a user still clicks the hidden radio, trigger the Apple Pay button
    var moduleRadio = document.getElementById('pmt-paypalr_applepay');
    if (moduleRadio) {
        moduleRadio.addEventListener('click', function () {
            selectApplePayRadio();
            triggerApplePayButtonClick();
        });
    }

    // Intercept checkout submission when Apple Pay radio is selected
    var checkoutForm = document.querySelector('form[name="checkout_payment"]');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (checkoutSubmitting) {
                return;
            }

            var radio = document.getElementById('pmt-paypalr_applepay');
            var statusField = document.getElementById('paypalr-applepay-status');
            var payloadApproved = statusField && statusField.value === 'approved';

            if (radio && radio.checked && !payloadApproved) {
                selectApplePayRadio();
                if (triggerApplePayButtonClick()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }
        });
    }

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
