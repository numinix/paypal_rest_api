<?php
/**
 * PayPal signup page controller responsible for orchestrating the onboarding
 * flow and communicating directly with the PayPal Partner APIs.
 */

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    // The public storefront does not define IS_ADMIN_FLAG; guard against
    // accidental execution in unsupported contexts.
    define('IS_ADMIN_FLAG', false);
}

$servicePath = __DIR__ . '/class.numinix_paypal_onboarding_service.php';
if (file_exists($servicePath)) {
    require_once $servicePath;
}

$helpersPath = __DIR__ . '/includes/nxp_paypal_helpers.php';
if (file_exists($helpersPath)) {
    require_once $helpersPath;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nxpPayPalSession = nxp_paypal_bootstrap_session();

if (!defined('META_TAG_TITLE')) {
    define('META_TAG_TITLE', 'PayPal Setup for Zen Cart | Numinix');
}

if (!defined('META_TAG_DESCRIPTION')) {
    define(
        'META_TAG_DESCRIPTION',
        'Launch PayPal in Zen Cart with Numinix—compliant configuration, fraud controls, dispute playbooks, and a faster go-live plan.'
    );
}

// Handle AJAX requests early and exit before rendering template content.
$requestedAction = nxp_paypal_detect_action();
if ($requestedAction !== null) {
    nxp_paypal_handle_ajax_action($requestedAction, $nxpPayPalSession);
    return;
}
