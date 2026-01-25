# Subscription Archiving Feature

## Overview

This document describes the subscription archiving feature added to the PayPal Advanced Checkout module. This feature allows administrators to archive old or legacy subscriptions to keep the active subscription list clean while preserving historical data.

## Changes Made

### 1. Database Schema Updates

**File:** `includes/modules/payment/paypal/PayPalRestful/Common/SubscriptionManager.php`

- Added `is_archived` column to the `paypal_subscriptions` table
  - Type: `TINYINT(1) NOT NULL DEFAULT 0`
  - Default: `0` (not archived)
- Added index `idx_is_archived` for query performance

The schema is automatically updated when the subscription manager is initialized, ensuring backward compatibility with existing installations.

### 2. Archive/Unarchive Actions

**File:** `admin/paypalr_subscriptions.php`

Added two new actions:

#### Archive Subscription
- **Action:** `archive_subscription`
- **Method:** GET
- **Parameters:** `subscription_id`
- **Function:** Sets `is_archived = 1` for the specified subscription
- **Success Message:** "Subscription #{id} has been archived."

#### Unarchive Subscription
- **Action:** `unarchive_subscription`
- **Method:** GET
- **Parameters:** `subscription_id`
- **Function:** Sets `is_archived = 0` for the specified subscription
- **Success Message:** "Subscription #{id} has been unarchived."

### 3. Filtering System

Added a new filter option to the subscription management interface:

**Filter Name:** `show_archived`

**Options:**
- **Active Only** (default) - Shows only non-archived subscriptions (`is_archived = 0`)
- **Show All** - Shows both archived and active subscriptions (no filter)
- **Archived Only** - Shows only archived subscriptions (`is_archived = 1`)

The filter is applied to:
- Main subscription list view
- CSV export functionality

### 4. User Interface Updates

**Archive/Unarchive Buttons:**
- Added "Archive" button for non-archived subscriptions (gray background)
- Added "Unarchive" button for archived subscriptions (light blue background)
- Buttons include confirmation dialogs to prevent accidental actions
- Located in the "Status & Actions" column alongside existing action buttons (Suspend, Cancel, Reactivate)

**Filter Dropdown:**
- Added "Archived" filter dropdown in the filter form
- Located between "Payment Method" and "Apply Filters" button
- Persists filter selection across page refreshes

### 5. Quick Status Button Fix

**Problem:** When clicking "Mark Cancelled" (or "Mark Active"/"Mark Pending"), users encountered an error if the subscription had invalid vault references.

**Root Cause:** The update_subscription action validated vault cards even for quick status changes, causing errors for legacy subscriptions.

**Solution:** When `set_status` is present in POST data, the system now:
1. Updates only the status field (bypassing all other validations)
2. Sets `last_modified` timestamp
3. Redirects immediately with a success message
4. Does not validate vault cards or other fields

This ensures quick status buttons work reliably for all subscriptions, including legacy ones.

## Usage

### Archiving a Subscription

1. Navigate to **Admin → Catalog → PayPal Subscriptions** (paypalr_subscriptions.php)
2. Find the subscription you want to archive
3. Click the "Archive" button in the Status & Actions column
4. Confirm the action in the dialog
5. The subscription will be archived and hidden from the default view

### Viewing Archived Subscriptions

1. Navigate to **Admin → Catalog → PayPal Subscriptions**
2. Change the "Archived" filter dropdown to "Archived Only"
3. Click "Apply Filters"
4. All archived subscriptions will be displayed

### Unarchiving a Subscription

1. View archived subscriptions (see above)
2. Find the subscription you want to restore
3. Click the "Unarchive" button in the Status & Actions column
4. Confirm the action in the dialog
5. The subscription will be unarchived and visible in the default view

## Default Behavior

- **New installations:** All subscriptions are non-archived by default
- **Existing installations:** All existing subscriptions remain non-archived after upgrade
- **Default view:** Shows only active (non-archived) subscriptions
- **Cancelled subscriptions:** Continue to appear in the default view unless explicitly archived
- **Sorting:** Subscriptions are sorted by date_added DESC (newest first), regardless of archive status

## Benefits

1. **Cleaner Interface:** Legacy or old subscriptions can be hidden without deletion
2. **Data Preservation:** Archived subscriptions are preserved for historical reference
3. **Easy Recovery:** Subscriptions can be unarchived if needed
4. **Flexible Filtering:** Administrators can view active, archived, or all subscriptions
5. **No Breaking Changes:** Existing functionality is preserved; archiving is purely additive

## Technical Notes

- Archive status is stored in the database, not session-dependent
- Archive actions update the `last_modified` timestamp
- CSV exports respect the archive filter setting
- Archive status is independent of subscription status (active, cancelled, etc.)
- The `is_archived` column has a database index for performance

## Compatibility

- **Backward Compatible:** Existing installations will work without changes
- **Auto-Migration:** Schema is automatically updated on first page load
- **Safe Upgrade:** Existing subscriptions are not affected by the update
- **No Breaking Changes:** All existing API methods and queries continue to work

## Future Enhancements

Potential future improvements could include:

- Bulk archive/unarchive operations
- Auto-archive rules based on age or status
- Archive statistics dashboard
- Archive date/timestamp tracking
- Archive reason notes
