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

### 2. `paypal_wpp_recurring_reminders.php` ✅ PORTED
**Legacy Location:** `legacy_recurring_reference/cron/paypal_wpp_recurring_reminders.php`
**Current Location:** `cron/paypal_wpp_recurring_reminders.php`

**Features Implemented:**
- [x] Renewal reminders (X days before expiration)
- [x] Payment reminders (X days before next billing)
- [x] Expiration notices on the expiration date
- [x] Status syncing with PayPal profile API
- [x] REST API subscription support

**Configuration Keys Used:**
- `PAYPAL_WPP_RECURRING_RENEWAL_REMINDER` - Days before expiration to send reminder
- `PAYPAL_WPP_RECURRING_PAYMENT_REMINDER` - Days before payment to send reminder

**Backward Compatibility:**
- Uses `PayPalProfileManager` which supports both REST and Legacy APIs
- Works with `paypalwpp.php`, `paypaldp.php`, and REST subscriptions
- Language constants defined with defaults

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

### 5. `remove_expired_cards.php` ✅ PORTED
**Legacy Location:** `legacy_recurring_reference/cron/remove_expired_cards.php`
**Current Location:** `cron/remove_expired_cards.php`

**Features Implemented:**
- [x] Marks expired saved cards as deleted
- [x] Uses SQL to check expiry date against current date
- [x] Also handles PayPal Vault table for REST API cards

---

### 6. `subscription_cancellations.php` ✅ PORTED
**Legacy Location:** `legacy_recurring_reference/cron/subscription_cancellations.php`
**Current Location:** `cron/subscription_cancellations.php`

**Features Implemented:**
- [x] Removes group pricing for customers with expired cancellations
- [x] Deletes processed cancellation records

**Database Tables:**
- `TABLE_SUBSCRIPTION_CANCELLATIONS`
- `TABLE_CUSTOMERS` (updates `customers_group_pricing`)

---

## Admin Management Pages Comparison

### 1. `paypal_subscriptions.php` ✅ FULLY PORTED
**Legacy Location:** `legacy_recurring_reference/management/paypal_subscriptions.php`
**Current Location:** `admin/paypalr_subscriptions.php`

**Features Implemented:**
- [x] Search by customer, product, status, payment module
- [x] Subscription list with full profile details
- [x] Cancel subscription (with PayPal API call)
- [x] Suspend subscription (with PayPal API call)
- [x] Reactivate subscription (with PayPal API call)
- [x] Edit subscription details (REST and legacy profiles)
- [x] CSV export functionality
- [x] Update subscription metadata
- [x] Change vault assignments
- [x] Status management

**Remaining (Low Priority):**
- [ ] Expiration report (separate page created)
- [ ] Manual profile refresh button
- [ ] Archive functionality for deleted records

---

### 2. `numinix_saved_card_recurring.php` ✅ PORTED
**Legacy Location:** `legacy_recurring_reference/management/numinix_saved_card_recurring.php`
**Current Location:** `admin/paypalr_saved_card_recurring.php`

**Features Implemented:**
- [x] Filter by customer, product, status
- [x] Cancel/re-activate scheduled payments
- [x] Update credit card on subscription
- [x] Update payment date
- [x] Update amount
- [x] Update product assignment
- [x] Shows period, frequency, billing cycles, domain
- [x] CSV export functionality

---

### 3. `active_subscriptions_report.php` ✅ PORTED
**Legacy Location:** `legacy_recurring_reference/management/active_subscriptions_report.php`
**Current Location:** `admin/paypalr_subscriptions_report.php`

**Features Implemented:**
- [x] Aggregated subscription report by product
- [x] Filter by status (active/suspended)
- [x] Filter by type (PayPal Legacy/Saved Card/REST API)
- [x] Search functionality
- [x] Sortable columns (product, subscriptions count, next billing, annual value)
- [x] Annual value calculation by currency
- [x] Type and status breakdown
- [x] Billing profile details

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
1. [x] Port `paypal_wpp_recurring_reminders.php` functionality - ✅ Created `cron/paypal_wpp_recurring_reminders.php`
2. [x] Port `numinix_saved_card_recurring.php` admin page - ✅ Created `admin/paypalr_saved_card_recurring.php`
3. [x] Add cancel/suspend/reactivate actions to `paypalr_subscriptions.php` - ✅ Added PayPal API actions
4. [x] Add CSV export to `paypalr_subscriptions.php` - ✅ Added export functionality

### Medium Priority
5. [x] Port `active_subscriptions_report.php` admin page - ✅ Created `admin/paypalr_subscriptions_report.php`
6. [x] Port `subscription_cancellations.php` cron script - ✅ Created `cron/subscription_cancellations.php`
7. [x] Port `remove_expired_cards.php` cron script - ✅ Created `cron/remove_expired_cards.php`
8. [ ] Verify profile cache refresh is working - Cache functionality integrated into PayPalProfileManager

### Low Priority
9. [ ] Review and cleanup `saved_card_snapshot_migration.php` after migration
10. [ ] Remove legacy reference directory after all tasks complete

---

## Testing Checklist

Before removing `legacy_recurring_reference/`:

- [x] All cron scripts work with REST and legacy payment modules
- [x] Admin pages support both REST and legacy subscriptions
- [x] Cancel/suspend/reactivate works for all subscription types
- [x] Reminder emails work for all subscription types
- [x] Payment processing works for saved cards
- [x] CSV export includes all subscription types
- [x] Reports show accurate data for all sources
- [ ] IPN handler continues to work for legacy modules (requires integration testing)
- [ ] Webhook handler works for REST modules (requires integration testing)

---

## Notes

- The current `cron/paypal_saved_card_recurring.php` shares significant code structure with the legacy implementation but includes REST API integration via `LegacySubscriptionMigrator::syncLegacySubscriptions()` at line 7. Both files handle the same core functionality (scheduled payment processing, card expiration handling, order creation) with minor implementation differences.
- The `PayPalProfileManager` class provides abstraction for both REST and Legacy APIs
- Admin pages need significant work to match legacy functionality
- Language files in `legacy_recurring_reference/includes/languages/` should be reviewed for any missing constants

---

# PayPal Wallet Module Native API Upgrade Tasks

This section outlines the tasks for upgrading Apple Pay and Venmo wallet modules to use PayPal's native APIs, similar to the Google Pay upgrade completed in PR #X.

## Context

Google Pay has been upgraded from the deprecated `paypal.Buttons({ fundingSource: GOOGLEPAY })` approach to the native `paypal.Googlepay()` API. This provides better integration with PayPal's latest features and follows their official documentation.

**Reference PRs/Commits:**
- Google Pay native implementation: Uses `paypal.Googlepay()`, `google.payments.api.PaymentsClient`, `confirmOrder()`

---

## Phase 1: Apple Pay Native API Upgrade ✅ COMPLETE

### Research & Planning
- [x] Review PayPal's official Apple Pay documentation: https://developer.paypal.com/docs/checkout/advanced/applepay/
- [x] Identify key differences between current `paypal.Buttons({ fundingSource: APPLEPAY })` and native `paypal.Applepay()` API
- [x] Document required SDK parameters for Apple Pay (`components=applepay`)
- [x] Understand Apple Pay Session API requirements

### Implementation Tasks

#### 1.1 SDK Loading Updates
- [x] Update SDK URL to use `components=applepay` instead of `buttons,googlepay,applepay`
- [x] Add any required Apple Pay-specific SDK parameters
- [x] Ensure proper error handling for SDK load failures

#### 1.2 Native Apple Pay Integration
- [x] Replace `paypal.Buttons({ fundingSource: APPLEPAY })` with `paypal.Applepay()` API
- [x] Implement `paypal.Applepay().config()` for payment configuration
- [x] Implement native Apple Pay button using ApplePaySession API
- [x] Implement `paypal.Applepay().confirmOrder()` for order confirmation
- [x] Add eligibility check with `paypal.Applepay().isEligible()`

#### 1.3 Payment Flow Implementation
- [x] Create ApplePaySession with proper merchant validation
- [x] Handle `onvalidatemerchant` callback
- [x] Handle `onpaymentauthorized` callback
- [x] Implement proper error handling for payment failures
- [x] Handle user cancellation gracefully

#### 1.4 PHP Backend Updates (if needed)
- [x] Review `paypalr_applepay.php` for any required changes
- [x] Update `ajaxGetWalletConfig()` to return Apple Pay-specific configuration (added `environment` field)
- [x] Ensure proper merchant domain validation is in place

### Testing
- [x] Create NativeApplePayImplementationTest.php with comprehensive tests
- [ ] Test on Safari/macOS with Apple Pay configured (requires real device)
- [ ] Test on iOS Safari with Apple Pay configured (requires real device)
- [x] Test eligibility hiding on non-Apple devices (via ApplePaySession.canMakePayments check)
- [x] Test error handling for declined payments
- [x] Test cancellation flow

### Documentation
- [x] Update code comments with reference to PayPal documentation
- [x] Update any developer documentation

---

## Phase 2: Venmo Integration Review

### Research & Assessment
- [ ] Review PayPal's official Venmo documentation: https://developer.paypal.com/docs/checkout/venmo/
- [ ] Determine if Venmo has a native API similar to Google Pay/Apple Pay
- [ ] Assess if current `paypal.Buttons({ fundingSource: VENMO })` approach is deprecated

### Current State Analysis
The current Venmo implementation uses:
```javascript
paypal.Buttons({
    fundingSource: paypal.FUNDING.VENMO,
    createOrder: function() { ... },
    onApprove: function(data) { ... }
})
```

### Decision Point
- [ ] **If native API exists**: Proceed with upgrade similar to Google Pay
- [ ] **If no native API**: Document that current approach is correct and no changes needed

### Implementation Tasks (if upgrade needed)

#### 2.1 SDK Loading Updates
- [ ] Update SDK URL parameters for Venmo component
- [ ] Handle Venmo-specific eligibility requirements (US only, mobile preferred)

#### 2.2 Native Venmo Integration (if applicable)
- [ ] Replace `paypal.Buttons({ fundingSource: VENMO })` with native Venmo API
- [ ] Implement payment configuration
- [ ] Implement order confirmation flow
- [ ] Add eligibility checks

#### 2.3 Testing
- [ ] Test on mobile devices (Venmo is mobile-focused)
- [ ] Test eligibility hiding for non-US users
- [ ] Test deep link flow to Venmo app
- [ ] Test web fallback flow

---

## Phase 3: Test Suite Updates

### Update Existing Tests
- [ ] Update `WalletIneligiblePaymentHidingTest.php` for new Apple Pay API patterns
- [ ] Update `WalletSdkIntentParameterTest.php` for new SDK components
- [ ] Update `WalletMerchantIdValidationTest.php` if merchant ID handling changes

### Create New Tests
- [ ] Create `NativeApplePayImplementationTest.php` (similar to `NativeGooglePayImplementationTest.php`)
- [ ] Create tests for Apple Pay Session flow
- [ ] Create tests for merchant validation
- [ ] Update Venmo tests if Venmo implementation changes

---

## Phase 4: Documentation & Cleanup

### Code Documentation
- [ ] Add reference links to PayPal documentation in code comments
- [ ] Document any browser/device requirements
- [ ] Document merchant setup requirements (Apple Developer account, domain verification)

### User Documentation
- [ ] Update admin configuration instructions
- [ ] Document Apple Pay merchant domain verification process
- [ ] Document Venmo eligibility requirements (US only)

### Code Cleanup
- [ ] Remove any deprecated code patterns
- [ ] Ensure consistent code style across wallet modules
- [ ] Review and update error messages

---

## Priority & Timeline

| Phase | Priority | Estimated Effort | Dependencies |
|-------|----------|------------------|--------------|
| Phase 1: Apple Pay | High | 2-3 days | Apple Pay documentation review |
| Phase 2: Venmo | Medium | 1-2 days | Venmo API research |
| Phase 3: Tests | High | 1 day | Phases 1-2 completion |
| Phase 4: Docs | Low | 0.5 days | All phases complete |

---

## Notes

- **Apple Pay** has a well-documented native API at PayPal and requires ApplePaySession integration
- **Venmo** may not have a separate native API like Google Pay - it might continue to use the PayPal Buttons approach as the recommended method
- The Google Pay implementation can serve as a template for the Apple Pay upgrade
- Thorough testing on actual Apple devices is essential for Apple Pay
