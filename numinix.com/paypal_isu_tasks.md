# PayPal ISU Token Exchange Issue - Implementation Tasks

## Issue Summary

The PayPal ISU (In-Signup Upgrade) process on Numinix.com is getting stuck at the step to get the seller access token. The token exchange request is failing with:

```
Status: 401
Error: invalid_request
Error Description: Authorization Header must have client_id and secret
```

## Root Cause Analysis

After analyzing the logs and comparing against PayPal's documentation, the issue is clear:

### PayPal Documentation Requirements

Per https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/:

```bash
curl -X POST https://api-m.sandbox.paypal.com/v1/oauth2/token
-u SHARED-ID:
-d 'grant_type=authorization_code&code=AUTH-CODE&code_verifier=SELLER-TOKEN'
```

Expected response structure:
```json
{
  "scope": "https://uri.paypal.com/services/payments/realtimepayment ...",
  "access_token": "A23AAHclqoiifoeiP9H4jLNZ7OJjcPlvdANa3UoJ2Zq5qn_kg-...",
  "token_type": "Bearer",
  "expires_in": 28799,
  "refresh_token": "R23AAG9SXLtr70FIgRGYWzFeon5pA8lwC6cX7F9pvK4db83uxptI5...",
  "nonce": "2020-02-05T15:43:54ZiBnhkZ7DMRJpzXd_AhUCfHgT2fPBWicqo1r7A2zbAj8"
}
```

### Current Implementation (Broken)

File: `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`

Lines 276-283:
```php
// Per PayPal docs: For onboarded Complete Token flow, only grant_type and code are required
// Do NOT include code_verifier - it will cause "Code verifier does not match" error
$tokenBody = http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $authCode,
], '', '&', PHP_QUERY_RFC3986);
```

**The issue:** The comment is incorrect. The `code_verifier` parameter IS required according to PayPal's documentation. The `seller_nonce` generated during the partner referral creation is used as the `code_verifier` during the token exchange.

### Evidence from Logs

The debug log shows:
- `seller_nonce`: `ZrDifJAGxvziAJ2x8H_1EMxP3ip3FuFHUVr940mV9P-taA` (available but NOT used)
- `shared_id`: `BAATDonxByZOFWxsCyIrgJV_A_Bu4PpAGAkOYNWNdDwB5Ea7Kn5qCWODxcdCHjLEfVzNstKYt9pp6jWc3s` (used correctly as Basic auth username)
- `auth_code`: `C21AAOLY3JEu4vqEEjZdCNRz0h-O92sMo-r2xltLhJMwl5-wXbc3MsSSCR8oCxWrzodfUr8h_VOZJMm-billSe1jI6rn_xM9A` (used correctly as the `code` parameter)

The token exchange is missing the `code_verifier` parameter which causes PayPal to reject the request.

---

## Implementation Tasks

### Task 1: Fix Token Exchange Request Body ✅

**File:** `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`

**Change:** Update the `exchangeAuthCodeForCredentials` method to include `code_verifier` parameter with the `seller_nonce` value.

**Before:**
```php
$tokenBody = http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $authCode,
], '', '&', PHP_QUERY_RFC3986);
```

**After:**
```php
$tokenBody = http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $authCode,
    'code_verifier' => $sellerNonce,
], '', '&', PHP_QUERY_RFC3986);
```

### Task 2: Remove Incorrect Comment ✅

**File:** `numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php`

Remove or update the misleading comment that states `code_verifier` should NOT be included.

### Task 3: Validate seller_nonce is Present ✅

Ensure that if `seller_nonce` is not available, the code logs an appropriate warning and handles the error gracefully rather than sending an incomplete request.

---

## Flow Verification

After implementation, the token exchange should follow this flow:

1. **Partner Referral Creation** - Already working
   - Generates `seller_nonce` (stored in session and database)
   - Returns `action_url` for merchant onboarding

2. **Merchant Completes Onboarding in PayPal Modal** - Already working
   - PayPal returns `authCode` and `sharedId` via postMessage
   - These are captured and persisted

3. **Token Exchange** - THIS IS BROKEN
   - Request:
     ```
     POST /v1/oauth2/token
     Authorization: Basic {base64(sharedId:)}
     Content-Type: application/x-www-form-urlencoded
     
     grant_type=authorization_code&code={authCode}&code_verifier={seller_nonce}
     ```
   - Response:
     ```json
     {
       "access_token": "...",
       "refresh_token": "...",
       "token_type": "Bearer",
       "expires_in": 28799
     }
     ```

4. **Credential Retrieval** - Will work once token exchange is fixed
   - Use seller access token to call credentials endpoint
   - Store merchant's client_id and client_secret

---

## Additional Issue: Database Column Missing

After the token exchange was fixed, a second issue was discovered:

### Error
```
MySQL error 1054: Unknown column 'seller_client_id' in 'field list'
```

### Root Cause
The `nxp_paypal_persist_credentials` function attempts to update columns (`seller_client_id`, `seller_client_secret`, `seller_access_token`, `seller_access_token_expires_at`) that may not exist if the 1.0.7 installer hasn't been run.

### Fix (Two-Part Solution)

**Part 1: Graceful Handling (nxp_paypal_helpers.php)**
Modified `nxp_paypal_persist_credentials` to:
1. Check which columns exist using a single `SHOW COLUMNS` query
2. Skip credential persistence gracefully if required columns are missing
3. Dynamically build the UPDATE query based on available columns
4. Allow the flow to continue even if persistence fails (credentials are still available in memory)

**Part 2: Database Schema Update (1_0_9.php installer)**
Created new installer `1_0_9.php` that ensures all required columns exist:
- `auth_code` - VARCHAR(512) for storing PayPal auth code
- `shared_id` - VARCHAR(128) for storing PayPal shared ID
- `seller_access_token` - TEXT for storing seller access token
- `seller_access_token_expires_at` - DATETIME for token expiry
- `seller_client_id` - VARCHAR(255) for seller's client ID
- `seller_client_secret` - TEXT for seller's client secret

---

## Testing Checklist

- [x] Verify seller_nonce is properly generated and stored during partner referral
- [x] Verify seller_nonce is correctly retrieved during token exchange
- [x] Verify token exchange request includes all three parameters:
  - `grant_type=authorization_code`
  - `code={authCode}`
  - `code_verifier={seller_nonce}`
- [x] Verify Basic auth header uses `sharedId:` format (no secret)
- [x] Verify successful access_token is returned from PayPal
- [x] Verify credentials endpoint is called with seller access token
- [x] Verify merchant credentials are properly stored (requires running 1.0.9 installer)
