(function () {
    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        loader: null,
    };

    var WALLET_BUTTON_MIN_WIDTH = '200px';
    var WALLET_BUTTON_MAX_WIDTH = '320px';

    var sharedSdkLoader = window.paypalrSdkLoaderState || { key: null, promise: null };
    window.paypalrSdkLoaderState = sharedSdkLoader;

    /**
     * Get CSP nonce from existing script tags if available.
     * This helps comply with Content Security Policy when loading external scripts.
     */
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

    function submitCheckoutForm()
    {
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

    function cacheVenmoPayload(payload) {
        try {
            return fetch('ppr_wallet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ wallet: 'venmo', payload: payload })
            }).then(parseWalletResponse).catch(function (error) {
                console.warn('Unable to cache Venmo payload', error);
                return { success: false };
            });
        } catch (error) {
            console.warn('Unable to cache Venmo payload', error);
            return Promise.resolve({ success: false });
        }
    }

    function setVenmoPayload(payload) {
        var payloadField = document.getElementById('paypalr-venmo-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Venmo payload', error);
                payloadField.value = '';
            }
        }

        var payloadPresent = hasPayloadData(payload);
        var statusField = document.getElementById('paypalr-venmo-status');
        if (statusField) {
            statusField.value = payloadPresent ? 'approved' : '';
        }

        if (payloadPresent && !checkoutSubmitting) {
            selectVenmoRadio();
            submitCheckoutForm();
        }

        if (!payloadPresent) {
            checkoutSubmitting = false;
        }
    }

    function selectVenmoRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_venmo');
        if (moduleRadio && moduleRadio.type === 'radio' && !moduleRadio.checked) {
            moduleRadio.checked = true;
            if (typeof jQuery !== 'undefined') {
                jQuery(moduleRadio).trigger('change');
            } else {
                moduleRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    function hideModuleRadio() {
        var moduleRadio = document.getElementById('pmt-paypalr_venmo');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalr-wallet-radio-hidden');
        }
    }

    /**
     * Hide the entire payment method container when payment is not eligible.
     * This hides the parent element (e.g., paypalr_venmo-custom-control-container)
     * so the user doesn't see an unavailable payment option.
     */
    function hidePaymentMethodContainer() {
        var container = document.getElementById('paypalr-venmo-button');
        if (!container) {
            return;
        }

        // Find the parent container that wraps this payment method
        // Common patterns: closest .moduleRow, closest payment container div, or parent with class containing 'container'
        var parentContainer = container.closest('[id*="paypalr_venmo"][id*="container"]') 
            || container.closest('.moduleRow')
            || container.closest('[class*="paypalr_venmo"]');

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

            if (parentId.indexOf('paypalr_venmo') !== -1 || 
                parentClass.indexOf('paypalr_venmo') !== -1 ||
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

    function rerenderVenmoButton() {
        if (typeof window.paypalrVenmoRender === 'function') {
            window.paypalrVenmoRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalr:venmo:rerender'));
        }
    }

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
            body: JSON.stringify({ wallet: 'venmo', config_only: true })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to load Venmo configuration', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to load Venmo configuration' };
        });
    }

    /**
     * Create a PayPal order for Venmo.
     * Called when user clicks the Venmo button.
     */
    function fetchWalletOrder() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'venmo' })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to create Venmo order', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Venmo order' };
        });
    }

    function buildSdkKey(config) {
        var currency = config.currency || 'USD';
        var merchantId = config.merchantId || '';
        var environment = config.environment || 'sandbox';
        return [config.clientId, currency, merchantId, environment].join('|');
    }

    function loadPayPalSdk(config) {
        if (!config || !config.clientId) {
            return Promise.reject(new Error('Missing clientId for PayPal SDK load'));
        }

        var desiredKey = buildSdkKey(config);
        var existingScript = document.querySelector('script[data-paypal-sdk="true"]');
        var isSandbox = config.environment === 'sandbox';

        if (sharedSdkLoader.promise && sharedSdkLoader.key === desiredKey && window.paypal && typeof window.paypal.Buttons === 'function') {
            return sharedSdkLoader.promise.then(function () { return window.paypal; });
        }

        if (existingScript) {
            var matchesClient = existingScript.src.indexOf(encodeURIComponent(config.clientId)) !== -1;
            var matchesCurrency = existingScript.src.indexOf('currency=' + encodeURIComponent(config.currency || 'USD')) !== -1;
            var matchesMerchant = !config.merchantId || existingScript.src.indexOf('merchant-id=' + encodeURIComponent(config.merchantId)) !== -1;

            if (matchesClient && matchesCurrency && matchesMerchant) {
                if (existingScript.dataset.loaded === 'true' && window.paypal && typeof window.paypal.Buttons === 'function') {
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

        var query = '?client-id=' + encodeURIComponent(config.clientId)
            + '&components=buttons,googlepay,applepay,venmo'
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        // Add buyer-country parameter to ensure Venmo eligibility detection works correctly
        // Venmo is US-only, so we always specify US as the buyer country
        query += '&buyer-country=US';

        // Only include merchant-id if it's a valid PayPal merchant ID (alphanumeric, typically 13 chars).
        // Do NOT include language label strings like "Merchant ID:" or placeholder values like "*".
        if (config.merchantId && /^[A-Z0-9]{5,20}$/i.test(config.merchantId)) {
            query += '&merchant-id=' + encodeURIComponent(config.merchantId);
        }

        sharedSdkLoader.promise = new Promise(function(resolve, reject) {
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

    function renderVenmoButton() {
        var container = document.getElementById('paypalr-venmo-button');
        if (!container) {
            return;
        }

        normalizeWalletContainer(container);
        container.innerHTML = '';

        // First, fetch only the SDK configuration (no order creation)
        fetchWalletConfig().then(function (config) {
            if (!config || config.success === false) {
                console.warn('Unable to load Venmo configuration', config);
                hidePaymentMethodContainer();
                return null;
            }

            sdkState.config = config;
            return loadPayPalSdk(config).then(function (paypal) {
                // Create the button instance to check eligibility
                var buttonInstance = paypal.Buttons({
                    fundingSource: paypal.FUNDING.VENMO,
                    style: {
                        shape: 'rect',
                        height: 40
                    },
                    // createOrder is called when user clicks the button - this is when we create the PayPal order
                    createOrder: function () {
                        return fetchWalletOrder().then(function (orderConfig) {
                            if (orderConfig && orderConfig.success !== false) {
                                sdkState.config = orderConfig;
                                return orderConfig.orderID;
                            }
                            throw new Error('Unable to create Venmo order');
                        });
                    },
                    onClick: function () {
                        selectVenmoRadio();
                    },
                    onApprove: function (data) {
                        var payload = {
                            orderID: data.orderID,
                            payerID: data.payerID,
                            paymentID: data.paymentID,
                            facilitatorAccessToken: data.facilitatorAccessToken,
                            wallet: 'venmo'
                        };
                        return cacheVenmoPayload(payload).finally(function () {
                            setVenmoPayload(payload);
                            document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: payload }));
                        });
                    },
                    onCancel: function (data) {
                        console.warn('Venmo cancelled', data);
                        setVenmoPayload({});
                        document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: {} }));
                    },
                    onError: function (error) {
                        console.error('Venmo encountered an error', error);
                        setVenmoPayload({});
                        document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: {} }));
                    }
                });

                // Check if Venmo is eligible for this user/device
                if (typeof buttonInstance.isEligible === 'function' && !buttonInstance.isEligible()) {
                    console.log('Venmo is not eligible for this user/device');
                    hidePaymentMethodContainer();
                    return null;
                }

                return buttonInstance.render('#paypalr-venmo-button');
            });
        }).catch(function (error) {
            console.error('Failed to render Venmo button', error);
            hidePaymentMethodContainer();
        });
    }

    function observeOrderTotal() {
        var totalElement = document.getElementById('ottotal');
        if (!totalElement || typeof MutationObserver === 'undefined') {
            return;
        }

        var rerenderTimeout = null;

        var observer = new MutationObserver(function(mutations) {
            var hasRelevantChange = mutations.some(function(mutation) {
                return mutation.type === 'characterData' || mutation.type === 'childList';
            });

            if (!hasRelevantChange) {
                return;
            }

            clearTimeout(rerenderTimeout);
            rerenderTimeout = setTimeout(rerenderVenmoButton, 50);
        });

        observer.observe(totalElement, { childList: true, subtree: true, characterData: true });
    }

    window.paypalrVenmoSetPayload = setVenmoPayload;
    window.paypalrVenmoSelectRadio = selectVenmoRadio;

    document.addEventListener('paypalr:venmo:payload', function (event) {
        setVenmoPayload(event.detail || {});
    });

    hideModuleRadio();

    var container = document.getElementById('paypalr-venmo-button');
    if (container) {
        normalizeWalletContainer(container);
        container.addEventListener('click', function() {
            selectVenmoRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-venmo-placeholder">' + (typeof paypalrVenmoText !== 'undefined' ? paypalrVenmoText : 'Venmo') + '</span>';
            normalizeWalletButton(container.firstElementChild);
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalrVenmoRender = renderVenmoButton;
    }

    renderVenmoButton();
    observeOrderTotal();
})();
