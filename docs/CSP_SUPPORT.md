# Content Security Policy (CSP) Support

## Overview

The PayPal Advanced Checkout module now includes full support for Content Security Policy (CSP) with script-src nonces. This ensures compatibility with modern web security practices and prevents CSP violations when loading PayPal SDK and related scripts.

## What is CSP?

Content Security Policy (CSP) is a web security standard that helps prevent cross-site scripting (XSS) attacks by controlling which scripts can execute on a webpage. When a CSP policy is in place with `script-src` directive, scripts need to either:
1. Be loaded from whitelisted domains
2. Include a nonce attribute that matches the CSP header
3. Have their content hashed and the hash added to the CSP

## How This Module Implements CSP

This module uses the **nonce-based approach**, which is the recommended method for dynamically loaded scripts. The implementation works as follows:

### 1. Server-Side (PHP)

When Zen Cart has CSP enabled and provides a `$GLOBALS['CSP_NONCE']` variable, the observer automatically:

- Adds the nonce attribute to the PayPal SDK script tag
- Adds the nonce attribute to inline script tags
- Sanitizes the nonce value using `htmlspecialchars()` for security

Example from `auto.paypalrestful.php`:

```php
// Add CSP nonce attribute if available (Zen Cart 2.0+ CSP support)
$csp_nonce = '';
if (isset($GLOBALS['CSP_NONCE']) && !empty($GLOBALS['CSP_NONCE'])) {
    $csp_nonce = htmlspecialchars($GLOBALS['CSP_NONCE'], ENT_QUOTES, 'UTF-8');
    $js_scriptparams[] = 'nonce="' . $csp_nonce . '"';
}
```

### 2. Client-Side (JavaScript)

JavaScript files that dynamically create script tags include a `getCspNonce()` helper function that:

- Queries for existing script tags with a nonce attribute
- Retrieves the nonce value from the first match
- Returns an empty string if no nonce is found

The nonce is then applied to dynamically created script tags:

```javascript
/**
 * Get CSP nonce from existing script tags if available.
 * This helps comply with Content Security Policy when loading external scripts.
 */
function getCspNonce() {
    var existingScript = document.querySelector('script[nonce]');
    return existingScript ? existingScript.nonce || existingScript.getAttribute('nonce') : '';
}

// When creating a new script tag
var script = document.createElement('script');
script.src = 'https://www.paypal.com/sdk/js' + query;

// Add CSP nonce if available
var nonce = getCspNonce();
if (nonce) {
    script.setAttribute('nonce', nonce);
}
```

## Affected Components

The following components now support CSP nonces:

1. **Observer (auto.paypalrestful.php)**
   - PayPal SDK script tag (`<script src="https://www.paypal.com/sdk/js...">`)
   - Inline Pay Later messaging script tag

2. **Google Pay (jquery.paypalr.googlepay.js)**
   - PayPal SDK script (dynamically loaded)
   - Google Pay JS library script (pay.google.com)

3. **Apple Pay (jquery.paypalr.applepay.js)**
   - PayPal SDK script (dynamically loaded)
   - Apple Pay SDK script (applepay.cdn-apple.com)

4. **Venmo (jquery.paypalr.venmo.js)**
   - PayPal SDK script (dynamically loaded)

## Compatibility

### With CSP Enabled

When Zen Cart provides a `$GLOBALS['CSP_NONCE']` variable:
- All script tags automatically include the nonce attribute
- No CSP violations occur
- Scripts execute normally

### Without CSP

When CSP is not enabled or `$GLOBALS['CSP_NONCE']` is not set:
- Script tags are rendered without nonce attributes
- Module continues to work normally
- No changes to existing behavior

## Testing

A comprehensive test suite (`tests/CspNonceSupportTest.php`) verifies:

1. Observer checks for `$GLOBALS['CSP_NONCE']` and adds nonce to script tags
2. Observer sanitizes the nonce value with `htmlspecialchars()`
3. JavaScript files include `getCspNonce()` helper function
4. Dynamically created script tags use the nonce attribute
5. Nonce is propagated from existing script tags to new ones

To run the test:

```bash
php tests/CspNonceSupportTest.php
```

## Setting Up CSP in Zen Cart

If you want to enable CSP in your Zen Cart installation:

1. **Generate a nonce for each request** (in your main application initialization):
   ```php
   $GLOBALS['CSP_NONCE'] = bin2hex(random_bytes(16));
   ```

2. **Send the CSP header** (before any output):
   ```php
   header("Content-Security-Policy: script-src 'self' 'nonce-" . $GLOBALS['CSP_NONCE'] . "' https://www.paypal.com https://pay.google.com https://applepay.cdn-apple.com; object-src 'none'; base-uri 'self';");
   ```

   Or for report-only mode (recommended for testing):
   ```php
   header("Content-Security-Policy-Report-Only: script-src 'self' 'nonce-" . $GLOBALS['CSP_NONCE'] . "' https://www.paypal.com https://pay.google.com https://applepay.cdn-apple.com; object-src 'none'; base-uri 'self';");
   ```

3. **Add nonce to other scripts** in your templates:
   ```php
   <script nonce="<?= htmlspecialchars($GLOBALS['CSP_NONCE'], ENT_QUOTES, 'UTF-8') ?>">
       // Your inline JavaScript
   </script>
   ```

## Troubleshooting

### CSP Violation Errors Still Appear

1. **Check that nonce is being generated**: Verify `$GLOBALS['CSP_NONCE']` is set before the observer runs
2. **Verify nonce matches**: The nonce in the CSP header must match the nonce in script tags
3. **Check browser console**: Look for specific CSP violation messages
4. **Enable debug logging**: Set PayPal debugging to include logging and check for SDK configuration

### Scripts Not Loading

1. **Ensure CSP header allows required domains**:
   - `https://www.paypal.com` (PayPal SDK)
   - `https://pay.google.com` (Google Pay JS)
   - `https://applepay.cdn-apple.com` (Apple Pay SDK)

2. **Check for cached pages**: If using page caching, ensure the nonce is not cached

## Security Considerations

- **Nonce must be unique per request**: Never reuse the same nonce across multiple page loads
- **Nonce must be unpredictable**: Use `random_bytes()` or similar cryptographically secure random generator
- **Sanitize nonce output**: Always use `htmlspecialchars()` when outputting the nonce to prevent injection
- **Don't use `'unsafe-inline'`**: The nonce-based approach is more secure than allowing all inline scripts

## References

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [MDN: CSP script-src directive](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/script-src)
- [web.dev: Strict CSP guide](https://web.dev/articles/strict-csp)
- [CSP Cheat Sheet](https://scotthelme.co.uk/csp-cheat-sheet/)

## Version History

- **v1.3.2** (2025-01-06): Added CSP nonce support to resolve Content Security Policy violations
