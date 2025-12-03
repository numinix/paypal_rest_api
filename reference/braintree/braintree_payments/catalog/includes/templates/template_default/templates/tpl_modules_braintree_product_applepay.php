<?php
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree_applepay.php');
$applePayModule = new braintree_applepay();
$clientToken = $applePayModule->generate_client_token();

$use3DS = (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_USE_3DS') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_USE_3DS === 'True');
?>

<style>
.apple-pay-button {
    appearance: -apple-pay-button;
    -apple-pay-button-type: buy;
    -apple-pay-button-style: black;
    height: 44px;
    width: 100%;
    max-width: 400px;
    display: inline-block;
    margin: 0.5em 0;
    cursor: pointer;
}
</style>

<script>
(function () {
    "use strict";

    window.applePayScriptsLoaded = window.applePayScriptsLoaded || false;
    let applePayRetryAttempts = 0;
    const MAX_RETRY_ATTEMPTS = 3;

    // Detect iOS Chrome (CriOS) - needed for sequential loading
    const ua = navigator.userAgent || "";
    const isIOSChrome = /CriOS/.test(ua);

    function hasModernBrowserSupport() {
        return (typeof window.Promise !== 'undefined') && (typeof window.fetch === 'function');
    }

    if (!hasModernBrowserSupport()) {
        return;
    }

    function loadApplePayScripts() {
        if (window.applePayScriptsLoaded) {
            console.log('Apple Pay (Product): Required scripts already loaded');
            return Promise.resolve();
        }

        const scripts = [
            "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
            "https://js.braintreegateway.com/web/3.133.0/js/apple-pay.min.js",
            "https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js"
        ];

        const loadScript = function (src) {
            return new Promise(function (resolve, reject) {
                const selector = 'script[src="' + src.replace(/"/g, '\\"') + '"]';
                const existing = document.querySelector(selector);
                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        console.log('Apple Pay (Product): Script already loaded', src);
                        resolve();
                        return;
                    }
                    // Script exists but may already be loaded (e.g., from cache on page refresh)
                    // Check if it's already loaded or still loading
                    if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                        existing.dataset.loaded = 'true';
                        console.log('Apple Pay (Product): Script already loaded (from cache)', src);
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', function () {
                        existing.dataset.loaded = 'true';
                        console.log('Apple Pay (Product): Script loaded', src);
                        resolve();
                    });
                    existing.addEventListener('error', function () {
                        console.warn('Apple Pay (Product): Failed to load script', src);
                        reject(new Error("Failed to load script: " + src));
                    });
                    return;
                }

                const script = document.createElement("script");
                script.src = src;
                script.async = true;
                script.dataset.loaded = 'false';
                script.addEventListener('load', function () {
                    script.dataset.loaded = 'true';
                    console.log('Apple Pay (Product): Loaded script', src);
                    resolve();
                });
                script.addEventListener('error', function () {
                    console.warn('Apple Pay (Product): Failed to load script', src);
                    reject(new Error("Failed to load script: " + src));
                });
                document.head.appendChild(script);
            });
        };

        console.log('Apple Pay (Product): Loading resources', scripts);
        console.log('Apple Pay (Product): Browser detection - iOS Chrome:', isIOSChrome);

        // iOS Chrome requires sequential loading to avoid race conditions and initialization issues
        // All other browsers work fine with parallel loading (faster)
        if (isIOSChrome) {
            console.log('Apple Pay (Product): Using sequential loading for iOS Chrome');
            return scripts.reduce(function (promise, src) {
                return promise.then(function () {
                    return loadScript(src);
                });
            }, Promise.resolve()).then(function () {
                window.applePayScriptsLoaded = true;
            });
        } else {
            console.log('Apple Pay (Product): Using parallel loading for non-iOS Chrome browsers');
            return Promise.all(scripts.map(function (src) {
                return loadScript(src);
            })).then(function () {
                window.applePayScriptsLoaded = true;
            });
        }
    }

    function onDomReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function initializeProductApplePay() {
        if (typeof ApplePaySession === "undefined" || !ApplePaySession.canMakePayments()) {
            return;
        }

        const container = document.getElementById("apple-pay-button-container");
        if (!container) {
            return;
        }

        braintree.client.create({
            authorization: "<?php echo $clientToken; ?>"
        }).then(function (clientInstance) {
            <?php if ($use3DS): ?>
            return braintree.threeDSecure.create({ client: clientInstance, version: 2 })
                .then(function (threeDSInstance) {
                    window.appleThreeDS = threeDSInstance;
                    return braintree.applePay.create({ client: clientInstance });
                })
                .catch(function (err) {
                    console.warn("3DS setup skipped:", err);
                    window.appleThreeDS = null;
                    return braintree.applePay.create({ client: clientInstance });
                });
            <?php else: ?>
            return braintree.applePay.create({ client: clientInstance });
            <?php endif; ?>
        }).then(function (applePayInstance) {
            if (!applePayInstance) {
                return;
            }

            ApplePaySession.canMakePaymentsWithActiveCard(applePayInstance.merchantIdentifier).then(function (canMakePayments) {
                if (!canMakePayments) {
                    return;
                }

                const button = document.createElement("button");
                button.className = "apple-pay-button";
                button.type = "button";
                container.appendChild(button);

                let cachedShippingContact = null;
                let cachedSelectedShippingId = null;
                window.applePayFinalTotal = "<?php echo $initialTotal; ?>";

                button.addEventListener("click", function () {
                    const paymentRequest = applePayInstance.createPaymentRequest({
                        countryCode: "<?php echo $storeCountryCode; ?>",
                        currencyCode: "<?php echo $currencyCode; ?>",
                        total: {
                            label: "<?php echo addslashes(STORE_NAME); ?>",
                            amount: window.applePayFinalTotal
                        },
                        requiredBillingContactFields: ["postalAddress", "name", "phone", "email"],
                        requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
                        shippingType: "shipping"
                    });

                    const session = new ApplePaySession(3, paymentRequest);

                    session.onvalidatemerchant = function (event) {
                        applePayInstance.performValidation({
                            validationURL: event.validationURL,
                            displayName: "<?php echo addslashes(STORE_NAME); ?>"
                        })
                            .then(function (merchantSession) {
                                session.completeMerchantValidation(merchantSession);
                            })
                            .catch(function (err) {
                                console.error('Apple Pay validation error:', err);
                                session.abort();
                            });
                    };

                    session.onshippingcontactselected = event => {
                        cachedShippingContact = event.shippingContact;

                        fetch("ajax/braintree_clear_cart.php", { method: "POST" })
                            .then(() => {
                                const form = document.forms["cart_quantity"];
                                if (!form) {
                                    throw new Error("Add-to-cart form not found");
                                }

                                const formData = new FormData(form);
                                return fetch(form.action, {
                                    method: "POST",
                                    body: formData
                                });
                            })
                            .then(() => {
                                return fetch("ajax/braintree.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({
                                        shippingAddress: cachedShippingContact,
                                        selectedShippingOptionId: cachedSelectedShippingId || null,
                                        module: "braintree_applepay"
                                    })
                                });
                            })
                            .then(response => response.json())
                            .then(data => {
                                const shippingParams = data.newShippingOptionParameters || {};
                                if (!cachedSelectedShippingId && shippingParams.defaultSelectedOptionId) {
                                    cachedSelectedShippingId = shippingParams.defaultSelectedOptionId;
                                }

                                const fallbackShippingMethods = Array.isArray(shippingParams.shippingOptions)
                                    ? shippingParams.shippingOptions.map(function (opt) {
                                        return {
                                            label: opt.label,
                                            amount: opt.price,
                                            identifier: opt.id,
                                            detail: opt.detail
                                        };
                                    })
                                    : [];
                                const shippingMethods = data.newShippingMethods || fallbackShippingMethods;

                                const transactionInfo = data.newTransactionInfo || {};
                                const total = (data.newTotal && data.newTotal.amount) || transactionInfo.totalPrice || "<?php echo $initialTotal; ?>";
                                const fallbackLineItems = Array.isArray(transactionInfo.displayItems)
                                    ? transactionInfo.displayItems.map(function (item) {
                                        return {
                                            label: item.label,
                                            amount: item.price
                                        };
                                    })
                                    : [];
                                const lineItems = data.newLineItems || fallbackLineItems;

                                window.applePayFinalTotal = total;

                                session.completeShippingContactSelection({
                                    newShippingMethods: shippingMethods,
                                    newTotal: { label: "<?php echo addslashes(STORE_NAME); ?>", amount: total },
                                    newLineItems: lineItems
                                });
                            })
                            .catch(err => {
                                console.warn("Apple Pay shipping flow failed:", err);
                                session.abort();
                            });
                    };

                    session.onshippingmethodselected = event => {
                        if (!cachedShippingContact) return session.abort();

                        cachedSelectedShippingId = event.shippingMethod.identifier;

                        fetch("ajax/braintree.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                selectedShippingOptionId: cachedSelectedShippingId,
                                shippingAddress: cachedShippingContact,
                                module: "braintree_applepay"
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                const transactionInfo = data.newTransactionInfo || {};
                                const total = (data.newTotal && data.newTotal.amount) || transactionInfo.totalPrice || "<?php echo $initialTotal; ?>";
                                const fallbackLineItems = Array.isArray(transactionInfo.displayItems)
                                    ? transactionInfo.displayItems.map(function (item) {
                                        return {
                                            label: item.label,
                                            amount: item.price
                                        };
                                    })
                                    : [];
                                const lineItems = data.newLineItems || fallbackLineItems;

                                window.applePayFinalTotal = total;

                                session.completeShippingMethodSelection({
                                    newTotal: { label: "<?php echo addslashes(STORE_NAME); ?>", amount: total },
                                    newLineItems: lineItems
                                });
                            })
                            .catch(() => session.abort());
                    };

                    session.onpaymentauthorized = function (event) {
                        applePayInstance.tokenize({ token: event.payment.token })
                            .then(function (payload) {
                                if (!payload || !payload.nonce) {
                                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    return;
                                }

                                const bin = payload.details && payload.details.bin;
                                const billingContact = event.payment.billingContact || event.payment.shippingContact;

                                const normalize = contact => ({
                                    name: ((contact.givenName || "") + " " + (contact.familyName || "")).trim(),
                                    email: contact.emailAddress || '',
                                    phone: contact.phoneNumber || '',
                                    address1: contact.addressLines ? contact.addressLines[0] : '',
                                    address2: contact.addressLines ? (contact.addressLines[1] || '') : '',
                                    postalCode: contact.postalCode || '',
                                    locality: contact.locality || '',
                                    administrativeArea: contact.administrativeArea || '',
                                    countryCode: contact.countryCode || '',
                                    country: contact.country || ''
                                });

                                const proceedToCheckout = function (finalNonce) {
                                    const payloadData = {
                                        payment_method_nonce: finalNonce,
                                        module: "braintree_applepay",
                                        currency: "<?php echo $currencyCode; ?>",
                                        total: window.applePayFinalTotal,
                                        shipping_address: normalize(event.payment.shippingContact),
                                        billing_address: normalize(event.payment.billingContact || event.payment.shippingContact),
                                        email: ((event.payment.billingContact && event.payment.billingContact.emailAddress) || (event.payment.shippingContact && event.payment.shippingContact.emailAddress) || '')
                                    };

                                    fetch("ajax/braintree_checkout_handler.php", {
                                        method: "POST",
                                        headers: { "Content-Type": "application/json" },
                                        body: JSON.stringify(payloadData)
                                    })
                                        .then(response => response.json())
                                        .then(json => {
                                            if (json.status === "success" && json.redirect_url) {
                                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                                window.location.href = json.redirect_url;
                                            } else {
                                                session.completePayment(ApplePaySession.STATUS_FAILURE);
                                            }
                                        })
                                        .catch(() => session.completePayment(ApplePaySession.STATUS_FAILURE));
                                };

                                if (window.appleThreeDS && bin) {
                                    window.appleThreeDS.verifyCard({
                                        nonce: payload.nonce,
                                        bin: bin,
                                        amount: window.applePayFinalTotal,
                                        email: billingContact.emailAddress || '',
                                        billingAddress: {
                                            givenName: billingContact.givenName || '',
                                            surname: billingContact.familyName || '',
                                            phoneNumber: billingContact.phoneNumber || '',
                                            streetAddress: (billingContact.addressLines && billingContact.addressLines[0]) ? billingContact.addressLines[0] : '',
                                            extendedAddress: (billingContact.addressLines && billingContact.addressLines[1]) ? billingContact.addressLines[1] : '',
                                            locality: billingContact.locality || '',
                                            region: billingContact.administrativeArea || '',
                                            postalCode: billingContact.postalCode || '',
                                            countryCodeAlpha2: billingContact.countryCode || ''
                                        },
                                        onLookupComplete: function (data, next) { next(); }
                                    }).then(function (verification) {
                                        if (verification && verification.nonce) {
                                            proceedToCheckout(verification.nonce);
                                        } else {
                                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                                        }
                                    }).catch(err => {
                                        console.warn("3DS verification failed:", err);
                                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    });
                                } else {
                                    proceedToCheckout(payload.nonce);
                                }
                            })
                            .catch(function (err) {
                                console.error('Apple Pay tokenize error:', err);
                                session.completePayment(ApplePaySession.STATUS_FAILURE);
                            });
                    };

                    session.begin();
                });
            });
        }).catch(error => {
            console.error('Error initializing Apple Pay:', error);
        });
    }

    function attemptApplePayInitialization() {
        loadApplePayScripts()
            .then(function () {
                onDomReady(initializeProductApplePay);
                applePayRetryAttempts = 0; // Reset on success
            })
            .catch(function (error) {
                console.error('Apple Pay (Product): Failed to load scripts:', error);
                applePayRetryAttempts++;
                
                if (applePayRetryAttempts < MAX_RETRY_ATTEMPTS) {
                    const delay = Math.min(1000 * Math.pow(2, applePayRetryAttempts - 1), 5000);
                    console.log('Apple Pay (Product): Retrying in ' + delay + 'ms (attempt ' + applePayRetryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
                    setTimeout(attemptApplePayInitialization, delay);
                } else {
                    // Fail silently after all retries on cart/product pages
                    console.warn('Apple Pay (Product): Initialization failed after ' + MAX_RETRY_ATTEMPTS + ' attempts. Button will not be displayed.');
                }
            });
    }

    attemptApplePayInitialization();
})();
</script>
<!-- Apple Pay Button Container -->
<div id="apple-pay-button-container"></div>