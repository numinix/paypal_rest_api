# Project Foundations & Environment Audit

## Storefront Template Audit
- **Active Template:** Confirmed the storefront is serving the custom `nmn` template. Verification performed through the Zen Cart admin panel (`Configuration → Layout Settings → Template Directory`) and by observing rendered assets sourced from `includes/templates/nmn/` during front-end inspection.
- **Theme Integrity:** No overrides detected outside of the `nmn` directory. Key template files (`tpl_main_page.php`, `css/stylesheet.css`, JavaScript includes) load from `includes/templates/nmn/`, ensuring the onboarding page can depend on the custom theme assets.

## PayPal Configuration Snapshot
- **Current Modules:** Legacy PayPal Express (`MODULE_PAYMENT_PAYPAL_EXPRESS`) and Website Payments Pro (`MODULE_PAYMENT_PAYPALWPP`) modules are enabled. Both modules rely on classic API credentials and could conflict with the new PayPal Complete Payments onboarding if not isolated.
- **Identified Conflicts:** Existing settings rely on merchant-managed credentials and IPN URLs. The refreshed onboarding flow no longer writes merchant API credentials into Zen Cart, so legacy modules should keep their manual values and remain isolated from the new configuration keys.
- **Recommended Actions:** Document migration steps for merchants who will transition from classic modules to PPCP, including toggling legacy modules to `False` once their new PayPal accounts are approved.

## Environment Toggles & HTTPS Enforcement
- **Sandbox as Default:** Set sandbox as the default environment for new installations to protect merchants during initial onboarding. Production (live) activation should require explicit confirmation from the merchant via the onboarding UI.
- **Configuration Controls:** Introduce toggles `NUMINIX_PPCP_ENVIRONMENT` (`sandbox`/`live`) and `NUMINIX_PPCP_FORCE_SSL` (`true`/`false`) to manage environment selection and SSL enforcement. Tie SSL enforcement to Zen Cart's `ENABLE_SSL` flag and surface warnings in the onboarding UI when HTTPS is disabled.
- **HTTPS Enforcement:** Require HTTPS for all onboarding requests. Redirect non-SSL access to the HTTPS equivalent and block AJAX calls originating from insecure origins to prevent tampering.

## Configuration Keys & Installer Versioning Plan
- **Configuration Keys:**
  - `NUMINIX_PPCP_ENVIRONMENT`: current environment (`sandbox`|`live`).
  - `NUMINIX_PPCP_FORCE_SSL`: boolean for mandatory HTTPS on onboarding routes.
  - `NUMINIX_PPCP_VERSION`: stored plugin version used for upgrade routines.
  - `NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_ID` / `NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_SECRET`: sandbox partner credentials entered manually.
  - `NUMINIX_PPCP_LIVE_PARTNER_CLIENT_ID` / `NUMINIX_PPCP_LIVE_PARTNER_CLIENT_SECRET`: live partner credentials entered manually.
  - `NUMINIX_PPCP_PARTNER_REFERRAL_LINK`: cached partner referral URL generated via the admin tool.
- **Migration & Versioning:** Maintain versioned installer scripts under `management/includes/installers/numinix_paypal_isu/`. Installer increments the stored `NUMINIX_PPCP_VERSION` and applies configuration diffs idempotently. Future upgrades append migration scripts (e.g., `2024_01_00_add_telemetry_flags.php`) that check the persisted version before execution.
- **Rollback Considerations:** Ensure migrations create backups of modified configuration rows and provide an uninstall routine that removes `NUMINIX_PPCP_*` keys while leaving legacy PayPal modules untouched.

## Telemetry & Logging
- **Event capture:** The storefront controller dispatches `NOTIFY_NUMINIX_PAYPAL_ISU_EVENT` with normalized payloads for start, finalize, status, cancellation, and telemetry events. The observer defined in `includes/classes/observers/class.numinix_paypal_signup.php` writes structured JSON lines to the Zen Cart log directory.
- **Log location:** Verify that `DIR_FS_LOGS` (or the fallback `<catalog>/logs`) is writable; onboarding telemetry is appended to `numinix_paypal_signup.log` for operational review.
