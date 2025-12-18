# PayPal Wallet Buttons Integration Guide

## Overview

This guide provides step-by-step instructions for integrating PayPal wallet buttons (Google Pay, Apple Pay, and Venmo) into your Zen Cart store on both shopping cart and product pages.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Shopping Cart Integration](#shopping-cart-integration)
3. [Product Page Integration](#product-page-integration)
4. [Configuration](#configuration)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Files

Ensure all wallet button files are uploaded to your Zen Cart installation:

**Template Files:**
- `includes/templates/template_default/templates/tpl_paypalr_shopping_cart.php`
- `includes/templates/template_default/templates/tpl_paypalr_product_info.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_googlepay.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_applepay.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_venmo.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_product_googlepay.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_product_applepay.php`
- `includes/templates/template_default/templates/tpl_modules_paypalr_product_venmo.php`

**AJAX Handlers:**
- `ajax/paypalr_wallet.php`
- `ajax/paypalr_wallet_checkout.php`
- `ajax/paypalr_wallet_clear_cart.php`

**Supporting Files:**
- `includes/auto_loaders/paypalr_wallet_ajax.core.php`
- `includes/functions/paypalr_functions.php`

### Module Requirements

The following payment modules must be installed and configured:
- PayPal Google Pay (`paypalr_googlepay`)
- PayPal Apple Pay (`paypalr_applepay`)
- PayPal Venmo (`paypalr_venmo`)

---

## Shopping Cart Integration

### Step 1: Locate Your Shopping Cart Template

Navigate to your active template's shopping cart file:
```
includes/templates/YOUR_TEMPLATE/templates/tpl_shopping_cart_default.php
```

If you're using the default template:
```
includes/templates/template_default/templates/tpl_shopping_cart_default.php
```

### Step 2: Find the Checkout Button Section

Look for the section containing the "Continue Checkout" or "Proceed to Checkout" button. This is typically near the bottom of the cart display, after the order totals.

### Step 3: Insert the Wallet Button Code

Add the following code **immediately before or after** the checkout button:

```php
<?php
  // PayPal Wallet Buttons (Google Pay, Apple Pay, Venmo)
  $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_paypalr_shopping_cart.php';
  if (!file_exists($template_path)) {
    $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_paypalr_shopping_cart.php';
  }
  if (file_exists($template_path)) {
    include($template_path);
  }
?>
```

### Example Placement

```php
<!-- Existing checkout button -->
<div class="buttonRow forward">
  <?php echo zen_draw_hidden_field('main_page', FILENAME_CHECKOUT_SHIPPING); ?>
  <?php echo zen_image_submit(BUTTON_IMAGE_CHECKOUT, BUTTON_CHECKOUT_ALT); ?>
</div>

<?php
  // PayPal Wallet Buttons (Google Pay, Apple Pay, Venmo)
  $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_paypalr_shopping_cart.php';
  if (!file_exists($template_path)) {
    $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_paypalr_shopping_cart.php';
  }
  if (file_exists($template_path)) {
    include($template_path);
  }
?>
```

---

## Product Page Integration

### Step 1: Locate Your Product Info Template

Navigate to your active template's product info display file:
```
includes/templates/YOUR_TEMPLATE/templates/tpl_product_info_display.php
```

If you're using the default template:
```
includes/templates/template_default/templates/tpl_product_info_display.php
```

### Step 2: Find the Add to Cart Button Section

Look for the section containing the "Add to Cart" button. This is typically within the product options/quantity form.

### Step 3: Insert the Wallet Button Code

Add the following code **immediately after** the "Add to Cart" button:

```php
<?php
  // PayPal Wallet Buttons (Google Pay, Apple Pay, Venmo)
  $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_paypalr_product_info.php';
  if (!file_exists($template_path)) {
    $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_paypalr_product_info.php';
  }
  if (file_exists($template_path)) {
    include($template_path);
  }
?>
```

### Example Placement

```php
<!-- Existing add to cart button -->
<div class="buttonRow">
  <?php echo zen_draw_hidden_field('products_id', (int)$_GET['products_id']); ?>
  <?php echo zen_image_submit(BUTTON_IMAGE_IN_CART, BUTTON_IN_CART_ALT); ?>
</div>

<?php
  // PayPal Wallet Buttons (Google Pay, Apple Pay, Venmo)
  $template_path = DIR_WS_TEMPLATES . $template_dir . '/templates/tpl_paypalr_product_info.php';
  if (!file_exists($template_path)) {
    $template_path = DIR_WS_TEMPLATES . 'template_default/templates/tpl_paypalr_product_info.php';
  }
  if (file_exists($template_path)) {
    include($template_path);
  }
?>
```

---

## Configuration

### Enable/Disable Wallet Buttons

Currently, wallet buttons are controlled by the payment module status. To enable or disable buttons:

1. Log into your Zen Cart admin panel
2. Navigate to **Modules > Payment**
3. Find the wallet payment module (e.g., "PayPal Google Pay")
4. Click **Edit**
5. Set **Module Status** to **True** or **False**

### Future Enhancement: Per-Page Display Control

**Note:** Display location constants (`SHOPPING_CART`, `PRODUCT_PAGE`) are not yet implemented in the payment modules. Once added, you'll be able to control button display on each page independently.

To add these controls, modify each payment module's configuration to include:
- `MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_SHOPPING_CART` - True/False
- `MODULE_PAYMENT_PAYPALR_GOOGLE_PAY_PRODUCT_PAGE` - True/False
- (Same for Apple Pay and Venmo)

### Required Configuration Settings

For wallet buttons to work, ensure each payment module has:

**All Modules:**
- Valid PayPal API credentials (Client ID, Secret)
- Correct environment (Sandbox/Production)
- Module status set to "True"

**Google Pay Specific:**
- Valid Google Merchant ID
- Domain registered in Google Pay Console

**Apple Pay Specific:**
- Domain registered in Apple Developer Console
- Valid merchant identifier

---

## Testing

### Pre-Testing Checklist

- [ ] All files uploaded correctly
- [ ] Payment modules installed and configured
- [ ] Template files include the integration code
- [ ] Browser cache cleared

### Shopping Cart Testing

1. Add items to cart
2. Navigate to shopping cart page
3. Verify wallet buttons appear (if modules enabled)
4. Click a wallet button
5. Complete payment flow
6. Verify order is created
7. Check order confirmation email

### Product Page Testing

1. Navigate to a product page
2. Select quantity and options (if applicable)
3. Verify wallet buttons appear (if modules enabled)
4. Click a wallet button
5. Verify product is added to cart
6. Complete payment flow
7. Verify order is created
8. Check order confirmation email

### Browser Testing

Test on multiple browsers and devices:
- **Chrome** (desktop & mobile) - All wallets
- **Safari** (desktop & iOS) - All wallets, especially Apple Pay
- **Firefox** (desktop & mobile) - All wallets
- **Edge** (desktop) - All wallets
- **iOS Safari** - Apple Pay (requires real device)
- **Android Chrome** - Google Pay, Venmo

### Device Testing

**Apple Pay:**
- Requires Safari browser
- Requires Apple device with Apple Pay configured
- Will not show on non-Apple devices (graceful degradation)

**Google Pay:**
- Works on all modern browsers
- Best experience on Chrome
- Requires Google account with payment method

**Venmo:**
- Best experience on mobile devices
- US accounts only
- Requires Venmo app installed (mobile)

---

## Troubleshooting

### Buttons Not Appearing

**Check 1: Module Status**
- Verify payment module is enabled in Admin > Modules > Payment

**Check 2: File Existence**
- Verify all template files are uploaded
- Check file permissions (should be readable)

**Check 3: Template Integration**
- Ensure integration code is in the correct template file
- Check for PHP errors in error logs

**Check 4: Browser Console**
- Open browser developer tools (F12)
- Check Console tab for JavaScript errors
- Look for SDK loading errors

### Buttons Appear But Don't Work

**Check 1: AJAX Handlers**
- Verify `ajax/paypalr_wallet.php` exists and is accessible
- Check web server error logs for PHP errors

**Check 2: Module Configuration**
- Verify API credentials are correct
- Check environment setting (Sandbox vs Production)
- Ensure Google Merchant ID is valid (Google Pay)

**Check 3: Session Issues**
- Clear browser cookies
- Test in incognito/private browsing mode
- Check Zen Cart session configuration

### Payment Fails

**Check 1: API Credentials**
- Verify Client ID and Secret are correct
- Ensure credentials match environment (Sandbox/Production)

**Check 2: Logs**
- Check `DIR_FS_LOGS/paypalr_wallet_handler.log`
- Look for API error messages
- Check PayPal dashboard for transaction details

**Check 3: Order Creation**
- Verify cart has items
- Check shipping methods are available
- Ensure tax calculation is working

### Apple Pay Specific Issues

**Problem: Apple Pay button doesn't appear**
- Only shows on Safari browser
- Requires Apple device
- Requires Apple Pay configured on device
- Domain must be registered with Apple

**Problem: Merchant validation fails**
- Domain not registered in Apple Developer Console
- Invalid merchant identifier
- PayPal/Braintree Apple Pay not properly configured

### Google Pay Specific Issues

**Problem: Google Pay button doesn't appear**
- Invalid Google Merchant ID
- Merchant ID not approved by Google
- Domain not registered in Google Pay Console

**Problem: Payment sheet doesn't open**
- Browser doesn't support Google Pay
- Google account has no payment methods
- Google Pay disabled in browser settings

### Venmo Specific Issues

**Problem: Venmo button doesn't appear**
- US accounts only
- Venmo integration requires mobile app on mobile devices
- Eligibility check failed

---

## Advanced Configuration

### Custom Styling

Wallet button styles are defined in `tpl_paypalr_shopping_cart.php`. To customize:

1. Copy the template to your custom template directory
2. Modify the `<style>` section at the bottom
3. Adjust button width, height, margins, etc.

### Error Handling

Custom error messages can be displayed by modifying the error div sections in each button template.

### Logging

Enable debug logging in each payment module's configuration to track:
- SDK initialization
- Order creation
- Payment processing
- Error conditions

Logs are written to: `DIR_FS_LOGS/paypalr_wallet_handler.log`

---

## Support

### Documentation References

- Braintree Google Pay: https://developer.paypal.com/braintree/docs/guides/google-pay
- Braintree Apple Pay: https://developer.paypal.com/braintree/docs/guides/apple-pay
- PayPal Venmo: https://developer.paypal.com/docs/checkout/venmo/

### Common Issues

Refer to the Troubleshooting section above for solutions to common problems.

### File Locations Quick Reference

**Templates:**
```
includes/templates/template_default/templates/tpl_paypalr_*.php
```

**AJAX Handlers:**
```
ajax/paypalr_wallet*.php
```

**Logs:**
```
DIR_FS_LOGS/paypalr_wallet_handler.log
```

---

## Summary

Once integrated, wallet buttons provide customers with fast, secure checkout options directly from the shopping cart or product pages. The buttons automatically handle:

- Payment authorization
- Shipping address collection
- Shipping method selection
- Order total calculation
- Order creation
- Cart cleanup
- Email notifications

All critical Zen Cart functionality is preserved, including:
- Tax calculation
- Shipping cost calculation
- Order total modules
- Guest checkout
- Multi-currency support
- Zone restrictions

The integration is designed to be seamless and require minimal modifications to existing templates.
