# Vault Recurring Payment Fix - INCOMPATIBLE_PARAMETER_VALUE Error

## Problem Fixed

Recurring subscription payments using saved (vaulted) credit cards were failing with PayPal API error 422 (UNPROCESSABLE_ENTITY):

```
{
    "errNum": 422,
    "errMsg": "An interface error (422) was returned from PayPal.",
    "name": "UNPROCESSABLE_ENTITY",
    "message": "The requested action could not be performed, semantically incorrect, or failed business validation.",
    "details": [
        {
            "field": "/payment_source/card/vault_id",
            "location": "body",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE",
            "description": "The value of the field is incompatible/redundant with other fields in the order."
        },
        {
            "field": "/payment_source/card/expiry",
            "location": "body",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE",
            "description": "The value of the field is incompatible/redundant with other fields in the order."
        },
        {
            "field": "/payment_source/card/billing_address",
            "location": "body",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE",
            "description": "The value of the field is incompatible/redundant with other fields in the order."
        }
    ]
}
```

This prevented recurring payments from being processed automatically, requiring manual intervention for each subscription payment.

## Root Cause

The `build_vault_payment_source()` method in `paypalSavedCardRecurring.php` was sending redundant card information along with the `vault_id`. When using a vaulted payment method, PayPal already has all the card details stored and considers additional fields like `expiry`, `last_digits`, `brand`, `name`, and `billing_address` to be incompatible/redundant.

### What Was Being Sent (Incorrect)

```json
{
  "intent": "CAPTURE",
  "purchase_units": [...],
  "payment_source": {
    "card": {
      "vault_id": "abc123...",
      "expiry": "2025-12",
      "last_digits": "1234",
      "brand": "VISA",
      "name": "John Doe",
      "billing_address": {...},
      "stored_credential": {
        "payment_initiator": "MERCHANT",
        "payment_type": "RECURRING",
        "usage": "SUBSEQUENT"
      }
    }
  }
}
```

### Expected Request Structure

According to [PayPal's Orders v2 API documentation](https://developer.paypal.com/docs/api/orders/v2/), when using a `vault_id`:

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
      }
    }
  }
}
```

**Only** `vault_id` and optionally `stored_credential` should be sent. All other card details are retrieved by PayPal from the vault.

## Solution

Modified the `build_vault_payment_source()` method in `includes/classes/paypalSavedCardRecurring.php` (lines 401-422):

**Before:**
```php
protected function build_vault_payment_source(array $cardDetails, array $options = array()) {
    // ... validation code ...
    $vaultCard = $this->find_vault_card_for_payment($cardDetails);
    $cardPayload = array('vault_id' => $vaultId);
    
    // These fields cause INCOMPATIBLE_PARAMETER_VALUE errors:
    $cardPayload['expiry'] = $expiry;              // ❌ REMOVED
    $cardPayload['last_digits'] = $lastDigits;     // ❌ REMOVED
    $cardPayload['brand'] = $brand;                // ❌ REMOVED
    $cardPayload['name'] = $name;                  // ❌ REMOVED
    $cardPayload['billing_address'] = $billing;    // ❌ REMOVED
    
    $cardPayload['stored_credential'] = $storedDefaults;
    return $cardPayload;
}
```

**After:**
```php
protected function build_vault_payment_source(array $cardDetails, array $options = array()) {
    // ... validation code ...
    
    // When using a vault_id, PayPal already has the card details stored.
    // Sending additional fields like expiry, last_digits, brand, name, or billing_address
    // causes an INCOMPATIBLE_PARAMETER_VALUE error from PayPal.
    // Only vault_id and optionally stored_credential should be sent.
    $cardPayload = array('vault_id' => $vaultId);
    
    // Add stored_credential for recurring payments
    $storedDefaults = array('payment_initiator' => 'MERCHANT', 'payment_type' => 'UNSCHEDULED', 'usage' => 'SUBSEQUENT');
    if (isset($options['stored_credential']) && is_array($options['stored_credential'])) {
        $storedDefaults = array_merge($storedDefaults, $options['stored_credential']);
    }
    $cardPayload['stored_credential'] = $storedDefaults;
    return $cardPayload;
}
```

### Changes Made

1. **Removed incompatible field assignments**: Deleted code that adds `expiry`, `last_digits`, `brand`, `name`, and `billing_address` to the card payload
2. **Removed unnecessary database lookup**: The `find_vault_card_for_payment()` call is no longer needed since we're not using the vault card details
3. **Added explanatory comments**: Documented why these fields must not be sent with `vault_id`
4. **Simplified code**: Reduced from ~60 lines to ~20 lines, making it clearer and more maintainable

## Testing

Created comprehensive test: `tests/RecurringVaultPaymentSourceTest.php`

This test verifies:
1. No incompatible fields (`expiry`, `last_digits`, `brand`, `name`, `billing_address`) are added
2. Only `vault_id` and `stored_credential` are present in the payload
3. `payment_type` is correctly set to `RECURRING` for subscription payments
4. Comments explain the PayPal API requirements

Run the test:
```bash
php tests/RecurringVaultPaymentSourceTest.php
```

### Related Tests

All existing vault tests continue to pass:
- `tests/CreatePayPalOrderRequestVaultTest.php` - Validates vault handling in order creation
- `tests/CreatePayPalOrderRequestVaultExpiryComponentsTest.php` - Tests vault expiry handling
- `tests/StoredCredentialStructureTest.php` - Validates stored credential structure

## Impact

### Before Fix
- ❌ Recurring payments failed with "INCOMPATIBLE_PARAMETER_VALUE" error
- ❌ Customers' saved cards couldn't be charged for subscriptions
- ❌ Manual intervention required to process each recurring payment
- ❌ Subscription revenue at risk

### After Fix
- ✅ Recurring payments process successfully using vaulted cards
- ✅ Subscriptions automatically renew on schedule
- ✅ Proper stored credential indicators sent to PayPal
- ✅ Compliant with PayPal's Orders v2 API requirements

## Verification Steps

1. **Set up a recurring subscription** with a saved credit card
2. **Wait for the scheduled payment date** or trigger the cron job manually
3. **Check the PayPal REST API logs** for the createOrder request:
   ```
   PayPal REST cardPayload: {"vault_id":"...","stored_credential":{...}}
   ```
4. **Verify no incompatible fields** are present (expiry, last_digits, brand, name, billing_address should NOT appear)
5. **Confirm successful payment**:
   - Order created in Zen Cart
   - Payment captured via PayPal
   - Subscription status updated to 'complete'
   - Next payment date scheduled
   - Customer receives confirmation email

## Error Logs Example

### Before Fix (Error)
```
2026-01-30 20:48:10: (index) ==> End createOrder
The curlPost (v2/checkout/orders) request was unsuccessful.
{
    "errNum": 422,
    "errMsg": "An interface error (422) was returned from PayPal.",
    "name": "UNPROCESSABLE_ENTITY",
    "details": [
        {
            "field": "/payment_source/card/vault_id",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE"
        },
        {
            "field": "/payment_source/card/expiry",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE"
        },
        {
            "field": "/payment_source/card/billing_address",
            "issue": "INCOMPATIBLE_PARAMETER_VALUE"
        }
    ]
}
```

### After Fix (Success)
```
2026-01-31: (index) ==> Start createOrder
PayPal REST cardPayload: {"vault_id":"VAULT-XXX","stored_credential":{"payment_initiator":"MERCHANT","payment_type":"RECURRING","usage":"SUBSEQUENT"}}
The curlPost (v2/checkout/orders) request was successful (201).
{
    "id": "ORDER-12345",
    "status": "APPROVED"
}
2026-01-31: (index) ==> End createOrder
```

## Related Files

- `includes/classes/paypalSavedCardRecurring.php` - Main fix (lines 401-422)
- `tests/RecurringVaultPaymentSourceTest.php` - Test coverage for the fix
- `tests/CreatePayPalOrderRequestVaultTest.php` - Related vault handling tests
- `includes/modules/payment/paypal/PayPalRestful/Zc2Pp/CreatePayPalOrderRequest.php` - Correctly handles vault in regular checkouts

## PayPal API Documentation References

- [Orders v2 API](https://developer.paypal.com/docs/api/orders/v2/)
- [Payment Tokens (Vault)](https://developer.paypal.com/docs/api/payment-tokens/v3/)
- [Stored Payment Sources](https://developer.paypal.com/docs/checkout/save-payment-methods/)
- [Card Payment Source Object](https://developer.paypal.com/docs/api/orders/v2/#definition-card_request)

## See Also

- `RECURRING_PAYMENT_FIX.md` - Related fix for stored_credential nesting issue
- `SUBSCRIPTION_ACTIVATION.md` - Subscription activation and vaulting architecture

## Change History

- **2026-01-31**: Fixed INCOMPATIBLE_PARAMETER_VALUE error when using vault_id for recurring payments
- **2026-01-31**: Removed redundant fields (expiry, last_digits, brand, name, billing_address) from vault payment source
- **2026-01-31**: Added comprehensive test coverage
- **Previous**: Recurring payments failed with INCOMPATIBLE_PARAMETER_VALUE errors
