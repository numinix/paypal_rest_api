<?php
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree_applepay.php');
$applePayModule = new braintree_applepay();
$clientToken = $applePayModule->generate_client_token();
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

<script src="https://js.braintreegateway.com/web/3.115.2/js/client.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.115.2/js/apple-pay.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.115.2/js/three-d-secure.min.js"></script>

<script>
"use strict";

document.addEventListener("DOMContentLoaded", function () {
    if (typeof ApplePaySession === "undefined" || !ApplePaySession.canMakePayments()) return;

    const container = document.getElementById("apple-pay-button-container");
    if (!container) return;

    braintree.client.create({
        authorization: "<?php echo $clientToken; ?>"
    }).then(clientInstance => {
        braintree.threeDSecure.create({ client: clientInstance, version: 2 }, function (err, threeDSInstance) {
            if (!err) {
                window.appleThreeDS = threeDSInstance;
            } else {
                console.warn("3DS setup skipped:", err);
                window.appleThreeDS = null;
            }
        });

        return braintree.applePay.create({ client: clientInstance });
    }).then(applePayInstance => {
        let applePayButtonReady = false;

        function initializeApplePayButton() {
            if (applePayButtonReady) return;
            applePayButtonReady = true;

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
                        if (err) return session.abort();
                        session.completeMerchantValidation(merchantSession);
                    });
                };

                session.onshippingcontactselected = event => {
                    cachedShippingContact = event.shippingContact;

                    // 1. Clear cart
                    fetch("ajax/braintree_clear_cart.php", { method: "POST" })
                    .then(() => {
                        // 2. Add product to cart
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
                        // 3. Now request shipping
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
                        const shippingOptionParams = data.newShippingOptionParameters || {};
                        if (!cachedSelectedShippingId && shippingOptionParams.defaultSelectedOptionId) {
                            cachedSelectedShippingId = shippingOptionParams.defaultSelectedOptionId;
                        }

                        const fallbackShippingOptions = Array.isArray(shippingOptionParams.shippingOptions) ? shippingOptionParams.shippingOptions : [];
                        const shippingMethods = data.newShippingMethods || fallbackShippingOptions.map(opt => ({
                            label: opt.label,
                            amount: opt.price,
                            identifier: opt.id,
                            detail: opt.detail
                        }));

                        const transactionInfo = data.newTransactionInfo || {};
                        const total = (data.newTotal && data.newTotal.amount) || transactionInfo.totalPrice || "<?php echo $initialTotal; ?>";
                        const lineItemSource = data.newLineItems || (Array.isArray(transactionInfo.displayItems) ? transactionInfo.displayItems : []);
                        const safeLineItems = Array.isArray(lineItemSource) ? lineItemSource : [];
                        const lineItems = safeLineItems.map(item => ({
                            label: item.label,
                            amount: item.price
                        }));

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
                        const lineItems = Array.isArray(data.newLineItems) ? data.newLineItems : [];
                        const total = (data.newTotal && data.newTotal.amount) || "<?php echo $initialTotal; ?>";

                        window.applePayFinalTotal = total;

                        session.completeShippingMethodSelection({
                            newTotal: { label: "<?php echo addslashes(STORE_NAME); ?>", amount: total },
                            newLineItems: lineItems
                        });
                    })
                    .catch(() => session.abort());
                };

                session.onpaymentauthorized = function (event) {
                    applePayInstance.tokenize({ token: event.payment.token }, function (err, payload) {
                        if (err || !payload || !payload.nonce) {
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
                                    streetAddress: (billingContact.addressLines && billingContact.addressLines[0]) || '',
                                    extendedAddress: (billingContact.addressLines && billingContact.addressLines[1]) || '',
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
                    });
                };

                session.begin();
            });
        }

        function fallbackToBasicApplePay(reason) {
            console.warn("Apple Pay active-card check failed or unavailable:", reason);
            try {
                if (typeof ApplePaySession !== "undefined" && typeof ApplePaySession.canMakePayments === "function") {
                    if (ApplePaySession.canMakePayments()) {
                        initializeApplePayButton();
                        return;
                    }
                }
            } catch (err) {
                console.warn("Apple Pay basic availability check failed:", err);
            }
        }

        const canCheckActiveCard = (
            typeof ApplePaySession !== "undefined" &&
            typeof ApplePaySession.canMakePaymentsWithActiveCard === "function" &&
            applePayInstance.merchantIdentifier
        );

        if (!canCheckActiveCard) {
            fallbackToBasicApplePay("Active-card check not supported in this browser.");
            return;
        }

        ApplePaySession.canMakePaymentsWithActiveCard(applePayInstance.merchantIdentifier)
            .then(function (canMakePayments) {
                if (canMakePayments) {
                    initializeApplePayButton();
                } else {
                    fallbackToBasicApplePay("No active cards");
                }
            })
            .catch(fallbackToBasicApplePay);
    });
});
</script>

<!-- Apple Pay Button Container -->
<div id="apple-pay-button-container"></div>
