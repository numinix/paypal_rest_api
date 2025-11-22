# PayPal Credit Card Module (paypalr_creditcard)

## Overview

The `paypalr_creditcard` payment module provides credit card payment processing as a separate, standalone module option alongside the base PayPal Advanced Checkout (`paypalr`) module. This separation allows merchants to offer PayPal wallet payments and credit card payments as distinct choices to customers during checkout.

## Purpose

Prior to this module, the `paypalr` module combined both PayPal wallet and credit card payment options within a single module selection. While functional, this design had some limitations:

1. Both payment methods appeared as a single option in the payment modules list
2. Customers saw both PayPal and credit card options within one payment method selection
3. Store administrators couldn't independently control the availability of each payment method

The `paypalr_creditcard` module addresses these issues by creating a separate module specifically for credit card payments.

## Features

- **Independent Module**: Appears as a separate payment method option in both admin and storefront
- **Credit Cards Only**: Shows only credit card input fields, no PayPal wallet button
- **Shared Infrastructure**: Uses `PayPalCommon` class for shared payment processing logic, follows same pattern as wallet modules (Google Pay, Apple Pay, Venmo)
- **Individual Control**: Can be enabled/disabled independently from the PayPal wallet option
- **Full Feature Support**: Supports all credit card features including:
  - Saved cards (PayPal Vault)
  - Multiple card types (Visa, MasterCard, Amex, Discover, etc.)
  - Authorization and capture modes
  - Refunds, voids, and captures from admin
  - 3D Secure authentication support

## Installation

1. **Prerequisite**: The base `paypalr` (PayPal Advanced Checkout) module must be installed and configured with valid API credentials

2. **Install the Module**:
   - Navigate to `Modules > Payment` in the admin
   - Find "PayPal Credit Cards" in the list
   - Click "Install"

3. **Configure**:
   - **Enable Credit Card Payments**: Set to "True" to enable the module
   - **Sort Order**: Set the display order relative to other payment modules
   - **Payment Zone**: Optionally restrict to specific geographic zones

## Configuration

The credit card module has its own configuration but shares the PayPal API credentials and most settings from the base `paypalr` module:

### Shared Settings (from paypalr)
- PayPal Server (live/sandbox)
- Client ID and Secret
- Transaction Mode (Final Sale vs Auth Only)
- Order Status IDs
- Currency settings
- Debugging options
- All PayPal API configuration

### Independent Settings (paypalr_creditcard specific)
- `MODULE_PAYMENT_PAYPALR_CREDITCARD_STATUS`: Enable/disable credit card payments
- `MODULE_PAYMENT_PAYPALR_CREDITCARD_SORT_ORDER`: Display order
- `MODULE_PAYMENT_PAYPALR_CREDITCARD_ZONE`: Geographic zone restriction
- `MODULE_PAYMENT_PAYPALR_CREDITCARD_VERSION`: Module version (read-only)

## Usage

### Recommended Setup

For the cleanest separation of PayPal wallet and credit card options:

1. **Install both modules**:
   - PayPal Advanced Checkout (`paypalr`)
   - PayPal Credit Cards (`paypalr_creditcard`)

2. **Configure paypalr for wallet-only**:
   - Set `Accept Credit Cards?` to `false`
   - This makes `paypalr` show only the PayPal wallet button

3. **Enable paypalr_creditcard**:
   - This provides the credit card payment option

Now customers will see two separate payment method choices:
- **PayPal** - For PayPal wallet payments
- **Credit Card** - For direct credit card payments

### Alternative Setups

You can use the modules in different configurations:

- **Credit cards only**: Enable only `paypalr_creditcard`, disable `paypalr`
- **Combined (original behavior)**: Enable only `paypalr` with `Accept Credit Cards = true`
- **PayPal wallet only**: Enable only `paypalr` with `Accept Credit Cards = false`

## Technical Details

### Architecture

```
paypalr_creditcard extends base

Uses PayPalCommon class for shared logic:
├── processCreditCardPayment() - Payment processing (auth/capture)
├── getVaultedCardsForCustomer() - Vault card retrieval  
├── storeVaultCardData() - Vault card storage
└── Other shared payment methods...
```

The credit card module:
- Extends `base` class (same pattern as Google Pay, Apple Pay, Venmo)
- Uses `PayPalCommon` class for shared payment processing logic
- Implements credit card-specific UI in `selection()` method
- Handles credit card validation in module
- Delegates payment processing to common class
- Forces `ppr_type='card'` in the session when selected

### Security

The module enforces the same security requirements as other PayPal modules:

- **SSL Required**: Credit card payments require HTTPS in production (sandbox allows HTTP for testing)
- **Card Type Validation**: At least one supported card type must be enabled in Zen Cart's credit card configuration
- **Parent Module Required**: The base `paypalr` module must be installed and functional

If these requirements aren't met, the credit card module automatically disables itself.

### Field Names

Form fields use the `paypalr_` prefix (same as parent module):
- `paypalr_cc_owner`
- `paypalr_cc_number`
- `paypalr_cc_expires_month`
- `paypalr_cc_expires_year`
- `paypalr_cc_cvv`
- `paypalr_saved_card` (for vault)
- `paypalr_cc_save_card` (save card checkbox)

### Session Data

When this module is selected, it sets:
```php
$_SESSION['payment'] = 'paypalr_creditcard';
$_SESSION['PayPalRestful']['ppr_type'] = 'card';
```

## Vault (Saved Cards)

The credit card module fully supports PayPal Vault for saving customer cards:

- Cards are automatically vaulted with PayPal for security
- Customers can choose to save cards for future use via checkbox
- Saved cards appear in the credit card module's payment selection
- Vault management uses the same `account_saved_credit_cards` page as the parent module
- Vault functionality requires `MODULE_PAYMENT_PAYPALR_ENABLE_VAULT = True` in the base module configuration

## Troubleshooting

### Module doesn't appear in admin

**Cause**: The parent `paypalr` module is not installed

**Solution**: Install and configure the PayPal Advanced Checkout module first

### Module is disabled on storefront

**Possible causes**:
1. SSL not configured (site not using HTTPS in production)
2. No supported card types enabled in Configuration > Credit Cards
3. Parent `paypalr` module has invalid API credentials
4. Module's STATUS is set to False or Retired

**Solution**: Check each requirement and ensure all are met

### Credit card fields don't appear

**Cause**: The `selection()` method may have filtered out the fields incorrectly

**Solution**: Check server error logs for PHP errors, verify the parent module shows credit card fields when configured to accept cards

## Compatibility

- **Zen Cart**: 1.5.7c and later
- **PHP**: 7.1 through 8.4 (PHP 7.4+ recommended)
- **Checkout Types**: 3-page checkout, One-Page Checkout (OPC)
- **Templates**: responsive_classic, ZCA Bootstrap, and custom templates

## Files

```
includes/modules/payment/paypalr_creditcard.php
includes/languages/english/modules/payment/lang.paypalr_creditcard.php
includes/languages/english/modules/payment/paypalr_creditcard.php (legacy)
```

## Support

For issues or questions:
1. Check that the base `paypalr` module is working correctly first
2. Review server error logs for PHP errors
3. Enable debugging in the base PayPal module to see transaction logs
4. Consult the PayPal Advanced Checkout wiki and support threads

## Changelog

### v1.3.3 (2025-01-17)
- Initial release of separate credit card module
- Extends paypalr base module for code reuse
- Independent enable/disable control
- Full vault and 3DS support
