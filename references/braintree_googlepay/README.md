# Braintree Google Pay

This module adds Google Pay as a payment option in Zen Cart using Braintree Payments.

## Installation
1. Install the [Braintree Payments for Zen Cart](https://www.numinix.com/braintree-payments-921.html) plugin.
2. Install the Numinix Premium Plugin Installer (version 2.0.0 or newer).
3. Back up your database and files.
4. Upload this package, keeping the directory structure (rename `YOUR_ADMIN` to your admin folder).
5. In Zen Cart admin, enable the module under **Modules > Payment** and copy your Braintree account settings.
6. Obtain and verify your Merchant ID in the Google Pay Console, then enter it in the module configuration.
7. Verify the Google Pay button renders correctly and test in Sandbox mode before switching to Production.

## 3D Secure and SCA considerations
- If you serve customers in the EEA or UK (e.g., Netherlands, Ireland, France), enable **Use 3D Secure** in the Braintree Google Pay configuration and make sure 3D Secure is turned on in your Braintree Control Panel. Google Pay will rely on a client token to support 3DS; a tokenization key alone cannot run 3DS flows.
- Test an EU/UK billing address in Sandbox before going live to confirm the Google Pay sheet opens and returns a 3DS-ready nonce.

Configuration tips and detailed instructions are available in [docs/Braintree Google Pay/readme.html](docs/Braintree%20Google%20Pay/readme.html).

## Tests
Run the setup script once to install PHP and PHPUnit:

```bash
./setup.sh
phpunit
```

Then execute the test suite with `phpunit`.

