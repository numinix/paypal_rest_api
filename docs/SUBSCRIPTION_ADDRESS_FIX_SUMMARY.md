# Subscription Billing Address and Shipping Address Fix

## Summary

This PR addresses critical issues with PayPal saved card subscriptions by adding support for billing address and shipping information storage and editing.

## Problem Statement

1. **Database Error**: Orders for subscriptions were failing with MySQL error:
   ```
   MySQL error 1054: Unknown column 'billing_name' in 'field list'
   ```
   This occurred because the code was trying to insert billing address and shipping data into columns that didn't exist in the `saved_credit_cards_recurring` table.

2. **Missing Admin UI**: Administrators had no way to:
   - Edit billing addresses for active subscriptions
   - Edit shipping addresses for active subscriptions
   - Change the payment method (saved credit card) for a subscription

3. **Missing Customer UI**: Customers had no way to edit their subscription addresses (deferred to future enhancement).

## Solution

### 1. Database Schema Updates

**File**: `includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SavedCreditCardsManager.php`

Added 11 new columns to the `saved_credit_cards_recurring` table via `ensureLegacyColumns()`:

**Billing Address Fields (9 columns)**:
- `billing_name` - VARCHAR(255)
- `billing_company` - VARCHAR(255)
- `billing_street_address` - VARCHAR(255)
- `billing_suburb` - VARCHAR(255) (address line 2)
- `billing_city` - VARCHAR(255)
- `billing_state` - VARCHAR(255)
- `billing_postcode` - VARCHAR(255)
- `billing_country_id` - INT(11) (FK to countries table)
- `billing_country_code` - CHAR(2) (ISO country code)

**Shipping Information Fields (2 columns)**:
- `shipping_method` - VARCHAR(255)
- `shipping_cost` - DECIMAL(15,4)

These columns are automatically added when the module is loaded (no manual SQL execution required).

### 2. Admin UI Updates

**File**: `admin/paypalac_subscriptions.php`

Added comprehensive UI for managing subscription addresses:

1. **Billing Address Section**: 
   - Full billing address edit form with all 9 fields
   - Country code validation (uppercase, 2-letter pattern)
   - Only shown for saved card subscriptions

2. **Shipping Information Section**:
   - Shipping method text field
   - Shipping cost numeric field
   - Informational note about rate locking
   - Only shown for saved card subscriptions

3. **Payment Method Selector**:
   - Dropdown to change the saved credit card used for a subscription
   - Shows card type, last 4 digits, and holder name
   - Indicates default card
   - Only shown for saved card subscriptions

### 3. Backend Updates

**File**: `admin/paypalac_subscriptions.php`

Updated the `update_subscription` action handler to:
- Accept billing address fields from POST data
- Accept shipping fields from POST data
- Convert billing country code to country ID
- Pass all data to `paypalSavedCardRecurring::update_payment_info()`

**File**: `includes/classes/paypalSavedCardRecurring.php`

Updated `update_payment_info()` method to:
- Accept and save shipping_method
- Accept and save shipping_cost
- Use proper SQL escaping for all fields

### 4. Data Capture on Subscription Creation

**File**: `includes/classes/observers/auto.paypalacestful_recurring.php`

Already captures billing and shipping data from orders:
- Extracts billing address from orders table
- Converts country name to country_id and country_code
- Extracts shipping method and cost from orders_total table
- Passes all data to `schedule_payment()` when creating subscriptions

### 5. Testing

**File**: `tests/SavedCreditCardsRecurringBillingAddressTest.php`

Created comprehensive test that verifies:
- All 9 billing address columns are added
- Both shipping columns are added
- SavedCreditCardsManager::ensureSchema() executes without errors

## Benefits

### For Administrators
- ✅ **Full Control**: Can edit billing addresses for any subscription
- ✅ **Flexibility**: Can change payment methods if customer's card changes
- ✅ **Visibility**: Can see shipping costs locked at subscription creation
- ✅ **Error Resolution**: Can fix address errors without canceling subscriptions

### For Customers
- ✅ **Predictability**: Shipping costs locked at subscription creation
- ✅ **Independence**: Subscriptions maintain their own address data
- ✅ **Future Support**: Foundation laid for customer-facing address editing

### For System
- ✅ **No More Errors**: Subscription orders will no longer fail due to missing columns
- ✅ **Data Independence**: Subscriptions don't rely on customer address changes
- ✅ **Rate Locking**: Shipping costs frozen at subscription creation
- ✅ **Auditability**: Address changes can be tracked

## Upgrade Path

### Automatic (Recommended)
Simply update the code - columns will be added automatically when the admin or subscription pages are loaded.

### Manual (If Needed)
Run the SQL script: `docs/upgrade_add_subscription_billing_addresses.sql`

## Testing

Run the test suite:
```bash
php tests/SavedCreditCardsRecurringBillingAddressTest.php
```

Expected output:
```
✓ SavedCreditCardsManager class loaded successfully
✓ SavedCreditCardsManager::ensureSchema() executed without errors
✓ billing_name column was added via ALTER TABLE
✓ billing_company column was added via ALTER TABLE
✓ billing_street_address column was added via ALTER TABLE
✓ billing_suburb column was added via ALTER TABLE
✓ billing_city column was added via ALTER TABLE
✓ billing_state column was added via ALTER TABLE
✓ billing_postcode column was added via ALTER TABLE
✓ billing_country_id column was added via ALTER TABLE
✓ billing_country_code column was added via ALTER TABLE
✓ shipping_method column was added via ALTER TABLE
✓ shipping_cost column was added via ALTER TABLE

✅ All billing address and shipping column tests passed
```

## Security

- ✅ No security vulnerabilities detected by CodeQL
- ✅ All inputs properly sanitized using `zen_db_prepare_input()`
- ✅ SQL injection prevention via prepared values
- ✅ HTML entity encoding for output
- ✅ HTML5 pattern validation for country codes

## Backwards Compatibility

- ✅ Old subscriptions (before this feature) continue to work
- ✅ Empty fields shown for subscriptions without stored addresses
- ✅ Admin can populate addresses manually if needed
- ✅ No data migration required

## Future Enhancements

1. **Customer-facing UI**: Add page for customers to edit their own subscription addresses
2. **Shipping Address Storage**: Add separate shipping address fields (currently only billing and shipping info)
3. **Address Validation**: Integrate with address validation API
4. **Change Notifications**: Email customers when address is changed

## Files Changed

1. `includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SavedCreditCardsManager.php` - Schema management
2. `admin/paypalac_subscriptions.php` - Admin UI and handlers
3. `includes/classes/paypalSavedCardRecurring.php` - Backend update support
4. `docs/upgrade_add_subscription_billing_addresses.sql` - Manual SQL script (updated)
5. `tests/SavedCreditCardsRecurringBillingAddressTest.php` - Test coverage (new)

## Documentation References

See also:
- `docs/ADMIN_ADDRESS_EDITING_AND_SHIPPING.md` - Detailed feature documentation
- `docs/SUBSCRIPTION_BILLING_ADDRESS_ARCHITECTURE.md` - Architecture overview
