# Google Pay Merchant Verification Process

## Overview

To use Google Pay buttons on cart and product pages when customers are **not logged in**, you must complete Google Pay merchant verification with Google Pay Console. This allows the native Google Pay SDK to capture the customer's email address, which is required for account creation.

When customers **are logged in**, the PayPal SDK is used instead, and merchant verification is not required since the email is already known.

## When is Merchant Verification Required?

Google Pay merchant verification is required only if:
1. You want to display Google Pay buttons on cart or product pages, AND
2. You want to allow non-logged-in users to complete purchases with Google Pay

If you only use Google Pay during checkout (where users typically have already created an account), merchant verification is **not required**.

## Verification Steps

### Step 1: Access Google Pay Business Console

1. Go to [Google Pay Business Console](https://pay.google.com/business/console/)
2. Sign in with your Google account
3. If this is your first time, you'll be prompted to create a business profile

### Step 2: Create or Select Your Business Profile

1. Enter your business information:
   - Business name
   - Business address
   - Business website
   - Support email address
2. Accept the Google Pay API Terms of Service
3. Click "Create Profile" or "Continue"

### Step 3: Register Your Merchant ID

1. In the Google Pay Business Console, navigate to "Integration" or "Merchant Info"
2. Note your **Google Merchant ID** (format: 12345678901234567890)
3. Copy this Merchant ID - you'll need it for your PayPal configuration

### Step 4: Configure Payment Gateway Integration

1. In the Google Pay Business Console, go to "Gateway settings" or "Payment methods"
2. Select **PayPal** as your payment gateway
3. Enter your PayPal credentials or merchant information as requested
4. Save the configuration

### Step 5: Domain Verification

1. In the Console, navigate to "Domain verification" or "Settings"
2. Add your store's domain (e.g., `www.yourstore.com`)
3. Google will provide verification methods:
   - **HTML file upload**: Download a verification file and upload to your site root
   - **DNS TXT record**: Add a TXT record to your domain's DNS settings
   - **Meta tag**: Add a meta tag to your homepage
4. Choose a method and complete the verification
5. Click "Verify" to confirm domain ownership

### Step 6: Submit Integration for Review

1. In the Console, navigate to the integration review section
2. Complete the merchant information form:
   - Provide screenshots of your checkout flow showing Google Pay button
   - Describe how Google Pay is integrated on your site
   - Confirm compliance with Google Pay branding guidelines
3. Submit for review

**Important**: Set your Google Pay Environment to **TEST** in your PayPal module configuration while completing this step. This allows you to take screenshots and test the integration before going live.

### Step 7: Configure Your PayPal Module

1. Log in to your Zen Cart admin panel
2. Go to **Modules > Payment > PayPal Google Pay**
3. Configure the following settings:
   - **Google Pay Merchant ID**: Enter the Merchant ID from Step 3
   - **Google Pay Environment**: 
     - Set to **TEST** during verification/testing
     - Change to **PRODUCTION** after Google approves your integration
   - **Enable on Shopping Cart Page**: True (if desired)
   - **Enable on Product Page**: True (if desired)

### Step 8: Testing in TEST Mode

1. With Google Pay Environment set to **TEST**:
   - Test transactions will use Google's test environment
   - You can complete the integration flow
   - Take screenshots for Google's review process
2. Ensure Google Pay buttons appear correctly on your cart/product pages
3. Complete a test purchase to verify the flow works end-to-end

### Step 9: Wait for Google Approval

1. Google typically reviews submissions within 1-2 business days
2. You'll receive an email when your integration is approved or if changes are needed
3. If changes are requested, make the updates and resubmit

### Step 10: Switch to PRODUCTION Mode

1. Once Google approves your integration:
2. Return to **Modules > Payment > PayPal Google Pay**
3. Change **Google Pay Environment** from TEST to **PRODUCTION**
4. Save the configuration
5. Your Google Pay buttons are now live and will process real transactions

## Important Notes

### Login Status Behavior

- **User NOT logged in + Merchant ID set**: Uses native Google Pay SDK to capture email
- **User logged in**: Uses PayPal SDK (no merchant ID needed)
- **Checkout page**: Always uses PayPal SDK regardless of login status

### Merchant ID Format

The Google Merchant ID should be a 12-20 character alphanumeric string provided by Google Pay Console. Example: `BCR2DN6T7KZD3XPC`

### Environment Settings

- **TEST**: Use during initial setup and for Google's review process
  - Transactions go through Google's test environment
  - No real charges are processed
  - Required for taking verification screenshots

- **PRODUCTION**: Use after Google approval
  - Processes real transactions
  - Charges actual payment methods
  - Only switch after receiving approval email from Google

### Troubleshooting

**Google Pay button doesn't appear on cart/product pages:**
- Verify Google Merchant ID is correctly entered
- Check that Google Pay Environment is set (TEST or PRODUCTION)
- Ensure "Enable on Shopping Cart Page" or "Enable on Product Page" is set to True
- If user is not logged in, confirm Merchant ID is set and valid

**"Merchant not found" error:**
- Merchant ID may be incorrect or not yet approved by Google
- Verify the ID matches exactly what's in Google Pay Console
- Ensure your integration has been approved by Google

**Domain verification fails:**
- Verify you've added the correct verification file/meta tag/DNS record
- Check that your domain matches exactly (with or without www)
- Wait a few hours for DNS changes to propagate if using DNS verification

## Additional Resources

- [Google Pay Web Integration Guide](https://developers.google.com/pay/api/web/guides/tutorial)
- [Google Pay Brand Guidelines](https://developers.google.com/pay/api/web/guides/brand-guidelines)
- [Google Pay Business Console](https://pay.google.com/business/console/)
- [PayPal Advanced Checkout Documentation](https://developer.paypal.com/docs/checkout/)

## Support

If you encounter issues during the verification process:
1. Check the Google Pay Business Console for specific error messages
2. Review the troubleshooting section above
3. Contact Google Pay Support through the Business Console
4. For PayPal integration issues, refer to PayPal's support resources
