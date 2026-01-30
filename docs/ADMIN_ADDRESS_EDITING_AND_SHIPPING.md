# Admin Address Editing & Shipping Storage

## Overview

This feature allows administrators to edit subscription billing addresses and view shipping information. It completes the subscription independence architecture by making all subscription data manageable by admin.

## Features Implemented

### 1. Admin Can Edit Billing Addresses

Administrators can now update billing addresses for any scheduled subscription through the admin panel.

**Access:** Admin → Saved Card Subscriptions → Click "Details" → Click "(Edit)" next to Billing Address

**Editable Fields:**
- Billing Name
- Billing Company
- Street Address
- Address Line 2 (Suburb)
- City
- State/Province
- Postal Code
- Country Code (2-letter ISO code, e.g., CA, US)

### 2. Shipping Information Storage

Shipping method and cost are now captured from the original order and stored with each subscription.

**Stored Data:**
- `shipping_method` - Method name/description (e.g., "USPS Priority Mail")
- `shipping_cost` - Cost at time of order creation (e.g., 12.50)

**Purpose:** The shipping rate is locked at subscription creation and reused for recurring orders, protecting against carrier rate increases.

### 3. Admin Details View

Each subscription now has an expandable "Details" section showing:
- Complete billing address
- Shipping method and cost
- Comments history

## How It Works

### On Subscription Creation

```
1. Customer places order with recurring product
   ↓
2. Observer extracts:
   - Billing address from orders table
   - Shipping info from orders_total table
   ↓
3. Stores with subscription in saved_credit_cards_recurring
   ↓
4. Subscription now has complete, independent data
```

### Admin Editing Billing Address

```
1. Admin clicks "Details" on subscription
   ↓
2. Expandable section shows billing address
   ↓
3. Admin clicks "(Edit)"
   ↓
4. Form appears with current values
   ↓
5. Admin updates fields and clicks "Save Address"
   ↓
6. Confirmation dialog
   ↓
7. POST to update_billing_address action
   ↓
8. Billing address updated in database
   ↓
9. Success message displayed
```

### Viewing Shipping Information

```
1. Admin clicks "Details" on subscription
   ↓
2. Right side shows:
   - Shipping Method: "USPS Priority Mail"
   - Shipping Cost: $12.50
   - Note: Rate locked at subscription creation
```

## Database Schema

### New Columns in `saved_credit_cards_recurring`

**Billing Address (9 fields):**
```sql
billing_name VARCHAR(255)
billing_company VARCHAR(255)
billing_street_address VARCHAR(255)
billing_suburb VARCHAR(255)
billing_city VARCHAR(255)
billing_state VARCHAR(255)
billing_postcode VARCHAR(255)
billing_country_id INT(11)
billing_country_code CHAR(2)
```

**Shipping Information (2 fields):**
```sql
shipping_method VARCHAR(255)
shipping_cost DECIMAL(15,4)
```

## User Interface

### Subscription List View

```
+-----------------------------------------------------------------+
| ID | Product | Customer | ... | Status | Actions              |
|----|---------|----------|-----|--------|----------------------|
| 1  | Product | John Doe | ... | active | [Skip] [Cancel]      |
|    |         |          |     |        | [Details]            |
+-----------------------------------------------------------------+
```

### Expanded Details View

```
+-------------------------------------------------------------------+
| Subscription #1 Details                                           |
|-------------------------------------------------------------------|
| Billing Address (Edit)          | Shipping Information           |
|                                  |                                |
| John Doe                         | Method: USPS Priority Mail     |
| Acme Corp                        | Cost: $12.50                   |
| 123 Main St                      |                                |
| Suite 100                        | This rate was locked at        |
| San Francisco, CA 94105          | subscription creation and will |
| US                               | be reused for recurring orders.|
|                                  |                                |
|                                  | Comments:                      |
|                                  | Subscription created from      |
|                                  | order #12345                   |
+-------------------------------------------------------------------+
```

### Edit Address Form

When admin clicks "(Edit)":

```
+-----------------------------------------------+
| Name:           [John Doe                   ] |
| Company:        [Acme Corp                  ] |
| Street Address: [123 Main St               ] |
| Address Line 2: [Suite 100                 ] |
| City:           [San Francisco             ] |
| State/Province: [CA                        ] |
| Postal Code:    [94105                     ] |
| Country Code:   [US                        ] |
|                                               |
| [Save Address] [Cancel]                       |
+-----------------------------------------------+
```

## Code Examples

### Subscription Creation (Observer)

```php
// Extract shipping from orders_total
$shippingQuery = $db->Execute(
    "SELECT class, title, value
       FROM " . TABLE_ORDERS_TOTAL . "
      WHERE orders_id = " . $ordersId . "
        AND class = 'ot_shipping'
      LIMIT 1"
);

if (!$shippingQuery->EOF) {
    $shippingInfo['shipping_method'] = $shippingQuery->fields['title'];
    $shippingInfo['shipping_cost'] = $shippingQuery->fields['value'];
}

// Pass to schedule_payment
$subscriptionId = $savedCardRecurring->schedule_payment(
    $amount,
    $nextBillingDate,
    $savedCreditCardId,
    $ordersProductsId,
    'Subscription created',
    array_merge($metadata, $billingAddress, $shippingInfo)
);
```

### Admin Address Update Action

```php
case 'update_billing_address':
    $addressData = array();
    $addressFields = array('billing_name', 'billing_company', ...);
    
    foreach ($addressFields as $field) {
        if (isset($_POST[$field])) {
            $addressData[$field] = zen_db_prepare_input($_POST[$field]);
        }
    }
    
    // Get country ID from country code
    if (!empty($addressData['billing_country_code'])) {
        $countryQuery = $db->Execute(...);
        $addressData['billing_country_id'] = $countryQuery->fields['countries_id'];
    }
    
    $paypalSavedCardRecurring->update_payment_info(
        $_POST['saved_card_recurring_id'],
        $addressData
    );
```

### Backend Update Support

```php
// In update_payment_info()
foreach (array('billing_name', 'billing_company', ...) as $addressKey) {
    if (isset($data[$addressKey])) {
        $snapshotUpdates[] = $addressKey . " = '" . 
            $this->escape_db_value($data[$addressKey]) . "'";
    }
}
```

## Security Considerations

1. **Admin Only**: Only admin users can edit addresses (existing admin authentication)
2. **Input Sanitization**: Uses `zen_db_prepare_input()` and `escape_db_value()`
3. **Confirmation Dialogs**: User confirms before saving changes
4. **Status Check**: Only scheduled subscriptions can be edited (prevents changes to completed/cancelled)
5. **SQL Injection Prevention**: All inputs are escaped or type-cast

## Backwards Compatibility

### Old Subscriptions (Before This Feature)
Subscriptions created before these features won't have billing address or shipping data stored.

**Handling:**
- Display shows: "No billing address stored (subscription created before address storage feature)"
- Edit form shows empty fields, admin can populate manually if needed
- Shipping section shows: "No shipping information (free shipping or created before feature)"

**Migration:**
- No data migration needed
- Old subscriptions continue to work with existing fallback logic
- New subscriptions automatically get full data

## Benefits

### For Administrators

✅ **Full Control**: Can correct any billing address errors
✅ **Customer Service**: Update address when customer moves
✅ **Visibility**: See shipping costs for each subscription
✅ **Transparency**: View complete subscription details in one place

### For Customers (Future)

With this foundation, customer-facing address editing can be added:
- Customers edit their own subscription addresses
- Same backend code handles updates
- Just need to add customer-facing UI

### For System

✅ **Independence**: Subscriptions don't rely on customer address changes
✅ **Rate Locking**: Shipping cost frozen at subscription creation
✅ **Predictability**: Recurring orders have consistent shipping charges
✅ **Auditability**: Address changes logged in comments

## Testing

### Test Address Editing

1. Go to Admin → Saved Card Subscriptions
2. Find an active (scheduled) subscription
3. Click "Details" button
4. Verify billing address and shipping info displayed
5. Click "(Edit)" next to Billing Address
6. Update one or more fields
7. Click "Save Address"
8. Confirm in dialog
9. Verify success message
10. Click "Details" again to verify changes saved

### Test with No Address Data

1. Find subscription created before feature (or manually NULL out fields)
2. Click "Details"
3. Should see "No billing address stored..." message
4. Click "(Edit)"
5. Form appears with empty fields
6. Admin can populate manually

### Test Shipping Display

1. Find subscription with shipping_method populated
2. Click "Details"
3. Verify shipping method and cost displayed
4. Verify note about rate locking

## Future Enhancements

### Customer-Facing Address Editing

Add to customer account page:
```php
// In includes/modules/pages/account_paypal_subscriptions/
- List customer's subscriptions
- "Edit Address" button per subscription
- Same edit form as admin
- Same backend update_payment_info() call
```

### Shipping Address Storage

If needed, add similar fields for shipping address:
```sql
shipping_name VARCHAR(255)
shipping_street_address VARCHAR(255)
shipping_city VARCHAR(255)
...
```

### Address Validation

Integrate with address validation API:
- Validate before saving
- Suggest corrections
- Ensure deliverability

## Summary

This feature completes the subscription independence architecture by:

1. ✅ Storing complete billing address with subscription
2. ✅ Storing shipping information with subscription
3. ✅ Allowing admin to edit addresses
4. ✅ Providing visibility into all subscription data
5. ✅ Preparing foundation for customer-facing editing

Subscriptions are now truly independent, manageable entities with their own complete data sets.
