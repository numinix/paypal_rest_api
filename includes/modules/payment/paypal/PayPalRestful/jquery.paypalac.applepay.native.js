/**
 * PayPal Apple Pay Integration - Native ApplePaySession API
 *
 * This module implements native Apple Pay integration using ApplePaySession API
 * to retrieve user email addresses, then processes payment through PayPal REST API.
 * Based on the Braintree reference implementation pattern.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
(function () {
    'use strict';

    var cachedShippingContact = null;
    var cachedSelectedShippingId = null;
    var pendingRedirectUrl = null;
    var applePayFinalTotal = null;

    /**
     * Initialize Apple Pay button
     */
    window.initPayPalRApplePay = function() {
        if (typeof ApplePaySession === "undefined" || !ApplePaySession.canMakePayments()) {
            console.log('[Apple Pay] Not available on this device/browser');
            hideApplePayContainer();
            return;
        }

        var config = window.paypalacApplePayConfig || {};
        applePayFinalTotal = config.initialTotal || '0.00';

        // Check if Apple Pay is available with active card
        ApplePaySession.canMakePaymentsWithActiveCard(config.merchantId || 'merchant.com.paypal')
            .then(function(canMakePayments) {
                if (!canMakePayments) {
                    console.log('[Apple Pay] No active cards available');
                    hideApplePayContainer();
                    return;
                }

                // Render Apple Pay button
                renderApplePayButton();
            })
            .catch(function(error) {
                console.error('[Apple Pay] Error checking payment availability:', error);
                hideApplePayContainer();
            });
    };

    /**
     * Render the Apple Pay button
     */
    function renderApplePayButton() {
        var container = document.getElementById('paypalac-applepay-button');
        if (!container) {
            console.warn('[Apple Pay] Button container not found');
            return;
        }

        // Clear container
        container.innerHTML = '';

        // Create Apple Pay button
        var button = document.createElement('button');
        button.className = 'apple-pay-button apple-pay-button-black';
        button.type = 'button';
        button.style.cssText = '-webkit-appearance: -apple-pay-button; -apple-pay-button-type: buy; -apple-pay-button-style: black; height: 44px; width: 100%; cursor: pointer;';
        
        button.addEventListener('click', function() {
            handleApplePayButtonClick();
        });

        container.appendChild(button);
        console.log('[Apple Pay] Button rendered');
    }

    /**
     * Handle Apple Pay button click
     */
    function handleApplePayButtonClick() {
        var config = window.paypalacApplePayConfig || {};
        
        var paymentRequest = {
            countryCode: config.storeCountryCode || 'US',
            currencyCode: config.currencyCode || 'USD',
            total: {
                label: config.storeName || 'Store',
                amount: applePayFinalTotal
            },
            supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
            merchantCapabilities: ['supports3DS'],
            requiredBillingContactFields: ['postalAddress', 'name', 'phone', 'email'],
            requiredShippingContactFields: ['postalAddress', 'name', 'phone', 'email'],
            shippingType: 'shipping'
        };

        var session = new ApplePaySession(3, paymentRequest);

        session.onvalidatemerchant = function(event) {
            // Call PayPal backend to validate merchant
            validateMerchant(event.validationURL).then(function(merchantSession) {
                session.completeMerchantValidation(merchantSession);
            }).catch(function(error) {
                console.error('[Apple Pay] Merchant validation failed:', error);
                session.abort();
            });
        };

        session.onshippingcontactselected = function(event) {
            cachedShippingContact = event.shippingContact;
            
            // Fetch shipping options from backend
            fetchShippingOptions(cachedShippingContact, cachedSelectedShippingId).then(function(data) {
                var shippingParams = data.newShippingOptionParameters || {};
                if (!cachedSelectedShippingId && shippingParams.defaultSelectedOptionId) {
                    cachedSelectedShippingId = shippingParams.defaultSelectedOptionId;
                }

                var shippingMethods = (data.newShippingMethods || []).map(function(method) {
                    return {
                        label: method.label,
                        amount: method.amount,
                        identifier: method.identifier,
                        detail: method.detail || ''
                    };
                });

                var lineItems = (data.newLineItems || []).map(function(item) {
                    return {
                        label: item.label,
                        amount: item.amount
                    };
                });

                var total = (data.newTotal && data.newTotal.amount) || applePayFinalTotal;
                applePayFinalTotal = total;

                session.completeShippingContactSelection({
                    newShippingMethods: shippingMethods,
                    newTotal: { label: config.storeName || 'Store', amount: total },
                    newLineItems: lineItems
                });
            }).catch(function(error) {
                console.error('[Apple Pay] Error fetching shipping options:', error);
                session.abort();
            });
        };

        session.onshippingmethodselected = function(event) {
            cachedSelectedShippingId = event.shippingMethod.identifier;
            if (!cachedShippingContact) {
                session.abort();
                return;
            }

            fetchShippingOptions(cachedShippingContact, cachedSelectedShippingId).then(function(data) {
                var lineItems = (data.newLineItems || []).map(function(item) {
                    return {
                        label: item.label,
                        amount: item.amount
                    };
                });

                var total = (data.newTotal && data.newTotal.amount) || applePayFinalTotal;
                applePayFinalTotal = total;

                session.completeShippingMethodSelection({
                    newTotal: { label: config.storeName || 'Store', amount: total },
                    newLineItems: lineItems
                });
            }).catch(function(error) {
                console.error('[Apple Pay] Error updating shipping method:', error);
                session.abort();
            });
        };

        session.oncancel = function() {
            console.log('[Apple Pay] Payment cancelled');
            if (pendingRedirectUrl) {
                window.location.href = pendingRedirectUrl;
            }
        };

            session.onpaymentauthorized = function(event) {
            var shipping = normalizeContact(event.payment.shippingContact);
            var billing = normalizeContact(event.payment.billingContact || event.payment.shippingContact);
            var email = billing.email || shipping.email || '';

            if (!email) {
                console.error('[Apple Pay] No email address provided');
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                return;
            }

            console.log('[Apple Pay] Email captured:', email);

            // Create PayPal order first, then process with email
            createPayPalOrder().then(function(orderData) {
                if (!orderData.success || !orderData.orderId) {
                    console.error('[Apple Pay] Failed to create PayPal order');
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    return Promise.reject(new Error('Order creation failed'));
                }

                console.log('[Apple Pay] PayPal order created:', orderData.orderId);

                // Now process payment with email and order ID
                return processApplePayPayment(orderData.orderId, shipping, billing, email);
            }).then(function(result) {
                if (result.success || result.status === 'success') {
                    session.completePayment(ApplePaySession.STATUS_SUCCESS);
                    pendingRedirectUrl = result.redirect_url || 'index.php?main_page=checkout_success';
                    setTimeout(function() {
                        window.location.href = pendingRedirectUrl;
                    }, 50);
                } else {
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                    showError(result.message || 'Payment failed');
                }
            }).catch(function(error) {
                console.error('[Apple Pay] Payment processing error:', error);
                session.completePayment(ApplePaySession.STATUS_FAILURE);
                showError('Payment failed');
            });
        };

        session.begin();
    }

    /**
     * Validate merchant with PayPal backend
     */
    function validateMerchant(validationURL) {
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet: 'apple_pay',
                action: 'validate_merchant',
                validationURL: validationURL
            })
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'Merchant validation failed');
            }
            return data.merchantSession;
        });
    }

    /**
     * Fetch shipping options from backend
     */
    function fetchShippingOptions(shippingContact, selectedShippingOptionId) {
        var config = window.paypalacApplePayConfig || {};
        
        return fetch('ajax/paypalac_wallet.php' + (config.sessionAppend || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                module: 'paypalac_applepay',
                shippingAddress: normalizeContact(shippingContact),
                selectedShippingOptionId: selectedShippingOptionId || undefined
            })
        }).then(function(response) {
            return response.json();
        });
    }

    /**
     * Create PayPal order for Apple Pay payment
     */
    function createPayPalOrder() {
        console.log('[Apple Pay] Creating PayPal order');
        
        return fetch('ppac_wallet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                wallet: 'apple_pay'
            })
        }).then(function(response) {
            return response.json();
        }).catch(function(error) {
            console.error('[Apple Pay] Error creating order:', error);
            return { success: false, message: error.message || 'Failed to create order' };
        });
    }

    /**
     * Process Apple Pay payment through PayPal backend
     * Now includes email address from native SDK
     */
    function processApplePayPayment(orderId, shipping, billing, email) {
        var config = window.paypalacApplePayConfig || {};
        
        console.log('[Apple Pay] Processing payment - OrderID:', orderId, 'Email:', email);

        return fetch('ajax/paypalac_wallet_checkout.php' + (config.sessionAppend || ''), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                paypal_order_id: orderId,
                module: 'paypalac_applepay',
                currency: config.currencyCode || 'USD',
                total: applePayFinalTotal,
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
     * Normalize Apple Pay contact to standard address format
     */
    function normalizeContact(contact) {
        if (!contact) {
            return {};
        }

        var fullName = ((contact.givenName || "") + " " + (contact.familyName || "")).trim();
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

    /**
     * Show error message
     */
    function showError(message) {
        var errorDiv = document.getElementById('paypalac-applepay-error');
        if (errorDiv) {
            errorDiv.innerText = message;
            errorDiv.style.display = 'block';
        }
    }

    /**
     * Hide Apple Pay container if not available
     */
    function hideApplePayContainer() {
        var container = document.getElementById('paypalac-applepay-button');
        if (container) {
            container.style.display = 'none';
        }
    }

})();
