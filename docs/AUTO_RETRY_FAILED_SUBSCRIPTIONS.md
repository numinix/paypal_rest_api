# Automatic Retry for Failed Subscriptions

## Feature Overview

As of this update, the PayPal recurring subscription cron automatically retries failed subscriptions on their next scheduled payment date.

## How It Works

### Previous Behavior
- Cron only processed subscriptions with `status = 'scheduled'`
- When a payment failed, status changed to `'failed'`
- Failed subscriptions remained in failed state until manually reactivated
- Required admin intervention or SQL update

### New Behavior
- Cron processes subscriptions with `status = 'scheduled'` **OR** `status = 'failed'`
- Failed subscriptions are automatically retried on their next scheduled date
- No manual intervention required
- Industry-standard subscription behavior

## Example Flow

1. **Initial Payment (Jan 1)**
   - Payment succeeds
   - Status: `'complete'`
   - Next payment date: Jan 8

2. **Second Payment (Jan 8)**
   - Payment fails (card declined)
   - Status: `'failed'`
   - Next payment date: Jan 15

3. **Automatic Retry (Jan 15)**
   - Cron automatically processes the failed subscription
   - Attempts payment again
   - If successful: Status → `'complete'`, Next date → Jan 22
   - If failed: Status stays `'failed'`, Next date → Jan 22
   - Continues retrying on each scheduled date

## Benefits

### For Customers
- Grace period to resolve payment issues
- Time to add funds to account
- Time to update expired cards
- Subscription continues without interruption (if resolved)

### For Merchants
- Increased successful payments (customers often resolve issues)
- Less manual work (no need to manually reactivate)
- Better revenue capture
- Improved customer retention

### For Admins
- Less manual intervention required
- Standard subscription system behavior
- Fewer support tickets about "failed" subscriptions

## Technical Details

### SQL Query
```sql
-- Old query (scheduled only)
SELECT saved_credit_card_recurring_id 
FROM saved_credit_cards_recurring 
WHERE status = 'scheduled' 
  AND next_payment_date <= CURDATE();

-- New query (scheduled + failed)
SELECT saved_credit_card_recurring_id 
FROM saved_credit_cards_recurring 
WHERE (status = 'scheduled' OR status = 'failed')
  AND next_payment_date <= CURDATE();
```

### Code Location
- **Method:** `paypalSavedCardRecurring::get_scheduled_payments()`
- **File:** `includes/classes/paypalSavedCardRecurring.php`
- **Line:** ~1940

## Configuration

No configuration required. This behavior is automatic.

## Disabling Auto-Retry (if needed)

If you want to prevent a specific subscription from being retried:

**Option 1: Cancel it**
```sql
UPDATE saved_credit_cards_recurring 
SET status = 'cancelled' 
WHERE saved_credit_card_recurring_id = 1;
```

**Option 2: Set future next_payment_date**
```sql
UPDATE saved_credit_cards_recurring 
SET next_payment_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
WHERE saved_credit_card_recurring_id = 1;
```

## Preventing Infinite Retries

### Current Behavior
The system will retry indefinitely on each scheduled date as long as:
- Status is `'failed'`
- Next payment date is <= today

### Future Enhancement (Optional)
Consider implementing a retry limit:
- Add `retry_count` column to track attempts
- Add `max_retries` configuration setting
- After max retries reached, change status to `'cancelled'` or `'suspended'`

This would prevent indefinitely retrying subscriptions that will never succeed (e.g., card permanently invalid).

## Monitoring

### Check Failed Subscriptions
```sql
SELECT saved_credit_card_recurring_id, 
       products_name, 
       next_payment_date,
       status
FROM saved_credit_cards_recurring 
WHERE status = 'failed'
ORDER BY next_payment_date;
```

### Check What Will Be Retried Today
```sql
SELECT saved_credit_card_recurring_id, 
       products_name, 
       next_payment_date,
       status
FROM saved_credit_cards_recurring 
WHERE status = 'failed'
  AND next_payment_date <= CURDATE();
```

## Customer Communication

Consider implementing customer notifications for failed payments:

1. **Immediate notification** - Payment failed, will retry
2. **Before next retry** - Reminder to update payment method
3. **After successful retry** - Confirmation payment went through
4. **After multiple failures** - Warning that subscription may be cancelled

This keeps customers informed and increases the success rate of retries.

## Related Documentation

- `docs/CRON_TROUBLESHOOTING.md` - General troubleshooting guide
- `docs/CRON_DATE_FIX.md` - Date column fixes
- Zen Cart subscription documentation

## Change History

- **2026-01-30:** Implemented automatic retry for failed subscriptions
- **Previous:** Manual reactivation required for failed subscriptions
