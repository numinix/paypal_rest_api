<?php
/**
 * tpl_modules_paypalr_venmo.php
 * Venmo button template for shopping cart page
 * 
 * Venmo uses PayPal Buttons SDK with VENMO funding source.
 * This is different from Google Pay/Apple Pay which use separate SDKs.
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_venmo.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_venmo.php');

    $venmoModule = new paypalr_venmo();
    
    // Get wallet configuration
    $walletConfig = $venmoModule->ajaxGetWalletConfig();
    if (empty($walletConfig['success']) || empty($walletConfig['client_id'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    $clientId = $walletConfig['client_id'];
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $environment = $walletConfig['environment'] ?? 'sandbox';
    $initialTotal = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
    
    // Build SDK URL
    // Note: As of 2025, PayPal SDK no longer accepts intent parameter
    // Intent is specified when creating the order, not when loading the SDK
    $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
    $sdkUrl .= '&components=buttons,googlepay,applepay,venmo';
    $sdkUrl .= '&enable-funding=venmo';
    $sdkUrl .= '&currency=' . urlencode($currency);
    
    // Add buyer-country for sandbox mode (required for testing)
    if ($environment === 'sandbox') {
        $sdkUrl .= '&buyer-country=US';
    }
?>

<script src="<?php echo $sdkUrl; ?>"></script>

<script>
"use strict";

window.paypalrVenmoSessionAppend = window.paypalrVenmoSessionAppend || "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>";

(function() {
    // Check if Venmo is eligible
    if (!window.paypal || !window.paypal.Buttons) {
        console.error('PayPal SDK not loaded for Venmo');
        return;
    }

    const venmoContainer = document.getElementById('venmo-button-container');
    const errorDiv = document.getElementById('venmo-error');
    
    if (!venmoContainer) {
        console.error('Venmo button container not found');
        return;
    }

    function showVenmoLoading() {
        if (venmoContainer) {
            venmoContainer.style.opacity = '0.5';
            venmoContainer.style.pointerEvents = 'none';
        }
        document.body.classList.add('processing-payment');
    }

    function hideVenmoLoading() {
        if (venmoContainer) {
            venmoContainer.style.opacity = '1';
            venmoContainer.style.pointerEvents = 'auto';
        }
        document.body.classList.remove('processing-payment');
    }

    function clearVenmoError() {
        if (errorDiv) {
            errorDiv.style.display = 'none';
            errorDiv.innerText = '';
        }
    }

    function showVenmoError(message) {
        if (errorDiv) {
            errorDiv.innerText = message || 'An error occurred with Venmo payment';
            errorDiv.style.display = 'block';
        }
        console.error('Venmo error:', message);
    }

    // Create PayPal Buttons with Venmo funding source
    const venmoButtons = paypal.Buttons({
        fundingSource: paypal.FUNDING.VENMO,
        
        style: {
            layout: 'vertical',
            color: 'blue',
            shape: 'rect',
            label: 'pay'
        },

        createOrder: function() {
            clearVenmoError();
            showVenmoLoading();
            
            return fetch('ajax/paypalr_wallet.php' + window.paypalrVenmoSessionAppend, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    module: 'paypalr_venmo',
                    action: 'create_order'
                })
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                hideVenmoLoading();
                if (data.success && data.order_id) {
                    return data.order_id;
                } else {
                    throw new Error(data.message || 'Failed to create Venmo order');
                }
            })
            .catch(function(error) {
                hideVenmoLoading();
                showVenmoError(error.message || 'Failed to create order');
                throw error;
            });
        },

        onApprove: function(data) {
            clearVenmoError();
            showVenmoLoading();
            
            return fetch('ajax/paypalr_wallet_checkout.php' + window.paypalrVenmoSessionAppend, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order_id: data.orderID,
                    module: 'paypalr_venmo',
                    payment_method_nonce: data.orderID,
                    total: '<?php echo $initialTotal; ?>'
                })
            })
            .then(function(response) {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                return response.text().then(function(text) {
                    const message = text && text.trim() ? text.trim() : 'Unexpected response from checkout handler.';
                    throw new Error(message);
                });
            })
            .then(function(json) {
                hideVenmoLoading();
                if (json.status === 'success') {
                    // Clear cart
                    fetch('ajax/paypalr_wallet_clear_cart.php' + window.paypalrVenmoSessionAppend, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ module: 'paypalr_venmo' })
                    }).finally(function() {
                        window.location.href = json.redirect_url;
                    });
                } else {
                    showVenmoError(json.message || 'Checkout failed');
                }
            })
            .catch(function(error) {
                hideVenmoLoading();
                showVenmoError(error.message || 'Checkout error');
            });
        },

        onCancel: function(data) {
            hideVenmoLoading();
            clearVenmoError();
            console.log('Venmo payment cancelled', data);
        },

        onError: function(err) {
            hideVenmoLoading();
            showVenmoError('Venmo payment error: ' + (err.message || 'Unknown error'));
            console.error('Venmo button error:', err);
        }
    });

    // Check if Venmo is eligible before rendering
    if (venmoButtons.isEligible()) {
        venmoButtons.render('#venmo-button-container')
            .then(function() {
                console.log('Venmo button rendered successfully');
            })
            .catch(function(err) {
                console.error('Failed to render Venmo button:', err);
                // Hide the container if rendering fails
                if (venmoContainer) {
                    venmoContainer.style.display = 'none';
                }
            });
    } else {
        console.log('Venmo is not eligible for this transaction');
        // Hide the container if not eligible
        if (venmoContainer) {
            venmoContainer.style.display = 'none';
        }
    }
})();
</script>

<!-- Venmo Button Container -->
<div id="venmo-button-container"></div>
<div id="venmo-error" style="display:none; color:red; margin-top:10px;"></div>

<style>
body.processing-payment {
    cursor: wait;
}
#venmo-button-container {
    min-height: 50px;
}
</style>
