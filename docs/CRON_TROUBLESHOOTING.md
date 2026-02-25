# Cron Not Processing Subscriptions - Troubleshooting Guide

## Problem

The cron shows "Processed: 0" but subscriptions exist in the admin panel.

## Automatic Retry for Failed Subscriptions

**As of the latest update, failed subscriptions are automatically retried on their next scheduled payment date.**

The cron now processes subscriptions with:
- `status = 'scheduled'` OR
- `status = 'failed'` (automatic retry)

This means if a payment fails (e.g., card declined), the cron will automatically retry it on the next scheduled date. The customer may have resolved the issue by that time (added funds, updated card, etc.).

## Common Causes (if subscriptions still not processing)

### 1. Subscription Status is "cancelled"

The cron does NOT process cancelled subscriptions. Only "scheduled" and "failed" subscriptions are processed.

**Check status:**
```sql
SELECT saved_credit_card_recurring_id, status, next_payment_date 
FROM saved_credit_cards_recurring 
WHERE saved_credit_card_recurring_id = 1;
```

**Solution:** If status is "cancelled" and you want to reactivate:
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

Recurring Payments — 2026-01-30 (America/New_York)
Processed: 1 Paid: 0 | Failed: 1 | Skipped: 0
```

**Note:** Failed subscriptions are now automatically retried. In the example above, subscription #1 with status "failed" and next payment date of 2026-01-29 WILL be processed because it's due and automatic retry is enabled.

## Workflow After a Failed Payment

When a payment fails, the subscription status changes to "failed". **The cron will automatically retry the subscription on the next scheduled date.**

This automatic retry gives customers time to:
- Add funds to their account
- Update their payment method
- Resolve any card issues

**If you want to prevent automatic retries:**
Set the status to "cancelled" instead of leaving it as "failed".

**To manually trigger an immediate retry:**
Update the next_payment_date to today:
```sql
UPDATE saved_credit_cards_recurring 
SET next_payment_date = CURDATE() 
WHERE saved_credit_card_recurring_id = 1;
```

Then run the cron.

## Testing

To test which subscriptions will be processed:

```sql
-- This query mimics what the cron does (now includes failed subscriptions)
SELECT saved_credit_card_recurring_id, status, next_payment_date
FROM saved_credit_cards_recurring 
WHERE (status = 'scheduled' OR status = 'failed')
  AND next_payment_date <= CURDATE();
```

If this returns your subscription ID, the cron will process it on the next run.

## Related Files

- `cron/paypalac_saved_card_recurring.php` - The cron job
- `includes/classes/paypalSavedCardRecurring.php` - Contains `get_scheduled_payments()`
- `admin/paypalac_saved_card_recurring.php` - Admin interface for managing subscriptions
