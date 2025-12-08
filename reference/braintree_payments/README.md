# braintree_payments

## Debug logging

- Debug logs intentionally sanitize Braintree SDK objects to avoid writing raw credentials (e.g., private keys or configuration payloads) to disk. When adding new log statements, convert SDK responses into plain PHP arrays or strings first.
- Sensitive identifiers (such as merchant account IDs) should be masked before logging. The helper `mask_sensitive_value()` is available on `BraintreeCommon` to assist with this pattern.
