# PayPal Advanced Checkout Payment Module

This Zen Cart payment module (`paypalr`) combines the processing for the **PayPal Payments Pro** (`paypaldp`) and **PayPal Express Checkout** (`paypalwpp`) payment modules that are currently built into Zen Cart distributions. The PayPal Advanced Checkout plugin uses PayPal's now-current [REST APIs](https://developer.paypal.com/api/rest/) to replace the older NVP (**N**ame **V**alue **P**air) communication methods and unifies the two legacy modules into one.

> **Note:** This fork is branded as **PayPal Advanced Checkout** to distinguish it from Zen Cart's core PayPal REST API module and prevent versioning conflicts.

## Documentation

For complete installation, configuration, usage, and troubleshooting instructions, please refer to the full documentation:

📖 **[PayPal Advanced Checkout Documentation](docs/PayPal%20Advanced%20Checkout/readme.html)**

## Quick Links

- **Project Wiki:** https://github.com/lat9/paypalr/wiki

## Requirements

- **Zen Cart:** 1.5.7c or later
- **PHP:** 7.4 through 8.4 (PHP 7.3 and older are not supported)
- **Required Patch:** Apply the [order_total notifier patch](https://github.com/lat9/paypalr/wiki/Required-changes-to-%60-includes-classes-order_total.php%60) for Zen Cart versions prior to 1.5.8a

## Support

Need assistance? Contact the Numinix support team at [support@numinix.com](mailto:support@numinix.com) with your Zen Cart version, PHP version, and details about your issue.
