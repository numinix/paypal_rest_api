# Subscription Archiving and Error Fix - Implementation Summary

## Overview

This PR addresses the issues described in the problem statement regarding the PayPal subscriptions page:

1. Error message when clicking "Mark Cancelled" button
2. Need for subscription archiving functionality  
3. Concerns about subscription ordering

## Problem 1: Error on "Mark Cancelled" Button

### Reported Issue
When clicking "Mark Cancelled", users saw an error message at the top of the page, though the subscription did show "Subscription cancelled" underneath.

### Root Cause
The `update_subscription` action was validating vault cards even for quick status changes. Legacy subscriptions with invalid vault references (vault_id > 0 but card deleted) failed validation with error:
```
"Unable to link the selected vaulted instrument. Please verify it still exists."
```

### Solution
Modified the quick status button handling to bypass all validation and update only the status field:

```php
if (isset($_POST['set_status']) && $_POST['set_status'] !== '') {
    $status = strtolower(trim((string) zen_db_prepare_input($_POST['set_status'])));
    // For quick status changes, only update the status field without validating other fields
    zen_db_perform(
        TABLE_PAYPAL_SUBSCRIPTIONS,
        ['status' => $status, 'last_modified' => date('Y-m-d H:i:s')],
        'update',
        'paypal_subscription_id = ' . (int) $subscriptionId
    );
    
    $messageStack->add_session(
        $messageStackKey,
        sprintf('Subscription #%d status has been updated to %s.', $subscriptionId, $status),
        'success'
    );
    
    zen_redirect($redirectUrl);
}
```

**Result:** Quick status buttons (Mark Cancelled, Mark Active, Mark Pending) now work reliably for all subscriptions.

## Problem 2: Subscription Archiving

### Reported Need
The problem statement asked:
> "Do you see a problem with adding an ability to delete subscriptions or "archive" them so they do not appear in the interface? If we do add an archiving ability, we'd need a way to view archived subscriptions as well."

### Solution: Complete Archiving System

#### 1. Database Schema (SubscriptionManager.php)
Added to the `paypal_subscriptions` table:
- Column: `is_archived TINYINT(1) NOT NULL DEFAULT 0`
- Index: `idx_is_archived` for query performance

Schema updates automatically on first page load after upgrade.

#### 2. Archive/Unarchive Actions (paypalac_subscriptions.php)

**Archive Action:**
- URL: `?action=archive_subscription&subscription_id={id}`
- Sets `is_archived = 1`
- Success message: "Subscription #{id} has been archived."

**Unarchive Action:**
- URL: `?action=unarchive_subscription&subscription_id={id}`
- Sets `is_archived = 0`
- Success message: "Subscription #{id} has been unarchived."

Both actions include confirmation dialogs to prevent accidents.

#### 3. Filtering System

Added "Archived" dropdown filter with three options:

| Option | Behavior | SQL Filter |
|--------|----------|------------|
| Active Only (default) | Shows non-archived subscriptions | `is_archived = 0` |
| Show All | Shows all subscriptions | No filter |
| Archived Only | Shows archived subscriptions only | `is_archived = 1` |

Filter applies to:
- Main subscription list
- CSV export

#### 4. User Interface Elements

**Archive/Unarchive Buttons:**
- Located in Status & Actions column
- Archive button: Gray background (`#777`)
- Unarchive button: Light blue background (`#5bc0de`)
- Both include confirmation dialogs
- Show/hide based on current archive status

**Filter Dropdown:**
```html
<div class="form-group">
    <label for="filter-archived">Archived</label>
    <select name="show_archived" id="filter-archived">
        <option value="">Active Only</option>
        <option value="all">Show All</option>
        <option value="only">Archived Only</option>
    </select>
</div>
```

## Problem 3: Subscription Ordering

### Reported Question
> "Will this subscription continue to take the top spot in the results, or will it show newer subscriptions first?"

### Current Behavior (Working as Expected)
Subscriptions are already sorted correctly:
```sql
ORDER BY ps.date_added DESC, ps.paypal_subscription_id DESC
```

This means:
- Newest subscriptions appear first
- Old cancelled subscriptions don't monopolize the top
- Archive feature provides additional cleanup option

**No changes needed** - sorting was already working correctly.

## Files Modified

### 1. SubscriptionManager.php
**Path:** `includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php`

**Changes:**
- Added `is_archived` column to schema
- Added `idx_is_archived` index

**Lines:** 85 (column), 98 (index)

### 2. paypalac_subscriptions.php  
**Path:** `admin/paypalac_subscriptions.php`

**Changes:**
- Fixed quick status button handling (lines 151-168)
- Added archive_subscription action (lines 359-381)
- Added unarchive_subscription action (lines 383-405)
- Added archive filter to main query (lines 517, 536-544)
- Added archive filter to CSV export (lines 412, 428-435)
- Added UI filter dropdown (lines 777-785)
- Added Archive/Unarchive buttons (lines 965-973)

### 3. Documentation (New Files)
- `docs/SUBSCRIPTION_ARCHIVING.md` - User guide and technical documentation
- `docs/SUBSCRIPTION_ARCHIVING_SUMMARY.md` - This implementation summary

## Benefits

1. **Error Fix:** Quick status buttons work reliably for all subscriptions
2. **Clean Interface:** Archive old subscriptions to reduce clutter
3. **Data Preservation:** Archived subscriptions are not deleted
4. **Flexible Viewing:** Easy toggle between active and archived subscriptions
5. **No Breaking Changes:** Fully backward compatible
6. **Performance:** Indexed archive column for efficient filtering

## Backward Compatibility

✅ **Fully Compatible**
- Existing subscriptions remain non-archived (default)
- Schema updates are automatic and safe
- No changes to existing API or functions
- New features are opt-in only

## Security

✅ **Security Scan Passed**
- CodeQL scan completed with no issues
- All inputs properly sanitized
- Archive status uses integer (0/1) only
- Actions require valid subscription ID
- Confirmation dialogs prevent accidents

## Testing Recommendations

### Quick Status Buttons
1. Test with subscriptions that have valid vault references
2. Test with subscriptions that have invalid vault references
3. Test with legacy subscriptions from migration
4. Verify no error messages appear
5. Verify status updates correctly

### Archiving
1. Archive a subscription → verify it disappears from default view
2. Filter to "Archived Only" → verify archived subscription appears
3. Unarchive the subscription → verify it reappears in active view
4. Test with multiple subscriptions at once

### Filtering
1. Test each filter option individually
2. Combine with other filters (status, customer, product)
3. Verify CSV export respects archive filter
4. Check pagination works correctly

### Backward Compatibility
1. Test fresh installation
2. Test upgrade path from older version
3. Verify existing subscriptions work
4. Check schema migration runs correctly

## Deployment

### Automatic Migration
When the admin loads the subscriptions page after upgrade:
1. `SubscriptionManager::ensureSchema()` runs
2. Checks if `is_archived` column exists
3. If not, adds column with default value 0
4. Adds index if not exists
5. All existing subscriptions remain non-archived

### Rollback Plan
If needed:
1. Revert code changes
2. Optionally: `ALTER TABLE paypal_subscriptions DROP COLUMN is_archived;`
3. Optionally: Drop index `idx_is_archived`

Note: Data is preserved even if column is kept.

## Conclusion

This implementation provides a complete solution to the reported issues:

✅ Fixed error when clicking "Mark Cancelled"  
✅ Added subscription archiving functionality  
✅ Confirmed subscription ordering works correctly  
✅ Provided way to view archived subscriptions  
✅ Maintained backward compatibility  
✅ Passed security scan  
✅ Documented thoroughly  

The changes are minimal, focused, and production-ready.
