# PayPal ISU Client-Side Signup Credential Exchange - Fix Notes

## Problem Summary
When users initiate PayPal signup from the client admin (paypalr_integrated_signup.php), the API credentials were never displayed or saved. The signup completed successfully on PayPal's side, but credentials were not retrieved.

## Root Cause Analysis

The fundamental issue is that PayPal's mini-browser (embedded overlay) flow doesn't work reliably:
1. The `paypalOnboardedCallback` function is often NOT called by PayPal's partner.js
2. The mini-browser doesn't create a proper `window.opener` relationship
3. PostMessage communication fails because there's no popup reference

### Why Numinix.com Standalone Signups Work
For signups initiated directly on Numinix.com:
- They use `window.open()` popup approach, NOT PayPal's mini-browser
- The SAME page serves as both main page AND popup return handler
- Popup completion page detects `window.opener`, sends postMessage, and closes
- Parent receives it and includes data in finalize request

### Why Client Admin Signups Failed
For signups from client admin (paypalr_integrated_signup.php):
- Uses PayPal's mini-browser which doesn't create proper window relationships
- The mini-browser's JavaScript callbacks often don't fire
- No reliable way to communicate between mini-browser and parent page

## Solution: New Simplified Admin Page

Created a new **simplified admin page** (`admin/paypalr_signup.php`) that follows the same approach as the working numinix.com paypal_signup page:

### Key Features

1. **Popup-based flow** - Uses `window.open()` instead of mini-browser
2. **Same page for main and return** - The page serves dual purpose:
   - When loaded normally: Shows the signup form and handles the flow
   - When loaded in popup with PayPal params: Sends postMessage to parent and closes
3. **Automatic credential saving** - Once credentials are received, saves them to the configuration table

### Flow

1. User clicks "Start PayPal Setup"
2. Page calls Numinix API to get redirect URL
3. PayPal opens in a popup window via `window.open()`
4. User completes PayPal signup in the popup
5. PayPal redirects popup back to this same page with tracking params
6. Popup page detects `window.opener`, sends postMessage with params, closes
7. Parent page receives message, calls finalize API with all params
8. Credentials are returned and displayed
9. User clicks "Save to Configuration" - credentials saved to DB

### Files

- **`admin/paypalr_signup.php`** - New simplified signup page
- Uses the same Numinix API endpoints as the original
- Saves credentials to `MODULE_PAYMENT_PAYPALR_CLIENTID_*` and `MODULE_PAYMENT_PAYPALR_SECRET_*` config keys

## Previous Fix Attempts (For Reference)

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
