# Subscription Billing Address Storage - Architecture Change

## Problem with Previous Approach

### What Was Wrong
The code was using **inference and lookups** to get billing addresses:
1. Looked up customer's default address from database
2. Inferred country codes from state/province (BC→CA, etc.)
3. Used vault card's billing address
4. Made subscriptions **dependent** on external data

### Why This Was Bad
- **Fragile**: If customer changes address, subscription breaks
- **Incomplete**: Lookups might fail or return wrong data
- **Not Editable**: Users couldn't update subscription addresses
- **Dependent**: Subscriptions tied to orders/customers

## Proper Solution

### Architecture Change
Subscriptions now store their **own** billing address when created.

### New Database Schema

Added to `saved_credit_cards_recurring` table:
```sql
billing_name VARCHAR(255)
billing_company VARCHAR(255)
billing_street_address VARCHAR(255)
billing_suburb VARCHAR(255)
billing_city VARCHAR(255)
billing_state VARCHAR(255)
billing_postcode VARCHAR(255)
billing_country_id INT(11)
billing_country_code CHAR(2)  -- ISO code: CA, US, etc.
```

### Data Flow

#### 1. Order Placement
```
Customer places order with billing address
  ↓
Order saved to `orders` table with billing_* fields
  ↓
Observer detects recurring product
  ↓
Extract billing address from order
  ↓
Create subscription with billing address
  ↓
Subscription stored in `saved_credit_cards_recurring`
```

#### 2. Recurring Payment
```
Cron runs to process subscription
  ↓
Load subscription from `saved_credit_cards_recurring`
  ↓
Use subscription's OWN billing address fields
  ↓
Build PayPal API request with billing_address
  ↓
Process payment ✓
```

#### 3. User Edits Subscription (Future)
```
User views subscriptions in account
  ↓
Clicks "Edit Address" on subscription
  ↓
Updates billing address form
  ↓
Save to subscription record
  ↓
Future payments use updated address
```

## Implementation Details

### 1. Subscription Creation (auto.paypalrestful_recurring.php)

**What Changed:**
```php
// NEW: Query order for billing address
$orderInfo = $db->Execute(
    "SELECT customers_id, currency, currency_value,
            billing_name, billing_company,
            billing_street_address, billing_suburb, billing_city,
            billing_state, billing_postcode, billing_country
       FROM " . TABLE_ORDERS . "
      WHERE orders_id = " . $ordersId
);

// NEW: Extract billing address from order
$billingAddress = array(
    'billing_name' => $orderInfo->fields['billing_name'],
    'billing_street_address' => $orderInfo->fields['billing_street_address'],
    'billing_city' => $orderInfo->fields['billing_city'],
    'billing_state' => $orderInfo->fields['billing_state'],
    'billing_postcode' => $orderInfo->fields['billing_postcode'],
    // ... etc
);

// NEW: Get country ID and ISO code
$countryQuery = $db->Execute(
    "SELECT countries_id, countries_iso_code_2
       FROM " . TABLE_COUNTRIES . "
      WHERE countries_name = '" . $billingAddress['billing_country'] . "'"
);
$billingAddress['billing_country_id'] = $countryQuery->fields['countries_id'];
$billingAddress['billing_country_code'] = $countryQuery->fields['countries_iso_code_2'];

// NEW: Pass billing address to schedule_payment
$subscriptionId = $savedCardRecurring->schedule_payment(
    $amount,
    $nextBillingDate,
    $savedCreditCardId,
    $ordersProductsId,
    'Subscription created from order',
    array_merge($metadata, $billingAddress)  // ← Include billing address
);
```

### 2. Metadata Normalization (paypalSavedCardRecurring.php)

**normalize_schedule_payment_metadata:**
```php
$normalized = array(
    // Existing fields
    'products_id' => null,
    'currency_code' => null,
    // ... etc
    
    // NEW: Billing address fields
    'billing_name' => null,
    'billing_company' => null,
    'billing_street_address' => null,
    'billing_suburb' => null,
    'billing_city' => null,
    'billing_state' => null,
    'billing_postcode' => null,
    'billing_country_id' => null,
    'billing_country_code' => null,
);

$map = array(
    // Existing mappings
    'products_id' => array('products_id', 'product_id'),
    // ... etc
    
    // NEW: Billing address mappings
    'billing_name' => array('billing_name'),
    'billing_street_address' => array('billing_street_address'),
    // ... etc
);
```

### 3. Subscription Storage (paypalSavedCardRecurring.php)

**schedule_payment:**
```php
$sql_data_array = array(
    // Existing fields
    array('fieldName' => 'products_id', 'value' => $metadata['products_id'], ...),
    // ... etc
);

// NEW: Add billing address fields to database insert
$billingAddressFields = array('billing_name', 'billing_company', ...);
foreach ($billingAddressFields as $field) {
    if (isset($metadata[$field]) && $metadata[$field] !== null) {
        $sql_data_array[] = array(
            'fieldName' => $field,
            'value' => $metadata[$field],
            'type' => ($field === 'billing_country_id') ? 'integer' : 'string'
        );
    }
}

$db->perform(TABLE_SAVED_CREDIT_CARDS_RECURRING, $sql_data_array);
```

### 4. Payment Processing (paypalSavedCardRecurring.php)

**build_billing_address_from_card (BEFORE):**
```php
// OLD: Complex lookup and inference logic
if (isset($vaultCard['billing_address'])) {
    $billing = $vaultCard['billing_address'];
    if (!isset($billing['country_code'])) {
        // Lookup customer address
        $countryCode = $this->getCustomerCountryCode(...);
        if ($countryCode === '') {
            // Infer from state/province
            if ($stateCode === 'BC') {
                $billing['country_code'] = 'CA';
            }
        }
    }
}
```

**build_billing_address_from_card (AFTER):**
```php
// NEW: Simply use stored subscription address
if (isset($cardDetails['billing_country_code'])) {
    // Use subscription's stored billing address
    $billing = array(
        'address_line_1' => $cardDetails['billing_street_address'],
        'postal_code' => $cardDetails['billing_postcode'],
        'country_code' => $cardDetails['billing_country_code'],
        'admin_area_2' => $cardDetails['billing_city'],
        'admin_area_1' => $cardDetails['billing_state'],
    );
    return $billing;
}

// Fallback: vault card (backwards compatibility)
if (isset($vaultCard['billing_address'])) {
    return $vaultCard['billing_address'];
}
```

## Benefits

### ✅ Independence
Subscriptions have their own complete address data, independent of:
- Customer's current address
- Original order
- Vault card data

### ✅ Predictability
No inference, no lookups, no fallbacks (for new subscriptions):
- Billing address is stored when subscription created
- Same address is used for all recurring payments
- No surprises, no failures from missing data

### ✅ Editability (Future)
Users will be able to edit subscription addresses:
- Update billing address in account management
- Changes apply to future payments
- Subscription remains valid

### ✅ Clean Code
Removed complex logic:
- No `getCustomerCountryCode()` helper needed
- No state/province inference (BC→CA)
- No database lookups during payment processing
- Straightforward data flow

## Migration Path

### Existing Subscriptions
Old subscriptions created before this change won't have billing address stored.

**Fallback Behavior:**
1. Check for stored billing_country_code
2. If not found, use vault card billing_address (old behavior)
3. Logs warning: "subscription should have its own stored address"

**User Action:**
User should create a new subscription (after deploying this code) to get proper address storage.

### New Subscriptions
All new subscriptions created after this change:
- ✅ Have complete billing address stored
- ✅ Use stored address for payments
- ✅ Will be editable (once UI is added)

## Database Upgrade

### Automatic Module Upgrade (v1.3.9)

The database changes are integrated into the PayPal REST API module's upgrade mechanism:

**File:** `includes/modules/payment/paypalr.php`
- Module version bumped from 1.3.8 to **1.3.9**
- Upgrade code in `tableCheckup()` method
- Executes automatically when admin accesses any page after code deployment

**Upgrade Process:**
1. Module detects `MODULE_PAYMENT_PAYPALR_VERSION` < `1.3.9`
2. Checks if columns already exist (idempotent)
3. Adds billing address and shipping columns
4. Updates module version to 1.3.9

**Safety Features:**
- Only runs if version upgrade is needed
- Checks for existing columns before altering table
- Follows same pattern as all previous version upgrades (1.3.1 - 1.3.8)

### Manual Upgrade (Alternative)

If you prefer to run the upgrade manually:
```bash
mysql your_database < docs/upgrade_add_subscription_billing_addresses.sql
```

### Verification
```sql
SHOW COLUMNS FROM saved_credit_cards_recurring 
WHERE Field LIKE 'billing_%' OR Field LIKE 'shipping_%';
```

Should show 9 billing_* columns and 2 shipping_* columns.

## Testing

### Test New Subscription Creation
1. Deploy code changes
2. Run database upgrade
3. Place order with recurring product
4. Check `saved_credit_cards_recurring` table
5. Verify billing_* fields are populated
6. Wait for next billing cycle OR trigger cron manually
7. Verify payment processes successfully
8. Check logs for "Using stored subscription billing_address"

### Expected Log Output
```
PayPal: Using stored subscription billing_address: {
  "address_line_1": "1244 Dewar Way",
  "postal_code": "V3C 5Z1",
  "country_code": "CA",
  "admin_area_2": "Port Coquitlam",
  "admin_area_1": "BC"
}
```

## Future Enhancements

### Subscription Address Editing UI
- Add "Edit Address" button to account_paypal_subscriptions page
- Create form with billing address fields
- Validate and save to subscription record
- Allow users to manage their subscriptions independently

### Shipping Address Support
If needed, add similar fields for shipping:
- `shipping_name`, `shipping_street_address`, etc.
- Use same pattern: store on creation, use for fulfillment

## Files Changed

1. `docs/upgrade_add_subscription_billing_addresses.sql` - SQL upgrade script
2. `admin/includes/init_includes/init_subscription_billing_address_upgrade.php` - PHP init script
3. `includes/classes/observers/auto.paypalrestful_recurring.php` - Extract & pass billing address
4. `includes/classes/paypalSavedCardRecurring.php` - Store & use billing address

## Summary

**Before:** Subscriptions relied on lookups, inference, and external data
**After:** Subscriptions store and use their own complete billing address

This is the proper architectural approach for independent subscription management.
