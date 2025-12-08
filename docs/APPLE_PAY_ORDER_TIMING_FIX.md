# Apple Pay Order Creation Timing Fix

## Problem

Apple Pay was creating PayPal orders when users opened the payment modal, even if they cancelled without authorizing payment. This resulted in:

1. **Wasted API calls** - Every time a user clicked the Apple Pay button, an API call was made to `ppr_wallet.php` to create an order
2. **Abandoned orders** - Orders were created in PayPal's system even when users cancelled
3. **User confusion** - Console logs showed order creation for payments that were never completed

### User Report

From the console logs:
```
LOG[Apple Pay] onvalidatemerchant called
LOG[Apple Pay] Starting order creation in parallel with merchant validation...
LOG[Apple Pay] fetchWalletOrder: Starting order creation request to ppr_wallet.php
LOG[Apple Pay] fetchWalletOrder: Received response after 783ms, status: 200
LOG[Apple Pay] fetchWalletOrder: Order creation completed after 784ms
LOG[Apple Pay] oncancel called
```

User quote: 
> "I never submitted payment, all I did was click the Apple Pay button. Why is it trying to create an order?"

## Solution

**Moved order creation from `onvalidatemerchant` to `onpaymentauthorized`**

### Before (Problematic)
```javascript
session.onvalidatemerchant = function (event) {
    // Start order creation when modal opens
    orderPromise = fetchWalletOrder();
    
    // Validate merchant
    applepay.validateMerchant({ validationUrl: event.validationURL })
        .then(session.completeMerchantValidation);
};

session.onpaymentauthorized = function (event) {
    // Wait for order that was already created
    orderPromise.then(function (config) {
        // Confirm payment
    });
};
```

**Issue**: Order is created as soon as modal opens, even if user cancels.

### After (Fixed)
```javascript
session.onvalidatemerchant = function (event) {
    // Only validate merchant - DO NOT create order yet
    applepay.validateMerchant({ validationUrl: event.validationURL })
        .then(session.completeMerchantValidation);
};

session.onpaymentauthorized = function (event) {
    // Create order NOW - when user has authorized payment
    orderPromise = fetchWalletOrder();
    
    orderPromise.then(function (config) {
        // Confirm payment
    });
};
```

**Solution**: Order is only created when user authorizes payment.

## Flow Comparison

### Previous Flow
1. User clicks Apple Pay button
2. ApplePaySession created with amount from page
3. `onvalidatemerchant` fires ➡️ **Order created**
4. User cancels
5. `oncancel` fires
6. **Result**: Order was created but wasted

### New Flow
1. User clicks Apple Pay button
2. ApplePaySession created with amount from page
3. `onvalidatemerchant` fires ➡️ Merchant validation only
4. User cancels
5. `oncancel` fires
6. **Result**: No order created, no waste

**OR** if user authorizes:

3. `onvalidatemerchant` fires ➡️ Merchant validation only
4. User authorizes payment
5. `onpaymentauthorized` fires ➡️ **Order created**
6. Payment confirmed with PayPal
7. **Result**: Order only created when needed

## Benefits

✅ **No wasted API calls** - `ppr_wallet.php` is only called when user authorizes payment

✅ **No abandoned orders** - PayPal orders are only created for actual payment attempts

✅ **Cleaner logs** - No confusing order creation messages when user cancels

✅ **Better UX** - Faster response when user cancels (no waiting for order creation)

✅ **No timeout issues** - Merchant validation still happens immediately (no delays)

## Technical Details

### Why Not Create Order Earlier?

Some might ask: "Why not create the order in advance to save time when the user authorizes?"

**Answer**: The time savings are minimal compared to the downsides:

1. **Most users cancel** - Statistics show many users open payment modals but cancel without paying
2. **Order creation is fast** - The order creation takes ~783ms, which is acceptable in the authorization flow
3. **Apple Pay validates quickly** - The merchant validation that must happen immediately is separate and fast
4. **Less waste** - Creating orders only when needed is more efficient overall

### Maintaining Merchant Validation Speed

The fix maintains the critical requirement that **merchant validation happens immediately** without waiting:

```javascript
session.onvalidatemerchant = function (event) {
    // Validate merchant IMMEDIATELY - no delays
    applepay.validateMerchant({ validationUrl: event.validationURL })
        .then(session.completeMerchantValidation);
};
```

This prevents Apple Pay timeout issues that would cause the modal to auto-close.

### Why ApplePaySession Shows Correct Amount

The amount shown to the user comes from the page, not the PayPal order:

```javascript
// Extract amount from page (e.g., #ottotal)
var orderTotal = getOrderTotalFromPage();

// Create session with this amount (synchronously, in click handler)
var session = new ApplePaySession(4, {
    total: {
        label: 'Total',
        amount: orderTotal.amount,  // From page, not API
        type: 'final'
    }
});
```

This allows us to:
- Show the correct amount to users immediately
- Create the ApplePaySession synchronously (required by Apple)
- Defer PayPal order creation until it's actually needed

## Testing

Three test files validate this fix:

1. **`ApplePayNoCancelOrderCreationTest.php`** - Specifically tests the cancel scenario
2. **`ApplePayMerchantValidationTimeoutFixTest.php`** - Tests timing and order creation location
3. **`ApplePaySessionUserGestureTest.php`** - Tests user gesture requirements are maintained

All tests verify:
- ✅ No order creation in `onvalidatemerchant`
- ✅ Order creation happens in `onpaymentauthorized`
- ✅ Merchant validation is not blocked
- ✅ Session is created synchronously with user gesture

## Migration Notes

### For Developers

If you have custom code that depends on `orderPromise` being set in `onvalidatemerchant`, you'll need to update it. The order is now only available in `onpaymentauthorized`.

### For Users

No changes needed. Users will see:
- Faster cancellation (no waiting for order creation)
- Cleaner console logs (no order creation on cancel)
- Same fast payment flow when authorizing

## Related Issues

This fix addresses the core issue where orders were created unnecessarily. Related improvements:

- Merchant validation speed (already optimized - validates immediately)
- User gesture compliance (already handled - session created synchronously)
- Amount display (already handled - extracted from page)

## References

- Apple Pay JS API: https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentrequest
- PayPal Apple Pay Guide: https://developer.paypal.com/docs/checkout/advanced/applepay/
- Original Issue: User console logs showing order creation on cancel
