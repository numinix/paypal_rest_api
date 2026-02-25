# Google Pay and Apple Pay Subscription Support Research

## Executive Summary

**Confirmed:** Both Google Pay and Apple Pay do support recurring payments/subscriptions through direct integration, but **NOT through PayPal's API**. The current PayPal Advanced Checkout integration routes these wallet payments through PayPal, which does not support vaulting for Google Pay or Apple Pay tokens.

To enable subscriptions with Google Pay and Apple Pay, a **separate direct integration** with these payment methods (bypassing PayPal) would be required.

---

## Research Findings

### Google Pay Subscription Support

**Status: Supported via Direct Integration (not via PayPal)**

Google Pay supports recurring payments through the following mechanism:

1. **Token-Based Recurring Billing**: Google Pay provides a tokenized payment credential (DPAN/FPAN) during the initial checkout
2. **Merchant/Gateway Managed Recurrence**: The merchant stores the token and uses their payment gateway to process subsequent charges
3. **No Direct Wallet Vaulting**: Google Pay itself doesn't store recurring billing agreements - this must be handled by the payment processor

**How It Works:**
- User authorizes initial payment via Google Pay
- Merchant receives a payment token (network token or FPAN depending on configuration)
- Merchant's payment gateway stores this token securely (PCI DSS compliant)
- Subsequent subscription charges use the stored token via the gateway's API
- Google Pay is NOT re-invoked for each recurring charge

**Requirements:**
- PCI DSS compliance for direct integration (or use a compliant gateway)
- Payment processor that supports Google Pay token storage for recurring billing
- Clear disclosure of subscription terms during checkout

**References:**
- [Google Pay API FAQ](https://developers.google.com/pay/api/faq)
- [Google Pay Web Overview](https://developers.google.com/pay/api/web/overview)
- [Worldpay Recurring with Google Pay](http://support.worldpay.com/support/CNP-API/content/recpaywithgoogle.htm)

---

### Apple Pay Subscription Support

**Status: Supported via Direct Integration (not via PayPal)**

Apple Pay has **explicit support for recurring payments** through the `ApplePayRecurringPaymentRequest` API:

1. **Native Recurring Payment Request**: The Apple Pay JS API includes `recurringPaymentRequest` property specifically for subscriptions
2. **Merchant Token (MPAN)**: Apple Pay can issue merchant-specific tokens that persist across device changes
3. **Payment Token Lifecycle**: Apple provides lifecycle notifications for token management

**How It Works:**
- Merchant initiates Apple Pay session with `recurringPaymentRequest` parameters
- Apple Pay displays subscription details to user (billing interval, amount, etc.)
- User authorizes with Face ID/Touch ID
- Apple issues a Merchant Token (MPAN) for recurring billing
- Subsequent charges use the stored MPAN via the payment processor

**Key API Properties:**
```javascript
recurringPaymentRequest: {
    paymentDescription: "Monthly Subscription",
    regularBilling: {
        amount: "19.99",
        label: "Monthly Fee",
        paymentTiming: "recurring",
        recurringPaymentIntervalUnit: "month",
        recurringPaymentIntervalCount: 1
    },
    managementURL: "https://example.com/manage-subscription"
}
```

**Requirements:**
- Apple Developer account and merchant registration
- Valid SSL certificate and domain verification
- Payment processor that supports Apple Pay merchant tokens
- Compliance with Apple Pay guidelines

**References:**
- [Apple Pay Recurring Payment Request](https://developer.apple.com/documentation/applepayontheweb/applepayrecurringpaymentrequest)
- [Stripe Apple Pay Recurring](https://docs.stripe.com/apple-pay/apple-pay-recurring)

---

## Why PayPal Integration Doesn't Support This

When Google Pay or Apple Pay is used through PayPal's Advanced Checkout:

1. **PayPal Acts as Intermediary**: The wallet token is sent to PayPal, not directly to the merchant
2. **No Token Storage**: PayPal processes the one-time transaction but does not store the wallet token for future use
3. **Vault API Limitations**: PayPal's Vault API supports vaulting credit cards and PayPal accounts, but NOT Google Pay or Apple Pay tokens

This is why the current implementation correctly disables these payment methods for subscription products - they cannot be used for recurring payments when routed through PayPal.

---

## Implementation Options

### Option 1: Direct Integration (Recommended for Full Support)

Implement Google Pay and Apple Pay directly (bypassing PayPal) with a compatible payment gateway.

**Pros:**
- Full subscription support
- Better authorization rates for recurring payments
- Native recurring payment UI (especially Apple Pay)

**Cons:**
- Requires additional payment gateway relationship (Stripe, Braintree, Adyen, etc.)
- More complex implementation
- Additional PCI compliance considerations
- Separate reconciliation from PayPal transactions

### Option 2: Hybrid Approach

Keep PayPal integration for one-time purchases, add separate direct integration for subscriptions.

**Pros:**
- Maintains existing PayPal functionality
- Adds subscription support incrementally
- Can use same gateway for both if supported

**Cons:**
- More complex checkout flow
- Must conditionally route based on cart contents
- Two payment processor relationships to maintain

### Option 3: Alternative Payment Methods Only

Continue using Credit Card (vaulted) and PayPal for subscriptions, with Google Pay/Apple Pay for one-time purchases only (current implementation).

**Pros:**
- No code changes required
- Simpler architecture
- Working solution for subscriptions via other methods

**Cons:**
- Limited payment options for subscription customers
- May reduce conversion for subscription products

---

## Implementation Steps (For Future Reference)

If Option 1 or Option 2 is chosen, the following steps would be required:

### Phase 1: Payment Gateway Selection

1. **Choose a Gateway** that supports:
   - Google Pay token storage for recurring billing
   - Apple Pay Merchant Tokens (MPAN)
   - Zen Cart/PHP integration
   
   Recommended options: Stripe, Braintree (PayPal owned), Adyen

2. **Obtain Credentials**:
   - Google Pay Merchant ID
   - Apple Pay Merchant Identity Certificate
   - Gateway API keys

### Phase 2: Direct Google Pay Integration

1. **Create new payment module** `paypalac_googlepay_direct.php` (or similar)
   - Separate from PayPal-routed version
   - Implements Google Pay Web API directly
   
2. **Implement token storage**:
   - On initial checkout, capture Google Pay token
   - Send to gateway for tokenization/storage
   - Store gateway's token reference in `paypal_vault` or new table
   
3. **Recurring billing flow**:
   - Cron job uses stored gateway token
   - Process via gateway's recurring payment API
   - Handle failures, retries, notifications

### Phase 3: Direct Apple Pay Integration

1. **Domain Verification**:
   - Register domain with Apple
   - Host `.well-known/apple-developer-merchantid-domain-association` file
   
2. **Create new payment module** `paypalac_applepay_direct.php`:
   - Implements Apple Pay JS API directly
   - Includes `recurringPaymentRequest` for subscriptions
   
3. **Merchant Token Management**:
   - Request MPAN during initial authorization
   - Store MPAN reference for recurring charges
   - Handle token lifecycle events (update, revoke)

### Phase 4: Checkout Flow Updates

1. **Conditional Module Selection**:
   - Detect subscription products in cart
   - Show direct integration modules instead of PayPal-routed versions
   - Or offer both with clear labeling
   
2. **Subscription Creation**:
   - Modify `auto.paypalacestful_recurring.php` observer
   - Support new token types from direct integrations
   - Route to appropriate recurring billing handler

### Phase 5: Testing & Compliance

1. **Sandbox Testing**:
   - Google Pay test cards
   - Apple Pay sandbox environment
   - Gateway sandbox/test mode
   
2. **PCI Compliance Review**:
   - Assess SAQ requirements
   - Document data handling
   - Gateway handles actual card data

---

## Estimated Development Effort

| Phase | Effort | Dependencies |
|-------|--------|--------------|
| Phase 1: Gateway Selection | 1-2 weeks | Business decision, contracts |
| Phase 2: Google Pay Direct | 3-4 weeks | Gateway SDK, testing |
| Phase 3: Apple Pay Direct | 3-4 weeks | Apple certificates, domain verification |
| Phase 4: Checkout Flow | 2-3 weeks | Module integration, UI updates |
| Phase 5: Testing | 2-3 weeks | All phases complete |

**Total: 11-16 weeks for full implementation**

---

## Conclusion

**Confirmation: Yes, Google Pay and Apple Pay DO support subscriptions through direct integration.**

However, this requires:
1. A separate payment gateway relationship (not routed through PayPal)
2. Significant development effort (11-16 weeks estimated)
3. Additional compliance and certification requirements
4. Ongoing maintenance of two payment processing paths

The current implementation correctly disables Google Pay and Apple Pay for subscription products because they cannot support recurring payments when used through PayPal's API. Enabling subscription support would require implementing a parallel direct integration path.

---

## Decision Required

Before proceeding with implementation, the following decisions need to be made:

1. **Which payment gateway to use** for direct integration?
2. **Is the development effort justified** by the expected increase in subscription conversions?
3. **Hybrid or full migration** - keep PayPal for one-time or migrate all?
4. **Timeline and resource allocation** for implementation

This document is provided for planning purposes only. No code changes have been made.
