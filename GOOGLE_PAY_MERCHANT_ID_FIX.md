# Google Pay 1.3.7 Merchant ID Configuration Fix

## Issue Summary

**Problem:** After upgrading to Google Pay module version 1.3.7, the new "Google Pay Merchant ID (optional)" configuration option was not appearing in the admin interface.

**Impact:** Users who upgraded to version 1.3.7 could not configure the optional Google Merchant ID, even though the feature was documented as part of the 1.3.7 release.

## Root Cause

The `tableCheckup()` method in `includes/modules/payment/paypalr_googlepay.php` contained an early return optimization:

```php
if (defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION') && 
    MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION === $current_version) {
    return;  // Exit early if already at current version
}
```

This optimization assumed that if the module version was already at 1.3.7, all required configurations must exist. However, this assumption failed in scenarios where:

1. The version number was updated to 1.3.7 but the SQL migration didn't complete
2. The configuration was manually deleted
3. The upgrade process was interrupted

## Solution

### 1. Added Configuration Existence Check

Modified the early return to verify that required configurations actually exist:

```php
if ($version_is_current && $this->merchantIdConfigExists()) {
    // Version is current AND all required configs exist
    return;
}
```

### 2. Created Helper Method

Extracted the database query into a reusable helper method to eliminate code duplication:

```php
protected function merchantIdConfigExists(): bool
{
    global $db;
    
    $check_query = $db->Execute(
        "SELECT configuration_key FROM " . TABLE_CONFIGURATION . "
         WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID'"
    );
    
    return !$check_query->EOF;
}
```

### 3. Added Fallback Logic

Added logic in the switch statement's default case to handle the scenario where the version is already 1.3.7 but the configuration is missing:

```php
default:
    // Version is >= 1.3.7, check if MERCHANT_ID config is missing and add it
    if ($version_is_current && !$this->merchantIdConfigExists()) {
        $this->applyVersionSqlFile('1.3.7_add_googlepay_merchant_id.sql');
    }
    break;
```

## Upgrade Scenarios

The fix correctly handles all three upgrade scenarios:

### Scenario 1: Normal Upgrade (1.3.6 → 1.3.7)
- **Initial State:** VERSION = 1.3.6, config doesn't exist
- **Process:** Version comparison triggers, SQL file applied, version updated
- **Result:** ✅ Configuration added successfully

### Scenario 2: Missing Config When Already at 1.3.7 (The Bug)
- **Initial State:** VERSION = 1.3.7, config doesn't exist
- **Process:** Early return prevented by `merchantIdConfigExists()`, default case applies SQL
- **Result:** ✅ Configuration added successfully

### Scenario 3: Normal State
- **Initial State:** VERSION = 1.3.7, config exists
- **Process:** Early return triggered by `merchantIdConfigExists()`
- **Result:** ✅ No unnecessary work performed

## Files Modified

- `includes/modules/payment/paypalr_googlepay.php` - Core fix with helper method
- `tests/GooglePayMerchantIdUpgradeTest.php` - Unit test
- `tests/GooglePayMerchantIdUpgradeIntegrationTest.php` - Integration test

## Testing

All tests pass successfully:

```bash
# Run all merchant ID validation tests
php tests/WalletMerchantIdValidationTest.php

# Run upgrade-specific tests
php tests/GooglePayMerchantIdUpgradeTest.php
php tests/GooglePayMerchantIdUpgradeIntegrationTest.php

# Run related Google Pay tests
php tests/NativeGooglePayImplementationTest.php
```

## For Users

If you upgraded to version 1.3.7 and don't see the "Google Pay Merchant ID (optional)" field:

1. **Via Admin Interface:** Click the "Upgrade" button in the PayPal Google Pay module settings
2. **Via Database:** The fix will automatically apply the next time `tableCheckup()` runs

The configuration will be added automatically. No manual intervention is required.

## Configuration Details

**Field Name:** Google Pay Merchant ID (optional)

**Configuration Key:** `MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID`

**Description:** Optional Google Merchant ID used for the PayPal SDK google-pay-merchant-id parameter. Must be 5-20 alphanumeric characters. Leave blank unless instructed by PayPal.

**Default Value:** Empty (blank)

**Validation:** 5-20 alphanumeric characters (when provided)

## References

- Version 1.3.7 Release Notes: `docs/developers/UPGRADE_FEATURE.md`
- SQL Migration File: `docs/developers/versions/1.3.7_add_googlepay_merchant_id.sql`
- Google Pay Setup Documentation: `GOOGLE_PAY_SETUP.md`

## Version History

- **v1.3.7:** Added optional Google Pay Merchant ID configuration
- **v1.3.6:** Removed unused Google Pay Merchant ID (before it was re-introduced as optional)
- **Fix Applied:** December 2025
