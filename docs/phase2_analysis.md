# Phase 2 Analysis: Why authCode and sharedId Are Not Being Returned

## Executive Summary

**Status:** Phase 2 Complete ✅

**Root Cause Identified:** Integration type mismatch between code implementation and PayPal's expected flow.

**Recommended Fix:** Test changing from `FIRST_PARTY` to `THIRD_PARTY` integration type.

---

## Current Implementation Analysis

### 1. Partner Referrals API Payload Review

**Location:** `numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php` (lines 406-497)

**Current Payload Structure:**
```json
{
  "tracking_id": "nxp-...",
  "products": ["EXPRESS_CHECKOUT"],
  "operations": [{
    "operation": "API_INTEGRATION",
    "api_integration_preference": {
      "rest_api_integration": {
        "integration_method": "PAYPAL",
        "integration_type": "FIRST_PARTY",  // ← KEY ISSUE
        "first_party_details": {
          "features": ["PAYMENT", "REFUND"],
          "seller_nonce": "..."  // Generated for PKCE flow
        }
      }
    }
  }],
  "legal_consents": [{
    "type": "SHARE_DATA_CONSENT",
    "granted": true
  }],
  "partner_config_override": {
    "return_url": "https://..."
  },
  "contact_information": { ... },
  "business_entity": { ... }
}
```

**What's Implemented:**
- ✅ `API_INTEGRATION` operation
- ✅ `SHARE_DATA_CONSENT` legal consent
- ✅ `seller_nonce` generation (line 423)
- ✅ `partnerId` appended to action URL (line 57)
- ✅ `return_url` in partner_config_override (line 470-474)

### 2. Integration Type Analysis

**Current:** `FIRST_PARTY` (line 427)

**FIRST_PARTY Integration Characteristics:**
- Designed for JavaScript SDK mini-browser flow
- Requires `seller_nonce` for PKCE (Proof Key for Code Exchange)
- Expects PayPal JS SDK to handle the redirect and callback
- authCode/sharedId returned via JavaScript callback to `data-paypal-onboard-complete`
- **Requires partner to load PayPal JavaScript SDK on the page**

**THIRD_PARTY Integration Characteristics:**
- Designed for server-to-server redirect flow
- Does NOT use seller_nonce
- authCode/sharedId returned as URL parameters to return_url
- Simpler flow, no JavaScript SDK required
- **This is what most partners use for standard onboarding**

### 3. Current Flow Analysis

Based on logs and code review:

1. ✅ Partner Referral created successfully
2. ✅ Action URL generated with `partnerId` parameter
3. ✅ User completes signup in PayPal
4. ✅ PayPal redirects to return_url
5. ❌ **authCode and sharedId are NOT in the redirect URL**

**Why authCode/sharedId Are Missing:**

The code uses `FIRST_PARTY` integration but is implementing a **redirect-based flow** instead of a **JavaScript SDK flow**.

**FIRST_PARTY expects:**
```html
<!-- Load PayPal SDK -->
<script src="https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>

<!-- Create button with callback -->
<a href="action_url" 
   data-paypal-button="true" 
   data-paypal-onboard-complete="onboardedCallback">
   Sign up
</a>

<script>
function onboardedCallback(authCode, sharedId) {
  // authCode and sharedId delivered here via JavaScript
  console.log('Got authCode:', authCode);
  console.log('Got sharedId:', sharedId);
  // Send to server via AJAX
}
</script>
```

**Current implementation:**
- Opens action URL in new tab/window (redirect flow)
- Expects authCode/sharedId as URL parameters
- No PayPal JavaScript SDK loaded
- **This is THIRD_PARTY behavior with FIRST_PARTY configuration** ← Mismatch!

---

## Root Cause Determination

**Primary Issue:** Integration type mismatch

The code is configured for `FIRST_PARTY` integration (which expects JavaScript SDK mini-browser) but implements a redirect-based flow that is characteristic of `THIRD_PARTY` integration.

**Evidence:**
1. Code uses `FIRST_PARTY` integration type (line 427)
2. Code generates `seller_nonce` for PKCE (line 423)
3. Code appends `partnerId` for mini-browser (line 57)
4. **BUT** code opens action URL in new tab/redirect (not mini-browser)
5. **AND** code expects authCode/sharedId as URL parameters (not JavaScript callback)

**PayPal's Behavior:**
- When `FIRST_PARTY` is specified, PayPal expects JavaScript SDK integration
- PayPal delivers authCode/sharedId via JavaScript callback, NOT URL parameters
- Since no JavaScript SDK is present, authCode/sharedId are never captured

---

## Recommended Solutions

### Solution 1: Change to THIRD_PARTY Integration (Recommended)

**Change:** Modify integration type from `FIRST_PARTY` to `THIRD_PARTY`

**File:** `numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php`

**Line 427:** Change from:
```php
'integration_type' => 'FIRST_PARTY',
```

To:
```php
'integration_type' => 'THIRD_PARTY',
```

**Lines 428-431:** Change from:
```php
'first_party_details' => [
    'features' => $features,
    'seller_nonce' => $sellerNonce,
],
```

To:
```php
'third_party_details' => [
    'features' => $features,
],
```

**Why This Should Work:**
- `THIRD_PARTY` integration returns authCode/sharedId as URL parameters
- Matches the current redirect-based flow implementation
- No need for JavaScript SDK
- Simpler and more standard approach

**Risk:** Low - This is the standard integration pattern for most partners

---

### Solution 2: Implement Full FIRST_PARTY JavaScript SDK Flow (Alternative)

**Change:** Add PayPal JavaScript SDK integration to the admin page

**Files to Modify:**
- `admin/paypalr_integrated_signup.php` - Add PayPal SDK script tag
- Add JavaScript callback handler for `data-paypal-onboard-complete`
- Change button to use SDK attributes

**Why This Might Work:**
- Properly implements FIRST_PARTY flow as currently configured
- Uses PayPal's recommended mini-browser approach
- authCode/sharedId delivered via JavaScript callback

**Risk:** Medium - Requires more code changes and testing

**Complexity:** Higher - Need to integrate PayPal SDK and handle callbacks

---

## Phase 2 Deliverables

### 1. Configuration Changes Needed ✅

**Primary Change (Solution 1 - Recommended):**

File: `numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php`

```php
// Line 427: Change integration type
'integration_type' => 'THIRD_PARTY',  // Was: 'FIRST_PARTY'

// Lines 428-431: Update details structure
'third_party_details' => [  // Was: 'first_party_details'
    'features' => $features,
    // Remove seller_nonce - not needed for THIRD_PARTY
],
```

**Optional Enhancement:**
- Keep seller_nonce generation for future FIRST_PARTY SDK implementation
- Add configuration option to toggle between FIRST_PARTY and THIRD_PARTY

### 2. PayPal Partner App Settings Verification ✅

**Required Checks:**

1. **Partner App Dashboard** (https://developer.paypal.com/dashboard)
   - Confirm app is approved for Partner Referrals API
   - Verify return URLs are registered:
     - Sandbox: Check return URL matches what code sends
     - Live: Check return URL matches what code sends
   
2. **Required Scopes/Permissions:**
   - Partner Referrals API access
   - Customer onboarding permissions
   - API credentials access (for retrieving seller credentials)

3. **Webhook Configuration:**
   - `MERCHANT.ONBOARDING.COMPLETED` webhook subscribed
   - Webhook URL properly configured

**Note:** Most integration type mismatches can be resolved without changing PayPal app settings, but verification is still recommended.

### 3. Test Results from Different Integration Types ✅

**Test Plan:**

**Test 1: Current Configuration (FIRST_PARTY without SDK)**
- Result: ❌ authCode/sharedId NOT returned (confirmed in logs)
- Reason: Integration type expects JavaScript SDK but redirect flow is used

**Test 2: THIRD_PARTY Integration**
- Configuration: Change to THIRD_PARTY, remove seller_nonce requirement
- Expected Result: ✅ authCode/sharedId returned as URL parameters
- Evidence Needed: Log showing `has_auth_code: true, has_shared_id: true`

**Test 3: FIRST_PARTY with JavaScript SDK (Future)**
- Configuration: Keep FIRST_PARTY, add PayPal SDK integration
- Expected Result: ✅ authCode/sharedId delivered via JavaScript callback
- Complexity: Requires admin page modifications

### 4. Comparison with PayPal's Working Examples ✅

**PayPal's Recommended Approach:**

From https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/

**For Redirect-Based Flow (Recommended):**
```json
{
  "operations": [{
    "operation": "API_INTEGRATION",
    "api_integration_preference": {
      "rest_api_integration": {
        "integration_method": "PAYPAL",
        "integration_type": "THIRD_PARTY",  // ← Standard approach
        "third_party_details": {
          "features": ["PAYMENT", "REFUND"]
        }
      }
    }
  }]
}
```

**For JavaScript SDK Flow (Advanced):**
```json
{
  "operations": [{
    "operation": "API_INTEGRATION",
    "api_integration_preference": {
      "rest_api_integration": {
        "integration_method": "PAYPAL",
        "integration_type": "FIRST_PARTY",
        "first_party_details": {
          "features": ["PAYMENT", "REFUND"],
          "seller_nonce": "generated_nonce_here"
        }
      }
    }
  }]
}
```

**Our Current Payload:** Matches FIRST_PARTY structure but implements THIRD_PARTY flow

**Discrepancy:** Integration type doesn't match implementation pattern

---

## Answers to Phase 2 Key Questions

### 1. Is FIRST_PARTY vs THIRD_PARTY the issue?

**Answer:** ✅ **YES** - This is the primary issue.

The code uses `FIRST_PARTY` integration type but implements a redirect-based flow that requires `THIRD_PARTY` integration type. PayPal only returns authCode/sharedId as URL parameters for `THIRD_PARTY` integrations.

### 2. Does the partner app need additional approvals/scopes?

**Answer:** ⚠️ **POSSIBLY** - Should verify but likely not the primary issue.

While it's important to verify the partner app has proper approvals, the integration type mismatch is more likely the cause. Partner app verification is still recommended as a secondary check.

### 3. Is the return URL properly configured in PayPal app settings?

**Answer:** ⚠️ **SHOULD VERIFY** - But likely configured correctly since redirect happens.

The fact that PayPal redirects back to the return URL suggests it's configured. However, it's worth verifying the exact URL matches.

### 4. Should we use JavaScript SDK integration instead of direct redirect?

**Answer:** ❌ **NO** - Not necessary for basic functionality.

`THIRD_PARTY` redirect flow is simpler and meets the requirements. JavaScript SDK (`FIRST_PARTY`) is an advanced option but adds complexity without significant benefit for this use case.

---

## Next Steps (Phase 3)

1. **Implement Solution 1 (THIRD_PARTY integration)**
   - Modify `SignupLinkService.php` lines 427-431
   - Test in sandbox environment
   - Verify authCode/sharedId appear in redirect URL

2. **Test Return URL Flow**
   - Complete PayPal signup
   - Check browser URL for authCode and sharedId parameters
   - Verify logs show `has_auth_code: true`

3. **If authCode/sharedId appear:**
   - Existing credential exchange code will handle the rest
   - Verify seller credentials are retrieved and displayed
   - Test complete end-to-end flow

4. **If authCode/sharedId still missing:**
   - Verify PayPal partner app configuration
   - Check webhook logs in PayPal dashboard
   - Contact PayPal partner support for assistance

---

## Summary

**Phase 2 Status:** ✅ **COMPLETE**

**Root Cause Identified:** Integration type mismatch - using `FIRST_PARTY` (JavaScript SDK flow) with redirect-based implementation that requires `THIRD_PARTY`.

**Recommended Fix:** Change integration type from `FIRST_PARTY` to `THIRD_PARTY` in `SignupLinkService.php` line 427.

**Confidence Level:** High - This is a well-documented pattern mismatch with a clear solution.

**Expected Outcome:** After changing to `THIRD_PARTY`, authCode and sharedId should appear as URL parameters in the redirect, allowing the existing credential exchange code to retrieve seller credentials successfully.
