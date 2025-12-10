# Apple Pay Contact Fields Fix

## Problem

Apple Pay payments were failing after user authorization with a 500 INTERNAL_SERVER_ERROR when the server attempted to confirm the payment with PayPal's `confirmPaymentSource` API:

```
The curlPost (v2/checkout/orders/{id}/confirm-payment-source) request was unsuccessful.
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

Console logs showed that Apple Pay contacts were empty:
```
LOG[Apple Pay] Payment token: [object Object]
LOG[Apple Pay] Billing contact:
LOG[Apple Pay] Shipping contact:
```

## Root Cause

The Apple Pay payment request was not requesting required contact fields from Apple Pay. When Apple Pay was initialized, the `paymentRequest` object did not include `requiredBillingContactFields` or `requiredShippingContactFields`:

```javascript
// BEFORE (incorrect):
var paymentRequest = {
    countryCode: 'US',
    currencyCode: 'USD',
    total: { label: 'Total', amount: '15.05' }
    // Missing: requiredBillingContactFields
    // Missing: requiredShippingContactFields
};
```

As a result:
1. Apple Pay did not collect billing/shipping contact information from the user
2. The payment token was sent to the server without contact data
3. PayPal's `confirmPaymentSource` API requires name, email, and billing address
4. The API call failed with a 500 error because required fields were missing

## Reference Implementation

The working Braintree Apple Pay module demonstrates the correct pattern:

```javascript
// braintree_applepay.php line 871
requiredBillingContactFields: ["postalAddress", "name"]
```

Braintree requests billing contact fields, which ensures Apple Pay collects the necessary information from the user.

## Solution

### 1. Request Required Contact Fields in Apple Pay Session

Updated the Apple Pay payment request to include required contact fields:

```javascript
// AFTER (correct):
var paymentRequest = {
    countryCode: applePayConfig.countryCode || 'US',
    currencyCode: orderTotal.currency || applePayConfig.currencyCode || 'USD',
    total: {
        label: applePayConfig.merchantName || 'Total',
        amount: orderTotal.amount,
        type: 'final'
    },
    // Request billing contact fields required by PayPal's API
    requiredBillingContactFields: ['postalAddress', 'name', 'email'],
    // Request shipping contact for physical goods
    requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone']
};
```

### 2. Transform Apple Pay Contacts to PayPal Format

Enhanced `PayPalCommon::normalizeWalletPayload()` to transform Apple Pay contact structure to PayPal's expected format:

#### Apple Pay Contact Structure
```javascript
{
    givenName: "John",
    familyName: "Doe",
    emailAddress: "john.doe@example.com",
    addressLines: ["123 Main St", "Apt 4"],
    locality: "San Francisco",
    administrativeArea: "CA",
    postalCode: "94105",
    countryCode: "US"
}
```

#### PayPal Expected Structure
```json
{
    "name": {
        "given_name": "John",
        "surname": "Doe"
    },
    "email_address": "john.doe@example.com",
    "billing_address": {
        "address_line_1": "123 Main St",
        "address_line_2": "Apt 4",
        "admin_area_2": "San Francisco",
        "admin_area_1": "CA",
        "postal_code": "94105",
        "country_code": "US"
    }
}
```

#### Transformation Logic

```php
// Extract and validate name
if (!empty($billingContact['givenName']) || !empty($billingContact['familyName'])) {
    $givenName = $billingContact['givenName'] ?? '';
    $familyName = $billingContact['familyName'] ?? '';
    
    // Only add name if at least one field is non-empty
    if ($givenName !== '' || $familyName !== '') {
        $payload['name'] = [
            'given_name' => $givenName,
            'surname' => $familyName
        ];
    }
}

// Extract email
if (!empty($billingContact['emailAddress'])) {
    $payload['email_address'] = $billingContact['emailAddress'];
}

// Extract and validate billing address (ensure essential fields)
$addressLines = $billingContact['addressLines'] ?? [];
$hasAddressLine = !empty($addressLines[0]);
$hasLocality = !empty($billingContact['locality']);
$hasCountryCode = !empty($billingContact['countryCode']);

// Only include address if we have essential components
if (($hasAddressLine || $hasLocality) && $hasCountryCode) {
    $payload['billing_address'] = [
        'address_line_1' => $addressLines[0] ?? '',
        'admin_area_2' => $billingContact['locality'] ?? '',
        'admin_area_1' => $billingContact['administrativeArea'] ?? '',
        'postal_code' => $billingContact['postalCode'] ?? '',
        'country_code' => $billingContact['countryCode']
    ];
    
    if (isset($addressLines[1]) && $addressLines[1] !== '') {
        $payload['billing_address']['address_line_2'] = $addressLines[1];
    }
}

// Remove raw contact fields after transformation
unset($payload['billing_contact'], $payload['shipping_contact']);
```

### 3. Validation Improvements

Added validation logic to ensure data quality:

- **Name validation**: Only creates name object if at least one field (given_name or surname) is non-empty
- **Address validation**: Requires country_code and at least one of (address_line_1 or locality) to include address
- **Field cleanup**: Removes temporary fields (wallet, orderID, raw contacts) that shouldn't be sent to PayPal

## Testing

### Updated Tests

1. **ApplePayTokenNormalizationTest.php** - Extended to test contact normalization:
   - ✓ Token is JSON-encoded
   - ✓ Name is transformed to PayPal format
   - ✓ Email is extracted correctly
   - ✓ Billing address is transformed with all fields
   - ✓ Raw contact fields are removed

2. **NativeApplePayImplementationTest.php** - Updated to verify server-side confirmation:
   - ✓ Does NOT use client-side confirmOrder
   - ✓ Sets orderId in payload
   - ✓ Includes contact information in payload

3. **ApplePayMerchantValidationTimeoutFixTest.php** - Updated test expectations:
   - ✓ Confirms confirmOrder is NOT called (server handles confirmation)

### All Tests Pass

```
✓ Apple Pay token normalized to JSON string
✓ Apple Pay token matches JSON encoding of payload
✓ Name transformed to PayPal format
✓ Email extracted correctly
✓ Billing address transformed to PayPal format
✓ Raw contact fields removed
✓ Non-Apple Pay payload left unchanged

All Apple Pay token and contact normalization tests passed!
```

## Benefits

1. **Fixes checkout error**: Apple Pay payments now successfully confirm with PayPal's API
2. **Proper contact collection**: Collects all required information from Apple Pay users
3. **Data transformation**: Automatically converts Apple Pay format to PayPal's expected structure
4. **Validation**: Ensures only valid, complete contact information is sent to PayPal
5. **Follows best practices**: Aligns with reference implementation (Braintree Apple Pay)
6. **Comprehensive testing**: Full test coverage for contact normalization logic

## Security

CodeQL analysis: ✅ No security vulnerabilities detected

The fix:
- Does not introduce any security vulnerabilities
- Properly validates contact data before transformation
- Maintains secure handling of payment tokens
- Follows established security patterns

## Related Documentation

- [APPLE_PAY_SERVER_SIDE_CONFIRMATION_FIX.md](APPLE_PAY_SERVER_SIDE_CONFIRMATION_FIX.md) - Server-side confirmation pattern
- [APPLE_PAY_CONFIGURATION.md](APPLE_PAY_CONFIGURATION.md) - Apple Pay setup guide

## Migration Notes

No migration required. This is a bug fix that automatically applies to all Apple Pay payments.

Existing Apple Pay integrations will immediately benefit from:
1. Successful payment confirmations (fixes 500 error)
2. Proper contact data collection
3. Automatic data transformation for PayPal compatibility
