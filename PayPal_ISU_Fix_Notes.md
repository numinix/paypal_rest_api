# PayPal ISU Client-Side Signup Credential Exchange - Fix Notes

## Problem Summary
When users initiate PayPal signup from the client admin (paypalr_integrated_signup.php), the API credentials were never displayed or saved. The signup completed successfully on PayPal's side, but credentials were not retrieved.

## Root Cause Analysis

### Issue 1: Missing Database Retrieval in Finalize Handler
The `nxp_paypal_handle_finalize()` function in `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php` was missing database retrieval logic for `authCode` and `sharedId`.

### Issue 2: PostMessage Handler Rejecting Valid Messages
The `handlePopupMessage()` function in `admin/paypalr_integrated_signup.php` was rejecting all messages when `state.popup` is null. This happens in the mini-browser flow because PayPal's mini-browser is an overlay, not a popup window opened via `window.open()`.

### Flow Analysis
When using PayPal's mini-browser/embedded signup flow:

1. **PayPal Callback Flow**: 
   - PayPal's `paypalOnboardedCallback` SHOULD receive authCode/sharedId
   - But this callback is often NOT called by PayPal's partner.js
   - Main page's status call would persist them to the database via `nxp_paypal_persist_auth_code()`
   
2. **Completion Page Flow**:
   - Mini-browser redirects to completion page
   - Completion page sends postMessage to opener (if it exists)
   - **BUG 1**: `handlePopupMessage` rejected messages when `state.popup` is null
   - Completion page calls finalize WITHOUT authCode/sharedId
   - **BUG 2**: Finalize handler was NOT checking the database for persisted authCode/sharedId
   - Falls back to merchant integration lookup which returns `step: waiting`

### Why Numinix.com Standalone Signups Worked
For signups initiated directly on Numinix.com:
- They use `window.open()` popup approach, NOT PayPal's mini-browser
- Popup completion page sends postMessage to parent
- Parent receives it and includes data in finalize request

### Why Client Admin Signups Failed
For signups from client admin (paypalr_integrated_signup.php):
- Uses mini-browser which doesn't set `state.popup`
- PostMessage handler rejected all messages
- authCode/sharedId come through JavaScript callback, which often doesn't fire
- Completion page's finalize doesn't have authCode/sharedId
- Database retrieval was missing in finalize handler

## Fixes Applied

### Fix 1: Database Retrieval in Finalize Handler

Added authCode/sharedId database retrieval to `nxp_paypal_handle_finalize()`:

```php
// If authCode and sharedId are not provided in the request, try to retrieve them
// from the database. This handles the cross-session case where the main page's
// callback persisted authCode/sharedId but the completion page's finalize doesn't have them.
if ((empty($authCode) || empty($sharedId)) && !empty($trackingId)) {
    $persistedAuthData = nxp_paypal_retrieve_auth_code($trackingId);
    if ($persistedAuthData !== null) {
        $authCode = $persistedAuthData['auth_code'];
        $sharedId = $persistedAuthData['shared_id'];
    }
}
```

### Fix 2: PostMessage Handler

Fixed `handlePopupMessage()` to accept messages even when `state.popup` is null:

```javascript
// Accept messages from:
// 1. Our popup (if we opened one)
// 2. Same origin (for mini-browser flow)
// 3. PayPal domains (for direct PayPal callbacks)
var isFromOurPopup = state.popup && event && event.source && event.source === state.popup;
var isFromSameOrigin = event && event.origin === window.location.origin;
var isFromPayPal = event && event.origin && (
    event.origin.indexOf('paypal.com') !== -1 ||
    event.origin.indexOf('paypalobjects.com') !== -1
);

// If we have a popup reference, only accept from that popup
// Otherwise, accept from same origin or PayPal
if (state.popup && !isFromOurPopup) {
    return;
}
if (!state.popup && !isFromSameOrigin && !isFromPayPal) {
    return;
}
```

### Fix 3: Enhanced Logging

Added comprehensive console logging to help debug callback and postMessage flows:
- Log when message event listener is attached
- Log all incoming postMessages with origin and data type
- Log when paypalOnboardingComplete event is received
- Log completion page's postMessage sending status

## Files Changed
1. `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`
   - Added authCode/sharedId database retrieval to `nxp_paypal_handle_finalize()`

2. `admin/paypalr_integrated_signup.php`
   - Fixed `handlePopupMessage()` to accept messages from same origin/PayPal when `state.popup` is null
   - Added comprehensive console logging for debugging

3. `tests/FinalizeHandlerAuthCodeRetrievalTest.php` (new file)
   - Added test to verify finalize handler retrieves auth code from database

## Future Tasks / Considerations

### 1. Callback Reliability Issue
The PayPal `paypalOnboardedCallback` callback often doesn't fire when using the mini-browser. Consider:
- Switching to the popup approach like numinix.com does (using `window.open()` instead of mini-browser)
- The popup approach is more reliable because it uses standard `window.opener` communication

### 2. Timing/Race Conditions
While the fixes handle many cases, there could still be race conditions:
- If completion page's finalize runs before authCode/sharedId are persisted to database
- The system will poll and eventually succeed once the callback fires and persists data

### 3. Merchant Integration Lookup Fallback
When authCode/sharedId are not available, the system falls back to PayPal's Merchant Integration API. This has a delay because:
- PayPal needs time to provision the merchant account after signup
- The lookup returns `step: waiting` until provisioning completes
- This can take seconds to minutes

## Testing Verification
Run these tests to verify the fix:
```bash
php tests/FinalizeHandlerAuthCodeRetrievalTest.php
php tests/AdminAuthCodeHandlingTest.php
php tests/SellerNoncePersistenceTest.php
php tests/CodeVerifierTokenExchangeTest.php
```

## References
- PayPal Seller Onboarding Documentation: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
- PayPal REST API Credentials Exchange: Uses OAuth2 authorization_code grant with code_verifier (PKCE)
