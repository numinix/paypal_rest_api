# Apple Pay Authorization/Capture Mismatch Fix

## Problem Summary

Apple Pay payments were failing with the following error when the transaction mode was set to "Auth Only (All Txns)":

```
The curlPost (v2/checkout/orders/0A779987P1079681B/capture) request was unsuccessful.
{
    "errNum": 422,
    "errMsg": "An interface error (422) was returned from PayPal.",
    "name": "UNPROCESSABLE_ENTITY",
    "message": "The requested action could not be performed, semantically incorrect, or failed business validation.",
    "details": [
        {
            "issue": "ACTION_DOES_NOT_MATCH_INTENT",
            "description": "Order was created with an intent to 'AUTHORIZE'. Please use v2/checkout/orders/order_id/authorize to perform authorization or alternately Create an order with an intent of 'CAPTURE'."
        }
    ]
}
```

## Root Cause

The issue occurred because:
1. When `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` was set to "Auth Only (All Txns)", orders were created with `AUTHORIZE` intent
2. The `captureWalletPayment` method in `paypal_common.php` always called `captureOrder`, regardless of the intent
3. PayPal rejected the capture request because the order was created with AUTHORIZE intent

## Solution

Updated the payment processing flow to respect the transaction mode setting:

### 1. Added Transaction Mode Constants
Added constants to `PayPalCommon` class to avoid hardcoded strings:
- `TRANSACTION_MODE_FINAL_SALE` = 'Final Sale'
- `TRANSACTION_MODE_AUTH_ALL` = 'Auth Only (All Txns)'
- `TRANSACTION_MODE_AUTH_CARD_ONLY` = 'Auth Only (Card-Only)'

### 2. Updated captureWalletPayment Method
Modified `captureWalletPayment` in `paypal_common.php` to:
- Accept `transaction_mode` and `ppr_type` parameters
- Determine whether to capture or authorize based on the transaction mode:
  - **CAPTURE** when:
    - Transaction mode is "Final Sale", OR
    - Transaction mode is "Auth Only (Card-Only)" AND payment type is NOT card (wallets should capture in card-only auth mode)
  - **AUTHORIZE** when:
    - Transaction mode is "Auth Only (All Txns)"
- Call `authorizeOrder()` when intent is AUTHORIZE
- Call `captureOrder()` when intent is CAPTURE
- Add proper logging for both operations

### 3. Updated Wallet Modules
Updated all wallet payment modules to pass the required parameters:
- `paypalr_applepay.php`: Pass `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` and `'apple_pay'`
- `paypalr_googlepay.php`: Pass `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` and `'google_pay'`
- `paypalr_venmo.php`: Pass `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` and `'venmo'`

### 4. Updated processCreditCardPayment
Updated `processCreditCardPayment` method to use the new constants instead of hardcoded strings for consistency.

## Transaction Mode Behavior

| Transaction Mode | Wallet Payments | Card Payments |
|-----------------|----------------|---------------|
| Final Sale | CAPTURE | CAPTURE |
| Auth Only (All Txns) | AUTHORIZE | AUTHORIZE |
| Auth Only (Card-Only) | CAPTURE | AUTHORIZE |

This ensures that:
- "Final Sale" always captures immediately
- "Auth Only (All Txns)" always authorizes first (requires manual capture later)
- "Auth Only (Card-Only)" only authorizes for card payments, but captures for wallets (Apple Pay, Google Pay, Venmo)

## Files Changed

1. `includes/modules/payment/paypal/paypal_common.php`
   - Added transaction mode constants
   - Updated `captureWalletPayment` method signature and implementation
   - Updated `processCreditCardPayment` to use constants

2. `includes/modules/payment/paypalr_applepay.php`
   - Updated `captureOrAuthorizePayment` to pass transaction mode parameters

3. `includes/modules/payment/paypalr_googlepay.php`
   - Updated `captureOrAuthorizePayment` to pass transaction mode parameters

4. `includes/modules/payment/paypalr_venmo.php`
   - Updated `captureOrAuthorizePayment` to pass transaction mode parameters

5. `tests/WalletCaptureOrAuthorizeIntentTest.php` (new)
   - Test to verify correct intent handling based on transaction mode
   - Validates all wallet modules pass required parameters
   - Validates constants are used instead of hardcoded strings

## Testing

All existing tests pass, including:
- `ApplePayClientSideConfirmOrderIdSaveTest.php` - ✓ PASSED
- `WalletModuleConstructorTest.php` - ✓ PASSED
- `WalletCaptureOrAuthorizeIntentTest.php` - ✓ PASSED (new test)
- Various other Apple Pay and wallet tests

## Impact

- **Apple Pay**: Now correctly authorizes when "Auth Only (All Txns)" is set, preventing the 422 error
- **Google Pay**: Same fix applied for consistency
- **Venmo**: Same fix applied for consistency
- **Backward Compatibility**: Existing "Final Sale" and "Auth Only (Card-Only)" modes continue to work as before
- **Maintainability**: Transaction mode strings are now defined as constants, reducing the risk of typos and making future updates easier

## Security Summary

No security vulnerabilities were introduced or discovered during this fix. The change is purely functional, ensuring the correct PayPal API endpoint is called based on the transaction mode setting.
