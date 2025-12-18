<?php
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_applepay.php');
$applePayModule = new paypalr_applepay();
$clientToken = $applePayModule->generate_client_token();

// Define variables if not already set (e.g., when included directly instead of via product info template)
if (!isset($storeCountryCode)) {
    $country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
    $country_result = $db->Execute($country_query);
    $storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';
}
if (empty($currencyCode)) {
    $currencyCode = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
}
if (!isset($initialTotal)) {
    // For product page, calculate based on product price
    $initialTotal = '0.00'; // Will be updated when product is added to cart
}
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
    const MAX_BRAINTREE_WAIT_ATTEMPTS = 10;

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
                    // Script exists but may already be loaded (e.g., from cache on page refresh or by another module like Google Pay)
                    // Check if it's already loaded or still loading
                    if (existing.readyState === 'complete' || existing.readyState === 'loaded' || !existing.readyState) {
                        // If readyState is 'complete', 'loaded', or undefined (script added via <script src=""> tag), consider it loaded
                        existing.dataset.loaded = 'true';
                        console.log('Apple Pay (Product): Script already loaded (from existing tag)', src);
                        resolve();
                        return;
                    }
                    // Script is still loading, wait for it
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
            // Use setTimeout to ensure the container element is rendered
            // even if the DOM is already "ready" when this script runs
            setTimeout(callback, 0);
        }
    }

    function initializeApplePay() {
        if (typeof ApplePaySession === "undefined" || !ApplePaySession.canMakePayments()) {
            return;
        }

        const container = document.getElementById("apple-pay-button-container");
        if (!container) {
            console.warn('Apple Pay (Product): Button container not found in DOM');
            return;
        }

        // Ensure braintree object is available before proceeding
        if (typeof braintree === "undefined" || !braintree.client) {
            console.warn('Apple Pay (Product): Braintree client not ready yet, will retry');
            return;
        }

        braintree.client.create({
            authorization: "<?php echo $clientToken; ?>"
        }).then(function (clientInstance) {
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
                let pendingRedirectUrl = null;
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

                    session.oncancel = event => {
                        console.log('Apple Pay (Product): Session cancelled or completed');
                        if (pendingRedirectUrl) {
                            console.log('Apple Pay (Product): Redirecting to', pendingRedirectUrl);
                            window.location.href = pendingRedirectUrl;
                        }
                    };

                    session.onshippingcontactselected = event => {
                        cachedShippingContact = event.shippingContact;

                        // 1. Clear cart
                        fetch("ajax/paypalr_wallet_clear_cart.php", { method: "POST" })
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
                            return fetch("ajax/paypalr_wallet.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({
                                    shippingAddress: cachedShippingContact,
                                    selectedShippingOptionId: cachedSelectedShippingId || undefined,
                                    module: "paypalr_applepay"
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
                        cachedSelectedShippingId = event.shippingMethod.identifier;
                        if (!cachedShippingContact) return session.abort();

                        fetch("ajax/paypalr_wallet.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                selectedShippingOptionId: event.shippingMethod.identifier,
                                shippingAddress: cachedShippingContact,
                                module: "paypalr_applepay"
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
                                    module: "paypalr_applepay",
                                    currency: "<?php echo $currencyCode; ?>",
                                    total: window.applePayFinalTotal,
                                    shipping_address: shipping,
                                    billing_address: billing,
                                    email: email
                                };

                                if (window.appleThreeDS && typeof window.appleThreeDS.verifyCard === 'function' && !window.appleThreeDS._destroyed) {
                                    const bin = payload.details && payload.details.bin;

                                    if (!bin) {
                                        finalizeApplePayPayment(payloadData, session);
                                        return;
                                    }

                                    window.appleThreeDS.verifyCard({
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
                // Wait for braintree object to be available after scripts load
                function waitForBraintree(attempts) {
                    attempts = attempts || 0;
                    if (typeof braintree !== "undefined" && braintree.client) {
                        console.log('Apple Pay (Product): Braintree client ready, initializing');
                        onDomReady(initializeApplePay);
                        applePayRetryAttempts = 0; // Reset on success
                    } else if (attempts < MAX_BRAINTREE_WAIT_ATTEMPTS) {
                        console.log('Apple Pay (Product): Waiting for braintree object (attempt ' + (attempts + 1) + ')');
                        setTimeout(function() { waitForBraintree(attempts + 1); }, 100);
                    } else {
                        console.error('Apple Pay (Product): Braintree client not available after waiting');
                        applePayRetryAttempts++;
                        if (applePayRetryAttempts < MAX_RETRY_ATTEMPTS) {
                            const delay = Math.min(1000 * Math.pow(2, applePayRetryAttempts - 1), 5000);
                            console.log('Apple Pay (Product): Retrying full initialization in ' + delay + 'ms');
                            setTimeout(attemptApplePayInitialization, delay);
                        }
                    }
                }
                waitForBraintree();
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
    console.log('Apple Pay (Product): Finalizing payment', payloadData);
    fetch("ajax/paypalr_wallet_checkout.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify(payloadData)
    })
    .then(response => {
        console.log('Apple Pay (Product): Received response', response);
        if (response.redirected && response.url) {
            return { status: 'success', redirect_url: response.url };
        }
        return response.json();
    })
    .then(json => {
        console.log('Apple Pay (Product): Parsed JSON', json);
        if (json.status === "success") {
            const redirectUrl = json.redirect_url || 'index.php?main_page=checkout_success';
            console.log('Apple Pay (Product): Payment successful, will redirect to', redirectUrl);
            pendingRedirectUrl = redirectUrl;
            session.completePayment(ApplePaySession.STATUS_SUCCESS);
            setTimeout(function () {
                window.location.href = redirectUrl;
            }, 50);
        } else {
            console.error('Apple Pay (Product): Payment failed', json);
            session.completePayment(ApplePaySession.STATUS_FAILURE);
        }
    })
    .catch(error => {
        console.error('Apple Pay (Product): Error during payment finalization', error);
        session.completePayment(ApplePaySession.STATUS_FAILURE);
    });
}
</script>

<!-- Apple Pay Button Container -->
<div id="apple-pay-button-container"></div>
