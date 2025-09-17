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
