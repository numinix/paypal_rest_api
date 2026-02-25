# Undefined Email Constant Fix Summary

## Problem

The cron job `paypalac_saved_card_recurring.php` was failing at the end with:
```
PHP Fatal error: Undefined constant "MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL" 
in cron/paypalac_saved_card_recurring.php:687
```

This happened when the `notify_error()` method tried to send error notifications, **and** when the cron tried to send the summary email at the end.

## Root Cause

The constant was used in TWO places without checking if it was defined:

1. **Line 2549** in `includes/classes/paypalSavedCardRecurring.php` - `notify_error()` method
2. **Lines 687-688** in `cron/paypalac_saved_card_recurring.php` - Summary email

The constant `MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL` is:
- A payment module configuration constant
- Set during module installation/configuration
- May not exist if module isn't fully configured
- Causes fatal errors in PHP 8+ when undefined

## The Fix

Added `defined()` checks with a proper fallback chain in BOTH locations.

### Fix 1: notify_error() method (paypalSavedCardRecurring.php)

**Before:**
```php
function notify_error($subject, $message, $type = 'error', $customers_email = '', $customers_name = '') {
    $to = MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL;  // Fatal error!
    ...
}
```

**After:**
```php
function notify_error($subject, $message, $type = 'error', $customers_email = '', $customers_name = '') {
    $to = '';
    if (defined('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL')) {
        $to = MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL;
    } elseif (defined('STORE_OWNER_EMAIL_ADDRESS')) {
        $to = STORE_OWNER_EMAIL_ADDRESS;
    } elseif (defined('EMAIL_FROM')) {
        $to = EMAIL_FROM;
    }
    ...
}
```

### Fix 2: Cron summary email (paypalac_saved_card_recurring.php)

**Before:**
```php
zen_mail(
    MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL,  // Fatal error!
    MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL,
    'Recurring Payment Log',
    $log,
    ...
);
```

**After:**
```php
// Determine email recipient with fallback chain
$notification_email = '';
if (defined('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL')) {
    $notification_email = MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL;
} elseif (defined('STORE_OWNER_EMAIL_ADDRESS')) {
    $notification_email = STORE_OWNER_EMAIL_ADDRESS;
} elseif (defined('EMAIL_FROM')) {
    $notification_email = EMAIL_FROM;
}

// Only send email if we have a valid recipient
if (!empty($notification_email)) {
    zen_mail($notification_email, $notification_email, 'Recurring Payment Log', $log, ...);
}
```

## Fallback Chain

The method now tries these email addresses in order:

1. **`MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL`** - Configured notification email
2. **`STORE_OWNER_EMAIL_ADDRESS`** - Store owner's email (Zen Cart standard)
3. **`EMAIL_FROM`** - Default "from" email address
4. **Empty string** - If none are defined, email is skipped (length check prevents sending)

## Additional Improvements

### Generic Error Message
Changed site-specific error message to be generic:

**Before:**
```php
"Please contact Numinix Support if you unsure of how to resolve this issue"
```

**After:**
```php
"Please contact your system administrator if you are unsure of how to resolve this issue"
```

**Benefits:**
- Works for any site, not just Numinix
- Fixed grammar: "if you unsure" → "if you are unsure"
- Professional and generic

## Behavior

### When Module Constant is Defined
If your site has the module properly configured with the notification email setting:
- Uses the configured email address
- Original functionality preserved

### When Module Constant is NOT Defined
If the constant doesn't exist:
- Falls back to store owner email
- Falls back to EMAIL_FROM if needed
- Gracefully handles missing configuration
- **No fatal error** - cron job continues running

## Testing

Created `tests/UndefinedEmailConstantTest.php` to validate:
- ✅ Constant is protected by `defined()` check
- ✅ No bare usage of constant exists
- ✅ Fallback chain is complete
- ✅ Email variable properly initialized
- ✅ Generic error messages used
- ✅ Cron file also checked and validated

All tests passing ✅

## Impact

The cron job can now:
- ✅ Send error notifications without fatal errors
- ✅ Send summary email reports without fatal errors
- ✅ Work with incomplete module configuration
- ✅ Gracefully degrade to fallback email addresses
- ✅ Continue processing subscriptions after errors
- ✅ Complete successfully from start to finish

## Files Changed

- `includes/classes/paypalSavedCardRecurring.php` - Fixed constant usage in `notify_error()`
- `cron/paypalac_saved_card_recurring.php` - Fixed constant usage in summary email
- `tests/UndefinedEmailConstantTest.php` - Comprehensive test for both locations

## Related Issues

This is part of a series of fixes to make the PayPal plugin work properly:
1. Date column references
2. MessageStack API usage
3. Site-specific customizations (removed)
4. **Email notification constants** (this fix - 2 locations)

All contribute to making the cron job run successfully in all environments.
