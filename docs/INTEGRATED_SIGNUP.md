# PayPal Integrated Sign-Up (ISU)

## Overview

The PayPal Advanced Checkout module includes an Integrated Sign-Up (ISU) feature that streamlines the process of connecting a PayPal account and obtaining API credentials. This feature provides a "Complete PayPal Setup" button directly in the module's admin configuration page.

## How It Works

### User Flow

1. **Initial Setup**
   - When an administrator first installs the PayPal Advanced Checkout module or when API credentials are not yet configured, they will see a "PayPal Account Setup" section in the module's admin configuration page.
   
2. **Starting the Onboarding Process**
   - Clicking the "Complete PayPal Setup" button initiates the secure onboarding process through Numinix.com
   - The user is redirected to the Numinix onboarding portal with encrypted store metadata
   - The button label indicates whether it's for Production or Sandbox environment based on the current module settings

3. **Completing Onboarding**
   - The user follows the guided steps on Numinix.com to connect their PayPal account
   - PayPal validates the account and generates API credentials
   - Upon successful completion, the user is redirected back to the store

4. **Automatic Configuration**
   - If the onboarding process successfully returns credentials, they are automatically saved to the module configuration
   - The admin sees a success message: "PayPal onboarding completed successfully! Your API credentials have been automatically configured."
   - The ISU button will no longer appear since credentials are now populated

5. **Manual Fallback**
   - If the onboarding process doesn't return credentials, the admin sees instructions for manually retrieving credentials from PayPal.com
   - The admin can also cancel the onboarding process and enter credentials manually at any time

## Technical Details

### Button Display Logic

The ISU button appears when:
- The module is being viewed in the admin area
- Either the Client ID or Secret is missing for the currently active environment (Live or Sandbox)

The button is hidden when:
- Both Client ID and Secret are populated for the active environment

### Environment Detection

The module automatically detects which environment is active:
- **Production/Live**: Uses `MODULE_PAYMENT_PAYPALR_CLIENTID_L` and `MODULE_PAYMENT_PAYPALR_SECRET_L`
- **Sandbox**: Uses `MODULE_PAYMENT_PAYPALR_CLIENTID_S` and `MODULE_PAYMENT_PAYPALR_SECRET_S`

### Credential Storage

When credentials are successfully returned from the onboarding process:
1. The system receives `client_id` and `client_secret` as URL parameters
2. The `paypalr_save_credentials()` function validates and saves them to the database
3. Credentials are stored in the `configuration` table with the appropriate keys
4. The `last_modified` timestamp is updated

### Security Considerations

- Credentials are transmitted via HTTPS
- Database updates use Zen Cart's `zen_db_input()` function to prevent SQL injection
- Errors are logged using `trigger_error()` without exposing sensitive details to users
- The onboarding tracking ID is stored in the session for correlation

## Manual Credential Entry

If automated onboarding fails or the admin prefers manual entry, the ISU section provides step-by-step instructions:

1. Log in to your PayPal account at https://www.paypal.com/
2. Navigate to **Apps & Credentials**
3. Create REST API credentials or retrieve existing ones
4. Copy the Client ID and Secret
5. Paste them into the module's configuration fields

## Files Modified

### Core Module Files

1. **includes/modules/payment/paypalr.php**
   - Added `getIsuButton()` method to generate the ISU section HTML
   - Method is called during admin initialization to add button to module description
   - Button only displays when credentials are missing

2. **admin/paypalr_integrated_signup.php**
   - Added credential detection on return from onboarding
   - Added `paypalr_save_credentials()` function to store credentials
   - Enhanced return handling to show appropriate success messages

### Language Files

3. **includes/languages/english/modules/payment/lang.paypalr.php**
   - Added ISU-specific language constants:
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_BUTTON`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_HEADING`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_INTRO`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_MANUAL_FALLBACK`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_SUCCESS_AUTO`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_RETURN_MESSAGE`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_CANCEL_MESSAGE`
     - `MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_ISU_ERROR_MESSAGE`

## Troubleshooting

### Button Not Appearing

If the ISU button doesn't appear:
- Verify that at least one credential (Client ID or Secret) is empty for the active environment
- Check that you're viewing the module in the admin area
- Ensure the language files have been properly loaded

### Credentials Not Saving

If credentials aren't being saved automatically:
- Check the store's error logs for database errors
- Verify that the `configuration` table is writable
- Ensure the configuration keys exist in the database
- Try manually entering credentials as a fallback

### Onboarding Process Issues

If the onboarding process fails to launch:
- Verify your store's URL is accessible from external servers
- Check that SSL/HTTPS is properly configured
- Review Numinix.com documentation for any service status issues
- Use the manual credential entry method as an alternative

## Related Files

The ISU implementation integrates with files in the `numinix.com` directory:
- `numinix.com/includes/modules/pages/paypal_signup/` - Storefront onboarding pages
- `numinix.com/management/includes/classes/Numinix/PaypalIsu/` - Backend services

Note: The `numinix.com` directory will be deployed to numinix.com and removed from this repository after successful testing.

## Support

For issues with:
- **The ISU button or credential saving**: Contact the module developer
- **The onboarding process itself**: Contact Numinix support with your tracking ID
- **PayPal account or API issues**: Contact PayPal support

## Version History

- **v1.3.4+**: Integrated Sign-Up button added to admin configuration page with automatic credential storage
