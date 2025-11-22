# QA & Testing Plan for PayPal Signup Flow

## Overview
This document captures the quality assurance scope for the PayPal signup experience delivered with the `nmn` template. It outlines the environments required for validation, the functional test matrix, failure simulations, and accessibility verification steps. The plan assumes that testers have access to both Zen Cart admin and storefront environments with sandbox and live PayPal partner credentials capable of calling the PayPal Partner APIs directly.

## Environments
- **Sandbox mode**: Zen Cart storefront configured to use sandbox partner credentials. HTTPS enforced via local reverse proxy or staging SSL.
- **Live mode (staging)**: Zen Cart storefront configured with live partner credentials routed to a controlled staging merchant account. HTTPS certificate must be valid.
- **Browsers**: Latest stable versions of Chrome, Firefox, Safari, and Edge on desktop; Chrome and Safari on mobile where feasible.

## Test Matrix
The following matrix covers sandbox and live onboarding flows executed directly against the PayPal Partner APIs.

| Scenario ID | Environment | Entry Point | Expected Outcome | Notes |
|-------------|-------------|-------------|------------------|-------|
| TM-01 | Sandbox | PayPal CTA on storefront signup page | Onboarding completes and success message displayed; tracking ID logged | Verify telemetry `finalize_success` event recorded |
| TM-02 | Live | PayPal CTA on storefront signup page | Merchant redirected to PayPal, completion confirmed, UI instructs merchant to obtain credentials from PayPal | Coordinate with live staging merchant account |
| TM-03 | Sandbox | Return to signup page with pending tracking ID | Page resumes waiting state and polls status until completion | Ensure poll interval respects backend guidance |
| TM-04 | Any | Popup opened from onboarding CTA | Popup opens with PayPal domain, CSRF nonce validated, no browser blocker issues | Works across major desktop browsers |

## Error Simulation & Resilience Checks
| Simulation ID | Trigger Method | Expected UI Response | Logging/Telemetry Verification |
|----------------|----------------|----------------------|-------------------------------|
| ER-01 | Force partner-referrals `start` call to return HTTP 500 via intercepted response | Error toast with retry option; onboarding halted | `finalize_failed` event with `start_http_500` reason |
| ER-02 | Use expired authorization `code` on finalize | UI transitions to error panel recommending restart | Log entry with `expired_code` and telemetry `finalize_failed` |
| ER-03 | Block popup via browser settings | Inline warning instructing to allow popups and retry | `popup_blocked` telemetry fired |
| ER-04 | Load page over HTTP | Redirect to HTTPS with warning banner | Server logs show redirect, no onboarding attempt |
| ER-05 | Simulate timeout on `status` poll | UI shows waiting state with countdown, eventually error prompt | Telemetry `status_timeout` and observer log entry |

### Execution Notes
- Simulations for backend failures and timeouts can be triggered by intercepting outbound PayPal API calls using tools such as Charles Proxy or browser DevTools to modify responses.
- Popup blocking can be tested by enabling the browser's popup blocker for the site prior to initiating onboarding.
- HTTP access validation requires temporarily disabling automatic HTTPS redirects at the reverse proxy to confirm the application-enforced redirect still triggers.

## Accessibility Review
- **Keyboard Navigation**: Tab through the entire signup page, ensuring focus order follows the visual layout. Confirm that CTA buttons, help links, and modal controls are accessible without a mouse.
- **Screen Reader Announcements**: Using NVDA (Windows) and VoiceOver (macOS), verify that state changes (Start, Waiting, Success, Error, Cancelled) announce via ARIA live regions and that instructions about retrieving credentials from PayPal are spoken clearly.
- **Color Contrast**: Validate text and interactive element contrast ratios meet WCAG AA standards using tools like axe or Lighthouse.
- **Popup Handling**: Confirm that focus returns to the initiating button when the popup closes, and that any error dialog is announced.
- **Responsive Behavior**: Inspect mobile view to ensure text reflows correctly and touch targets meet minimum size requirements.

## Reporting
- Document each test run in the QA tracker with scenario IDs, environment, browser, tester, date, and outcome.
- File defects referencing the relevant scenario or simulation ID, including network logs and screenshots when applicable.
- Summarize accessibility findings in a dedicated section of the release sign-off, including remediation status for any issues discovered.
