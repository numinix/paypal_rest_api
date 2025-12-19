# PayPal Signup Deployment & Support Guide

This guide documents the steps required to deploy the PayPal Signup module, configure it for merchants, and support post-launch operations.

## Deployment Checklist

1. **Pre-deployment validation**
   - Verify the Zen Cart storefront is in maintenance mode or otherwise ready for configuration changes.
   - Confirm backups exist for the database and `includes/configure.php`.
   - Ensure the target environment (sandbox or live) has the correct partner API credentials available.
2. **Installer execution**
   - Upload the plugin package to the Zen Cart root, preserving directory structure.
   - Visit the admin installer (`/management/index.php?cmd=install&module=paypal-isu`) and confirm the reported version matches the release.
   - Execute the installer and validate that configuration keys with the `NUMINIX_PPCP_` prefix are created or updated.
3. **Template asset publishing**
   - Clear template cache directories to remove stale `nmn` assets.
   - Deploy CSS and JS updates under `includes/templates/nmn/` and verify file permissions are writable by the web server.
   - Confirm the Zen Cart `logs/` directory allows write access so the plugin can append to `numinix_paypal_signup.log`.
   - Load the storefront PayPal signup page (`index.php?main_page=paypal_signup`) to ensure assets render with no 404 responses.
4. **Post-install verification**
   - Trigger a sandbox onboarding flow to confirm the direct PayPal Partner API redirects correctly and telemetry logging is captured.
   - Record deployment outcomes in change management system.
  - Inspect the admin **Configuration** and **Tools** menus to ensure the Numinix PayPal entries were registered. The
    configuration page is exposed via the `configNuminixPayPalIsu` key while the active management tool remains
    `toolsNuminixPaypalIsuSignupLink` in the `zen_admin_pages` table. Their menu text constants (`BOX_CONFIGURATION_NUMINIX_PAYPAL_ISU`
    and `BOX_NUMINIX_PAYPAL_SIGNUP_LINK`) are defined in `YOUR_ADMIN/includes/languages/english/extra_definitions/numinix_paypal_isu.php`,
    which Zen Cart loads on every admin request. The legacy `customerNuminixPaypalIsuSaveCreds` page was de-registered in v1.0.4.
  - For v1.0.2 and newer, open the **PayPal Signup Link Generator** page from the Tools menu and confirm the form at
    `management/paypal_request_signup_link.php` renders correctly.
   - Review the **Numinix PayPal Integrated Sign-up** configuration group to ensure the sandbox/live partner client ID and
     secret fields (`NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_ID`, `NUMINIX_PPCP_SANDBOX_PARTNER_CLIENT_SECRET`,
     `NUMINIX_PPCP_LIVE_PARTNER_CLIENT_ID`, `NUMINIX_PPCP_LIVE_PARTNER_CLIENT_SECRET`) are present.

## Merchant & Support Configuration Steps

1. **Environment selection**
   - In the admin settings, choose the desired environment (`sandbox` for testing, `live` for production). Document the choice in merchant records.
2. **Onboarding initiation**
   - Direct the merchant to the public PayPal signup page and instruct them to click **Get Started**. Confirm that pop-up blockers are disabled or a whitelisted exception is added.
3. **Completing PayPal flow**
   - Ensure the merchant signs in to PayPal using the appropriate business account and grants permissions when prompted.
   - Advise the merchant to keep the browser tab open until the success confirmation is displayed.
4. **Credential verification**
   - After onboarding completes, direct the merchant to PayPal &gt; Apps &amp; Credentials to retrieve the Client ID and Secret issued to their account.
   - Remind the merchant to record the Merchant ID and other identifiers displayed within PayPal’s dashboard for future reference.
   - If the PayPal dashboard does not show new credentials within one business day, escalate to PayPal Partner Support with the onboarding tracking ID.
5. **Support escalation**
   - Capture `tracking_id` and `env` values when escalating to Numinix support.
   - Reference telemetry logs (`logs/numinix_paypal_signup.log`) for correlation when troubleshooting.

## Rollback & Monitoring Guidance

1. **Rollback process**
   - Revert uploaded files using source control or backup archives, restoring the previous version of `includes/templates/nmn/` assets and PayPal module files.
   - Run the management installer rollback script (`/management/index.php?cmd=uninstall&module=paypal-isu`) if configuration removal is required.
   - Restore database snapshots taken prior to deployment if configuration entries need to be reset.
2. **Post-rollback validation**
   - Confirm that the PayPal signup page returns a 404 or the previous content as expected.
   - Validate that admin configuration keys prefixed with `NUMINIX_PPCP_` are removed or restored to prior values.
   - Verify that telemetry observers are disabled and no new signup events are logged to `logs/numinix_paypal_signup.log`.
3. **Monitoring**
   - Review web server error logs and Zen Cart `logs/` directory for onboarding-related warnings daily for the first week post-launch.
   - Monitor PayPal Partner status dashboards for API availability and latency.
   - Track merchant completion metrics and report anomalies to stakeholders within 24 hours.

