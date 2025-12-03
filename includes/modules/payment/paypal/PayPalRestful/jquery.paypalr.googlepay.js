(function () {
    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        loader: null,
    };

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

    function fetchWalletOrder() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'google_pay' })
        }).then(function(response) {
            return response.json();
        });
    }

    function loadPayPalSdk(config) {
        var lastConfig = window.paypalrSdkConfig || {};
        var needsReload = !config || !config.clientId;
        needsReload = needsReload || (lastConfig.clientId !== config.clientId)
            || (lastConfig.currency !== config.currency)
            || (lastConfig.merchantId !== config.merchantId);

        if (!needsReload && window.paypal && typeof window.paypal.Buttons === 'function') {
            return Promise.resolve(window.paypal);
        }

        if (sdkState.loader && !needsReload) {
            return sdkState.loader;
        }

        if (needsReload) {
            sdkState.loader = null;
            var existing = document.querySelector('script[data-paypal-sdk="true"]');
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }
        }

        var query = '?client-id=' + encodeURIComponent(config.clientId)
            + '&components=buttons,googlepay,applepay,venmo'
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        if (config.intent) {
            query += '&intent=' + encodeURIComponent(config.intent);
        }

        if (config.merchantId) {
            query += '&merchant-id=' + encodeURIComponent(config.merchantId);
        }

        sdkState.loader = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js' + query;
            script.dataset.paypalSdk = 'true';
            script.onload = function () {
                window.paypalrSdkConfig = {
                    clientId: config.clientId,
                    currency: config.currency,
                    merchantId: config.merchantId
                };
                resolve(window.paypal);
            };
            script.onerror = function (event) {
                reject(event);
            };
            document.head.appendChild(script);
        });

        return sdkState.loader;
    }

    function renderGooglePayButton() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        fetchWalletOrder().then(function (config) {
            if (!config || config.success === false) {
                console.warn('Unable to load Google Pay configuration', config);
                return;
            }

            sdkState.config = config;
            return loadPayPalSdk(config).then(function (paypal) {
                return paypal.Buttons({
                    fundingSource: paypal.FUNDING.GOOGLEPAY,
                    style: {
                        shape: 'rect',
                        height: 40
                    },
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
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: payload }));
                    },
                    onCancel: function (data) {
                        console.warn('Google Pay cancelled', data);
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: {} }));
                    },
                    onError: function (error) {
                        console.error('Google Pay encountered an error', error);
                        document.dispatchEvent(new CustomEvent('paypalr:googlepay:payload', { detail: {} }));
                    }
                }).render('#paypalr-googlepay-button');
            });
        }).catch(function (error) {
            console.error('Failed to render Google Pay button', error);
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
