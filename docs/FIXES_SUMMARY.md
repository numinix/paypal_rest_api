# Summary of Fixes

This PR addresses three critical issues in the PayPal REST API plugin:

## 1. MessageStack.add_session() Format Correction

### Problem
Admin files were using the **catalog format** (3 parameters: class, message, type) instead of the correct **admin format** (2 parameters: message, type).

**Incorrect (Catalog):**
```php
$messageStack->add_session('header', 'Success message', 'success');
```

**Correct (Admin):**
```php
$messageStack->add_session('Success message', 'success');
```

### Files Fixed
- `admin/paypalr_subscriptions.php` - 29 instances
- `admin/paypalr_saved_card_recurring.php` - 14 instances  
- `admin/paypalr_integrated_signup.php` - 1 instance
- `admin/paypalr_upgrade.php` - 1 instance

**Total: 51 messageStack calls corrected**

### Testing
Created `tests/MessageStackAdminFormatTest.php` to validate all admin files use correct 2-parameter format.

## 2. Update Payment Info Date Column Fix

### Problem
The `update_payment_info()` method tried to UPDATE a non-existent `date` column in the `saved_credit_cards_recurring` table.

**Error:**
```
MySQL error 1054: Unknown column 'date' in 'field list' :: 
UPDATE saved_credit_cards_recurring SET ... date = '2026-01-29' ...
```

### Root Cause
The table schema has `date_added` and `next_payment_date` columns, but NOT a `date` column.

### Solution
Changed UPDATE statements to use `next_payment_date` instead of `date`:

```php
// Before
$sql .= ", date = '" . $this->escape_db_value($data['date']) . "'";

// After  
$sql .= ", next_payment_date = '" . $this->escape_db_value($data['date']) . "'";
```

### Files Fixed
- `includes/classes/paypalSavedCardRecurring.php` - 2 instances (lines 2193, 2196)

### Testing
Created `tests/UpdatePaymentInfoDateColumnTest.php` to validate the fix.

## 3. Duplicate Key Error in Legacy Subscription Migrator

### Problem
When running the cron job `paypal_saved_card_recurring.php`, got duplicate key error:

**Error:**
```
MySQL error 1062: Duplicate entry '0' for key 'idx_orders_product' :: 
INSERT INTO paypal_subscriptions ... orders_products_id ... VALUES ... '' ...
```

### Root Cause
- When `orders_products_id` is 0, the code set it to `null`
- But `zen_db_perform()` converts `null` to empty string `''`
- MySQL converts empty string to `0`
- Multiple records with `orders_products_id = 0` violate UNIQUE constraint

### Solution
Instead of setting to `null`, completely **unset** the key from the array:

```php
// Before
if (isset($record['orders_products_id']) && (int)$record['orders_products_id'] === 0) {
    $record['orders_products_id'] = null;  // zen_db_perform converts this to ''
}

// After
if (isset($record['orders_products_id']) && (int)$record['orders_products_id'] === 0) {
    unset($record['orders_products_id']);  // Allows database to insert NULL
}
```

### Files Fixed
- `includes/modules/payment/paypal/PayPalRestful/Common/LegacySubscriptionMigrator.php`

## Test Results

All tests passing ✅

```
✅ AdminSubscriptionsDateColumnTest.php - PASSED
✅ MessageStackAdminFormatTest.php - PASSED  
✅ UpdatePaymentInfoDateColumnTest.php - PASSED
```

## Impact

These fixes ensure:
1. ✅ Admin success/error messages display correctly
2. ✅ Subscription next payment dates can be updated via admin
3. ✅ Cron jobs can successfully migrate legacy subscriptions without duplicate key errors
