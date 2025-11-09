# Numinix Partner Attribution Tracking

## Overview

All PayPal modules in this plugin include the Numinix partner attribution ID (`NuminixPPCP_SP`) in every API call to PayPal through centralized code. This document explains how the partner tracking is implemented and verified.

## Implementation

The partner attribution tracking is implemented in the `PayPalRestfulApi` class located at:
```
includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php
```

### Key Components

1. **Partner Attribution Constant** (line 110):
   ```php
   public const PARTNER_ATTRIBUTION_ID = 'NuminixPPCP_SP';
   ```

2. **Authorization Header Method** (line 931):
   The `setAuthorizationHeader()` method automatically includes the partner attribution in all API requests:
   ```php
   $curl_options[CURLOPT_HTTPHEADER] = [
       'Content-Type: application/json',
       "Authorization: Bearer $oauth2_token",
       'Prefer: return=representation',
       'PayPal-Partner-Attribution-Id: ' . self::PARTNER_ATTRIBUTION_ID,
   ];
   ```

3. **All HTTP Methods Include Partner Attribution**:
   - `curlPost()` - Used for creating orders, captures, authorizations, refunds, etc.
   - `curlGet()` - Used for retrieving order status, transactions, vault tokens, etc.
   - `curlPatch()` - Used for updating vault tokens, webhooks, trackers, etc.
   - `curlDelete()` - Used for deleting vault tokens

   All of these methods call `setAuthorizationHeader()`, ensuring the partner attribution is included in every request.

## Modules Using Centralized Code

### Payment Modules
All payment modules use the centralized `PayPalRestfulApi` class:

1. **Main Module** (`paypalr.php`)
   - Directly instantiates `PayPalRestfulApi` for all payment operations
   - Lines 633, 669

2. **Wallet Modules** (extend `paypalr`)
   - `paypalr_applepay.php` - Apple Pay integration
   - `paypalr_googlepay.php` - Google Pay integration
   - `paypalr_venmo.php` - Venmo integration
   - All inherit the API instantiation from the parent `paypalr` class

### Supporting Code
Other parts of the plugin also use the centralized API class:

1. **Admin Order Tracking** (`admin/includes/classes/observers/auto.PaypalRestAdmin.php`)
   - Line 91: `new PayPalRestfulApi(...)`

2. **Payment Listener** (`ppr_listener.php`)
   - Line 98: `new PayPalRestfulApi(...)`

3. **Vault Management** (`includes/modules/pages/account_saved_credit_cards/header_php.php`)
   - Lines 528, 658: `new PayPalRestfulApi(...)`

4. **Subscription Management** (`includes/modules/pages/account_paypal_subscriptions/header_php.php`)
   - Line 455: `new PayPalRestfulApi(...)`

5. **Webhook Handlers**
   - `includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookHandlerContract.php` (line 67)
   - `includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookResponder.php` (line 119)

## Verification

A comprehensive test suite is provided to verify partner attribution:

```bash
php tests/PartnerAttributionTest.php
```

This test verifies:
1. The partner attribution constant is defined with the correct value
2. The `setAuthorizationHeader()` method includes the partner attribution header
3. All CURL methods (POST, GET, PATCH, DELETE) use `setAuthorizationHeader()`
4. All wallet modules extend the main `paypalr` class
5. All supporting code locations use `PayPalRestfulApi`

## Conclusion

**YES**, all PayPal modules support the custom Numinix partner tracking through centralized code when processing payments. The partner attribution ID is:
- Defined in a single location (`PayPalRestfulApi::PARTNER_ATTRIBUTION_ID`)
- Automatically included in all HTTP headers for every API call
- Used by all payment modules and supporting code without exception

This centralized approach ensures that:
- No code duplication is required
- Partner tracking cannot be accidentally omitted
- Future modules automatically inherit the partner attribution
- Changes to the partner ID only need to be made in one place
