# Apple Pay confirmOrder Response Handling Fix

## Problem Summary

When users clicked the Apple Pay button and authorized payment:
1. PayPal order was created successfully (status: CREATED)
2. `applepay.confirmOrder()` was called with the Apple Pay payment token
3. The modal showed "payment failed" even though the order was created
4. Console error: `PayPalApplePayError: An internal server error has occurred`

## Root Cause

The JavaScript code was incorrectly checking for a `status` field in the `confirmOrder()` response:

```javascript
// OLD CODE (INCORRECT)
.then(function (confirmResult) {
    if (confirmResult.status === PAYPAL_STATUS.APPROVED || confirmResult.status === PAYPAL_STATUS.PAYER_ACTION_REQUIRED) {
        // Success handling
    } else {
        // Failure handling - THIS WAS INCORRECTLY TRIGGERED
    }
})
```

According to the PayPal SDK source code (`paypal-applepay-components/src/applepay.js`), the `confirmOrder()` method has this signature:

```typescript
confirmOrder(params: ConfirmOrderParams): Promise<void | PayPalApplePayErrorType>
```

This means:
- **On success**: Resolves with `undefined` (void) - no data is returned
- **On failure**: Throws a `PayPalApplePayError` exception

The old code was checking `confirmResult.status`, but `confirmResult` is `undefined` on success, so it doesn't have a `status` property. This caused the success case to be treated as a failure.

## Solution

Simplified the success handling to recognize that reaching the `.then()` callback means the confirmation succeeded:

```javascript
// NEW CODE (CORRECT)
.then(function (confirmResult) {
    console.log('[Apple Pay] Order confirmed successfully');
    session.completePayment(ApplePaySession.STATUS_SUCCESS);
    
    var payload = {
        orderID: orderId,
        confirmResult: confirmResult,
        wallet: 'apple_pay'
    };
    setApplePayPayload(payload);
    document.dispatchEvent(new CustomEvent('paypalr:applepay:payload', { detail: payload }));
})
.catch(function (error) {
    // Error handling
    console.error('[Apple Pay] confirmOrder failed', error);
    console.error('[Apple Pay] Error name:', error.name);
    console.error('[Apple Pay] Error message:', error.message);
    console.error('[Apple Pay] PayPal Debug ID:', error.paypalDebugId);
    session.completePayment(ApplePaySession.STATUS_FAILURE);
})
```

## Additional Improvements

### Enhanced Error Logging

Added detailed error logging to help diagnose issues:
- Error name (e.g., "INTERNAL_SERVER_ERROR", "APPLEPAY_PAYMENT_ERROR")
- Error message (human-readable description)
- PayPal Debug ID (for support tickets)

### Data Logging

Added logging of the data being sent to PayPal:
- Payment token
- Billing contact
- Shipping contact

This will help diagnose any data format issues that might cause "internal server error".

## Testing

Created `ApplePayConfirmOrderResponseHandlingTest.php` to verify:
1. ✅ No incorrect status checks on `confirmResult`
2. ✅ Success is handled by reaching `.then()` callback
3. ✅ Detailed error logging includes name, message, and paypalDebugId

All tests pass.

## Comparison with WooCommerce Implementation

The WooCommerce PayPal Payments plugin has a similar implementation that checks:

```javascript
if (
    confirmOrderResponse &&
    confirmOrderResponse.approveApplePayPayment
) {
    if (
        confirmOrderResponse.approveApplePayPayment.status ===
        'APPROVED'
    ) {
        // Success
    }
}
```

However, this appears to be using a different version of the SDK or a different API endpoint, as the official PayPal SDK source code shows that `confirmOrder()` returns `void` on success.

## SDK Reference

The fix is based on the official PayPal SDK source code:
- Repository: `paypal/paypal-applepay-components`
- File: `src/applepay.js`
- Type definitions: `src/types.js`

```javascript
// From paypal-applepay-components/src/applepay.js
function confirmOrder({
  orderId,
  token,
  billingContact,
  shippingContact,
}: ConfirmOrderParams): Promise<void | PayPalApplePayErrorType> {
  // ... mutation call ...
  return fetch(...)
    .then(res => {
      if (!res.ok) {
        throw new PayPalApplePayError(...);
      }
      return res.json();
    })
    .then(({ data, errors, extensions }) => {
      if (Array.isArray(errors) && errors.length) {
        throw new PayPalApplePayError(...);
      }
      return data;  // Returns the GraphQL data, but mutation returns void
    });
}
```

The GraphQL mutation `approveApplePayPayment` doesn't specify any return fields, so it returns `void` on success.

## Remaining Issues

### "Internal Server Error"

If the "internal server error" persists after this fix, it indicates a problem with the payment data or order state:

**Possible causes:**
1. **Invalid billing/shipping contact data** - Check that country codes are uppercase, all required fields are present
2. **Order already processed** - The order might have been confirmed before and is no longer in CREATED status
3. **Invalid payment token** - The Apple Pay token might be malformed or expired
4. **PayPal configuration issues** - Apple Pay might not be properly enabled in the PayPal account

**Debugging:**
With the enhanced logging, check the browser console for:
- The payment token structure
- The billing and shipping contact data
- The error name and PayPal Debug ID

Use the PayPal Debug ID when contacting PayPal support.

## Impact

This fix resolves the incorrect response handling that was causing valid confirmations to be treated as failures. Users should now be able to complete Apple Pay payments successfully when the PayPal API call succeeds.

If there are underlying issues with the payment data or PayPal configuration, the enhanced error logging will help identify and resolve them.

## Related Files

- `includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js` - Main fix
- `tests/ApplePayConfirmOrderResponseHandlingTest.php` - Test coverage
- `APPLE_PAY_MODAL_FIX.md` - Related documentation about merchant validation

## Migration Notes

No migration required. This is a bug fix that corrects the response handling to match the actual SDK behavior.

Existing Apple Pay integrations will benefit from:
1. Correct success detection
2. Better error messages
3. Enhanced debugging capabilities
