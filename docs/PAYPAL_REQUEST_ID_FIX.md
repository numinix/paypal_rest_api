# PayPal-Request-Id Header Fix

## Problem Fixed

PayPal API was rejecting recurring payment createOrder requests with the following error:

```json
{
    "errNum": 400,
    "name": "INVALID_REQUEST",
    "message": "Request is not well-formed, syntactically incorrect, or violates schema.",
    "details": [
        {
            "issue": "PAYPAL_REQUEST_ID_REQUIRED",
            "description": "A PayPal-Request-Id is required if you are trying to process payment for an Order. Please specify a PayPal-Request-Id or Create the Order without a 'payment_source' specified."
        }
    ]
}
```

## Root Cause

When creating PayPal orders with a `payment_source` parameter (used for vaulted cards in recurring payments), PayPal's API now **requires** a `PayPal-Request-Id` header.

### What is PayPal-Request-Id?

The `PayPal-Request-Id` header is an **idempotency key** that:

1. **Prevents Duplicate Transactions**: If the same request is sent multiple times with the same ID, PayPal will return the same response without creating duplicate orders
2. **Enables Safe Retries**: Network errors or timeouts can be safely retried using the same request ID
3. **Required for payment_source**: Mandatory when creating orders with a payment_source (vaulted card or token)

### PayPal Documentation

From PayPal's API documentation:

> "The server stores keys for 24 hours. If you send a request with a key that was stored within the last 24 hours, the server returns the original response instead of processing the request again."

## Solution Implemented

### Code Changes

**File:** `includes/classes/paypalSavedCardRecurring.php` (lines 492-497)

Added PayPal-Request-Id generation before the createOrder call:

```php
// Generate a unique PayPal-Request-Id for idempotency
// Use subscription ID and current date to create a deterministic but unique ID
$subscription_id = isset($payment_details['saved_credit_card_recurring_id']) 
    ? $payment_details['saved_credit_card_recurring_id'] : 0;
$request_id = 'recurring_' . $subscription_id . '_' . date('Ymd');
$client->setPayPalRequestId($request_id);
error_log('PayPal REST Request-Id: ' . $request_id);
```

### Request ID Format

**Pattern:** `recurring_{subscription_id}_{YYYYMMDD}`

**Examples:**
- Subscription #1 on Jan 30, 2026: `recurring_1_20260130`
- Subscription #25 on Feb 15, 2026: `recurring_25_20260215`

### Why This Format?

1. **Deterministic**: Same subscription on same day = same ID
2. **Unique**: Different subscriptions or different days = different ID
3. **Retry-Safe**: Failed payments can be retried with the same ID (same day)
4. **Debug-Friendly**: ID clearly indicates subscription and date

## How It Works

### Request Flow

```
1. Cron runs to process subscription #1
   ↓
2. Generate Request-Id: 'recurring_1_20260130'
   ↓
3. Set header via client->setPayPalRequestId()
   ↓
4. Call createOrder with payment_source
   ↓
5. PayPal receives request with header:
   PayPal-Request-Id: recurring_1_20260130
   ↓
6. PayPal processes payment (or returns cached response)
```

### Retry Scenario

If the cron runs multiple times on the same day for the same subscription:

```
First attempt (10:00 AM):
- Request-Id: recurring_1_20260130
- Result: Order created, ID: ABC123

Network error, cron retries (10:05 AM):
- Request-Id: recurring_1_20260130 (SAME)
- Result: PayPal returns existing order ABC123 (no duplicate)
```

### Different Day Scenario

```
Jan 30, 2026:
- Request-Id: recurring_1_20260130
- Creates new order

Jan 31, 2026:
- Request-Id: recurring_1_20260131 (DIFFERENT)
- Creates new order (if needed)
```

## Infrastructure Already Exists

The PayPal REST client already had infrastructure for this:

**File:** `includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/PayPalAdvancedCheckoutApi.php`

```php
// Property (line 138)
protected $paypalRequestId = '';

// Setter method (line 203)
public function setPayPalRequestId(string $request_id)
{
    $this->paypalRequestId = $request_id;
}

// Header injection (line 1015)
if ($this->paypalRequestId !== '') {
    $curl_options[CURLOPT_HTTPHEADER][] = 'PayPal-Request-Id: ' . $this->paypalRequestId;
}
```

The fix simply **uses existing functionality** that wasn't being called for recurring payments.

## Testing

### Automated Test: PayPalRequestIdTest.php

Verifies:
1. ✓ `setPayPalRequestId` is called before `createOrder`
2. ✓ Request ID includes 'recurring_' prefix
3. ✓ Request ID is deterministic (subscription ID + date)
4. ✓ Request ID is logged for debugging

Run the test:
```bash
php tests/PayPalRequestIdTest.php
```

### Manual Verification

Check the logs for successful payment processing:

```
[30-Jan-2026 17:06:42] PayPal REST Request-Id: recurring_1_20260130
[30-Jan-2026 17:06:42] PayPal REST createOrder request: {...}
[30-Jan-2026 17:06:42] PayPal REST createOrder raw response: {"id":"ABC123",...}
```

**Before fix:** Response was `false` with PAYPAL_REQUEST_ID_REQUIRED error
**After fix:** Response contains order ID and payment succeeds

## Impact

### Before Fix
- ❌ All recurring payments failed with 400 error
- ❌ "PAYPAL_REQUEST_ID_REQUIRED" error
- ❌ Subscriptions couldn't be processed automatically
- ❌ Manual intervention required for each payment

### After Fix
- ✅ Recurring payments process successfully
- ✅ PayPal accepts createOrder requests
- ✅ Idempotency prevents duplicates
- ✅ Safe retries on network errors
- ✅ Automatic subscription renewal works

## PayPal API Changes

This fix addresses a PayPal API requirement that appears to be:
1. **New or Newly Enforced**: The requirement wasn't previously strict
2. **Security Enhancement**: Prevents accidental duplicate charges
3. **Best Practice**: Aligns with industry-standard idempotency patterns

## Related Files

- `includes/classes/paypalSavedCardRecurring.php` - Request ID generation
- `includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/PayPalAdvancedCheckoutApi.php` - Header handling infrastructure
- `tests/PayPalRequestIdTest.php` - Test coverage
- `docs/PAYPAL_REQUEST_ID_FIX.md` - This documentation

## Additional Notes

### Why Not UUID?

We could use a random UUID for each request, but the deterministic approach has advantages:

**Random UUID Approach:**
```php
$request_id = uniqid('recurring_', true);  // recurring_1234567890abcdef
```

**Our Deterministic Approach:**
```php
$request_id = 'recurring_' . $subscription_id . '_' . date('Ymd');
```

**Advantages of Deterministic:**
1. Same-day retries use same ID (true idempotency)
2. Easy to debug (can see subscription and date in logs)
3. Predictable for troubleshooting

**Disadvantage:**
- Multiple different payment attempts on same day would reuse same ID
- This is actually desirable for failed retries but could be problematic if we need multiple successful payments same day
- For recurring subscriptions, we only process one payment per day per subscription, so this is not an issue

## Change History

- **2026-01-30**: Added PayPal-Request-Id header for recurring payments
- **Previous**: All recurring payment requests failed with PAYPAL_REQUEST_ID_REQUIRED error
