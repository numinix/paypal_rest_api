# Zen Cart v2 PayPal Signup Page — Task Plan

## 1. Project Foundations & Environment
- [x] Audit existing Zen Cart v2 storefront and confirm custom template `nmn` is active in public storefront.
- [x] Document current PayPal-related configurations and identify conflicts with planned onboarding features.
- [x] Configure environment toggles (sandbox/live) defaults and ensure HTTPS enforcement.
- [x] Define configuration keys (`NUMINIX_PPCP_*`) and create migration plan for installer versioning.

## 2. Installer & Upgrade Framework (`management/`)
- [x] Build auto-loader entries within `management/includes/auto_loaders/` to register new controllers and services.
- [x] Create installer/upgrade scripts in `management/includes/installers/numinix_paypal_isu/` that:
  - [x] Track plugin version in database using version file increments.
  - [x] Create/update configuration keys for environment selection, SSL enforcement, and partner credentials.
- [x] Implement upgrade logic to handle future schema/config changes idempotently.

## 3. Public Route Controller (`includes/modules/pages/paypal_signup/header_php.php`)
- [x] Parse query parameters (`env`, `step`, `code`, `tracking_id`) and initialize session state (`nxp_paypal.*`).
- [x] Validate HTTPS, origin, and CSRF nonce before initiating onboarding actions.
- [x] Implement handlers for start/finalize/status AJAX calls to the PayPal Partner APIs.
- [x] Integrate telemetry event dispatching for onboarding lifecycle stages.

## 4. Template & Assets (Template `nmn`)
- [x] Create `includes/templates/nmn/templates/tpl_paypal_signup_default.php` with stateful UI (Start, Waiting, Success, Error, Cancelled).
- [x] Develop accompanying CSS/JS assets under `includes/templates/nmn/css/` and `includes/templates/nmn/js/` for responsive layout, accessibility, and popup handling.
- [x] Ensure accessibility (ARIA live regions, focus management) and branding (PayPal marks, "Powered by Numinix", BN attribution).

## 5. Language Localization (`includes/languages/english/paypal_signup.php`)
- [x] Define strings for all UI states, buttons, instructions, and error messages.
- [x] Provide onboarding copy that explains popup flow, readiness steps, and how merchants retrieve credentials from PayPal.
- [x] Structure language file to support future translations and reuse in admin notices.

## 6. Backend Proxy Integration
- [x] Create service class to wrap HTTP calls to Numinix backend (`start`, `finalize`, `status`) with retry and rate-limiting safeguards.
- [x] Implement origin/tracking_id binding and error translation layer for UI messaging.
- [x] Provide polling mechanism that tracks onboarding progress until PayPal confirms completion.

## 7. Telemetry & Logging
- [x] Define event schema for onboarding lifecycle (`start`, `popup_opened`, `returned_from_paypal`, `finalize_success`, `finalize_failed`, `cancelled`).
- [x] Implement logging hooks/observer class (`includes/classes/observers/class.numinix_paypal_signup.php`) to capture events without sensitive data.
  - [x] Ensure logs include environment, Zen Cart version, and plugin version.

## 8. QA & Testing
- [x] Develop comprehensive test matrix covering direct PayPal Partner API flows across sandbox/live. See `docs/qa_testing.md`.
- [x] Simulate error cases (backend failure, expired code, popup blocked, non-SSL access) and verify UI responses. Documented in `docs/qa_testing.md`.
- [x] Conduct accessibility review (keyboard navigation, screen reader announcements). Findings summarized in `docs/qa_testing.md`.

## 9. Deployment & Documentation
- [x] Prepare deployment checklist for installer execution and template asset publishing.
- [x] Document configuration steps for merchants and support staff, including guidance on retrieving credentials from PayPal.
- [x] Provide rollback plan and monitoring guidance for post-launch support.
