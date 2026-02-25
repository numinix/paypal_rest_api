# Subscription Management Enhancement

## Overview
This enhancement enables automatic activation and admin management of subscriptions for products configured without PayPal plan IDs. Previously, these subscriptions would be logged as "awaiting_vault" or "pending" and never become visible in the admin interface.

## Problem Addressed
After placing an order for a subscription product managed in Zen Cart (no plan ID), subscriptions were not visible or manageable in the Zen Cart admin because:
- Subscriptions were logged with status "pending" or "awaiting_vault" 
- No mechanism existed to link subscriptions with vault when the vault token became available
- Status was never updated after vault token was saved
- Admin interface showed empty or incomplete subscription lists

## Solution Implemented

### 1. Automatic Subscription Activation
Added `SubscriptionManager::activateSubscriptionsWithVault()` method that:
- Links subscriptions to vault tokens when they become available
- Updates subscription status from "awaiting_vault" to "active"
- Associates the PayPal vault ID with the subscription record
- Updates the last_modified timestamp

### 2. Observer Integration
Enhanced `zcObserverPaypaladvcheckoutRecurring` to:
- Listen for `NOTIFY_PAYPALAC_VAULT_CARD_SAVED` notifications
- Automatically activate subscriptions when vault cards are saved
- Send `NOTIFY_SUBSCRIPTIONS_ACTIVATED` notification for downstream integrations

### 3. Database Query Improvements
- Handles both NULL and empty string vault_id values
- Uses constant for vault_id max length (64 characters)
- Properly filters subscriptions needing activation

## Flow Diagram

```
1. Customer Orders Subscription Product (no plan ID)
   ↓
2. Observer detects subscription attributes
   ↓
3. SubscriptionManager::logSubscription()
   - Status: "awaiting_vault" (if no vault yet)
   ↓
4. Vault Token Saved (immediately or via webhook)
   ↓
5. VaultManager::saveVaultedCard()
   ↓
6. NOTIFY_PAYPALAC_VAULT_CARD_SAVED sent
   ↓
7. zcObserverPaypaladvcheckoutRecurring catches notification
   ↓
8. activateSubscriptionsWithVault() called
   - Links subscription to vault
   - Status: "awaiting_vault" → "active"
   ↓
9. NOTIFY_SUBSCRIPTIONS_ACTIVATED sent
   ↓
10. Subscription visible in admin/paypalac_subscriptions.php
```

## Files Modified

### Core Functionality
- `includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php`
  - Added `activateSubscriptionsWithVault()` method
  - Added `VAULT_ID_MAX_LENGTH` constant

- `includes/classes/observers/auto.paypalacestful_recurring.php`
  - Added `NOTIFY_PAYPALAC_VAULT_CARD_SAVED` to event listeners
  - Added `updateNotifyPaypalacVaultCardSaved()` handler method

### Tests
- `tests/SubscriptionVaultActivationTest.php` (new)
  - 3 tests, 28 assertions
  - Tests activation logic, parameter validation, query conditions

- `tests/RecurringObserverVaultNotificationTest.php` (new)
  - 3 tests, 15 assertions  
  - Tests observer integration, notification handling, event attachment

## Admin Interface Impact

Subscriptions now appear in `admin/paypalac_subscriptions.php` with:
- Status: "active" (instead of stuck in "awaiting_vault")
- Full vault information displayed
- All management actions available:
  - View subscription details
  - Update billing information
  - Pause/Resume subscription
  - Cancel subscription
  - Export to CSV

## API Reference

### SubscriptionManager::activateSubscriptionsWithVault()

```php
/**
 * Link pending subscriptions with a newly vaulted payment token and activate them.
 *
 * @param int $customersId The customer ID
 * @param int $ordersId The order ID
 * @param int $paypalVaultId The paypal_vault_id from the vault table
 * @param string $vaultId The PayPal vault token ID
 * @return int Number of subscriptions that were activated
 */
public static function activateSubscriptionsWithVault(
    int $customersId,
    int $ordersId,
    int $paypalVaultId,
    string $vaultId
): int
```

### Observer Method

```php
/**
 * Handle vault card save notification to activate subscriptions.
 *
 * @param object $class The calling class
 * @param string $eventID The event identifier
 * @param array $vaultRecord The saved vault record from VaultManager
 */
public function updateNotifyPaypalacVaultCardSaved(
    &$class,
    $eventID,
    $vaultRecord
): void
```

## Notifications

### Consumed
- `NOTIFY_PAYPALAC_VAULT_CARD_SAVED` - Triggered when vault card is saved

### Produced
- `NOTIFY_SUBSCRIPTIONS_ACTIVATED` - Triggered when subscriptions are activated
  - Parameters:
    - `customers_id` - Customer ID
    - `orders_id` - Order ID
    - `vault_id` - Vault token ID
    - `activated_count` - Number of subscriptions activated

## Database Schema

No schema changes required. Uses existing columns:
- `paypal_subscriptions.paypal_vault_id` - Links to vault table
- `paypal_subscriptions.vault_id` - PayPal vault token
- `paypal_subscriptions.status` - Subscription status
- `paypal_subscriptions.last_modified` - Timestamp

## Backward Compatibility

✅ Fully backward compatible:
- Existing subscriptions remain unchanged
- PayPal-managed subscriptions (with plan_id) work as before
- No database migrations required
- No configuration changes needed
- Existing observers and webhooks unaffected

## Testing

All tests pass:
```
PHPUnit 8.5.50

Subscription Vault Activation
 ✔ Activate subscriptions with vault links and activates subscriptions
 ✔ Activate subscriptions with invalid parameters returns zero
 ✔ Activate subscriptions queries correct conditions

Recurring Observer Vault Notification
 ✔ Observer activates subscriptions when vault notification received
 ✔ Observer ignores invalid vault record
 ✔ Observer attaches to vault notification

OK (6 tests, 43 assertions)
```

## Security Considerations

- Input validation: All parameters validated before database operations
- SQL injection protection: Uses zen_db_input() and parameterized queries
- Status transitions: Only allows activation from specific states
- Vault verification: Requires valid vault record before activation
- No external API calls: All operations are local database updates

## Performance Impact

Minimal:
- Single SELECT query per order (finds subscriptions to activate)
- One UPDATE per subscription (typically 1-2 subscriptions per order)
- Executes only when vault card is saved (not on every request)
- Uses database indexes for efficient queries

## Future Enhancements

Potential improvements for future releases:
1. Batch processing for webhook-based activations
2. Retry logic for failed activations
3. Admin notification when subscriptions are activated
4. Dashboard widget showing recent activations
5. Activation audit trail in subscription history

## Support

For questions or issues:
- Review admin/paypalac_subscriptions.php for subscription management
- Check logs for NOTIFY_SUBSCRIPTIONS_ACTIVATED events
- Verify vault records exist in paypal_vault table
- Ensure subscription attributes are properly configured on products
