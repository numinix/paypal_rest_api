# PayPal REST API Module - Upgrade to v1.3.9

## Overview

Version 1.3.9 adds billing address and shipping information storage to subscriptions, making them independent from orders and customer records.

## What's New in v1.3.9

### Database Schema Changes

New columns in `saved_credit_cards_recurring` table:

**Billing Address:**
- `billing_name` - Customer name
- `billing_company` - Company name (optional)
- `billing_street_address` - Street address line 1
- `billing_suburb` - Street address line 2
- `billing_city` - City
- `billing_state` - State/Province
- `billing_postcode` - Postal/ZIP code
- `billing_country_id` - Country ID (FK)
- `billing_country_code` - ISO country code (CA, US, etc.)

**Shipping Information:**
- `shipping_method` - Shipping method name/description
- `shipping_cost` - Shipping cost at time of subscription creation

### Architecture Improvements

**Before v1.3.9:**
- Subscriptions dependent on customer/order data
- Complex lookups and inference during payment processing
- Fragile when customer data changes
- Not user-editable

**After v1.3.9:**
- Subscriptions store their own complete billing address
- Shipping cost locked at creation (protected from rate increases)
- Independent from orders and customer records
- Admin can edit subscription addresses
- Clean, predictable payment processing

## Upgrade Process

### Automatic Upgrade

The upgrade happens automatically when any admin accesses an admin page after code deployment:

1. **Detection:**
   - Module checks `MODULE_PAYMENT_PAYPALR_VERSION`
   - If < 1.3.9, upgrade is triggered

2. **Execution:**
   - Verifies `saved_credit_cards_recurring` table exists
   - Checks if columns already exist (idempotent)
   - Adds billing address and shipping columns
   - Updates module version to 1.3.9

3. **Completion:**
   - Version recorded in database
   - Won't run again (version-gated)

**No user intervention required.**

### Manual Upgrade (Optional)

If you prefer to run the upgrade manually before deployment:

```bash
mysql your_database < docs/upgrade_add_subscription_billing_addresses.sql
```

This is safe even if automatic upgrade will run later (idempotent).

### Verification

After upgrade, verify columns were added:

```sql
SHOW COLUMNS FROM saved_credit_cards_recurring 
WHERE Field LIKE 'billing_%' OR Field LIKE 'shipping_%';
```

Expected: 11 new columns (9 billing + 2 shipping)

## Impact on Existing Subscriptions

### Old Subscriptions (Created Before v1.3.9)

**Behavior:**
- Don't have billing address stored in subscription record
- Fall back to vault card billing_address (old behavior)
- Continue to work but with less reliability
- Should be replaced with new subscriptions when convenient

**Recommendation:**
- Users should create new subscriptions after upgrade
- Old subscriptions can be cancelled or allowed to expire naturally

### New Subscriptions (Created After v1.3.9)

**Behavior:**
- Complete billing address stored with subscription
- Shipping method and cost locked at creation
- Independent from orders and customer records
- Admin can edit addresses via admin interface
- Reliable, predictable payment processing

## Admin Features

### View Subscription Details

In admin > Modules > Payment > PayPal REST API > Subscriptions:
1. Click "Details" button for any subscription
2. Expandable section shows:
   - Billing address (formatted)
   - Shipping method and cost
   - Comments history

### Edit Billing Address

For **scheduled** subscriptions:
1. Click "Details" to expand
2. Click "(Edit)" next to Billing Address
3. Update any fields (name, address, city, state, postal, country)
4. Click "Save Address"
5. Confirm changes
6. Address updated in database

**Permissions:**
- Only scheduled subscriptions can be edited
- Completed/cancelled subscriptions are read-only

## Code Changes

### Files Modified

**Core Module:**
- `includes/modules/payment/paypalr.php`
  - Version bumped to 1.3.9
  - Added upgrade case in tableCheckup()

**Subscription Creation:**
- `includes/classes/observers/auto.paypalrestful_recurring.php`
  - Extract billing address from order
  - Extract shipping info from order
  - Store with subscription

**Subscription Management:**
- `includes/classes/paypalSavedCardRecurring.php`
  - Store billing/shipping on creation
  - Use stored address for payments
  - Support address updates

**Admin Interface:**
- `admin/paypalr_saved_card_recurring.php`
  - Display billing address
  - Edit form for address updates
  - Display shipping information

### Files Added

**Documentation:**
- `docs/SUBSCRIPTION_BILLING_ADDRESS_ARCHITECTURE.md`
- `docs/ADMIN_ADDRESS_EDITING_AND_SHIPPING.md`
- `docs/UPGRADE_TO_V1.3.9.md` (this file)
- `docs/upgrade_add_subscription_billing_addresses.sql`

## Testing

### Test Upgrade

1. Deploy code to test environment
2. Access admin page
3. Verify upgrade executed (check logs)
4. Verify columns exist in database
5. Verify `MODULE_PAYMENT_PAYPALR_VERSION` = '1.3.9'

### Test Subscription Creation

1. Place order with recurring product
2. Complete checkout with valid billing address
3. Check `saved_credit_cards_recurring` table
4. Verify billing_* and shipping_* fields populated
5. Verify country_code is correct (CA, US, etc.)

### Test Payment Processing

1. Wait for next billing cycle OR trigger cron manually
2. Check cron logs
3. Look for: "Using stored subscription billing_address"
4. Verify payment processes successfully
5. Check PayPal for transaction

### Test Admin Editing

1. Go to admin subscriptions page
2. Find a scheduled subscription
3. Click "Details"
4. Verify address displays correctly
5. Click "(Edit)"
6. Update address fields
7. Save changes
8. Verify database updated
9. Next payment should use new address

## Troubleshooting

### Upgrade Didn't Run

**Check:**
- Is code deployed?
- Access admin page to trigger
- Check `MODULE_PAYMENT_PAYPALR_VERSION` in database
- Check error logs

**Solution:**
- Run manual SQL upgrade
- Or: Force by temporarily changing version in database

### Columns Already Exist Error

**Cause:**
- Columns added manually
- Previous upgrade attempt

**Solution:**
- Upgrade code checks for existing columns (should not error)
- If error occurs, columns are already there (safe to ignore)

### Old Subscriptions Still Failing

**Cause:**
- Old subscriptions don't have stored addresses
- Using vault fallback (may be incomplete)

**Solution:**
- Create new subscription (will have complete data)
- Or: Admin can edit old subscription address

## Migration Plan

### For Production Deployment

1. **Backup Database**
   ```bash
   mysqldump database > backup_before_v1.3.9.sql
   ```

2. **Deploy Code**
   - Upload new files
   - Overwrite existing files

3. **Access Admin**
   - Visit any admin page
   - Upgrade runs automatically
   - Check logs for success

4. **Verify**
   - Check database columns
   - Check module version
   - Test new subscription creation

5. **Monitor**
   - Watch cron logs for payment processing
   - Verify payments succeed
   - Check for any errors

### Rollback Plan

If issues occur:

1. **Restore Code**
   - Revert to v1.3.8 files

2. **Database:**
   - Columns can stay (won't cause issues)
   - Or: Drop columns if needed

3. **Version:**
   ```sql
   UPDATE configuration 
   SET configuration_value = '1.3.8' 
   WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_VERSION';
   ```

## Support

For issues or questions:
- Check logs: `error_log`, cron output
- Review documentation in `docs/` directory
- Verify database schema matches expected
- Test with new subscription creation

## Summary

Version 1.3.9 provides:
- ✅ Independent subscription addresses
- ✅ Locked shipping rates
- ✅ Admin editing capability
- ✅ Reliable payment processing
- ✅ Automatic upgrade mechanism
- ✅ Backwards compatibility

This is a significant architectural improvement that makes subscriptions more robust and manageable.
