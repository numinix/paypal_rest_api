# Cron Date Column Fix Summary

## Problem

The cron job `paypal_saved_card_recurring.php` was failing with:
```
MySQL error 1054: Unknown column 'date' in 'where clause' :: 
SELECT saved_credit_card_recurring_id FROM saved_credit_cards_recurring 
WHERE status = 'scheduled' AND date <= '2026-01-29'
```

## Root Cause

Multiple methods in `paypalSavedCardRecurring.php` were referencing a non-existent `date` column. The `saved_credit_cards_recurring` table schema has:
- ✅ `date_added` - When the subscription was created
- ✅ `next_payment_date` - When the next payment is due
- ✅ `last_modified` - When the record was last updated
- ❌ `date` - **Does NOT exist**

## Methods Fixed

### 1. get_scheduled_payments() (Line 1947)
**Used by:** Cron job to find subscriptions due for payment

**Before:**
```php
$sql = 'SELECT saved_credit_card_recurring_id FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . 
       ' WHERE status = \'scheduled\' AND date <= \'' . date('Y-m-d') . '\'';
```

**After:**
```php
$sql = 'SELECT saved_credit_card_recurring_id FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . 
       ' WHERE status = \'scheduled\' AND next_payment_date <= \'' . date('Y-m-d') . '\'';
```

### 2. count_failed_payments() (Lines 2296, 2301)
**Used by:** Calculate failed payment statistics

**Before:**
```php
$result = $db->Execute('SELECT MAX(date) as last_success FROM ' . 
                       TABLE_SAVED_CREDIT_CARDS_RECURRING . " WHERE " . $scopeSql . 
                       " AND status = 'complete'");
...
$sql .= " AND date > '" . $last_successful_payment . "'";
```

**After:**
```php
$result = $db->Execute('SELECT MAX(next_payment_date) as last_success FROM ' . 
                       TABLE_SAVED_CREDIT_CARDS_RECURRING . " WHERE " . $scopeSql . 
                       " AND status = 'complete'");
...
$sql .= " AND next_payment_date > '" . $last_successful_payment . "'";
```

### 3. customer_has_subscription() (Line 2382)
**Used by:** Check if customer already has an active subscription

**Before:**
```php
if ((int) $subscription['products_id'] == (int) $product_id && 
    $subscription['status'] == 'scheduled' && 
    strtotime($subscription['date']) > strtotime('today')) {
```

**After:**
```php
if ((int) $subscription['products_id'] == (int) $product_id && 
    $subscription['status'] == 'scheduled' && 
    strtotime($subscription['next_payment_date']) > strtotime('today')) {
```

## Testing

Created `tests/CronDateColumnTest.php` with comprehensive validation:
- ✅ Validates `get_scheduled_payments()` uses `next_payment_date` in WHERE clause
- ✅ Validates `count_failed_payments()` uses `MAX(next_payment_date)` 
- ✅ Validates `count_failed_payments()` uses `next_payment_date` in second WHERE clause
- ✅ Validates `customer_has_subscription()` accesses `$subscription['next_payment_date']`

All tests passing ✅

## Impact

The cron job `cron/paypal_saved_card_recurring.php` can now:
- ✅ Successfully query for scheduled payments due today
- ✅ Calculate failed payment statistics without errors
- ✅ Check for existing customer subscriptions correctly

## Files Changed

- `includes/classes/paypalSavedCardRecurring.php` - 4 SQL queries fixed
- `tests/CronDateColumnTest.php` - New test file created
