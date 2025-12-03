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
        // Apple Pay should remain hidden on unsupported browsers
        return;
    }

    function loadApplePayScripts() {
        if (window.applePayScriptsLoaded) {
            console.log('Apple Pay (Cart): Required scripts already loaded');
            return Promise.resolve();
        }

        const scripts = [
            "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
            "https://js.braintreegateway.com/web/3.133.0/js/apple-pay.min.js"
        ];

        <?php if ($use3DS): ?>
        scripts.push("https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js");
        <?php else: ?>
        console.log('Apple Pay (Cart): 3DS disabled via module settings; skipping 3DS resources');
        delete window.threeDS;
        <?php endif; ?>

        const loadScript = function (src) {
            return new Promise(function (resolve, reject) {
                const selector = 'script[src="' + src.replace(/"/g, '\\"') + '"]';
                const existing = document.querySelector(selector);
                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        console.log('Apple Pay (Cart): Script already loaded', src);
                        resolve();
                        return;
                    }
                    // Script exists but may already be loaded (e.g., from cache on page refresh)
                    // Check if it's already loaded or still loading
                    if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                        existing.dataset.loaded = 'true';
                        console.log('Apple Pay (Cart): Script already loaded (from cache)', src);
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', function () {
                        existing.dataset.loaded = 'true';
                        console.log('Apple Pay (Cart): Script loaded', src);
                        resolve();
                    });
                    existing.addEventListener('error', function () {
                        console.warn('Apple Pay (Cart): Failed to load script', src);
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
                    console.log('Apple Pay (Cart): Loaded script', src);
                    resolve();
                });
                script.addEventListener('error', function () {
                    console.warn('Apple Pay (Cart): Failed to load script', src);
                    reject(new Error("Failed to load script: " + src));
                });
                document.head.appendChild(script);
            });
        };

        console.log('Apple Pay (Cart): Loading resources', scripts);
        console.log('Apple Pay (Cart): Browser detection - iOS Chrome:', isIOSChrome);

        // iOS Chrome requires sequential loading to avoid race conditions and initialization issues
        // All other browsers work fine with parallel loading (faster)
        if (isIOSChrome) {
            console.log('Apple Pay (Cart): Using sequential loading for iOS Chrome');
            return scripts.reduce(function (promise, src) {
                return promise.then(function () {
                    return loadScript(src);
                });
            }, Promise.resolve()).then(function () {
                window.applePayScriptsLoaded = true;
            });
        } else {
            console.log('Apple Pay (Cart): Using parallel loading for non-iOS Chrome browsers');
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

    function initializeApplePay() {
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
                    window.threeDS = threeDSInstance;
                    return braintree.applePay.create({ client: clientInstance });
                })
                .catch(function (err) {
                    if (err && err.code === "THREEDS_NOT_ENABLED_FOR_V2") {
                        return braintree.threeDSecure.create({ client: clientInstance, version: 1 })
                            .then(function (fallbackInstance) {
                                window.threeDS = fallbackInstance;
                                return braintree.applePay.create({ client: clientInstance });
                            })
                            .catch(function (errV1) {
                                console.error('3DS fallback error:', errV1);
                                return braintree.applePay.create({ client: clientInstance });
                            });
                    } else {
                        console.error('3DS error:', err);
                        return braintree.applePay.create({ client: clientInstance });
                    }
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

                        fetch("ajax/braintree.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                shippingAddress: cachedShippingContact,
                                selectedShippingOptionId: cachedSelectedShippingId || undefined,
                                module: "braintree_applepay"
                            })
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
                                newTotal: { label: "<?php echo addslashes($storeName); ?>", amount: total },
                                newLineItems: lineItems
                            });
                        })
                        .catch(() => session.abort());
                    };

                    session.onshippingmethodselected = event => {
                        cachedSelectedShippingId = event.shippingMethod.identifier;
                        if (!cachedShippingContact) return session.abort();

                        fetch("ajax/braintree.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                selectedShippingOptionId: event.shippingMethod.identifier,
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
                                newTotal: { label: "<?php echo addslashes($storeName); ?>", amount: total },
                                newLineItems: lineItems
                            });
                        })
                        .catch(() => session.abort());
                    };

                    session.onpaymentauthorized = event => {
                        applePayInstance.tokenize({ token: event.payment.token })
                            .then(function (payload) {
                                if (!payload || !payload.nonce) {
                                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    return;
                                }

                                const shipping = normalizeContact(event.payment.shippingContact);
                                const billing = normalizeContact(event.payment.billingContact || event.payment.shippingContact);
                                const email = billing.email || shipping.email || '';

                                const payloadData = {
                                    payment_method_nonce: payload.nonce,
                                    module: "braintree_applepay",
                                    currency: "<?php echo $currencyCode; ?>",
                                    total: window.applePayFinalTotal,
                                    shipping_address: shipping,
                                    billing_address: billing,
                                    email: email
                                };

                                <?php if ($use3DS): ?>
                                if (window.threeDS && typeof window.threeDS.verifyCard === 'function' && !window.threeDS._destroyed) {
                                    const bin = payload.details && payload.details.bin;

                                    if (!bin) {
                                        finalizeApplePayPayment(payloadData, session);
                                        return;
                                    }

                                    window.threeDS.verifyCard({
                                        amount: window.applePayFinalTotal,
                                        nonce: payload.nonce,
                                        bin: bin,
                                        email: email,
                                        billingAddress: {
                                            givenName: billing.name ? billing.name.split(" ")[0] || '' : '',
                                            surname: billing.name ? billing.name.split(" ")[1] || '' : '',
                                            phoneNumber: billing.phone,
                                            streetAddress: billing.address1,
                                            locality: billing.locality,
                                            region: billing.administrativeArea,
                                            postalCode: billing.postalCode,
                                            countryCodeAlpha2: billing.countryCode
                                        },
                                        onLookupComplete: function (data, next) {
                                            next();
                                        }
                                    }).then(function (verification) {
                                        if (verification && verification.nonce) {
                                            payloadData.payment_method_nonce = verification.nonce;
                                            finalizeApplePayPayment(payloadData, session);
                                        } else {
                                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                                        }
                                    }).catch(function () {
                                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    });
                                } else {
                                    finalizeApplePayPayment(payloadData, session);
                                }
                                <?php else: ?>
                                finalizeApplePayPayment(payloadData, session);
                                <?php endif; ?>
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
                onDomReady(initializeApplePay);
                applePayRetryAttempts = 0; // Reset on success
            })
            .catch(function (error) {
                console.error('Apple Pay (Cart): Failed to load scripts:', error);
                applePayRetryAttempts++;
                
                if (applePayRetryAttempts < MAX_RETRY_ATTEMPTS) {
                    const delay = Math.min(1000 * Math.pow(2, applePayRetryAttempts - 1), 5000);
                    console.log('Apple Pay (Cart): Retrying in ' + delay + 'ms (attempt ' + applePayRetryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
                    setTimeout(attemptApplePayInitialization, delay);
                } else {
                    // Fail silently after all retries on cart/product pages
                    console.warn('Apple Pay (Cart): Initialization failed after ' + MAX_RETRY_ATTEMPTS + ' attempts. Button will not be displayed.');
                }
            });
    }

    attemptApplePayInitialization();
})();

function normalizeContact(contact) {
    const fullName = ((contact.givenName || "") + " " + (contact.familyName || "")).trim();
    return {
        name: fullName,
        email: contact.emailAddress || '',
        phone: contact.phoneNumber || '',
        address1: contact.addressLines ? contact.addressLines[0] : '',
        address2: contact.addressLines ? (contact.addressLines[1] || '') : '',
        postalCode: contact.postalCode || '',
        locality: contact.locality || '',
        administrativeArea: contact.administrativeArea || '',
        countryCode: contact.countryCode || '',
        country: contact.country || ''
    };
}

function finalizeApplePayPayment(payloadData, session) {
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
}
</script>
<!-- Apple Pay Button Container -->
<div id="apple-pay-button-container"></div>