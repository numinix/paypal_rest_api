# Apple Pay and Google Pay Server-Side Confirmation Fix

## Problem

### Apple Pay
Apple Pay payments were showing as failed in the Zen Cart checkout even though PayPal orders were created successfully. The error message was:

```
ERROR[Apple Pay] confirmOrder failed PayPalApplePayError: An internal server error has occurred
ERROR[Apple Pay] Error name: PayPalApplePayError
ERROR[Apple Pay] Error message: An internal server error has occurred
ERROR[Apple Pay] PayPal Debug ID:
```

The PHP logs showed the order was created successfully:
```
createPayPalOrder(apple_pay): PayPal order created successfully.
  PayPal Order ID: 5C0664932W584702L
  Status: CREATED
```

But the checkout page showed the payment as failed because the JavaScript confirmOrder call was failing.

### Google Pay
Google Pay had the same double-confirmation pattern, calling `googlepay.confirmOrder()` on the client side before the server-side `confirmPaymentSource()` call. While it may not have shown errors yet, this created potential for the same issues as Apple Pay.

## Root Cause

Both modules were attempting to confirm the payment **twice**:

1. **Client-side (JavaScript)**: After getting payment data, calling `confirmOrder()` (applepay or googlepay) with the payment token/data
2. **Server-side (PHP)**: In `pre_confirmation_check`, calling `confirmPaymentSource()` with the payload

For Apple Pay, the client-side confirmOrder was failing with "An internal server error", which prevented the form from being submitted. This meant the server-side confirmation never had a chance to run.

For Google Pay, the double-confirmation could cause issues if the first confirmation succeeded, making the second one fail (order already confirmed).

## Braintree Reference Implementation

The working Braintree Apple Pay module follows a different pattern:

```javascript
session.onpaymentauthorized = function (event) {
    applePayInstance.tokenize({ token: event.payment.token }).then(function (payload) {
        // Complete the session with SUCCESS
        session.completePayment(ApplePaySession.STATUS_SUCCESS);
        
        // Submit the form with the payment nonce
        submitNonce(payload.nonce);
    });
};
```

**Key points:**
- Braintree tokenizes the payment token to get a nonce
- It immediately completes the Apple Pay session with SUCCESS
- It submits the form with the nonce
- The **server** processes the actual payment

## Solution

Updated the PayPal REST API implementation to follow the same pattern:

### Before (Incorrect)

```javascript
session.onpaymentauthorized = function (event) {
    fetchWalletOrder().then(function (config) {
        // Try to confirm order on client side
        applepay.confirmOrder({
            orderId: orderId,
            token: event.payment.token,
            billingContact: event.payment.billingContact,
            shippingContact: event.payment.shippingContact
        }).then(function (confirmResult) {
            // This was failing with "internal server error"
            session.completePayment(ApplePaySession.STATUS_SUCCESS);
            setApplePayPayload({ orderID: orderId, confirmResult: confirmResult });
        }).catch(function (error) {
            // Error prevented form submission
            session.completePayment(ApplePaySession.STATUS_FAILURE);
        });
    });
};
```

### After (Correct)

```javascript
session.onpaymentauthorized = function (event) {
    fetchWalletOrder().then(function (config) {
        // Complete the session immediately after order creation
        session.completePayment(ApplePaySession.STATUS_SUCCESS);
        
        // Pass payment token and contacts to server
        var payload = {
            orderID: orderId,
            token: event.payment.token,
            wallet: 'apple_pay'
        };
        
        if (event.payment.billingContact) {
            payload.billing_contact = event.payment.billingContact;
        }
        
        if (event.payment.shippingContact) {
            payload.shipping_contact = event.payment.shippingContact;
        }
        
        // Submit form - server will handle confirmPaymentSource
        setApplePayPayload(payload);
    });
};
```

### Google Pay (After)

```javascript
paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
    console.log('[Google Pay] Payment data received from Google Pay sheet');
    
    // Build the payload with the payment data
    // The server-side confirmPaymentSource API call will use this data
    var payload = {
        orderID: orderId,
        paymentMethodData: paymentData.paymentMethodData,
        wallet: 'google_pay'
    };
    
    console.log('[Google Pay] Setting payload and submitting form');
    setGooglePayPayload(payload);
    // Form submits, server will handle confirmPaymentSource
});
```

### Server-Side Processing

The PHP code in `paypal_common.php::processWalletConfirmation()` already handles the confirmation correctly for both wallets:

```php
$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
    $_SESSION['PayPalRestful']['Order']['id'],
    [$walletType => $payload]  // 'apple_pay' or 'google_pay' with token/data
);

if ($confirm_response === false) {
    $this->paymentModule->setMessageAndRedirect($errorMessages['confirm_failed'], FILENAME_CHECKOUT_PAYMENT);
}

$response_status = $confirm_response['status'] ?? '';
if (in_array($response_status, $walletSuccessStatuses, true)) {
    $_SESSION['PayPalRestful']['Order']['status'] = $response_status;
}
```

## Benefits

1. **Follows working reference**: Matches the proven Braintree Apple Pay pattern
2. **Single confirmation**: Payment is confirmed only once (server-side), eliminating the duplicate confirmation attempt
3. **Better error handling**: Server-side errors can be properly logged and handled with user-friendly messages
4. **Improved user experience**: Payment modals complete immediately after user authorization, providing faster feedback
5. **Consistency**: Both Apple Pay and Google Pay now follow the same pattern
6. **Maintainability**: Simpler client-side code, centralized confirmation logic on server

## Testing

### Apple Pay
Created `ApplePayServerSideConfirmationTest.php` to validate:
1. ✅ confirmOrder is NOT called on client side
2. ✅ Session is completed immediately after order creation
3. ✅ Payload includes payment token
4. ✅ Payload conditionally includes billing/shipping contacts
5. ✅ Server handles confirmPaymentSource

### Google Pay
Created `GooglePayServerSideConfirmationTest.php` to validate:
1. ✅ confirmOrder is NOT called on client side after loadPaymentData
2. ✅ Payload includes paymentMethodData
3. ✅ Server handles confirmPaymentSource
4. ✅ Follows same pattern as Apple Pay for consistency

All existing wallet tests continue to pass.

## Migration Notes

No migration required. This is a bug fix that corrects the payment confirmation flow.

Existing Apple Pay and Google Pay integrations will immediately benefit from:
1. Working payment confirmations (fixes Apple Pay "internal server error")
2. No more "internal server error" failures
3. Better alignment with industry best practices

## Related Files

### Apple Pay
- `includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js` - Main fix
- `tests/ApplePayServerSideConfirmationTest.php` - Test coverage
- `docs/APPLE_PAY_CONFIRM_ORDER_FIX.md` - Previous historical fix (now superseded)

### Google Pay
- `includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js` - Main fix
- `tests/GooglePayServerSideConfirmationTest.php` - Test coverage

### Shared
- `includes/modules/payment/paypal/paypal_common.php` - Server-side confirmation handling (used by both)

## Security Summary

CodeQL analysis: ✅ No security vulnerabilities detected
Code review: ✅ Only minor style suggestions, no functional issues

The fix:
- Does not introduce any new security vulnerabilities
- Properly validates data before sending to PayPal API
- Maintains secure handling of payment tokens for both Apple Pay and Google Pay
- Follows established security patterns from Braintree reference
- Centralizes payment confirmation on the server for better security control
