# Maintaining Billing Schedule for Late Payments

## Overview

This document explains how the PayPal recurring subscription system maintains the original billing schedule even when payments are processed late.

## The Problem

When a subscription payment fails and is later successfully processed, the system needs to decide when to schedule the next payment. There are two approaches:

1. **Wrong Approach:** Calculate next payment from when the payment was actually processed
2. **Correct Approach:** Calculate next payment from the original billing schedule

## Example Scenario

### Monthly Subscription Billing on the 15th

**Timeline:**
- Jan 1: Subscription created
- Jan 15: Payment fails (card declined)
- Jan 20: Payment succeeds (customer added funds)

**Wrong Approach (Before Fix):**
- Base date: Jan 1 (date_added) or Jan 20 (processing date)
- Next payment: Feb 1 or Feb 20
- Problem: Billing schedule shifts forward

**Correct Approach (After Fix):**
- Base date: Jan 15 (next_payment_date from database)
- Next payment: Feb 15
- Result: Billing schedule maintained on the 15th of each month

## How It Works

### Key Code Change

**File:** `cron/paypal_saved_card_recurring.php`

**Before (Line 300):**
```php
$scheduledDateString = isset($paymentDetails['date']) ? $paymentDetails['date'] : '';
```

**After:**
```php
// Use next_payment_date as the base to maintain the original billing schedule
// This ensures that if a payment is late, the billing schedule doesn't shift forward
$scheduledDateString = isset($paymentDetails['next_payment_date']) ? $paymentDetails['next_payment_date'] : '';
```

### Why This Matters

The `$paymentDetails['date']` field is actually `date_added` (when the subscription was created), not `next_payment_date` (when the payment is due). Using `next_payment_date` ensures:

1. **Consistent billing dates** - Customers always billed on the same day
2. **Fair pricing** - Customers pay for the billing period they used
3. **Predictable schedule** - Both merchant and customer know when next payment is due

## Catch-Up Payments

When a payment is very late (over one billing period), the system will process multiple payments:

### Example: Payment Over a Month Late

**Scenario:**
- Monthly subscription, bills on 15th
- Jan 15 payment fails
- Feb 15 passes without payment
- Feb 20: Customer updates card and cron runs

**What Happens:**

1. **First calculation:**
   - Base: Jan 15 (next_payment_date from DB)
   - Next: Feb 15 (already passed)
   - Action: Process Feb 15 payment immediately

2. **After Feb 15 payment succeeds:**
   - Base: Feb 15 (the payment we just processed)
   - Next: Mar 15
   - Action: Schedule for Mar 15

3. **Cron detects Mar 15 is in future:**
   - Saves Mar 15 as next_payment_date
   - Exits loop

**Result:** Customer pays for both Jan and Feb, next payment Mar 15

## Edge Cases

### Multiple Failed Payments

If a payment fails multiple times before succeeding:

```
Jan 15: Fail (card declined)
Jan 20: Retry succeeds
```

The system uses `num_failed_payments` to adjust the intended billing date, but still calculates the next payment from the original schedule (Jan 15), not the retry date (Jan 20).

### Subscription Created Mid-Month

```
Created: Jan 10
First billing: Jan 15
Payment succeeds: Jan 15
Next billing: Feb 15
```

The billing date (15th) is maintained based on `next_payment_date`, not the creation date (10th).

### Annual Subscriptions

Same logic applies:
```
Created: Jan 10, 2026
First billing: Jan 15, 2026
Payment fails and succeeds Jan 20, 2026
Next billing: Jan 15, 2027 (NOT Jan 20, 2027)
```

## Testing

### Test Scenarios

The fix includes comprehensive tests in `tests/NextPaymentDateCalculationTest.php`:

1. **Monthly subscription, payment 5 days late**
   - Verifies next billing maintains the original day of month

2. **Monthly subscription, over a month late**
   - Verifies catch-up payments are processed
   - Verifies schedule is restored after catch-up

3. **Weekly subscription, payment 3 days late**
   - Verifies weekly subscriptions maintain day of week

4. **Comparison: date_added vs next_payment_date**
   - Demonstrates the difference between the wrong and right approaches

### Running Tests

```bash
php tests/NextPaymentDateCalculationTest.php
```

All tests should pass with output showing the calculations maintain the billing schedule.

## Benefits

### For Customers

1. **Predictability** - Always know when the next payment will occur
2. **Consistency** - Billing date doesn't change based on when payment processes
3. **Fair** - Only pay for the periods you use

### For Merchants

1. **Predictable cash flow** - Know when payments will arrive
2. **Less confusion** - Easier to explain billing to customers
3. **Professional** - Standard subscription system behavior

### For Support Teams

1. **Easier to explain** - "You're always billed on the 15th"
2. **Less tickets** - Fewer confused customers
3. **Clear documentation** - This document explains the behavior

## Related Code

### Files Modified

- `cron/paypal_saved_card_recurring.php` - Main fix (line 302)
- `tests/NextPaymentDateCalculationTest.php` - Test coverage

### Related Functions

- `determineIntendedBillingDate()` - Determines which date to use as base
- `advanceBillingCycle()` - Calculates next payment date
- `schedule_payment()` - Saves next payment date to database

## Database Fields

### saved_credit_cards_recurring table

- `date_added` - When subscription was created (not used for calculations)
- `next_payment_date` - When next payment is due (used for calculations)
- `last_modified` - Last time record was updated

The fix ensures we always use `next_payment_date` as the base for calculating the next billing cycle.

## Backward Compatibility

This fix is backward compatible:
- Existing subscriptions continue with their current schedule
- No migration needed
- No changes to database schema
- Works with all billing periods (day, week, month, year)

## Future Enhancements

Potential improvements to consider:

1. **Grace Period** - Allow X days grace before marking as failed
2. **Retry Schedule** - Automatic retries at specific intervals
3. **Customer Notifications** - Email when payment fails/succeeds
4. **Admin Dashboard** - Show subscriptions with upcoming payments
5. **Billing Day Adjustment** - Allow customer to change their billing day

## Summary

The fix ensures that late payments don't shift the billing schedule forward. By using `next_payment_date` instead of `date_added` or the current date, the system maintains the original billing schedule, providing consistency and fairness for both customers and merchants.

This is standard behavior for subscription systems and ensures customers can rely on consistent billing dates.
