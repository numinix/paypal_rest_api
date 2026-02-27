/**
 * PayPal Apple Pay Integration - Wallet Button Implementation
 *
 * This module implements Apple Pay for cart/product pages with different approaches
 * based on user login status:
 * 
 * LOGGED IN USERS:
 * - Uses PayPal SDK (paypal.Applepay()) for payment processing
 * - User email comes from session (no need to collect from Apple Pay)
 * - Shipping address and options fetched via ajax endpoint
 * 
 * GUEST USERS (NOT LOGGED IN):
 * - Uses native Apple Pay SDK directly (ApplePaySession)
 * - Email address collected from Apple Pay
 * - Shipping address and options still fetched via ajax endpoint
 *
 * Reference: https://developer.paypal.com/docs/checkout/advanced/applepay/
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    // Check if user is logged in - set by template
    var isLoggedIn = window.paypalacWalletIsLoggedIn === true;

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

    var sharedSdkLoader = window.paypalacSdkLoaderState || { key: null, promise: null };
    window.paypalacSdkLoaderState = sharedSdkLoader;

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
        // On product pages, the config provides the product price directly.
        // Use it as the initial total since the #ottotal element (cart total)
        // won't include the currently viewed product.
        var appleConfig = window.paypalacApplePayConfig;
        if (appleConfig && appleConfig.initialTotal) {
            var configAmount = parseFloat(appleConfig.initialTotal);
            if (!isNaN(configAmount) && configAmount > 0) {
                return {
                    amount: configAmount.toFixed(2),
                    currency: appleConfig.currencyCode || 'USD'
                };
            }
        }

        // Get the element selector from configuration or use default
        var container = document.getElementById('paypalac-applepay-button');
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

    /**
     * Collect the product form data (quantity and attributes) from the current
     * page.  Used by fetchWalletOrder and fetchShippingOptions on product pages
     * so the server can set up the Buy Now cart.
     * @param {Object} requestData - The request object to augment in place.
     */
    function collectProductFormData(requestData) {
        var qtyInput = document.querySelector('input[name="cart_quantity"]');
        requestData.cart_quantity = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
        var cartForm = document.querySelector('form[name="cart_quantity"]');
        if (cartForm) {
            var attribs = {};
            var selects = cartForm.querySelectorAll('select[name^="id["]');
            for (var i = 0; i < selects.length; i++) {
                attribs[selects[i].name] = selects[i].value;
            }
            var radios = cartForm.querySelectorAll('input[type="radio"][name^="id["]:checked');
            for (var j = 0; j < radios.length; j++) {
                attribs[radios[j].name] = radios[j].value;
            }
            var checkboxes = cartForm.querySelectorAll('input[type="checkbox"][name^="id["]:checked');
            for (var k = 0; k < checkboxes.length; k++) {
                attribs[checkboxes[k].name] = checkboxes[k].value;
            }
            var texts = cartForm.querySelectorAll('input[type="text"][name^="id["], textarea[name^="id["]');
            for (var m = 0; m < texts.length; m++) {
                if (texts[m].value) {
                    attribs[texts[m].name] = texts[m].value;
                }
            }
            if (Object.keys(attribs).length > 0) {
                requestData.attributes = attribs;
            }
        }
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
        var payloadField = document.getElementById('paypalac-applepay-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Apple Pay payload', error);
                payloadField.value = '';
            }
        }

        var payloadPresent = hasPayloadData(payload);
        var statusField = document.getElementById('paypalac-applepay-status');
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
        var moduleRadio = document.getElementById('pmt-paypalac_applepay');
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
        var moduleRadio = document.getElementById('pmt-paypalac_applepay');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalac-wallet-radio-hidden');
            moduleRadio.style.display = 'none';
            moduleRadio.setAttribute('aria-hidden', 'true');
            moduleRadio.tabIndex = -1;
            return true;
        }

        return false;
    }

    function getApplePayButton() {
        var container = document.getElementById('paypalac-applepay-button');
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
     * This hides the parent element (e.g., paypalac_applepay-custom-control-container)
     * so the user doesn't see an unavailable payment option.
     */
    function hidePaymentMethodContainer() {
        var container = document.getElementById('paypalac-applepay-button');
        if (!container) {
            return;
        }

        // Find the parent container that wraps this payment method
        // Common patterns: closest .moduleRow, closest payment container div, or parent with class containing 'container'
        var parentContainer = container.closest('[id*="paypalac_applepay"][id*="container"]')
            || container.closest('.moduleRow')
            || container.closest('[class*="paypalac_applepay"]');

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

            if (parentId.indexOf('paypalac_applepay') !== -1 ||
                parentClass.indexOf('paypalac_applepay') !== -1 ||
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
        var moduleLabel = document.querySelector('label[for="pmt-paypalac_applepay"]');
        if (moduleLabel) {
            moduleLabel.classList.add('paypalac-wallet-label-hidden');
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

    function rerenderApplePayButton() {
        if (typeof window.paypalacApplePayRender === 'function') {
            window.paypalacApplePayRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalac:applepay:rerender'));
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
     * Includes products_id when on a product page so the server can check
     * whether the viewed product is virtual (shipping not required).
     */
    function fetchWalletConfig() {
        var requestData = { wallet: 'apple_pay', config_only: true };
        // Detect product page context from the Apple Pay config or from URL
        var productId = (window.paypalacApplePayConfig && window.paypalacApplePayConfig.productId)
            ? window.paypalacApplePayConfig.productId
            : 0;
        if (!productId) {
            var match = window.location.search.match(/[?&]products_id=(\d+)/);
            if (match) {
                productId = parseInt(match[1], 10);
            }
        }
        if (productId) {
            requestData.products_id = productId;
        }
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
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
        console.log('[Apple Pay] fetchWalletOrder: Starting order creation request to ppac_wallet.php');
        var startTime = Date.now();

        var orderData = { wallet: 'apple_pay' };

        // On product pages, send product info so the server can set up the
        // "Buy Now" cart before creating the PayPal order.
        var appleConfig = window.paypalacApplePayConfig;
        if (appleConfig && appleConfig.productId) {
            orderData.products_id = appleConfig.productId;
            collectProductFormData(orderData);
        }

        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        }).then(function(response) {
            var elapsed = Date.now() - startTime;
            console.log('[Apple Pay] fetchWalletOrder: Received response after ' + elapsed + 'ms, status:', response.status);
            return parseWalletResponse(response);
        }).then(function(data) {
            var elapsed = Date.now() - startTime;
            console.log('[Apple Pay] fetchWalletOrder: Order creation completed after ' + elapsed + 'ms, data:', data);
            return data;
        }).catch(function (error) {
            var elapsed = Date.now() - startTime;
            console.error('[Apple Pay] fetchWalletOrder: Unable to create Apple Pay order after ' + elapsed + 'ms', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Apple Pay order' };
        });
    }

    /**
     * Fetch updated shipping options and totals when address or shipping selection changes.
     * Called by Apple Pay shipping contact selection and shipping method selection handlers.
     * 
     * @param {Object} shippingContact - Apple Pay shipping contact object
     * @param {string} selectedShippingMethodId - Currently selected shipping method identifier (optional)
     * @returns {Promise} Promise resolving to updated shipping methods and totals
     */
    function fetchShippingOptions(shippingContact, selectedShippingMethodId) {
        console.log('[Apple Pay] Fetching shipping options for address - countryCode:', shippingContact.countryCode, 'postalCode:', shippingContact.postalCode);
        
        // Normalize the Apple Pay contact format to match what the server expects
        var normalizedAddress = {
            name: ((shippingContact.givenName || '') + ' ' + (shippingContact.familyName || '')).trim(),
            address1: (shippingContact.addressLines && shippingContact.addressLines[0]) || '',
            address2: (shippingContact.addressLines && shippingContact.addressLines[1]) || '',
            locality: shippingContact.locality || '',
            administrativeArea: shippingContact.administrativeArea || '',
            postalCode: shippingContact.postalCode || '',
            countryCode: shippingContact.countryCode || '',
            phoneNumber: shippingContact.phoneNumber || ''
        };
        
        var requestData = {
            module: 'paypalac_applepay',
            shippingAddress: normalizedAddress
        };
        
        // Include selected shipping option if provided
        if (selectedShippingMethodId) {
            requestData.selectedShippingOptionId = selectedShippingMethodId;
        }

        // On product pages, send product info so the server can set up the
        // "Buy Now" cart (reset + add just this product) before quoting shipping.
        var appleConfig = window.paypalacApplePayConfig;
        if (appleConfig && appleConfig.productId) {
            requestData.products_id = appleConfig.productId;
            if (appleConfig.initialTotal) {
                var productPrice = parseFloat(appleConfig.initialTotal);
                if (!isNaN(productPrice) && productPrice > 0) {
                    requestData.productPrice = productPrice.toFixed(2);
                }
            }
            collectProductFormData(requestData);
        }
        
        // Use configurable base path for AJAX endpoint to support subdirectory installations
        var ajaxBasePath = window.paypalacAjaxBasePath || 'ajax/';
        var ajaxUrl = ajaxBasePath + 'paypalac_wallet.php';
        
        return fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        }).then(function(response) {
            console.log('[Apple Pay] Shipping options response status:', response.status);
            return parseWalletResponse(response);
        }).then(function(data) {
            console.log('[Apple Pay] Shipping options received - shipping method count:', (data.newShippingMethods) ? data.newShippingMethods.length : 0);
            return data;
        }).catch(function (error) {
            console.error('[Apple Pay] Failed to fetch shipping options', error);
            throw error;
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

        // Detect PayPal SDK already loaded by another template (e.g. the synchronous <script> tag)
        // even when sharedSdkLoader hasn't been set. Avoids duplicate SDK loading which can
        // cause the second load to silently fail and leave the promise chain unresolved.
        if (!existingScript && window.paypal && typeof window.paypal.Applepay === 'function') {
            sharedSdkLoader.key = desiredKey;
            sharedSdkLoader.promise = Promise.resolve(window.paypal);
            return sharedSdkLoader.promise;
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
                window.paypalacSdkConfig = {
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
     * Creates an Apple Pay session and initiates the payment flow using ApplePaySession.
     * 
     * IMPORTANT: ApplePaySession must be created synchronously within the user gesture handler
     * to avoid "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler."
     * 
     * Flow:
     * 1. Extract order total from the page (e.g., #ottotal element) synchronously
     * 2. Create ApplePaySession synchronously with this amount
     * 3. Call session.begin() synchronously
     * 4. In onvalidatemerchant: Validate merchant IMMEDIATELY (don't create order yet)
     * 5. In onpaymentauthorized: Create PayPal order, then confirm payment with PayPal
     * 
     * This approach:
     * - Maintains user gesture context (session created synchronously)
     * - Shows actual amount to user (extracted from page, not $0.00 placeholder)
     * - Prevents merchant validation timeout (validates immediately without waiting)
     * - Only creates PayPal orders when user authorizes payment (not on cancel)
     */
    function onApplePayButtonClicked(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        var sessionAbortReason = null;

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
        console.log('[Apple Pay] Order total from page:', orderTotal);

        // Step 2: Variables to store order data (will be created in onvalidatemerchant)
        var orderId = null;
        var orderConfig = null;
        var orderPromise = null;

        // Store the last shipping contact so onshippingmethodselected can reuse it
        var lastShippingContact = null;

        // Create payment request with the actual order amount from the page
        // Request contact fields based on user login status and cart content
        var cartRequiresShipping = !!(sdkState.config && sdkState.config.cartRequiresShipping);
        var billingFields = ['postalAddress', 'name'];
        
        // Only request email from Apple Pay if user is NOT logged in
        // Logged-in users have email in session on server side
        if (!isLoggedIn) {
            billingFields.push('email');
        }
        
        var paymentRequest = {
            countryCode: applePayConfig.countryCode || 'US',
            currencyCode: orderTotal.currency || applePayConfig.currencyCode || 'USD',
            merchantCapabilities: applePayConfig.merchantCapabilities || ['supports3DS'],
            supportedNetworks: applePayConfig.supportedNetworks || ['visa', 'masterCard', 'amex', 'discover'],
            total: {
                label: applePayConfig.merchantName || 'Total',
                amount: orderTotal.amount,
                type: 'final'
            },
            // Request billing contact fields - email only for guest users
            requiredBillingContactFields: billingFields
        };

        // Only request shipping contact fields when the cart contains physical items
        // For virtual-only carts, no shipping address or shipping options are needed
        if (cartRequiresShipping) {
            var shippingFields = ['postalAddress', 'name', 'phone'];
            if (!isLoggedIn) {
                shippingFields.push('email');
            }
            paymentRequest.requiredShippingContactFields = shippingFields;
        }

        // Step 3: Create ApplePaySession synchronously in the click handler
        // This MUST happen synchronously to maintain user gesture context
        var session;
        try {
            console.log('[Apple Pay] Creating ApplePaySession with payment request:', paymentRequest);
            session = new ApplePaySession(4, paymentRequest);
            console.log('[Apple Pay] ApplePaySession created successfully');
        } catch (e) {
            console.error('[Apple Pay] Failed to create ApplePaySession', e);
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
            return;
        }

        // Step 4: Handle merchant validation
        // IMPORTANT: Validate merchant IMMEDIATELY, don't wait for order creation
        // Order creation should ONLY happen in onpaymentauthorized (when user authorizes payment)
        session.onvalidatemerchant = function (event) {
            console.log('[Apple Pay] onvalidatemerchant called, validationURL:', event.validationURL);
            
            // DO NOT create order here - only validate merchant
            // Order creation happens in onpaymentauthorized when user actually authorizes payment
            console.log('[Apple Pay] Calling validateMerchant (order creation will happen in onpaymentauthorized)...');
            applepay.validateMerchant({
                validationUrl: event.validationURL
            }).then(function (merchantValidationResponse) {
                var merchantSession = merchantValidationResponse && merchantValidationResponse.merchantSession
                    ? merchantValidationResponse.merchantSession
                    : merchantValidationResponse;

                if (!merchantSession) {
                    sessionAbortReason = 'Merchant validation returned empty session';
                    console.error('[Apple Pay] Merchant validation succeeded but no session returned');
                    session.abort();
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return;
                }

                console.log('[Apple Pay] validateMerchant succeeded, completing merchant validation');
                session.completeMerchantValidation(merchantSession);
            }).catch(function (error) {
                console.error('[Apple Pay] Merchant validation failed', error);
                sessionAbortReason = 'Merchant validation failed';
                session.abort();
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
            });
        };

        // Only register shipping handlers when the cart requires shipping.
        // For virtual-only carts, no shipping address or options are collected.
        if (cartRequiresShipping) {
            // Handle shipping contact selection (address changes)
            session.onshippingcontactselected = function (event) {
                console.log('[Apple Pay] onshippingcontactselected called');

                // Save the shipping contact so onshippingmethodselected can reuse it
                lastShippingContact = event.shippingContact;
                
                fetchShippingOptions(event.shippingContact, null)
                    .then(function(response) {
                        console.log('[Apple Pay] Shipping contact update completed - total:', response.newTotal ? response.newTotal.amount : 'N/A');
                        
                        // Check for error response
                        if (response.error) {
                            console.error('[Apple Pay] Shipping contact update error:', response.error);
                            session.completeShippingContactSelection({
                                newShippingMethods: [],
                                newTotal: response.newTotal || { label: 'Total', amount: '0.00' },
                                newLineItems: response.newLineItems || [],
                                errors: [new ApplePayError('shippingContactInvalid')]
                            });
                            return;
                        }
                        
                        // Return the updated shipping methods and totals
                        session.completeShippingContactSelection({
                            newShippingMethods: response.newShippingMethods || [],
                            newTotal: response.newTotal || { label: 'Total', amount: '0.00' },
                            newLineItems: response.newLineItems || []
                        });
                    })
                    .catch(function(error) {
                        console.error('[Apple Pay] Failed to fetch shipping options:', error);
                        session.completeShippingContactSelection({
                            newShippingMethods: [],
                            newTotal: { label: 'Total', amount: '0.00' },
                            newLineItems: [],
                            errors: [new ApplePayError('shippingContactInvalid')]
                        });
                    });
            };

            // Handle shipping method selection (shipping option changes)
            session.onshippingmethodselected = function (event) {
                console.log('[Apple Pay] onshippingmethodselected called - method:', event.shippingMethod.identifier);
                
                // Reuse the shipping contact from the last onshippingcontactselected event
                // Apple Pay does not provide the contact in onshippingmethodselected
                if (!lastShippingContact) {
                    console.warn('[Apple Pay] No saved shipping contact for method selection - this should not happen');
                    session.completeShippingMethodSelection({
                        newTotal: { label: 'Total', amount: orderTotal.amount },
                        newLineItems: []
                    });
                    return;
                }

                fetchShippingOptions(lastShippingContact, event.shippingMethod.identifier)
                    .then(function(response) {
                        console.log('[Apple Pay] Shipping method update completed - total:', response.newTotal ? response.newTotal.amount : 'N/A');
                        
                        // Return the updated totals
                        session.completeShippingMethodSelection({
                            newTotal: response.newTotal || { label: 'Total', amount: '0.00' },
                            newLineItems: response.newLineItems || []
                        });
                    })
                    .catch(function(error) {
                        console.error('[Apple Pay] Failed to update totals for shipping method:', error);
                        session.completeShippingMethodSelection({
                            newTotal: { label: 'Total', amount: '0.00' },
                            newLineItems: []
                        });
                    });
            };
        }

        // Step 5: Handle payment authorization
        // Create order only when user authorizes payment
        session.onpaymentauthorized = function (event) {
            console.log('[Apple Pay] onpaymentauthorized called - creating order now that user has authorized payment');
            
            // Create the order now that user has authorized payment
            console.log('[Apple Pay] Creating PayPal order...');
            orderPromise = fetchWalletOrder();
            
            // Wait for order to be created
            orderPromise.then(function (config) {
                console.log('[Apple Pay] Order creation completed, config:', config);
                
                if (!config || config.success === false || !config.orderID) {
                    console.error('[Apple Pay] Failed to create PayPal order for Apple Pay', config);
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return;
                }

                // Store order data
                orderConfig = config;
                orderId = config.orderID;
                sdkState.config = config;
                
                // Check if PayPal Applepay.confirmOrder is available
                if (!sdkState.applepay || typeof sdkState.applepay.confirmOrder !== 'function') {
                    console.error('[Apple Pay] PayPal Applepay.confirmOrder not available');
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                    return;
                }

                console.log('[Apple Pay] Calling paypal.Applepay().confirmOrder...', orderId);

                // Confirm the order using PayPal's client-side API
                // This handles the Apple Pay token and device context properly
                return sdkState.applepay.confirmOrder({
                    orderId: orderId,
                    token: event.payment.token,
                    billingContact: event.payment.billingContact || null,
                    shippingContact: event.payment.shippingContact || null
                }).then(function (confirmResult) {
                    console.log('[Apple Pay] confirmOrder result:', confirmResult);

                    // Only now do we tell Apple Pay the payment succeeded
                    session.completePayment(ApplePaySession.STATUS_SUCCESS);

                    // Extract shipping and billing addresses from Apple Pay payment data
                    var shippingContact = event.payment.shippingContact || {};
                    var billingContact = event.payment.billingContact || {};
                    
                    // Build the complete payload for checkout
                    var checkoutPayload = {
                        payment_method_nonce: orderId, // Use orderID as the payment reference
                        module: 'paypalac_applepay',
                        total: config.amount,
                        currency: config.currency || 'USD',
                        email: shippingContact.emailAddress || billingContact.emailAddress || '',
                        shipping_address: {
                            name: ((shippingContact.givenName || '') + ' ' + (shippingContact.familyName || '')).trim(),
                            address1: (shippingContact.addressLines && shippingContact.addressLines[0]) || '',
                            address2: (shippingContact.addressLines && shippingContact.addressLines[1]) || '',
                            locality: shippingContact.locality || '',
                            administrativeArea: shippingContact.administrativeArea || '',
                            postalCode: shippingContact.postalCode || '',
                            countryCode: shippingContact.countryCode || '',
                            phoneNumber: shippingContact.phoneNumber || ''
                        },
                        billing_address: {
                            name: ((billingContact.givenName || '') + ' ' + (billingContact.familyName || '')).trim(),
                            address1: (billingContact.addressLines && billingContact.addressLines[0]) || '',
                            address2: (billingContact.addressLines && billingContact.addressLines[1]) || '',
                            locality: billingContact.locality || '',
                            administrativeArea: billingContact.administrativeArea || '',
                            postalCode: billingContact.postalCode || '',
                            countryCode: billingContact.countryCode || '',
                            phoneNumber: billingContact.phoneNumber || ''
                        },
                        orderID: orderId,
                        paypal_order_id: orderId
                    };
                    
                    console.log('[Apple Pay] Sending checkout request to ajax handler');
                    
                    // Send to checkout handler instead of just submitting form
                    var ajaxBasePath = window.paypalacAjaxBasePath || 'ajax/';
                    var checkoutUrl = ajaxBasePath + 'paypalac_wallet_checkout.php';
                    
                    return fetch(checkoutUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(checkoutPayload)
                    }).then(function(response) {
                        return response.json();
                    }).then(function(checkoutResult) {
                        console.log('[Apple Pay] Checkout result:', checkoutResult);
                        
                        if (checkoutResult.status === 'success' && checkoutResult.redirect_url) {
                            console.log('[Apple Pay] Redirecting to:', checkoutResult.redirect_url);
                            window.location.href = checkoutResult.redirect_url;
                        } else {
                            console.error('[Apple Pay] Checkout failed:', checkoutResult);
                            setApplePayPayload({});
                            if (typeof window.oprcHideProcessingOverlay === 'function') {
                                window.oprcHideProcessingOverlay();
                            }
                            alert('Checkout failed: ' + (checkoutResult.message || 'Unknown error'));
                        }
                    }).catch(function(checkoutError) {
                        console.error('[Apple Pay] Checkout request failed:', checkoutError);
                        setApplePayPayload({});
                        if (typeof window.oprcHideProcessingOverlay === 'function') {
                            window.oprcHideProcessingOverlay();
                        }
                        alert('Checkout failed. Please try again.');
                    });
                }).catch(function (err) {
                    console.error('[Apple Pay] confirmOrder failed:', err);
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    setApplePayPayload({});
                    if (typeof window.oprcHideProcessingOverlay === 'function') {
                        window.oprcHideProcessingOverlay();
                    }
                });
            }).catch(function (error) {
                console.error('[Apple Pay] Order creation failed', error);
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                setApplePayPayload({});
                if (typeof window.oprcHideProcessingOverlay === 'function') {
                    window.oprcHideProcessingOverlay();
                }
            });
        };

        // Step 6: Handle cancellation
        session.oncancel = function (event) {
            console.log('[Apple Pay] oncancel called, event:', event);
            if (sessionAbortReason) {
                console.error('[Apple Pay] Session aborted before completion:', sessionAbortReason);
            } else {
                console.log('[Apple Pay] Session cancelled - this may be due to user cancellation OR merchant validation timeout');
                console.log('[Apple Pay] To diagnose: Check if onvalidatemerchant completed successfully above');
            }
            setApplePayPayload({});
            if (typeof window.oprcHideProcessingOverlay === 'function') {
                window.oprcHideProcessingOverlay();
            }
        };

        // Step 7: Start the Apple Pay session synchronously
        // This must be called synchronously in the click handler
        console.log('[Apple Pay] Calling session.begin()...');
        session.begin();
        console.log('[Apple Pay] session.begin() called - waiting for onvalidatemerchant callback');
    }

    /**
     * Create a native Apple Pay button element.
     * Note: Sizing via CSS custom properties (--apple-pay-button-width, etc.) is handled in paypalac.css
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
        var container = document.getElementById('paypalac-applepay-button');
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
     * To customize: <div id="paypalac-applepay-button" data-total-selector="custom-total-id"></div>
     */
    function observeOrderTotal() {
        // Get the element selector from configuration or use default
        var container = document.getElementById('paypalac-applepay-button');
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

    window.paypalacApplePaySetPayload = setApplePayPayload;
    window.paypalacApplePaySelectRadio = selectApplePayRadio;

    document.addEventListener('paypalac:applepay:payload', function (event) {
        setApplePayPayload(event.detail || {});
    });

    // Hide the radio button on page load
    ensureWalletSelectionHidden();

    // If a user still clicks the hidden radio, select the payment method
    // but do NOT launch the modal - only the button click or form submit should do that
    var moduleRadio = document.getElementById('pmt-paypalac_applepay');
    if (moduleRadio) {
        moduleRadio.addEventListener('click', function () {
            selectApplePayRadio();
        });
    }

    // Intercept checkout submission when Apple Pay radio is selected
    var checkoutForm = document.querySelector('form[name="checkout_payment"]');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (checkoutSubmitting) {
                return;
            }

            var radio = document.getElementById('pmt-paypalac_applepay');
            var statusField = document.getElementById('paypalac-applepay-status');
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
    var container = document.getElementById('paypalac-applepay-button');
    if (container) {
        container.addEventListener('click', function () {
            selectApplePayRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalac-applepay-placeholder">' + (typeof paypalacApplePayText !== 'undefined' ? paypalacApplePayText : 'Apple Pay') + '</span>';
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalacApplePayRender = renderApplePayButton;
    }

    renderApplePayButton();

    observeOrderTotal();
})();
