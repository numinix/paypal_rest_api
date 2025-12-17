# Google Pay Setup Requirements

## Overview

This document explains how to properly configure Google Pay for the PayPal REST API integration. Google Pay requires specific setup steps in your PayPal account before it will work.

## Problem: "Configuration is missing allowedPaymentMethods"

If you see this error in your browser console:

```
[Google Pay] Configuration is missing allowedPaymentMethods
```

This means that `paypal.Googlepay().config()` is not returning the required payment configuration. The most common cause is that **Google Pay is not enabled in your PayPal account**.

## Solution: Enable Google Pay in PayPal

### For Sandbox/Test Environment

1. Go to the [PayPal Developer Dashboard](https://developer.paypal.com/)
2. Navigate to **Apps & Credentials**
3. Select your **Sandbox** app
4. Scroll down to the **Features** section
5. Find the **Google Pay** checkbox
6. **Check the box** to enable Google Pay
7. Click **Save**

### For Live/Production Environment

**IMPORTANT**: Enabling Google Pay in sandbox is NOT sufficient for production. You must also enable it in your live PayPal business account.

1. Go to the [PayPal Business Dashboard](https://www.paypal.com/businessprofile/settings)
2. Navigate to **Account Settings** or **Payments**
3. Look for **Google Pay** or **Alternative Payment Methods**
4. Enable **Google Pay** for your business account
5. Complete any required onboarding steps

**Note**: If you don't see the Google Pay option, you may need to:
- Verify your PayPal business account is in good standing
- Ensure your business is in a supported country
- Contact PayPal support to enable Google Pay for your account

### Verify Setup

After enabling Google Pay, verify it's working:

1. Clear your browser cache
2. Reload the checkout page
3. Check the browser console for these success messages:
   ```
   [Google Pay] Initializing PayPal Googlepay API
   [Google Pay] Eligibility check passed
   [Google Pay] Checking if ready to pay with 1 payment methods
   [Google Pay] Device is ready to pay, creating button
   [Google Pay] Button rendered successfully
   ```

## Do I Need a Google Merchant ID?

**For PayPal REST API Integration: NO**

When using PayPal as your payment gateway (which this integration does), you do **NOT** need to register separately with Google for a Google Merchant ID. PayPal acts as the gateway and handles the Google Pay integration for you.

The only requirement is to enable Google Pay in your PayPal account as described above.

### Sandbox vs. Production

- **Sandbox**: Works without Google Merchant ID once enabled in PayPal Developer Dashboard
- **Production**: Works without Google Merchant ID once enabled in PayPal Business Account

## Technical Details

### How PayPal Google Pay Works

1. **SDK Loading**: The PayPal SDK is loaded with the `googlepay` component:
   ```javascript
   https://www.paypal.com/sdk/js?components=googlepay&client-id=YOUR_CLIENT_ID
   ```

2. **Configuration**: When you call `paypal.Googlepay().config()`, PayPal returns:
   - `allowedPaymentMethods`: Array of supported payment methods
   - `merchantInfo`: Merchant information
   - `apiVersion`: Google Pay API version
   - And other configuration details

3. **Payment Processing**: PayPal acts as the payment gateway, so:
   - You don't need a separate Google Merchant ID
   - PayPal handles tokenization and payment processing
   - Payments are processed through your PayPal account

### What If allowedPaymentMethods Is Missing?

The `allowedPaymentMethods` array is provided by PayPal's SDK and is **required** for Google Pay to work. If it's missing, it means:

1. Google Pay is not enabled in your PayPal account (most common)
2. Your PayPal account is not eligible for Google Pay
3. There's a configuration issue with your PayPal app
4. Your site is not using HTTPS (required for Google Pay)

## Troubleshooting

### Button Doesn't Appear

**Possible causes:**
1. Google Pay not enabled in PayPal account
2. Not using HTTPS
3. Unsupported browser/device
4. Country/currency not supported

**Solution:**
- Enable Google Pay in PayPal account (see above)
- Ensure your site uses HTTPS (required for production)
- Test in a supported browser (Chrome, Safari, Edge)
- Verify your country and currency are supported by Google Pay

### Console Error: "Not eligible for this user/device"

This is normal for desktop browsers that don't support Google Pay. Google Pay primarily works on:
- Android devices with Chrome
- iOS devices with Safari
- Desktop Chrome with saved cards

### Console Error: "isReadyToPay response: {result: false}"

This means the current device/browser cannot use Google Pay. This is expected behavior and the module will hide itself automatically.

## Environment Modes

### Sandbox (Test) Mode

- Uses PayPal Sandbox credentials
- Google Pay works in TEST mode
- No real money is processed
- Simulated payment flows

### Live (Production) Mode

- Uses PayPal Live credentials  
- Google Pay works in PRODUCTION mode
- Real money is processed
- **MUST enable Google Pay in your live PayPal business account**

## Required Configuration

The following PayPal configuration values must be set:

- `MODULE_PAYMENT_PAYPALR_SERVER`: `sandbox` or `live`
- `MODULE_PAYMENT_PAYPALR_CLIENTID_S`: Your Sandbox Client ID (for sandbox)
- `MODULE_PAYMENT_PAYPALR_CLIENTID_L`: Your Live Client ID (for live)
- `MODULE_PAYMENT_PAYPALR_SECRET_S`: Your Sandbox Secret (for sandbox)
- `MODULE_PAYMENT_PAYPALR_SECRET_L`: Your Live Secret (for live)

**Note**: The `MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID` setting has been removed in version 1.3.6 as it's not required for PayPal REST API integration.

## Support and Resources

### Official Documentation

- [PayPal Google Pay Integration Guide](https://developer.paypal.com/docs/checkout/apm/google-pay/)
- [PayPal Developer Dashboard](https://developer.paypal.com/)
- [Google Pay Web Integration](https://developers.google.com/pay/api/web/guides/tutorial)

### Common Issues

1. **"Configuration is missing allowedPaymentMethods"**
   - Solution: Enable Google Pay in your PayPal account

2. **Button not showing**
   - Solution: Check console for errors, verify HTTPS, enable Google Pay

3. **Payment fails after button click**
   - Solution: Check PayPal credentials, verify account is in good standing

4. **Different results in sandbox vs. production**
   - Solution: Enable Google Pay in BOTH sandbox AND live PayPal accounts

### Contact Support

If you've followed all steps above and Google Pay still isn't working:

1. Check your browser console for detailed error messages
2. Verify Google Pay is enabled in your PayPal account (both sandbox and live as appropriate)
3. Ensure you're using HTTPS
4. Contact PayPal Merchant Support for assistance with enabling Google Pay on your account

## Version History

- **v1.3.6**: Removed `MODULE_PAYMENT_PAYPALR_GOOGLEPAY_MERCHANT_ID` requirement
- Previous: Required Google Merchant ID (no longer needed)

## Summary

**Key Takeaways:**

1. ✅ **Do**: Enable Google Pay in your PayPal account (required)
2. ✅ **Do**: Use HTTPS for production
3. ✅ **Do**: Enable in both sandbox AND live PayPal accounts
4. ❌ **Don't**: Try to register separately with Google for a Merchant ID
5. ❌ **Don't**: Configure `google-pay-merchant-id` in the SDK URL
6. ❌ **Don't**: Pass `merchantId` to `paypal.Googlepay()` constructor

**This integration uses PayPal as the payment gateway, which means PayPal handles all Google Pay configuration and processing. You only need to enable Google Pay in your PayPal account.**
