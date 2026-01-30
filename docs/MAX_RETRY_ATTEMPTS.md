# Configuring Maximum Retry Attempts for Failed Subscriptions

## Overview

When a recurring subscription payment fails, the system can automatically retry the payment. This document explains how to configure the maximum number of retry attempts.

## Default Behavior

**By default, retries are UNLIMITED.**

If the constant `SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED` is not defined, the system will retry failed subscriptions indefinitely (once per day).

This is often the desired behavior because:
- Customers may have temporary issues (insufficient funds, card temporarily blocked)
- Customers may take time to update their payment method
- Merchants want to maximize successful payments and revenue

## Configuring Maximum Retries

To limit the number of retry attempts, define the constant in your configuration:

### Method 1: Configuration File

Create or edit a configuration file (e.g., `includes/extra_configures/paypal_recurring_config.php`):

```php
<?php
/**
 * PayPal Recurring Subscription Configuration
 */

// Maximum number of failed payment attempts before stopping retries
// Set to 0 for unlimited retries (default)
// Set to a positive number (e.g., 3, 5, 10) to limit retries
define('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED', 5);
```

### Method 2: Admin Module Configuration

If your PayPal module supports admin configuration, you can define this in the module settings. Check your admin panel under:
- Modules > Payment > PayPal Saved Card
- Look for "Max Failed Attempts" or similar setting

## How It Works

### Unlimited Retries (Default)

When `SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED` is **not defined** or set to **0**:

```
Day 1: Payment fails → Reschedule for Day 2
Day 2: Payment fails → Reschedule for Day 3
Day 3: Payment fails → Reschedule for Day 4
... continues indefinitely ...
Day 30: Payment succeeds → Normal billing cycle resumes
```

**Log output:**
```
Payment failed, rescheduled for tomorrow. Customer has been notified. (unlimited retries)
```

**Results array:**
```php
'max_attempts' => 'Unlimited'
```

### Limited Retries (e.g., 5 attempts)

When `SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED` is set to **5**:

```
Day 1: Payment fails (attempt 1 of 5) → Reschedule for Day 2
Day 2: Payment fails (attempt 2 of 5) → Reschedule for Day 3
Day 3: Payment fails (attempt 3 of 5) → Reschedule for Day 4
Day 4: Payment fails (attempt 4 of 5) → Reschedule for Day 5
Day 5: Payment fails (attempt 5 of 5) → STOP retrying, send final notification
```

**Log output (attempts 1-4):**
```
Payment failed, rescheduled for tomorrow. Customer has been notified. (attempt 3 of 5)
```

**Log output (attempt 5):**
```
User has been notified after 5 consecutive failed attempts to process card (max: 5)
```

**Results array:**
```php
'max_attempts' => 5
```

## Email Notifications

### During Retry Period

When a payment fails but will be retried:
- Email subject: `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL_SUBJECT`
- Email content: `SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL`
- Tells customer the payment failed and will be retried

### After Max Attempts Reached

When retries are exhausted (only if max > 0):
- Email subject: `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL_SUBJECT`
- Email content: `SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL`
- Tells customer all retry attempts failed and action is required

## Recommendations

### For Most Merchants: Unlimited Retries

**Recommended:** Leave undefined (unlimited retries)

**Pros:**
- Maximizes successful payments
- Gives customers time to resolve issues
- Reduces manual intervention
- Better customer experience

**Cons:**
- May delay recognition of permanently failed subscriptions
- Could send many notification emails

### For High-Value Subscriptions: Limited Retries

**Recommended:** Set to 5-10 attempts

**Pros:**
- Identifies problem subscriptions quickly
- Limits notification emails
- Forces customer action sooner
- Better for expensive subscriptions

**Cons:**
- May lose some recoverable subscriptions
- Requires more manual intervention

## Examples

### Example 1: E-commerce Store (Recommended: Unlimited)

**Business:** Online store selling $10-$50 monthly subscriptions

**Configuration:**
```php
// Don't define the constant, or explicitly set to 0
define('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED', 0);
```

**Rationale:**
- Low-value subscriptions benefit from patience
- Customers often resolve issues within a few days/weeks
- Maximizes subscription retention

### Example 2: Professional Services (Recommended: 5 attempts)

**Business:** B2B SaaS selling $100-$500 monthly subscriptions

**Configuration:**
```php
define('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED', 5);
```

**Rationale:**
- High-value subscriptions need attention quickly
- Business customers can resolve issues faster
- Prevents long periods of unpaid service access

### Example 3: Membership Site (Recommended: 10 attempts)

**Business:** Membership site with $20 monthly fee

**Configuration:**
```php
define('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED', 10);
```

**Rationale:**
- Balance between patience and intervention
- 10 days is reasonable for personal payment issues
- Not too many notification emails

## Monitoring

### Check Failed Subscriptions

Query to see subscriptions with failed payments:

```sql
SELECT 
    saved_credit_card_recurring_id,
    customers_id,
    products_name,
    status,
    next_payment_date,
    comments
FROM saved_credit_cards_recurring
WHERE status = 'failed'
ORDER BY next_payment_date DESC;
```

### Check Retry Count

To see how many times a subscription has failed, check the `count_failed_payments()` method in the cron log or query the comments field.

## Technical Details

### Code Location

**File:** `cron/paypal_saved_card_recurring.php`

**Lines:** ~637-665

### Logic

```php
// Get max allowed (0 = unlimited)
$max_fails_allowed = defined('SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED') 
    ? (int)SAVED_CREDIT_CARDS_RECURRING_MAX_FAILS_ALLOWED 
    : 0;

// Check if exceeded
$has_exceeded_max_attempts = ($max_fails_allowed > 0 && $num_failed_payments >= $max_fails_allowed);

if ($has_exceeded_max_attempts) {
    // Stop retrying, send final notification
} else {
    // Reschedule for tomorrow
}
```

### Key Points

1. **Default is 0 (unlimited)** - Safe fallback if constant not defined
2. **Only limits if > 0** - Zero means unlimited, any positive number is the limit
3. **Comparison uses >=** - Stops ON reaching the limit (not after)
4. **Daily retries** - Each retry is scheduled for the next day

## Troubleshooting

### Subscriptions Not Retrying

**Problem:** Failed subscriptions aren't being retried

**Check:**
1. Is the constant set to a very low number (e.g., 1)?
2. Has the subscription already exceeded the limit?
3. Is the cron job running daily?

**Solution:**
- Set to 0 for unlimited or increase the number
- Manually reset the subscription to 'scheduled' status
- Verify cron is scheduled correctly

### Too Many Retry Emails

**Problem:** Customers complaining about too many failure emails

**Check:**
1. Is the constant undefined or 0 (unlimited)?
2. Are customers actually resolving their payment issues?

**Solution:**
- Set a reasonable limit (5-10 attempts)
- Review email templates to ensure they're helpful
- Consider adding a "unsubscribe from retry notifications" option

### Subscriptions Stopping Too Soon

**Problem:** Subscriptions giving up before customers can fix issues

**Check:**
1. What is the current limit?
2. How long does it typically take customers to resolve issues?

**Solution:**
- Increase the limit or set to 0 (unlimited)
- Analyze customer behavior to find optimal retry period
- Consider different limits for different subscription types

## Related Documentation

- `docs/AUTO_RETRY_FAILED_SUBSCRIPTIONS.md` - How failed subscriptions are automatically retried
- `docs/CRON_TROUBLESHOOTING.md` - General cron troubleshooting
- `docs/BILLING_SCHEDULE_MAINTENANCE.md` - How billing schedules are maintained

## Summary

**Default:** Unlimited retries (0)
- Best for most use cases
- Maximizes successful payments
- Better customer experience

**Custom:** Set a positive number
- Good for high-value subscriptions
- Faster identification of problems
- Limits notification emails

Choose the approach that best fits your business model and customer base.
