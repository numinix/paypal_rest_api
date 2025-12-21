# Numinix.com Test Failure Analysis

## Test Results Summary

**Status:** ❌ Test Failed - No credentials displayed

**What Happened:**
1. ✅ Onboarding started successfully (tracking_id: `nxp-3b8c0cd9fb32e1321694`)
2. ✅ Popup opened with PayPal signup URL  
3. ✅ User completed signup in popup
4. ❌ After clicking "Return to Numinix", page reloaded instead of showing credentials
5. ❌ Signup button still visible (not in "completed" state)
6. ❌ No finalize event in server logs

## Root Cause Identified

**The Problem:** Popup communication flow is broken

### Expected Flow:
1. Parent window opens PayPal signup in popup
2. User completes signup in popup
3. PayPal redirects popup to return_url with authCode/sharedId parameters
4. **Return URL page (in popup) sends postMessage to parent window**
5. Parent window receives postMessage with authCode/sharedId
6. Parent window calls `/finalize` endpoint with credentials
7. Parent window displays credentials

### Actual Flow:
1. Parent window opens PayPal signup in popup ✅
2. User completes signup in popup ✅
3. PayPal redirects popup to return_url ✅
4. **Return URL page loads in popup but NO postMessage sent** ❌
5. Parent window never receives authCode/sharedId ❌
6. No finalize event triggered ❌
7. Page reloads, returns to initial state ❌

## Technical Analysis

### Numinix.com Code Expects postMessage

**File:** `numinix.com/includes/modules/pages/paypal_signup/jscript_paypal_signup.js`

**Lines 757-810:** `handlePopupMessage()` function

The parent window listens for postMessage events from the popup:

```javascript
function handlePopupMessage(event) {
    if (!state.popup || (event && event.source && event.source !== state.popup)) {
        return;
    }

    var payload = event.data;
    
    // Check for completion event
    var completionEvent = normalized === 'paypal_onboarding_complete'
        || normalized === 'paypal_partner_onboarding_complete'
        || payload.paypal_onboarding_complete === true
        || payload.paypalOnboardingComplete === true;

    if (!completionEvent || !state.session.tracking_id) {
        return;
    }

    // Capture authCode/sharedId/merchantId from PayPal postMessage
    if (payload.merchantId) {
        state.session.merchant_id = payload.merchantId;
    }
    if (payload.authCode) {
        state.session.authCode = payload.authCode;
    }
    if (payload.sharedId) {
        state.session.sharedId = payload.sharedId;
    }

    setStatus('Processing your PayPal account details…', 'info');
    finalizeOnboarding();
}

window.addEventListener('message', handlePopupMessage);
```

**This code waits for a postMessage that never arrives!**

### The Missing Piece: Return URL Handler

When PayPal redirects to the return_url, that page needs to:
1. Extract authCode/sharedId from URL parameters
2. Send postMessage to window.opener (parent window)
3. Close the popup

**Problem:** The Numinix.com paypal_signup page is NOT designed to run in a popup and send postMessage. It's designed to be the parent page that RECEIVES postMessage.

## Why This Wasn't Caught Earlier

This issue affects **both** Numinix.com and the admin plugin, but manifests differently:

### Admin Plugin (paypalr_integrated_signup.php)
- Uses mini-browser SDK with `displayMode=minibrowser`
- PayPal SDK handles postMessage automatically
- **But authCode/sharedId still not being passed** (separate SDK issue)

### Numinix.com (paypal_signup page)
- Opens PayPal in regular popup (no SDK)
- Expects manual postMessage from return URL page
- **Return URL page doesn't send postMessage** (this issue)

## The Solution

We need to create a **dedicated return URL handler page** that:
1. Runs in the popup window after PayPal redirect
2. Reads authCode/sharedId from URL parameters
3. Sends postMessage to parent window (window.opener)
4. Closes the popup

### Option 1: Modify Existing paypal_signup Page

Add code to detect if running in popup and send postMessage:

```javascript
// Check if this page is loaded in a popup (has window.opener)
if (window.opener && !window.opener.closed) {
    // Read URL parameters
    var urlParams = new URLSearchParams(window.location.search);
    var authCode = urlParams.get('authCode') || urlParams.get('auth_code');
    var sharedId = urlParams.get('sharedId') || urlParams.get('shared_id');
    var merchantId = urlParams.get('merchantId') || urlParams.get('merchantIdInPayPal');
    var trackingId = urlParams.get('tracking_id');
    
    if (authCode || sharedId || merchantId) {
        // Send postMessage to parent window
        window.opener.postMessage({
            event: 'paypal_onboarding_complete',
            authCode: authCode,
            sharedId: sharedId,
            merchantId: merchantId,
            tracking_id: trackingId
        }, window.opener.location.origin);
        
        // Close popup after a brief delay
        setTimeout(function() {
            window.close();
        }, 500);
    }
}
```

**Pros:**
- Simple, minimal code addition
- Works with existing page structure
- Backward compatible

**Cons:**
- Page loads unnecessary content (forms, etc.) just to send postMessage
- Slightly slower than dedicated handler

### Option 2: Create Dedicated Return URL Handler

Create new lightweight page: `paypal_signup_return.php`

```php
<?php
// Minimal page that just sends postMessage and closes
?>
<!DOCTYPE html>
<html>
<head>
    <title>PayPal Setup Complete</title>
</head>
<body>
    <p>Processing your PayPal account...</p>
    <script>
    (function() {
        if (!window.opener || window.opener.closed) {
            window.location.href = 'index.php?main_page=paypal_signup';
            return;
        }
        
        var params = new URLSearchParams(window.location.search);
        var authCode = params.get('authCode') || params.get('auth_code');
        var sharedId = params.get('sharedId') || params.get('shared_id');
        var merchantId = params.get('merchantId') || params.get('merchantIdInPayPal');
        var trackingId = params.get('tracking_id');
        
        window.opener.postMessage({
            event: 'paypal_onboarding_complete',
            authCode: authCode,
            sharedId: sharedId,
            merchantId: merchantId,
            tracking_id: trackingId
        }, window.opener.location.origin);
        
        setTimeout(function() {
            window.close();
        }, 500);
    })();
    </script>
</body>
</html>
```

**Pros:**
- Lightweight, fast
- Clean separation of concerns
- Purpose-built for the task

**Cons:**
- Requires new file
- Need to update return_url in referral request

## Recommended Approach

**Implement Option 1** (modify existing paypal_signup page) because:
1. Minimal code change
2. No new files needed
3. Works with existing return_url configuration
4. Can be implemented immediately

## Implementation Steps

1. Add popup detection and postMessage code to `jscript_paypal_signup.js`
2. Place code early in execution (before heavy page load)
3. Test with tracking_id parameter to verify flow
4. Verify postMessage is received by parent
5. Confirm finalize endpoint is called
6. Verify credentials display

## Expected Results After Fix

**Server Logs Should Show:**
```json
{"event":"start","tracking_id":"nxp-..."}
{"event":"popup_opened","tracking_id":"nxp-..."}
{"event":"finalize","tracking_id":"nxp-...","has_auth_code":true,"has_shared_id":true}
{"event":"finalize_success","tracking_id":"nxp-...","credentials":{"client_id":"...","client_secret":"..."}}
```

**User Should See:**
```
✓ PayPal Onboarding Complete

Save these credentials in your PayPal module configuration:
Environment: live
Client ID: [value]
Client Secret: [value]

⚠️ Store these credentials securely. Do not share them publicly.
```

## Critical Question Remains

Even after fixing the postMessage flow, we still need PayPal to return authCode and sharedId in the redirect URL.

**From @jefflew's test logs:**
- Return URL: `https://www.numinix.com/index.php?main_page=paypal_signup&tracking_id=nxp-3b8c0cd9fb32e1321694&env=live`
- **No authCode or sharedId parameters visible**

This suggests PayPal is not returning these parameters at all, which brings us back to the original problem:
- Is the Partner account approved for credential exchange?
- Does the referral request have correct operations/capabilities?
- Is FIRST_PARTY integration causing the issue?

**Next Steps:**
1. Fix postMessage flow (this will enable us to see what PayPal IS returning)
2. Test again and capture actual return URL with all parameters
3. Determine if authCode/sharedId are present but not logged, or truly missing
4. If truly missing, investigate Partner account configuration

## Summary

**Immediate Issue:** Return URL page doesn't send postMessage to parent window

**Fix:** Add postMessage code to paypal_signup page when running in popup

**Underlying Issue:** Still unknown if PayPal is returning authCode/sharedId

**Testing Required:** After implementing fix, test again to see actual parameters returned by PayPal
