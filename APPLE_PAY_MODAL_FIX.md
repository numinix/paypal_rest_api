# Apple Pay Modal Auto-Close Fix

## Problem Summary

When users clicked the Apple Pay button at checkout:
1. The Apple Pay modal opened showing the correct price
2. A processing animation displayed briefly
3. The modal immediately closed with "payment not completed" message
4. Console logged "cancelled by user" even though user didn't cancel

## Root Cause

The Apple Pay session was timing out during merchant validation because the code was waiting for PayPal order creation to complete before validating the merchant with Apple.

### Previous (Broken) Flow:
```
User clicks button
  → fetchWalletOrder() starts (async, may take seconds)
  → ApplePaySession created and begin() called
  → Apple Pay fires onvalidatemerchant callback
  → Code WAITS for orderPromise to resolve
  → If order creation is slow (>5 seconds), Apple Pay times out
  → Session auto-cancels
  → oncancel fires with no explicit abort reason
  → Logs "cancelled by user" (misleading - actually a timeout)
```

### New (Fixed) Flow:
```
User clicks button
  → ApplePaySession created and begin() called
  → Apple Pay fires onvalidatemerchant callback
  → Code calls validateMerchant() IMMEDIATELY
  → Order creation starts in PARALLEL (doesn't block validation)
  → Merchant validation completes quickly
  → User sees payment sheet
  → User authorizes payment
  → onpaymentauthorized waits for order, then confirms
  → Payment succeeds
```

## Changes Made

### JavaScript (jquery.paypalr.applepay.js)

**Key Change:** Moved `validateMerchant()` call to execute immediately in `onvalidatemerchant` without waiting for order creation.

#### Before:
```javascript
session.onvalidatemerchant = function (event) {
    // WRONG: Wait for order before validating merchant
    orderPromise.then(function (config) {
        // ... validate config ...
        return applepay.validateMerchant({...}).then(...);
    });
};
```

#### After:
```javascript
session.onvalidatemerchant = function (event) {
    // Start order creation in parallel (don't wait)
    if (!orderPromise) {
        orderPromise = fetchWalletOrder();
    }
    
    // RIGHT: Validate merchant immediately
    applepay.validateMerchant({
        validationUrl: event.validationURL
    }).then(function (merchantSession) {
        session.completeMerchantValidation(merchantSession);
    });
};

session.onpaymentauthorized = function (event) {
    // Wait for order here (when actually needed)
    orderPromise.then(function (config) {
        // ... validate config ...
        // Confirm payment with PayPal
        applepay.confirmOrder({...});
    });
};
```

### Enhanced Logging

Added comprehensive console logging to help diagnose issues:
- Order creation timing measurements
- Step-by-step flow tracking with `[Apple Pay]` prefix
- Clear indication of whether cancel was user-initiated or timeout

### Tests Added

1. **ApplePayMerchantValidationTimeoutFixTest.php**
   - Verifies merchant validation happens immediately
   - Confirms order creation doesn't block validation
   - Ensures order is awaited in onpaymentauthorized

2. **Updated Existing Tests**
   - ApplePaySessionUserGestureTest.php
   - WalletActualAmountUsageTest.php

## Testing the Fix

### For Developers:
```bash
# Run all Apple Pay tests
cd /path/to/paypal_rest_api
php tests/ApplePayMerchantValidationTimeoutFixTest.php
php tests/ApplePaySessionUserGestureTest.php
php tests/WalletActualAmountUsageTest.php
```

### For End Users:
1. Clear browser cache
2. Go to checkout page
3. Click Apple Pay button
4. Open browser console (F12)
5. Look for `[Apple Pay]` log messages showing the flow
6. Payment sheet should appear and stay open
7. Complete payment normally

### Expected Console Output:
```
[Apple Pay] Order total from page: {amount: "50.00", currency: "USD"}
[Apple Pay] Creating ApplePaySession with payment request: {...}
[Apple Pay] ApplePaySession created successfully
[Apple Pay] Calling session.begin()...
[Apple Pay] session.begin() called - waiting for onvalidatemerchant callback
[Apple Pay] onvalidatemerchant called, validationURL: https://...
[Apple Pay] Starting order creation in parallel with merchant validation...
[Apple Pay] Calling validateMerchant immediately...
[Apple Pay] fetchWalletOrder: Starting order creation request to ppr_wallet.php
[Apple Pay] validateMerchant succeeded, completing merchant validation
[Apple Pay] fetchWalletOrder: Received response after 250ms, status: 200
[Apple Pay] fetchWalletOrder: Order creation completed after 255ms, data: {...}
[Apple Pay] onpaymentauthorized called
[Apple Pay] Order creation completed, config: {...}
[Apple Pay] Order validation passed, orderID: xxx, amount: 50.00
[Apple Pay] Confirming order with PayPal, orderID: xxx
[Apple Pay] confirmOrder result: {...}
[Apple Pay] Order confirmed successfully, status: APPROVED
```

## Benefits of This Fix

1. **Prevents Timeout:** Merchant validation completes within Apple Pay's time window
2. **Shows Correct Amount:** Still uses page amount in payment sheet (not $0.00)
3. **Maintains User Gesture:** Session still created synchronously
4. **Better Performance:** Order creation and merchant validation happen in parallel
5. **Clearer Debugging:** Enhanced logging helps diagnose future issues

## Compatibility

- ✅ Works with existing PayPal SDK
- ✅ Compatible with user gesture requirements
- ✅ Maintains actual amount display (not placeholder)
- ✅ No changes required to PHP backend
- ✅ All existing tests pass

## Next Steps

Once you provide the Braintree reference implementation, we can:
1. Compare the `selection()` function patterns
2. Verify our approach matches proven working implementations
3. Identify any additional improvements or patterns to adopt
4. Add any missing validation or error handling
