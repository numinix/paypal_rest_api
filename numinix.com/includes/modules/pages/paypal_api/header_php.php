<?php
/**
 * Lightweight API endpoint for PayPal onboarding actions.
 */

declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    // Ensure compatibility when executed from the storefront.
    define('IS_ADMIN_FLAG', false);
}

$servicePath = __DIR__ . '/../paypal_signup/class.numinix_paypal_onboarding_service.php';
if (file_exists($servicePath)) {
    require_once $servicePath;
}

$helpersPath = __DIR__ . '/../paypal_signup/includes/nxp_paypal_helpers.php';
if (file_exists($helpersPath)) {
    require_once $helpersPath;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nxpPayPalSession = nxp_paypal_bootstrap_session();

$requestedAction = nxp_paypal_detect_action();
if ($requestedAction === null) {
    nxp_paypal_json_error('Missing nxp_paypal_action parameter.', 400);
}

nxp_paypal_handle_ajax_action($requestedAction, $nxpPayPalSession);
exit;
