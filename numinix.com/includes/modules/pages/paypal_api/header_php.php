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

// Log incoming API request
if (function_exists('nxp_paypal_log_debug')) {
    nxp_paypal_log_debug('PayPal API endpoint called', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'action' => $_REQUEST['nxp_paypal_action'] ?? 'not provided',
        'has_nonce' => !empty($_REQUEST['nonce']),
        'has_tracking_id' => !empty($_REQUEST['tracking_id']),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    ]);
}

$nxpPayPalSession = nxp_paypal_bootstrap_session();

$requestedAction = nxp_paypal_detect_action();
if ($requestedAction === null) {
    if (function_exists('nxp_paypal_log_debug')) {
        nxp_paypal_log_debug('API request rejected: Missing nxp_paypal_action parameter', [
            'request_keys' => array_keys($_REQUEST),
        ]);
    }
    nxp_paypal_json_error('Missing nxp_paypal_action parameter.', 400);
}

nxp_paypal_handle_ajax_action($requestedAction, $nxpPayPalSession);
exit;
