# Apple Pay confirmPaymentSource Schema Fix

## Problem

Apple Pay payments were failing after user authorization and being redirected back to checkout instead of proceeding to checkout success. The error logs showed:

```
The curlPost (v2/checkout/orders/{id}/confirm-payment-source) request was unsuccessful.
{
    "errNum": 400,
    "errMsg": "An interface error (400) was returned from PayPal.",
    "name": "INVALID_REQUEST",
    "message": "Request is not well-formed, syntactically incorrect, or violates schema.",
    "details": [
        {
            "field": "\/payment_source\/apple_pay\/name",
            "location": "body",
            "issue": "MALFORMED_REQUEST_JSON",
            "description": "The request JSON is not well formed."
        }
    ],
    "debug_id": "ebf340b6070d6"
}
```

Additionally, some attempts resulted in:
```
{
    "errNum": 500,
    "errMsg": "An interface error (500) was returned from PayPal.",
    "name": "INTERNAL_SERVER_ERROR",
    "message": "An internal server error has occurred.",
    "details": [
        {
            "issue": "INTERNAL_SERVICE_ERROR",
            "description": "An internal service error has occurred."
        }
    ]
}
```

## Root Cause

The `normalizeWalletPayload()` function in `paypal_common.php` was transforming Apple Pay contact information and adding `name`, `email_address`, and `billing_address` fields to the Apple Pay payment source before calling `confirmPaymentSource`.

However, **PayPal's confirmPaymentSource API for Apple Pay only accepts the `token` field** in the `apple_pay` payment source object. Any additional fields cause the API to reject the request with a `MALFORMED_REQUEST_JSON` error.

### Incorrect Payload (Before Fix)

```json
{
    "payment_source": {
        "apple_pay": {
            "token": "{encrypted-payment-token-string}",
            "name": {
                "given_name": "John",
                "surname": "Doe"
            },
            "email_address": "john.doe@example.com",
            "billing_address": {
                "address_line_1": "123 Main St",
                "admin_area_2": "San Francisco",
                "admin_area_1": "CA",
                "postal_code": "94105",
                "country_code": "US"
            }
        }
    }
}
```

❌ This format is **rejected** by PayPal with `MALFORMED_REQUEST_JSON` at field `/payment_source/apple_pay/name`

### Correct Payload (After Fix)

```json
{
    "payment_source": {
        "apple_pay": {
            "token": "{encrypted-payment-token-string}"
        }
    }
}
```

✅ This format is **accepted** by PayPal

## Why Contact Information Isn't Needed

The contact information (name, email, billing address) is **already included in the PayPal order** when it's created via the `createOrder` endpoint. The order includes:

- Shipping information with customer name and address
- Billing information  
- Email address
- All other customer details

When `confirmPaymentSource` is called, PayPal already has all the necessary contact information from the order. The `confirmPaymentSource` endpoint only needs:

1. The order ID (in the URL path)
2. The encrypted Apple Pay payment token (to verify the payment authorization)

Adding contact fields to the payment source is not only unnecessary but causes the request to be rejected as malformed.

## Solution

Updated `PayPalCommon::normalizeWalletPayload()` to:

1. Convert the Apple Pay token from array to JSON string (as before)
2. **Remove all fields except `token`** from the normalized payload
3. Return only `['token' => '{json-string}']` for Apple Pay

### Code Changes

**Before:**
```php
if ($walletType === 'apple_pay') {
    // Convert token
    $payload['token'] = json_encode($payload['token']);
    
    // Transform billing contact to PayPal format (INCORRECT)
    if (isset($payload['billing_contact'])) {
        $payload['name'] = [...];
        $payload['email_address'] = ...;
        $payload['billing_address'] = [...];
        unset($payload['billing_contact']);
    }
    
    // Remove other fields
    unset($payload['shipping_contact'], $payload['wallet'], $payload['orderID']);
}
return $payload;
```

**After:**
```php
if ($walletType === 'apple_pay') {
    // Convert token from array to JSON string
    if (isset($payload['token']) && is_array($payload['token'])) {
        $payload['token'] = json_encode($payload['token']);
    }
    
    // For Apple Pay confirmPaymentSource, PayPal only accepts the token field.
    // Contact information should NOT be included as it causes MALFORMED_REQUEST_JSON.
    // The contact info is already in the order from createOrder.
    
    // Return only the token field
    return ['token' => $payload['token']];
}
return $payload;
```

## Testing

Updated `ApplePayTokenNormalizationTest.php` to validate:

1. ✅ Token is properly JSON-encoded from array to string
2. ✅ Contact fields (name, email_address, billing_address) are NOT included in normalized payload
3. ✅ Only the `token` field is present in the normalized payload
4. ✅ Non-Apple Pay wallets (e.g., Google Pay) are not affected

### Test Results

```
✓ Apple Pay token normalized to JSON string
✓ Apple Pay token matches JSON encoding of payload
✓ Contact fields correctly excluded from payment source
✓ Token field present and properly encoded
✓ Normalized payload contains only token field
✓ Non-Apple Pay payload left unchanged

All Apple Pay token normalization tests passed!
```

## Impact

This fix resolves:

- ❌ 400 INVALID_REQUEST errors with MALFORMED_REQUEST_JSON
- ❌ 500 INTERNAL_SERVER_ERROR failures  
- ❌ Apple Pay payments being redirected back to checkout
- ✅ Apple Pay payments now complete successfully and proceed to checkout_success

## Benefits

1. **Complies with PayPal's API schema**: Only sends fields that PayPal expects and accepts
2. **Eliminates redundant data**: Contact info is already in the order; no need to send it again
3. **Simpler payload**: Reduces complexity and potential points of failure
4. **Follows API best practices**: Sends minimal required data to complete the operation
5. **Better error handling**: Eliminates a entire class of schema validation errors

## Related Files

- `includes/modules/payment/paypal/paypal_common.php` - Main fix in `normalizeWalletPayload()`
- `tests/ApplePayTokenNormalizationTest.php` - Updated test coverage
- `docs/APPLE_PAY_CONTACT_FIELDS_FIX.md` - Previous historical documentation (now superseded)

## References

- PayPal Orders API v2: `/v2/checkout/orders/{id}/confirm-payment-source`
- Apple Pay payment source schema: Only `token` field is accepted
- Error logs showing MALFORMED_REQUEST_JSON at field `/payment_source/apple_pay/name`

## Security Summary

CodeQL analysis: ✅ No security vulnerabilities detected  
Code review: ✅ No issues found

The fix:
- Does not introduce any security vulnerabilities
- Reduces attack surface by sending less data
- Maintains secure handling of payment tokens
- Follows PayPal's official API specifications
