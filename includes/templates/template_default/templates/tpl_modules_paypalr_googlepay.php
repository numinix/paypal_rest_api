<?php
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_googlepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_googlepay.php');

    $googlePayModule = new paypalr_googlepay();
    $clientToken = $googlePayModule->generate_client_token();

    $jsonString = base64_decode($clientToken);
    $jsonData = json_decode($jsonString, true);
    $authorizationFingerprint = $jsonData['authorizationFingerprint'];

    $googleMerchantId     = MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_MERCHANT_ID;
    $googlePayEnvironment = MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_ENVIRONMENT;
    $use3DS = (defined('MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_USE_3DS') && MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_USE_3DS === 'True');

?>
<script src="https://pay.google.com/gp/p/js/pay.js"></script>
<script src="https://js.braintreegateway.com/web/3.133.0/js/client.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.133.0/js/google-payment.min.js"></script>
<?php if ($use3DS): ?>
<script src="https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js"></script>
<?php endif; ?>

<script>
"use strict";

window.paypalrGooglePaySessionAppend = window.paypalrGooglePaySessionAppend || "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";

// Initialize Braintree Client
braintree.client.create({
    authorization: "<?php echo $clientToken; ?>"
}, function (err, clientInstance) {
    if (err) {
        console.error("Error creating Braintree client:", err);
        return;
    }

    <?php if ($use3DS): ?>
    braintree.threeDSecure.create({ client: clientInstance, version: 2 }, function (err, threeDSInstance) {
        if (err && err.code === 'THREEDS_NOT_ENABLED_FOR_V2') {
            braintree.threeDSecure.create({ client: clientInstance, version: 1 }, function (errV1, fallbackInstance) {
                if (!errV1) window.threeDS = fallbackInstance;
            });
        } else if (!err) {
            window.threeDS = threeDSInstance;
        }
    });
    <?php endif; ?>

    // Initialize Braintree Google Payment Instance
    braintree.googlePayment.create({
        client: clientInstance,
        googlePayVersion: 2,
        googleMerchantId: '<?php echo $googleMerchantId; ?>'
    }, function (err, googlePaymentInstance) {
        if (err) {
            console.error("Error creating Braintree Google Payment instance:", err);
            return;
        }

        const totalPrice = '<?php echo $initialTotal; ?>';
        const errorDiv = document.getElementById('google-pay-error');
        window.googlePayFinalTotal = totalPrice;

        // Define the correct paymentDataRequest
        const paymentDataRequest = googlePaymentInstance.createPaymentDataRequest({
            allowedPaymentMethods: [
                {
                    type: "CARD",
                    parameters: {
                        allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
                        allowedCardNetworks: ["AMEX", "DISCOVER", "MASTERCARD", "VISA"],
                        // Request the billing address
                        billingAddressRequired: true,
                        billingAddressParameters: {
                            format: "FULL",
                            phoneNumberRequired: true
                        },
                    },
                    tokenizationSpecification: googlePaymentInstance.tokenizationSpecification
                }
            ],
            transactionInfo: {
                totalPriceStatus: 'FINAL',
                totalPrice: totalPrice,
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

        // Initialize the PaymentsClient
        const paymentsClient = new google.payments.api.PaymentsClient({
            environment: "<?php echo $googlePayEnvironment; ?>",
            paymentDataCallbacks: {
                onPaymentAuthorized: onPaymentAuthorized,
                onPaymentDataChanged: onPaymentDataChanged
            }
        });

        // Check if Google Pay is Ready to Pay
        paymentsClient.isReadyToPay({
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: paymentDataRequest.allowedPaymentMethods
        }).then(function(response) {
            if (response.result) {
                addGooglePayButton();
            } else {
                console.error('Google Pay is not available.');
            }
        }).catch(function(err) {
            console.error('Error determining readiness to use Google Pay:', err);
        });

        function addGooglePayButton() {
            const button = paymentsClient.createButton({
                onClick: onGooglePaymentButtonClicked
            });
            document.getElementById('google-pay-button-container').appendChild(button);
        }

        function onGooglePaymentButtonClicked() {
            clearGooglePayError(); // Clear any previous errors
            paymentsClient.loadPaymentData(paymentDataRequest)
                .then(function(paymentData) {
                    // The onPaymentAuthorized callback will be automatically triggered.
                    console.log('Payment data loaded successfully.');
                })
                .catch(function(err) {
                    console.error('Error loading payment data:', err);
                });
        }

        function onPaymentAuthorized(paymentData) {
            // Close Google Pay modal right away
            const closeModalPromise = Promise.resolve({ transactionState: 'PENDING' });

            // Immediately begin background processing
            googlePaymentInstance.parseResponse(paymentData, function (err, result) {
                if (err || !result.nonce) {
                    console.error('Google Pay parse error:', err);
                    return;
                }

                const proceedToCheckout = function (nonce) {
                    showGooglePayLoading();

                    fetch('ajax/paypalr_wallet_checkout.php' + window.paypalrGooglePaySessionAppend, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            payment_method_nonce: nonce,
                            module: 'paypalr_googlepay',
                            currency: '<?php echo $currencyCode; ?>',
                            total: window.googlePayFinalTotal,
                            email: paymentData.email,
                            shipping_address: paymentData.shippingAddress,
                            billing_address: paymentData.paymentMethodData.info.billingAddress
                        })
                    })
                    .then(response => {
                        const contentType = response.headers.get('content-type') || '';
                        if (response.redirected && response.url) {
                            return { status: 'success', redirect_url: response.url };
                        }
                        if (contentType.includes('application/json')) {
                            return response.json();
                        }
                        return response.text().then(text => {
                            const message = text && text.trim() ? text.trim() : 'Unexpected response from checkout handler.';
                            throw new Error(message);
                        });
                    })
                    .then(json => {
                        hideGooglePayLoading();
                        if (json.status === 'success') {
                            window.location.href = json.redirect_url;
                        } else {
                            if (errorDiv) {
                                errorDiv.innerText = json.message;
                                errorDiv.style.display = 'block';
                            }
                            console.error('Checkout failed:', json);
                        }
                    })
                    .catch(error => {
                        hideGooglePayLoading();
                        const errorMessage = error && error.message ? error.message : error;
                        if (errorDiv) {
                            errorDiv.innerText = errorMessage;
                            errorDiv.style.display = 'block';
                        }
                        console.error('Checkout AJAX error:', error);
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
                        console.error("3DS verification failed:", err);

                        let message = "Your card could not be verified. Please try another payment method.";
                        if (err && err.message) {
                            // You can customize based on error codes if needed
                            message += "\\nReason: " + err.message;
                        }

                        if (errorDiv) {
                            errorDiv.innerText = message;
                            errorDiv.style.display = 'block';
                        }
                    });
                } else {
                    proceedToCheckout(result.nonce);
                }
                <?php else: ?>
                proceedToCheckout(result.nonce);
                <?php endif; ?>
            });

            // Return immediately to Google Pay to close the modal
            return closeModalPromise;
        }

        function clearGooglePayError() {
            const errorBox = document.getElementById('google-pay-error');
            if (errorBox) {
                errorBox.style.display = 'none';
                errorBox.innerText = '';
            }
        }

        function getGoogleShippingOptions(shippingAddress) {
            return fetch('ajax/paypalr_wallet.php' + window.paypalrGooglePaySessionAppend, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shippingAddress: shippingAddress, module: 'paypalr_googlepay' })
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
            return fetch('ajax/paypalr_wallet.php' + window.paypalrGooglePaySessionAppend, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ shippingAddress: shippingAddress, selectedShippingOptionId: selectedShippingOptionId, module: 'paypalr_googlepay' })
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

            clearGooglePayError(); // Clear any previous errors

            switch (intermediatePaymentData.callbackTrigger) {
                case "INITIALIZE":
                case "SHIPPING_ADDRESS":
                    return getGoogleShippingOptions(shippingAddress)
                        .then(shippingOptions => {
                            const selectedShippingOptionId = shippingOptions.defaultSelectedOptionId;
                            return calculateNewTransactionInfo(selectedShippingOptionId, shippingAddress)
                                .then(transactionInfo => {
                                    // Update the 3DS amount to match the new cart total
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

                case "SHIPPING_OPTION":
                    const selectedOptionId = intermediatePaymentData.shippingOptionData.id;
                    return calculateNewTransactionInfo(selectedOptionId, shippingAddress)
                        .then(transactionInfo => {
                            // Update total again if shipping option changes
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
    });
});
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