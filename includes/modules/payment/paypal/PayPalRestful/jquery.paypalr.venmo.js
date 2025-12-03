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

    function rerenderVenmoButton() {
        if (typeof window.paypalrVenmoRender === 'function') {
            window.paypalrVenmoRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalr:venmo:rerender'));
        }
    }

    function fetchWalletOrder() {
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'venmo' })
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

    function renderVenmoButton() {
        var container = document.getElementById('paypalr-venmo-button');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        fetchWalletOrder().then(function (config) {
            if (!config || config.success === false) {
                console.warn('Unable to load Venmo configuration', config);
                return;
            }

            sdkState.config = config;
            return loadPayPalSdk(config).then(function (paypal) {
                return paypal.Buttons({
                    fundingSource: paypal.FUNDING.VENMO,
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
                        document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: payload }));
                    },
                    onCancel: function (data) {
                        console.warn('Venmo cancelled', data);
                        document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: {} }));
                    },
                    onError: function (error) {
                        console.error('Venmo encountered an error', error);
                        document.dispatchEvent(new CustomEvent('paypalr:venmo:payload', { detail: {} }));
                    }
                }).render('#paypalr-venmo-button');
            });
        }).catch(function (error) {
            console.error('Failed to render Venmo button', error);
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
        container.addEventListener('click', function() {
            selectVenmoRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalr-venmo-placeholder">' + (typeof paypalrVenmoText !== 'undefined' ? paypalrVenmoText : 'Venmo') + '</span>';
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalrVenmoRender = renderVenmoButton;
    }

    renderVenmoButton();
    observeOrderTotal();
})();
