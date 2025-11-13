<?php
// the following code is a work in progress. at the current time the braintree api does not support shipping options

require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/braintree_paypal.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree_paypal.php');
$paypalModule = new braintree_paypal();
$clientToken = $paypalModule->generate_client_token();
?>

<script src="https://js.braintreegateway.com/web/3.115.2/js/client.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.115.2/js/paypal-checkout.min.js"></script>

<script>
"use strict";

window.braintreePayPalSessionAppend = window.braintreePayPalSessionAppend || "<?php echo (SESSION_RECREATE === 'True') ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";
let finalTotal = "<?php echo $initialTotal; ?>";
let currentPaymentId = null;
let cachedShippingAddress = null;
let cachedShippingOptionId = null;

function showPayPalButton(paypalCheckoutInstance) {
    const buttonContainer = document.getElementById('paypal-button-container');
    if (!buttonContainer) return;
    buttonContainer.innerHTML = '';

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
        onApprove: function (data, actions) {
            paypalCheckoutInstance.tokenizePayment(data, function (err, payload) {
                if (err || !payload || !payload.nonce) {
                    console.error("PayPal tokenization error:", err);
                    return;
                }

                fetch("ajax/braintree_checkout_handler.php" + window.braintreePayPalSessionAppend, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        payment_method_nonce: payload.nonce,
                        paymentId: data.paymentId,
                        module: "braintree_paypal",
                        currency: "<?php echo $currencyCode; ?>",
                        total: finalTotal,
                        billing_address: payload.details?.billingAddress || {},
                        shipping_address: payload.details?.shippingAddress || {}
                    })
                }).then(response => response.json()).then(json => {
                    if (json.status === "success" && json.redirect_url) {
                        window.location.href = json.redirect_url;
                    } else {
                        alert("Payment failed: " + (json.message || "Unknown error"));
                    }
                }).catch(err => {
                    console.error("AJAX error:", err);
                    alert("An error occurred during processing.");
                });
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
                finalTotal = json?.amount?.value || finalTotal;

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
                }, function (err) {
                    if (err) {
                        console.error("updatePayment error (onShippingChange):", err);
                    }
                });
            })
            .catch(err => {
                console.error("onShippingChange fetch error:", err);
            });
        },
        onCancel: function (data) {
            console.log("PayPal payment cancelled", data);
        },
        onError: function (err) {
            console.error("PayPal error", err);
        }
    }).render('#paypal-button-container');
}

document.addEventListener("DOMContentLoaded", function () {
    braintree.client.create({
        authorization: "<?php echo $clientToken; ?>"
    }).then(clientInstance => {
        return braintree.paypalCheckout.create({ client: clientInstance });
    }).then(paypalCheckoutInstance => {
        paypalCheckoutInstance.loadPayPalSDK({
            currency: "<?php echo $currencyCode; ?>"
        }, function (sdkErr) {
            if (sdkErr) {
                console.error("PayPal SDK load error:", sdkErr);
                return;
            }
            showPayPalButton(paypalCheckoutInstance);
        });
    }).catch(err => {
        console.error("PayPal setup error:", err);
    });
});
</script>

<!-- PayPal Button Container -->
<div id="paypal-button-container"></div>