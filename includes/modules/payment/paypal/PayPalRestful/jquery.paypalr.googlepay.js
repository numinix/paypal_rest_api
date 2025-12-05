(function () {
    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        loader: null,
    };

    var sharedSdkLoader = window.paypalrSdkLoaderState || { key: null, promise: null };
    window.paypalrSdkLoaderState = sharedSdkLoader;

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

    function buildSdkKey(config) {
        var currency = config.currency || 'USD';
        var merchantId = config.merchantId || '';
        // Note: intent is not included in SDK key because it's not passed to the SDK URL
        return [config.clientId, currency, merchantId].join('|');
    }

    function loadPayPalSdk(config) {
        if (!config || !config.clientId) {
            return Promise.reject(new Error('Missing clientId for PayPal SDK load'));
        }

        var desiredKey = buildSdkKey(config);
        var existingScript = document.querySelector('script[data-paypal-sdk="true"]');

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
            + '&components=buttons,googlepay,applepay'
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        // Note: The 'intent' parameter is NOT a valid PayPal SDK URL parameter.
        // Intent (capture/authorize) is specified when creating the PayPal order, not when loading the SDK.

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
            script.onload = function () {
                script.dataset.loaded = 'true';
                sharedSdkLoader.key = desiredKey;
                window.paypalrSdkConfig = {
                    clientId: config.clientId,
                    currency: config.currency,
                    merchantId: config.merchantId
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

            // Google Pay requires a merchant ID to be configured
            if (!config.merchantId || !/^[A-Z0-9]{5,20}$/i.test(config.merchantId)) {
                console.warn('Google Pay: Invalid or missing Google Merchant ID');
                hidePaymentMethodContainer();
                return null;
            }

            sdkState.config = config;
            return loadPayPalSdk(config).then(function (paypal) {
                // Verify GOOGLEPAY funding source is available
                if (!paypal.FUNDING || !paypal.FUNDING.GOOGLEPAY) {
                    console.warn('Google Pay funding source not available in PayPal SDK');
                    hidePaymentMethodContainer();
                    return null;
                }

                // Create the button instance to check eligibility
                var buttonInstance = paypal.Buttons({
                    fundingSource: paypal.FUNDING.GOOGLEPAY,
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
                            throw new Error('Unable to create Google Pay order');
                        });
                    },
                    onClick: function () {
                        selectGooglePayRadio();
                    },
                    onApprove: function (data) {
                        var payload = {
                            orderID: data.orderID,
                            payerID: data.payerID,
                            paymentID: data.paymentID,
                            facilitatorAccessToken: data.facilitatorAccessToken,
                            wallet: 'google_pay'
                        };
                        setGooglePayPayload(payload);
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: payload }));
                    },
                    onCancel: function (data) {
                        console.warn('Google Pay cancelled', data);
                        setGooglePayPayload({});
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: {} }));
                    },
                    onError: function (error) {
                        console.error('Google Pay encountered an error', error);
                        setGooglePayPayload({});
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: {} }));
                    }
                });

                // Check if Google Pay is eligible for this user/device
                if (typeof buttonInstance.isEligible === 'function' && !buttonInstance.isEligible()) {
                    console.log('Google Pay is not eligible for this user/device');
                    hidePaymentMethodContainer();
                    return null;
                }

                return buttonInstance.render('#paypalr-googlepay-button');
            });
        }).catch(function (error) {
            console.error('Failed to render Google Pay button', error);
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
            rerenderTimeout = setTimeout(rerenderGooglePayButton, 50);
        });

        observer.observe(totalElement, { childList: true, subtree: true, characterData: true });
    }

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
        container.addEventListener('click', function() {
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
