# PayPal Environment Detection Fix

## Problems Fixed

### 1. Environment Mismatch - Production Subscriptions Using Sandbox Credentials

**Symptom:**
```
PayPalAdvancedCheckoutApi::__construct: REST credentials for the sandbox environment are not fully configured.
The curlPost (v1/oauth2/token) request failed with invalid credentials.
"10002": "https://api-m.sandbox.paypal.com/v1/oauth2/token"
```

**Root Cause:**

The `get_paypal_rest_client()` method in `paypalSavedCardRecurring.php` was looking for non-existent constants:
- `MODULE_PAYMENT_PAYPALAC_CLIENT_ID`
- `MODULE_PAYMENT_PAYPALAC_CLIENT_SECRET`
- `MODULE_PAYMENT_PAYPALAC_ENVIRONMENT`

Since these constants don't exist, it would:
1. Default to empty credentials
2. Default to 'sandbox' environment
3. Fail to authenticate with PayPal

This meant subscriptions created in **production** would try to use **sandbox** API endpoints with empty/invalid credentials.

### 2. Fatal Error - Missing Email Constants

**Symptom:**
```
PHP Fatal error: Undefined constant "SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL"
```

**Root Cause:**

The cron script referenced email template constants that were never defined:
- `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL`
- `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT`
- `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL`
- `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT`

## Solutions Implemented

### Fix 1: Correct Environment Detection

**File:** `includes/classes/paypalSavedCardRecurring.php` (lines 58-77)

**Before:**
```php
$clientId = defined('MODULE_PAYMENT_PAYPALAC_CLIENT_ID') ? MODULE_PAYMENT_PAYPALAC_CLIENT_ID : '';
$clientSecret = defined('MODULE_PAYMENT_PAYPALAC_CLIENT_SECRET') ? MODULE_PAYMENT_PAYPALAC_CLIENT_SECRET : '';
$environment = '';
if (defined('MODULE_PAYMENT_PAYPALAC_ENVIRONMENT')) {
    $environment = MODULE_PAYMENT_PAYPALAC_ENVIRONMENT;
}
// ... defaults to sandbox
```

**After:**
```php
// Determine environment from MODULE_PAYMENT_PAYPALAC_SERVER
$environment = 'sandbox'; // Default to sandbox
if (defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
    $environment = strtolower(MODULE_PAYMENT_PAYPALAC_SERVER);
}

// Get the appropriate credentials based on environment
if ($environment === 'live') {
    $clientId = defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_L : '';
    $clientSecret = defined('MODULE_PAYMENT_PAYPALAC_SECRET_L') ? MODULE_PAYMENT_PAYPALAC_SECRET_L : '';
} else {
    $clientId = defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S') ? MODULE_PAYMENT_PAYPALAC_CLIENTID_S : '';
    $clientSecret = defined('MODULE_PAYMENT_PAYPALAC_SECRET_S') ? MODULE_PAYMENT_PAYPALAC_SECRET_S : '';
}
```

### Fix 2: Define Email Constants

**File:** `cron/paypal_saved_card_recurring.php` (lines 20-49)

Added constant definitions with default email templates:

```php
// Define email constants if not already defined
if (!defined('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL')) {
    define('SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL', 
        'Dear %s,' . "\n\n" .
        'We were unable to process your recurring payment for %s.' . "\n\n" .
        'Card ending in: %s' . "\n\n" .
        'After multiple attempts, we could not complete the transaction. Please update your payment method to continue your subscription for %s.' . "\n\n" .
        'Thank you for your business.'
    );
}
// ... and 3 more constants
```

## How Environment Detection Works Now

### 1. Configuration Constants

These constants are defined in the PayPal payment module configuration:

| Constant | Purpose | Values |
|----------|---------|--------|
| `MODULE_PAYMENT_PAYPALAC_SERVER` | Determines environment | 'live' or 'sandbox' |
| `MODULE_PAYMENT_PAYPALAC_CLIENTID_L` | Live Client ID | Your production client ID |
| `MODULE_PAYMENT_PAYPALAC_SECRET_L` | Live Secret | Your production secret |
| `MODULE_PAYMENT_PAYPALAC_CLIENTID_S` | Sandbox Client ID | Your sandbox client ID |
| `MODULE_PAYMENT_PAYPALAC_SECRET_S` | Sandbox Secret | Your sandbox secret |

### 2. Environment Selection Flow

```
1. Check MODULE_PAYMENT_PAYPALAC_SERVER
   ├─ If 'live' → Use CLIENTID_L and SECRET_L
   └─ Otherwise → Use CLIENTID_S and SECRET_S (default)

2. Initialize PayPalAdvancedCheckoutApi with:
   - Environment ('live' or 'sandbox')
   - Client ID (from appropriate constant)
   - Client Secret (from appropriate constant)

3. API calls go to correct endpoint:
   - Live: https://api-m.paypal.com
   - Sandbox: https://api-m.sandbox.paypal.com
```

### 3. Consistency Across Codebase

The fix aligns with how other parts of the code determine environment:

**Example from `PayPalAdvancedCheckoutApi.php`:**
```php
if (defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
    $configured = strtolower((string)MODULE_PAYMENT_PAYPALAC_SERVER);
    if ($configured === 'live') {
        return 'live';
    }
}
return 'sandbox';
```

## Testing

### Automated Test: PayPalEnvironmentDetectionTest.php

Verifies:
1. ✓ Uses `MODULE_PAYMENT_PAYPALAC_SERVER` for environment detection
2. ✓ Uses `CLIENTID_L` and `SECRET_L` for live environment
3. ✓ Uses `CLIENTID_S` and `SECRET_S` for sandbox environment
4. ✓ No old incorrect constant names remain
5. ✓ Email constants are properly defined

Run the test:
```bash
php tests/PayPalEnvironmentDetectionTest.php
```

### Manual Verification

1. **Check Environment Configuration:**
   - Admin → Modules → Payment → PayPal Commerce Platform
   - Verify "Transaction Server" is set correctly ('live' or 'sandbox')
   - Verify correct credentials are entered for your environment

2. **Monitor Cron Logs:**
   - Look for successful authentication (no "invalid credentials" errors)
   - Verify API calls go to correct endpoint
   - Check for absence of fatal errors

3. **Test Recurring Payment:**
   - Run cron: `/shop/cron/paypal_saved_card_recurring.php`
   - Check logs for:
     ```
     PayPal REST createOrder request: {...}
     PayPal REST createOrder raw response: {...}
     ```
   - Should see valid order ID in response (not `false`)

## Impact

### Before Fix
- ❌ Production subscriptions failed with invalid credentials
- ❌ API calls went to wrong environment
- ❌ Fatal errors prevented cron from completing
- ❌ Customers didn't receive failure notifications

### After Fix
- ✅ Production subscriptions use production credentials
- ✅ Sandbox subscriptions use sandbox credentials
- ✅ API calls go to correct endpoint
- ✅ No fatal errors
- ✅ Customers receive appropriate email notifications

## Related Files

- `includes/classes/paypalSavedCardRecurring.php` - Environment detection fix
- `cron/paypal_saved_card_recurring.php` - Email constants definition
- `tests/PayPalEnvironmentDetectionTest.php` - Test coverage
- `includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/PayPalAdvancedCheckoutApi.php` - Reference implementation

## Change History

- **2026-01-30**: Fixed environment detection to use correct constants
- **2026-01-30**: Added email constant definitions to prevent fatal errors
- **Previous**: Wrong constants caused all subscriptions to fail with invalid credentials
