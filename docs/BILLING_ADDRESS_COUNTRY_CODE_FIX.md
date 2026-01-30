# Billing Address country_code Fix

## Problem Fixed

PayPal API was rejecting recurring payment createOrder requests with the following error:

```json
{
    "errNum": 400,
    "name": "INVALID_REQUEST",
    "message": "Request is not well-formed, syntactically incorrect, or violates schema.",
    "details": [
        {
            "field": "/payment_source/card/billing_address/country_code",
            "location": "body",
            "issue": "MISSING_REQUIRED_PARAMETER",
            "description": "A required field / parameter is missing."
        }
    ]
}
```

## Root Cause

When creating orders with vaulted cards, the billing_address retrieved from PayPal's vault doesn't always include the `country_code` field. PayPal's API now **requires** the country_code field in all billing addresses.

### Missing Field

**Before (incomplete - rejected by PayPal):**
```json
{
  "billing_address": {
    "address_line_1": "1244 Dewar Way",
    "postal_code": "V3C 5Z1",
    "admin_area_2": "Port Coquitlam",
    "admin_area_1": "BC"
  }
}
```

**After (complete - accepted by PayPal):**
```json
{
  "billing_address": {
    "address_line_1": "1244 Dewar Way",
    "postal_code": "V3C 5Z1",
    "admin_area_2": "Port Coquitlam",
    "admin_area_1": "BC",
    "country_code": "CA"
  }
}
```

## Why This Happened

1. **Vault Cards Store Incomplete Data**: When cards are vaulted with PayPal, the billing_address may not include country_code
2. **API Requirements Changed**: PayPal now enforces country_code requirement (previously optional)
3. **Code Trusted Vault Data**: The `build_billing_address_from_card()` method returned vault billing_address as-is without validation

## Solution Implemented

### Code Changes

**File:** `includes/classes/paypalSavedCardRecurring.php`

#### 1. Enhanced `build_billing_address_from_card()` Method (lines 296-313)

**Before:**
```php
if (isset($vaultCard['billing_address']) && is_array($vaultCard['billing_address']) && count($vaultCard['billing_address']) > 0) {
    return $vaultCard['billing_address'];  // Returned as-is, might be missing country_code
}
```

**After:**
```php
if (isset($vaultCard['billing_address']) && is_array($vaultCard['billing_address']) && count($vaultCard['billing_address']) > 0) {
    // Use vault card's billing address but ensure country_code is present
    $billing = $vaultCard['billing_address'];
    
    // If country_code is missing, try to get it from customer's address
    if (!isset($billing['country_code']) || $billing['country_code'] === '') {
        $customers_id = $this->determineCardCustomerId($cardDetails);
        $countryCode = $this->getCustomerCountryCode($customers_id, $cardDetails);
        if ($countryCode !== '') {
            $billing['country_code'] = $countryCode;
        }
    }
    
    return $billing;
}
```

#### 2. Added `getCustomerCountryCode()` Helper Method (lines 361-385)

```php
protected function getCustomerCountryCode($customers_id, $cardDetails) {
    global $db;
    
    // Get customer's default address
    $addressId = isset($cardDetails['address_id']) ? (int) $cardDetails['address_id'] : 0;
    if ($addressId <= 0 && $customers_id > 0) {
        $customerLookup = $db->Execute("SELECT customers_default_address_id FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int) $customers_id . " LIMIT 1");
        if (!$customerLookup->EOF) {
            $addressId = (int) $customerLookup->fields['customers_default_address_id'];
        }
    }
    
    if ($addressId <= 0) {
        return '';
    }
    
    // Get country from address
    $address = $db->Execute("SELECT entry_country_id FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int) $addressId . " LIMIT 1");
    if ($address->EOF) {
        return '';
    }
    
    // Get ISO 2-letter country code
    $countryCode = '';
    if (isset($address->fields['entry_country_id']) && (int) $address->fields['entry_country_id'] > 0) {
        $country = zen_get_countries($address->fields['entry_country_id']);
        if (isset($country['countries_iso_code_2'])) {
            $countryCode = $country['countries_iso_code_2'];
        }
    }
    
    return $countryCode;
}
```

## How It Works

### Flow Diagram

```
1. Process recurring payment
   ↓
2. Build card payload with billing_address
   ↓
3. Check if vault card has billing_address
   ↓ (YES)
4. Use vault billing_address
   ↓
5. Check if country_code is present
   ↓ (NO - missing)
6. Get customer ID from card details
   ↓
7. Call getCustomerCountryCode()
   ↓
8. Retrieve customer's default address
   ↓
9. Get country from address
   ↓
10. Convert to ISO 2-letter code (CA, US, etc.)
    ↓
11. Add country_code to billing_address
    ↓
12. Return complete billing_address with country_code
```

### Example Scenarios

#### Scenario 1: Vault Card Missing country_code

**Input (vault card billing_address):**
```json
{
  "address_line_1": "1244 Dewar Way",
  "postal_code": "V3C 5Z1",
  "admin_area_2": "Port Coquitlam",
  "admin_area_1": "BC"
}
```

**Customer Address in Database:**
- Country ID: 38 (Canada)
- ISO Code: "CA"

**Output (enhanced billing_address):**
```json
{
  "address_line_1": "1244 Dewar Way",
  "postal_code": "V3C 5Z1",
  "admin_area_2": "Port Coquitlam",
  "admin_area_1": "BC",
  "country_code": "CA"
}
```

#### Scenario 2: Vault Card Already Has country_code

**Input:**
```json
{
  "address_line_1": "123 Main St",
  "postal_code": "90210",
  "admin_area_2": "Beverly Hills",
  "admin_area_1": "CA",
  "country_code": "US"
}
```

**Output (unchanged):**
```json
{
  "address_line_1": "123 Main St",
  "postal_code": "90210",
  "admin_area_2": "Beverly Hills",
  "admin_area_1": "CA",
  "country_code": "US"
}
```

## Country Code Format

The country_code must be in **ISO 3166-1 alpha-2** format (2-letter codes):

| Country | Code |
|---------|------|
| Canada | CA |
| United States | US |
| United Kingdom | GB |
| Australia | AU |
| Germany | DE |
| France | FR |

The `zen_get_countries()` function provides the correct ISO codes from the Zen Cart database.

## Testing

### Automated Test: BillingAddressCountryCodeTest.php

Verifies:
1. ✓ Checks for missing country_code in vault card billing_address
2. ✓ Adds country_code when missing
3. ✓ Helper method exists and retrieves ISO country code
4. ✓ Billing array includes country_code field

Run the test:
```bash
php tests/BillingAddressCountryCodeTest.php
```

### Manual Verification

Check the logs for complete billing_address:

```
[30-Jan-2026 17:14:18] PayPal REST cardPayload: {
  "vault_id": "...",
  "billing_address": {
    "address_line_1": "1244 Dewar Way",
    "postal_code": "V3C 5Z1",
    "admin_area_2": "Port Coquitlam",
    "admin_area_1": "BC",
    "country_code": "CA"
  },
  ...
}
```

**Before fix:** Missing country_code, PayPal returns 400 error
**After fix:** country_code present, PayPal accepts request

## Impact

### Before Fix
- ❌ All recurring payments with vaulted cards failed
- ❌ PayPal returned MISSING_REQUIRED_PARAMETER error
- ❌ Subscriptions couldn't be processed
- ❌ Manual intervention required

### After Fix
- ✅ country_code added automatically when missing
- ✅ Billing address meets PayPal's requirements
- ✅ Recurring payments process successfully
- ✅ No manual intervention needed
- ✅ Backward compatible (doesn't break existing country_codes)

## Edge Cases Handled

### 1. Customer Without Default Address
If customer has no default address:
- `getCustomerCountryCode()` returns empty string
- billing_address may still be missing country_code
- PayPal may reject (but this is rare - customers usually have addresses)

### 2. Invalid Country ID
If country ID doesn't map to a valid country:
- `zen_get_countries()` returns empty/null
- country_code not added
- Falls back to original vault data

### 3. Already Has country_code
If vault billing_address already has country_code:
- Condition check skips country code lookup
- Original value preserved
- No unnecessary database queries

## Related Files

- `includes/classes/paypalSavedCardRecurring.php` - Main fix
- `tests/BillingAddressCountryCodeTest.php` - Test coverage
- `docs/BILLING_ADDRESS_COUNTRY_CODE_FIX.md` - This documentation

## Change History

- **2026-01-30**: Added country_code to billing_address for PayPal API compliance
- **Previous**: All recurring payments failed with MISSING_REQUIRED_PARAMETER error for country_code
