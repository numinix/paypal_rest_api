# PayPal Integrated Sign-Up (ISU) Implementation

## Overview

The PayPal ISU feature has been redesigned to provide a seamless experience where:
- **Admin users** stay on their admin page throughout the onboarding process
- **Direct users** on numinix.com see their credentials after completion
- The numinix.com backend acts as an API service to handle PayPal Partner API calls

## Architecture

### Components

1. **Admin ISU Page** (`admin/paypalr_integrated_signup.php`)
   - Provides in-admin onboarding interface
   - Proxies AJAX requests to numinix.com
   - Opens PayPal in popup window
   - Polls for completion and auto-fills credentials

2. **Numinix.com API** (`numinix.com/includes/modules/pages/paypal_signup/`)
   - Handles PayPal Partner API authentication
   - Creates partner referrals
   - Retrieves merchant integration status and credentials
   - Returns data via JSON responses

3. **Credential Extraction** (`class.numinix_paypal_onboarding_service.php`)
   - Extracts merchant credentials from PayPal's oauth_integrations
   - Returns client_id and client_secret when onboarding completes

## Flow Diagrams

### Admin-Initiated Flow

```
┌─────────────┐
│ Admin clicks│
│ ISU button  │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ AJAX → numinix.com  │
│ POST /start         │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Get PayPal URL      │
│ Open popup window   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ User completes      │
│ PayPal onboarding   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Popup closes        │
│ Admin polls status  │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Credentials returned│
│ Auto-save to config │
│ Display to user     │
└─────────────────────┘
```

### Numinix.com Direct Flow

```
┌─────────────┐
│ User visits │
│ numinix.com │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ Click Start Signup  │
│ AJAX → /start       │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Get PayPal URL      │
│ Open popup window   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ User completes      │
│ PayPal onboarding   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ JavaScript polls    │
│ for completion      │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Credentials         │
│ displayed on page   │
│ User copies them    │
└─────────────────────┘
```

## API Endpoints

### POST /index.php?main_page=paypal_signup

All requests require:
- `nxp_paypal_action`: Action to perform (start, finalize, status)
- `nonce`: Session-based CSRF token
- `env`: Environment (sandbox or live)

#### Action: start

Initiates the onboarding process.

**Request:**
```json
{
  "nxp_paypal_action": "start",
  "nonce": "abc123...",
  "env": "sandbox"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tracking_id": "nxp-abc123...",
    "partner_referral_id": "partner-ref-123",
    "redirect_url": "https://www.paypal.com/...",
    "action_url": "https://www.paypal.com/...",
    "step": "waiting",
    "polling_interval": 5000,
    "nonce": "abc123..."
  }
}
```

#### Action: status

Polls for onboarding status and retrieves credentials when complete.

**Request:**
```json
{
  "nxp_paypal_action": "status",
  "nonce": "abc123...",
  "tracking_id": "nxp-abc123...",
  "env": "sandbox"
}
```

**Response (In Progress):**
```json
{
  "success": true,
  "data": {
    "tracking_id": "nxp-abc123...",
    "step": "waiting",
    "polling_interval": 5000,
    "merchant_id": "",
    "payments_receivable": false
  }
}
```

**Response (Completed with Credentials):**
```json
{
  "success": true,
  "data": {
    "tracking_id": "nxp-abc123...",
    "step": "completed",
    "merchant_id": "MERCHANT123",
    "payments_receivable": true,
    "credentials": {
      "client_id": "AYx...",
      "client_secret": "EC..."
    }
  }
}
```

## Security Considerations

### Credential Handling

1. **Transmission**: Credentials are transmitted over HTTPS only
2. **Logging**: Credentials are redacted from all event logs and telemetry
3. **Session Storage**: Credentials stored temporarily in session only until saved to database
4. **Database Storage**: Credentials saved using Zen Cart's `zen_db_input()` to prevent SQL injection
5. **Display**: Credentials displayed with warning to store securely

### CSRF Protection

- Nonce token generated per session
- Validated on all AJAX requests
- Token included in responses for subsequent requests

### Origin Validation

- Admin proxy validates requests come from admin session
- Numinix.com validates origin headers
- CORS properly configured for admin requests

## Configuration

### Required Constants

The following constants should be defined in the module configuration:

```php
// Numinix portal URL (default: https://www.numinix.com/index.php)
MODULE_PAYMENT_PAYPALR_NUMINIX_PORTAL

// PayPal environment (sandbox or live)
MODULE_PAYMENT_PAYPALR_SERVER

// Partner API credentials (stored in numinix.com environment)
NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_ID
NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_SECRET
NUMINIX_PPCP_LIVE_PARTNER_CLIENT_ID
NUMINIX_PPCP_LIVE_PARTNER_CLIENT_SECRET
```

## Testing

### Manual Testing

#### Admin Flow

1. Navigate to admin modules page
2. Select PayPal Advanced Checkout
3. Click "Complete PayPal Setup" button
4. Verify popup opens with PayPal
5. Complete PayPal onboarding
6. Verify popup closes
7. Verify credentials appear on admin page
8. Verify credentials saved to database
9. Return to module configuration and verify credentials populated

#### Numinix.com Flow

1. Navigate to https://www.numinix.com/index.php?main_page=paypal_signup
2. Click "Start PayPal Signup"
3. Complete PayPal onboarding
4. Verify credentials displayed on page
5. Copy credentials
6. Navigate to admin and paste credentials

### Edge Cases to Test

- [ ] Popup blocker enabled
- [ ] User closes popup before completion
- [ ] Network error during polling
- [ ] PayPal returns error
- [ ] Invalid credentials from PayPal
- [ ] Session timeout during onboarding
- [ ] Multiple simultaneous onboarding attempts

## Troubleshooting

### Popup Blocked

If popups are blocked:
1. Allow popups for the admin domain
2. Retry the onboarding process

### Credentials Not Returned

If credentials aren't returned after completion:
1. Check PayPal Partner API credentials are configured
2. Verify merchant account is approved
3. Check error logs for API errors
4. Manually retrieve credentials from PayPal.com

### Database Save Fails

If credentials can't be saved:
1. Check database permissions
2. Verify configuration keys exist
3. Check error logs for SQL errors
4. Manually enter credentials in module config

## Code Structure

```
admin/
  paypalr_integrated_signup.php       # Admin ISU page (new AJAX-based)
  paypalr_integrated_signup_old.php   # Legacy redirect-based version

numinix.com/
  includes/
    modules/pages/paypal_signup/
      header_php.php                   # API handlers (start, status, finalize)
      class.numinix_paypal_onboarding_service.php  # PayPal Partner API client
      jscript_paypal_signup.js         # Frontend JS for numinix.com page
    templates/nmn/
      templates/
        tpl_paypal_signup_default.php  # Numinix.com page template
      css/
        paypal_signup.css               # Styling including credentials display
```

## Migration Notes

### For Existing Installations

The new implementation is backwards compatible:
- Old redirect-based flow still works via `action=return`
- New AJAX-based flow is default for fresh ISU button clicks
- Existing tracking IDs and sessions remain valid

### Deployment Steps

1. Deploy updated `admin/paypalr_integrated_signup.php`
2. Deploy updated numinix.com files
3. Test both flows in sandbox environment
4. Monitor for errors in production
5. Update documentation and support materials

## Future Enhancements

- [ ] Add webhook for completion notification
- [ ] Support for multi-currency setup
- [ ] Batch onboarding for multiple merchants
- [ ] Enhanced error reporting and diagnostics
- [ ] Automatic retry on transient failures
