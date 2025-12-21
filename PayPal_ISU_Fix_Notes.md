# PayPal ISU Client-Side Signup Credential Exchange - Fix Notes

## Problem Summary
When users initiate PayPal signup from the client admin (paypalr_integrated_signup.php), the API credentials were never displayed or saved. The signup completed successfully on PayPal's side, but credentials were not retrieved.

## Root Cause Analysis

### The Issue
The `nxp_paypal_handle_finalize()` function in `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php` was missing database retrieval logic for `authCode` and `sharedId`.

### Flow Analysis
When using PayPal's mini-browser/embedded signup flow:

1. **PayPal Callback Flow**: 
   - PayPal's `paypalOnboardedCallback` receives authCode/sharedId
   - Main page's status call persists them to the database via `nxp_paypal_persist_auth_code()`
   
2. **Completion Page Flow**:
   - Completion page opens in a popup/new tab
   - PayPal redirects to completion page WITHOUT authCode/sharedId in the URL
   - Completion page calls finalize WITHOUT authCode/sharedId
   - **BUG**: Finalize handler was NOT checking the database for persisted authCode/sharedId
   - Falls back to merchant integration lookup which returns `step: waiting`

### Why Numinix.com Standalone Signups Worked
For signups initiated directly on Numinix.com:
- The completion page receives authCode/sharedId (either in URL or via session)
- The finalize request includes authCode/sharedId
- Credentials are exchanged successfully

### Why Client Admin Signups Failed
For signups from client admin (paypalr_integrated_signup.php):
- Completion page is a different session from the main page
- authCode/sharedId come through JavaScript callback, not URL
- Completion page's finalize doesn't have authCode/sharedId
- Database retrieval was missing in finalize handler

## Fix Applied

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
        nxp_paypal_log_debug('Retrieved authCode and sharedId from database for finalize', [
            'tracking_id' => $trackingId,
        ]);
    }
}
```

This matches the existing logic in `nxp_paypal_handle_status()` (lines 687-698).

## Files Changed
1. `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`
   - Added authCode/sharedId database retrieval to `nxp_paypal_handle_finalize()`

2. `tests/FinalizeHandlerAuthCodeRetrievalTest.php` (new file)
   - Added test to verify finalize handler retrieves auth code from database

## Future Tasks / Considerations

### 1. Callback Reliability
The fix relies on the PayPal callback (`paypalOnboardedCallback`) firing and the main page's status call persisting authCode/sharedId before the completion page's finalize runs. Consider:
- Adding retry logic if the callback doesn't fire
- Implementing a fallback mechanism if authCode/sharedId are never persisted

### 2. Mini-Browser Flow Verification
Some tests indicate the mini-browser flow may not be working correctly (`useMiniBrowserFlow` function missing). This should be investigated separately.

### 3. Timing/Race Conditions
While the fix handles the cross-session case, there could still be race conditions:
- If completion page's finalize runs before main page's status persists authCode/sharedId
- The fix handles this by falling back to merchant integration lookup and polling

### 4. Error Handling
Consider adding more explicit error messages when:
- authCode/sharedId cannot be retrieved from database
- PayPal callback never fires
- Merchant integration lookup times out

### 5. Test Infrastructure
Some existing tests have failures unrelated to this fix:
- `PayPalPartnerJsCallbackTest.php` - Mini-browser flow tests failing
- `MerchantIdDatabasePersistenceTest.php` - Tracking ID extraction test failing
- `AuthCodeCredentialExchangeTest.php` - Grant type pattern matching issue

These should be investigated and fixed separately.

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
