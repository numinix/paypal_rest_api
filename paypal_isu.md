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

## PayPal ISU Flow (Per Official Documentation)

According to PayPal's Partner Referrals API documentation (https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/):

1. **Partner creates referral** → POST `/v2/customer/partner-referrals`
   - Required fields:
     - `operations`: Array with API_INTEGRATION configuration
     - `products`: Array like ["PPCP"] for PayPal Complete Payments
     - `legal_consents`: Array with SHARE_DATA_CONSENT granted
     - `tracking_id`: Single-use token for seller identification
   - Response includes:
     - `partner_referral_id`: Reference ID for this onboarding
     - `links`: Array with `action_url` for seller signup
   - Must configure proper return URL in app settings or request

2. **Merchant completes signup** → Clicks action_url, signs in/creates account
   - PayPal validates merchant
   - Merchant grants permissions to partner
   - PayPal screens for risk and compliance

3. **PayPal redirects to return_url** → URL includes GET parameters:
   - `merchantIdInPayPal` or `merchantId`: Seller's PayPal merchant ID
   - **`authCode`**: Temporary authorization code (expires quickly)
   - **`sharedId`**: Partner-merchant relationship ID (may be same as tracking_id)
   - `permissionsGranted`: true/false
   - `consentStatus`: true/false
   - `isEmailConfirmed`: true/false
   - `accountStatus`: BUSINESS_ACCOUNT, etc.

4. **Partner exchanges authCode** → POST `/v1/oauth2/token`
   - Required:
     - `grant_type=authorization_code`
     - `code={authCode}` 
     - Partner credentials in Basic Auth header
   - Response:
     - `access_token`: Seller's access token for API calls
     - `token_type`: "Bearer"
     - `expires_in`: Token lifetime in seconds
     - May include `refresh_token` for long-term access

5. **Partner retrieves seller credentials**
   - Option A: Use seller's access token directly for API calls on their behalf
   - Option B: Call merchant integration endpoint to get seller's client_id/secret
   - Note: Per PayPal docs, after onboarding the partner receives credentials/tokens
   - Store these securely for making API calls on seller's behalf

## Critical Discovery: What the ISU Flow Actually Provides

Based on PayPal's official documentation, there's an important clarification about what the Integrated Sign-Up flow provides:

### What ISU Does Provide:
1. ✅ Onboards sellers to use PayPal for payments
2. ✅ Establishes partner-seller relationship
3. ✅ Returns `authCode` that can be exchanged for seller's access token
4. ✅ Returns `merchantId` and `sharedId` for tracking
5. ✅ Grants partner permission to make API calls on seller's behalf

### What ISU Does NOT Directly Provide:
1. ❌ Seller's raw API credentials (client_id and secret) via API
2. ❌ Automatic credential provisioning for seller's own use

### The Key Question:
**What is the goal of our ISU implementation?**

**Option A: Partner makes API calls on behalf of seller**
- Use the access_token from authCode exchange
- Partner's system processes payments for seller using seller's token
- ✅ This is what Partner Referrals API is designed for

**Option B: Seller gets their own credentials to use independently**
- Seller needs to create app in PayPal Developer Dashboard themselves
- Seller retrieves client_id/secret from their dashboard
- ❌ This is NOT what Partner Referrals API provides automatically

### Our Current Implementation Assumption:
Looking at the logs and code, it appears we're trying to retrieve and display seller credentials (client_id/secret) to the user. **This may not align with how PayPal's Partner Referrals API actually works.**

### Recommended Next Steps:
1. **Clarify the business requirement**: What credentials does the seller need and for what purpose?
2. **If sellers need their own credentials**: They should create an app in PayPal Developer Dashboard
3. **If partner system will make calls**: Use the access_token from authCode exchange
4. **Consider hybrid approach**: Guide sellers to create their own app after ISU completes

---

## Research Phases

### Phase 1: Understand PayPal's Return Mechanism ✅

**Objective:** Determine exactly how PayPal returns authCode and sharedId

**Status:** COMPLETED based on official PayPal documentation review

**Findings from PayPal Documentation:**
- ✅ authCode and sharedId are returned as **GET parameters** in the return_url
- ✅ Parameter names are: `authCode`, `sharedId`, `merchantId` (or `merchantIdInPayPal`)
- ✅ Additional parameters: `permissionsGranted`, `consentStatus`, `isEmailConfirmed`, `accountStatus`
- ✅ The `operations` field in referral request MUST include API_INTEGRATION
- ✅ The `products` field should specify products like "PPCP" (PayPal Complete Payments)
- ✅ Legal consent SHARE_DATA_CONSENT must be granted for data sharing
- ✅ Partner account must be approved by PayPal for Partner Referrals API access
- ✅ Same behavior for sandbox and production (different endpoints but same flow)

**Key Requirements Identified:**
1. Partner account must be approved for Partner Referrals API
2. Referral request must include proper `operations` configuration:
   ```json
   "operations": [{
     "operation": "API_INTEGRATION",
     "api_integration_preference": {
       "rest_api_integration": {
         "integration_method": "PAYPAL",
         "integration_type": "FIRST_PARTY",
         "first_party_details": {
           "features": ["PAYMENT", "REFUND"]
         }
       }
     }
   }]
   ```
3. Must include `legal_consents` with SHARE_DATA_CONSENT
4. Return URL must be registered in partner app settings (or passed in request)

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

### Phase 4: Review OAuth Token Exchange ✅

**Objective:** Ensure we can properly exchange authCode for access token

**Status:** COMPLETED based on official PayPal documentation

**Findings:**

The OAuth token exchange endpoint: `POST /v1/oauth2/token`

**Required Headers:**
```
Authorization: Basic {base64(partner_client_id:partner_secret)}
Content-Type: application/x-www-form-urlencoded
```

**Required Body Parameters:**
```
grant_type=authorization_code
code={authCode}
```

**Optional (but often required):**
```
redirect_uri={same_uri_used_in_referral}
```

**Response Structure:**
```json
{
  "access_token": "A21AAHqn...",
  "token_type": "Bearer",
  "expires_in": 32400,
  "scope": "...",
  "refresh_token": "..." (optional, for long-term access)
}
```

**Important Notes:**
- The authCode expires quickly (typically minutes), so exchange must happen immediately
- The `sharedId` is NOT used in token exchange - it's for tracking/correlation only
- Partner credentials (not seller credentials) are used for Basic Auth
- The resulting access_token belongs to the seller and allows partner to act on their behalf
- Token expiration should be tracked; refresh tokens can extend access

**Error Handling:**
- Invalid/expired authCode: Returns 401 UNAUTHORIZED
- Wrong credentials: Returns 401 UNAUTHORIZED  
- Missing parameters: Returns 400 BAD_REQUEST

---

### Phase 5: Investigate Merchant Details API ✅

**Objective:** Understand how to use seller's access token after exchange

**Status:** COMPLETED based on official PayPal documentation

**Findings:**

After obtaining the seller's access token via authCode exchange, the partner has two options:

**Option 1: Direct API Calls (Recommended)**
- Use the seller's access_token directly in API calls
- The token allows the partner to make API calls on behalf of the seller
- Include in Authorization header: `Bearer {seller_access_token}`
- Typical operations: Create orders, process payments, issue refunds
- This is the standard pattern for partner/platform integrations

**Option 2: Retrieve Seller Credentials (Advanced)**
- Call merchant integration endpoint (if needed for specific use cases)
- Endpoint: `/v1/customer/partners/{partner-id}/merchant-integrations/{merchant-id}`
- May return seller's own API credentials (client_id/secret)
- Note: This is typically NOT needed for standard partner integrations

**For Our Use Case:**
The goal is to provide the seller with their own API credentials (client_id and secret) that they can use independently. According to PayPal documentation:

1. **The seller's credentials are managed by the seller themselves** through their PayPal dashboard
2. **Partners receive an access token** to act on seller's behalf
3. **For credential display**: The seller should retrieve their own credentials from PayPal Developer Dashboard (https://developer.paypal.com → Apps & Credentials)

**Important Clarification:**
- Partners do NOT receive the seller's raw client_id/secret through the API
- Partners receive an access_token that grants them permission to act on seller's behalf
- If the goal is to give sellers their own credentials to manage:
  - Sellers must log into PayPal Developer Dashboard
  - Create an app under "Apps & Credentials"
  - Their client_id and secret appear there
  
**Alternative Approach:**
If the ISU flow is meant to automatically provision API credentials for the seller:
- This may require PayPal's Embedded Onboarding SDK approach
- Or sellers manually create apps after onboarding
- Documentation: https://developer.paypal.com/docs/multiparty/embedded-integration/

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

### Questions Answered by PayPal Documentation:

1. **Does our partner account have proper permissions?** ✅ PARTIALLY ANSWERED
   - ✅ Must be approved PayPal partner with Partner Referrals API access
   - ⚠️ NEED TO VERIFY: Is our Numinix partner account approved and configured?

2. **What's the correct referral API payload?** ✅ ANSWERED
   - ✅ operations: API_INTEGRATION with rest_api_integration details
   - ✅ products: ["PPCP"] or other PayPal products
   - ✅ legal_consents: SHARE_DATA_CONSENT must be granted
   - ✅ tracking_id: Required for seller identification

3. **How exactly does PayPal return authCode/sharedId?** ✅ ANSWERED
   - ✅ GET parameters in return URL
   - ✅ Parameter names: authCode, sharedId, merchantId (or merchantIdInPayPal)
   - ✅ Additional: permissionsGranted, consentStatus, isEmailConfirmed, accountStatus

4. **Which API endpoint gives us the credentials?** ⚠️ CLARIFIED
   - ⚠️ Token exchange at `/v1/oauth2/token` returns **access_token**, not raw credentials
   - ⚠️ access_token allows partner to act on seller's behalf
   - ⚠️ Seller's raw client_id/secret are NOT available via Partner Referrals API
   - ❓ NEED TO CLARIFY: What credentials does the seller actually need?

5. **Are there different flows for sandbox vs. live?** ✅ ANSWERED
   - ✅ Same flow, different endpoints
   - ✅ Sandbox: api-m.sandbox.paypal.com
   - ✅ Live: api-m.paypal.com

### New Critical Questions:

6. **What is the business goal of ISU in our implementation?** ❓ NEEDS CLARIFICATION
   - Option A: Numinix makes API calls on behalf of seller (uses access_token)
   - Option B: Seller gets credentials to use independently (needs manual app creation)
   - Option C: Hybrid approach (guide seller to create app after ISU)

7. **What should we display to the user after successful ISU?** ❓ NEEDS CLARIFICATION
   - If Option A: Display success message, store access_token server-side
   - If Option B: Guide user to PayPal Developer Dashboard to create app
   - If Option C: Show both - success + instructions for manual credential creation

8. **How are we currently using these credentials?** ❓ NEEDS INVESTIGATION
   - Does paypalr module make calls on behalf of seller?
   - Or does seller's Zen Cart store make direct API calls?
   - This determines which credentials are actually needed

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

## References and Documentation

### Official PayPal Documentation (Reviewed):
1. **Build Onboarding for Sellers**  
   https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
   - Primary guide for Partner Referrals API
   - Details on operations, products, legal consents
   - Sample request/response structures

2. **Partner Referrals API Reference**  
   https://developer.paypal.com/docs/api/partner-referrals/v2/
   - Complete API specification
   - Endpoint details and parameters
   - Error codes and handling

3. **Create Onboarding Credentials**  
   https://developer.paypal.com/docs/multiparty/embedded-integration/create-onboarding-credentials/
   - How partners obtain onboarding credentials
   - Tracking ID usage
   - Webhook configuration

4. **Onboard Sellers Before Payment**  
   https://developer.paypal.com/docs/multiparty/seller-onboarding/before-payment/
   - Pre-payment onboarding flow
   - Seller screening and validation
   - Status tracking

5. **OAuth 2.0 Authentication**  
   https://developer.paypal.com/api/rest/authentication/
   - Token exchange details
   - Grant types including authorization_code
   - Token refresh and expiration

6. **Get Access Token**  
   https://developer.paypal.com/reference/get-an-access-token/
   - Endpoint reference for token requests
   - Header and parameter requirements

### Community Resources:
7. **Partner Referrals Community Discussions**  
   https://www.paypal-community.com/t5/REST-APIs/bd-p/rest-api
   - Real-world troubleshooting
   - authCode/sharedId common issues
   - Partner onboarding questions

### Key Insights from Documentation Review:
- ✅ authCode and sharedId ARE returned as GET parameters (confirmed)
- ✅ operations array must include API_INTEGRATION with proper configuration
- ✅ SHARE_DATA_CONSENT must be granted in legal_consents
- ✅ Partner account must be pre-approved by PayPal
- ⚠️ Partner Referrals API provides access_token, NOT seller's raw credentials
- ⚠️ Sellers get their own credentials from PayPal Developer Dashboard, not via API

---

**Document Version:** 2.0  
**Created:** 2025-12-20  
**Updated:** 2025-12-20 (Added official PayPal documentation findings)  
**Status:** Updated with PayPal official documentation - Ready for Phase 2  
**Next Update:** After current code analysis (Phase 2)
