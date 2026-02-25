# PayPal Advanced Checkout — Conversion Plan

## Overview

Rename the plugin from `paypalr` (PayPal RESTful) to `paypalac` (PayPal Advanced Checkout) to fully
differentiate from the original Zen Cart PayPal REST API module (https://github.com/lat9/paypalr).
After conversion, **no** reference to `paypalr` or "PayPal REST API" should remain in any file name,
constant, class name, CSS/JS identifier, or documentation.

### Naming Map (Quick Reference)

| Old Pattern | New Pattern |
|---|---|
| `paypalr` (lowercase) | `paypalac` |
| `PAYPALR` (uppercase) | `PAYPALAC` |
| `paypalRestful` / `paypalrestful` | `paypalAdvCheckout` / `paypaladvcheckout` |
| `PayPalRestful` (namespace / directory) | `PayPalAdvancedCheckout` |
| `ppr_` (file prefix — "PayPal Rest") | `ppac_` |
| `pprAutoload` | `ppacAutoload` |
| "PayPal REST API" / "RESTful" (docs) | "PayPal Advanced Checkout" |
| `jquery.paypalr.*` (JS plugins) | `jquery.paypalac.*` |
| `window.paypalr*` (JS globals) | `window.paypalac*` |
| `paypalr-*` (CSS classes / IDs) | `paypalac-*` |
| Admin page keys `paypalr*` | `paypalac*` |
| `lat9/paypalr` wiki links | project-owned documentation links |

---

## Phase 1 — Core Payment Module Files & Class Names
> Rename the main payment module files, class names, and module codes.

### 1.1 File Renames

- [ ] `includes/modules/payment/paypalr.php` → `paypalac.php`
- [ ] `includes/modules/payment/paypalr_paylater.php` → `paypalac_paylater.php`
- [ ] `includes/modules/payment/paypalr_creditcard.php` → `paypalac_creditcard.php`
- [ ] `includes/modules/payment/paypalr_googlepay.php` → `paypalac_googlepay.php`
- [ ] `includes/modules/payment/paypalr_applepay.php` → `paypalac_applepay.php`
- [ ] `includes/modules/payment/paypalr_venmo.php` → `paypalac_venmo.php`
- [ ] `includes/modules/payment/paypalr_savedcard.php` → `paypalac_savedcard.php`

### 1.2 Class Names (inside the files above)

| Old Class | New Class |
|---|---|
| `class paypalr` | `class paypalac` |
| `class paypalr_paylater` | `class paypalac_paylater` |
| `class paypalr_creditcard` | `class paypalac_creditcard` |
| `class paypalr_googlepay` | `class paypalac_googlepay` |
| `class paypalr_applepay` | `class paypalac_applepay` |
| `class paypalr_venmo` | `class paypalac_venmo` |
| `class paypalr_savedcard` | `class paypalac_savedcard` |

### 1.3 Module Code Strings

Replace every `$this->code = 'paypalr...'` assignment:

- [ ] `'paypalr'` → `'paypalac'`
- [ ] `'paypalr_paylater'` → `'paypalac_paylater'`
- [ ] `'paypalr_creditcard'` → `'paypalac_creditcard'`
- [ ] `'paypalr_googlepay'` → `'paypalac_googlepay'`
- [ ] `'paypalr_applepay'` → `'paypalac_applepay'`
- [ ] `'paypalr_venmo'` → `'paypalac_venmo'`
- [ ] `'paypalr_savedcard'` → `'paypalac_savedcard'`

### 1.4 Internal References Within Core Files

Inside `paypalac.php` (formerly `paypalr.php`) and all variant files:

- [ ] All `MODULE_PAYMENT_PAYPALR_*` constant references → `MODULE_PAYMENT_PAYPALAC_*`
- [ ] All `paypalr` string literals used for payment identification → `paypalac`
- [ ] `CURRENT_VERSION` constant comment and version config key
- [ ] `tableCheckup()` method: admin page key registrations (see Phase 5)
- [ ] All `instanceof paypalr` checks → `instanceof paypalac`
- [ ] All `new paypalr(` instantiations → `new paypalac(`

---

## Phase 2 — Configuration Constants (Database & Language)
> Rename all MODULE_PAYMENT_PAYPALR_* constants that are stored in the database and defined in language files.

### 2.1 MODULE_PAYMENT_PAYPALR_* → MODULE_PAYMENT_PAYPALAC_*

The following constants appear in language files, payment module files, admin pages, observers, and tests. **Every** occurrence must change.

**Core Settings:**
- [ ] `MODULE_PAYMENT_PAYPALR_STATUS` → `MODULE_PAYMENT_PAYPALAC_STATUS`
- [ ] `MODULE_PAYMENT_PAYPALR_SORT_ORDER` → `MODULE_PAYMENT_PAYPALAC_SORT_ORDER`
- [ ] `MODULE_PAYMENT_PAYPALR_ZONE` → `MODULE_PAYMENT_PAYPALAC_ZONE`
- [ ] `MODULE_PAYMENT_PAYPALR_VERSION` → `MODULE_PAYMENT_PAYPALAC_VERSION`
- [ ] `MODULE_PAYMENT_PAYPALR_SERVER` → `MODULE_PAYMENT_PAYPALAC_SERVER`

**Credentials:**
- [ ] `MODULE_PAYMENT_PAYPALR_CLIENTID_L` → `MODULE_PAYMENT_PAYPALAC_CLIENTID_L`
- [ ] `MODULE_PAYMENT_PAYPALR_SECRET_L` → `MODULE_PAYMENT_PAYPALAC_SECRET_L`
- [ ] `MODULE_PAYMENT_PAYPALR_CLIENTID_S` → `MODULE_PAYMENT_PAYPALAC_CLIENTID_S`
- [ ] `MODULE_PAYMENT_PAYPALR_SECRET_S` → `MODULE_PAYMENT_PAYPALAC_SECRET_S`

**Transaction & Order Settings:**
- [ ] `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` → `MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE`
- [ ] `MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID` → `MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID`
- [ ] `MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID` → `MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID`
- [ ] `MODULE_PAYMENT_PAYPALR_CURRENCY` → `MODULE_PAYMENT_PAYPALAC_CURRENCY`
- [ ] `MODULE_PAYMENT_PAYPALR_DEBUGGING` → `MODULE_PAYMENT_PAYPALAC_DEBUGGING`

**Features:**
- [ ] `MODULE_PAYMENT_PAYPALR_ACCEPT_CARDS` → `MODULE_PAYMENT_PAYPALAC_ACCEPT_CARDS`
- [ ] `MODULE_PAYMENT_PAYPALR_SCA_ALWAYS` → `MODULE_PAYMENT_PAYPALAC_SCA_ALWAYS`
- [ ] `MODULE_PAYMENT_PAYPALR_SOFT_DESCRIPTOR` → `MODULE_PAYMENT_PAYPALAC_SOFT_DESCRIPTOR`
- [ ] `MODULE_PAYMENT_PAYPALR_PAYLATER_MESSAGING` → `MODULE_PAYMENT_PAYPALAC_PAYLATER_MESSAGING`
- [ ] `MODULE_PAYMENT_PAYPALR_ENABLE_VAULT` → `MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT`
- [ ] `MODULE_PAYMENT_PAYPALR_DISABLE_ON_ERROR` → `MODULE_PAYMENT_PAYPALAC_DISABLE_ON_ERROR`

> **Note:** Any additional `MODULE_PAYMENT_PAYPALR_*` constants discovered during implementation must also be renamed.

### 2.2 Language Definition Files That Define/Use These Constants

- [ ] `includes/languages/english/modules/payment/paypalr.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_shared.php`
- [ ] `includes/languages/english/modules/payment/paypalr_paylater.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_paylater.php`
- [ ] `includes/languages/english/modules/payment/paypalr_creditcard.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_creditcard.php`
- [ ] `includes/languages/english/modules/payment/paypalr_googlepay.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_googlepay.php`
- [ ] `includes/languages/english/modules/payment/paypalr_applepay.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_applepay.php`
- [ ] `includes/languages/english/modules/payment/paypalr_venmo.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_venmo.php`
- [ ] `includes/languages/english/modules/payment/paypalr_savedcard.php`
- [ ] `includes/languages/english/modules/payment/lang.paypalr_savedcard.php`

Each file must be:
1. **Renamed** (filename `paypalr` → `paypalac`)
2. **Contents updated** (all `PAYPALR` constant names → `PAYPALAC`)

### 2.3 Database Migration — Auto-Copy from `paypalr` on Install

Stores that already have the `paypalr` plugin installed must be able to transition to `paypalac`
without reconfiguring. The new module's **`install()`** method handles this automatically.
Users must then **manually** uninstall the old `paypalr` module and remove its files.

#### 2.3.1 Migration Logic in `install()`

Before inserting new configuration rows, `install()` must check whether old `MODULE_PAYMENT_PAYPALR_*`
keys exist in the `configuration` table. If they do, copy each old value into the corresponding
new `MODULE_PAYMENT_PAYPALAC_*` row so the store keeps its existing settings.

```
Pseudocode — inside install(), before the INSERT:

  1. SELECT configuration_key, configuration_value
       FROM configuration
      WHERE configuration_key LIKE 'MODULE_PAYMENT_PAYPALR_%'

  2. Build a key→value map from the result set.

  3. After the normal INSERT of new PAYPALAC rows (with default values),
     for each row returned in step 1:
       old_key  = row.configuration_key                        (e.g. MODULE_PAYMENT_PAYPALR_SERVER)
       new_key  = replace('PAYPALR', 'PAYPALAC', old_key)      (e.g. MODULE_PAYMENT_PAYPALAC_SERVER)
       old_val  = row.configuration_value                       (e.g. 'live')

       UPDATE configuration
          SET configuration_value = old_val
        WHERE configuration_key  = new_key
        LIMIT 1
```

This means:
- Fresh installs (no old keys) → defaults are used as usual.
- Upgrades from `paypalr` → old values are copied to the new keys automatically.
- The old `paypalr` keys are **not deleted** — the user must uninstall the old module
  through the Zen Cart admin and remove old `paypalr` files manually.

#### 2.3.2 Configuration Keys to Migrate

The following database-stored keys have a 1-to-1 old→new mapping. Every value
must be preserved during migration:

| Old Key (`PAYPALR`) | New Key (`PAYPALAC`) |
|---|---|
| `MODULE_PAYMENT_PAYPALR_STATUS` | `MODULE_PAYMENT_PAYPALAC_STATUS` |
| `MODULE_PAYMENT_PAYPALR_VERSION` | `MODULE_PAYMENT_PAYPALAC_VERSION` |
| `MODULE_PAYMENT_PAYPALR_DISABLE_ON_ERROR` | `MODULE_PAYMENT_PAYPALAC_DISABLE_ON_ERROR` |
| `MODULE_PAYMENT_PAYPALR_SERVER` | `MODULE_PAYMENT_PAYPALAC_SERVER` |
| `MODULE_PAYMENT_PAYPALR_CLIENTID_L` | `MODULE_PAYMENT_PAYPALAC_CLIENTID_L` |
| `MODULE_PAYMENT_PAYPALR_SECRET_L` | `MODULE_PAYMENT_PAYPALAC_SECRET_L` |
| `MODULE_PAYMENT_PAYPALR_CLIENTID_S` | `MODULE_PAYMENT_PAYPALAC_CLIENTID_S` |
| `MODULE_PAYMENT_PAYPALR_SECRET_S` | `MODULE_PAYMENT_PAYPALAC_SECRET_S` |
| `MODULE_PAYMENT_PAYPALR_SORT_ORDER` | `MODULE_PAYMENT_PAYPALAC_SORT_ORDER` |
| `MODULE_PAYMENT_PAYPALR_ZONE` | `MODULE_PAYMENT_PAYPALAC_ZONE` |
| `MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID` | `MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID` |
| `MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID` | `MODULE_PAYMENT_PAYPALAC_ORDER_PENDING_STATUS_ID` |
| `MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID` | `MODULE_PAYMENT_PAYPALAC_REFUNDED_STATUS_ID` |
| `MODULE_PAYMENT_PAYPALR_VOIDED_STATUS_ID` | `MODULE_PAYMENT_PAYPALAC_VOIDED_STATUS_ID` |
| `MODULE_PAYMENT_PAYPALR_HELD_STATUS_ID` | `MODULE_PAYMENT_PAYPALAC_HELD_STATUS_ID` |
| `MODULE_PAYMENT_PAYPALR_BRANDNAME` | `MODULE_PAYMENT_PAYPALAC_BRANDNAME` |
| `MODULE_PAYMENT_PAYPALR_SOFT_DESCRIPTOR` | `MODULE_PAYMENT_PAYPALAC_SOFT_DESCRIPTOR` |
| `MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE` | `MODULE_PAYMENT_PAYPALAC_TRANSACTION_MODE` |
| `MODULE_PAYMENT_PAYPALR_CURRENCY` | `MODULE_PAYMENT_PAYPALAC_CURRENCY` |
| `MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK` | `MODULE_PAYMENT_PAYPALAC_CURRENCY_FALLBACK` |
| `MODULE_PAYMENT_PAYPALR_ENABLE_VAULT` | `MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT` |
| `MODULE_PAYMENT_PAYPALR_SCA_ALWAYS` | `MODULE_PAYMENT_PAYPALAC_SCA_ALWAYS` |
| `MODULE_PAYMENT_PAYPALR_PAYLATER_MESSAGING` | `MODULE_PAYMENT_PAYPALAC_PAYLATER_MESSAGING` |
| `MODULE_PAYMENT_PAYPALR_HANDLING_OT` | `MODULE_PAYMENT_PAYPALAC_HANDLING_OT` |
| `MODULE_PAYMENT_PAYPALR_INSURANCE_OT` | `MODULE_PAYMENT_PAYPALAC_INSURANCE_OT` |
| `MODULE_PAYMENT_PAYPALR_DISCOUNT_OT` | `MODULE_PAYMENT_PAYPALAC_DISCOUNT_OT` |
| `MODULE_PAYMENT_PAYPALR_DEBUGGING` | `MODULE_PAYMENT_PAYPALAC_DEBUGGING` |

> **Note:** `MODULE_PAYMENT_PAYPALR_ACCEPT_CARDS` was removed in v1.3.4 and does not need migration.

#### 2.3.3 Admin Page Keys — No Auto-Migration Needed

The old `paypalr` admin page registrations (`paypalrSubscriptions`, `paypalrSavedCardRecurring`,
`paypalrSubscriptionsReport`, `paypalrWebhookLogs`) live in the `admin_pages` table but belong
to the old module. The new `paypalac` module's `tableCheckup()` will register its own pages
(`paypalacSubscriptions`, etc.) independently. When the user uninstalls the old `paypalr` module,
its page registrations are cleaned up by the old module's `remove()` method.

#### 2.3.4 Order History — Backward Compatibility

Existing orders in the `paypal` table reference `paypalr` as the payment module code in the
`module_name` or `module_mode` columns. These records must remain valid.  The admin notification
handler (in the observer/admin class) should recognize **both** `paypalr` and `paypalac` as
valid module codes when displaying PayPal order details, so historical orders continue to
show payment information correctly.

#### 2.3.5 Upgrade Instructions for Store Owners

After installing the new `paypalac` module:

1. Go to **Admin → Modules → Payment → PayPal Advanced Checkout** and click **Install**.
   The installer automatically copies all settings from the old `paypalr` module if present.
2. Verify your configuration (credentials, order statuses, etc.) are correct.
3. Test a transaction in sandbox mode.
4. Go to **Admin → Modules → Payment → PayPal RESTful** (the old module) and click **Remove**.
5. Delete all old `paypalr` files from the server.

---

## Phase 3 — Admin Page Files & Assets
> Rename admin PHP pages, their JavaScript, CSS, and language files.

### 3.1 Admin PHP Pages

- [ ] `admin/paypalr_integrated_signup.php` → `paypalac_integrated_signup.php`
- [ ] `admin/paypalr_signup.php` → `paypalac_signup.php`
- [ ] `admin/paypalr_subscriptions.php` → `paypalac_subscriptions.php`
- [ ] `admin/paypalr_saved_card_recurring.php` → `paypalac_saved_card_recurring.php`
- [ ] `admin/paypalr_subscriptions_report.php` → `paypalac_subscriptions_report.php`
- [ ] `admin/paypalr_webhook_logs.php` → `paypalac_webhook_logs.php`
- [ ] `admin/paypalr_upgrade.php` → `paypalac_upgrade.php`

### 3.2 Admin JavaScript Files

- [ ] `admin/includes/javascript/paypalr_integrated_signup.js` → `paypalac_integrated_signup.js`
- [ ] `admin/includes/javascript/paypalr_integrated_signup_callback.js` → `paypalac_integrated_signup_callback.js`
- [ ] `admin/includes/javascript/paypalr_integrated_signup_complete.js` → `paypalac_integrated_signup_complete.js`
- [ ] `admin/includes/javascript/paypalr_signup.js` → `paypalac_signup.js`
- [ ] `admin/includes/javascript/paypalr_saved_card_recurring.js` → `paypalac_saved_card_recurring.js`
- [ ] `admin/includes/javascript/paypalr_subscriptions.js` → `paypalac_subscriptions.js`

### 3.3 Admin CSS Files

- [ ] `admin/includes/css/paypalr_integrated_signup.css` → `paypalac_integrated_signup.css`
- [ ] `admin/includes/css/paypalr_signup.css` → `paypalac_signup.css`
- [ ] `admin/includes/css/paypalr_subscriptions.css` → `paypalac_subscriptions.css`
- [ ] `admin/includes/css/paypalr_saved_card_recurring.css` → `paypalac_saved_card_recurring.css`
- [ ] `admin/includes/css/paypalr_webhook_logs.css` → `paypalac_webhook_logs.css`

### 3.4 Admin Language Files

- [ ] `admin/includes/languages/english/paypalr_subscriptions.php` → `paypalac_subscriptions.php`
- [ ] `admin/includes/languages/english/paypalr_saved_card_recurring.php` → `paypalac_saved_card_recurring.php`
- [ ] `admin/includes/languages/english/paypalr_webhook_logs.php` → `paypalac_webhook_logs.php`
- [ ] `admin/includes/languages/english/paypalr_subscriptions_report.php` → `paypalac_subscriptions_report.php`
- [ ] `admin/includes/languages/english/extra_definitions/paypalr_admin_names.php` → `paypalac_admin_names.php`

### 3.5 Admin Data Files

- [ ] `admin/includes/extra_datafiles/paypalr_filenames.php` → `paypalac_filenames.php`

### 3.6 FILENAME_* and BOX_* Constants

In `paypalac_filenames.php` (formerly `paypalr_filenames.php`):

| Old Constant | New Constant |
|---|---|
| `FILENAME_PAYPALR_SUBSCRIPTIONS` | `FILENAME_PAYPALAC_SUBSCRIPTIONS` |
| `FILENAME_PAYPALR_SAVED_CARD_RECURRING` | `FILENAME_PAYPALAC_SAVED_CARD_RECURRING` |
| `FILENAME_PAYPALR_SUBSCRIPTIONS_REPORT` | `FILENAME_PAYPALAC_SUBSCRIPTIONS_REPORT` |
| `FILENAME_PAYPALR_WEBHOOK_LOGS` | `FILENAME_PAYPALAC_WEBHOOK_LOGS` |

In `paypalac_admin_names.php` (formerly `paypalr_admin_names.php`):

| Old Constant | New Constant |
|---|---|
| `BOX_PAYPALR_SUBSCRIPTIONS` | `BOX_PAYPALAC_SUBSCRIPTIONS` |
| `BOX_PAYPALR_SAVED_CARD_RECURRING` | `BOX_PAYPALAC_SAVED_CARD_RECURRING` |
| `BOX_PAYPALR_SUBSCRIPTIONS_REPORT` | `BOX_PAYPALAC_SUBSCRIPTIONS_REPORT` |
| `BOX_PAYPALR_WEBHOOK_LOGS` | `BOX_PAYPALAC_WEBHOOK_LOGS` |

### 3.7 Admin Page Registration Keys

In `paypalac.php` `tableCheckup()` method, update all `zen_register_admin_page()` calls:

| Old Page Key | New Page Key |
|---|---|
| `paypalrSubscriptions` | `paypalacSubscriptions` |
| `paypalrSavedCardRecurring` | `paypalacSavedCardRecurring` |
| `paypalrSubscriptionsReport` | `paypalacSubscriptionsReport` |
| `paypalrWebhookLogs` | `paypalacWebhookLogs` |

Also update all `zen_page_key_exists()` checks for these keys.

### 3.8 Admin Observer

- [ ] `admin/includes/classes/observers/auto.PaypalRestAdmin.php` — rename class `zcObserverPaypalRestAdmin` → `zcObserverPaypalacAdmin` and update all `paypalr` references inside

### 3.9 JavaScript Global Variables (Admin)

In admin JS files, rename:
- [ ] `window.paypalrISUConfig` → `window.paypalacISUConfig`
- [ ] `window.paypalrISUCompleteConfig` → `window.paypalacISUCompleteConfig`
- [ ] All other `paypalr`-prefixed JS variables in admin scripts

---

## Phase 4 — Frontend JavaScript & CSS
> Rename jQuery plugin files, CSS files, and update all JS globals and CSS selectors.

### 4.1 jQuery Plugin File Renames (in `includes/modules/payment/paypal/PayPalRestful/`)

- [ ] `jquery.paypalr.applepay.js` → `jquery.paypalac.applepay.js`
- [ ] `jquery.paypalr.applepay.native.js` → `jquery.paypalac.applepay.native.js`
- [ ] `jquery.paypalr.applepay.wallet.js` → `jquery.paypalac.applepay.wallet.js`
- [ ] `jquery.paypalr.googlepay.js` → `jquery.paypalac.googlepay.js`
- [ ] `jquery.paypalr.googlepay.native.js` → `jquery.paypalac.googlepay.native.js`
- [ ] `jquery.paypalr.googlepay.wallet.js` → `jquery.paypalac.googlepay.wallet.js`
- [ ] `jquery.paypalr.venmo.js` → `jquery.paypalac.venmo.js`
- [ ] `jquery.paypalr.checkout.js` → `jquery.paypalac.checkout.js`
- [ ] `jquery.paypalr.paylater.js` → `jquery.paypalac.paylater.js`
- [ ] `jquery.paypalr.disable.js` → `jquery.paypalac.disable.js`
- [ ] `jquery.paypalr.jssdk_messages.js` → `jquery.paypalac.jssdk_messages.js`

### 4.2 CSS File Renames (in `includes/modules/payment/paypal/PayPalRestful/`)

- [ ] `paypalr.css` → `paypalac.css`
- [ ] `paypalr.admin.css` → `paypalac.admin.css`
- [ ] `paypalr_bootstrap.css` → `paypalac_bootstrap.css`

### 4.3 JavaScript Global Variables (Frontend)

Replace **every** `window.paypalr*` variable across all JS and PHP files that emit inline JS:

| Old Variable | New Variable |
|---|---|
| `window.paypalrSdkLoaderState` | `window.paypalacSdkLoaderState` |
| `window.paypalrSdkConfig` | `window.paypalacSdkConfig` |
| `window.paypalrAjaxBasePath` | `window.paypalacAjaxBasePath` |
| `window.paypalrApplePayConfig` | `window.paypalacApplePayConfig` |
| `window.paypalrApplePayRender` | `window.paypalacApplePayRender` |
| `window.paypalrApplePaySetPayload` | `window.paypalacApplePaySetPayload` |
| `window.paypalrApplePaySelectRadio` | `window.paypalacApplePaySelectRadio` |
| `window.paypalrGooglePayConfig` | `window.paypalacGooglePayConfig` |
| `window.paypalrGooglePayRender` | `window.paypalacGooglePayRender` |
| `window.paypalrGooglePaySetPayload` | `window.paypalacGooglePaySetPayload` |
| `window.paypalrGooglePaySelectRadio` | `window.paypalacGooglePaySelectRadio` |
| `window.paypalrVenmoRender` | `window.paypalacVenmoRender` |
| `window.paypalrVenmoSetPayload` | `window.paypalacVenmoSetPayload` |
| `window.paypalrVenmoSelectRadio` | `window.paypalacVenmoSelectRadio` |
| `window.paypalrPaylaterRender` | `window.paypalacPaylaterRender` |
| `window.paypalrPaylaterSetPayload` | `window.paypalacPaylaterSetPayload` |
| `window.paypalrPaylaterSelectRadio` | `window.paypalacPaylaterSelectRadio` |
| `window.paypalrWalletIsLoggedIn` | `window.paypalacWalletIsLoggedIn` |
| `window.paypalrVenmoSessionAppend` | `window.paypalacVenmoSessionAppend` |

> Any additional `window.paypalr*` variables discovered must also be renamed.

### 4.4 CSS Classes & IDs

Replace all `paypalr-*` CSS class names and HTML IDs across PHP templates, CSS files, and JS files:

**IDs:**
- [ ] `paypalr-cc-owner` → `paypalac-cc-owner`
- [ ] `paypalr-cc-number` → `paypalac-cc-number`
- [ ] `paypalr-cc-expires-month` → `paypalac-cc-expires-month`
- [ ] `paypalr-cc-expires-year` → `paypalac-cc-expires-year`
- [ ] `paypalr-cc-cvv` → `paypalac-cc-cvv`
- [ ] `paypalr-saved-card` → `paypalac-saved-card`
- [ ] `paypalr-saved-card-new` → `paypalac-saved-card-new`
- [ ] `paypalr-savedcard-select` → `paypalac-savedcard-select`
- [ ] `paypalr-paylater-button` → `paypalac-paylater-button`
- [ ] `paypalr-paylater-payload` → `paypalac-paylater-payload`
- [ ] `paypalr-paylater-status` → `paypalac-paylater-status`
- [ ] `paypalr-applepay-button` → `paypalac-applepay-button`
- [ ] `paypalr-applepay-payload` → `paypalac-applepay-payload`
- [ ] `paypalr-applepay-status` → `paypalac-applepay-status`
- [ ] `paypalr-applepay-error` → `paypalac-applepay-error`
- [ ] `paypalr-venmo-button` → `paypalac-venmo-button`
- [ ] `paypalr-venmo-payload` → `paypalac-venmo-payload`
- [ ] `paypalr-venmo-status` → `paypalac-venmo-status`
- [ ] `paypalr-googlepay-button` → `paypalac-googlepay-button`
- [ ] `paypalr-googlepay-payload` → `paypalac-googlepay-payload`
- [ ] `paypalr-googlepay-status` → `paypalac-googlepay-status`

**CSS Classes:**
- [ ] `paypalr-savedcard-select` → `paypalac-savedcard-select`
- [ ] `paypalr-subscriptions-table` → `paypalac-subscriptions-table`
- [ ] `paypalr-subscription-meta` → `paypalac-subscription-meta`
- [ ] `paypalr-subscription-actions` → `paypalac-subscription-actions`
- [ ] `paypalr-upgrade-button` → `paypalac-upgrade-button`
- [ ] `paypalr-paylater-button` → `paypalac-paylater-button`
- [ ] `paypalr-card-logo` → `paypalac-card-logo`
- [ ] `paypalr-card-logos` → `paypalac-card-logos`
- [ ] `paypalr-venmo-button` → `paypalac-venmo-button`
- [ ] `paypalr-applepay-button` → `paypalac-applepay-button`
- [ ] `paypalr-googlepay-button` → `paypalac-googlepay-button`

> Any additional `paypalr-*` CSS identifiers discovered must also be renamed.

---

## Phase 5 — AJAX Handlers, Functions & Autoloaders
> Rename AJAX handler files, utility functions, and autoloader registrations.

### 5.1 AJAX Handler File Renames

- [ ] `ajax/paypalr_wallet.php` → `ajax/paypalac_wallet.php`
- [ ] `ajax/paypalr_wallet_checkout.php` → `ajax/paypalac_wallet_checkout.php`
- [ ] `ajax/paypalr_wallet_clear_cart.php` → `ajax/paypalac_wallet_clear_cart.php`

### 5.2 Function Files

- [ ] `includes/functions/paypalr_functions.php` → `paypalac_functions.php`
- [ ] Update all function names that start with `paypalr_` inside to `paypalac_`

### 5.3 Autoloader Files

- [ ] `includes/auto_loaders/paypalr_wallet_ajax.core.php` → `paypalac_wallet_ajax.core.php`
- [ ] Update all `paypalr` references inside the autoloader

### 5.4 Extra Language Definitions

- [ ] `includes/languages/english/extra_definitions/lang.paypalr_redirect_listener_definitions.php` → `lang.paypalac_redirect_listener_definitions.php`

### 5.5 Root-Level Entry Points (ppr_ files → ppac_ files)

These files use the `ppr_` prefix which stands for "PayPal Rest". Rename to `ppac_` ("PayPal Advanced Checkout"):

- [ ] `ppr_webhook.php` → `ppac_webhook.php`
- [ ] `ppr_listener.php` → `ppac_listener.php`
- [ ] `ppr_wallet.php` → `ppac_wallet.php`
- [ ] `ppr_add_card.php` → `ppac_add_card.php`

### 5.6 Extra Datafiles (ppr_ database/filename constants)

- [ ] `admin/includes/extra_datafiles/ppr_database_tables.php` → `ppac_database_tables.php`
- [ ] `includes/extra_datafiles/ppr_database_tables.php` → `ppac_database_tables.php`
- [ ] `includes/extra_datafiles/ppr_account_saved_credit_cards_filenames.php` → `ppac_account_saved_credit_cards_filenames.php`
- [ ] `includes/extra_datafiles/ppr_account_paypal_subscriptions_filenames.php` → `ppac_account_paypal_subscriptions_filenames.php`

Update all contents of these files:
- [ ] Replace any `ppr_` references in comments/defines with `ppac_`
- [ ] Replace any "PayPal Rest" references in comments with "PayPal Advanced Checkout"

### 5.7 Internal Entry Point Files (inside PayPalRestful/)

- [ ] `includes/modules/payment/paypal/PayPalRestful/ppr_webhook.php` → `ppac_webhook.php`
- [ ] `includes/modules/payment/paypal/PayPalRestful/ppr_listener.php` → `ppac_listener.php`

---

## Phase 6 — Observer Classes
> Rename observer files and their class names.

### 6.1 Storefront Observers

| Old File | New File | Old Class | New Class |
|---|---|---|---|
| `includes/classes/observers/auto.paypalrestful.php` | `auto.paypaladvcheckout.php` | `zcObserverPaypalrestful` | `zcObserverPaypaladvcheckout` |
| `includes/classes/observers/auto.paypalrestful_vault.php` | `auto.paypaladvcheckout_vault.php` | `zcObserverPaypalrestfulVault` | `zcObserverPaypaladvcheckoutVault` |
| `includes/classes/observers/auto.paypalrestful_savedcards.php` | `auto.paypaladvcheckout_savedcards.php` | `zcObserverPaypalrestfulSavedCards` | `zcObserverPaypaladvcheckoutSavedCards` |
| `includes/classes/observers/auto.paypalrestful_recurring.php` | `auto.paypaladvcheckout_recurring.php` | `zcObserverPaypalrestfulRecurring` | `zcObserverPaypaladvcheckoutRecurring` |

### 6.2 Admin Observer

| Old File | New File | Old Class | New Class |
|---|---|---|---|
| `admin/includes/classes/observers/auto.PaypalRestAdmin.php` | `auto.PaypalacAdmin.php` | `zcObserverPaypalRestAdmin` | `zcObserverPaypalacAdmin` |

### 6.3 Internal References

- [ ] Update all `paypalr` and `paypalrestful` references within observer class bodies
- [ ] Update all observer attach/notification names if they contain `paypalr`

---

## Phase 7 — PayPalRestful Namespace & Directory
> Rename the `PayPalRestful` PHP namespace and directory tree to `PayPalAdvancedCheckout`.

### 7.1 Directory Rename

- [ ] `includes/modules/payment/paypal/PayPalRestful/` → `includes/modules/payment/paypal/PayPalAdvancedCheckout/`

### 7.2 Namespace Updates

Every PHP file under the directory uses namespaces like `PayPalRestful\Admin`, `PayPalRestful\Api`, etc.

**Namespaces to rename:**

| Old Namespace | New Namespace |
|---|---|
| `PayPalRestful\Admin` | `PayPalAdvancedCheckout\Admin` |
| `PayPalRestful\Admin\Formatters` | `PayPalAdvancedCheckout\Admin\Formatters` |
| `PayPalRestful\Api` | `PayPalAdvancedCheckout\Api` |
| `PayPalRestful\Api\Data` | `PayPalAdvancedCheckout\Api\Data` |
| `PayPalRestful\Common` | `PayPalAdvancedCheckout\Common` |
| `PayPalRestful\Compatibility` | `PayPalAdvancedCheckout\Compatibility` |
| `PayPalRestful\Token` | `PayPalAdvancedCheckout\Token` |
| `PayPalRestful\Webhooks` | `PayPalAdvancedCheckout\Webhooks` |
| `PayPalRestful\Webhooks\Events` | `PayPalAdvancedCheckout\Webhooks\Events` |
| `PayPalRestful\Zc2Pp` | `PayPalAdvancedCheckout\Zc2Pp` |

### 7.3 Autoloader Updates

In `includes/modules/payment/paypal/pprAutoload.php` (→ `ppacAutoload.php`):
- [ ] Rename file
- [ ] Update all `addPrefix('PayPalRestful\...')` → `addPrefix('PayPalAdvancedCheckout\...')`
- [ ] Update all directory path strings from `PayPalRestful/` to `PayPalAdvancedCheckout/`

### 7.4 Use/Import Statements

Every file with `use PayPalRestful\...` must be updated to `use PayPalAdvancedCheckout\...`. This affects:

- All payment module files (`paypalac.php`, `paypalac_creditcard.php`, etc.)
- All admin pages
- All observer classes
- All test files
- `paypal_common.php`
- AJAX handlers
- Root entry points (`ppac_webhook.php`, `ppac_listener.php`, etc.)
- `webhook.core.php` autoloader
- `paypalSavedCardRecurring.php` class

### 7.5 String References to Namespace

- [ ] Update all `class_exists('PayPalRestful\\...')` calls
- [ ] Update all `ReflectionClass('PayPalRestful\\...')` calls
- [ ] Update all path strings containing `'PayPalRestful/'`

---

## Phase 8 — Template Files
> Rename template files and update their internal references.

### 8.1 File Renames

- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_applepay.php` → `tpl_modules_paypalac_applepay.php`
- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_googlepay.php` → `tpl_modules_paypalac_googlepay.php`
- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_venmo.php` → `tpl_modules_paypalac_venmo.php`
- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_product_applepay.php` → `tpl_modules_paypalac_product_applepay.php`
- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_product_googlepay.php` → `tpl_modules_paypalac_product_googlepay.php`
- [ ] `includes/templates/template_default/templates/tpl_modules_paypalr_product_venmo.php` → `tpl_modules_paypalac_product_venmo.php`
- [ ] `includes/templates/template_default/templates/tpl_paypalr_product_info.php` → `tpl_paypalac_product_info.php`
- [ ] `includes/templates/template_default/templates/tpl_paypalr_shopping_cart.php` → `tpl_paypalac_shopping_cart.php`

### 8.2 Internal Template References

- [ ] Update all `paypalr` string references inside template files (CSS classes, IDs, JS references)
- [ ] Update all template include paths that reference `paypalr` filenames

---

## Phase 9 — Webhook Autoloader
> Update the webhook bootstrap autoloader.

### 9.1 File: `includes/auto_loaders/webhook.core.php`

- [ ] Update all `PayPalRestful` namespace references to `PayPalAdvancedCheckout`
- [ ] Update all file path references from `PayPalRestful/` to `PayPalAdvancedCheckout/`
- [ ] Update any `paypalr` references in comments and string literals

---

## Phase 10 — paypal_common.php & Shared PayPal Files
> Update the shared PayPal common library.

### 10.1 File: `includes/modules/payment/paypal/paypal_common.php`

- [ ] Replace all `PayPalRestful` namespace references with `PayPalAdvancedCheckout`
- [ ] Replace all `MODULE_PAYMENT_PAYPALR_*` constants with `MODULE_PAYMENT_PAYPALAC_*`
- [ ] Replace all `paypalr` string references with `paypalac`
- [ ] Update any "PayPal REST API" or "RESTful" text in comments

### 10.2 File: `includes/modules/payment/paypal/pprAutoload.php` → `ppacAutoload.php`

- [ ] Rename file
- [ ] Update all namespace prefix registrations (see Phase 7.3)

---

## Phase 11 — Cron Files
> Update cron job files that reference paypalr.

### 11.1 Files to Update

- [ ] `cron/paypal_saved_card_recurring.php` — update all `paypalr` / `PayPalRestful` references
- [ ] `cron/remove_expired_cards.php` — update all `paypalr` / `PayPalRestful` references
- [ ] `cron/subscription_cancellations.php` — update all `paypalr` / `PayPalRestful` references
- [ ] `cron/paypal_wpp_recurring_reminders.php` — update all `paypalr` / `PayPalRestful` references

---

## Phase 12 — Documentation
> Remove all references to "PayPal REST API", "RESTful", "paypalr", and the lat9/paypalr repo.

### 12.1 README.md

- [ ] Replace all "PayPal REST API" / "RESTful" references with "PayPal Advanced Checkout"
- [ ] Remove or replace all `lat9/paypalr` links with project-owned documentation
- [ ] Replace any `paypalr` references with `paypalac`
- [ ] Update the module description to not reference the original REST API module

### 12.2 docs/ Directory Markdown Files

The following files contain `paypalr` or "PayPal REST API" references and must be updated:

- [ ] `docs/ADMIN_PAGE_REGISTRATION.md`
- [ ] `docs/CRON_TROUBLESHOOTING.md`
- [ ] `docs/ENVIRONMENT_DETECTION_FIX.md`
- [ ] `docs/FIXES_SUMMARY.md`
- [ ] `docs/GOOGLE_APPLE_PAY_SUBSCRIPTION_RESEARCH.md`
- [ ] `docs/IMPLEMENTATION_SUMMARY.md`
- [ ] `docs/MESSAGE_DISPLAY_FIX.md`
- [ ] `docs/PAYPAL_REQUEST_ID_FIX.md`
- [ ] `docs/SUBSCRIPTION_ACTIVATION.md`
- [ ] `docs/SUBSCRIPTION_ADDRESS_FIX_SUMMARY.md`
- [ ] `docs/SUBSCRIPTION_ARCHIVING.md`
- [ ] `docs/SUBSCRIPTION_ARCHIVING_SUMMARY.md`
- [ ] `docs/SUBSCRIPTION_BILLING_ADDRESS_ARCHITECTURE.md`
- [ ] `docs/SUBSCRIPTION_CATEGORIZATION_FIX.md`
- [ ] `docs/SUBSCRIPTION_FAILURE_RESCHEDULE_FIX.md`
- [ ] `docs/UPGRADE_TO_V1.3.9.md`
- [ ] `docs/VAULT_INCOMPATIBLE_PARAMETER_FIX.md`

### 12.3 HTML Documentation

- [ ] `docs/PayPal Advanced Checkout/readme.html` — update all `paypalr` and "REST API" references

---

## Phase 13 — Tests
> Update all test files that reference paypalr, PayPalRestful, or REST API.

### 13.1 Test Files Requiring Updates

The following test files contain `paypalr`, `PAYPALR`, or `PayPalRestful` references:

- [ ] `tests/AdminPageRegistrationConstantsTest.php`
- [ ] `tests/AddressNullCountryCodeHandlingTest.php`
- [ ] `tests/AdminAuthCodeHandlingTest.php`
- [ ] `tests/AdminSubscriptionsDateColumnTest.php`
- [ ] `tests/ApplePayAmountValidationTest.php`
- [ ] `tests/ApplePayAuthorizeModeOrderStatusTest.php`
- [ ] `tests/ApplePayButtonCssCustomPropertiesTest.php`
- [ ] `tests/ApplePayButtonCssStylingTest.php`
- [ ] `tests/ApplePayClientSideConfirmOrderIdSaveTest.php`
- [ ] `tests/ApplePayClientSideConfirmationTest.php`
- [ ] `tests/ApplePayConfirmOrderResponseHandlingTest.php`
- [ ] `tests/ApplePayJsSdkLoaderTest.php`
- [ ] `tests/ApplePayMerchantValidationTimeoutFixTest.php`
- [ ] `tests/ApplePayNoCancelOrderCreationTest.php`
- [ ] `tests/ApplePayOrderTotalSelectorTest.php`
- [ ] `tests/ApplePayServerSideConfirmationTest.php`
- [ ] `tests/ApplePaySessionUserGestureTest.php`
- [ ] `tests/ApplePayTokenAsJsonStringTest.php`
- [ ] `tests/ApplePayWalletPayloadGuidTest.php`
- [ ] `tests/AuthCodeCredentialExchangeTest.php`
- [ ] `tests/AuthorizeParentTxnIdTest.php`
- [ ] `tests/BulkArchiveSubscriptionsTest.php`
- [ ] `tests/CreatePayPalOrderRequestGenericDiscountModuleTest.php`
- [ ] `tests/CreatePayPalOrderRequestShippingDiscountTest.php`
- [ ] `tests/CreatePayPalOrderRequestStoreCreditDiscountTest.php`
- [ ] `tests/CreatePayPalOrderRequestVaultExpiryComponentsTest.php`
- [ ] `tests/CreatePayPalOrderRequestVaultTest.php`
- [ ] `tests/CreatePayPalOrderRequestWalletPaymentSourceTest.php`
- [ ] `tests/CredentialTrimmingTest.php`
- [ ] `tests/CreditCardAcceptedCardsDisplayTest.php`
- [ ] `tests/CreditCardErrorMessageFormattingTest.php`
- [ ] `tests/CreditCardOnFocusTest.php`
- [ ] `tests/CreditCardProcessButtonAjaxTest.php`
- [ ] `tests/CreditCardSkipAlreadyAuthorizedTest.php`
- [ ] `tests/CspNonceSupportTest.php`
- [ ] `tests/DeterminePayerActionRedirectPageTest.php`
- [ ] `tests/EnvironmentCredentialSavingTest.php`
- [ ] `tests/FraudAlertEmailTest.php`
- [ ] `tests/GooglePayAuthorizeModeOrderStatusTest.php`
- [ ] `tests/GooglePayClientSideConfirmationTest.php`
- [ ] `tests/GooglePaySdkLoadingConditionalTest.php`
- [ ] `tests/GooglePayUserGestureTest.php`
- [ ] `tests/HelpersDateConversionTest.php`
- [ ] `tests/LanguageClassAliasTest.php`
- [ ] `tests/LegacySubscriptionMigratorDuplicateKeyFixTest.php`
- [ ] `tests/LoggerCardNumberMaskingTest.php`
- [ ] `tests/MerchantIdPostMessageTest.php`
- [ ] `tests/MessageStackAdminFormatTest.php`
- [ ] `tests/MessageStackOutputTest.php`
- [ ] `tests/NativeApplePayImplementationTest.php`
- [ ] `tests/NativeGooglePayImplementationTest.php`
- [ ] `tests/ObserverCouponCalcsTest.php`
- [ ] `tests/ObserverFallbackOrderValuesTest.php`
- [ ] `tests/ObserverIndependentModulesTest.php`
- [ ] `tests/OrderGuidFinancialDataTest.php`
- [ ] `tests/OrderGuidReuseStatusCheckTest.php`
- [ ] `tests/PartnerAttributionTest.php`
- [ ] `tests/PayPalCommonTableVaultConstantTest.php`
- [ ] `tests/PayPalCreditCardModuleTest.php`
- [ ] `tests/PayPalEnvironmentDetectionTest.php`
- [ ] `tests/PayPalOnboardingApiEndpointTest.php`
- [ ] `tests/PayPalPartnerJsCallbackTest.php`
- [ ] `tests/PayPalRequestIdTest.php`
- [ ] `tests/PayPalRestfulApiConstructorTest.php`
- [ ] `tests/PaymentModuleOrderStatusId1Test.php`
- [ ] `tests/PaypalrProcessButtonAjaxReturnTypeTest.php`
- [ ] `tests/PaypalrVersion138UpgradeTest.php`
- [ ] `tests/ProxyRedirectUrlLoggingTest.php`
- [ ] `tests/RecurringAttributeKeyNormalizationTest.php`
- [ ] `tests/RecurringCronPaymentSourceNullHandlingTest.php`
- [ ] `tests/RecurringCronSkipDuplicateSubscriptionTest.php`
- [ ] `tests/RecurringObserverVaultNotificationTest.php`
- [ ] `tests/RecurringPaymentIntentTest.php`
- [ ] `tests/SavedCardAdminNotificationTest.php`
- [ ] `tests/SavedCardBrandDisplayTest.php`
- [ ] `tests/SavedCardCcInfoGetterTest.php`
- [ ] `tests/SavedCardForwardedFieldTest.php`
- [ ] `tests/SavedCardJsValidationSkipTest.php`
- [ ] `tests/SavedCardOrderStatusTest.php`
- [ ] `tests/SavedCardPaymentMethodNormalizationTest.php`
- [ ] `tests/SavedCardSelectBoxTest.php`
- [ ] `tests/SavedCardStatusConstantsTest.php`
- [ ] `tests/SavedCardTopLevelModuleTest.php`
- [ ] `tests/SavedCreditCardsManagerSchemaTest.php`
- [ ] `tests/SavedCreditCardsRecurringBillingAddressTest.php`
- [ ] `tests/SavedCreditCardsRecurringConstantTest.php`
- [ ] `tests/SdkConfigurationLoggingTest.php`
- [ ] `tests/SellerNoncePersistenceTest.php`
- [ ] `tests/SubscriptionManagerNullHandlingTest.php`
- [ ] `tests/SubscriptionSkipNextPaymentTest.php`
- [ ] `tests/SubscriptionVaultActivationTest.php`
- [ ] `tests/TokenRequest401RetryTest.php`
- [ ] `tests/VaultManagerDuplicateKeyTest.php`
- [ ] `tests/VaultManagerExpiredCardTest.php`
- [ ] `tests/VaultObserverSaveTest.php`
- [ ] `tests/VaultRecordOrderMatchingTest.php`
- [ ] `tests/VaultSaveCardFieldNameTest.php`
- [ ] `tests/VaultSaveCardPrefixTest.php`
- [ ] `tests/VaultToSavedCreditCardsSyncTest.php`
- [ ] `tests/VaultVisibilityTest.php`
- [ ] `tests/VenmoAuthorizeModeOrderStatusTest.php`
- [ ] `tests/VoidButtonValidationTest.php`
- [ ] `tests/WalletActualAmountUsageTest.php`
- [ ] `tests/WalletCaptureOrAuthorizeIntentTest.php`
- [ ] `tests/WalletCreatePayPalOrderVisibilityTest.php`
- [ ] `tests/WalletIneligiblePaymentHidingTest.php`
- [ ] `tests/WalletMerchantIdValidationTest.php`
- [ ] `tests/WalletModuleConstructorTest.php`
- [ ] `tests/WalletModuleRadioHiddenTest.php`
- [ ] `tests/WalletSdkComponentsCompatibilityTest.php`
- [ ] `tests/WalletSdkIntentParameterTest.php`
- [ ] `tests/WebhookLogsAdminReportTest.php`
- [ ] `tests/manual_verification.php`
- [ ] `tests/manual_verification_logger_fix.php`
- [ ] `tests/manual_verification_messagestack.php`
- [ ] `tests/Webhooks/PaymentCaptureCompletedTest.php`
- [ ] `tests/Webhooks/VaultPaymentTokenUpdatedTest.php`

### 13.2 Test File Renames

Some test files have `paypalr` in their filename:
- [ ] `tests/PaypalrProcessButtonAjaxReturnTypeTest.php` → `PaypalacProcessButtonAjaxReturnTypeTest.php`
- [ ] `tests/PaypalrVersion138UpgradeTest.php` → `PaypalacVersion138UpgradeTest.php`
- [ ] `tests/PayPalRestfulApiConstructorTest.php` → `PayPalAdvancedCheckoutApiConstructorTest.php`

---

## Phase 14 — Miscellaneous Files
> Catch-all for remaining files that reference paypalr.

### 14.1 Page Header Files

- [ ] `includes/modules/pages/account_saved_credit_cards/header_php.php` — update `paypalr` / `PayPalRestful` references
- [ ] `includes/modules/pages/account_paypal_subscriptions/header_php.php` — update `paypalr` / `PayPalRestful` references

### 14.2 Class Files

- [ ] `includes/classes/paypalSavedCardRecurring.php` — update all `paypalr` / `PayPalRestful` references

### 14.3 Template Overrides

- [ ] `includes/templates/YOUR_TEMPLATE/templates/tpl_account_saved_credit_cards_default.php` — update any `paypalr` / `PayPalRestful` references

---

## Phase 15 — Final Verification
> Validate that the conversion is complete and no old references remain.

### 15.1 Automated Search

- [ ] Run `grep -ri "paypalr" --include="*.php" --include="*.js" --include="*.css" --include="*.md" --include="*.html"` — expect **zero** results
- [ ] Run `grep -ri "PAYPALR" --include="*.php" --include="*.js" --include="*.css"` — expect **zero** results
- [ ] Run `grep -ri "PayPalRestful" --include="*.php" --include="*.js"` — expect **zero** results
- [ ] Run `grep -ri "paypal rest api" --include="*.php" --include="*.js" --include="*.md" --include="*.html"` — expect **zero** results (except possibly "PayPal REST APIs" describing what PayPal provides, not our module name)
- [ ] Run `find . -name "*paypalr*"` — expect **zero** results
- [ ] Run `find . -name "*ppr_*"` — expect **zero** results
- [ ] Run `find . -name "*PayPalRestful*"` — expect **zero** results

### 15.2 Manual Verification

- [ ] Verify all PHP classes can be autoloaded with new namespace
- [ ] Verify all admin pages load correctly
- [ ] Verify all payment module variants register with Zen Cart under new module codes
- [ ] Verify JavaScript SDK loads and wallet buttons render
- [ ] Verify webhooks are received and processed
- [ ] Verify cron jobs function correctly
- [ ] Verify CSS styles apply correctly

### 15.3 Test Suite

- [ ] Run all existing tests and verify they pass
- [ ] Verify no test references old naming conventions

---

## Summary Statistics

| Category | Approximate Count |
|---|---|
| Files to rename | ~85 |
| Files with content changes | ~200+ |
| Constants to rename | ~40+ |
| CSS classes/IDs to rename | ~30+ |
| JS globals to rename | ~20+ |
| Namespace paths to update | ~10 |
| Admin page keys to update | 4 |
| Observer classes to rename | 5 |
| Test files to update | ~110+ |
| Documentation files to update | ~20 |

---

## Important Notes

1. **Automatic Config Migration**: The new `paypalac` module's `install()` method detects existing
   `MODULE_PAYMENT_PAYPALR_*` keys in the database and copies their **values** into the new
   `MODULE_PAYMENT_PAYPALAC_*` keys. This ensures beta testers and existing stores keep their
   credentials, order statuses, and all other settings without reconfiguring. See Phase 2.3 for
   full implementation details.

2. **Old Module Not Touched**: The migration **copies** values — it does not rename or delete
   the old `paypalr` keys. Both modules can coexist temporarily. The store owner must manually
   uninstall the old `paypalr` module via Admin → Modules → Payment and then delete the old files.

3. **Admin Page Keys**: The old module's admin page registrations (`paypalrSubscriptions`, etc.) are
   left in place and cleaned up when the store owner uninstalls the old module. The new module
   registers its own pages independently (`paypalacSubscriptions`, etc.).

4. **Order History**: Existing orders in the `paypal` table reference `paypalr` as the payment module.
   The admin observer/notification handler must recognize both `paypalr` and `paypalac` module codes
   so that historical order details continue to display correctly.

5. **Webhook URLs**: If webhook URLs contain `ppr_webhook.php`, these need to be re-registered with
   PayPal after the rename to `ppac_webhook.php`. The `install()` method should handle webhook
   re-registration automatically.

6. **Template Overrides**: Customers who have copied template files to custom template directories
   will need to rename their copies manually. Document this in upgrade instructions.
