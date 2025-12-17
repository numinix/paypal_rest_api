# PayPal Payment Modules - Upgrade Feature

## Overview

As of version 1.3.3, all PayPal payment modules now include an automatic upgrade button feature that appears in the Zen Cart admin panel when a newer version is available.

## Supported Modules

- **PayPal Advanced Checkout** (`paypalr`)
- **PayPal Apple Pay** (`paypalr_applepay`)
- **PayPal Google Pay** (`paypalr_googlepay`)
- **PayPal Venmo** (`paypalr_venmo`)

## How It Works

### Version Tracking

Each module now tracks its own version in the database:
- `MODULE_PAYMENT_PAYPALR_VERSION` - PayPal Advanced Checkout
- `MODULE_PAYMENT_PAYPALR_APPLEPAY_VERSION` - PayPal Apple Pay
- `MODULE_PAYMENT_PAYPALR_GOOGLEPAY_VERSION` - PayPal Google Pay
- `MODULE_PAYMENT_PAYPALR_VENMO_VERSION` - PayPal Venmo

### Upgrade Button Display

When you access **Modules → Payment** in the admin panel:

1. The system compares the installed version (from database) with the latest version (in code)
2. If the installed version is lower, an **"Upgrade to X.X.X"** button appears below the module description
3. The button is only shown when an upgrade is actually available

### Upgrade Process

When you click the upgrade button:

1. You're redirected to `admin/paypalr_upgrade.php`
2. The handler loads the payment module
3. It calls the module's `tableCheckup()` method, which applies all incremental updates
4. Database configuration values are updated
5. You're redirected back to the modules page with a success message

### Incremental Updates

The upgrade process automatically applies all version-specific changes defined in the module's `tableCheckup()` method. For example, upgrading from 1.3.2 to 1.3.3 will:

1. Check if version < 1.3.3
2. Apply any 1.3.3-specific configuration changes
3. Update the version number in the database

This ensures that all intermediate updates are applied, even if you skip versions.

## For Developers

### Adding Version-Specific Updates

To add new configuration or database changes for a future version:

1. Update `CURRENT_VERSION` constant in the module file
2. Add a new case in the `tableCheckup()` method's switch statement
3. Include database queries or configuration updates in the case block

Example:
```php
case version_compare(MODULE_PAYMENT_PAYPALR_VERSION, '1.3.4', '<'):
    $db->Execute(
        "INSERT IGNORE INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, ...)
         VALUES
            ('New Setting', 'MODULE_PAYMENT_PAYPALR_NEW_SETTING', ...)"
    );
    // Fall through to next version
```

### Module Structure

Each module must:
- Define `CURRENT_VERSION` constant
- Have a `MODULE_PAYMENT_*_VERSION` config key in database
- Implement `tableCheckup()` method with version-specific upgrade logic
- Call `tableCheckup()` during install and when IS_ADMIN_FLAG is true

## Benefits

1. **No Manual Database Changes** - All updates are applied automatically
2. **Version Safety** - Upgrade logic ensures all intermediate updates are applied
3. **User-Friendly** - One-click upgrade process
4. **Consistent** - Same upgrade mechanism across all modules
5. **Forward Compatible** - Structure supports future version updates

## Version History

### 1.3.6 (Current)
- Removed the unused Google Pay merchant ID configuration key; PayPal REST Google Pay flows no longer require this value.

### 1.3.4
- Updated PayPal Vault configuration description via upgrade action
- Removed legacy ACCEPT_CARDS configuration (credit card module is now separate)

### 1.3.3
- Added upgrade button functionality
- Implemented version tracking for wallet modules
- Enhanced `tableCheckup()` for incremental upgrades

### 1.3.2
- PayPal Vault support
- Error handling improvements

### Earlier Versions
- See git history for detailed changelog
