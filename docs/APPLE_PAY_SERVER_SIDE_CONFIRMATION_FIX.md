# Apple Pay Server-Side Confirmation Fix

## Problem

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

## Root Cause

The code was attempting to confirm the payment **twice**:

1. **Client-side (JavaScript)**: After order creation, calling `applepay.confirmOrder()` with the payment token
2. **Server-side (PHP)**: In `pre_confirmation_check`, calling `confirmPaymentSource()` with the payload

The client-side confirmOrder was failing with "An internal server error", which prevented the form from being submitted. This meant the server-side confirmation never had a chance to run.

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

### Server-Side Processing

The PHP code in `paypal_common.php::processWalletConfirmation()` already handles the confirmation correctly:

```php
$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(
    $_SESSION['PayPalRestful']['Order']['id'],
    ['apple_pay' => $payload]  // Includes token and contacts
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
4. **Improved user experience**: Apple Pay modal completes immediately after user authorization, providing faster feedback
5. **Consistent with other wallets**: Google Pay and Venmo also handle confirmation server-side

## Testing

Created `ApplePayServerSideConfirmationTest.php` to validate:
1. ✅ confirmOrder is NOT called on client side
2. ✅ Session is completed immediately after order creation
3. ✅ Payload includes payment token
4. ✅ Payload conditionally includes billing/shipping contacts
5. ✅ Server handles confirmPaymentSource

All existing Apple Pay tests continue to pass.

## Migration Notes

No migration required. This is a bug fix that corrects the payment confirmation flow.

Existing Apple Pay integrations will immediately benefit from:
1. Working payment confirmations
2. No more "internal server error" failures
3. Better alignment with industry best practices

## Related Files

- `includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js` - Main fix
- `includes/modules/payment/paypal/paypal_common.php` - Server-side confirmation handling
- `tests/ApplePayServerSideConfirmationTest.php` - Test coverage
- `docs/APPLE_PAY_CONFIRM_ORDER_FIX.md` - Previous historical fix (now superseded)

## Security Summary

CodeQL analysis: ✅ No security vulnerabilities detected
Code review: ✅ Only minor style suggestions, no functional issues

The fix:
- Does not introduce any new security vulnerabilities
- Properly validates data before sending to PayPal API
- Maintains secure handling of payment tokens
- Follows established security patterns from Braintree reference
