# PayPal Integrated Sign Up (ISU) - Implementation Task List

## Executive Summary

This document outlines the tasks required to fix the PayPal Integrated Sign Up (ISU) implementation to properly follow PayPal's Partner Referrals API documentation. The current implementation is not working because **authCode and sharedId are not being captured from PayPal's redirect**, which are required to exchange for API credentials.

## Current State Analysis

### What's Working:
1. ✅ User clicks button to launch onboarding
2. ✅ Numinix.com proxy creates signup URL via Partner Referrals API  
3. ✅ User completes PayPal modal/signup flow
4. ✅ PayPal redirects back to return URL

### What's NOT Working:
1. ❌ **authCode and sharedId are NOT being captured** from PayPal's redirect
2. ❌ Return URL may not be configured to receive these parameters
3. ❌ System gets stuck in "provisioning" polling loop indefinitely
4. ❌ No credentials are ever retrieved because authCode/sharedId are missing
5. ❌ User has to manually copy credentials (which never appear)

### Evidence from Logs:
```
[2025-12-20T06:11:55+00:00] Completion handler called {
    "has_merchant_id": true,
    "has_auth_code": false,      ← MISSING
    "has_shared_id": false,      ← MISSING
    "merchant_id_value": "4N75MD5JG25EQ"
}
```

## PayPal ISU Flow (Per Documentation)

According to PayPal's Partner Referrals API documentation:

1. **Partner creates referral** → POST `/v2/customer/partner-referrals`
   - Returns `action_url` (signup link)
   - Must include proper `return_url` and `tracking_id`

2. **Merchant completes signup** → Clicks action_url, signs in/creates account

3. **PayPal redirects to return_url** → URL includes:
   - `merchantIdInPayPal` or `merchantId`
   - **`authCode`** (authorization code - CRITICAL)
   - **`sharedId`** (merchant identifier - CRITICAL)
   - Other status parameters

4. **Partner exchanges authCode/sharedId** → POST `/v1/oauth2/token`
   - Uses `grant_type=authorization_code`
   - Uses `code={authCode}` 
   - Returns seller's access token

5. **Partner uses access token** → Make API calls on behalf of seller
   - Get merchant details
   - Retrieve API credentials
   - Configure payment settings

## Research Phases

### Phase 1: Understand PayPal's Return Mechanism ⏳

**Objective:** Determine exactly how PayPal returns authCode and sharedId

**Tasks:**
- [ ] Review PayPal Partner Referrals API documentation for return URL specification
- [ ] Identify what parameters PayPal includes in the redirect (GET parameters vs. POST)
- [ ] Determine if there's a specific format or configuration needed in the referral request
- [ ] Check if `operations` field in referral request affects what's returned
- [ ] Verify if `products` or `capabilities` requested impact the return parameters
- [ ] Research if there are different flows (modal vs. redirect) and their implications
- [ ] Document PayPal's exact expected return URL format
- [ ] Check if partner account needs specific permissions/configuration

**Deliverable:** 
- Document: `docs/paypal_isu_return_mechanism.md` explaining how authCode/sharedId are returned

**Questions to Answer:**
1. Are authCode and sharedId returned as GET parameters in the return_url?
2. Is there a difference between sandbox and production behavior?
3. Does the partner account need special permissions enabled?
4. What's the format: `?authCode=xxx&sharedId=yyy` or something else?

---

### Phase 2: Analyze Current Referral Request ⏳

**Objective:** Examine our current Partner Referrals API call to identify missing pieces

**Tasks:**
- [ ] Review the current payload sent to `/v2/customer/partner-referrals`
- [ ] Check if `return_url` is properly formatted and includes tracking_id
- [ ] Verify `operations` array includes correct API_INTEGRATION setup
- [ ] Confirm `products` array includes necessary products (e.g., EXPRESS_CHECKOUT)
- [ ] Check if `capabilities` should be specified
- [ ] Validate `partner_config_override` if needed
- [ ] Compare our request to PayPal's example requests in documentation
- [ ] Review Numinix.com's onboarding service implementation

**Files to Examine:**
- `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`
- `numinix.com/api/paypal_onboarding.php`
- `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`

**Deliverable:**
- Document current referral request structure
- List of discrepancies vs. PayPal documentation
- Specific fields that need to be added/modified

---

### Phase 3: Test Return URL in Isolation ⏳

**Objective:** Verify that return URL can receive and parse authCode/sharedId

**Tasks:**
- [ ] Create a test return URL endpoint that logs ALL incoming parameters
- [ ] Manually test PayPal signup with test return URL
- [ ] Verify authCode and sharedId appear in the parameters
- [ ] Document the exact parameter names and formats PayPal uses
- [ ] Test in both sandbox and production (if available)
- [ ] Check for any URL encoding issues
- [ ] Verify GET vs POST method used by PayPal
- [ ] Test with different browsers to ensure consistency

**Test Script Location:**
- Create: `numinix.com/test/paypal_return_test.php` (temporary test endpoint)

**Deliverable:**
- Confirmation that PayPal does/doesn't send authCode and sharedId
- If missing: Documentation of what's wrong with our setup
- If present: Exact parameter names and example values

---

### Phase 4: Review OAuth Token Exchange ⏳

**Objective:** Ensure we can properly exchange authCode for access token

**Tasks:**
- [ ] Review PayPal's `/v1/oauth2/token` endpoint documentation
- [ ] Understand the exact payload format for `authorization_code` grant
- [ ] Identify required headers (Authorization, Content-Type)
- [ ] Document the response structure (access_token, refresh_token, etc.)
- [ ] Check if sharedId is used in the token exchange or just for tracking
- [ ] Verify partner client credentials are configured correctly
- [ ] Test token exchange with mock authCode (if possible)
- [ ] Document token expiration and refresh process

**API Endpoint:**
```
POST https://api-m.sandbox.paypal.com/v1/oauth2/token
Authorization: Basic {base64(client_id:secret)}
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code={authCode}
```

**Deliverable:**
- Working example of token exchange
- Helper function specification for token exchange
- Error handling requirements

---

### Phase 5: Investigate Merchant Details API ⏳

**Objective:** Understand how to retrieve seller's API credentials after getting access token

**Tasks:**
- [ ] Review PayPal's merchant/seller details API endpoints
- [ ] Determine which endpoint returns API credentials (client_id, secret)
- [ ] Check if it's `/v1/customer/partners/{partner-id}/merchant-integrations/{merchant-id}`
- [ ] Verify authentication method (partner token vs. seller token)
- [ ] Document response structure and credential format
- [ ] Identify if there are different endpoints for sandbox vs. live
- [ ] Check permissions required to access merchant details
- [ ] Test with sandbox merchant if possible

**Potential Endpoints:**
- `/v1/customer/partners/{partner-id}/merchant-integrations/{merchant-id}`
- `/v1/identity/oauth2/userinfo?schema=paypalv1.1` (using seller token)

**Deliverable:**
- API endpoint specification
- Request/response examples
- Function to retrieve credentials

---

## Implementation Phases

### Phase 6: Fix Partner Referral Request ⏳

**Objective:** Update the referral API call to ensure authCode/sharedId are returned

**Tasks:**
- [ ] Update referral request payload based on Phase 2 findings
- [ ] Ensure `return_url` is properly formatted with all required parameters
- [ ] Add/modify `operations` array if needed
- [ ] Add/modify `products` array if needed
- [ ] Add `capabilities` if required
- [ ] Test referral creation in sandbox
- [ ] Verify returned `action_url` is correct
- [ ] Test complete signup flow to confirm authCode/sharedId appear

**Files to Modify:**
- `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`

**Testing:**
- Manual test: Complete signup and verify parameters in return URL
- Log all parameters received at return URL

**Success Criteria:**
- authCode and sharedId appear in return URL after signup

---

### Phase 7: Update Return URL Handler ⏳

**Objective:** Capture and process authCode and sharedId from PayPal's redirect

**Tasks:**
- [ ] Update completion handler to properly extract authCode and sharedId
- [ ] Add validation for these parameters
- [ ] Store them in session for subsequent API calls
- [ ] Add comprehensive logging
- [ ] Handle missing parameter errors gracefully
- [ ] Pass parameters to credential exchange function
- [ ] Update client-side JavaScript if needed to handle new flow

**Files to Modify:**
- `admin/paypalr_integrated_signup.php` (completion handler)
- `numinix.com/api/paypal_onboarding.php` (if handling server-side)
- `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`

**Testing:**
- Test with valid authCode/sharedId
- Test with missing parameters
- Test with invalid parameters
- Verify logging captures all cases

---

### Phase 8: Implement Token Exchange ⏳

**Objective:** Exchange authCode for seller's access token

**Tasks:**
- [ ] Create function to call `/v1/oauth2/token` endpoint
- [ ] Implement proper Basic authentication with partner credentials
- [ ] Build correct payload with grant_type and code
- [ ] Parse response to extract access_token
- [ ] Store access_token securely (encrypted if possible)
- [ ] Add error handling for expired/invalid codes
- [ ] Add retry logic for network failures
- [ ] Log token exchange events (without exposing token)
- [ ] Handle token expiration and refresh if needed

**New Function:**
```php
function nxp_paypal_exchange_auth_code(string $authCode, string $environment): array
{
    // Implementation
}
```

**Files to Modify:**
- `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`
- `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`

**Testing:**
- Test with valid authCode
- Test with expired authCode
- Test with invalid authCode
- Test error handling
- Verify token is stored correctly

---

### Phase 9: Implement Credential Retrieval ⏳

**Objective:** Use seller's access token to retrieve their API credentials

**Tasks:**
- [ ] Create function to call merchant details API
- [ ] Use seller's access token for authentication
- [ ] Parse response to extract client_id and secret
- [ ] Handle different credential formats (sandbox vs. live)
- [ ] Add error handling for API failures
- [ ] Store credentials in tracking database
- [ ] Return credentials to client admin
- [ ] Add logging for credential retrieval

**New Function:**
```php
function nxp_paypal_get_seller_credentials(string $accessToken, string $merchantId, string $environment): array
{
    // Implementation
}
```

**Files to Modify:**
- `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`
- `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`

**Testing:**
- Test with valid access token
- Test with expired token
- Test with different merchant types
- Verify credentials are correct format
- Test credentials work for API calls

---

### Phase 10: Update Client-Side Flow ⏳

**Objective:** Update admin UI to handle new credential retrieval flow

**Tasks:**
- [ ] Update JavaScript to handle authCode/sharedId from return URL
- [ ] Remove or reduce polling mechanism (may not be needed)
- [ ] Update status display to show credential retrieval progress
- [ ] Add error messages for authCode/sharedId missing
- [ ] Update success message to display credentials
- [ ] Add copy-to-clipboard functionality for credentials
- [ ] Test auto-save of credentials to module config
- [ ] Update loading states and progress indicators

**Files to Modify:**
- `admin/paypalr_integrated_signup.php`

**Testing:**
- Test complete flow from button click to credential display
- Test error scenarios (authCode missing, exchange failed, etc.)
- Test copy-to-clipboard
- Test auto-save functionality
- Verify user experience is smooth

---

### Phase 11: Remove Obsolete Provisioning Logic ⏳

**Objective:** Clean up old polling/provisioning code that's no longer needed

**Tasks:**
- [ ] Remove or refactor status polling that waits for provisioning
- [ ] Update `status` action to return credentials immediately
- [ ] Remove `finalize` action if it's redundant
- [ ] Clean up session management
- [ ] Update database schema if needed
- [ ] Remove unused helper functions
- [ ] Update comments and documentation

**Files to Modify:**
- `admin/paypalr_integrated_signup.php`
- `numinix.com/api/paypal_onboarding.php`
- `numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php`

**Testing:**
- Verify new flow works without old code
- Test that cleanup doesn't break anything
- Verify no dead code remains

---

### Phase 12: Security Review ⏳

**Objective:** Ensure the new flow is secure

**Tasks:**
- [ ] Validate all input parameters (authCode, sharedId, merchantId)
- [ ] Verify CSRF protection on all endpoints
- [ ] Check for XSS vulnerabilities in credential display
- [ ] Ensure access tokens are stored securely
- [ ] Verify credentials are transmitted over HTTPS only
- [ ] Add rate limiting to prevent abuse
- [ ] Review logging to ensure no sensitive data is exposed
- [ ] Validate return URL to prevent open redirects
- [ ] Test authorization checks on all endpoints
- [ ] Conduct security scan with automated tools

**Security Checklist:**
- [ ] Input validation on all parameters
- [ ] CSRF tokens validated
- [ ] No XSS vulnerabilities
- [ ] Sensitive data encrypted at rest
- [ ] HTTPS enforced
- [ ] Rate limiting implemented
- [ ] No secrets in logs
- [ ] No open redirects
- [ ] Authorization checks present
- [ ] Security scan completed

---

### Phase 13: Testing and Validation ⏳

**Objective:** Thoroughly test the complete ISU flow

**Tasks:**
- [ ] Test complete flow in sandbox from start to finish
- [ ] Test with new PayPal account creation
- [ ] Test with existing PayPal account login
- [ ] Test error scenarios (network failures, expired codes, etc.)
- [ ] Test in multiple browsers (Chrome, Firefox, Safari, Edge)
- [ ] Test with popup blockers enabled
- [ ] Test credential retrieval timing
- [ ] Verify credentials work for actual PayPal API calls
- [ ] Test environment switching (sandbox ↔ live)
- [ ] Perform load testing if applicable

**Test Scenarios:**
1. ✅ Happy path: New account creation, credentials retrieved
2. ✅ Happy path: Existing account login, credentials retrieved
3. ❌ User cancels during signup
4. ❌ Network failure during token exchange
5. ❌ Invalid authCode received
6. ❌ Merchant not properly onboarded
7. ❌ API rate limit exceeded
8. ❌ Session expired during process
9. ❌ Browser closed mid-flow
10. ✅ Credentials successfully saved to config

**Testing Checklist:**
- [ ] Sandbox complete flow works
- [ ] Live complete flow works (if available)
- [ ] All error scenarios handled
- [ ] All browsers work
- [ ] Credentials are valid
- [ ] Auto-save works
- [ ] Manual copy works
- [ ] Logging is comprehensive
- [ ] No console errors
- [ ] Performance is acceptable

---

### Phase 14: Documentation Updates ⏳

**Objective:** Document the new ISU flow for future maintenance

**Tasks:**
- [ ] Update README with ISU flow description
- [ ] Document the authCode/sharedId exchange process
- [ ] Create troubleshooting guide for common issues
- [ ] Add code comments to key functions
- [ ] Create sequence diagram of complete flow
- [ ] Document configuration requirements
- [ ] Update API documentation
- [ ] Create developer guide for ISU integration

**Documentation Files:**
- `README.md` - Update ISU section
- `docs/paypal_isu_flow.md` - Detailed flow documentation
- `docs/paypal_isu_troubleshooting.md` - Troubleshooting guide
- Inline code comments in all modified files

**Diagrams to Create:**
- Sequence diagram: Complete ISU flow
- Architecture diagram: System components
- Error flow diagram: Error handling paths

---

## Timeline Estimates

### Research Phases (1-5): 8-12 hours
- Phase 1: 2-3 hours (PayPal documentation review)
- Phase 2: 1-2 hours (Current code analysis)
- Phase 3: 2-3 hours (Testing return URL)
- Phase 4: 1-2 hours (OAuth review)
- Phase 5: 2-3 hours (Merchant API review)

### Implementation Phases (6-11): 12-16 hours
- Phase 6: 2-3 hours (Fix referral request)
- Phase 7: 2-3 hours (Update return handler)
- Phase 8: 2-3 hours (Token exchange)
- Phase 9: 2-3 hours (Credential retrieval)
- Phase 10: 2-3 hours (Client-side updates)
- Phase 11: 2-3 hours (Cleanup)

### Security & Testing (12-14): 8-10 hours
- Phase 12: 3-4 hours (Security review)
- Phase 13: 4-5 hours (Testing)
- Phase 14: 2-3 hours (Documentation)

**Total Estimated Time: 28-38 hours**

---

## Critical Questions to Resolve

### Before Starting Implementation:

1. **Does our partner account have proper permissions?**
   - Can we receive authCode and sharedId?
   - Are we approved for the Partner Referrals API?

2. **What's the correct referral API payload?**
   - What operations, products, capabilities are needed?
   - Is there a specific configuration for getting authCode back?

3. **How exactly does PayPal return authCode/sharedId?**
   - URL parameters? POST data? Fragment?
   - What are the exact parameter names?

4. **Which API endpoint gives us the credentials?**
   - Is it the merchant integration endpoint?
   - Do we need seller's token or partner token?

5. **Are there different flows for sandbox vs. live?**
   - Do we need different configurations?
   - Are the return mechanisms identical?

---

## Success Criteria

The implementation will be considered successful when:

1. ✅ User clicks "Complete PayPal Setup" button
2. ✅ PayPal modal/window opens with signup flow
3. ✅ User completes signup or logs in to existing account
4. ✅ PayPal redirects back with **authCode**, **sharedId**, and **merchantId**
5. ✅ System captures these parameters successfully
6. ✅ authCode is exchanged for seller's access token
7. ✅ Access token is used to retrieve API credentials
8. ✅ Credentials (client_id, secret) are displayed to user
9. ✅ Credentials are automatically saved to module config
10. ✅ User can copy credentials manually if auto-save fails
11. ✅ Complete flow takes < 30 seconds after signup
12. ✅ Error messages are clear and actionable
13. ✅ Logging captures all important events
14. ✅ Security review passes
15. ✅ All tests pass

---

## Dependencies and Prerequisites

### Partner Account Requirements:
- PayPal partner account with API credentials
- Partner approved for Partner Referrals API
- Sandbox and live environment configured
- Proper permissions for merchant onboarding

### Technical Requirements:
- PHP 7.1+ with cURL support
- HTTPS enabled on all return URLs
- Database access for credential storage
- Session support enabled

### Access Requirements:
- Access to PayPal Developer Dashboard
- Access to Numinix.com server configuration
- Access to client admin codebase
- Ability to test in sandbox environment

---

## Risk Assessment

### High Risk Items:
1. **PayPal API changes** - PayPal may have updated their ISU flow
   - Mitigation: Review latest documentation before starting

2. **Partner permissions** - We may not have necessary approvals
   - Mitigation: Verify partner account status first (Phase 1)

3. **Credential exposure** - Security vulnerability in credential handling
   - Mitigation: Comprehensive security review (Phase 12)

### Medium Risk Items:
1. **Browser compatibility** - Different browsers handle redirects differently
   - Mitigation: Test in all major browsers

2. **Network failures** - Token exchange or API calls may fail
   - Mitigation: Implement retry logic and error handling

### Low Risk Items:
1. **UI/UX issues** - Credential display may need refinement
   - Mitigation: User testing and feedback

---

## Rollback Plan

If implementation fails or causes issues:

1. **Code Rollback:** Revert to current implementation via git
2. **Database:** No schema changes planned, credentials stored separately
3. **User Impact:** Users can still complete PayPal setup via manual entry
4. **Testing:** Verify rollback in staging before production

---

## Next Steps

1. **Review this document** with team and stakeholders
2. **Approve phases** and timeline
3. **Begin Phase 1** (Research) to answer critical questions
4. **Create milestone tickets** for each phase
5. **Set up testing environment** for sandbox testing
6. **Schedule check-in meetings** after each phase

---

**Document Version:** 1.0  
**Created:** 2025-12-20  
**Status:** Ready for review and approval  
**Next Update:** After Phase 1 completion
