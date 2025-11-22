# Partner Attribution Quick Reference

## TL;DR
✅ **All PayPal modules include Numinix partner tracking automatically**  
✅ **No action required when adding new modules**  
✅ **Partner ID: `NuminixPPCP_SP`**

## For Developers Adding New Payment Features

### ✅ DO THIS (Automatic Partner Tracking)
```php
// Option 1: Extend the main paypalr class
class paypalr_newwallet extends paypalr {
    // You automatically get partner tracking!
}

// Option 2: Use PayPalRestfulApi directly
use PayPalRestful\Api\PayPalRestfulApi;
$api = new PayPalRestfulApi('sandbox', $clientId, $secret);
// Partner tracking is automatically included!
```

### ❌ DON'T DO THIS (Bypasses Partner Tracking)
```php
// DON'T make direct CURL calls to PayPal
curl_setopt($ch, CURLOPT_URL, 'https://api.paypal.com/...');
// This bypasses the centralized API and partner tracking!

// DON'T create your own API wrapper
class MyPayPalApi {
    // This won't include partner tracking!
}
```

## Quick Verification
Run the test anytime:
```bash
php tests/PartnerAttributionTest.php
```

## Where Partner Tracking Happens
1. **Defined:** `PayPalRestfulApi::PARTNER_ATTRIBUTION_ID`
2. **Applied:** `PayPalRestfulApi::setAuthorizationHeader()`
3. **Used by:** ALL HTTP methods (POST, GET, PATCH, DELETE)

## Questions?
See detailed documentation: `docs/PARTNER_ATTRIBUTION.md`
