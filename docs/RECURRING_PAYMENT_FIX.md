# Recurring Payment Processing Fix - "Unable to create PayPal order"

## Problem Fixed

Recurring subscription payments were failing with the error:
```
Paypal error: Unable to create PayPal order
```

This prevented customers from having their recurring payments processed using their saved credit cards.

## Root Cause

The `stored_credential` object was incorrectly nested inside an `attributes` key in the PayPal API request:

```php
// WRONG (old code - line 405)
$cardPayload['attributes']['stored_credential'] = $storedDefaults;
```

According to [PayPal's Orders v2 API documentation](https://developer.paypal.com/docs/api/orders/v2/), when using a vaulted card for recurring payments, the `stored_credential` must be at the **top level** of the card object in the payment source.

### Expected Request Structure

```json
{
  "intent": "CAPTURE",
  "purchase_units": [...],
  "payment_source": {
    "card": {
      "vault_id": "abc123...",
      "stored_credential": {
        "payment_initiator": "MERCHANT",
        "payment_type": "RECURRING",
        "usage": "SUBSEQUENT"
      },
      "expiry": "2025-12",
      "last_digits": "1234",
      "brand": "VISA",
      "billing_address": {...}
    }
  }
}
```

### What Was Being Sent (Incorrect)

```json
{
  "intent": "CAPTURE",
  "purchase_units": [...],
  "payment_source": {
    "card": {
      "vault_id": "abc123...",
      "attributes": {
        "stored_credential": {
          "payment_initiator": "MERCHANT",
          "payment_type": "RECURRING",
          "usage": "SUBSEQUENT"
        }
      },
      ...
    }
  }
}
```

The nested structure caused PayPal to reject the request, returning an error response without an order ID.

## Solution

Changed line 405 in `includes/classes/paypalSavedCardRecurring.php` from:

```php
$cardPayload['attributes']['stored_credential'] = $storedDefaults;
```

To:

```php
$cardPayload['stored_credential'] = $storedDefaults;
```

## Additional Improvements

### 1. Filtered Debug Output
**File**: `cron/paypal_saved_card_recurring.php` (line 374)

Changed the debug SQL query to exclude cancelled subscriptions:
```sql
WHERE status != 'cancelled'
```

This makes the debug output cleaner and focuses on active subscriptions.

### 2. Enhanced Logging
**File**: `includes/classes/paypalSavedCardRecurring.php`

Added detailed logging to help diagnose future issues:
- Line 476: Logs the constructed card payload
- Line 484: Logs the final credential_id being used
- Line 489: Logs the complete createOrder request
- Line 496-498: Logs both raw and normalized API responses

### 3. Fixed Error Message
**File**: `includes/classes/paypalSavedCardRecurring.php` (line 484)

Fixed undefined variable warning by removing reference to `$paypal_saved_card_recurring_id` which is not in scope.

## Testing

Created comprehensive test: `tests/StoredCredentialStructureTest.php`

This test verifies:
1. `stored_credential` is NOT nested inside `attributes` (prevents regression)
2. `payment_type` is correctly set to `RECURRING` for subscription payments
3. Structure matches PayPal's API requirements

Run the test:
```bash
php tests/StoredCredentialStructureTest.php
```

## Impact

### Before Fix
- Recurring payments failed with "Unable to create PayPal order"
- Customers couldn't have their subscriptions automatically renewed
- Manual intervention required to process each payment

### After Fix
- Recurring payments process successfully using saved vault cards
- Subscriptions automatically renew on schedule
- Proper stored credential indicators sent to PayPal for recurring transactions

## Verification Steps

1. **Check the logs** when a recurring payment is processed:
   ```
   PayPal REST cardPayload: {"vault_id":"...","stored_credential":{...}}
   PayPal REST final credential_id: ...
   PayPal REST createOrder request: {"intent":"CAPTURE",...}
   PayPal REST createOrder raw response: {...}
   PayPal REST createOrder normalized response: {"id":"...","status":"..."}
   ```

2. **Verify successful payment**:
   - Subscription status changes from 'failed' to 'complete'
   - Order is created in Zen Cart
   - Transaction ID is recorded
   - Customer receives confirmation email

3. **Check debug output** excludes cancelled subscriptions:
   ```
   === DEBUG: Active Subscriptions in Database ===
   ID: 1 | Status: failed | Next Payment: 2026-01-29 | Product: ...
   (Cancelled subscriptions are not shown)
   === END DEBUG ===
   ```

## Related Files

- `includes/classes/paypalSavedCardRecurring.php` - Main fix
- `cron/paypal_saved_card_recurring.php` - Debug output filter
- `tests/StoredCredentialStructureTest.php` - Test coverage

## PayPal API Documentation References

- [Orders v2 API](https://developer.paypal.com/docs/api/orders/v2/)
- [Payment Tokens (Vault)](https://developer.paypal.com/docs/api/payment-tokens/v3/)
- [Stored Payment Sources](https://developer.paypal.com/docs/checkout/save-payment-methods/)

## Change History

- **2026-01-30**: Fixed stored_credential nesting issue causing recurring payment failures
- **2026-01-30**: Added debug output filter for cancelled subscriptions
- **2026-01-30**: Enhanced logging for better debugging
- **Previous**: Recurring payments failed with "Unable to create PayPal order"
