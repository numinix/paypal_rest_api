# Subscription Failure Reschedule Fix

## Problem Fixed

When a subscription payment failed, the cron job was incorrectly creating a **new duplicate subscription** instead of updating the existing subscription's next payment date. This caused:

1. **Duplicate subscriptions** in the database
2. Original subscription stuck in "failed" status with yesterday's date
3. New subscription created with "scheduled" status and tomorrow's date
4. Loss of subscription history and payment tracking

Additionally, REST API subscriptions were incorrectly reported as "Legacy module unavailable" when the `api_type` field in the `saved_credit_cards` table was empty or NULL.

## Example of the Bug

### Before Fix
```
Initial state:
- Subscription #1: status=scheduled, next_payment_date=2026-01-29

After payment fails:
- Subscription #1: status=failed, next_payment_date=2026-01-29
- Subscription #2: status=scheduled, next_payment_date=2026-01-30 (NEW DUPLICATE!)
```

### After Fix
```
Initial state:
- Subscription #1: status=scheduled, next_payment_date=2026-01-29

After payment fails:
- Subscription #1: status=failed, next_payment_date=2026-01-30 (UPDATED, not duplicated)
```

## Root Cause

The cron job was calling `schedule_payment()` after a payment failure, which always **inserts a new row** into the database:

```php
// OLD CODE (line ~659 in cron/paypal_saved_card_recurring.php)
$paypalSavedCardRecurring->schedule_payment(
    $payment_details['amount'], 
    $tomorrow, 
    $payment_details['saved_credit_card_id'], 
    $rescheduleOrdersProductsId, 
    'Recurring payment automatically scheduled after failure.', 
    $metadata
);
```

## Solution

Changed the cron to use `update_payment_info()` instead, which **updates the existing subscription**:

```php
// NEW CODE (line ~659 in cron/paypal_saved_card_recurring.php)
$paypalSavedCardRecurring->update_payment_info($payment_id, array(
    'date' => $tomorrow,
    'comments' => 'Recurring payment rescheduled after failure.'
));
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
if (in_array($api_type, array('paypalr', 'rest')) || ($api_type === '' && $has_vault_card)) {
    // Process as REST API payment
}
```

This prevents REST API subscriptions from incorrectly falling through to the legacy payment processing code and getting the "Legacy module unavailable" error.

## How It Works Now

When a payment fails:

1. `process_payment()` attempts the payment and fails
2. `update_payment_status()` sets status to 'failed' and logs the error
3. Cron checks if max retry attempts have been exceeded
4. If not exceeded, **updates the existing subscription**:
   - Sets `next_payment_date` to tomorrow
   - Keeps status as 'failed'
   - Appends comment about rescheduling
5. Cron sends notification email to customer
6. Tomorrow, cron automatically retries (because `get_scheduled_payments()` includes 'failed' status)

## Benefits

### Prevents Data Corruption
- No duplicate subscriptions
- Maintains subscription history
- Preserves payment tracking

### Correct Auto-Retry Behavior
- Subscription remains in 'failed' status
- Next payment date is updated correctly
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
1. Cron uses `update_payment_info()` instead of `schedule_payment()` on failure
2. REST API subscription detection includes vault card fallback
3. Failed subscriptions are included in `get_scheduled_payments()`

## Files Changed

1. **cron/paypal_saved_card_recurring.php** (line ~659)
   - Changed from `schedule_payment()` to `update_payment_info()`
   
2. **includes/classes/paypalSavedCardRecurring.php** (line ~1140)
   - Added vault card fallback for REST API detection

3. **tests/SubscriptionFailureRescheduleTest.php** (new file)
   - Comprehensive test coverage

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

## Related Documentation

- `docs/AUTO_RETRY_FAILED_SUBSCRIPTIONS.md` - How auto-retry works
- `docs/MAX_RETRY_ATTEMPTS.md` - Configuring maximum retry attempts
- `docs/CRON_TROUBLESHOOTING.md` - General cron troubleshooting

## Change History

- **2026-01-30:** Fixed duplicate subscription creation on payment failure
- **2026-01-30:** Added REST API subscription detection fallback
- **Previous:** Bug existed - duplicates were created on each failure
