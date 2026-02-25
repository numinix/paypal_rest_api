# Undefined Constants Fix Summary

## Problem

The cron job `paypalac_saved_card_recurring.php` was failing with:
```
PHP Fatal error: Uncaught Error: Undefined constant "CATEGORY_ID_PLANS" 
in paypalSavedCardRecurring.php:1650
```

This happened when calling `prepare_order()` during subscription processing.

## Root Cause

Line 1650 in `paypalSavedCardRecurring.php` used site-specific constants without checking if they were defined:

```php
if (!zen_product_in_category($products_id, CATEGORY_ID_PLANS) && 
    !zen_product_in_category($products_id, CATEGORY_ID_CUSTOM_PLANS)) {
```

These constants (`CATEGORY_ID_PLANS` and `CATEGORY_ID_CUSTOM_PLANS`) are part of an "NX mod" customization that:
- May not exist in all installations
- Are site-specific category IDs
- Cause fatal errors in PHP 8+ when undefined (PHP 7 would just show a notice)

## The Fix

Added `defined()` checks before using the constants:

**Before:**
```php
if (!zen_product_in_category($products_id, CATEGORY_ID_PLANS) && 
    !zen_product_in_category($products_id, CATEGORY_ID_CUSTOM_PLANS)) {
    // Initialize store credit
    $store_credit = new storeCredit();
    $_SESSION['storecredit'] = $store_credit->retrieve_customer_credit($_SESSION['customer_id']);
}
else {
    $_SESSION['storecredit'] = 0;
}
```

**After:**
```php
$isPlansProduct = false;
if (defined('CATEGORY_ID_PLANS') && zen_product_in_category($products_id, CATEGORY_ID_PLANS)) {
    $isPlansProduct = true;
}
if (defined('CATEGORY_ID_CUSTOM_PLANS') && zen_product_in_category($products_id, CATEGORY_ID_CUSTOM_PLANS)) {
    $isPlansProduct = true;
}

if (!$isPlansProduct) {
    // Initialize store credit
    $store_credit = new storeCredit();
    $_SESSION['storecredit'] = $store_credit->retrieve_customer_credit($_SESSION['customer_id']);
}
else {
    $_SESSION['storecredit'] = 0;
}
```

## Behavior

### When Constants Are Defined
If the site has `CATEGORY_ID_PLANS` and/or `CATEGORY_ID_CUSTOM_PLANS` defined:
- Products in those categories are identified as "plans products"
- Store credit is NOT allowed for plans products
- Maintains original "NX mod" functionality

### When Constants Are NOT Defined
If the constants don't exist (typical for most installations):
- `$isPlansProduct` stays `false`
- Store credit is allowed for all products
- No fatal error occurs
- Cron job completes successfully

## Testing

Created `tests/UndefinedConstantsTest.php` to validate:
- ✅ Both constants are protected by `defined()` checks
- ✅ No bare usage of constants exists
- ✅ Backward compatibility is maintained
- ✅ Logic properly handles store credit

## Impact

The cron job `cron/paypalac_saved_card_recurring.php` can now:
- ✅ Run successfully without site-specific constants
- ✅ Process recurring payments without fatal errors
- ✅ Maintain compatibility with customized installations
- ✅ Work across different Zen Cart configurations

## Files Changed

- `includes/classes/paypalSavedCardRecurring.php` - Fixed constant usage
- `tests/UndefinedConstantsTest.php` - New test file
