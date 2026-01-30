# Cron Not Processing Subscriptions - Troubleshooting Guide

## Problem

The cron shows "Processed: 0" but subscriptions exist in the admin panel.

## Common Causes

### 1. Subscription Status is Not "scheduled"

The cron only processes subscriptions with `status = 'scheduled'`. If a subscription has failed previously, it may be stuck in `status = 'failed'`.

**Solution:** Update the subscription status to 'scheduled' in the admin or database.

**In Admin:**
- Go to Admin > PayPal > Saved Card Subscriptions
- Find the subscription
- If it shows "Failed" status, you can reactivate it or manually update the status

**In Database:**
```sql
UPDATE saved_credit_cards_recurring 
SET status = 'scheduled' 
WHERE saved_credit_card_recurring_id = 1;
```

### 2. Next Payment Date is in the Future

The cron only processes subscriptions where `next_payment_date <= today's date`.

**Check the date:**
```sql
SELECT saved_credit_card_recurring_id, next_payment_date, status 
FROM saved_credit_cards_recurring 
WHERE saved_credit_card_recurring_id = 1;
```

**Solution:** Update the next_payment_date to today or earlier:
```sql
UPDATE saved_credit_cards_recurring 
SET next_payment_date = CURDATE() 
WHERE saved_credit_card_recurring_id = 1;
```

### 3. Column Name Issues

After our fixes, the cron looks for `next_payment_date` column. If your database still has `date` column or is missing `next_payment_date`, the query will fail.

**Check your schema:**
```sql
SHOW COLUMNS FROM saved_credit_cards_recurring LIKE '%date%';
```

**Expected columns:**
- `date_added` - When subscription was created
- `next_payment_date` - When next payment is due
- `last_modified` - Last update timestamp

## Using the Debug Output

As of the latest commit, the cron now shows debug output:

```
=== DEBUG: All Subscriptions in Database ===
ID: 1 | Status: failed | Next Payment: 2026-01-29 | Product: NEW NCRS Membership Canada
=== END DEBUG ===

No payments scheduled for today (2026-01-30) or earlier.
Subscriptions must have:
  - status = 'scheduled'
  - next_payment_date <= '2026-01-30'
```

This immediately shows:
1. The subscription exists (ID: 1)
2. The status is "failed" (not "scheduled")
3. The next payment date was yesterday (2026-01-29)

**To fix this specific case:**
```sql
UPDATE saved_credit_cards_recurring 
SET status = 'scheduled', 
    next_payment_date = '2026-01-30'
WHERE saved_credit_card_recurring_id = 1;
```

## Workflow After a Failed Payment

When a payment fails, the subscription status changes to "failed". The cron will NOT automatically retry failed subscriptions on subsequent runs.

**To re-enable a failed subscription:**

1. **Verify the payment method** - Make sure the credit card is valid and not expired
2. **Update the status** - Change from "failed" to "scheduled"  
3. **Set the next payment date** - Usually set to today or the next billing date
4. **Run the cron** - The subscription will be picked up on the next run

**Admin Interface:**
- Some admin pages have a "Reactivate" button for failed subscriptions
- This automatically sets status back to "scheduled"

## Preventing Future Issues

1. **Monitor failed payments** - Set up notifications for failed subscriptions
2. **Auto-retry logic** - Consider implementing auto-retry for failed payments (with limits)
3. **Customer notifications** - Email customers when their payment fails so they can update their card
4. **Card expiration checks** - Proactively notify customers before cards expire

## Testing

To test if a subscription will be processed:

```sql
-- This query mimics what the cron does
SELECT saved_credit_card_recurring_id 
FROM saved_credit_cards_recurring 
WHERE status = 'scheduled' 
  AND next_payment_date <= CURDATE();
```

If this returns your subscription ID, the cron will process it on the next run.

## Related Files

- `cron/paypal_saved_card_recurring.php` - The cron job
- `includes/classes/paypalSavedCardRecurring.php` - Contains `get_scheduled_payments()`
- `admin/paypalr_saved_card_recurring.php` - Admin interface for managing subscriptions
