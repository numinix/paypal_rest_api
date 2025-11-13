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

    const scriptQueue = [
        "https://js.braintreegateway.com/web/3.115.2/js/client.min.js",
        "https://js.braintreegateway.com/web/3.115.2/js/apple-pay.min.js"
        <?php if ($use3DS): ?>,
        "https://js.braintreegateway.com/web/3.115.2/js/three-d-secure.min.js"
        <?php endif; ?>
    ];

    function loadScript(url) {
        return new Promise(function (resolve, reject) {
            const selector = 'script[src="' + url.replace(/"/g, '\"') + '"]';
            const existing = document.querySelector(selector);
            if (existing) {
                if (existing.dataset.loaded === 'true') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () {
                    existing.dataset.loaded = 'true';
                    resolve();
                });
                existing.addEventListener('error', function () {
                    reject(new Error('Failed to load ' + url));
                });
                return;
            }

            const script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.dataset.loaded = 'false';
            script.addEventListener('load', function () {
                script.dataset.loaded = 'true';
                resolve();
            });
            script.addEventListener('error', function () {
                reject(new Error('Failed to load ' + url));
            });
            document.head.appendChild(script);
        });
    }

    function loadScriptsSequential(urls) {
        return urls.reduce(function (promise, url) {
            return promise.then(function () {
                return loadScript(url);
            });
        }, Promise.resolve());
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
        }).then(clientInstance => {
            <?php if ($use3DS): ?>
            braintree.threeDSecure.create({ client: clientInstance, version: 2 }, function (err, threeDSInstance) {
                if (err && err.code === "THREEDS_NOT_ENABLED_FOR_V2") {
                    braintree.threeDSecure.create({ client: clientInstance, version: 1 }, function (errV1, fallbackInstance) {
                        if (!errV1) window.threeDS = fallbackInstance;
                    });
                } else if (!err) {
                    window.threeDS = threeDSInstance;
                }
            });
            <?php endif; ?>

            return braintree.applePay.create({ client: clientInstance });
        }).then(applePayInstance => {
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
                        }, function (err, merchantSession) {
                            if (err) {
                                session.abort();
                                return;
                            }
                            session.completeMerchantValidation(merchantSession);
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
                            if (!cachedSelectedShippingId && data.newShippingOptionParameters?.defaultSelectedOptionId) {
                                cachedSelectedShippingId = data.newShippingOptionParameters.defaultSelectedOptionId;
                            }

                            const shippingMethods = data.newShippingMethods || (data.newShippingOptionParameters?.shippingOptions || []).map(opt => ({
                                label: opt.label,
                                amount: opt.price,
                                identifier: opt.id,
                                detail: opt.detail
                            }));

                            const total = data.newTotal?.amount || data.newTransactionInfo?.totalPrice || "<?php echo $initialTotal; ?>";
                            const lineItems = data.newLineItems || (data.newTransactionInfo?.displayItems || []).map(item => ({
                                label: item.label,
                                amount: item.price
                            }));

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
                            const total = data.newTotal?.amount || data.newTransactionInfo?.totalPrice || "<?php echo $initialTotal; ?>";
                            const lineItems = data.newLineItems || (data.newTransactionInfo?.displayItems || []).map(item => ({
                                label: item.label,
                                amount: item.price
                            }));

                            window.applePayFinalTotal = total;

                            session.completeShippingMethodSelection({
                                newTotal: { label: "<?php echo addslashes($storeName); ?>", amount: total },
                                newLineItems: lineItems
                            });
                        })
                        .catch(() => session.abort());
                    };

                    session.onpaymentauthorized = event => {
                        applePayInstance.tokenize({ token: event.payment.token }, (err, payload) => {
                            if (err || !payload || !payload.nonce) {
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
                                const bin = payload.details?.bin;

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
                                        givenName: billing.name?.split(" ")[0] || '',
                                        surname: billing.name?.split(" ")[1] || '',
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
                        });
                    };

                    session.begin();
                });
            });
        }).catch(error => {
            console.error('Error initializing Apple Pay:', error);
        });
    }

    let applePayRetryAttempts = 0;
    const MAX_RETRY_ATTEMPTS = 3;

    function attemptApplePayInitialization() {
        loadScriptsSequential(scriptQueue)
            .then(function () {
                onDomReady(initializeApplePay);
                applePayRetryAttempts = 0; // Reset on success
            })
            .catch(function (error) {
                console.error('Braintree Apple Pay assets failed to load:', error);
                applePayRetryAttempts++;
                
                if (applePayRetryAttempts < MAX_RETRY_ATTEMPTS) {
                    const delay = Math.min(1000 * Math.pow(2, applePayRetryAttempts - 1), 5000);
                    console.log('Apple Pay: Retrying in ' + delay + 'ms (attempt ' + applePayRetryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
                    setTimeout(attemptApplePayInitialization, delay);
                } else {
                    // Fail silently after all retries on cart/product pages
                    console.warn('Apple Pay: Initialization failed after ' + MAX_RETRY_ATTEMPTS + ' attempts. Button will not be displayed.');
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