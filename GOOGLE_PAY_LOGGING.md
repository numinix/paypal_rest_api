# Google Pay Console Logging Documentation

## Overview

Comprehensive console logging has been added to the Google Pay module to help debug payment flow issues. All log messages are prefixed with `[Google Pay]` for easy filtering.

## Log Messages by Flow Stage

### 1. Button Initialization

```javascript
[Google Pay] Starting button rendering
[Google Pay] Fetching wallet configuration
[Google Pay] Configuration loaded: {success: true, clientId: "...", ...}
[Google Pay] Environment: sandbox, Sandbox: true, Has merchant ID: true
[Google Pay] Loading PayPal SDK and Google Pay JS library
[Google Pay] SDKs loaded successfully
[Google Pay] Initializing PayPal Googlepay API
[Google Pay] Eligibility check passed
[Google Pay] Creating PaymentsClient with environment: TEST
[Google Pay] Checking if ready to pay with 1 payment methods
[Google Pay] isReadyToPay response: {result: true}
[Google Pay] Device is ready to pay, creating button
[Google Pay] Button rendered successfully
```

### 2. Payment Flow (When Button Clicked)

```javascript
[Google Pay] Button clicked, starting payment flow
[Google Pay] Getting base payment configuration from PayPal SDK
[Google Pay] Configuration valid, allowed payment methods: 1
[Google Pay] Step 1: Creating PayPal order to get actual amount
[Google Pay] fetchWalletOrder: Starting order creation request to ppr_wallet.php
[Google Pay] fetchWalletOrder: Received response after 245ms, status: 200
[Google Pay] fetchWalletOrder: Order creation completed after 250ms, data: {...}
[Google Pay] Order creation result: {success: true, orderID: "...", amount: "50.00", ...}
[Google Pay] Order validated - ID: 8XY123ABC, Amount: 50.00, Currency: USD
[Google Pay] Step 2: Requesting payment data from Google Pay, total: 50.00
[Google Pay] Payment data received from Google Pay sheet
[Google Pay] Step 3: Confirming order with PayPal, orderID: 8XY123ABC
[Google Pay] Step 4: Order confirmation result: {status: "APPROVED", ...}
[Google Pay] Order confirmed successfully, status: APPROVED
```

### 3. User Cancellation

```javascript
[Google Pay] Button clicked, starting payment flow
[Google Pay] Getting base payment configuration from PayPal SDK
[Google Pay] Configuration valid, allowed payment methods: 1
[Google Pay] Step 1: Creating PayPal order to get actual amount
[Google Pay] fetchWalletOrder: Order creation completed after 250ms
[Google Pay] Order validated - ID: 8XY123ABC, Amount: 50.00, Currency: USD
[Google Pay] Step 2: Requesting payment data from Google Pay, total: 50.00
[Google Pay] Payment cancelled by user
```

### 4. Error Scenarios

#### Configuration Error
```javascript
[Google Pay] Starting button rendering
[Google Pay] Fetching wallet configuration
[Google Pay] Unable to load configuration: {success: false, message: "..."}
```

#### Missing Payment Methods
```javascript
[Google Pay] Button clicked, starting payment flow
[Google Pay] Getting base payment configuration from PayPal SDK
[Google Pay] Configuration is missing allowedPaymentMethods
```

#### Order Creation Failed
```javascript
[Google Pay] Button clicked, starting payment flow
[Google Pay] Step 1: Creating PayPal order to get actual amount
[Google Pay] fetchWalletOrder: Unable to create Google Pay order after 5000ms
[Google Pay] Failed to create PayPal order: {success: false, ...}
```

#### Payment Error
```javascript
[Google Pay] Button clicked, starting payment flow
[Google Pay] Step 1: Creating PayPal order to get actual amount
[Google Pay] Order validated - ID: 8XY123ABC, Amount: 50.00, Currency: USD
[Google Pay] Step 2: Requesting payment data from Google Pay, total: 50.00
[Google Pay] Payment error occurred: {statusCode: "...", message: "..."}
```

## Debugging Tips

### 1. Filter Console Logs

In browser console, filter to see only Google Pay logs:
```javascript
// Filter: [Google Pay]
```

### 2. Check Order Creation Timing

Look for the timing in `fetchWalletOrder` logs:
```javascript
[Google Pay] fetchWalletOrder: Order creation completed after XXXms
```

If this takes more than 3-5 seconds, it may cause user gesture timeout issues.

### 3. Verify Configuration

Check that configuration is loaded successfully:
- `success: true` in configuration
- Valid `clientId`
- Correct `environment` (sandbox/production)
- Valid `googleMerchantId` (in production)

### 4. Check Eligibility

Ensure these checks pass:
```javascript
[Google Pay] Eligibility check passed
[Google Pay] isReadyToPay response: {result: true}
[Google Pay] Device is ready to pay
```

If any fail, the button won't render.

### 5. Track Payment Flow Steps

The payment flow is logged in 4 clear steps:
1. **Step 1**: Create PayPal order
2. **Step 2**: Request payment data from Google Pay
3. **Step 3**: Confirm order with PayPal
4. **Step 4**: Handle confirmation result

Look for all 4 steps completing successfully.

## Common Issues and Logs

### Issue: Button Doesn't Appear

**Check for:**
```javascript
[Google Pay] Button container not found
// OR
[Google Pay] Not eligible for this user/device
// OR
[Google Pay] Not ready to pay on this device
```

**Solution:**
- Ensure container `#paypalr-googlepay-button` exists
- Test on compatible device/browser
- Confirm PayPal credentials and environment are configured (Google Merchant ID is not required for the PayPal REST integration)

### Issue: Payment Fails After Sheet Opens

**Check for:**
```javascript
[Google Pay] Order validated - ID: ..., Amount: ...
[Google Pay] Step 2: Requesting payment data from Google Pay
[Google Pay] Payment error occurred: {...}
```

**Solution:**
- Check error message in logs
- Verify PayPal account configuration
- Ensure Google Pay is enabled in PayPal

### Issue: $0.00 Shown in Payment Sheet

**Check for:**
```javascript
[Google Pay] Order validated - ID: ..., Amount: 0.00
// OR
[Google Pay] Order created but amount is missing
```

**Solution:**
- Verify cart has items
- Check order total calculation
- Review PHP backend order creation

## Comparison with Apple Pay

Both modules now have consistent logging:

| Feature | Apple Pay | Google Pay |
|---------|-----------|------------|
| Log Prefix | `[Apple Pay]` | `[Google Pay]` |
| Timing Measurements | ✓ | ✓ |
| Step-by-Step Flow | ✓ | ✓ |
| Error Categorization | ✓ | ✓ |
| Configuration Logging | ✓ | ✓ |
| User Cancellation Detection | ✓ | ✓ |

## Performance Monitoring

Use the timing logs to monitor performance:

```javascript
// Good performance (< 500ms)
[Google Pay] fetchWalletOrder: Order creation completed after 245ms

// Acceptable performance (< 2000ms)
[Google Pay] fetchWalletOrder: Order creation completed after 1850ms

// Slow performance (> 2000ms) - may impact user experience
[Google Pay] fetchWalletOrder: Order creation completed after 3500ms
```

If order creation consistently takes > 2 seconds, consider:
- Optimizing server-side order creation
- Checking database performance
- Reviewing network latency
- Implementing caching where appropriate

## Integration with Error Reporting

These logs can be captured and sent to error reporting services:

```javascript
// Example: Capture Google Pay errors
window.addEventListener('console', function(event) {
  if (event.message.includes('[Google Pay]') && event.level === 'error') {
    // Send to error reporting service
    yourErrorReporter.log({
      type: 'google_pay_error',
      message: event.message,
      timestamp: new Date().toISOString()
    });
  }
});
```

## Next Steps

When you provide the Braintree Google Pay reference implementation, we can:
1. Compare logging patterns
2. Verify our flow matches proven implementations
3. Identify any missing logs or diagnostic information
4. Add additional debugging capabilities as needed
