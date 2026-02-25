# Subscription Categorization and Next Billing Date - Implementation Summary

## Problem Statement
When creating a subscription order using a saved card without a Plan ID (Zen Cart managed subscription), the following issues occurred:

1. **Incorrect Categorization**: The subscription appeared under "Vaulted Subscriptions" instead of "Saved Card Subscriptions"
2. **Missing Billing Date Display**: The next billing date was not visible on the Vaulted Subscriptions admin page
3. **No Manual Date Editing**: There was no option to manually change the next billing date for testing purposes

## Root Cause
The observer `auto.paypalacestful_recurring.php` was routing ALL subscriptions to `TABLE_PAYPAL_SUBSCRIPTIONS` (Vaulted Subscriptions), regardless of whether they had a `plan_id`. This was incorrect for Zen Cart-managed subscriptions (those without a `plan_id`).

## Solution Implemented

### 1. Subscription Routing Logic (Observer)
**File**: `includes/classes/observers/auto.paypalacestful_recurring.php`

The observer now checks for the presence of `plan_id` and routes subscriptions accordingly:

- **Subscriptions WITH plan_id** (PayPal-managed):
  - Route to: `TABLE_PAYPAL_SUBSCRIPTIONS`
  - Admin page: Vaulted Subscriptions (`paypalac_subscriptions.php`)
  - Uses: `SubscriptionManager::logSubscription()`
  
- **Subscriptions WITHOUT plan_id** (Zen Cart-managed):
  - Route to: `TABLE_SAVED_CREDIT_CARDS_RECURRING`
  - Admin page: Saved Card Subscriptions (`paypalac_saved_card_recurring.php`)
  - Uses: `paypalSavedCardRecurring::schedule_payment()`

#### Key Implementation Details:
1. **getSavedCreditCardId()**: Looks up the `saved_credit_card_id` from the vault record
2. **calculateNextBillingDate()**: Calculates the next billing date based on `billing_period` and `billing_frequency`
3. Proper error handling when vault or saved card records are not available

### 2. Next Billing Date Display & Editing (Admin)
**File**: `admin/paypalac_subscriptions.php`

Added functionality to the Vaulted Subscriptions admin page:

- **Display**: Shows the `next_payment_date` field in the billing details section
- **Edit**: Provides a date input field (`<input type="date">`) for manual editing
- **Validation**: Validates date format (YYYY-MM-DD) before saving
- **Save**: Persists changes to the database with proper sanitization

### 3. Security Measures
All user inputs are properly sanitized:
- `zen_db_prepare_input()` for form inputs
- `zen_db_input()` for database queries
- Date format validation with `DateTime::createFromFormat()`
- SQL parameters are properly escaped

## Testing

### New Test: SubscriptionRoutingTest.php
Created comprehensive test coverage:
1. ✓ Zen Cart-managed subscription attributes validation
2. ✓ PayPal-managed subscription attributes validation
3. ✓ Routing logic for subscriptions without plan_id
4. ✓ Routing logic for subscriptions with plan_id
5. ✓ Next billing date calculation accuracy
6. ✓ saved_credit_card_id lookup from vault_id

### Existing Tests
All existing tests continue to pass:
- ✓ SubscriptionManagerNullHandlingTest.php

## Usage

### For Zen Cart-Managed Subscriptions (No Plan ID):
1. Create a subscription product with billing attributes:
   - `billing_period`: MONTH, WEEK, DAY, YEAR, etc.
   - `billing_frequency`: Number (e.g., 1 for every month)
   - `total_billing_cycles`: Total number of billing cycles
   - Do NOT include `plan_id`

2. When a customer orders this product with a saved card:
   - Subscription will appear under "Saved Card Subscriptions"
   - Next billing date is automatically calculated
   - Admin can edit: date, amount, card, product

### For PayPal-Managed Subscriptions (With Plan ID):
1. Create a subscription product with:
   - `plan_id`: PayPal subscription plan identifier

2. When a customer orders this product:
   - Subscription will appear under "Vaulted Subscriptions"
   - Next billing date is now visible and editable
   - PayPal manages the billing cycle

## Admin Pages

### Saved Card Subscriptions (`paypalac_saved_card_recurring.php`)
- **Purpose**: Manage Zen Cart-controlled subscriptions
- **Features**: Edit date, amount, card, product, cancel, reactivate
- **Table**: `TABLE_SAVED_CREDIT_CARDS_RECURRING`

### Vaulted Subscriptions (`paypalac_subscriptions.php`)
- **Purpose**: Manage PayPal-controlled subscriptions
- **Features**: View/edit billing details, vault assignments, next billing date (NEW)
- **Table**: `TABLE_PAYPAL_SUBSCRIPTIONS`

## Files Changed
1. `includes/classes/observers/auto.paypalacestful_recurring.php` - Routing logic
2. `admin/paypalac_subscriptions.php` - Next billing date display/edit
3. `tests/SubscriptionRoutingTest.php` - New test coverage

## Backward Compatibility
- Existing PayPal-managed subscriptions (with plan_id) continue to work as before
- No database schema changes required
- All existing tests pass
- New subscriptions are properly routed based on plan_id presence

## Notes for Testing
To test the next billing date editing:
1. Navigate to Admin → PayPal Subscriptions → Vaulted Subscriptions
2. Find a subscription record
3. Edit the "Next Billing Date" field in the Billing Details column
4. Click the update button
5. Verify the date is saved and displayed correctly
