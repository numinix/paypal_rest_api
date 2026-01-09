/**
 * PayPal Google Pay Integration - Native Google Pay API
 *
 * This module implements native Google Pay integration using google.payments.api
 * to retrieve user email addresses, then processes payment through PayPal REST API.
 * Based on the Braintree reference implementation pattern.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    var paymentsClient = null;
    var googlePayFinalTotal = null;

    /**
     * Initialize Google Pay button
     */
    window.initPayPalRGooglePay = function() {
        if (typeof google === 'undefined' || !google.payments || !google.payments.api) {
            console.log('[Google Pay] Google Pay API not loaded');
            hideGooglePayContainer();
            return;
        }

        var config = window.paypalrGooglePayConfig || {};
        googlePayFinalTotal = config.initialTotal || '0.00';

        // Initialize Google Payments Client
        paymentsClient = new google.payments.api.PaymentsClient({
            environment: config.environment || 'TEST',
            paymentDataCallbacks: {
                onPaymentAuthorized: onPaymentAuthorized,
                onPaymentDataChanged: onPaymentDataChanged
            }
        });

        // Check if Google Pay is ready
        var isReadyToPayRequest = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [{
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                    allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
                }
            }]
        };

        paymentsClient.isReadyToPay(isReadyToPayRequest)
            .then(function(response) {
                if (response.result) {
                    renderGooglePayButton();
                } else {
                    console.log('[Google Pay] Not available');
                    hideGooglePayContainer();
                }
            })
            .catch(function(error) {
                console.error('[Google Pay] Error checking readiness:', error);
                hideGooglePayContainer();
            });
    };

    /**
     * Render the Google Pay button
     */
    function renderGooglePayButton() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (!container) {
            console.warn('[Google Pay] Button container not found');
            return;
        }

        container.innerHTML = '';

        var button = paymentsClient.createButton({
            onClick: handleGooglePayButtonClick,
            buttonSizeMode: 'fill'
        });

        container.appendChild(button);
        console.log('[Google Pay] Button rendered');
    }

    /**
     * Handle Google Pay button click
     */
    function handleGooglePayButtonClick() {
        var config = window.paypalrGooglePayConfig || {};

        var paymentDataRequest = {
            apiVersion: 2,
            apiVersionMinor: 0,
            allowedPaymentMethods: [{
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                    allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA'],
                    billingAddressRequired: true,
                    billingAddressParameters: {
                        format: 'FULL',
                        phoneNumberRequired: true
                    }
                },
                tokenizationSpecification: {
                    type: 'PAYMENT_GATEWAY',
                    parameters: {
                        gateway: 'paypal',
                        gatewayMerchantId: config.googleMerchantId || ''
                    }
                }
            }],
            merchantInfo: {
                merchantId: config.googleMerchantId || '',
                merchantName: config.storeName || 'Store'
            },
            transactionInfo: {
                totalPriceStatus: 'FINAL',
                totalPrice: googlePayFinalTotal,
                currencyCode: config.currencyCode || 'USD',
                countryCode: config.storeCountryCode || 'US'
            },
            emailRequired: true,
            shippingAddressRequired: true,
            shippingOptionRequired: true,
            callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION', 'PAYMENT_AUTHORIZATION']
        };

        paymentsClient.loadPaymentData(paymentDataRequest)
            .then(function(paymentData) {
                console.log('[Google Pay] Payment data loaded');
            })
            .catch(function(error) {
                console.error('[Google Pay] Error loading payment data:', error);
            });
    }

    /**
     * Handle payment data changes (shipping address/option selection)
     */
    function onPaymentDataChanged(intermediatePaymentData) {
        var config = window.paypalrGooglePayConfig || {};
        var shippingAddress = intermediatePaymentData.shippingAddress;

        clearError();

        switch (intermediatePaymentData.callbackTrigger) {
            case 'INITIALIZE':
            case 'SHIPPING_ADDRESS':
                if (!shippingAddress) {
                    return Promise.resolve({});
                }

                return fetchShippingOptions(shippingAddress, null).then(function(data) {
                    var shippingOptions = data.newShippingOptionParameters;
                    var transactionInfo = data.newTransactionInfo;

                    if (transactionInfo && transactionInfo.totalPrice) {
                        googlePayFinalTotal = transactionInfo.totalPrice;
                    }

                    return {
                        newShippingOptionParameters: shippingOptions,
                        newTransactionInfo: transactionInfo
                    };
                }).catch(function(error) {
                    console.error('[Google Pay] Error in address callback:', error);
                    return {
                        error: {
                            reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                            message: 'Cannot ship to the selected address.',
                            intent: 'SHIPPING_ADDRESS'
                        }
                    };
                });

            case 'SHIPPING_OPTION':
                var selectedOptionId = intermediatePaymentData.shippingOptionData.id;
                return fetchShippingOptions(shippingAddress, selectedOptionId).then(function(data) {
                    var transactionInfo = data.newTransactionInfo;

                    if (transactionInfo && transactionInfo.totalPrice) {
                        googlePayFinalTotal = transactionInfo.totalPrice;
                    }

                    return {
                        newTransactionInfo: transactionInfo
                    };
                }).catch(function(error) {
                    console.error('[Google Pay] Error in shipping option callback:', error);
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

    /**
     * Handle payment authorization
     */
    function onPaymentAuthorized(paymentData) {
        var config = window.paypalrGooglePayConfig || {};
        
        // Extract email address
        var email = paymentData.email || '';
        var shippingAddress = paymentData.shippingAddress;
        var billingAddress = paymentData.paymentMethodData.info.billingAddress;

        if (!email) {
            console.error('[Google Pay] No email address provided');
            return Promise.resolve({ transactionState: 'ERROR' });
        }

        console.log('[Google Pay] Email captured:', email);

        // Close Google Pay modal immediately
        var closeModalPromise = Promise.resolve({ transactionState: 'SUCCESS' });

        // Create PayPal order first, then process with email
        createPayPalOrder().then(function(orderData) {
            if (!orderData.success || !orderData.orderId) {
                console.error('[Google Pay] Failed to create PayPal order');
                showError('Failed to create order');
                return Promise.reject(new Error('Order creation failed'));
            }

            console.log('[Google Pay] PayPal order created:', orderData.orderId);

            // Now process payment with email and order ID
            return processGooglePayPayment(orderData.orderId, email, shippingAddress, billingAddress);
        }).then(function(result) {
            if (result.success || result.status === 'success') {
                var redirectUrl = result.redirect_url || 'index.php?main_page=checkout_success';
                window.location.href = redirectUrl;
            } else {
                showError(result.message || 'Payment failed');
            }
        }).catch(function(error) {
            console.error('[Google Pay] Payment processing error:', error);
            showError('Payment failed');
        });

        return closeModalPromise;
    }

    /**
     * Fetch shipping options from backend
     */
    function fetchShippingOptions(shippingAddress, selectedShippingOptionId) {
        var config = window.paypalrGooglePayConfig || {};

        var normalizedAddress = {
            name: shippingAddress.name || '',
            address1: shippingAddress.address1 || '',
            address2: shippingAddress.address2 || '',
            address3: shippingAddress.address3 || '',
            locality: shippingAddress.locality || '',
            administrativeArea: shippingAddress.administrativeArea || '',
            postalCode: shippingAddress.postalCode || '',
            countryCode: shippingAddress.countryCode || '',
            phoneNumber: shippingAddress.phoneNumber || ''
        };

        var requestData = {
            module: 'paypalr_googlepay',
            shippingAddress: normalizedAddress
        };

        if (selectedShippingOptionId) {
            requestData.selectedShippingOptionId = selectedShippingOptionId;
        }

        return fetch('ajax/paypalr_wallet.php' + (config.sessionAppend || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        }).then(function(response) {
            return response.json();
        });
    }

    /**
     * Create PayPal order for Google Pay payment
     */
    function createPayPalOrder() {
        console.log('[Google Pay] Creating PayPal order');
        
        return fetch('ppr_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet: 'google_pay'
            })
        }).then(function(response) {
            return response.json();
        }).catch(function(error) {
            console.error('[Google Pay] Error creating order:', error);
            return { success: false, message: error.message || 'Failed to create order' };
        });
    }

    /**
     * Process Google Pay payment through PayPal backend
     * Now includes email address from native SDK
     * @param {string} orderId - PayPal order ID
     * @param {string} email - User email address
     * @param {object} shippingAddress - Shipping address
     * @param {object} billingAddress - Billing address
     */
    function processGooglePayPayment(orderId, email, shippingAddress, billingAddress) {
        var config = window.paypalrGooglePayConfig || {};

        console.log('[Google Pay] Processing payment - OrderID:', orderId, 'Email:', email);

        // Normalize addresses
        var shipping = {
            name: shippingAddress.name || '',
            address1: shippingAddress.address1 || '',
            address2: shippingAddress.address2 || '',
            address3: shippingAddress.address3 || '',
            locality: shippingAddress.locality || '',
            administrativeArea: shippingAddress.administrativeArea || '',
            postalCode: shippingAddress.postalCode || '',
            countryCode: shippingAddress.countryCode || '',
            phoneNumber: shippingAddress.phoneNumber || '',
            email: email
        };

        var billing = {
            name: billingAddress.name || '',
            address1: billingAddress.address1 || '',
            address2: billingAddress.address2 || '',
            address3: billingAddress.address3 || '',
            locality: billingAddress.locality || '',
            administrativeArea: billingAddress.administrativeArea || '',
            postalCode: billingAddress.postalCode || '',
            countryCode: billingAddress.countryCode || '',
            phoneNumber: billingAddress.phoneNumber || '',
            email: email
        };

        return fetch('ajax/paypalr_wallet_checkout.php' + (config.sessionAppend || ''), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                paypal_order_id: orderId,
                module: 'paypalr_googlepay',
                currency: config.currencyCode || 'USD',
                total: googlePayFinalTotal,
                email: email,
                shipping_address: shipping,
                billing_address: billing
            })
        }).then(function(response) {
            if (response.redirected && response.url) {
                return { success: true, redirect_url: response.url };
            }
            return response.json();
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        var errorDiv = document.getElementById('paypalr-googlepay-error');
        if (errorDiv) {
            errorDiv.innerText = message;
            errorDiv.style.display = 'block';
        }
    }

    /**
     * Clear error message
     */
    function clearError() {
        var errorDiv = document.getElementById('paypalr-googlepay-error');
        if (errorDiv) {
            errorDiv.innerText = '';
            errorDiv.style.display = 'none';
        }
    }

    /**
     * Hide Google Pay container if not available
     */
    function hideGooglePayContainer() {
        var container = document.getElementById('paypalr-googlepay-button');
        if (container) {
            container.style.display = 'none';
        }
    }

})();
