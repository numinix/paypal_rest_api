/**
 * PayPal Google Pay Integration - Wallet Button Implementation
 *
 * This module implements Google Pay for cart/product pages with different approaches
 * based on user login status:
 * 
 * LOGGED IN USERS:
 * - Uses PayPal SDK (paypal.Googlepay()) for payment processing
 * - User email comes from session (no need to collect from Google Pay)
 * - Shipping address and options fetched via ajax endpoint
 * 
 * GUEST USERS (NOT LOGGED IN):
 * - Uses native Google Pay SDK directly (google.payments.api.PaymentsClient)
 * - Email address collected from Google Pay
 * - Shipping address and options still fetched via ajax endpoint
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/googlepay/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    // Check if user is logged in - set by template
    var isLoggedIn = window.paypalrWalletIsLoggedIn === true;

    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        googlepay: null,
        paymentsClient: null
    };

    var WALLET_BUTTON_MIN_WIDTH = '200px';
    var WALLET_BUTTON_MAX_WIDTH = '320px';

    var sharedSdkLoader = window.paypalrSdkLoaderState || { key: null, promise: null };
    window.paypalrSdkLoaderState = sharedSdkLoader;

    var googlePayJsLoaded = false;
    var googlePayJsPromise = null;

    // -------------------------------------------------------------------------
    // Button Rendering State (Global across all script executions)
    // -------------------------------------------------------------------------
    
    // Use window object to ensure flags persist across multiple script loads
    // (e.g., when both product and cart templates include this script)
    if (typeof window.paypalrGooglePayState === 'undefined') {
        window.paypalrGooglePayState = {
            renderingInProgress: false,
            buttonRendered: false
        };
    }
    var renderState = window.paypalrGooglePayState;

    // -------------------------------------------------------------------------
    // Utility Functions
    // -------------------------------------------------------------------------

    function normalizeWalletContainer(element) {
        if (!element) {
            return;
        }

        element.style.display = 'flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
        element.style.width = '100%';
        element.style.maxWidth = WALLET_BUTTON_MAX_WIDTH;
        element.style.minWidth = WALLET_BUTTON_MIN_WIDTH;
        element.style.margin = '0';
        element.style.boxSizing = 'border-box';
    }

    function normalizeWalletButton(element) {
        if (!element) {
            return;
        }

        element.style.width = '100%';
        element.style.maxWidth = WALLET_BUTTON_MAX_WIDTH;
        element.style.minWidth = WALLET_BUTTON_MIN_WIDTH;
        element.style.boxSizing = 'border-box';
    }

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
            moduleRadio.style.display = 'none';
            moduleRadio.setAttribute('aria-hidden', 'true');
            moduleRadio.tabIndex = -1;
            return true;
        }

        return false;
    }

    function getGooglePayButton() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            return null;
        }

        return container.querySelector('button');
    }

    function triggerGooglePayButtonClick() {
        var button = getGooglePayButton();

        if (button) {
            button.click();
            return true;
        }

        return false;
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
            moduleLabel.style.display = 'none';
            moduleLabel.setAttribute('aria-hidden', 'true');
            return true;
        }

        return false;
    }

    function ensureWalletSelectionHidden() {
        hideModuleRadio();
        hideModuleLabel();

        if (typeof MutationObserver === 'undefined' || typeof document === 'undefined') {
            return;
        }

        var attempts = 0;
        var observer = new MutationObserver(function () {
            var radioHidden = hideModuleRadio();
            var labelHidden = hideModuleLabel();

            attempts++;

            if ((radioHidden && labelHidden) || attempts >= 20) {
                observer.disconnect();
            }
        });

        observer.observe(document.body || document.documentElement, { childList: true, subtree: true });
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
        console.log('[Google Pay] fetchWalletOrder: Starting order creation request to ppr_wallet.php');
        var startTime = Date.now();
        
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'google_pay' })
        }).then(function(response) {
            var elapsed = Date.now() - startTime;
            console.log('[Google Pay] fetchWalletOrder: Received response after ' + elapsed + 'ms, status:', response.status);
            return parseWalletResponse(response);
        }).then(function(data) {
            var elapsed = Date.now() - startTime;
            console.log('[Google Pay] fetchWalletOrder: Order creation completed after ' + elapsed + 'ms, data:', data);
            return data;
        }).catch(function (error) {
            var elapsed = Date.now() - startTime;
            console.error('[Google Pay] fetchWalletOrder: Unable to create Google Pay order after ' + elapsed + 'ms', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Google Pay order' };
        });
    }

    /**
     * Fetch updated shipping options and totals when address or shipping selection changes.
     * Called by onPaymentDataChanged callback.
     * 
     * @param {Object} shippingAddress - Google Pay shipping address object
     * @param {string} selectedShippingOptionId - Currently selected shipping option ID (optional)
     * @returns {Promise} Promise resolving to updated transaction info and shipping options
     */
    function fetchShippingOptions(shippingAddress, selectedShippingOptionId) {
        console.log('[Google Pay] Fetching shipping options for address - countryCode:', shippingAddress.countryCode, 'postalCode:', shippingAddress.postalCode);
        
        // Normalize the Google Pay address format to match what the server expects
        var normalizedAddress = {
            name: shippingAddress.name || '',
            address1: shippingAddress.address1 || '',
            address2: shippingAddress.address2 || '',
            address3: shippingAddress.address3 || '',
            locality: shippingAddress.locality || '',
            administrativeArea: shippingAddress.administrativeArea || '',
            postalCode: shippingAddress.postalCode || '',
            countryCode: shippingAddress.countryCode || '',
            phoneNumber: shippingAddress.phoneNumber || ''
        };
        
        var requestData = {
            module: 'paypalr_googlepay',
            shippingAddress: normalizedAddress
        };
        
        // Include selected shipping option if provided
        if (selectedShippingOptionId) {
            requestData.selectedShippingOptionId = selectedShippingOptionId;
        }
        
        // Use configurable base path for AJAX endpoint to support subdirectory installations
        var ajaxBasePath = window.paypalrAjaxBasePath || 'ajax/';
        var ajaxUrl = ajaxBasePath + 'paypalr_wallet.php';
        
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        }).then(function(response) {
            console.log('[Google Pay] Shipping options response status:', response.status);
            return parseWalletResponse(response);
        }).then(function(data) {
            console.log('[Google Pay] Shipping options received - shipping method count:', (data.newShippingOptionParameters && data.newShippingOptionParameters.shippingOptions) ? data.newShippingOptionParameters.shippingOptions.length : 0);
            return data;
        }).catch(function (error) {
            console.error('[Google Pay] Failed to fetch shipping options', error);
            throw error;
        });
    }

    /**
     * Update shipping information via AJAX
     * Used for PayPal Buttons component callbacks
     */
    function updateShipping(shippingAddress, shippingOption) {
        console.log('[Google Pay] Updating shipping via AJAX');
        
        var requestData = {
            module: 'paypalr_googlepay'
        };
        
        if (shippingAddress) {
            requestData.shippingAddress = {
                name: shippingAddress.recipient_name || '',
                address1: shippingAddress.line1 || '',
                address2: shippingAddress.line2 || '',
                locality: shippingAddress.city || '',
                administrativeArea: shippingAddress.state || '',
                postalCode: shippingAddress.postal_code || '',
                countryCode: shippingAddress.country_code || ''
            };
        }
        
        if (shippingOption) {
            requestData.selectedShippingOptionId = shippingOption.id;
        }
        
        var ajaxBasePath = window.paypalrAjaxBasePath || 'ajax/';
        var ajaxUrl = ajaxBasePath + 'paypalr_wallet.php';
        
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        }).then(function(response) {
            return parseWalletResponse(response);
        }).then(function(data) {
            console.log('[Google Pay] Shipping update response:', data);
            return data;
        }).catch(function(error) {
            console.error('[Google Pay] Failed to update shipping:', error);
            return { error: true, message: error.message };
        });
    }

    /**
     * Process wallet checkout via AJAX
     * Used for PayPal Buttons component onApprove callback
     */
    function processWalletCheckout(orderID) {
        console.log('[Google Pay] Processing wallet checkout for order:', orderID);
        
        var checkoutPayload = {
            module: 'paypalr_googlepay',
            orderID: orderID
        };
        
        var ajaxBasePath = window.paypalrAjaxBasePath || 'ajax/';
        var checkoutUrl = ajaxBasePath + 'paypalr_wallet_checkout.php';
        
        return fetch(checkoutUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(checkoutPayload)
        }).then(function(response) {
            return response.json();
        }).then(function(result) {
            console.log('[Google Pay] Checkout result:', result);
            return {
                success: result.status === 'success',
                redirectUrl: result.redirect_url,
                message: result.message
            };
        }).catch(function(error) {
            console.error('[Google Pay] Checkout request failed:', error);
            return {
                success: false,
                message: error.message || 'Checkout request failed'
            };
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

            if (matchesClient && matchesCurrency) {
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

        // Note: The google-pay-merchant-id parameter is NO LONGER SUPPORTED by PayPal SDK.
        // As of 2025, PayPal's SDK returns a 400 error if this parameter is included.
        // Google Pay merchant configuration is now handled internally by PayPal.
        // The MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID configuration is preserved
        // for backward compatibility but is not used in the SDK URL.
        // 
        // Historical validation (no longer used for SDK URL):
        // Do NOT include language label strings like "Merchant ID:" or placeholder values like "*".
        // Validation pattern: /^[A-Z0-9]{5,20}$/i.test(config.merchantId)
        // var merchantIdIsValid = /^[A-Z0-9]{5,20}$/i.test(config.merchantId || '');
        // var googleMerchantId = config.googleMerchantId || config.merchantId;
        // The above validation is no longer needed since google-pay-merchant-id is not passed to SDK.

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
     * Handle changes to shipping address or shipping option in the Google Pay modal.
     * This callback is invoked by Google Pay when the user selects/changes their address
     * or shipping method. It must return updated transaction info and shipping options.
     * 
     * @param {Object} intermediatePaymentData - Contains shippingAddress and shippingOptionData
     * @returns {Promise} Promise resolving to updated payment data (newTransactionInfo, newShippingOptionParameters)
     */
    function onPaymentDataChanged(intermediatePaymentData) {
        console.log('[Google Pay] onPaymentDataChanged called - callbackTrigger:', intermediatePaymentData.callbackTrigger);
        
        return new Promise(function(resolve) {
            var shippingAddress = intermediatePaymentData.shippingAddress;
            var shippingOptionData = intermediatePaymentData.shippingOptionData;
            var selectedShippingOptionId = shippingOptionData ? shippingOptionData.id : null;
            
            // If we have a shipping address, fetch updated shipping options and totals
            if (shippingAddress) {
                fetchShippingOptions(shippingAddress, selectedShippingOptionId)
                    .then(function(response) {
                        console.log('[Google Pay] Shipping update completed - totalPrice:', response.newTransactionInfo ? response.newTransactionInfo.totalPrice : 'N/A');
                        
                        // Check for error response
                        if (response.error) {
                            console.error('[Google Pay] Shipping update error:', response.error);
                            resolve({
                                error: {
                                    reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                    message: response.error,
                                    intent: 'SHIPPING_ADDRESS'
                                }
                            });
                            return;
                        }
                        
                        // Return the updated transaction info and shipping options
                        // The ajax endpoint returns the structure Google Pay expects
                        resolve({
                            newTransactionInfo: response.newTransactionInfo,
                            newShippingOptionParameters: response.newShippingOptionParameters
                        });
                    })
                    .catch(function(error) {
                        console.error('[Google Pay] Failed to fetch shipping options:', error);
                        resolve({
                            error: {
                                reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                message: 'Unable to calculate shipping for this address',
                                intent: 'SHIPPING_ADDRESS'
                            }
                        });
                    });
            } else {
                // No shipping address provided yet, return default response
                console.log('[Google Pay] No shipping address provided');
                resolve({});
            }
        });
    }

    /**
     * Handle the Google Pay button click event.
     * Creates a PayPal order and initiates the Google Pay payment flow.
     * 
     * For logged-in users: Uses PayPal SDK only (no Google Pay SDK)
     * For guests: Uses both PayPal SDK and Google Pay SDK with full modal UI
     * 
     * IMPORTANT: For guests, paymentsClient.loadPaymentData() should be called as close to 
     * the user gesture as possible to avoid potential user gesture timeout issues in some browsers.
     * 
     * The order is created BEFORE calling loadPaymentData to get the actual amount, but both 
     * operations happen within the same user gesture handler to minimize delay.
     */
    function onGooglePayButtonClicked() {
        console.log('[Google Pay] Button clicked, starting payment flow');
        console.log('[Google Pay] User logged in:', isLoggedIn);
        
        selectGooglePayRadio();

        // Show processing overlay if available
        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
        }

        // Get Google Pay SDK references
        var googlepay = sdkState.googlepay;
        var paymentsClient = sdkState.paymentsClient;

        // For logged-in users, paymentsClient won't exist (PayPal SDK only)
        // For guests, both googlepay and paymentsClient are required
        if (!googlepay) {
            console.error('[Google Pay] PayPal Googlepay SDK not initialized');
            setGooglePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
            return;
        }

        if (!isLoggedIn && !paymentsClient) {
            console.error('[Google Pay] Guest mode requires Google Pay SDK PaymentsClient, but it is not initialized');
            setGooglePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
            return;
        }

        // Get the payment data request configuration from PayPal
        console.log('[Google Pay] Getting base payment configuration from PayPal SDK');

        Promise.resolve().then(function () {
            return googlepay.config();
        }).then(function (basePaymentDataRequest) {
            var allowedPaymentMethods = getAllowedPaymentMethods(basePaymentDataRequest);

            if (!allowedPaymentMethods) {
                console.error('[Google Pay] Configuration is missing allowedPaymentMethods');
                console.error('[Google Pay] This usually means Google Pay is not enabled in your PayPal account.');
                console.error('[Google Pay] To fix: Go to PayPal Developer Dashboard > Apps & Credentials > Your App > Features > Enable Google Pay');
                console.error('[Google Pay] For live/production mode, you must enable Google Pay in your live PayPal business account.');
                console.error('[Google Pay] Documentation: https://developer.paypal.com/docs/checkout/apm/google-pay/');
                setGooglePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
                return null;
            }

            console.log('[Google Pay] Configuration valid, allowed payment methods:', allowedPaymentMethods.length);

            // Add billing address requirements to allowedPaymentMethods
            // Create a deep copy to avoid mutating the original PayPal config
            var modifiedPaymentMethods = JSON.parse(JSON.stringify(allowedPaymentMethods));
            if (modifiedPaymentMethods && modifiedPaymentMethods[0] && modifiedPaymentMethods[0].parameters) {
                modifiedPaymentMethods[0].parameters.billingAddressRequired = true;
                modifiedPaymentMethods[0].parameters.billingAddressParameters = {
                    format: 'FULL',
                    phoneNumberRequired: true
                };
                console.log('[Google Pay] Added billing address requirements to payment methods');
            } else {
                console.warn('[Google Pay] Unable to add billing address requirements - payment method parameters not found');
            }

            // Step 1: Create PayPal order first to get the actual amount
            // This is done within the user gesture handler to minimize delay
            console.log('[Google Pay] Step 1: Creating PayPal order to get actual amount');
            return fetchWalletOrder().then(function (orderConfig) {
                console.log('[Google Pay] Order creation result:', orderConfig);
                
                if (!orderConfig || orderConfig.success === false) {
                    console.error('[Google Pay] Failed to create PayPal order', orderConfig);
                    setGooglePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return null;
                }

                if (!orderConfig.amount) {
                    console.error('[Google Pay] Order created but amount is missing', orderConfig);
                    setGooglePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return null;
                }

                console.log('[Google Pay] Order validated - ID:', orderConfig.orderID, 'Amount:', orderConfig.amount, 'Currency:', orderConfig.currency);

                sdkState.config = orderConfig;
                var orderId = orderConfig.orderID;

                // For logged-in users: Collect shipping data but not email (email from session)
                if (isLoggedIn) {
                    console.log('[Google Pay] Logged-in user - collecting shipping data for confirmOrder (email from session)');
                    
                    // Build payment data request with shipping but no email
                    var paymentDataRequest = {
                        apiVersion: basePaymentDataRequest.apiVersion || 2,
                        apiVersionMinor: basePaymentDataRequest.apiVersionMinor || 0,
                        allowedPaymentMethods: modifiedPaymentMethods,
                        transactionInfo: {
                            totalPriceStatus: 'FINAL',
                            totalPrice: orderConfig.amount,
                            currencyCode: orderConfig.currency || basePaymentDataRequest.transactionInfo?.currencyCode || 'USD',
                            countryCode: 'US'
                        },
                        merchantInfo: basePaymentDataRequest.merchantInfo || {},
                        emailRequired: false,
                        // Enable shipping address and shipping option selection in the Google Pay modal
                        shippingAddressRequired: true,
                        shippingAddressParameters: {
                            phoneNumberRequired: true
                        },
                        shippingOptionRequired: true,
                        // Provide initial shipping options to avoid Google Pay error
                        // These will be updated via onPaymentDataChanged callback when address changes
                        shippingOptionParameters: {
                            defaultSelectedOptionId: 'shipping_option_unselected',
                            shippingOptions: []
                        },
                        // Register callbacks for address and shipping option changes
                        callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION']
                    };
                    
                    console.log('[Google Pay] Requesting payment data with shipping from Google Pay');
                    
                    // Collect payment data from Google Pay with shipping but no email
                    return paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
                        console.log('[Google Pay] Payment data received for logged-in user');
                        
                        // Call confirmOrder with payment data
                        console.log('[Google Pay] Calling confirmOrder for logged-in user');
                        return googlepay.confirmOrder({
                            orderId: orderId,
                            paymentMethodData: paymentData.paymentMethodData
                        }).then(function (confirmResult) {
                            console.log('[Google Pay] confirmOrder result for logged-in user:', confirmResult);
                            
                            // Extract shipping and billing addresses from Google Pay payment data
                            var shippingAddress = paymentData.shippingAddress || {};
                            var billingAddress = paymentData.paymentMethodData.info.billingAddress || {};
                            var shippingOption = paymentData.shippingOptionData || {};
                            
                            // Build the checkout payload with order ID and shipping data
                            var checkoutPayload = {
                                payment_method_nonce: orderId,
                                module: 'paypalr_googlepay',
                                total: orderConfig.amount,
                                currency: orderConfig.currency || 'USD',
                                orderID: orderId,
                                isLoggedIn: true,
                                shipping_address: shippingAddress,
                                billing_address: billingAddress,
                                shipping_option: shippingOption
                            };
                            
                            console.log('[Google Pay] Sending checkout request to ajax handler for logged-in user');
                            
                            // Send to checkout handler
                            var ajaxBasePath = window.paypalrAjaxBasePath || 'ajax/';
                            var checkoutUrl = ajaxBasePath + 'paypalr_wallet_checkout.php';
                            
                            return fetch(checkoutUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(checkoutPayload)
                            }).then(function(response) {
                                return response.json();
                            }).then(function(checkoutResult) {
                                console.log('[Google Pay] Checkout result for logged-in user:', checkoutResult);
                                
                                if (checkoutResult.status === 'success' && checkoutResult.redirect_url) {
                                    console.log('[Google Pay] Redirecting to:', checkoutResult.redirect_url);
                                    window.location.href = checkoutResult.redirect_url;
                                } else {
                                    console.error('[Google Pay] Checkout failed:', checkoutResult);
                                    setGooglePayPayload({});
                                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                                        window.oprcHideProcessingOverlay();
                                    }
                                    alert('Checkout failed: ' + (checkoutResult.message || 'Unknown error'));
                                }
                            }).catch(function(checkoutError) {
                                console.error('[Google Pay] Checkout request failed for logged-in user:', checkoutError);
                                setGooglePayPayload({});
                                if (typeof window.oprcHideProcessingOverlay === 'function') {
                                    window.oprcHideProcessingOverlay();
                                }
                                alert('Checkout request failed: ' + (checkoutError && checkoutError.message ? checkoutError.message : 'Unknown error'));
                            });
                        }).catch(function(confirmError) {
                            console.error('[Google Pay] confirmOrder failed for logged-in user:', confirmError);
                            setGooglePayPayload({});
                            if (typeof window.oprcHideProcessingOverlay === 'function') {
                                window.oprcHideProcessingOverlay();
                            }
                            alert('Payment confirmation failed: ' + (confirmError && confirmError.message ? confirmError.message : 'An internal server error has occurred'));
                        });
                    });
                }

                // Guest user flow - use Google Pay modal
                console.log('[Google Pay] Guest user - building payment data request for Google Pay modal');

                // Build payment data request with the actual order amount
                var paymentDataRequest = {
                    apiVersion: basePaymentDataRequest.apiVersion || 2,
                    apiVersionMinor: basePaymentDataRequest.apiVersionMinor || 0,
                    allowedPaymentMethods: modifiedPaymentMethods,
                    transactionInfo: {
                        totalPriceStatus: 'FINAL',
                        totalPrice: orderConfig.amount,
                        currencyCode: orderConfig.currency || basePaymentDataRequest.transactionInfo?.currencyCode || 'USD',
                        countryCode: 'US'
                    },
                    merchantInfo: basePaymentDataRequest.merchantInfo || {},
                    // Email collection: Only required for guest users (not logged in)
                    // Logged-in users have email in session on server side
                    emailRequired: !isLoggedIn,
                    // Enable shipping address and shipping option selection in the Google Pay modal
                    shippingAddressRequired: true,
                    shippingAddressParameters: {
                        phoneNumberRequired: true
                        // Note: emailRequired in shippingAddressParameters is NOT used because
                        // we handle email collection at the top level with emailRequired: !isLoggedIn
                    },
                    shippingOptionRequired: true,
                    // Provide initial shipping options to avoid Google Pay error
                    // These will be updated via onPaymentDataChanged callback when address changes
                    shippingOptionParameters: {
                        defaultSelectedOptionId: 'shipping_option_unselected',
                        shippingOptions: []
                    },
                    // Register callbacks for address and shipping option changes
                    // Note: PAYMENT_AUTHORIZATION is not included because PayPal SDK uses confirmOrder() API
                    callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION']
                };

                console.log('[Google Pay] Step 2: Requesting payment data from Google Pay, total:', paymentDataRequest.transactionInfo.totalPrice);

                // Step 2: Invoke Google Pay payment sheet with actual amount
                // This is called in the .then() callback but remains within user gesture context
                return paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
                    console.log('[Google Pay] Payment data received from Google Pay sheet');
                    console.log('[Google Pay] Payment method data:', paymentData.paymentMethodData);
                    
                    // Step 3: Confirm the order using PayPal's client-side API
                    // Similar to Apple Pay, Google Pay now uses client-side confirmation via confirmOrder()
                    // This is the recommended flow per PayPal's Advanced Integration documentation
                    console.log('[Google Pay] Calling paypal.Googlepay().confirmOrder...', orderId);
                    
                    return googlepay.confirmOrder({
                        orderId: orderId,
                        paymentMethodData: paymentData.paymentMethodData
                    }).then(function (confirmResult) {
                        console.log('[Google Pay] confirmOrder result:', confirmResult);
                        
                        // Extract shipping and billing addresses from Google Pay payment data
                        var shippingAddress = paymentData.shippingAddress || {};
                        var billingAddress = paymentData.paymentMethodData.info.billingAddress || {};
                        // Email extraction: paymentData.email is typically empty with PayPal SDK since
                        // we don't use emailRequired (incompatible with confirmOrder() flow).
                        // For logged-in users, email comes from session on the server side.
                        var email = paymentData.email || billingAddress.emailAddress || '';
                        
                        // Build the complete payload for checkout
                        var checkoutPayload = {
                            payment_method_nonce: orderId, // Use orderID as the payment reference
                            module: 'paypalr_googlepay',
                            total: orderConfig.amount,
                            currency: orderConfig.currency || 'USD',
                            email: email,
                            shipping_address: shippingAddress,
                            billing_address: billingAddress,
                            orderID: orderId
                        };
                        
                        console.log('[Google Pay] Sending checkout request to ajax handler');
                        
                        // Send to checkout handler instead of just submitting form
                        var ajaxBasePath = window.paypalrAjaxBasePath || 'ajax/';
                        var checkoutUrl = ajaxBasePath + 'paypalr_wallet_checkout.php';
                        
                        return fetch(checkoutUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(checkoutPayload)
                        }).then(function(response) {
                            return response.json();
                        }).then(function(checkoutResult) {
                            console.log('[Google Pay] Checkout result:', checkoutResult);
                            
                            if (checkoutResult.status === 'success' && checkoutResult.redirect_url) {
                                console.log('[Google Pay] Redirecting to:', checkoutResult.redirect_url);
                                window.location.href = checkoutResult.redirect_url;
                            } else {
                                console.error('[Google Pay] Checkout failed:', checkoutResult);
                                setGooglePayPayload({});
                                if (typeof window.oprcHideProcessingOverlay === 'function') {
                                    window.oprcHideProcessingOverlay();
                                }
                                alert('Checkout failed: ' + (checkoutResult.message || 'Unknown error'));
                            }
                        }).catch(function(checkoutError) {
                            console.error('[Google Pay] Checkout request failed:', checkoutError);
                            setGooglePayPayload({});
                            if (typeof window.oprcHideProcessingOverlay === 'function') {
                                window.oprcHideProcessingOverlay();
                            }
                            alert('Checkout failed. Please try again.');
                        });
                    }).catch(function (confirmError) {
                        console.error('[Google Pay] confirmOrder failed:', confirmError);
                        setGooglePayPayload({});
                        if (typeof window.oprcHideProcessingOverlay === 'function') {
                            window.oprcHideProcessingOverlay();
                        }
                        throw confirmError;
                    });
                });
            });
        }).catch(function (error) {
            console.log('[Google Pay] Payment flow completed or error occurred');
            
            // Handle specific error types
            if (error && error.statusCode === 'CANCELED') {
                console.log('[Google Pay] Payment cancelled by user');
            } else {
                console.error('[Google Pay] Payment error occurred', error);
            }
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
        console.log('[Google Pay] Starting button rendering');
        
        // Prevent concurrent or duplicate rendering using global flags
        if (renderState.renderingInProgress || renderState.buttonRendered) {
            console.log('[Google Pay] Button already rendered or rendering in progress, skipping');
            return;
        }
        renderState.renderingInProgress = true;
        
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            console.warn('[Google Pay] Button container not found');
            renderState.renderState.renderingInProgress = false;
            return;
        }

        normalizeWalletContainer(container);
        container.innerHTML = '';

        // First, fetch only the SDK configuration (no order creation)
        console.log('[Google Pay] Fetching wallet configuration');
        fetchWalletConfig().then(function (config) {
            console.log('[Google Pay] Configuration loaded:', config);
            
            if (!config || config.success === false) {
                console.warn('[Google Pay] Unable to load configuration', config);
                renderState.renderingInProgress = false;
                hidePaymentMethodContainer();
                return null;
            }

            var isSandbox = config.environment === 'sandbox';
            var merchantIdIsValid = /^[A-Z0-9]{5,20}$/i.test(config.merchantId || '');
            var googleMerchantId = config.googleMerchantId || config.merchantId;
            var hasMerchantId = merchantIdIsValid || (googleMerchantId && /^[A-Z0-9]{5,20}$/i.test(googleMerchantId));

            console.log('[Google Pay] Environment:', config.environment, 'Sandbox:', isSandbox, 'Has merchant ID:', hasMerchantId);
            console.log('[Google Pay] User logged in:', isLoggedIn, 'Guest wallet enabled:', config.enableGuestWallet);
            // Note: Guest wallet availability check is now handled at PHP template level
            // If this script is loaded, the button should be shown

            sdkState.config = config;

            // For logged-in users: Use PayPal SDK only (no Google Pay SDK needed)
            // This approach allows Google Pay without merchant verification
            if (isLoggedIn) {
                console.log('[Google Pay] Logged-in user detected - using PayPal SDK only (no Google Pay SDK)');
                // Simply show the button container - we'll use standard PayPal buttons
                // which include Google Pay as a funding option
                renderState.buttonRendered = true;
                renderState.renderingInProgress = false;
                
                // For logged-in users, we should hide the Google Pay button
                // and rely on PayPal/Apple Pay buttons in the standard button template
                hidePaymentMethodContainer();
                return null;
            }

            // Guest user path - requires Google Pay SDK and merchant verification
            console.log('[Google Pay] Guest user detected - using both PayPal SDK and Google Pay SDK');
            // Load both PayPal SDK and Google Pay JS in parallel
            console.log('[Google Pay] Loading PayPal SDK and Google Pay JS library');
            return Promise.all([
                loadPayPalSdk(config),
                loadGooglePayJs()
            ]).then(function (results) {
                var paypal = results[0];
                console.log('[Google Pay] SDKs loaded successfully');

                // Verify PayPal Googlepay API is available
                if (typeof paypal.Googlepay !== 'function') {
                    console.warn('[Google Pay] PayPal Googlepay API not available');
                    renderState.renderingInProgress = false;
                    hidePaymentMethodContainer();
                    return null;
                }

                // Initialize PayPal Google Pay
                // Note: As of 2025, Google Pay merchant configuration is handled internally by PayPal
                // and should NOT be passed to the Googlepay() constructor or SDK URL
                console.log('[Google Pay] Initializing PayPal Googlepay API');
                var googlepay = paypal.Googlepay();
                sdkState.googlepay = googlepay;

                // Check eligibility using PayPal's isEligible method
                if (typeof googlepay.isEligible === 'function' && !googlepay.isEligible()) {
                    console.log('[Google Pay] Not eligible for this user/device');
                    renderState.renderingInProgress = false;
                    hidePaymentMethodContainer();
                    return null;
                }

                console.log('[Google Pay] Eligibility check passed');

                // Create Google Payments Client
                // Use environment from config (stored in sdkState) to determine Google Pay environment
                var googlePayEnvironment = (sdkState.config && sdkState.config.environment === 'sandbox') ? 'TEST' : 'PRODUCTION';
                console.log('[Google Pay] Creating PaymentsClient with environment:', googlePayEnvironment);
                var paymentsClient = new google.payments.api.PaymentsClient({
                    environment: googlePayEnvironment,
                    paymentDataCallbacks: {
                        onPaymentDataChanged: onPaymentDataChanged
                    }
                });
                sdkState.paymentsClient = paymentsClient;

                // Get base configuration from PayPal for isReadyToPay check
                return Promise.resolve().then(function () {
                    return googlepay.config();
                }).then(function (baseConfig) {
                    var allowedPaymentMethods = getAllowedPaymentMethods(baseConfig);

                    if (!allowedPaymentMethods) {
                        console.error('[Google Pay] Configuration is missing allowedPaymentMethods');
                        console.error('[Google Pay] This usually means Google Pay is not enabled in your PayPal account.');
                        console.error('[Google Pay] To fix: Go to PayPal Developer Dashboard > Apps & Credentials > Your App > Features > Enable Google Pay');
                        console.error('[Google Pay] For live/production mode, you must enable Google Pay in your live PayPal business account.');
                        console.error('[Google Pay] Documentation: https://developer.paypal.com/docs/checkout/apm/google-pay/');
                        renderState.renderingInProgress = false;
                        hidePaymentMethodContainer();
                        return null;
                    }

                    console.log('[Google Pay] Checking if ready to pay with', allowedPaymentMethods.length, 'payment methods');

                    // Check if user is ready to pay with Google Pay
                    var isReadyToPayRequest = {
                        apiVersion: baseConfig.apiVersion || 2,
                        apiVersionMinor: baseConfig.apiVersionMinor || 0,
                        allowedPaymentMethods: allowedPaymentMethods
                    };

                    return paymentsClient.isReadyToPay(isReadyToPayRequest)
                        .then(function (response) {
                            console.log('[Google Pay] isReadyToPay response:', response);
                            
                            if (!response.result) {
                                console.log('[Google Pay] Not ready to pay on this device');
                                renderState.renderingInProgress = false;
                                hidePaymentMethodContainer();
                                return null;
                            }

                            console.log('[Google Pay] Device is ready to pay, creating button');

                            // Create and render the Google Pay button
                            var button = paymentsClient.createButton({
                                onClick: onGooglePayButtonClicked,
                                buttonColor: 'black',
                                buttonType: 'pay',
                                buttonRadius: 4,
                                buttonSizeMode: 'fill'
                            });

                            normalizeWalletButton(button);
                            container.appendChild(button);
                            renderState.buttonRendered = true;
                            renderState.renderingInProgress = false;
                            console.log('[Google Pay] Button rendered successfully');

                            return button;
                        });
                });
            });
        }).catch(function (error) {
            console.error('[Google Pay] Failed to render button', error);
            renderState.renderingInProgress = false;
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
    ensureWalletSelectionHidden();

    var moduleRadio = document.getElementById('pmt-paypalr_googlepay');
    if (moduleRadio) {
        moduleRadio.addEventListener('click', function () {
            selectGooglePayRadio();
            triggerGooglePayButtonClick();
        });
    }

    var checkoutForm = document.querySelector('form[name="checkout_payment"]');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (checkoutSubmitting) {
                return;
            }

            var radio = document.getElementById('pmt-paypalr_googlepay');
            var statusField = document.getElementById('paypalr-googlepay-status');
            var payloadApproved = statusField && statusField.value === 'approved';

            if (radio && radio.checked && !payloadApproved) {
                selectGooglePayRadio();
                if (triggerGooglePayButtonClick()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }
        });
    }

    // Add click handler to the button container to select the radio
    var container = document.getElementById('paypalr-googlepay-button');
    if (container) {
        normalizeWalletContainer(container);
        container.addEventListener('click', function () {
            selectGooglePayRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-googlepay-placeholder">' + (typeof paypalrGooglePayText !== 'undefined' ? paypalrGooglePayText : 'Google Pay') + '</span>';
            normalizeWalletButton(container.firstElementChild);
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalrGooglePayRender = renderGooglePayButton;
    }

    renderGooglePayButton();

    observeOrderTotal();
})();
