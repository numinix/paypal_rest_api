# Legacy Recurring Payments Feature Migration Tasks

This document outlines the features from `legacy_recurring_reference/` that need to be reviewed, ported, or confirmed as already implemented in the current REST API implementation before the legacy directory can be removed.

## Overview

The `legacy_recurring_reference/` directory contains the original Numinix Recurring Payments implementation that works with:
- PayPal Website Payments Pro (`paypalwpp.php`)
- PayPal Standard (`paypal.php`)  
- PayPal Direct Payments (`paypaldp.php`)
- Payflow modules (`payflow.php`)

The current implementation targets the PayPal REST API and Advanced Checkout features. This migration must maintain backward compatibility with the legacy payment modules.

---

## Cron Scripts Comparison

### 1. `paypal_saved_card_recurring.php` ✅ IMPLEMENTED
**Legacy Location:** `legacy_recurring_reference/cron/paypal_saved_card_recurring.php`
**Current Location:** `cron/paypal_saved_card_recurring.php`

**Status:** Already ported to current implementation with additional features.

Both versions include:
- Processing scheduled payments for saved cards
- Expired/deleted card detection and replacement logic
- Order creation for successful payments
- Retry scheduling for failed payments
- Email notifications for payment failures
- Email reporting (text and HTML formats)
- Group pricing management
- Support for store credit payments

**Compatibility:** Works with legacy saved card system.

---

### 2. `paypal_wpp_recurring_reminders.php` ⚠️ NEEDS REVIEW
**Legacy Location:** `legacy_recurring_reference/cron/paypal_wpp_recurring_reminders.php`
**Current Location:** Not directly ported

**Features to Port/Verify:**
- [ ] Renewal reminders (X days before expiration)
- [ ] Payment reminders (X days before next billing)
- [ ] Expiration notices on the expiration date
- [ ] Status syncing with PayPal profile API

**Configuration Keys Used:**
- `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER` - Days before expiration to send reminder
- `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER` - Days before payment to send reminder

**Backward Compatibility Notes:**
- Uses `PayPalProfileManager` which supports both REST and Legacy APIs
- Should work with `paypalwpp.php`, `paypaldp.php`, and REST subscriptions
- Language constants: `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL`, `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL`, `PAYPAL_WPP_RECURRING_EXPIRED_NOTICE`

**Task:** Create or integrate reminder functionality into the REST implementation cron system.

---

### 3. `paypal_profile_cache_refresh.php` ⚠️ NEEDS REVIEW
**Legacy Location:** `legacy_recurring_reference/cron/paypal_profile_cache_refresh.php`
**Current Location:** Not directly ported

**Features:**
- Finds stale subscription profile caches
- Enqueues profile refresh jobs for batch processing
- Uses queue-based architecture for scalability

**Functions Used:**
- `zen_paypal_subscription_cache_table_name()`
- `zen_paypal_subscription_profile_cache_ttl()`
- `zen_paypal_subscription_refresh_queue_ensure_schema()`
- `zen_paypal_subscription_refresh_queue_enqueue_many()`
- `zen_paypal_subscription_refresh_queue_metrics()`

**Task:** Verify if cache refresh is handled by current implementation or needs porting.

---

### 4. `paypal_profile_refresh_worker.php` ⚠️ NEEDS REVIEW
**Legacy Location:** `legacy_recurring_reference/cron/paypal_profile_refresh_worker.php`
**Current Location:** Not directly ported

**Features:**
- Worker process for profile refresh queue
- Batch processing with configurable job limits
- Progress tracking and metrics reporting

**Task:** Verify if worker functionality is needed for REST implementation.

---

### 5. `remove_expired_cards.php` ✅ REVIEW FOR PORT
**Legacy Location:** `legacy_recurring_reference/cron/remove_expired_cards.php`
**Current Location:** Not directly ported

**Features:**
- Marks expired saved cards as deleted
- Uses SQL to check expiry date against current date

**Task:** This is a simple utility script. Verify if needed or if handled elsewhere.

---

### 6. `subscription_cancellations.php` ✅ REVIEW FOR PORT
**Legacy Location:** `legacy_recurring_reference/cron/subscription_cancellations.php`
**Current Location:** Not directly ported

**Features:**
- Removes group pricing for customers with expired cancellations
- Deletes processed cancellation records

**Database Tables:**
- `TABLE_SUBSCRIPTION_CANCELLATIONS`
- `TABLE_CUSTOMERS` (updates `customers_group_pricing`)

**Task:** Verify if this logic exists in current implementation or needs porting.

---

## Admin Management Pages Comparison

### 1. `paypal_subscriptions.php` ⚠️ PARTIAL IMPLEMENTATION
**Legacy Location:** `legacy_recurring_reference/management/paypal_subscriptions.php`
**Current Location:** `admin/paypalr_subscriptions.php`

**Legacy Features:**
- Search by customer, product, status
- Subscription list with full profile details
- Cancel, suspend, reactivate actions
- Edit subscription details (REST and legacy profiles)
- CSV export
- Expiration report
- Manual profile refresh
- Archive deleted subscriptions

**Current Implementation Features:**
- Filter by customer, product, status, payment module
- Update subscription metadata
- Change vault assignments
- Status management

**Features to Port:**
- [ ] Cancel subscription (with PayPal API call)
- [ ] Suspend subscription (with PayPal API call)
- [ ] Reactivate subscription (with PayPal API call)
- [ ] CSV export functionality
- [ ] Expiration report
- [ ] Manual profile refresh button
- [ ] Edit billing cycles (amount, next billing date)
- [ ] Archive functionality for deleted records

---

### 2. `numinix_saved_card_recurring.php` ⚠️ NEEDS PORT
**Legacy Location:** `legacy_recurring_reference/management/numinix_saved_card_recurring.php`
**Current Location:** Not directly ported

**Features:**
- Filter by customer, product, status
- Cancel/re-activate scheduled payments
- Update credit card on subscription
- Update payment date
- Update amount
- Update product assignment
- Shows period, frequency, billing cycles, domain

**Task:** This admin page manages saved card recurring payments specifically. Needs to be ported or integrated into existing admin pages.

---

### 3. `active_subscriptions_report.php` ⚠️ NEEDS PORT
**Legacy Location:** `legacy_recurring_reference/management/active_subscriptions_report.php`
**Current Location:** Not available

**Features:**
- Aggregated subscription report by product
- Filter by status (active/suspended)
- Filter by type (PayPal/Saved Card)
- Filter by product type
- Search functionality
- Sortable columns (product, subscriptions count, next billing, annual value)
- Annual value calculation by currency
- Type and status breakdown
- Billing profile details

**Task:** Create equivalent report functionality in REST admin area.

---

### 4. `saved_card_snapshot_migration.php` ✅ MIGRATION TOOL
**Legacy Location:** `legacy_recurring_reference/management/saved_card_snapshot_migration.php`
**Current Location:** Not needed after migration complete

**Features:**
- One-time migration tool for saved card subscription snapshots
- Populates missing metadata from order history

**Task:** Keep available until all legacy data is migrated, then can be removed.

---

## IPN Handler

### `ipn_main_handler.php` ⚠️ REVIEW
**Legacy Location:** `legacy_recurring_reference/ipn_main_handler.php`
**Current Location:** `ppr_listener.php` and `ppr_webhook.php`

**Legacy Features:**
- Express Checkout handling
- IPN validation and processing
- Order creation from IPN
- Status updates from PayPal notifications
- Subscription payment handling (`subscr_payment` txn_type)
- Notifier: `NOTIFY_PAYPAL_WPP_RECURRING_PAYMENT_RECEIVED`

**Backward Compatibility Notes:**
- Legacy modules still need IPN handling
- REST modules use webhooks instead

**Task:** Ensure IPN handler remains available for legacy payment modules.

---

## Classes and Includes

### `paypalSavedCardRecurring.php`
**Legacy Location:** `legacy_recurring_reference/includes/classes/paypalSavedCardRecurring.php`
**Current Location:** Should exist in `includes/classes/`

**Task:** Verify current implementation has all methods from legacy class.

### `PayPalProfileManager.php`
**Location:** Both implementations use this class

**Features:**
- Abstracts PayPal REST and Legacy API calls
- Profile status retrieval
- Cancel, suspend, reactivate operations
- Billing cycle updates

**Task:** Ensure all operations work with both REST and legacy payment modules.

---

## Configuration Keys to Verify

The following configuration keys from the legacy implementation should be verified or created:

- [ ] `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER` - Days before renewal to send reminder
- [ ] `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER` - Days before payment to send reminder
- [ ] `SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED` - Max payment failures before notification
- [ ] `SAVED_CREDIT_CARDS_RECURRING_FAILURE_RECIPIENTS` - Additional email recipients for failure notifications
- [ ] `MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL` - Admin notification email

---

## Language Constants to Port

Legacy language files contain definitions that should be available:

- [ ] `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL`
- [ ] `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_SUBJECT`
- [ ] `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER_EMAIL_INVALID_PRODUCT`
- [ ] `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL`
- [ ] `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER_EMAIL_SUBJECT`
- [ ] `PAYPAL_WPP_RECURRING_EXPIRED_NOTICE`
- [ ] `PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_EMAIL_SUBJECT`
- [ ] `PAYPAL_WPP_RECURRING_EXPIRED_NOTICE_INVALID_PRODUCT`
- [ ] `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL`
- [ ] `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT`
- [ ] `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL`
- [ ] `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT`

---

## Database Tables

Ensure these tables exist and are compatible:

- [ ] `TABLE_PAYPAL_RECURRING` - PayPal subscription records
- [ ] `TABLE_PAYPAL_RECURRING_ARCHIVE` - Archived subscription records
- [ ] `TABLE_SAVED_CREDIT_CARDS` - Saved payment cards
- [ ] `TABLE_SAVED_CREDIT_CARDS_RECURRING` - Saved card recurring schedules
- [ ] `TABLE_SUBSCRIPTION_CANCELLATIONS` - Scheduled cancellations for group pricing removal
- [ ] `TABLE_PAYPAL_SUBSCRIPTIONS` - REST API subscription records
- [ ] `TABLE_PAYPAL_VAULT` - REST API vaulted payment methods

---

## Backward Compatibility Requirements

### Payment Module Support
The following payment modules must continue to work with the recurring system:

1. **paypalwpp.php (Website Payments Pro)**
   - Uses NVP/SOAP API for recurring profiles
   - Requires `GetRecurringPaymentsProfileDetails` API support
   - Requires `ManageRecurringPaymentsProfileStatus` API support

2. **paypal.php (PayPal Standard)**
   - Uses IPN for subscription notifications
   - `txn_type=subscr_payment` for payment receipts

3. **paypaldp.php (Direct Payment)**
   - Similar to WPP, uses NVP API
   - May use saved card processing

4. **payflow.php (Payflow)**
   - Uses Payflow API
   - Different authentication mechanism

### PayPalProfileManager Compatibility
The `PayPalProfileManager` class must support:
- REST API subscription operations
- Legacy NVP/SOAP API operations
- Automatic detection of profile type (REST vs Legacy)

---

## Priority Tasks

### High Priority
1. [ ] Port `paypal_wpp_recurring_reminders.php` functionality
2. [ ] Port `numinix_saved_card_recurring.php` admin page
3. [ ] Add cancel/suspend/reactivate actions to `paypalr_subscriptions.php`
4. [ ] Add CSV export to `paypalr_subscriptions.php`

### Medium Priority
5. [ ] Port `active_subscriptions_report.php` admin page
6. [ ] Port `subscription_cancellations.php` cron script
7. [ ] Port `remove_expired_cards.php` cron script
8. [ ] Verify profile cache refresh is working

### Low Priority
9. [ ] Review and cleanup `saved_card_snapshot_migration.php` after migration
10. [ ] Remove legacy reference directory after all tasks complete

---

## Testing Checklist

Before removing `legacy_recurring_reference/`:

- [ ] All cron scripts work with REST and legacy payment modules
- [ ] Admin pages support both REST and legacy subscriptions
- [ ] Cancel/suspend/reactivate works for all subscription types
- [ ] Reminder emails work for all subscription types
- [ ] Payment processing works for saved cards
- [ ] CSV export includes all subscription types
- [ ] Reports show accurate data for all sources
- [ ] IPN handler continues to work for legacy modules
- [ ] Webhook handler works for REST modules

---

## Notes

- The current `cron/paypal_saved_card_recurring.php` appears to be a direct copy from the legacy implementation with REST API integration added via `LegacySubscriptionMigrator::syncLegacySubscriptions()`
- The `PayPalProfileManager` class provides abstraction for both REST and Legacy APIs
- Admin pages need significant work to match legacy functionality
- Language files in `legacy_recurring_reference/includes/languages/` should be reviewed for any missing constants
