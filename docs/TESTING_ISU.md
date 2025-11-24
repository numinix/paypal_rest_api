# PayPal ISU Testing Guide

## Overview
This guide provides step-by-step instructions for testing the new PayPal Integrated Sign-Up (ISU) implementation.

## Prerequisites

### Environment Setup
1. PayPal Sandbox Account with Partner API access
2. Zen Cart installation with PayPal Advanced Checkout module
3. Access to numinix.com deployment
4. Partner API credentials configured:
   - `NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_ID`
   - `NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_SECRET`

### Configuration
Ensure these constants are set correctly:
```php
MODULE_PAYMENT_PAYPALR_SERVER = 'sandbox'  // For testing
MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL = 'https://www.numinix.com/index.php'
```

## Test Scenarios

### Scenario 1: Admin-Initiated ISU (Happy Path)

**Objective:** Verify admin user stays on admin page and credentials are auto-saved

**Steps:**
1. Log into Zen Cart admin
2. Navigate to Modules → Payment
3. Select "PayPal Advanced Checkout"
4. Click "Complete PayPal Setup" button
5. Verify:
   - ✅ Admin page doesn't redirect to numinix.com
   - ✅ Popup window opens with PayPal
   - ✅ Status message shows "Starting PayPal signup..."

6. In PayPal popup, complete the onboarding:
   - Log in with sandbox business account
   - Grant permissions
   - Complete all required steps

7. When PayPal redirects back:
   - ✅ Popup closes automatically
   - ✅ Admin page shows "Waiting for PayPal to complete setup..."
   - ✅ Status updates to show credentials retrieved
   - ✅ Client ID and Secret are displayed
   - ✅ Click "Return to PayPal Module"

8. Verify module configuration:
   - ✅ Client ID field is populated
   - ✅ Client Secret field is populated
   - ✅ Credentials match what was displayed

**Expected Result:** User never leaves admin, credentials auto-filled

---

### Scenario 2: Numinix.com Direct ISU (Happy Path)

**Objective:** Verify credentials are displayed on numinix.com page

**Steps:**
1. Navigate to `https://www.numinix.com/index.php?main_page=paypal_signup&env=sandbox`
2. Click "Start PayPal Signup" button
3. Verify:
   - ✅ Popup opens with PayPal
   - ✅ Status shows "Follow the steps in the PayPal window..."

4. Complete PayPal onboarding in popup

5. When complete:
   - ✅ Popup closes
   - ✅ Page shows "PayPal Onboarding Complete"
   - ✅ Credentials are displayed with:
     - Environment (Sandbox/Live)
     - Client ID
     - Client Secret
   - ✅ Warning message about storing securely

6. Copy credentials
7. Navigate to admin and paste into module config
8. Save configuration

**Expected Result:** Credentials displayed for manual entry

---

### Scenario 3: Popup Blocker Test

**Objective:** Verify error handling when popups are blocked

**Steps:**
1. Enable popup blocker in browser
2. Follow Scenario 1 steps 1-4
3. Verify:
   - ✅ Error message: "Please allow popups for this site to continue"
   - ✅ Start button is re-enabled
   - ✅ User can retry after allowing popups

**Expected Result:** Clear error message, recoverable state

---

### Scenario 4: User Cancels Onboarding

**Objective:** Verify handling when user closes popup early

**Steps:**
1. Follow Scenario 1 steps 1-5
2. In PayPal popup, click "Cancel" or close window
3. Verify:
   - ✅ Admin page shows "PayPal window closed"
   - ✅ Start button is re-enabled
   - ✅ User can restart process

**Expected Result:** Graceful handling of cancellation

---

### Scenario 5: Network Error During Polling

**Objective:** Verify error handling for API failures

**Steps:**
1. Start onboarding process
2. Complete PayPal onboarding
3. Simulate network interruption (disconnect WiFi or use browser DevTools)
4. Verify:
   - ✅ Error message displayed
   - ✅ Polling stops
   - ✅ User can refresh and check status

**Expected Result:** Error handling without crashes

---

### Scenario 6: Multiple Environments

**Objective:** Verify sandbox and live environments work correctly

**Steps:**
1. Test Scenario 1 with `MODULE_PAYMENT_PAYPALR_SERVER = 'sandbox'`
2. Verify credentials saved to sandbox keys:
   - `MODULE_PAYMENT_PAYPALR_CLIENTID_S`
   - `MODULE_PAYMENT_PAYPALR_SECRET_S`

3. Change to `MODULE_PAYMENT_PAYPALR_SERVER = 'live'`
4. Test Scenario 1 with live PayPal account
5. Verify credentials saved to live keys:
   - `MODULE_PAYMENT_PAYPALR_CLIENTID_L`
   - `MODULE_PAYMENT_PAYPALR_SECRET_L`

**Expected Result:** Correct credential storage per environment

---

## API Response Validation

### Verify Credential Extraction

When testing, check that PayPal returns credentials in the expected format:

**Check browser DevTools → Network → Response for status/finalize:**

```json
{
  "success": true,
  "data": {
    "step": "completed",
    "credentials": {
      "client_id": "AYx...",  // Should start with "A"
      "client_secret": "EC..."  // Should start with "E" or "A"
    }
  }
}
```

If credentials are missing:
1. Check PayPal Partner API response in server logs
2. Verify `oauth_integrations` array is present
3. Check `partner_client_id` and `partner_client_secret` fields

---

## Security Checks

### Verify Credential Redaction

1. Check server logs (numinix.com)
2. Search for "finalize_success" or "status_success" events
3. Verify credentials are NOT in logs:
   ```
   ✅ Good: "credentials": "[redacted]"
   ❌ Bad: "credentials": {"client_id": "AY...", ...}
   ```

### Verify HTTPS

1. Open browser DevTools → Network
2. Check all API requests use HTTPS
3. Verify no mixed content warnings

### Verify Session Security

1. Check nonce is validated on each request
2. Verify session timeout after inactivity
3. Test with different browser tabs (session should be shared)

---

## Troubleshooting

### Credentials Not Returned

**Problem:** Status shows "completed" but no credentials

**Debugging:**
1. Check PayPal Partner API credentials are correct
2. Review server logs for API errors
3. Verify merchant account has permissions granted
4. Check `oauth_integrations` array in raw API response

**Resolution:** May need to retry onboarding or contact PayPal support

---

### Database Save Fails

**Problem:** Credentials displayed but not saved

**Debugging:**
1. Check database permissions
2. Verify configuration keys exist in `configuration` table
3. Review PHP error logs

**Resolution:** Manually enter credentials if auto-save fails

---

### Popup Closes Immediately

**Problem:** PayPal popup opens and closes

**Debugging:**
1. Check browser console for JavaScript errors
2. Verify popup blocker settings
3. Check PayPal URL is valid

**Resolution:** Allow popups and retry

---

## Regression Testing

Ensure existing functionality still works:

1. **Legacy redirect flow:** Test with old ISU link format
2. **Manual credential entry:** Verify still works
3. **Module installation:** Test fresh install
4. **Module updates:** Test upgrade from previous version
5. **Payment processing:** Verify payments work with ISU credentials

---

## Performance Testing

1. **Popup open time:** Should be < 2 seconds
2. **Status polling:** Should complete within 10-30 seconds
3. **Credential display:** Should appear within 5 seconds of completion
4. **Database save:** Should complete within 1 second

---

## Browser Compatibility

Test in these browsers:
- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)
- ✅ Mobile Safari (iOS)
- ✅ Chrome Mobile (Android)

---

## Checklist

Before marking as complete:

- [ ] Scenario 1: Admin ISU happy path
- [ ] Scenario 2: Numinix.com direct ISU
- [ ] Scenario 3: Popup blocker handling
- [ ] Scenario 4: Cancellation handling
- [ ] Scenario 5: Network error handling
- [ ] Scenario 6: Multiple environments
- [ ] Credentials properly extracted
- [ ] Credentials redacted from logs
- [ ] HTTPS enforced
- [ ] Session security verified
- [ ] Regression tests pass
- [ ] Browser compatibility confirmed
- [ ] Documentation updated

---

## Reporting Issues

When reporting issues, include:

1. **Scenario** being tested
2. **Browser** and version
3. **Environment** (sandbox/live)
4. **Steps to reproduce**
5. **Expected behavior**
6. **Actual behavior**
7. **Screenshots** (if applicable)
8. **Console logs** (browser DevTools)
9. **Server logs** (if available)
10. **Network responses** (from DevTools)
