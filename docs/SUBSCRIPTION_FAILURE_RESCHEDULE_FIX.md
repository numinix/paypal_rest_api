# Subscription Failure Handling Fix

## Problem Fixed

When a subscription payment failed, the cron job had two issues:

1. **Creating duplicate subscriptions** instead of keeping the existing one
2. **Incorrectly updating next_payment_date to "tomorrow"** which caused subscription drift

Additionally, REST API subscriptions were incorrectly reported as "Legacy module unavailable" when the `api_type` field in the `saved_credit_cards` table was empty or NULL.

## The Subscription Drift Problem

### What is Subscription Drift?

Subscription drift occurs when billing dates move forward with each failure, losing alignment with the original billing schedule.

**Example of drift (INCORRECT behavior):**
```
Initial: next_payment_date = Jan 15 (original billing day)
Fails on Jan 15 → updates to Jan 16
Fails on Jan 16 → updates to Jan 17
Fails on Jan 17 → updates to Jan 18
Succeeds on Jan 18 → next billing = Feb 18 (WRONG! Should be Feb 15)
```

### Correct Behavior (No Drift)

The next_payment_date should **stay the same** when a payment fails. This allows:
- Maintaining the original billing schedule
- Collecting missed payments (dates in the past are valid)
- Proper calculation of next billing date from intended date

**Example of correct behavior:**
```
Initial: next_payment_date = Jan 15 (original billing day)
Fails on Jan 15 → stays Jan 15
Fails on Jan 16 → stays Jan 15
Fails on Jan 17 → stays Jan 15
Succeeds on Jan 18 → calculates next billing from Jan 15 → Feb 15 (CORRECT!)
```

## Example Flow

### Before Fix (Creating Duplicates)
```
Initial state:
- Subscription #1: status=scheduled, next_payment_date=2026-01-29

After payment fails:
- Subscription #1: status=failed, next_payment_date=2026-01-29
- Subscription #2: status=scheduled, next_payment_date=2026-01-30 (DUPLICATE!)
```

### After Fix (Preserving Date)
```
Initial state:
- Subscription #1: status=scheduled, next_payment_date=2026-01-29

After payment fails:
- Subscription #1: status=failed, next_payment_date=2026-01-29 (UNCHANGED - no drift!)

After payment succeeds later:
- Subscription #1: status=complete, date=2026-01-29 (even if processed on 2026-01-31)
- Subscription #2: status=scheduled, next_payment_date=calculated from 2026-01-29
```

## Root Cause

The original cron code called `schedule_payment()` after a payment failure, which always **inserts a new row** into the database:

```php
// ORIGINAL BUGGY CODE (line ~659 in cron/paypal_saved_card_recurring.php)
$paypalSavedCardRecurring->schedule_payment(
    $payment_details['amount'], 
    $tomorrow, 
    $payment_details['saved_credit_card_id'], 
    $rescheduleOrdersProductsId, 
    'Recurring payment automatically scheduled after failure.', 
    $metadata
);
```

The first fix attempted to use `update_payment_info()` but still updated the date to tomorrow, causing drift:

```php
// FIRST FIX ATTEMPT (still had drift issue)
$paypalSavedCardRecurring->update_payment_info($payment_id, array(
    'date' => $tomorrow,  // This causes drift!
    'comments' => 'Recurring payment rescheduled after failure.'
));
```

## Solution

The correct solution is to **do nothing** when a payment fails (except update status and send notification):

```php
// CORRECT FIX (line ~654 in cron/paypal_saved_card_recurring.php)
// Keep trying - subscription will be retried by cron on next run
// Do NOT update next_payment_date - this prevents subscription drift
// The next billing date is calculated from the original schedule, not from today
$message = sprintf(SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL, ...);
zen_mail(...);
$next_retry_date = $payment_details['next_payment_date'];
```

## Additional Fix: REST API Detection

Added a fallback in `process_payment()` to detect REST API subscriptions even when `api_type` is not set:

```php
// includes/classes/paypalSavedCardRecurring.php (line ~1140)
$api_type = isset($payment_details['api_type']) ? $payment_details['api_type'] : '';
// Fallback: if api_type is not set but there's a vault card, it's a REST API subscription
$has_vault_card = isset($payment_details['paypal_vault_card']) 
    && is_array($payment_details['paypal_vault_card']) 
    && !empty($payment_details['paypal_vault_card']);
if (in_array($api_type, array('paypalac', 'rest')) || ($api_type === '' && $has_vault_card)) {
    // Process as REST API payment
}
```

This prevents REST API subscriptions from incorrectly falling through to the legacy payment processing code and getting the "Legacy module unavailable" error.

## How It Works Now

When a payment fails:

1. `process_payment()` attempts the payment and fails
2. `update_payment_status()` sets status to 'failed' and logs the error
3. Cron checks if max retry attempts have been exceeded
4. If not exceeded:
   - **Does NOT update next_payment_date** (prevents drift)
   - Status remains 'failed'
   - Sends notification email to customer
5. Tomorrow, cron automatically retries with the **same date** (because `get_scheduled_payments()` includes 'failed' status)
6. When payment eventually succeeds:
   - Calculates intended billing date from the original schedule
   - Creates order with the intended date (may be in the past)
   - Calculates next billing date from the intended date (not from today)
   - Creates NEW subscription for next billing cycle

## Benefits

### Prevents Data Corruption
- No duplicate subscriptions
- Maintains subscription history
- Preserves payment tracking

### Prevents Subscription Drift
- Billing dates stay aligned to original schedule
- Customers billed on consistent dates
- Missed payments correctly attributed to their billing period

### Correct Auto-Retry Behavior
- Subscription remains in 'failed' status
- Same next_payment_date allows daily retries
- Cron automatically retries as designed

### Proper REST API Handling
- REST API subscriptions are correctly identified
- Vault cards are used for payment processing
- No false "Legacy module unavailable" errors

## Testing

A comprehensive test has been added to verify the fix:

```bash
php tests/SubscriptionFailureRescheduleTest.php
```

This test verifies:
1. Cron does NOT update next_payment_date on failure (no drift)
2. REST API subscription detection includes vault card fallback
3. Failed subscriptions are included in `get_scheduled_payments()`
4. Successful payments create new subscription for next cycle

## Files Changed

1. **cron/paypal_saved_card_recurring.php** (line ~654)
   - Removed call to `update_payment_info()` that was updating the date
   - Now preserves next_payment_date to prevent drift
   
2. **includes/classes/paypalSavedCardRecurring.php** (line ~1140)
   - Added vault card fallback for REST API detection

3. **tests/SubscriptionFailureRescheduleTest.php** (updated)
   - Comprehensive test coverage for date preservation

4. **docs/SUBSCRIPTION_FAILURE_RESCHEDULE_FIX.md** (updated)
   - Complete documentation of drift issue and fix

## Migration Notes

### If You Have Duplicate Subscriptions

You may have existing duplicate subscriptions created by the old bug. To clean them up:

```sql
-- Find potential duplicates (same customer, product, and created recently)
SELECT 
    sccr1.saved_credit_card_recurring_id AS original_id,
    sccr2.saved_credit_card_recurring_id AS duplicate_id,
    sccr1.products_name,
    sccr1.status AS original_status,
    sccr2.status AS duplicate_status,
    sccr1.next_payment_date AS original_date,
    sccr2.next_payment_date AS duplicate_date
FROM saved_credit_cards_recurring sccr1
JOIN saved_credit_cards_recurring sccr2 
    ON sccr1.saved_credit_card_id = sccr2.saved_credit_card_id
    AND sccr1.products_id = sccr2.products_id
    AND sccr1.saved_credit_card_recurring_id < sccr2.saved_credit_card_recurring_id
WHERE sccr1.status = 'failed'
  AND sccr2.status = 'scheduled'
  AND sccr2.next_payment_date = DATE_ADD(sccr1.next_payment_date, INTERVAL 1 DAY);
```

**Manual review recommended before deleting any subscriptions.**

### If You Have Drifted Billing Dates

Subscriptions that failed multiple times may have drifted billing dates. The system will now prevent further drift, but existing drift won't be automatically corrected. You may need to manually adjust billing dates if needed.

## Related Documentation

- `docs/AUTO_RETRY_FAILED_SUBSCRIPTIONS.md` - How auto-retry works
- `docs/MAX_RETRY_ATTEMPTS.md` - Configuring maximum retry attempts
- `docs/CRON_TROUBLESHOOTING.md` - General cron troubleshooting

## Change History

- **2026-01-30:** Fixed subscription drift by preserving next_payment_date on failure
- **2026-01-30:** Fixed duplicate subscription creation
- **2026-01-30:** Added REST API subscription detection fallback
- **Previous:** Bugs existed - duplicates created and dates drifted on each failure
