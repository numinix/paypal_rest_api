<?php
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/braintree_googlepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree_googlepay.php');
    $braintreeSdkVersion = Braintree\Version::get();

    $googlePayModule = new braintree_googlepay();
    $clientToken = $googlePayModule->generate_client_token();

    $googleMerchantId     = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID;
    $googlePayEnvironment = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT;
    $currencyCode         = $_SESSION['currency'];
    $initialTotal         = number_format($currencies->value(zen_get_products_base_price((int)$_GET['products_id'])), 2, '.', '');
    if (method_exists($googlePayModule, 'isThreeDSecureEnabled')) {
        $use3DS = $googlePayModule->isThreeDSecureEnabled();
    } else {
        $use3DS = (defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS === 'True');
    }
?>
<script>
(function () {
    "use strict";

    window.googlePayScriptsLoaded = window.googlePayScriptsLoaded || false;
    let googlePayRetryAttempts = 0;
    const MAX_RETRY_ATTEMPTS = 3;

    // Detect iOS Chrome (CriOS) - needed for sequential loading
    const ua = navigator.userAgent || "";
    const isIOSChrome = /CriOS/.test(ua);

    function loadGooglePayScripts() {
        if (window.googlePayScriptsLoaded) {
            console.log('Google Pay (Product): Required scripts already loaded');
            return Promise.resolve();
        }

        const scripts = [
            "https://pay.google.com/gp/p/js/pay.js",
            "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
            "https://js.braintreegateway.com/web/3.133.0/js/google-payment.min.js"
        ];

        <?php if ($use3DS): ?>
        scripts.push("https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js");
        <?php else: ?>
        console.log('Google Pay (Product): 3DS disabled via module settings; skipping 3DS resources');
        delete window.threeDS;
        <?php endif; ?>

        const loadScript = function (src) {
            return new Promise(function (resolve, reject) {
                const selector = 'script[src="' + src.replace(/"/g, '\\"') + '"]';
                const existing = document.querySelector(selector);
                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        console.log('Google Pay (Product): Script already loaded', src);
                        resolve();
                        return;
                    }
                    // Script exists but may already be loaded (e.g., from cache on page refresh)
                    // Check if it's already loaded or still loading
                    if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                        existing.dataset.loaded = 'true';
                        console.log('Google Pay (Product): Script already loaded (from cache)', src);
                        resolve();
                        return;
                    }
                    existing.addEventListener('load', function () {
                        existing.dataset.loaded = 'true';
                        console.log('Google Pay (Product): Script loaded', src);
                        resolve();
                    });
                    existing.addEventListener('error', function () {
                        console.warn('Google Pay (Product): Failed to load script', src);
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
                    console.log('Google Pay (Product): Loaded script', src);
                    resolve();
                });
                script.addEventListener('error', function () {
                    console.warn('Google Pay (Product): Failed to load script', src);
                    reject(new Error("Failed to load script: " + src));
                });
                document.head.appendChild(script);
            });
        };

        console.log('Google Pay (Product): Loading resources', scripts);
        console.log('Google Pay (Product): Browser detection - iOS Chrome:', isIOSChrome);

        // iOS Chrome requires sequential loading to avoid race conditions and initialization issues
        // All other browsers work fine with parallel loading (faster)
        if (isIOSChrome) {
            console.log('Google Pay (Product): Using sequential loading for iOS Chrome');
            return scripts.reduce(function (promise, src) {
                return promise.then(function () {
                    return loadScript(src);
                });
            }, Promise.resolve()).then(function () {
                window.googlePayScriptsLoaded = true;
            });
        } else {
            console.log('Google Pay (Product): Using parallel loading for non-iOS Chrome browsers');
            return Promise.all(scripts.map(function (src) {
                return loadScript(src);
            })).then(function () {
                window.googlePayScriptsLoaded = true;
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

    function hasRequiredBrowserApis() {
        return (typeof window.Promise !== 'undefined') && (typeof window.fetch === 'function');
    }

    function showGooglePayUnsupportedMessage() {
        var errorBox = document.getElementById('google-pay-error');
        if (errorBox) {
            errorBox.innerText = 'Google Pay is not available in this browser. Please choose another payment method.';
            errorBox.style.display = 'block';
        }
    }

    if (!hasRequiredBrowserApis()) {
        onDomReady(showGooglePayUnsupportedMessage);
        return;
    }

    function initializeGooglePay() {
        braintree.client.create({
            authorization: "<?php echo $clientToken; ?>"
        }).then(function (clientInstance) {
            <?php if ($use3DS): ?>
            braintree.threeDSecure.create({ client: clientInstance, version: 2 })
                .then(function (threeDSInstance) {
                    window.threeDS = threeDSInstance;
                })
                .catch(function (err) {
                    if (err && err.code === 'THREEDS_NOT_ENABLED_FOR_V2') {
                        return braintree.threeDSecure.create({ client: clientInstance, version: 1 })
                            .then(function (fallbackInstance) {
                                window.threeDS = fallbackInstance;
                            })
                            .catch(function (errV1) {
                                console.error('3DS fallback error:', errV1);
                            });
                    } else {
                        console.error('3DS error:', err);
                    }
                });
            <?php endif; ?>

            return braintree.googlePayment.create({
                client: clientInstance,
                googlePayVersion: 2,
                googleMerchantId: '<?php echo $googleMerchantId; ?>'
            });
        }).then(function (googlePaymentInstance) {
            const totalPrice = '<?php echo $initialTotal; ?>';
            window.googlePayFinalTotal = totalPrice;

                const paymentDataRequest = googlePaymentInstance.createPaymentDataRequest({
                    allowedPaymentMethods: [
                        {
                            type: "CARD",
                            parameters: {
                                allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
                                allowedCardNetworks: ["AMEX", "DISCOVER", "MASTERCARD", "VISA"],
                                billingAddressRequired: true,
                                billingAddressParameters: {
                                    format: "FULL",
                                    phoneNumberRequired: true
                                }
                            },
                            tokenizationSpecification: googlePaymentInstance.tokenizationSpecification
                        }
                    ],
                    transactionInfo: {
                        totalPriceStatus: 'FINAL',
                        totalPrice: '<?php echo $initialTotal; ?>',
                        currencyCode: '<?php echo $currencyCode; ?>',
                        countryCode: '<?php echo $storeCountryCode; ?>'
                    },
                    merchantInfo: {
                        merchantId: '<?php echo $googleMerchantId; ?>',
                        merchantName: '<?php echo STORE_NAME; ?>'
                    },
                    emailRequired: true,
                    shippingAddressRequired: true,
                    shippingOptionRequired: true,
                    callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION', 'PAYMENT_AUTHORIZATION']
                });

                const paymentsClient = new google.payments.api.PaymentsClient({
                    environment: "<?php echo $googlePayEnvironment; ?>",
                    paymentDataCallbacks: {
                        onPaymentAuthorized: onPaymentAuthorized,
                        onPaymentDataChanged: onPaymentDataChanged
                    }
                });

                paymentsClient.isReadyToPay({
                    apiVersion: 2,
                    apiVersionMinor: 0,
                    allowedPaymentMethods: paymentDataRequest.allowedPaymentMethods
                }).then(function (response) {
                    if (response.result) {
                        addGooglePayButton();
                    } else {
                        console.error('Google Pay is not available.');
                    }
                }).catch(function (err) {
                    console.error('Error determining readiness to use Google Pay:', err);
                });

                function addGooglePayButton() {
                    const button = paymentsClient.createButton({
                        onClick: onGooglePaymentButtonClicked
                    });
                    document.getElementById('google-pay-button-container').appendChild(button);
                }

                function onGooglePaymentButtonClicked() {
                    clearGooglePayError();
                    paymentsClient.loadPaymentData(paymentDataRequest)
                        .then(function (paymentData) {
                            console.log('Payment data loaded successfully.');
                        })
                        .catch(function (err) {
                            console.error('Error loading payment data:', err);
                        });
                }

                function onPaymentAuthorized(paymentData) {
                    const closeModalPromise = Promise.resolve({ transactionState: 'PENDING' });

                    googlePaymentInstance.parseResponse(paymentData)
                        .then(function (result) {
                            if (!result.nonce) {
                                console.error('Error parsing Google Pay response: no nonce');
                                return;
                            }

                const proceedToCheckout = function (nonce) {
                    showGooglePayLoading();
                    fetchJSON('ajax/braintree_checkout_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            payment_method_nonce: nonce,
                            module: 'braintree_googlepay',
                            currency: '<?php echo $currencyCode; ?>',
                            total: window.googlePayFinalTotal,
                            email: paymentData.email,
                            shipping_address: paymentData.shippingAddress,
                            billing_address: paymentData.paymentMethodData.info.billingAddress
                        })
                    })
                    .then(json => {
                        hideGooglePayLoading();

                                        if (json.status === 'success') {
                                            window.location.href = json.redirect_url;
                                        } else {
                                            const errorDiv = document.getElementById('google-pay-error');
                                            if (errorDiv) {
                                                errorDiv.innerText = json.message || 'There was an error processing your payment.';
                                                errorDiv.style.display = 'block';
                                            }
                                        }
                                    })
                                    .catch(error => {
                                        hideGooglePayLoading();
                                        console.error('Checkout request error:', error);
                                    });
                            };
                            showGooglePayLoading();
                            <?php if ($use3DS): ?>
                            if (window.threeDS && typeof window.threeDS.verifyCard === 'function' && !window.threeDS._destroyed) {
                                window.threeDS.verifyCard({
                                    amount: window.googlePayFinalTotal,
                                    nonce: result.nonce,
                                    bin: result.details.bin,
                                    email: '<?php echo addslashes($order->customer['email_address']); ?>',
                                    billingAddress: {
                                        givenName: '<?php echo addslashes($order->billing['firstname']); ?>',
                                        surname: '<?php echo addslashes($order->billing['lastname']); ?>',
                                        phoneNumber: '<?php echo addslashes($order->customer['telephone']); ?>',
                                        streetAddress: '<?php echo addslashes($order->billing['street_address']); ?>',
                                        locality: '<?php echo addslashes($order->billing['city']); ?>',
                                        region: '<?php echo addslashes($order->billing['state']); ?>',
                                        postalCode: '<?php echo addslashes($order->billing['postcode']); ?>',
                                        countryCodeAlpha2: '<?php echo addslashes($order->billing['country']['iso_code_2']); ?>'
                                    },
                                    onLookupComplete: function (data, next) { next(); }
                                }).then(function (verification) {
                                    hideGooglePayLoading();
                                    if (verification && verification.nonce) {
                                        proceedToCheckout(verification.nonce);
                                    }
                                }).catch(function (err) {
                                    hideGooglePayLoading();
                                    console.error('3DS error:', err);
                                    const errorDiv = document.getElementById('google-pay-error');
                                    if (errorDiv) {
                                        errorDiv.innerText = 'Your card could not be verified. Please try another payment method.';
                                        errorDiv.style.display = 'block';
                                    }
                                });
                            } else {
                                proceedToCheckout(result.nonce);
                            }
                            <?php else: ?>
                            proceedToCheckout(result.nonce);
                            <?php endif; ?>
                        })
                        .catch(function (err) {
                            console.error('Error parsing Google Pay response:', err);
                        });

                    return closeModalPromise;
                }

        function getGoogleShippingOptions(shippingAddress) {
            return fetch('ajax/braintree.php' + window.braintreeGooglePaySessionAppend, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shippingAddress: shippingAddress, module: 'braintree_googlepay' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.newShippingOptionParameters && Array.isArray(data.newShippingOptionParameters.shippingOptions)) {
                    return data.newShippingOptionParameters;
                } else {
                    throw new Error("Invalid shipping options format");
                }
            })
            .catch(err => {
                console.error("Error fetching shipping options:", err);
                throw err;
            });
        }

        function calculateNewTransactionInfo(selectedShippingOptionId, shippingAddress) {
            return fetch('ajax/braintree.php' + window.braintreeGooglePaySessionAppend, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shippingAddress: shippingAddress, selectedShippingOptionId: selectedShippingOptionId, module: 'braintree_googlepay' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.newTransactionInfo) {
                    return data.newTransactionInfo;
                } else {
                    throw new Error("No transaction info returned");
                }
            })
            .catch(err => {
                console.error("Error fetching transaction info:", err);
                throw err;
            });
        }

                function onPaymentDataChanged(intermediatePaymentData) {
                    const shippingAddress = intermediatePaymentData.shippingAddress;
                    clearGooglePayError();
                    switch (intermediatePaymentData.callbackTrigger) {
                        case "INITIALIZE":
                            if (!window.productCartAdded) {
                                window.productCartAdded = true;
                                return clearCartAndAddProduct()
                                    .then(() => {
                                        console.log("Cart cleared and product added during INITIALIZE callback.");
                                        return getGoogleShippingOptions(shippingAddress)
                                            .then(shippingOptions => {
                                                const selectedShippingOptionId = shippingOptions.defaultSelectedOptionId;
                                                return calculateNewTransactionInfo(selectedShippingOptionId, shippingAddress)
                                                    .then(transactionInfo => ({
                                                        newShippingOptionParameters: shippingOptions,
                                                        newTransactionInfo: transactionInfo
                                                    }));
                                            })
                                            .catch(err => {
                                                console.error("Error in onPaymentDataChanged (SHIPPING_ADDRESS) after INITIALIZE:", err);
                                                return {
                                                    error: {
                                                        reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                                        message: 'Cannot ship to the selected address.',
                                                        intent: 'SHIPPING_ADDRESS'
                                                    }
                                                };
                                            });
                                    })
                                    .catch(err => {
                                        console.error("Error during INITIALIZE clearCartAndAddProduct:", err);
                                        return getGoogleShippingOptions(shippingAddress)
                                            .then(shippingOptions => {
                                                const selectedShippingOptionId = shippingOptions.defaultSelectedOptionId;
                                                return calculateNewTransactionInfo(selectedShippingOptionId, shippingAddress)
                                                    .then(transactionInfo => ({
                                                        newShippingOptionParameters: shippingOptions,
                                                        newTransactionInfo: transactionInfo
                                                    }));
                                            })
                                            .catch(err => {
                                                console.error("Error in onPaymentDataChanged (SHIPPING_ADDRESS) after INITIALIZE error:", err);
                                                return {
                                                    error: {
                                                        reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                                        message: 'Cannot ship to the selected address.',
                                                        intent: 'SHIPPING_ADDRESS'
                                                    }
                                                };
                                            });
                                    });
                            }
                            break;
                        case "SHIPPING_ADDRESS":
                            return getGoogleShippingOptions(shippingAddress)
                                .then(shippingOptions => {
                                    const selectedShippingOptionId = shippingOptions.defaultSelectedOptionId;
                                    return calculateNewTransactionInfo(selectedShippingOptionId, shippingAddress)
                                        .then(transactionInfo => {
                                            window.googlePayFinalTotal = transactionInfo.totalPrice;

                                            return {
                                                newShippingOptionParameters: shippingOptions,
                                                newTransactionInfo: transactionInfo
                                            };
                                        });
                                })
                                .catch(err => {
                                    console.error("Error in onPaymentDataChanged (SHIPPING_ADDRESS):", err);
                                    return {
                                        error: {
                                            reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                            message: 'Cannot ship to the selected address.',
                                            intent: 'SHIPPING_ADDRESS'
                                        }
                                    };
                                });
                            break;
                        case "SHIPPING_OPTION":
                            const selectedOptionId = intermediatePaymentData.shippingOptionData.id;
                            return calculateNewTransactionInfo(selectedOptionId, shippingAddress)
                                .then(transactionInfo => {
                                    window.googlePayFinalTotal = transactionInfo.totalPrice;

                                    return {
                                        newTransactionInfo: transactionInfo
                                    };
                                })
                                .catch(err => {
                                    console.error("Error in onPaymentDataChanged (SHIPPING_OPTION):", err);
                                    return {
                                        error: {
                                            reason: 'SHIPPING_OPTION_INVALID',
                                            message: 'Invalid shipping option selected.',
                                            intent: 'SHIPPING_OPTION'
                                        }
                                    };
                                });
                        default:
                            return Promise.resolve({});
                    }
                }

        // Helper function to fetch and attempt to parse JSON.
        // If the response appears to be HTML (e.g. a redirect), it returns { success: true }.
        function fetchJSON(url, options) {
            return fetch(url, options)
                .then(response => response.text())
                .then(text => {
                    text = (text || '').trim();
                    if (!text) {
                        console.log('Empty response, assuming success.');
                        return { success: true };
                    }
                    if (text.charAt(0) === '<') {
                        console.log('Received HTML response, assuming success.');
                        return { success: true };
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Error parsing JSON:', e, text);
                        return { success: true };
                    }
                });
        }

                function clearCartAndAddProduct() {
                    return fetchJSON('ajax/braintree_clear_cart.php', {
                        method: 'POST',
                        redirect: 'manual'
                    })
                        .then(data => {
                            console.log('Cart clear result:', data.success ? 'Cleared' : 'Not cleared');
                            return true;
                        })
                        .catch(() => {
                            console.log('Cart reset skipped. Proceeding...');
                            return true;
                        })
                        .then(() => {
                            var form = document.forms['cart_quantity'];
                            if (!form) {
                                console.error('Add-to-cart form not found.');
                                return Promise.reject('Add-to-cart form missing');
                            }
                            var formData = new FormData(form);
                            return fetchJSON(form.action, {
                                method: 'POST',
                                body: formData,
                                redirect: 'manual'
                            });
                        })
                        .then(data => {
                            if (data.success) {
                                console.log('Product added to cart successfully.');
                                return data;
                            } else {
                                throw new Error('Add-to-cart failed.');
                            }
                        });
                }
        }).catch(function (err) {
            console.error("Error initializing Google Pay:", err);
        });
    }

    function attemptGooglePayInitialization() {
        loadGooglePayScripts()
            .then(function () {
                onDomReady(initializeGooglePay);
                googlePayRetryAttempts = 0; // Reset on success
            })
            .catch(function (error) {
                console.error('Google Pay (Product): Failed to load scripts:', error);
                googlePayRetryAttempts++;
                
                if (googlePayRetryAttempts < MAX_RETRY_ATTEMPTS) {
                    const delay = Math.min(1000 * Math.pow(2, googlePayRetryAttempts - 1), 5000);
                    console.log('Google Pay (Product): Retrying in ' + delay + 'ms (attempt ' + googlePayRetryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
                    setTimeout(attemptGooglePayInitialization, delay);
                } else {
                    // Fail silently after all retries on cart/product pages
                    console.warn('Google Pay (Product): Initialization failed after ' + MAX_RETRY_ATTEMPTS + ' attempts. Button will not be displayed.');
                }
            });
    }

    attemptGooglePayInitialization();
})();

function clearGooglePayError() {
    const errorBox = document.getElementById('google-pay-error');
    if (errorBox) {
        errorBox.style.display = 'none';
        errorBox.innerText = '';
    }
}

function showGooglePayLoading() {
    document.body.classList.add('processing-payment');
}

function hideGooglePayLoading() {
    document.body.classList.remove('processing-payment');
}
</script>

<!-- Google Pay Button Container -->
<div id="google-pay-button-container"></div>
<div id="google-pay-error" style="display:none; color:red; margin-top:10px;"></div>


<style>
body.processing-payment {
    cursor: wait;
}
</style>