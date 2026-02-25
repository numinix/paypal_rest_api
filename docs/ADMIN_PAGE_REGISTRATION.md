# Admin Page Registration for PayPal Subscription Pages

## Issue
The PayPal subscription admin pages (`paypalac_subscriptions.php`, `paypalac_saved_card_recurring.php`, `paypalac_subscriptions_report.php`) existed but were not registered in the Zen Cart admin menu system, making them inaccessible to merchants.

## Solution
Created a single Zen Cart installer file to register all subscription-related admin pages in the admin menu.

## Files Created

### Unified Installer (Auto-execute on first page access)

**`admin/includes/installers/paypal_advanced_checkout/1_0_0.php`**

This single installer registers all three subscription pages:

1. **Vaulted Subscriptions**
   - Menu location: Customers > Vaulted Subscriptions
   - Page key: `paypalacSubscriptions`
   - Sort order: 10

2. **Saved Card Subscriptions**
   - Menu location: Customers > Saved Card Subscriptions
   - Page key: `paypalacSavedCardRecurring`
   - Sort order: 11

3. **Active Subscriptions Report**
   - Menu location: Reports > Active Subscriptions Report
   - Page key: `paypalacSubscriptionsReport`
   - Sort order: 100

### Language Definitions

Language definitions are split between two admin-wide loading directories as per Zen Cart conventions:

**`admin/includes/extra_datafiles/paypalac_filenames.php`** - Filename constants
```php
define('FILENAME_PAYPALAC_SUBSCRIPTIONS', 'paypalac_subscriptions');
define('FILENAME_PAYPALAC_SAVED_CARD_RECURRING', 'paypalac_saved_card_recurring');
define('FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT', 'paypalac_subscriptions_report');
```

**`admin/includes/languages/english/extra_definitions/paypalac_admin_names.php`** - Menu label constants
```php
define('BOX_PAYPALAC_SUBSCRIPTIONS', 'Vaulted Subscriptions');
define('BOX_PAYPALAC_SAVED_CARD_RECURRING', 'Saved Card Subscriptions');
define('BOX_PAYPALAC_SUBSCRIPTIONS_REPORT', 'Active Subscriptions Report');
```

These files are automatically loaded admin-wide by Zen Cart, ensuring the constants are available when the installer runs.

#### Why Split the Files?

Following Zen Cart conventions:
- **`extra_datafiles`**: Contains technical constants like filenames (`FILENAME_*`) and database table names. These are non-translatable system identifiers.
- **`extra_definitions`**: Contains user-facing language strings like menu labels (`BOX_*`). These can be translated for multi-language support.

This separation ensures that the installer has access to all required constants before any specific admin page is loaded, fixing the issue where constants were previously only available on the page itself.

## How It Works

### Zen Cart Installer System
Zen Cart 1.5.0+ uses an automatic installer system:

1. **Location**: Files placed in `admin/includes/installers/{installer_name}/version.php`
2. **Execution**: Runs automatically when any admin page is first accessed
3. **Version tracking**: Prevents duplicate execution via version number
4. **Registration**: Uses `zen_register_admin_page()` to add pages to admin menu

### Installation Process

When a merchant first navigates to any of the subscription pages:

1. Zen Cart detects the installer file exists
2. Checks if pages are already registered using `zen_page_key_exists()`
3. If not registered, calls `zen_register_admin_page()` for each page with:
   - Page key (unique identifier)
   - Language constant (menu label)
   - Filename constant
   - Query parameters (if any)
   - Parent menu key (`'customers'` or `'reports'`)
   - Display on menu (`'Y'`)
   - Sort order (10, 11, 100)
4. All pages are added to the admin menu and persisted in database

### Result

Merchants will now see these menu items:

**Under Customers:**
```
Admin
  └─ Customers
      ├─ ... (existing items)
      ├─ Vaulted Subscriptions
      └─ Saved Card Subscriptions
```

**Under Reports:**
```
Admin
  └─ Reports
      ├─ ... (existing items)
      └─ Active Subscriptions Report
```
   - Page key (unique identifier)
   - Language constant (menu label)
   - Filename constant
   - Query parameters (if any)
   - Parent menu key (`'customers'`)
   - Display on menu (`'Y'`)
   - Sort order (10, 11, 12)
4. Page is added to the admin menu and persisted in database

### Result

Merchants will now see these menu items under **Customers**:

```
Admin
  └─ Customers
      ├─ ... (existing items)
      ├─ Vaulted Subscriptions
      ├─ Saved Card Subscriptions
      └─ Active Subscriptions Report
```

## Benefits

1. **Discoverability**: Pages are now visible in the admin menu
2. **Standard integration**: Uses Zen Cart's official registration system
3. **Automatic setup**: No manual database changes required
4. **Version safe**: Won't re-register on subsequent visits
5. **Multi-language ready**: Uses language constants for labels
6. **Single installer**: All pages registered from one unified installer

## Testing

To test the registration:

1. Clear any existing registrations (if testing in development):
   ```sql
   DELETE FROM admin_pages WHERE page_key LIKE 'paypalac%';
   ```

2. Access any of the subscription pages:
   - `admin/paypalac_subscriptions.php`
   - `admin/paypalac_saved_card_recurring.php`
   - `admin/paypalac_subscriptions_report.php`

3. Verify the pages appear in the correct menus:
   - Vaulted Subscriptions under Customers
   - Saved Card Subscriptions under Customers
   - Active Subscriptions Report under Reports

4. Check database:
   ```sql
   SELECT page_key, menu_key, display_on_menu, sort_order 
   FROM admin_pages 
   WHERE page_key LIKE 'paypalac%';
   ```

## Compatibility

- **Zen Cart**: 1.5.0 and later (uses `zen_register_admin_page()`)
- **PHP**: 7.4 through 8.4
- **Backward compatible**: Does not affect existing installations
- **Safe**: Checks prevent duplicate registrations

## Future Enhancements

If additional subscription pages are added in the future:

1. Edit the installer: `admin/includes/installers/paypal_advanced_checkout/1_0_0.php`
2. Add new registration block with unique page key
3. Add language constant to: `admin/includes/languages/english/paypalac_subscriptions.php`
4. Use appropriate menu_key ('customers', 'reports', etc.) and sort order

---

**Latest Commit**: Combined all installers into single paypal_advanced_checkout directory  
**Issue**: #3791923171 (Reports menu placement)  
**Documentation**: This file
