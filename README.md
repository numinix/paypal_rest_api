# PayPal Advanced Checkout Payment Module

Encapsulated Zen Cart plugin (`zc_plugins/PayPalAdvancedCheckout`) providing PayPal REST Advanced Checkout (`paypalac`) and companion wallet/card modules.

> **Note:** This fork is branded as **PayPal Advanced Checkout** to distinguish it from Zen Cart's core PayPal Advanced Checkout module and prevent versioning conflicts.

## Requirements

- **Zen Cart:** 2.0.1 or later (storefront encapsulation is native in 2.1.0+; on 2.0.1 follow Zen Cart’s encapsulated-plugin guidance)
- **PHP:** 7.4 through 8.4
- **Extensions:** cURL, JSON, OpenSSL

## Installation (Plugin Manager)

1. Upload the `zc_plugins/PayPalAdvancedCheckout` folder into your store’s `zc_plugins/` directory (keep the `v2.0.0` version folder intact).
2. In Admin → **Modules → Plugin Manager**, install **PayPal Advanced Checkout**.
3. Installing removes any prior **non-encapsulated overlay** copies of this plugin (if present) and redeploys catalog-root entrypoints (`ppac_listener.php`, `ppac_webhook.php`, `ppac_wallet.php`, `ppac_add_card.php`, ajax/cron shims).
4. Existing **Modules → Payment** configuration (`MODULE_PAYMENT_PAYPALAC_*` and companion keys) is **preserved** — the Plugin Manager install does not wipe merchant credentials or settings.
5. Confirm payment modules under Admin → **Modules → Payment**, then configure credentials as needed.

### Migrating from the classic overlay

If the store already had the unzipped overlay (for example numinix.com):

- Plugin Manager install deletes the old overlay files when found.
- Database configuration and schema from the overlay continue to work.
- After install, commit the removed overlay files on the site’s git tree so deployments do not restore them.

### Uninstall

Plugin Manager uninstall is destructive: it removes payment-module configuration keys and catalog-root entrypoints. Prefer disabling modules in Modules → Payment if you only need to turn processing off.

## Cron

Prefer the stable dispatcher (survives version upgrades):

```bash
php zc_plugins/PayPalAdvancedCheckout/cron.php paypalac_saved_card_recurring
```

Legacy `cron/paypalac_*.php` shims under the catalog root are redeployed on install/upgrade for existing crontab entries.

## Documentation

📖 **[PayPal Advanced Checkout Documentation](zc_plugins/PayPalAdvancedCheckout/v2.0.0/docs/PayPal%20Advanced%20Checkout/readme.html)**

- **Project Wiki:** https://github.com/numinix/paypal_rest_api/wiki

## Support

Need assistance? Contact the Numinix support team at [support@numinix.com](mailto:support@numinix.com) with your Zen Cart version, PHP version, and details about your issue.
