(function () {
    if (window.__paypalacPaylaterRuntime && window.__paypalacPaylaterRuntime.initialized) {
        if (typeof window.paypalacPaylaterRender === 'function') {
            window.paypalacPaylaterRender();
        }
        return;
    }

    window.__paypalacPaylaterRuntime = window.__paypalacPaylaterRuntime || {};
    window.__paypalacPaylaterRuntime.initialized = true;

    var checkoutSubmitting = false;
    var sdkState = {
        config: null,
        loader: null,
    };
    var renderRequestId = 0;

    var WALLET_BUTTON_MIN_WIDTH = '200px';
    var WALLET_BUTTON_MAX_WIDTH = '320px';
    // Fixed target width matching --paypalac-wallet-button-width in paypalac.css.
    // Setting an explicit inline width (rather than 100%) keeps this button the
    // same size as Apple Pay/Google Pay/Venmo even before/without the CSS file
    // loading, since Apple Pay never sets an inline width at all.
    var WALLET_BUTTON_WIDTH = '240px';

    var sharedSdkLoader = window.paypalacPaylaterSdkLoaderState || { key: null, promise: null };
    window.paypalacPaylaterSdkLoaderState = sharedSdkLoader;

    function getPayPalNamespace() {
        var candidates = [window.paypalacPaylater, window.paypal, window.PayPalSDK];
        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i] && typeof candidates[i].Buttons === 'function') {
                return candidates[i];
            }
        }

        return window.paypalacPaylater || window.paypal || window.PayPalSDK;
    }

    function normalizeWalletContainer(element) {
        if (!element) {
            return;
        }

        element.style.display = 'flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
        element.style.width = WALLET_BUTTON_WIDTH;
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

    function parseAmountValue(value) {
        if (value === null || typeof value === 'undefined') {
            return null;
        }

        if (typeof value === 'number' && isFinite(value)) {
            return value;
        }

        var normalized = String(value).replace(/[^0-9.,-]/g, '').replace(/,/g, '');
        if (normalized === '' || normalized === '-' || normalized === '.') {
            return null;
        }

        var parsed = parseFloat(normalized);
        return isFinite(parsed) ? parsed : null;
    }

    function getOrderTotalFromPage() {
        var totalElement = document.getElementById('ottotal');
        if (!totalElement) {
            return null;
        }

        return parseAmountValue(totalElement.textContent || totalElement.innerText || '');
    }

    function configHasKnownOrderTotal(config) {
        return config && config.orderTotal !== null && typeof config.orderTotal !== 'undefined' && config.orderTotal !== '';
    }

    function getEffectiveOrderTotal(config) {
        if (configHasKnownOrderTotal(config)) {
            return parseAmountValue(config.orderTotal);
        }

        return getOrderTotalFromPage();
    }

    function isWithinPayLaterLimits(total, config) {
        if (total === null || !config) {
            return true;
        }

        var minAmount = parseAmountValue(config.minAmount);
        var maxAmount = parseAmountValue(config.maxAmount);

        if (minAmount !== null && total < minAmount) {
            return false;
        }

        if (maxAmount !== null && maxAmount > 0 && total > maxAmount) {
            return false;
        }

        return true;
    }

    function shouldHidePayLaterForConfig(config) {
        if (!config) {
            return true;
        }

        if (config.success === false) {
            return true;
        }

        if (config.withinLimits === false) {
            return true;
        }

        var orderTotal = getEffectiveOrderTotal(config);
        if (orderTotal !== null && !isWithinPayLaterLimits(orderTotal, config)) {
            return true;
        }

        return false;
    }

    function logPayLaterLimitState(config, source) {
        if (!config) {
            return;
        }

        var orderTotal = getEffectiveOrderTotal(config);
        console.log(
            'Pay Later limit check (' + (source || 'unknown') + '):',
            {
                minAmount: config.minAmount,
                maxAmount: config.maxAmount,
                orderTotal: orderTotal,
                withinLimits: config.withinLimits,
                shouldHide: shouldHidePayLaterForConfig(config)
            }
        );
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
        showCheckoutProcessingOverlay();

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

    function cachePaylaterPayload(payload) {
        try {
            return fetch('ppac_wallet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ wallet: 'paylater', payload: payload })
            }).then(parseWalletResponse).catch(function (error) {
                console.warn('Unable to cache Pay Later payload', error);
                return { success: false };
            });
        } catch (error) {
            console.warn('Unable to cache Pay Later payload', error);
            return Promise.resolve({ success: false });
        }
    }

    function setPaylaterPayload(payload) {
        var payloadField = document.getElementById('paypalac-paylater-payload');
        if (payloadField) {
            try {
                payloadField.value = JSON.stringify(payload || {});
            } catch (error) {
                console.error('Unable to serialise Pay Later payload', error);
                payloadField.value = '';
            }
        }

        var payloadPresent = hasPayloadData(payload);
        var statusField = document.getElementById('paypalac-paylater-status');
        if (statusField) {
            statusField.value = payloadPresent ? 'approved' : '';
        }

        if (payloadPresent && !checkoutSubmitting) {
            selectPaylaterRadio();
            submitCheckoutForm();
        }

        if (!payloadPresent) {
            checkoutSubmitting = false;
        }
    }

    function selectPaylaterRadio() {
        var moduleRadio = document.getElementById('pmt-paypalac_paylater');
        if (moduleRadio && moduleRadio.type === 'radio') {
            if (!moduleRadio.checked) {
                moduleRadio.checked = true;
            }
            if (typeof jQuery !== 'undefined') {
                jQuery(moduleRadio).trigger('change');
            } else {
                moduleRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    function isPaylaterSelected() {
        var moduleRadio = document.getElementById('pmt-paypalac_paylater');
        return !!(moduleRadio && moduleRadio.checked);
    }

    function isPaylaterApproved() {
        var statusField = document.getElementById('paypalac-paylater-status');
        return !!(statusField && statusField.value === 'approved');
    }

    function clearPaylaterApprovedStatus() {
        var statusField = document.getElementById('paypalac-paylater-status');
        if (statusField) {
            statusField.value = '';
        }
    }

    function findPaylaterPaymentRow() {
        var moduleRadio = document.getElementById('pmt-paypalac_paylater');
        var container = document.getElementById('paypalac-paylater-button');
        var fromRadio = moduleRadio
            ? (moduleRadio.closest('.moduleRow')
                || moduleRadio.closest('[id*="paypalac_paylater"]')
                || moduleRadio.closest('.custom-control')
                || moduleRadio.parentElement)
            : null;
        var fromButton = container
            ? (container.closest('.moduleRow')
                || container.closest('[id*="paypalac_paylater"]')
                || container.closest('.custom-control')
                || container.parentElement)
            : null;
        return fromRadio || fromButton;
    }

    function shouldStartPayLaterApproval() {
        // Confirm Order must start Pay Later approval whenever the Pay Later
        // radio is selected and the Buttons popup has not already approved.
        return isPaylaterSelected() && !isPaylaterApproved() && !checkoutSubmitting;
    }

    function getPaylaterButtonElement() {
        var container = document.getElementById('paypalac-paylater-button');
        if (!container) {
            return null;
        }

        return container.querySelector('.paypal-button')
            || container.querySelector('[data-funding-source="paylater"]')
            || container.querySelector('[role="link"]')
            || container.querySelector('iframe');
    }

    function serializeCheckoutPostsForPayLater() {
        var form = document.querySelector('form[name="checkout_payment"]');
        if (!form || typeof FormData === 'undefined' || typeof URLSearchParams === 'undefined') {
            return 'payment=paypalac_paylater&ppac_type=paylater';
        }

        var params = new URLSearchParams();
        var formData = new FormData(form);
        formData.forEach(function (value, key) {
            if (key === 'request') {
                return;
            }
            params.append(key, value == null ? '' : String(value));
        });
        params.set('payment', 'paypalac_paylater');
        params.set('ppac_type', 'paylater');
        return params.toString();
    }

    function fetchPayLaterConfirmRedirect() {
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet: 'paylater',
                confirm_redirect: true,
                // Resume OPC after PayPal return; JSON requests leave $_POST empty.
                checkout_posts: serializeCheckoutPostsForPayLater()
            })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to start Pay Later Confirm Order redirect', error);
            return {
                success: false,
                message: error && error.message ? error.message : 'Unable to start Pay Later'
            };
        });
    }

    function showCheckoutProcessingOverlay() {
        // Prefer OPRC's overlay helper when present (newer plugin builds).
        if (typeof window.oprcShowProcessingOverlay === 'function') {
            window.oprcShowProcessingOverlay();
            return;
        }

        // Redline / older OPRC themes expose blockPage() with the branded message.
        if (typeof window.blockPage === 'function') {
            window.blockPage(false, false);
            return;
        }

        if (typeof jQuery === 'undefined' || typeof jQuery.blockUI !== 'function') {
            return;
        }

        var message = (typeof oprcProcessingText !== 'undefined' && oprcProcessingText)
            ? oprcProcessingText
            : 'Please wait…';
        var blockOptions = { message: message };

        if (typeof oprcMessageBackground !== 'undefined') {
            blockOptions.css = {
                border: 'none',
                padding: '15px',
                backgroundColor: oprcMessageBackground,
                '-webkit-border-radius': '10px',
                '-moz-border-radius': '10px',
                opacity: (typeof oprcMessageOpacity !== 'undefined') ? oprcMessageOpacity : 0.8,
                color: (typeof oprcMessageTextColor !== 'undefined') ? oprcMessageTextColor : '#fff'
            };
        }

        if (typeof oprcMessageOverlayColor !== 'undefined') {
            blockOptions.overlayCSS = {
                backgroundColor: oprcMessageOverlayColor,
                color: (typeof oprcMessageOverlayTextColor !== 'undefined') ? oprcMessageOverlayTextColor : '#000',
                opacity: (typeof oprcMessageOverlayOpacity !== 'undefined') ? oprcMessageOverlayOpacity : 0.6
            };
        }

        jQuery.blockUI(blockOptions);
    }

    function startPayLaterConfirmOrderRedirect() {
        selectPaylaterRadio();
        clearPaylaterApprovedStatus();
        releaseCheckoutOverlay();
        showCheckoutProcessingOverlay();

        return fetchPayLaterConfirmRedirect().then(function (result) {
            if (result && result.success && result.approveUrl) {
                window.location.href = result.approveUrl;
                return true;
            }

            console.error('Pay Later Confirm Order redirect failed', result);
            releaseCheckoutOverlay();
            var message = (result && result.message)
                ? result.message
                : 'Unable to start Pay Later. Please click the Pay Later button, or try again.';
            window.alert(message);
            return false;
        });
    }

    function wrapSubmitCheckout() {
        if (typeof window.submitCheckout !== 'function' || window.submitCheckout.__paypalacPaylaterWrapped) {
            return;
        }

        var originalSubmitCheckout = window.submitCheckout;
        function wrappedSubmitCheckout() {
            if (shouldStartPayLaterApproval()) {
                startPayLaterConfirmOrderRedirect();
                return false;
            }

            return originalSubmitCheckout.apply(this, arguments);
        }
        wrappedSubmitCheckout.__paypalacPaylaterWrapped = true;
        window.submitCheckout = wrappedSubmitCheckout;
    }

    function releaseCheckoutOverlay() {
        if (typeof window.oprcHideProcessingOverlay === 'function') {
            window.oprcHideProcessingOverlay();
        }
        if (typeof jQuery !== 'undefined' && typeof jQuery.unblockUI === 'function') {
            jQuery.unblockUI();
        }
    }

    function interceptPaylaterCheckoutSubmit(event) {
        if (!shouldStartPayLaterApproval()) {
            return;
        }

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        } else if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        startPayLaterConfirmOrderRedirect();
    }

    function interceptConfirmOrderClick(event) {
        wrapSubmitCheckout();

        var submitRoot = document.getElementById('js-submit');
        if (!submitRoot || !event.target || !submitRoot.contains(event.target)) {
            return;
        }

        // Selecting the Pay Later radio can lag behind a theme custom-radio
        // paint; if the click is Confirm Order and Pay Later is the intended
        // method (radio checked OR its row was the last payment interaction),
        // force-select and redirect.
        if (!isPaylaterSelected() && window.__paypalacPaylaterRowInteracted) {
            selectPaylaterRadio();
        }

        if (!shouldStartPayLaterApproval()) {
            return;
        }

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        } else if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        startPayLaterConfirmOrderRedirect();
    }

    function bindPaylaterRowSelection() {
        if (window.__paypalacPaylaterRowSelectionBound) {
            return;
        }
        window.__paypalacPaylaterRowSelectionBound = true;
        window.__paypalacPaylaterRowInteracted = false;

        document.addEventListener('click', function (event) {
            var row = findPaylaterPaymentRow();
            if (!row || !event.target || !row.contains(event.target)) {
                return;
            }
            // Do not steal clicks aimed at the hosted Buttons iframe/button —
            // those start the yellow-button popup flow.
            var buttonEl = getPaylaterButtonElement();
            if (buttonEl && (event.target === buttonEl || buttonEl.contains(event.target))) {
                window.__paypalacPaylaterRowInteracted = true;
                selectPaylaterRadio();
                return;
            }
            window.__paypalacPaylaterRowInteracted = true;
            selectPaylaterRadio();
        }, true);

        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'pmt-paypalac_paylater') {
                window.__paypalacPaylaterRowInteracted = !!event.target.checked;
            } else if (event.target && event.target.name === 'payment' && event.target.id !== 'pmt-paypalac_paylater') {
                window.__paypalacPaylaterRowInteracted = false;
            }
        }, true);
    }

    function hideModuleRadio() {
        var moduleRadio = document.getElementById('pmt-paypalac_paylater');
        if (moduleRadio) {
            moduleRadio.classList.add('paypalac-wallet-radio-hidden');
            var control = moduleRadio.closest('.custom-radio, .custom-control, .nmx-radio');
            if (control) {
                control.classList.add('paypalac-wallet-radio-hidden-control');
            }
            return true;
        }

        return false;
    }

    function hideModuleLabel() {
        var moduleLabel = document.querySelector('label[for="pmt-paypalac_paylater"]');
        if (moduleLabel) {
            moduleLabel.classList.add('paypalac-wallet-label-hidden');
            moduleLabel.style.display = 'none';
            moduleLabel.setAttribute('aria-hidden', 'true');
            return true;
        }

        return false;
    }

    function findPaymentMethodWrapper() {
        var moduleRadio = document.getElementById('pmt-paypalac_paylater');
        if (moduleRadio) {
            return moduleRadio.closest('[id*="paypalac_paylater"][id*="container"]')
                || moduleRadio.closest('.moduleRow')
                || moduleRadio.closest('[class*="paypalac_paylater"]')
                || moduleRadio.parentElement;
        }

        var container = document.getElementById('paypalac-paylater-button');
        if (!container) {
            return null;
        }

        return container.closest('[id*="paypalac_paylater"][id*="container"]')
            || container.closest('.moduleRow')
            || container.closest('[class*="paypalac_paylater"]')
            || container.parentElement;
    }

    /**
     * Hide the entire payment method when it cannot be used at all (config
     * failure or amount outside Pay Later limits).
     */
    function hidePaymentMethodContainer() {
        hideModuleRadio();
        hideModuleLabel();

        var wrapper = findPaymentMethodWrapper();
        if (wrapper) {
            wrapper.style.display = 'none';
            return;
        }

        var container = document.getElementById('paypalac-paylater-button');
        if (container) {
            container.style.display = 'none';
        }
    }

    /**
     * Buttons SDK can report Pay Later ineligible after a cancel/return even
     * though Confirm Order redirect still works. Keep the radio row visible and
     * only clear the yellow button so shoppers can still use Confirm Order.
     */
    function hidePayLaterButtonOnly() {
        var container = document.getElementById('paypalac-paylater-button');
        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }
    }

    function rerenderPaylaterButton() {
        if (sdkState.config && shouldHidePayLaterForConfig(sdkState.config)) {
            logPayLaterLimitState(sdkState.config, 'rerender');
            hidePaymentMethodContainer();
            return;
        }

        if (typeof window.paypalacPaylaterRender === 'function') {
            window.paypalacPaylaterRender();
        }

        if (typeof document !== 'undefined' && typeof document.dispatchEvent === 'function') {
            document.dispatchEvent(new CustomEvent('paypalac:paylater:rerender'));
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
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'paylater', config_only: true })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to load Pay Later configuration', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to load Pay Later configuration' };
        });
    }

    /**
     * Create a PayPal order for Pay Later.
     * Called when user clicks the Pay Later button.
     */
    function fetchWalletOrder() {
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet: 'paylater' })
        }).then(parseWalletResponse).catch(function (error) {
            console.error('Unable to create Pay Later order', error);
            return { success: false, message: error && error.message ? error.message : 'Unable to create Pay Later order' };
        });
    }

    function buildSdkKey(config) {
        var currency = config.currency || 'USD';
        var merchantId = config.merchantId || '';
        var environment = config.environment || 'sandbox';
        var intent = normalizeSdkIntent(config.intent);
        return [config.clientId, currency, merchantId, environment, intent, 'paylater'].join('|');
    }

    function normalizeSdkIntent(intent) {
        var value = String(intent || 'capture').toLowerCase();
        return (value === 'authorize') ? 'authorize' : 'capture';
    }

    function getScriptSdkIntent(script) {
        if (!script || !script.src) {
            return 'capture';
        }

        var match = script.src.match(/[?&]intent=([^&]*)/i);
        if (!match || match[1] === '') {
            return 'capture';
        }

        return normalizeSdkIntent(decodeURIComponent(match[1]));
    }

    function findHeaderPayPalScript() {
        return document.getElementById('PayPalJSSDK')
            || document.querySelector('script[data-namespace="PayPalSDK"]')
            || document.querySelector('script[src*="paypal.com/sdk/js"]');
    }

    function namespaceHasPayLaterButtons(ns) {
        return !!(ns && typeof ns.Buttons === 'function' && ns.FUNDING && ns.FUNDING.PAYLATER);
    }

    function headerScriptAllowsSandboxPayLater(script) {
        if (!script || !script.src) {
            return true;
        }

        // Sandbox Pay Later Buttons eligibility follows buyer-country. Reusing a
        // header SDK loaded with CA (or any non-US country) makes isEligible()
        // false even though Confirm Order redirect still works.
        var match = script.src.match(/[?&]buyer-country=([^&]*)/i);
        if (!match) {
            return true;
        }

        return String(decodeURIComponent(match[1] || '')).toUpperCase() === 'US';
    }

    function findReusablePayLaterNamespace(config) {
        var desiredIntent = normalizeSdkIntent(config && config.intent);
        var isSandbox = ((config && config.environment) || '') === 'sandbox';

        // Prefer the dedicated Pay Later namespace when present.
        if (namespaceHasPayLaterButtons(window.paypalacPaylater)) {
            return window.paypalacPaylater;
        }

        var headerScript = findHeaderPayPalScript();
        var headerIntentMatches = getScriptSdkIntent(headerScript) === desiredIntent;
        var headerNs = window.PayPalSDK || window.paypal;

        if (headerIntentMatches && namespaceHasPayLaterButtons(headerNs)) {
            if (isSandbox && !headerScriptAllowsSandboxPayLater(headerScript)) {
                return null;
            }
            return headerNs;
        }

        return null;
    }

    function loadPayPalSdk(config) {
        if (!config || !config.clientId) {
            return Promise.reject(new Error('Missing clientId for PayPal SDK load'));
        }

        var desiredKey = buildSdkKey(config);
        var existingPaylaterScript = document.querySelector('script[data-paypal-sdk="paylater"]');
        var isSandbox = (config.environment || '') === 'sandbox';
        var reusableNs = findReusablePayLaterNamespace(config);

        if (reusableNs) {
            sharedSdkLoader.key = desiredKey;
            sharedSdkLoader.promise = Promise.resolve(reusableNs);
            return sharedSdkLoader.promise;
        }

        var paypalNs = window.paypalacPaylater;
        if (sharedSdkLoader.promise && sharedSdkLoader.key === desiredKey && namespaceHasPayLaterButtons(paypalNs)) {
            return sharedSdkLoader.promise.then(function () { return window.paypalacPaylater || getPayPalNamespace(); });
        }

        if (namespaceHasPayLaterButtons(window.paypalacPaylater)) {
            sharedSdkLoader.key = desiredKey;
            sharedSdkLoader.promise = Promise.resolve(window.paypalacPaylater);
            return sharedSdkLoader.promise;
        }

        if (existingPaylaterScript) {
            var matchesClient = existingPaylaterScript.src.indexOf(encodeURIComponent(config.clientId)) !== -1;
            var matchesCurrency = existingPaylaterScript.src.indexOf('currency=' + encodeURIComponent(config.currency || 'USD')) !== -1;
            var matchesMerchant = !config.merchantId || existingPaylaterScript.src.indexOf('merchant-id=' + encodeURIComponent(config.merchantId)) !== -1;
            var matchesPaylater = existingPaylaterScript.src.indexOf('enable-funding=paylater') !== -1;
            var matchesButtons = existingPaylaterScript.src.indexOf('components=buttons') !== -1
                || existingPaylaterScript.src.indexOf('components=') === -1
                || /components=[^&]*buttons/.test(existingPaylaterScript.src);
            var matchesIntent = getScriptSdkIntent(existingPaylaterScript) === normalizeSdkIntent(config.intent);

            if (matchesClient && matchesCurrency && matchesMerchant && matchesPaylater && matchesButtons && matchesIntent) {
                if (namespaceHasPayLaterButtons(window.paypalacPaylater)) {
                    sharedSdkLoader.key = desiredKey;
                    sharedSdkLoader.promise = Promise.resolve(window.paypalacPaylater);
                    return sharedSdkLoader.promise;
                }

                return new Promise(function (resolve, reject) {
                    existingPaylaterScript.addEventListener('load', function () {
                        existingPaylaterScript.dataset.loaded = 'true';
                        sharedSdkLoader.key = desiredKey;
                        resolve(window.paypalacPaylater || getPayPalNamespace());
                    });
                    existingPaylaterScript.addEventListener('error', function (event) {
                        sharedSdkLoader.promise = null;
                        reject(event);
                    });
                });
            }

            if (existingPaylaterScript.parentNode) {
                existingPaylaterScript.parentNode.removeChild(existingPaylaterScript);
            }
        }

        var query = '?client-id=' + encodeURIComponent(config.clientId)
            + '&components=buttons'
            + '&enable-funding=paylater'
            + '&intent=' + encodeURIComponent(normalizeSdkIntent(config.intent))
            + '&currency=' + encodeURIComponent(config.currency || 'USD');

        if (isSandbox) {
            query += '&buyer-country=US';
        }

        if (config.merchantId && /^[A-Z0-9]{5,20}$/i.test(config.merchantId)) {
            query += '&merchant-id=' + encodeURIComponent(config.merchantId);
        }

        sharedSdkLoader.promise = new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://www.paypal.com/sdk/js' + query;
            script.dataset.paypalSdk = 'paylater';
            script.dataset.loaded = 'false';
            script.setAttribute('data-namespace', 'paypalacPaylater');

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
                resolve(window.paypalacPaylater || getPayPalNamespace());
            };
            script.onerror = function (event) {
                sharedSdkLoader.promise = null;
                reject(event);
            };
            document.head.appendChild(script);
        });

        return sharedSdkLoader.promise;
    }

    function renderPaylaterButton() {
        var container = document.getElementById('paypalac-paylater-button');
        if (!container) {
            return;
        }

        renderRequestId++;
        var currentRenderRequestId = renderRequestId;

        normalizeWalletContainer(container);
        container.innerHTML = '';

        // First, fetch only the SDK configuration (no order creation)
        fetchWalletConfig().then(function (config) {
            if (currentRenderRequestId !== renderRequestId) {
                return null;
            }

            if (!config || config.success === false) {
                console.warn('Unable to load Pay Later configuration', config);
                hidePaymentMethodContainer();
                return null;
            }

            if (shouldHidePayLaterForConfig(config)) {
                logPayLaterLimitState(config, 'config');
                hidePaymentMethodContainer();
                return null;
            }

            sdkState.config = config;
            container.style.display = '';
            return loadPayPalSdk(config).then(function (paypal) {
                if (currentRenderRequestId !== renderRequestId) {
                    return null;
                }

                // Create the button instance to check eligibility
                var buttonInstance = paypal.Buttons({
                    fundingSource: paypal.FUNDING.PAYLATER,
                    style: {
                        shape: 'rect',
                        height: 40,
                        color: 'gold'
                    },
                    // createOrder is called when user clicks the button - this is when we create the PayPal order
                    createOrder: function () {
                        return fetchWalletOrder().then(function (orderConfig) {
                            if (orderConfig && orderConfig.success !== false) {
                                sdkState.config = orderConfig;
                                return orderConfig.orderID;
                            }

                            var failureMessage = (orderConfig && orderConfig.message)
                                ? orderConfig.message
                                : 'Unable to create Pay Later order';
                            console.error('Pay Later order creation failed', orderConfig || {});
                            throw new Error(failureMessage);
                        });
                    },
                    onClick: function () {
                        selectPaylaterRadio();
                    },
                    onApprove: function (data) {
                        var payload = {
                            orderID: data.orderID,
                            payerID: data.payerID,
                            paymentID: data.paymentID,
                            facilitatorAccessToken: data.facilitatorAccessToken,
                            wallet: 'paylater'
                        };
                        return cachePaylaterPayload(payload).finally(function () {
                            setPaylaterPayload(payload);
                            document.dispatchEvent(new CustomEvent('paypalac:paylater:payload', { detail: payload }));
                        });
                    },
                    onCancel: function (data) {
                        console.warn('Pay Later cancelled', data);
                        setPaylaterPayload({});
                        document.dispatchEvent(new CustomEvent('paypalac:paylater:payload', { detail: {} }));
                    },
                    onError: function (error) {
                        console.error('Pay Later encountered an error', error);
                        setPaylaterPayload({});
                        document.dispatchEvent(new CustomEvent('paypalac:paylater:payload', { detail: {} }));
                    }
                });

                // Buttons eligibility is for the yellow widget only. Confirm Order
                // uses a server redirect and must remain available when the SDK
                // reports ineligible (common after cancel/return from PayPal).
                try {
                    if (typeof buttonInstance.isEligible === 'function' && !buttonInstance.isEligible()) {
                        console.log('Pay Later Buttons widget is not eligible; keeping radio for Confirm Order');
                        hidePayLaterButtonOnly();
                        return null;
                    }
                } catch (eligibilityError) {
                    console.warn('Error checking Pay Later Buttons eligibility:', eligibilityError);
                    hidePayLaterButtonOnly();
                    return null;
                }

                if (currentRenderRequestId !== renderRequestId) {
                    return null;
                }

                return buttonInstance.render('#paypalac-paylater-button');
            });
        }).catch(function (error) {
            if (currentRenderRequestId !== renderRequestId) {
                return;
            }

            console.error('Failed to render Pay Later button', error);
            hidePayLaterButtonOnly();
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
            rerenderTimeout = setTimeout(function() {
                var pageTotal = getOrderTotalFromPage();
                if (sdkState.config) {
                    sdkState.config.orderTotal = pageTotal;
                }
                rerenderPaylaterButton();
            }, 50);
        });

        observer.observe(totalElement, { childList: true, subtree: true, characterData: true });
    }

    window.paypalacPaylaterSetPayload = setPaylaterPayload;
    window.paypalacPaylaterSelectRadio = selectPaylaterRadio;

    document.addEventListener('paypalac:paylater:payload', function (event) {
        setPaylaterPayload(event.detail || {});
    });

    // Match PayPal: keep the native radio visible and place the branded
    // Pay Later button to its right (no text label). Confirm Order starts
    // a full-page Pay Later approval redirect (browsers block synthetic
    // clicks on the hosted Buttons iframe).
    bindPaylaterRowSelection();
    wrapSubmitCheckout();
    document.addEventListener('click', interceptConfirmOrderClick, true);

    if (!window.__paypalacPaylaterSubmitInterceptInstalled) {
        window.__paypalacPaylaterSubmitInterceptInstalled = true;
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.getAttribute('name') !== 'checkout_payment') {
                return;
            }
            interceptPaylaterCheckoutSubmit(event);
        }, true);
    }

    var container = document.getElementById('paypalac-paylater-button');
    if (container) {
        normalizeWalletContainer(container);
        container.addEventListener('click', function() {
            selectPaylaterRadio();
        });

        if (container.innerHTML.trim() === '') {
            container.innerHTML = '<span class="paypalac-paylater-placeholder">' + (typeof paypalacPaylaterText !== 'undefined' ? paypalacPaylaterText : 'Pay Later') + '</span>';
            normalizeWalletButton(container.firstElementChild);
        }
    }

    if (typeof window !== 'undefined') {
        window.paypalacPaylaterRender = renderPaylaterButton;
    }

    renderPaylaterButton();
    observeOrderTotal();
})();
