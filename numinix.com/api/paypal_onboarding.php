<?php
/**
 * Standalone API endpoint for PayPal onboarding actions.
 *
 * This endpoint bypasses Zen Cart's standard page handling to avoid issues
 * with action/securityToken validation that would cause redirects when
 * called from external systems.
 *
 * Usage: POST to /api/paypal_onboarding.php with nxp_paypal_action parameter.
 */

declare(strict_types=1);

// Ensure we're not in admin mode for this API endpoint - must be set before application_top
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

// Bootstrap Zen Cart without triggering page-based action/security handling
require '../includes/configure.php';
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);

// Start session before application_top to ensure consistent session handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load application without page-specific processing
require_once 'includes/application_top.php';

// Load the onboarding service and helpers
$servicePath = DIR_WS_MODULES . 'pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
$helpersPath = DIR_WS_MODULES . 'pages/paypal_signup/includes/nxp_paypal_helpers.php';

$missingFiles = [];
if (!file_exists($servicePath)) {
    $missingFiles[] = 'onboarding service';
}
if (!file_exists($helpersPath)) {
    $missingFiles[] = 'helper functions';
}

if (!empty($missingFiles)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PayPal onboarding API is not properly configured. Missing: ' . implode(', ', $missingFiles),
    ]);
    exit;
}

require_once $servicePath;
require_once $helpersPath;

// Log incoming API request
if (function_exists('nxp_paypal_log_debug')) {
    nxp_paypal_log_debug('PayPal API standalone endpoint called', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'action' => $_REQUEST['nxp_paypal_action'] ?? 'not provided',
        'has_nonce' => !empty($_REQUEST['nonce']),
        'has_tracking_id' => !empty($_REQUEST['tracking_id']),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    ]);
}

if (!function_exists('nxp_paypal_bootstrap_session') || !function_exists('nxp_paypal_detect_action') || !function_exists('nxp_paypal_handle_ajax_action')) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PayPal onboarding API helper functions are not available.',
    ]);
    exit;
}

$nxpPayPalSession = nxp_paypal_bootstrap_session();

$requestedAction = nxp_paypal_detect_action();

// Handle PayPal return redirect (GET request after modal completion)
// PayPal sends parameters like merchantIdInPayPal, merchantId, permissionsGranted, consentStatus
if ($requestedAction === null && nxp_paypal_is_paypal_return_redirect()) {
    if (function_exists('nxp_paypal_log_debug')) {
        nxp_paypal_log_debug('PayPal return redirect detected, showing completion page', [
            'merchantIdInPayPal' => $_GET['merchantIdInPayPal'] ?? 'not provided',
            'permissionsGranted' => $_GET['permissionsGranted'] ?? 'not provided',
        ]);
    }
    nxp_paypal_show_completion_page();
    exit;
}

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
