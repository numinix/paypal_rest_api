<?php
// the following code is a work in progress. at the current time the braintree api does not support shipping options

require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/braintree_paypal.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree_paypal.php');
$paypalModule = new braintree_paypal();
$clientToken = $paypalModule->generate_client_token();
?>

<script src="https://js.braintreegateway.com/web/3.133.0/js/client.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.133.0/js/paypal-checkout.min.js"></script>

<script>
(function () {
    "use strict";

    window.braintreePayPalSessionAppend = window.braintreePayPalSessionAppend || "<?php echo (SESSION_RECREATE === 'True') ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";

    let finalTotal = "<?php echo $initialTotal; ?>";
    let currentPaymentId = null;
    let cachedShippingAddress = null;
    let cachedShippingOptionId = null;

    function hasRequiredBrowserApis() {
        return (typeof window.Promise !== 'undefined') && (typeof window.fetch === 'function');
    }

    function showPayPalError(message) {
        const errorBox = document.getElementById('paypal-error');
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        } else {
            alert(message);
        }
    }

    function hidePayPalError() {
        const errorBox = document.getElementById('paypal-error');
        if (errorBox) {
            errorBox.style.display = 'none';
            errorBox.textContent = '';
        }
    }

    function showPayPalButton(paypalCheckoutInstance) {
        const buttonContainer = document.getElementById('paypal-button-container');
        if (!buttonContainer) return;
        buttonContainer.innerHTML = '';
        hidePayPalError();

        paypal.Buttons({
            createOrder: function () {
                return paypalCheckoutInstance.createPayment({
                    flow: 'checkout',
                    amount: finalTotal,
                    currency: "<?php echo $currencyCode; ?>",
                    enableBillingAddress: true,
                    enableShippingAddress: true,
                    shippingAddressEditable: true
                }).then(function (paymentId) {
                    currentPaymentId = paymentId;
                    return paymentId;
                });
            },
            onApprove: function (data) {
                paypalCheckoutInstance.tokenizePayment(data)
                    .then(function (payload) {
                        if (!payload || !payload.nonce) {
                            console.error("PayPal tokenization error: no payload or nonce");
                            showPayPalError('Unable to tokenize the PayPal payment. Please try another method.');
                            return;
                        }

                        const details = payload.details || {};
                        const billingAddress = details.billingAddress || {};
                        const shippingAddress = details.shippingAddress || {};

                        fetch("ajax/braintree_checkout_handler.php" + window.braintreePayPalSessionAppend, {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                payment_method_nonce: payload.nonce,
                                paymentId: data.paymentId,
                                module: "braintree_paypal",
                                currency: "<?php echo $currencyCode; ?>",
                                total: finalTotal,
                                billing_address: billingAddress,
                                shipping_address: shippingAddress
                            })
                        }).then(response => response.json()).then(json => {
                            if (json.status === "success" && json.redirect_url) {
                                window.location.href = json.redirect_url;
                            } else {
                                const message = json && json.message ? json.message : 'There was an error processing your payment.';
                                showPayPalError(message);
                            }
                        }).catch(err => {
                            console.error("AJAX error:", err);
                            showPayPalError('There was an error processing your payment. Please try again.');
                        });
                    })
                    .catch(function (err) {
                        console.error("PayPal tokenization error:", err);
                        showPayPalError('Unable to tokenize the PayPal payment. Please try another method.');
                    });
            },
            onShippingChange: function (data) {
                cachedShippingAddress = data.shipping_address || cachedShippingAddress;
                cachedShippingOptionId = data.selected_shipping_option || cachedShippingOptionId;

                return fetch("ajax/braintree.php" + window.braintreePayPalSessionAppend, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        shippingAddress: cachedShippingAddress,
                        selectedShippingOptionId: cachedShippingOptionId,
                        module: "braintree_paypal"
                    })
                })
                .then(response => response.json())
                .then(json => {
                    if (json && json.amount && json.amount.value) {
                        finalTotal = json.amount.value;
                    }

                    const shippingOptions = (json.shipping_options || []).map((opt, idx) => ({
                        id: opt.id,
                        type: 'SHIPPING',
                        label: opt.label,
                        selected: idx === 0 || opt.id === cachedShippingOptionId,
                        amount: {
                            currency: opt.amount.currency_code,
                            value: opt.amount.value
                        }
                    }));

                    return paypalCheckoutInstance.updatePayment({
                        paymentId: data.paymentId,
                        amount: finalTotal,
                        currency: "<?php echo $currencyCode; ?>",
                        shippingOptions: shippingOptions
                    })
                        .then(function () {
                            // Payment update successful
                        })
                        .catch(function (err) {
                            console.error("updatePayment error (onShippingChange):", err);
                        });
                })
                .catch(err => {
                    console.error("onShippingChange fetch error:", err);
                    showPayPalError('We could not update shipping for your PayPal payment.');
                });
            },
            onCancel: function (data) {
                console.log("PayPal payment cancelled", data);
            },
            onError: function (err) {
                console.error("PayPal error", err);
                showPayPalError('PayPal encountered an error. Please try again later.');
            }
        }).render('#paypal-button-container');
    }

    if (!hasRequiredBrowserApis()) {
        document.addEventListener('DOMContentLoaded', function () {
            showPayPalError('PayPal checkout is not available in this browser. Please choose another payment method.');
        });
        return;
    }

    document.addEventListener("DOMContentLoaded", function () {
        braintree.client.create({
            authorization: "<?php echo $clientToken; ?>"
        }).then(function (clientInstance) {
            return braintree.paypalCheckout.create({ client: clientInstance });
        }).then(function (paypalCheckoutInstance) {
            return paypalCheckoutInstance.loadPayPalSDK({
                currency: "<?php echo $currencyCode; ?>"
            })
                .then(function () {
                    showPayPalButton(paypalCheckoutInstance);
                });
        }).catch(function (err) {
            console.error("PayPal setup error:", err);
            showPayPalError('PayPal could not be initialized. Please try again later.');
        });
    });
})();
</script>

<!-- PayPal Button Container -->
<div id="paypal-button-container"></div>
<div id="paypal-error" style="display:none; color:red; margin-top:10px;"></div>