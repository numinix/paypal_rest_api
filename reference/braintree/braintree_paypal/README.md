# zc_braintree_paypal

A Zen Cart plugin that integrates PayPal payments via Braintree. This repository contains the payment module, language files and admin installer used to add the option to your storefront.

## Overview

The module relies on the [Braintree PHP SDK](https://github.com/braintree/braintree_php) and the shared `BraintreeCommon` class provided by the separate zc_braintree package. Using the Braintree JavaScript client it renders a PayPal button on the checkout page and processes the resulting payment nonce via the Braintree gateway.

Main PHP logic lives in [`includes/modules/payment/braintree_paypal.php`](includes/modules/payment/braintree_paypal.php). Client‑side behaviour is handled by the JavaScript code embedded by that file which loads the Braintree PayPal Checkout scripts only when necessary.

## Directory layout

```
includes/
  modules/payment/braintree_paypal.php  – Payment module
  languages/english/modules/payment/…   – Language definitions
YOUR_ADMIN/
  includes/auto_loaders/…               – Autoloader to run the installer
  includes/init_includes/…              – Installer/upgrade bootstrap
  includes/installers/braintree_paypal/ – Versioned installer scripts
setup.sh                                – Helper script to install PHP CLI dependencies
```

`docs/Braintree PayPal` contains an HTML version of the installation guide.

## Installation

1. Copy the files into your Zen Cart installation, preserving the directory structure.
2. Log in to your admin area and navigate to *Modules → Payment*.
3. Locate **Braintree PayPal** and click *Install*. The installer reads the scripts under `YOUR_ADMIN/includes/installers/braintree_paypal` to create the required configuration entries.
4. Configure your Braintree merchant ID, public and private keys along with the environment (sandbox or production).

The configuration options also allow setting the order statuses used for successful, pending and refunded payments as well as enabling debug logging.

## Operation

During checkout the module:

1. Generates a Braintree client token using `BraintreeCommon`.
2. Loads the Braintree `client.js` and `paypal-checkout.js` libraries.
3. Renders the PayPal button and tokenises the selected PayPal account.
4. Submits the nonce to Zen Cart where `before_process()` finalises the transaction via Braintree.
5. Stores the transaction details in the Braintree table and updates order status accordingly.

Refund and capture actions performed from the admin orders page delegate to the shared `BraintreeCommon` helper functions.

## Requirements

- PHP with `mbstring` and `xml` extensions
- Braintree PHP SDK accessible via `includes/modules/payment/braintree/lib/`
- Zen Cart 1.5.x

Optional: run `setup.sh` to install PHP CLI and PHPUnit if you need to execute unit tests.

## License

Released under the GNU General Public License v3. See [`license.txt`](license.txt) for details.
