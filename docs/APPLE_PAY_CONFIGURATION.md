# Apple Pay Configuration Guide

## Overview

This guide covers configuration options for the PayPal Apple Pay integration module.

## Order Total Element Selector

The Apple Pay module needs to extract the order total from the checkout page to display the correct amount in the Apple Pay payment sheet. By default, it uses the `#ottotal` element (standard Zen Cart order total element).

### Default Configuration

If you're using a standard Zen Cart template, no configuration is needed. The module will automatically detect the order total from `#ottotal`.

### Custom Configuration

If your Zen Cart theme uses a different element ID for the order total, you can configure it using the `data-total-selector` attribute:

```html
<div id="paypalr-applepay-button" data-total-selector="your-custom-total-id"></div>
```

#### Example

If your theme uses `#cart-total` instead of `#ottotal`:

```html
<div id="paypalr-applepay-button" data-total-selector="cart-total"></div>
```

### How It Works

The Apple Pay module uses this configuration in two places:

1. **`getOrderTotalFromPage()`** - Extracts the order total amount and currency from the page when the user clicks the Apple Pay button
2. **`observeOrderTotal()`** - Monitors the order total element for changes and automatically re-renders the Apple Pay button when the amount changes

### Requirements

The order total element should:
- Contain the total amount as text (e.g., "$123.45", "USD 123.45", "€123,45")
- Update dynamically when cart contents change
- Be present on the page before the Apple Pay button is rendered

### Currency Detection

The module automatically detects the currency from the order total text:
- `$` or "USD" → USD
- `€` or "EUR" → EUR  
- `£` or "GBP" → GBP
- "CAD" → CAD
- "AUD" → AUD

If no currency symbol is found, it defaults to USD.

## Technical Details

### User Gesture Compliance

The Apple Pay module creates the `ApplePaySession` synchronously in the click handler to comply with Apple's requirement that sessions must be created from a user gesture. The order total is extracted from the page synchronously, and the PayPal order is created asynchronously in the `onvalidatemerchant` callback.

This approach ensures:
- ✅ No "InvalidAccessError: Must create a new ApplePaySession from a user gesture handler" errors
- ✅ Users see the actual order amount (not $0.00)
- ✅ The Apple Pay button automatically updates when order totals change

### Code Flow

1. User clicks Apple Pay button (user gesture starts)
2. Extract order total from page element (synchronous)
3. Create `ApplePaySession` with page amount (synchronous)
4. Start `fetchWalletOrder()` to create PayPal order (async)
5. Call `session.begin()` (synchronous)
6. In `onvalidatemerchant`: Wait for order creation, then validate merchant
7. In `onpaymentauthorized`: Confirm order with PayPal

## Troubleshooting

### "Order total element not found" Warning

If you see this warning in the browser console:
```
Apple Pay: Order total element not found: #ottotal
```

**Solution**: Configure a custom selector using `data-total-selector` attribute.

### Amount Shows as $0.00

If the Apple Pay sheet shows $0.00:
1. Verify the order total element exists on the page
2. Verify the element contains readable amount text
3. Check the browser console for extraction errors
4. Ensure the element ID matches your configuration

### Button Doesn't Update When Total Changes

If the Apple Pay button doesn't update when you change quantities:
1. Verify the order total element ID is correct
2. Check that the element content changes (not just attributes)
3. Ensure JavaScript is enabled
4. Check browser console for MutationObserver errors

## Related Documentation

- [CSP Support](CSP_SUPPORT.md) - Content Security Policy configuration
- [Testing ISU](TESTING_ISU.md) - Testing integrated signup features
