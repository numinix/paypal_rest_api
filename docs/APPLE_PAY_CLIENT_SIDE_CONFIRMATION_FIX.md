# Apple Pay Client-Side Confirmation Fix

## Problem
Server-side `POST /v2/checkout/orders/{id}/confirm-payment-source` returns `500 INTERNAL_SERVICE_ERROR` when called for Apple Pay payments.

PayPal's Apple Pay integration is designed for the PayPal JS SDK to perform confirmation via `paypal.Applepay().confirmOrder()`, not via server-side API calls.

## Root Cause
- Server-side `confirm-payment-source` API lacks the proper device/session context for Apple Pay
- PayPal frequently responds with 500 errors when this endpoint is called server-side for Apple Pay
- This is a PayPal limitation/design decision - their Apple Pay flow expects client-side confirmation

## Solution
Move the Apple Pay confirmation step to the browser using the PayPal JS SDK and skip server-side `confirmPaymentSource` for Apple Pay.

### Implementation Details

#### 1. Client-Side Changes (jquery.paypalr.applepay.js)

**Before:**
```javascript
// Old flow: Create order, complete session, send token to server
session.onpaymentauthorized = function (event) {
    fetchWalletOrder().then(function (config) {
        // Complete session immediately
        session.completePayment(ApplePaySession.STATUS_SUCCESS);
        
        // Send token and contacts to server
        var payload = {
            orderID: orderId,
            token: event.payment.token,
            billing_contact: event.payment.billingContact,
            shipping_contact: event.payment.shippingContact,
            wallet: 'apple_pay'
        };
        setApplePayPayload(payload);
    });
};
```

**After:**
```javascript
// New flow: Create order, confirm client-side, then complete session
session.onpaymentauthorized = function (event) {
    fetchWalletOrder().then(function (config) {
        // Call PayPal's client-side confirmOrder
        return sdkState.applepay.confirmOrder({
            orderId: orderId,
            token: event.payment.token,
            billingContact: event.payment.billingContact || null,
            shippingContact: event.payment.shippingContact || null
        }).then(function (confirmResult) {
            // Only NOW complete the session (after confirmation succeeds)
            session.completePayment(ApplePaySession.STATUS_SUCCESS);

            // Server just needs to proceed with authorize/capture
            var payload = {
                orderID: orderId,
                wallet: 'apple_pay',
                confirmed: true  // Flag indicating client-side confirmation done
            };
            setApplePayPayload(payload);
        }).catch(function (err) {
            // Handle confirmation failure
            session.completePayment(ApplePaySession.STATUS_FAILURE);
            setApplePayPayload({});
        });
    });
};
```

**Key Changes:**
1. Call `paypal.Applepay().confirmOrder()` with order ID, token, and contacts
2. Only call `session.completePayment(SUCCESS)` AFTER `confirmOrder` succeeds
3. Payload includes `confirmed: true` flag instead of raw token/contacts
4. Added error handling for `confirmOrder` failures

#### 2. Server-Side Changes (paypal_common.php)

**Before:**
```php
// Old flow: All wallets went through confirmPaymentSource
if ($walletType !== 'apple_pay') {
    $paypal_order_created = $this->paymentModule->createPayPalOrder($walletType);
    // ...
}

$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
    $_SESSION['PayPalRestful']['Order']['id'],
    [$walletType => $payload]
);
// This caused 500 errors for Apple Pay
```

**After:**
```php
// New flow: Apple Pay returns early, others continue as before
if ($walletType === 'apple_pay') {
    // Apple Pay confirmation is handled client-side
    $_SESSION['PayPalRestful']['Order']['wallet_payment_confirmed'] = true;
    $_SESSION['PayPalRestful']['Order']['payment_source'] = 'apple_pay';

    $this->paymentModule->log->write(
        "pre_confirmation_check (apple_pay) skipped server confirmPaymentSource; confirmed client-side.",
        true,
        'after'
    );

    return;  // Skip server-side confirmation
}

// Google Pay and Venmo: Create order on server, then confirm
$paypal_order_created = $this->paymentModule->createPayPalOrder($walletType);
// ...

$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
    $_SESSION['PayPalRestful']['Order']['id'],
    [$walletType => $payload]
);
```

**Key Changes:**
1. Early return for Apple Pay before `createPayPalOrder` and `confirmPaymentSource`
2. Set session flags to indicate client-side confirmation completed
3. Added logging for debugging
4. Google Pay and Venmo continue with server-side confirmation

## Flow Comparison

### Apple Pay (New)
1. User clicks Apple Pay button → ApplePaySession created
2. Merchant validation (immediate)
3. User authorizes payment → Create PayPal order
4. **Client-side:** `paypal.Applepay().confirmOrder()` ✅
5. **Client-side:** `session.completePayment(SUCCESS)` ✅
6. Submit form with `{orderID, wallet: 'apple_pay', confirmed: true}`
7. **Server:** Skip confirmPaymentSource (return early)
8. **Server:** Proceed to `authorizeOrder()` or `captureOrder()`

### Google Pay & Venmo (Unchanged)
1. User clicks button → Create PayPal order (client-side)
2. User authorizes payment → Get payment data
3. Submit form with payment data
4. **Server:** `createPayPalOrder()` (already done on client, order ID in session)
5. **Server:** `confirmPaymentSource()` ✅
6. **Server:** Proceed to `authorizeOrder()` or `captureOrder()`

## Why This Fixes the 500 Error

`paypal.Applepay().confirmOrder()` is PayPal's official mechanism for Apple Pay confirmation. It:
- Properly handles the Apple Pay token with correct device/session context
- Performs the confirmation within PayPal's JS SDK (same origin)
- Avoids the server-side API limitations that cause 500 errors

## Testing

Created comprehensive test suite:
- `ApplePayClientSideConfirmationTest.php` - Validates new flow
- Updated `ApplePayServerSideConfirmationTest.php` - Marked deprecated
- Updated `ApplePayMerchantValidationTimeoutFixTest.php` - Expects confirmOrder call
- Updated `ApplePayCreateOrderOnlyFlowTest.php` - Tests early return pattern

All tests pass ✅

## References
- Problem statement: POST /v2/checkout/orders/{id}/confirm-payment-source → 500 INTERNAL_SERVICE_ERROR
- PayPal Apple Pay API: https://developer.paypal.com/docs/api/orders/v2/#definition-apple_pay_request
- PayPal Applepay JS SDK: https://developer.paypal.com/docs/checkout/advanced/applepay/

## Security
CodeQL scan completed with 0 alerts ✅
