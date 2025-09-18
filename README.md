# PayPal RESTful API Payment Module
This Zen Cart payment module (`paypalr`) combines the processing for the **PayPal Payments Pro** (`paypaldp`) and **PayPal Express Checkout** (`paypalwpp`) payment modules that are currently built into Zen Cart distributions.  Instead of using the older NVP (**N**ame **V**alue **P**air) methods to communicate with PayPal, this payment module uses PayPal's now-current [REST APIs](https://developer.paypal.com/api/rest/) and combines the two legacy methods into one.

Zen Cart Support Thread: https://www.zen-cart.com/forumdisplay.php?170-PayPal-RESTful-support

Zen Cart Plugin Download Link: https://www.zen-cart.com/downloads.php?do=file&id=2382

**Zen Cart compatibility.** The payment module supports Zen Cart v1.5.7c and later, provided that the following requirements are met:

1. Apply the module's [required `order_total` notifier patch](https://github.com/lat9/paypalr/wiki/Required-changes-to-%60-includes-classes-order_total.php%60); this is the only change that touches a core file.
2. Use the bundled ObserverManager trait shim that this module installs automatically.
3. Use the bundled language shim that this module installs automatically.

Those shims provide the 1.5.8a trait and language fallbacks automatically&mdash;no additional downloads are needed beyond the `order_total` patch.

The module's operation has been validated …

1. With PHP versions 7.4 through 8.4; **PHP 7.3 will result in fatal PHP errors!**
2. In Zen Cart's 3-page checkout environment (v1.5.7**c**+, v2.0.x and v2.1.0)
3. With One-Page Checkout  (OPC), v2.4.6-2.5.3
   1. Using *OPC*'s guest-checkout feature.
   2. Both requiring confirmation and not!
4. With both the built-in responsive_classic and [ZCA Bootstrap](https://www.zen-cart.com/downloads.php?do=file&id=2191) (v3.6.2-3.7.7) templates.

For additional information, refer to the payment-module's [wiki articles](https://github.com/lat9/paypalr/wiki).

## Integrated sign-up (ISU)

Zen Cart's admin exposes a **Complete PayPal setup** button that launches PayPal's integrated sign-up (ISU) experience. The helper posts the store's metadata to PayPal, opens the hosted onboarding flow in a new window, and then routes the merchant back through `admin/paypalr_integrated_signup.php` so the module can finish configuration.

### Prerequisites

* Install the module and apply the required `order_total` notifier patch.
* Populate the storefront metadata that ISU sends to PayPal (store name, owner name, and owner email address) from Zen Cart's configuration.
* Provide PayPal partner credentials for both sandbox and live environments without committing secrets to version control. Use either `includes/local/paypal_partner_credentials.php` (not shipped in the plugin package) or set environment variables:
  * `PAYPAL_PARTNER_CLIENT_ID_SANDBOX` / `PAYPAL_PARTNER_CLIENT_SECRET_SANDBOX`
  * `PAYPAL_PARTNER_CLIENT_ID_LIVE` / `PAYPAL_PARTNER_CLIENT_SECRET_LIVE`

### Sandbox versus live

The module chooses the sandbox or live onboarding flow based on the **PayPal Server** setting (`MODULE_PAYMENT_PAYPALR_SERVER`). Sandbox flows let you test onboarding without touching production accounts. Live onboarding requires production partner credentials and writes the resulting merchant credentials back to your configuration (via the helper's `paypalr_isu` session data) once PayPal redirects to Zen Cart.

### Partner attribution and redirects

PayPal requires partners to identify themselves whenever merchants onboard. The helper injects PayPal's partner attribution id `NuminixPPCP_SP` into the API request and sets both return and cancel URLs so PayPal can route the merchant back to `admin/paypalr_integrated_signup.php?action=return` or `action=cancel`. After a successful return the helper verifies the onboarding status, stores the newly issued merchant credentials, and finally redirects the administrator to the Payment Modules page.

## Partner credential packaging guidance

Partner credentials should never be committed to a repository or shipped in a plugin archive. Keep secrets in `includes/local/paypal_partner_credentials.php` (which stays outside the distribution) or rely on environment variables when deploying to staging and production. The helper reads from the local configuration file first and then from the environment, making it safe to package the module without exposing API keys.
