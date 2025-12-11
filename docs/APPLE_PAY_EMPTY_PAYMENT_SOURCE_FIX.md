# Apple Pay MALFORMED_REQUEST_JSON Fix

## Problem

Apple Pay payments were failing during order creation with the following error:

```json
{
    "errNum": 400,
    "errMsg": "An interface error (400) was returned from PayPal.",
    "name": "INVALID_REQUEST",
    "message": "Request is not well-formed, syntactically incorrect, or violates schema.",
    "details": [
        {
            "field": "/payment_source/apple_pay",
            "location": "body",
            "issue": "MALFORMED_REQUEST_JSON",
            "description": "The request JSON is not well formed."
        }
    ],
    "debug_id": "8fbe68286f932"
}
```

The order creation request included an empty `payment_source.apple_pay` object:

```php
'payment_source' => array (
    'apple_pay' => array (
    ),
),
```

## Root Cause

The code was attempting to include the Apple Pay payment token during order creation by reading it from the session. However, the token is not available in the session at order creation time because:

1. **JavaScript Flow**: User clicks Apple Pay button → `onpaymentauthorized` callback fires → `fetchWalletOrder()` is called
2. **Order Creation**: `ajaxCreateWalletOrder()` → `createPayPalOrder()` → `CreatePayPalOrderRequest` constructor runs
3. **Token Availability**: At this point, the token has NOT been sent to the server yet, so `$_SESSION['PayPalRestful']['WalletPayload']['apple_pay']` is empty
4. **Fallback Behavior**: The code fell back to an empty array: `$this->request['payment_source']['apple_pay'] = [];`
5. **PayPal API Error**: PayPal's API rejects empty `payment_source` objects with `MALFORMED_REQUEST_JSON`

The token is only available LATER when the form is submitted and `processWalletConfirmation()` is called.

## Solution

Modified `CreatePayPalOrderRequest.php` to **NOT include `payment_source.apple_pay` at all** when the token is not available in session:

### Before (Incorrect)

```php
elseif ($ppr_type === 'apple_pay') {
    $appleWalletPayload = $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] ?? null;
    if (is_array($appleWalletPayload) && isset($appleWalletPayload['token']) && $appleWalletPayload['token'] !== '') {
        $this->request['payment_source']['apple_pay'] = ['token' => $appleWalletPayload['token']];
    } else {
        // WRONG: PayPal rejects empty payment_source objects
        $this->request['payment_source']['apple_pay'] = [];
    }
}
```

### After (Correct)

```php
elseif ($ppr_type === 'apple_pay') {
    // Include the wallet token if present.
    // If the token isn't available yet (e.g., during initial order creation from the button click),
    // do NOT include payment_source.apple_pay at all - PayPal rejects empty payment_source objects.
    $appleWalletPayload = $_SESSION['PayPalRestful']['WalletPayload']['apple_pay'] ?? null;
    if (is_array($appleWalletPayload) && isset($appleWalletPayload['token']) && $appleWalletPayload['token'] !== '') {
        $this->request['payment_source']['apple_pay'] = ['token' => $appleWalletPayload['token']];
    }
    // If token is not available, don't include payment_source.apple_pay
}
```

## Why This Works

PayPal's Order Creation API for Apple Pay supports two scenarios:

### Scenario 1: Order Creation WITHOUT Token (Initial Creation)
```json
{
    "intent": "AUTHORIZE",
    "purchase_units": [...]
    // NO payment_source field at all
}
```
✅ **Accepted by PayPal** - Order is created in `CREATED` status, ready for payment confirmation

### Scenario 2: Order Creation WITH Token (When Available)
```json
{
    "intent": "AUTHORIZE",
    "purchase_units": [...],
    "payment_source": {
        "apple_pay": {
            "token": "{encrypted-payment-token-string}"
        }
    }
}
```
✅ **Accepted by PayPal** - Order is created with payment source information

### Scenario 3: Order Creation WITH Empty Object (Previous Behavior)
```json
{
    "intent": "AUTHORIZE",
    "purchase_units": [...],
    "payment_source": {
        "apple_pay": {}  // Empty object
    }
}
```
❌ **Rejected by PayPal** with `MALFORMED_REQUEST_JSON` error

## Apple Pay Payment Flow

The correct flow is:

1. **Order Creation** (during button click, before user authorizes):
   - No token in session
   - Create order WITHOUT `payment_source.apple_pay`
   - PayPal returns order ID

2. **Payment Confirmation** (after user authorizes in Apple Pay modal):
   - Token is sent to server
   - Token is stored in session via `normalizeWalletPayload()`
   - Call `confirmPaymentSource()` with the token
   - PayPal processes the payment

## Code Changes

**File**: `includes/modules/payment/paypal/PayPalRestful/Zc2Pp/CreatePayPalOrderRequest.php`

- Removed the `else` block that set `payment_source.apple_pay` to an empty array
- Added comment explaining that empty objects are rejected by PayPal
- Token is only included when it's actually available in session

## Test Updates

**File**: `tests/CreatePayPalOrderRequestWalletPaymentSourceTest.php`

Added two test cases for Apple Pay:

1. **Test 2a**: Apple Pay WITH token in session
   - Expects `payment_source.apple_pay` to be present with the token
   
2. **Test 2b**: Apple Pay WITHOUT token in session
   - Expects `payment_source.apple_pay` to NOT be present at all
   - Verifies that empty arrays are not created

## Related Documentation

This fix supersedes the previous understanding documented in:
- `docs/APPLE_PAY_500_ERROR_FIX.md` - Which suggested empty `payment_source.apple_pay` was required

The correct behavior is now:
- **No token** → **No `payment_source.apple_pay`**
- **Token available** → **Include `payment_source.apple_pay` with token**
- **Never** → Empty `payment_source.apple_pay` object

## Testing

All existing tests pass:
- ✅ `CreatePayPalOrderRequestShippingDiscountTest.php`
- ✅ `CreatePayPalOrderRequestVaultExpiryComponentsTest.php`
- ✅ `CreatePayPalOrderRequestVaultTest.php`
- ✅ `CreatePayPalOrderRequestWalletPaymentSourceTest.php` (updated)

## Impact

This fix resolves the Apple Pay order creation failure and allows users to successfully complete Apple Pay transactions. The change is minimal and focused only on the conditional inclusion of `payment_source.apple_pay`.
