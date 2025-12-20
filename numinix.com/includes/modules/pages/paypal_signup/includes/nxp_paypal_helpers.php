<?php
/**
 * Shared helper functions for the Numinix PayPal onboarding endpoints.
 */

declare(strict_types=1);

function nxp_paypal_bootstrap_session(): array
{
    $defaults = [
        'env' => nxp_paypal_default_environment(),
        'step' => 'start',
        'code' => null,
        'tracking_id' => null,
        'partner_referral_id' => null,
        'merchant_id' => null,
        'auth_code' => null,
        'shared_id' => null,
        'nonce' => nxp_paypal_generate_nonce(),
        'updated_at' => time(),
    ];

    $state = $_SESSION['nxp_paypal'] ?? [];
    if (!is_array($state)) {
        $state = [];
    }

    $input = [
        'env' => nxp_paypal_filter_string($_REQUEST['env'] ?? null),
        'step' => nxp_paypal_filter_string($_REQUEST['step'] ?? null),
        'code' => nxp_paypal_filter_string($_REQUEST['code'] ?? null),
        'tracking_id' => nxp_paypal_filter_string($_REQUEST['tracking_id'] ?? null),
        'partner_referral_id' => nxp_paypal_filter_string($_REQUEST['partner_referral_id'] ?? null),
        'merchant_id' => nxp_paypal_filter_string(
            $_REQUEST['merchant_id']
            ?? $_REQUEST['merchantIdInPayPal']
            ?? $_REQUEST['merchantId']
            ?? null
        ),
        'auth_code' => nxp_paypal_filter_string($_REQUEST['authCode'] ?? $_REQUEST['auth_code'] ?? null),
        'shared_id' => nxp_paypal_filter_string($_REQUEST['sharedId'] ?? $_REQUEST['shared_id'] ?? null),
    ];

    $allowedEnvironments = ['sandbox', 'live'];
    if (!empty($input['env']) && in_array($input['env'], $allowedEnvironments, true)) {
        $state['env'] = $input['env'];
    }

    if (!empty($input['step'])) {
        $state['step'] = $input['step'];
    }

    if (!empty($input['code'])) {
        $state['code'] = $input['code'];
    }

    if (!empty($input['tracking_id'])) {
        $state['tracking_id'] = $input['tracking_id'];
    }

    if (!empty($input['partner_referral_id'])) {
        $state['partner_referral_id'] = $input['partner_referral_id'];
    }

    if (!empty($input['merchant_id'])) {
        $state['merchant_id'] = $input['merchant_id'];
    }

    if (!empty($input['auth_code'])) {
        $state['auth_code'] = $input['auth_code'];
    }

    if (!empty($input['shared_id'])) {
        $state['shared_id'] = $input['shared_id'];
    }

    $state = array_merge($defaults, $state);
    $state['updated_at'] = time();

    if (empty($state['nonce'])) {
        $state['nonce'] = nxp_paypal_generate_nonce();
    }

    $_SESSION['nxp_paypal'] = $state;

    return $state;
}

/**
 * Determines the requested AJAX action.
 *
 * @return string|null
 */
function nxp_paypal_detect_action(): ?string
{
    $action = $_REQUEST['nxp_paypal_action'] ?? null;
    if (!is_string($action) || $action === '') {
        return null;
    }

    return strtolower($action);
}

/**
 * Handles AJAX requests for the onboarding flow.
 *
 * @param string                 $action
 * @param array<string, mixed>   $session
 * @return void
 */
function nxp_paypal_handle_ajax_action(string $action, array $session): void
{
    nxp_paypal_log_debug('Received AJAX action request', [
        'action' => $action,
        'has_nonce' => !empty($_REQUEST['nonce']),
        'has_tracking_id' => !empty($_REQUEST['tracking_id'] ?? $session['tracking_id'] ?? null),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);

    if (!nxp_paypal_is_secure_request()) {
        nxp_paypal_log_debug('Request rejected: HTTPS required');
        nxp_paypal_json_error('SECURE transport is required for onboarding.');
    }

    // Origin validation - for API calls (proxy from external sites), origin may not match
    // We still validate for browser-based requests but allow API-style calls
    if (!nxp_paypal_validate_origin_for_action($action)) {
        nxp_paypal_log_debug('Request rejected: Origin validation failed', [
            'action' => $action,
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
            'host' => $_SERVER['HTTP_HOST'] ?? 'none',
        ]);
        nxp_paypal_json_error('Request origin mismatch.');
    }

    // Nonce validation - 'start' action allows empty nonce for new sessions
    // Other actions require a valid nonce from a prior start call
    $nonce = nxp_paypal_filter_string($_REQUEST['nonce'] ?? null);
    if (!nxp_paypal_validate_nonce_for_action($action, $nonce, $session)) {
        nxp_paypal_log_debug('Request rejected: Invalid session token', [
            'action' => $action,
            'nonce_provided' => $nonce !== null ? 'yes' : 'no',
            'session_has_nonce' => !empty($session['nonce']) ? 'yes' : 'no',
        ]);
        nxp_paypal_json_error('Invalid session token.');
    }

    switch ($action) {
        case 'start':
            nxp_paypal_dispatch_event('start', $session);
            nxp_paypal_handle_start($session);
            break;
        case 'finalize':
            nxp_paypal_handle_finalize($session);
            break;
        case 'status':
            nxp_paypal_handle_status($session);
            break;
        case 'telemetry':
            nxp_paypal_handle_telemetry($session);
            break;
        case 'lead':
            nxp_paypal_handle_lead($session);
            break;
        default:
            nxp_paypal_log_debug('Request rejected: Unsupported action', ['action' => $action]);
            nxp_paypal_json_error('Unsupported onboarding action.');
    }
}

/**
 * Handles storefront lead submissions.
 *
 * @param array<string, mixed> $session
 * @return void
 */
function nxp_paypal_handle_lead(array $session): void
{
    $name = nxp_paypal_filter_string($_POST['name'] ?? null);
    if ($name === null) {
        nxp_paypal_json_error('Please provide your name.');
    }

    $emailRaw = (string)($_POST['email'] ?? '');
    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        nxp_paypal_json_error('Please provide a valid email address.');
    }

    $storeUrlRaw = trim((string)($_POST['store_url'] ?? ''));
    if ($storeUrlRaw !== '' && !preg_match('/^https?:\/\//i', $storeUrlRaw)) {
        $storeUrlRaw = 'https://' . $storeUrlRaw;
    }
    $storeUrl = $storeUrlRaw !== '' ? filter_var($storeUrlRaw, FILTER_VALIDATE_URL) : null;
    if ($storeUrl === false || $storeUrl === null) {
        nxp_paypal_json_error('Please provide a valid store URL.');
    }

    $platform = nxp_paypal_filter_string($_POST['platform'] ?? null);
    if ($platform === null) {
        nxp_paypal_json_error('Please choose your platform.');
    }

    $notesRaw = trim((string)($_POST['notes'] ?? ''));
    $notesSanitized = strip_tags($notesRaw);
    $notesLength = function_exists('mb_strlen') ? mb_strlen($notesSanitized) : strlen($notesSanitized);
    if ($notesLength > 2000) {
        $notesSanitized = function_exists('mb_substr')
            ? mb_substr($notesSanitized, 0, 2000)
            : substr($notesSanitized, 0, 2000);
    }

    $consent = isset($_POST['consent']) && in_array((string)$_POST['consent'], ['yes', '1', 'true'], true);

    $context = [
        'name' => $name,
        'email' => $email,
        'store_url' => $storeUrl,
        'platform' => $platform,
        'consent' => $consent ? 'yes' : 'no',
    ];

    if ($notesSanitized !== '') {
        $context['notes'] = $notesSanitized;
    }

    nxp_paypal_dispatch_event('lead_submit', $context);

    $storeOwnerEmail = '';
    if (defined('STORE_OWNER_EMAIL_ADDRESS') && STORE_OWNER_EMAIL_ADDRESS !== '') {
        $storeOwnerEmail = (string)STORE_OWNER_EMAIL_ADDRESS;
    } elseif (function_exists('zen_get_configuration_key_value')) {
        $configEmail = zen_get_configuration_key_value('STORE_OWNER_EMAIL_ADDRESS');
        if (is_string($configEmail) && $configEmail !== '') {
            $storeOwnerEmail = $configEmail;
        }
    }

    $storeOwnerName = defined('STORE_NAME') && STORE_NAME !== ''
        ? (string)STORE_NAME
        : 'Store Owner';

    $fromEmail = defined('EMAIL_FROM') && EMAIL_FROM !== ''
        ? (string)EMAIL_FROM
        : $context['email'];

    $fromName = $storeOwnerName;

    $subject = 'New PayPal setup lead';

    $leadDetails = [
        'Name' => $context['name'],
        'Email' => $context['email'],
        'Store URL' => $context['store_url'],
        'Platform' => $context['platform'],
        'Notes' => $context['notes'] ?? 'N/A',
        'Consent' => $context['consent'],
    ];

    $plainBodyLines = [
        'A new PayPal setup lead was submitted:',
        '',
    ];

    foreach ($leadDetails as $label => $value) {
        $plainBodyLines[] = $label . ': ' . (string)$value;
    }

    $plainBody = implode("\n", $plainBodyLines);

    $htmlBody = '<p>A new PayPal setup lead was submitted:</p><ul>';
    foreach ($leadDetails as $label => $value) {
        $htmlBody .= '<li><strong>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . ':</strong> '
            . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $htmlBody .= '</ul>';

    if ($storeOwnerEmail !== '' && filter_var($storeOwnerEmail, FILTER_VALIDATE_EMAIL) && function_exists('zen_mail')) {
        try {
            $mailResult = zen_mail(
                $storeOwnerName,
                $storeOwnerEmail,
                $subject,
                $plainBody,
                $fromName,
                $fromEmail,
                'default',
                'default',
                '',
                $htmlBody
            );

            if ($mailResult === false) {
                error_log('Unable to send PayPal lead notification email: zen_mail returned false.');
            }
        } catch (Throwable $exception) {
            error_log('Unable to send PayPal lead notification email: ' . $exception->getMessage());
        }
    } else {
        error_log('Unable to send PayPal lead notification email: missing recipient or zen_mail().');
    }

    nxp_paypal_json_success([
        'message' => 'Thanks! We\'ll send your PayPal readiness checklist shortly.'
    ]);
}

/**
 * Initiates the onboarding flow with PayPal's Partner APIs.
 *
 * @param array<string, mixed> $session
 * @return void
 */
function nxp_paypal_handle_start(array $session): void
{
    // Ensure the generated tracking ID and environment are stored before building the return URL.
    // The completion page relies on these query parameters to persist merchant/auth codes when
    // the popup session is isolated from the admin session (e.g., cross-domain flows).
    if (empty($_SESSION['nxp_paypal']['tracking_id'])) {
        $_SESSION['nxp_paypal']['tracking_id'] = $session['tracking_id'] ?: nxp_paypal_generate_tracking_id();
    }
    if (empty($_SESSION['nxp_paypal']['env'])) {
        $_SESSION['nxp_paypal']['env'] = $session['env'];
    }

    // Check if client provided a return URL (for proxy requests from external admin panels)
    $clientReturnUrl = nxp_paypal_filter_string($_REQUEST['client_return_url'] ?? null);
    $returnUrl = $clientReturnUrl ?: nxp_paypal_current_url();
    
    // Validate and enhance the return URL
    if ($clientReturnUrl !== null) {
        // Validate URL to prevent open redirect vulnerabilities
        if (!nxp_paypal_validate_return_url($clientReturnUrl)) {
            nxp_paypal_log_debug('Client return URL validation failed', [
                'client_return_url' => $clientReturnUrl,
                'origin' => nxp_paypal_get_origin(),
            ]);
            nxp_paypal_json_error('Invalid return URL provided.');
        }
        
        // Add tracking_id and environment to return URL if not already present
        $returnUrl = nxp_paypal_enhance_return_url($clientReturnUrl, $_SESSION['nxp_paypal']['tracking_id'], $session['env']);
        
        nxp_paypal_log_debug('Using client-provided return URL', [
            'client_return_url' => $clientReturnUrl,
            'enhanced_return_url' => $returnUrl,
        ]);
    }

    $payload = [
        'environment' => $session['env'],
        'tracking_id' => $_SESSION['nxp_paypal']['tracking_id'],
        'origin' => nxp_paypal_get_origin(),
        'return_url' => $returnUrl,
    ];

    nxp_paypal_log_debug('Processing start action', [
        'environment' => $payload['environment'],
        'tracking_id' => $payload['tracking_id'],
        'origin' => $payload['origin'],
        'return_url' => $payload['return_url'],
        'has_client_return_url' => $clientReturnUrl !== null,
    ]);

    try {
        $response = nxp_paypal_onboarding_service()->start($payload);
    } catch (Throwable $exception) {
        $message = $exception->getMessage() ?: 'Unable to initiate onboarding.';
        nxp_paypal_log_debug('Start action failed with exception', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]);
        nxp_paypal_dispatch_event('start_failed', [
            'error' => $message,
            'request' => $payload,
            'response' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ],
        ]);
        nxp_paypal_json_error($message);
    }
    if (!$response['success']) {
        nxp_paypal_log_debug('Start action returned failure', [
            'message' => $response['message'] ?? 'Unknown error',
        ]);
        nxp_paypal_dispatch_event('start_failed', [
            'error' => $response['message'],
            'request' => $payload,
            'response' => $response,
        ]);
        nxp_paypal_json_error($response['message']);
    }

    $_SESSION['nxp_paypal']['tracking_id'] = $response['data']['tracking_id'] ?? $payload['tracking_id'];
    if (!empty($response['data']['partner_referral_id'])) {
        $_SESSION['nxp_paypal']['partner_referral_id'] = $response['data']['partner_referral_id'];
    }
    if (!empty($response['data']['seller_nonce'])) {
        $_SESSION['nxp_paypal']['seller_nonce'] = $response['data']['seller_nonce'];
    }
    $_SESSION['nxp_paypal']['step'] = !empty($response['data']['step']) ? $response['data']['step'] : 'waiting';
    $_SESSION['nxp_paypal']['updated_at'] = time();

    $context = is_array($response['data']) ? $response['data'] : [];
    $context['request'] = $payload;
    $context['response'] = $response;

    nxp_paypal_dispatch_event('start_success', $context);

    nxp_paypal_log_debug('Start action completed successfully', [
        'tracking_id' => $_SESSION['nxp_paypal']['tracking_id'],
        'has_redirect_url' => !empty($response['data']['redirect_url']),
    ]);

    $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
    // Include the nonce in the response for the client
    $responseData['nonce'] = $session['nonce'];
    nxp_paypal_json_success([
        'data' => $responseData,
    ]);
}

/**
 * Finalizes the onboarding flow using PayPal status checks.
 *
 * @param array<string, mixed> $session
 * @return void
 */
function nxp_paypal_handle_finalize(array $session): void
{
    $code = nxp_paypal_filter_string($_REQUEST['code'] ?? ($session['code'] ?? null));
    $trackingId = $session['tracking_id'] ?? nxp_paypal_filter_string($_REQUEST['tracking_id'] ?? null);
    $merchantId = nxp_paypal_filter_string(
        $_REQUEST['merchant_id']
        ?? $_REQUEST['merchantIdInPayPal']
        ?? $_REQUEST['merchantId']
        ?? ($session['merchant_id'] ?? null)
    );

    $authCode = nxp_paypal_filter_string(
        $_REQUEST['authCode']
        ?? $_REQUEST['auth_code']
        ?? ($session['auth_code'] ?? null)
    );

    $sharedId = nxp_paypal_filter_string(
        $_REQUEST['sharedId']
        ?? $_REQUEST['shared_id']
        ?? ($session['shared_id'] ?? null)
    );

    $sellerNonce = nxp_paypal_filter_string(
        $_REQUEST['seller_nonce']
        ?? $_REQUEST['sellerNonce']
        ?? ($session['seller_nonce'] ?? null)
    );

    if (empty($trackingId)) {
        nxp_paypal_json_error('Missing tracking reference.');
    }

    $payload = [
        'code' => $code,
        'tracking_id' => $trackingId,
        'environment' => $session['env'],
        'partner_referral_id' => $session['partner_referral_id'] ?? nxp_paypal_filter_string($_REQUEST['partner_referral_id'] ?? null),
        'merchant_id' => $merchantId,
        'auth_code' => $authCode,
        'shared_id' => $sharedId,
        'seller_nonce' => $sellerNonce,
    ];

    // Persist identifiers that arrive via finalize to make credential exchange resilient
    if ($merchantId !== null) {
        $_SESSION['nxp_paypal']['merchant_id'] = $merchantId;
        if (!empty($trackingId)) {
            nxp_paypal_persist_merchant_id($trackingId, $merchantId, $session['env'] ?? 'sandbox');
        }
    }

    if ($authCode !== null && $sharedId !== null && $trackingId !== null) {
        $_SESSION['nxp_paypal']['auth_code'] = $authCode;
        $_SESSION['nxp_paypal']['shared_id'] = $sharedId;
        nxp_paypal_persist_auth_code($trackingId, $authCode, $sharedId, $session['env'] ?? 'sandbox');
    }
    if ($sellerNonce !== null && $sellerNonce !== '') {
        $_SESSION['nxp_paypal']['seller_nonce'] = $sellerNonce;
    }

    try {
        $response = nxp_paypal_onboarding_service()->finalize($payload);
    } catch (Throwable $exception) {
        $message = $exception->getMessage() ?: 'Unable to finalize onboarding.';
        nxp_paypal_dispatch_event('finalize_failed', [
            'error' => $message,
            'request' => $payload,
            'response' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ],
        ]);
        nxp_paypal_json_error($message);
    }
    if (!$response['success']) {
        nxp_paypal_dispatch_event('finalize_failed', [
            'error' => $response['message'],
            'request' => $payload,
            'response' => $response,
        ]);
        nxp_paypal_json_error($response['message']);
    }

    // Persist credentials and seller token before exposing data to the client
    $sellerToken = $response['data']['seller_token'] ?? null;
    $credentials = $response['data']['credentials'] ?? null;
    if (is_array($credentials) && !empty($trackingId)) {
        nxp_paypal_persist_credentials(
            $trackingId,
            $credentials,
            is_array($sellerToken) ? $sellerToken : [],
            $session['env'] ?? 'sandbox'
        );
    }

    // Ensure seller_token is not returned to the browser
    if (isset($response['data']['seller_token'])) {
        unset($response['data']['seller_token']);
    }

    $_SESSION['nxp_paypal']['step'] = !empty($response['data']['step']) ? $response['data']['step'] : 'finalized';
    if (!empty($response['data']['partner_referral_id'])) {
        $_SESSION['nxp_paypal']['partner_referral_id'] = $response['data']['partner_referral_id'];
    }
    if (!empty($response['data']['merchant_id'])) {
        $_SESSION['nxp_paypal']['merchant_id'] = $response['data']['merchant_id'];
    } elseif (!empty($payload['merchant_id'])) {
        $_SESSION['nxp_paypal']['merchant_id'] = $payload['merchant_id'];
    }
    $_SESSION['nxp_paypal']['updated_at'] = time();

    // Store credentials in session if available
    $credentials = null;
    if (isset($response['data']['credentials']) && is_array($response['data']['credentials'])) {
        $credentials = $response['data']['credentials'];
        $_SESSION['nxp_paypal']['credentials'] = $credentials;
    }

    $redactedCredentials = nxp_paypal_redact_credentials($credentials);

    $context = is_array($response['data']) ? $response['data'] : [];
    $context['request'] = $payload;
    $context['response'] = $response;

    if ($redactedCredentials !== null) {
        $context['credentials'] = $redactedCredentials;
    }

    if (isset($context['response']['data']['credentials'])) {
        $context['response']['data']['credentials'] = $redactedCredentials;
    }

    nxp_paypal_dispatch_event('finalize_success', $context);

    // Return full response including credentials to the client
    $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
    nxp_paypal_json_success([
        'data' => $responseData,
    ]);
}

/**
 * Polls PayPal for onboarding status updates.
 *
 * @param array<string, mixed> $session
 * @return void
 */
function nxp_paypal_handle_status(array $session): void
{
    $trackingId = $session['tracking_id'] ?? nxp_paypal_filter_string($_REQUEST['tracking_id'] ?? null);
    if (empty($trackingId)) {
        nxp_paypal_json_error('Missing tracking reference.');
    }

    $merchantId = nxp_paypal_filter_string(
        $_REQUEST['merchant_id']
        ?? $_REQUEST['merchantIdInPayPal']
        ?? $_REQUEST['merchantId']
        ?? ($session['merchant_id'] ?? null)
    );

    // Extract authCode and sharedId from request (may come from postMessage)
    $authCode = nxp_paypal_filter_string(
        $_REQUEST['authCode']
        ?? $_REQUEST['auth_code']
        ?? ($session['auth_code'] ?? null)
    );
    $sharedId = nxp_paypal_filter_string(
        $_REQUEST['sharedId']
        ?? $_REQUEST['shared_id']
        ?? ($session['shared_id'] ?? null)
    );

    $sellerNonce = nxp_paypal_filter_string(
        $_REQUEST['seller_nonce']
        ?? $_REQUEST['sellerNonce']
        ?? ($session['seller_nonce'] ?? null)
    );

    // If merchant_id is not provided in the request or session, try to retrieve it
    // from the database. This handles the cross-session case where the PayPal redirect
    // (in the popup) stored the merchant_id but the status poll comes from a different session.
    if (empty($merchantId) && !empty($trackingId)) {
        $persistedMerchantId = nxp_paypal_retrieve_merchant_id($trackingId);
        if ($persistedMerchantId !== null) {
            $merchantId = $persistedMerchantId;
            nxp_paypal_log_debug('Retrieved merchant_id from database for status poll', [
                'tracking_id' => $trackingId,
                'merchant_id_prefix' => substr($merchantId, 0, 4) . '...',
            ]);
        }
    }

    // Persist merchant/authCode/sharedId data from the status request into the session and DB
    if (!empty($merchantId)) {
        $_SESSION['nxp_paypal']['merchant_id'] = $merchantId;
        if (!empty($trackingId)) {
            nxp_paypal_persist_merchant_id($trackingId, $merchantId, $session['env'] ?? 'sandbox');
        }
    }

    if (!empty($authCode) && !empty($sharedId) && !empty($trackingId)) {
        $_SESSION['nxp_paypal']['auth_code'] = $authCode;
        $_SESSION['nxp_paypal']['shared_id'] = $sharedId;
        nxp_paypal_persist_auth_code($trackingId, $authCode, $sharedId, $session['env'] ?? 'sandbox');
    }

    // If authCode and sharedId are not provided in the request, try to retrieve them
    // from the database. Per PayPal docs, these are needed to exchange for seller credentials.
    if ((empty($authCode) || empty($sharedId)) && !empty($trackingId)) {
        $persistedAuthData = nxp_paypal_retrieve_auth_code($trackingId);
        if ($persistedAuthData !== null) {
            $authCode = $persistedAuthData['auth_code'];
            $sharedId = $persistedAuthData['shared_id'];
            nxp_paypal_log_debug('Retrieved authCode and sharedId from database for credential exchange', [
                'tracking_id' => $trackingId,
            ]);
        }
    }

    $payload = [
        'tracking_id' => $trackingId,
        'environment' => $session['env'],
        'partner_referral_id' => $session['partner_referral_id'] ?? nxp_paypal_filter_string($_REQUEST['partner_referral_id'] ?? null),
        'merchant_id' => $merchantId,
        'auth_code' => $authCode,
        'shared_id' => $sharedId,
        'seller_nonce' => $sellerNonce,
    ];

    try {
        $response = nxp_paypal_onboarding_service()->status($payload);
    } catch (Throwable $exception) {
        $message = $exception->getMessage() ?: 'Unable to retrieve onboarding status.';
        nxp_paypal_dispatch_event('status_failed', [
            'error' => $message,
            'request' => $payload,
            'response' => [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ],
        ]);
        nxp_paypal_json_error($message);
    }
    if (!$response['success']) {
        nxp_paypal_dispatch_event('status_failed', [
            'error' => $response['message'],
            'request' => $payload,
            'response' => $response,
        ]);
        nxp_paypal_json_error($response['message']);
    }

    // Persist credentials and seller token before exposing data to the client
    $sellerToken = $response['data']['seller_token'] ?? null;
    $credentials = $response['data']['credentials'] ?? null;
    if (is_array($credentials) && !empty($trackingId)) {
        nxp_paypal_persist_credentials(
            $trackingId,
            $credentials,
            is_array($sellerToken) ? $sellerToken : [],
            $session['env'] ?? 'sandbox'
        );
    }

    // Ensure seller_token is not returned to the browser
    if (isset($response['data']['seller_token'])) {
        unset($response['data']['seller_token']);
    }

    if (!empty($response['data']['step'])) {
        $_SESSION['nxp_paypal']['step'] = $response['data']['step'];
    }

    if (!empty($response['data']['partner_referral_id'])) {
        $_SESSION['nxp_paypal']['partner_referral_id'] = $response['data']['partner_referral_id'];
    }

    if (!empty($response['data']['merchant_id'])) {
        $_SESSION['nxp_paypal']['merchant_id'] = $response['data']['merchant_id'];
    } elseif (!empty($payload['merchant_id'])) {
        $_SESSION['nxp_paypal']['merchant_id'] = $payload['merchant_id'];
    }

    $_SESSION['nxp_paypal']['updated_at'] = time();

    // Store credentials in session if available, but redact from event logs
    if (isset($response['data']['credentials']) && is_array($response['data']['credentials'])) {
        $_SESSION['nxp_paypal']['credentials'] = $response['data']['credentials'];

        // Clean up the tracking record after successful credential retrieval for security
        // This ensures sensitive data is not retained longer than necessary
        if (!empty($trackingId)) {
            nxp_paypal_delete_tracking_record($trackingId);
            nxp_paypal_log_debug('Deleted tracking record after successful credential retrieval', [
                'tracking_id' => $trackingId,
            ]);
        }
    }

    $context = is_array($response['data']) ? $response['data'] : [];
    $context['request'] = $payload;
    $context['response'] = $response;

    // Remove credentials from event logging context for security
    if (isset($context['credentials'])) {
        unset($context['credentials']);
    }
    if (isset($context['response']['data']['credentials'])) {
        unset($context['response']['data']['credentials']);
    }

    nxp_paypal_dispatch_event('status_success', $context);

    // Return full response including credentials to the client
    $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
    nxp_paypal_json_success([
        'data' => $responseData,
    ]);
}

/**
 * Records a front-end telemetry event.
 *
 * @param array<string, mixed> $session
 * @return void
 */
function nxp_paypal_handle_telemetry(array $session): void
{
    $event = nxp_paypal_filter_string($_REQUEST['event'] ?? null);
    if ($event === null) {
        nxp_paypal_json_error('Telemetry event missing.');
    }

    $eventKey = strtolower($event);
    $schema = nxp_paypal_event_schema();
    if (!isset($schema[$eventKey])) {
        nxp_paypal_json_error('Unsupported telemetry event.');
    }

    $rawContext = $_REQUEST['context'] ?? [];
    $context = [];

    if (is_string($rawContext) && $rawContext !== '') {
        $decoded = json_decode($rawContext, true);
        if (is_array($decoded)) {
            $context = $decoded;
        }
    } elseif (is_array($rawContext)) {
        $context = $rawContext;
    }

    nxp_paypal_dispatch_event($eventKey, is_array($context) ? $context : []);
    nxp_paypal_json_success(['event' => $eventKey]);
}

/**
 * Dispatches an onboarding telemetry event.
 *
 * @param string               $event
 * @param array<string, mixed> $context
 * @return void
 */
function nxp_paypal_dispatch_event(string $event, array $context = []): void
{
    global $zco_notifier;

    $payload = nxp_paypal_normalize_event_payload($event, $context);

    if (isset($zco_notifier) && is_object($zco_notifier) && method_exists($zco_notifier, 'notify')) {
        $zco_notifier->notify('NOTIFY_NUMINIX_PAYPAL_ISU_EVENT', $payload);
    }

    nxp_paypal_log_debug('Numinix PayPal ISU event: ' . ($payload['event'] ?? 'event_type_missing'), $payload);
}

/**
 * Returns a redacted version of the credentials for logging.
 *
 * @param array<string, mixed>|null $credentials
 * @return array<string, string>|null
 */
function nxp_paypal_redact_credentials(?array $credentials): ?array
{
    if (empty($credentials)) {
        return null;
    }

    $masked = [];
    foreach ($credentials as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }

        $masked[$key] = nxp_paypal_mask_sensitive_string((string)$value);
    }

    return !empty($masked) ? $masked : null;
}

/**
 * Returns the event schema used for telemetry logging.
 *
 * @return array<string, array<string, mixed>>
 */
function nxp_paypal_event_schema(): array
{
    return [
        'start' => [
            'description' => 'Merchant initiated onboarding.',
            'context_fields' => ['tracking_id'],
        ],
        'start_success' => [
            'description' => 'Onboarding start call completed successfully.',
            'context_fields' => ['tracking_id', 'redirect_url', 'step', 'request', 'response'],
        ],
        'start_failed' => [
            'description' => 'Onboarding start call failed.',
            'context_fields' => ['error', 'request', 'response'],
        ],
        'popup_opened' => [
            'description' => 'Popup window was successfully opened in the browser.',
            'context_fields' => ['tracking_id', 'method'],
        ],
        'returned_from_paypal' => [
            'description' => 'Merchant returned from PayPal to finalize onboarding.',
            'context_fields' => ['tracking_id', 'method'],
        ],
        'finalize_success' => [
            'description' => 'Finalize call succeeded.',
            'context_fields' => ['tracking_id', 'step', 'polling_interval', 'request', 'response', 'credentials'],
        ],
        'finalize_failed' => [
            'description' => 'Finalize call failed.',
            'context_fields' => ['error', 'tracking_id', 'request', 'response'],
        ],
        'status_success' => [
            'description' => 'Status polling succeeded.',
            'context_fields' => ['tracking_id', 'step', 'polling_interval', 'request', 'response'],
        ],
        'status_failed' => [
            'description' => 'Status polling failed.',
            'context_fields' => ['error', 'tracking_id', 'request', 'response'],
        ],
        'cancelled' => [
            'description' => 'Merchant cancelled onboarding.',
            'context_fields' => ['tracking_id', 'reason'],
        ],
        'lead_submit' => [
            'description' => 'Storefront lead form submitted.',
            'context_fields' => ['name', 'email', 'store_url', 'platform', 'notes', 'consent'],
        ],
    ];
}

/**
 * Normalizes the event payload prior to logging.
 *
 * @param string               $event
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function nxp_paypal_normalize_event_payload(string $event, array $context = []): array
{
    $eventKey = strtolower($event);
    $schema = nxp_paypal_event_schema();
    $definition = $schema[$eventKey] ?? ['context_fields' => []];
    $allowedFields = (array)($definition['context_fields'] ?? []);

    $safeContext = nxp_paypal_normalize_log_context($context, $allowedFields);

    $session = $_SESSION['nxp_paypal'] ?? [];
    if (!is_array($session)) {
        $session = [];
    }

    $environment = isset($session['env']) ? (string)$session['env'] : nxp_paypal_default_environment();
    $trackingId = isset($session['tracking_id']) ? (string)$session['tracking_id'] : null;
    $step = isset($session['step']) ? (string)$session['step'] : null;

    $payload = [
        'event' => $eventKey,
        'timestamp' => time(),
        'environment' => $environment,
        'zen_cart_version' => nxp_paypal_detect_zen_cart_version(),
        'plugin_version' => nxp_paypal_detect_plugin_version(),
        'tracking_id' => $trackingId,
        'step' => $step,
        'context' => $safeContext,
    ];

    if (!isset($payload['tracking_id']) || $payload['tracking_id'] === null) {
        unset($payload['tracking_id']);
    }

    if (!isset($payload['step']) || $payload['step'] === null) {
        unset($payload['step']);
    }

    return $payload;
}

/**
 * Normalizes and redacts log context data before recording events.
 *
 * @param array<string, mixed> $context
 * @param array<int, string>   $allowedFields
 * @return array<string, mixed>
 */
function nxp_paypal_normalize_log_context(array $context, array $allowedFields): array
{
    $normalized = [];

    foreach ($context as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        if (!empty($allowedFields) && !in_array($key, $allowedFields, true)) {
            continue;
        }

        $normalized[$key] = nxp_paypal_normalize_log_value($value, [strtolower($key)]);
    }

    return $normalized;
}

/**
 * Recursively normalizes values for logging with redaction applied when needed.
 *
 * @param mixed                 $value
 * @param array<int, string>    $path
 * @return mixed
 */
function nxp_paypal_normalize_log_value($value, array $path = [])
{
    if ($value instanceof DateTimeInterface) {
        return $value->format(DateTime::ATOM);
    }

    if ($value instanceof \JsonSerializable) {
        return nxp_paypal_normalize_log_value($value->jsonSerialize(), $path);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_int($key) && !is_string($key)) {
                continue;
            }

            $childPath = $path;
            $childPath[] = is_string($key) ? strtolower((string)$key) : (string)$key;

            $normalized[$key] = nxp_paypal_normalize_log_value($item, $childPath);
        }

        return $normalized;
    }

    if (is_object($value)) {
        if (method_exists($value, '__toString')) {
            $value = (string)$value;
        } else {
            $value = get_class($value);
        }
    }

    if (is_string($value)) {
        if (nxp_paypal_should_redact_log_path($path)) {
            return nxp_paypal_mask_sensitive_string($value);
        }

        return $value;
    }

    if (is_int($value) || is_float($value) || $value === null) {
        return $value;
    }

    return (string)$value;
}

/**
 * Determines if a given key path should be redacted from logs.
 *
 * @param array<int, string> $path
 * @return bool
 */
function nxp_paypal_should_redact_log_path(array $path): bool
{
    if (empty($path)) {
        return false;
    }

    foreach ($path as $segment) {
        $segment = strtolower((string)$segment);

        if ($segment === 'authorization_url') {
            continue;
        }

        if (strpos($segment, 'client_secret') !== false
            || strpos($segment, 'clientid') !== false
            || strpos($segment, 'client_id') !== false
            || strpos($segment, 'merchantid') !== false
            || strpos($segment, 'merchant_id') !== false
            || strpos($segment, 'access_token') !== false
            || strpos($segment, 'refresh_token') !== false
            || strpos($segment, 'token') !== false
            || strpos($segment, 'secret') !== false
            || strpos($segment, 'password') !== false
            || strpos($segment, 'credential') !== false
            || strpos($segment, 'cookie') !== false
            || strpos($segment, 'signature') !== false
            || strpos($segment, 'jwt') !== false
            || ($segment === 'authorization')
        ) {
            return true;
        }

        if (strpos($segment, 'auth') !== false && strpos($segment, 'code') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Masks sensitive string content while retaining leading/trailing context.
 *
 * @param string $value
 * @return string
 */
function nxp_paypal_mask_sensitive_string(string $value): string
{
    $prefixes = ['Bearer ', 'Basic ', 'Digest ', 'Token '];
    foreach ($prefixes as $prefix) {
        if (stripos($value, $prefix) === 0) {
            $suffix = substr($value, strlen($prefix));
            return $prefix . nxp_paypal_mask_sensitive_string($suffix);
        }
    }

    $length = strlen($value);
    if ($length === 0) {
        return '';
    }

    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
}

/**
 * Detects the active Zen Cart version string.
 *
 * @return string|null
 */
function nxp_paypal_detect_zen_cart_version(): ?string
{
    if (defined('PROJECT_VERSION_MAJOR')) {
        $version = (string)PROJECT_VERSION_MAJOR;
        if (defined('PROJECT_VERSION_MINOR')) {
            $version .= '.' . (string)PROJECT_VERSION_MINOR;
        }
        if (defined('PROJECT_VERSION_PATCH1')) {
            $version .= '.' . (string)PROJECT_VERSION_PATCH1;
        }

        return trim($version, '.');
    }

    if (defined('PROJECT_VERSION')) {
        return (string)PROJECT_VERSION;
    }

    return null;
}

/**
 * Attempts to resolve the installed plugin version.
 *
 * @return string|null
 */
function nxp_paypal_detect_plugin_version(): ?string
{
    static $version;
    static $resolved = false;

    if ($resolved) {
        return $version;
    }

    $resolved = true;

    if (defined('NUMINIX_PPCP_VERSION')) {
        $version = (string)NUMINIX_PPCP_VERSION;
        return $version;
    }

    if (function_exists('zen_get_configuration_key_value')) {
        $value = zen_get_configuration_key_value('NUMINIX_PPCP_VERSION');
        if (is_string($value) && $value !== '') {
            $version = $value;
            return $version;
        }
    }

    if (defined('TABLE_CONFIGURATION')) {
        global $db;
        if (isset($db) && is_object($db) && method_exists($db, 'bindVars') && method_exists($db, 'Execute')) {
            $lookupSql = "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = :configKey LIMIT 1";
            $lookupSql = $db->bindVars($lookupSql, ':configKey', 'NUMINIX_PPCP_VERSION', 'string');
            $result = $db->Execute($lookupSql);
            if ($result && !$result->EOF && isset($result->fields['configuration_value'])) {
                $version = (string)$result->fields['configuration_value'];
                return $version;
            }
        }
    }

    $installerDirectory = __DIR__ . '/../../../../management/installers/numinix_paypal_isu';
    if (is_dir($installerDirectory)) {
        $installerFiles = glob($installerDirectory . '/*.php');
        if (is_array($installerFiles) && !empty($installerFiles)) {
            natsort($installerFiles);
            $latest = array_pop($installerFiles);
            if (is_string($latest)) {
                $detected = str_replace('_', '.', basename($latest, '.php'));
                if ($detected !== '') {
                    $version = $detected;
                }
            }
        }
    }

    return $version;
}

/**
 * Validates that requests are served over HTTPS when required.
 *
 * @return bool
 */
function nxp_paypal_is_secure_request(): bool
{
    $forceSsl = defined('NUMINIX_PPCP_FORCE_SSL') ? strtolower((string)NUMINIX_PPCP_FORCE_SSL) : 'true';
    if ($forceSsl !== 'true') {
        return true;
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

/**
 * Ensures the request originates from the same host.
 *
 * @return bool
 */
function nxp_paypal_validate_origin(): bool
{
    $allowedHost = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin === '') {
        $origin = $_SERVER['HTTP_REFERER'] ?? '';
    }

    if ($origin === '') {
        return true;
    }

    $parsed = parse_url($origin);
    if (!is_array($parsed) || empty($parsed['host'])) {
        return false;
    }

    return strtolower($parsed['host']) === strtolower((string)$allowedHost);
}

/**
 * Validates the provided CSRF nonce against session state.
 *
 * @param string|null $nonce
 * @return bool
 */
function nxp_paypal_validate_nonce(?string $nonce): bool
{
    if (!is_string($nonce) || $nonce === '') {
        return false;
    }

    $session = $_SESSION['nxp_paypal']['nonce'] ?? '';
    return hash_equals((string)$session, $nonce);
}

/**
 * Validates origin for specific action types.
 * For API proxy calls (from external admin panels), we allow cross-origin requests
 * since they are authenticated via nonce exchange.
 *
 * @param string $action
 * @return bool
 */
function nxp_paypal_validate_origin_for_action(string $action): bool
{
    // 'start' action allows cross-origin since it initiates a new session
    // API proxy calls will always have a different origin
    if ($action === 'start') {
        return true;
    }

    // Check if this is an API proxy request (XHR from external admin panel)
    // These requests are authenticated via nonce and don't share session with numinix.com
    if (nxp_paypal_is_api_proxy_request()) {
        // For API proxy requests, we allow cross-origin since they're authenticated
        // via nonce validation which happens separately in nxp_paypal_validate_nonce_for_action
        return true;
    }
    
    // For browser-based requests, if we have a valid tracking_id in the request that matches
    // the session, we can trust the request came from a legitimate source
    $trackingId = nxp_paypal_filter_string($_REQUEST['tracking_id'] ?? null);
    $sessionTrackingId = $_SESSION['nxp_paypal']['tracking_id'] ?? null;
    
    if ($trackingId !== null && $sessionTrackingId !== null && $trackingId === $sessionTrackingId) {
        return true;
    }
    
    // Fall back to standard origin validation
    return nxp_paypal_validate_origin();
}

/**
 * Determines if the current request is an API proxy request from an external admin panel.
 *
 * API proxy requests are identified by:
 * - XMLHttpRequest header (X-Requested-With)
 * - POST method (API calls are always POST)
 * - Presence of action-related parameters (nonce, tracking_id)
 *
 * @return bool
 */
function nxp_paypal_is_api_proxy_request(): bool
{
    // Check for XMLHttpRequest header
    $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($xRequestedWith) !== 'xmlhttprequest') {
        return false;
    }

    // API proxy requests are POST
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    if ($method !== 'POST') {
        return false;
    }

    // API proxy requests should have nonce and/or tracking_id
    $hasNonce = !empty($_REQUEST['nonce']);
    $hasTrackingId = !empty($_REQUEST['tracking_id']);

    return $hasNonce || $hasTrackingId;
}

/**
 * Validates nonce based on the action being performed.
 * The 'start' action allows empty nonce for new sessions (generates a fresh one).
 * All other actions require a valid nonce from a prior 'start' call.
 *
 * @param string               $action
 * @param string|null          $nonce
 * @param array<string, mixed> $session
 * @return bool
 */
function nxp_paypal_validate_nonce_for_action(string $action, ?string $nonce, array $session): bool
{
    // For 'start' action, allow empty nonce - we'll generate a new one
    if ($action === 'start') {
        // If nonce is provided, validate it; if not, allow the request
        if ($nonce === null || $nonce === '') {
            return true;
        }
        // If nonce IS provided, it must match the session
        return nxp_paypal_validate_nonce($nonce);
    }

    // For API proxy requests, the nonce comes from the client which received it
    // from the 'start' action response. We need to validate it against the session
    // that was established during that 'start' call.
    // However, since each proxy request to numinix.com creates a new session,
    // we can't validate the nonce against session state.
    // Instead, we trust that the nonce was provided and is non-empty.
    // The real security is in the tracking_id which ties requests together.
    if (nxp_paypal_is_api_proxy_request()) {
        // For API proxy requests, just ensure a nonce was provided
        // The tracking_id provides the security binding between requests
        return $nonce !== null && $nonce !== '';
    }
    
    // For all other actions, require a valid nonce matching the session
    return nxp_paypal_validate_nonce($nonce);
}

/**
 * Logs debug information to the Zen Cart logs directory (Numinix side).
 *
 * @param string               $message
 * @param array<string, mixed> $context
 * @return void
 */
function nxp_paypal_log_debug(string $message, array $context = []): void
{
    $logFile = nxp_paypal_resolve_debug_log_file();
    if ($logFile === null) {
        // Fall back to error_log
        $logEntry = 'Numinix PayPal ISU: ' . $message;
        if (!empty($context)) {
            $sanitized = nxp_paypal_redact_log_context($context);
            $logEntry .= ' ' . json_encode($sanitized, JSON_UNESCAPED_SLASHES);
        }
        error_log($logEntry);
        return;
    }
    
    $timestamp = date('c');
    $logEntry = '[' . $timestamp . '] ' . $message;
    
    if (!empty($context)) {
        $sanitized = nxp_paypal_redact_log_context($context);
        $encoded = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $logEntry .= ' ' . $encoded;
        }
    }
    
    $directory = dirname($logFile);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
    }
    
    @file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Determines the debug log file path within the Zen Cart logs directory.
 *
 * @return string|null
 */
function nxp_paypal_resolve_debug_log_file(): ?string
{
    $baseDir = null;
    
    if (defined('DIR_FS_LOGS') && DIR_FS_LOGS !== '') {
        $baseDir = DIR_FS_LOGS;
    } elseif (defined('DIR_FS_CATALOG') && DIR_FS_CATALOG !== '') {
        $baseDir = rtrim(DIR_FS_CATALOG, '\\/') . '/logs';
    }
    
    if ($baseDir === null) {
        return null;
    }
    
    $baseDir = rtrim($baseDir, '\\/');
    return $baseDir . '/numinix_paypal_api_debug.log';
}

/**
 * Persists merchant_id keyed by tracking_id for cross-session retrieval.
 *
 * When PayPal redirects back to the completion page (in a popup), the merchant_id
 * is passed as a URL parameter. However, subsequent status polls come from the
 * store admin panel via proxy, which has a different PHP session. This function
 * persists the merchant_id to the database so it can be retrieved by the
 * tracking_id in subsequent status polls.
 *
 * Records expire after 1 hour and are cleaned up automatically.
 * Records are deleted after successful credential retrieval for security.
 *
 * @param string $trackingId
 * @param string $merchantId
 * @param string $environment
 * @return bool
 */
function nxp_paypal_persist_merchant_id(string $trackingId, string $merchantId, string $environment = 'sandbox'): bool
{
    if ($trackingId === '' || $merchantId === '') {
        return false;
    }

    // Validate tracking_id format (alphanumeric and dash only, max 64 chars)
    if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/', $trackingId)) {
        nxp_paypal_log_debug('Invalid tracking_id format for persistence', [
            'tracking_id_length' => strlen($trackingId),
        ]);
        return false;
    }

    // Validate merchant_id format (alphanumeric only, max 32 chars - PayPal merchant IDs are typically 13 chars)
    if (!preg_match('/^[A-Z0-9]{1,32}$/i', $merchantId)) {
        nxp_paypal_log_debug('Invalid merchant_id format for persistence', [
            'merchant_id_length' => strlen($merchantId),
        ]);
        return false;
    }

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        nxp_paypal_log_debug('Unable to persist merchant_id: database unavailable');
        return false;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        nxp_paypal_log_debug('Unable to persist merchant_id: tracking table not defined');
        return false;
    }

    // Clean up expired records first (older than 1 hour)
    nxp_paypal_cleanup_expired_tracking();

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

    try {
        // Check if record already exists for this tracking_id
        $checkSql = "SELECT id FROM " . $tableName . " WHERE tracking_id = :trackingId LIMIT 1";
        $checkSql = $db->bindVars($checkSql, ':trackingId', $trackingId, 'string');
        $result = $db->Execute($checkSql);

        if ($result && !$result->EOF) {
            // Update existing record
            $updateSql = "UPDATE " . $tableName . " SET "
                . "merchant_id = :merchantId, "
                . "environment = :environment, "
                . "expires_at = :expiresAt, "
                . "updated_at = NOW() "
                . "WHERE tracking_id = :trackingId";
            $updateSql = $db->bindVars($updateSql, ':merchantId', $merchantId, 'string');
            $updateSql = $db->bindVars($updateSql, ':environment', $environment, 'string');
            $updateSql = $db->bindVars($updateSql, ':expiresAt', $expiresAt, 'string');
            $updateSql = $db->bindVars($updateSql, ':trackingId', $trackingId, 'string');
            $db->Execute($updateSql);
        } else {
            // Insert new record
            $insertSql = "INSERT INTO " . $tableName . " "
                . "(tracking_id, merchant_id, environment, expires_at, created_at, updated_at) "
                . "VALUES (:trackingId, :merchantId, :environment, :expiresAt, NOW(), NOW())";
            $insertSql = $db->bindVars($insertSql, ':trackingId', $trackingId, 'string');
            $insertSql = $db->bindVars($insertSql, ':merchantId', $merchantId, 'string');
            $insertSql = $db->bindVars($insertSql, ':environment', $environment, 'string');
            $insertSql = $db->bindVars($insertSql, ':expiresAt', $expiresAt, 'string');
            $db->Execute($insertSql);
        }

        nxp_paypal_log_debug('Persisted merchant_id to database for cross-session retrieval', [
            'tracking_id' => $trackingId,
            'merchant_id_prefix' => substr($merchantId, 0, 4) . '...',
            'environment' => $environment,
            'expires_at' => $expiresAt,
        ]);

        return true;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to persist merchant_id to database', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Persists authCode and sharedId keyed by tracking_id for credential exchange.
 *
 * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
 * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
 * access token. Then, use this access token to get the seller's REST API credentials."
 * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
 *
 * This function stores these values so the status polling endpoint can retrieve and use
 * them to exchange for the seller's credentials via the PayPal OAuth2 token endpoint.
 *
 * Records expire after 1 hour and are cleaned up automatically.
 * Records are deleted after successful credential retrieval for security.
 *
 * @param string $trackingId
 * @param string $authCode
 * @param string $sharedId
 * @param string $environment
 * @return bool
 */
function nxp_paypal_persist_auth_code(string $trackingId, string $authCode, string $sharedId, string $environment = 'sandbox'): bool
{
    if ($trackingId === '' || $authCode === '' || $sharedId === '') {
        return false;
    }

    // Validate tracking_id format (alphanumeric and dash only, max 64 chars)
    if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/', $trackingId)) {
        nxp_paypal_log_debug('Invalid tracking_id format for auth code persistence', [
            'tracking_id_length' => strlen($trackingId),
        ]);
        return false;
    }

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        nxp_paypal_log_debug('Unable to persist auth code: database unavailable');
        return false;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        nxp_paypal_log_debug('Unable to persist auth code: tracking table not defined');
        return false;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

    try {
        // Check if record already exists for this tracking_id
        $checkSql = "SELECT id FROM " . $tableName . " WHERE tracking_id = :trackingId LIMIT 1";
        $checkSql = $db->bindVars($checkSql, ':trackingId', $trackingId, 'string');
        $result = $db->Execute($checkSql);

        if ($result && !$result->EOF) {
            // Update existing record
            $updateSql = "UPDATE " . $tableName . " SET "
                . "auth_code = :authCode, "
                . "shared_id = :sharedId, "
                . "environment = :environment, "
                . "expires_at = :expiresAt, "
                . "updated_at = NOW() "
                . "WHERE tracking_id = :trackingId";
            $updateSql = $db->bindVars($updateSql, ':authCode', $authCode, 'string');
            $updateSql = $db->bindVars($updateSql, ':sharedId', $sharedId, 'string');
            $updateSql = $db->bindVars($updateSql, ':environment', $environment, 'string');
            $updateSql = $db->bindVars($updateSql, ':expiresAt', $expiresAt, 'string');
            $updateSql = $db->bindVars($updateSql, ':trackingId', $trackingId, 'string');
            $db->Execute($updateSql);
        } else {
            // Insert new record
            $insertSql = "INSERT INTO " . $tableName . " "
                . "(tracking_id, auth_code, shared_id, environment, expires_at, created_at, updated_at) "
                . "VALUES (:trackingId, :authCode, :sharedId, :environment, :expiresAt, NOW(), NOW())";
            $insertSql = $db->bindVars($insertSql, ':trackingId', $trackingId, 'string');
            $insertSql = $db->bindVars($insertSql, ':authCode', $authCode, 'string');
            $insertSql = $db->bindVars($insertSql, ':sharedId', $sharedId, 'string');
            $insertSql = $db->bindVars($insertSql, ':environment', $environment, 'string');
            $insertSql = $db->bindVars($insertSql, ':expiresAt', $expiresAt, 'string');
            $db->Execute($insertSql);
        }

        nxp_paypal_log_debug('Persisted authCode and sharedId to database for credential exchange', [
            'tracking_id' => $trackingId,
            'environment' => $environment,
            'expires_at' => $expiresAt,
        ]);

        return true;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to persist auth code to database', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Persists seller access token and REST credentials so they can be forwarded to the store config.
 *
 * @param string               $trackingId
 * @param array<string, mixed> $credentials
 * @param array<string, mixed> $sellerToken
 * @param string               $environment
 * @return bool
 */
function nxp_paypal_persist_credentials(
    string $trackingId,
    array $credentials,
    array $sellerToken = [],
    string $environment = 'sandbox'
): bool {
    $clientId = isset($credentials['client_id']) ? (string)$credentials['client_id'] : '';
    $clientSecret = isset($credentials['client_secret']) ? (string)$credentials['client_secret'] : '';
    if ($trackingId === '' || $clientId === '' || $clientSecret === '') {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/', $trackingId)) {
        nxp_paypal_log_debug('Invalid tracking_id format for credential persistence', [
            'tracking_id_length' => strlen($trackingId),
        ]);
        return false;
    }

    $accessToken = isset($sellerToken['access_token']) ? (string)$sellerToken['access_token'] : '';
    $tokenExpiryTs = isset($sellerToken['access_token_expires_at'])
        ? (int)$sellerToken['access_token_expires_at']
        : 0;
    $tokenExpiresAt = $tokenExpiryTs > 0 ? date('Y-m-d H:i:s', $tokenExpiryTs) : null;

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        nxp_paypal_log_debug('Unable to persist seller credentials: database unavailable');
        return false;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        nxp_paypal_log_debug('Unable to persist seller credentials: tracking table not defined');
        return false;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    try {
        $updateSql = "UPDATE " . $tableName . " SET "
            . "seller_client_id = :clientId, "
            . "seller_client_secret = :clientSecret, "
            . "seller_access_token = :accessToken, "
            . "seller_access_token_expires_at = :tokenExpiresAt, "
            . "environment = :environment, "
            . "expires_at = :expiresAt, "
            . "updated_at = NOW() "
            . "WHERE tracking_id = :trackingId";

        $updateSql = $db->bindVars($updateSql, ':clientId', $clientId, 'string');
        $updateSql = $db->bindVars($updateSql, ':clientSecret', $clientSecret, 'string');
        $updateSql = $db->bindVars($updateSql, ':accessToken', $accessToken, 'string');
        $updateSql = $db->bindVars($updateSql, ':tokenExpiresAt', $tokenExpiresAt, 'string');
        $updateSql = $db->bindVars($updateSql, ':environment', $environment, 'string');
        $updateSql = $db->bindVars($updateSql, ':expiresAt', $expiresAt, 'string');
        $updateSql = $db->bindVars($updateSql, ':trackingId', $trackingId, 'string');
        $db->Execute($updateSql);

        nxp_paypal_log_debug('Persisted seller credentials for transmission', [
            'tracking_id' => $trackingId,
            'environment' => $environment,
            'client_id_prefix' => substr($clientId, 0, 6) . '...',
            'has_access_token' => $accessToken !== '' ? 'yes' : 'no',
        ]);

        return true;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to persist seller credentials', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Retrieves persisted authCode and sharedId by tracking_id from the database.
 *
 * @param string $trackingId
 * @return array{auth_code: string, shared_id: string}|null
 */
function nxp_paypal_retrieve_auth_code(string $trackingId): ?array
{
    if ($trackingId === '') {
        return null;
    }

    // Validate tracking_id format
    if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/', $trackingId)) {
        return null;
    }

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        return null;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        return null;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;

    try {
        $sql = "SELECT auth_code, shared_id, expires_at FROM " . $tableName 
            . " WHERE tracking_id = :trackingId LIMIT 1";
        $sql = $db->bindVars($sql, ':trackingId', $trackingId, 'string');
        $result = $db->Execute($sql);

        if (!$result || $result->EOF) {
            return null;
        }

        // Check expiry
        $currentTime = time();
        $expiresAt = strtotime($result->fields['expires_at']);
        if ($expiresAt !== false && $expiresAt < $currentTime) {
            // Expired - delete the record
            nxp_paypal_delete_tracking_record($trackingId);
            return null;
        }

        $authCode = (string)($result->fields['auth_code'] ?? '');
        $sharedId = (string)($result->fields['shared_id'] ?? '');
        
        if ($authCode === '' || $sharedId === '') {
            return null;
        }

        nxp_paypal_log_debug('Retrieved persisted authCode and sharedId from database', [
            'tracking_id' => $trackingId,
        ]);

        return [
            'auth_code' => $authCode,
            'shared_id' => $sharedId,
        ];
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to retrieve auth code from database', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}

/**
 * Retrieves a persisted merchant_id by tracking_id from the database.
 *
 * @param string $trackingId
 * @return string|null The merchant_id if found and not expired, null otherwise
 */
function nxp_paypal_retrieve_merchant_id(string $trackingId): ?string
{
    if ($trackingId === '') {
        return null;
    }

    // Validate tracking_id format
    if (!preg_match('/^[a-zA-Z0-9-]{1,64}$/', $trackingId)) {
        return null;
    }

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        return null;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        return null;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;

    try {
        $sql = "SELECT merchant_id, expires_at FROM " . $tableName 
            . " WHERE tracking_id = :trackingId LIMIT 1";
        $sql = $db->bindVars($sql, ':trackingId', $trackingId, 'string');
        $result = $db->Execute($sql);

        if (!$result || $result->EOF) {
            return null;
        }

        // Check expiry - cache current time for consistent comparison
        $currentTime = time();
        $expiresAt = strtotime($result->fields['expires_at']);
        if ($expiresAt !== false && $expiresAt < $currentTime) {
            // Expired - delete the record
            nxp_paypal_delete_tracking_record($trackingId);
            return null;
        }

        $merchantId = (string)$result->fields['merchant_id'];
        if ($merchantId === '') {
            return null;
        }

        nxp_paypal_log_debug('Retrieved persisted merchant_id from database', [
            'tracking_id' => $trackingId,
            'merchant_id_prefix' => substr($merchantId, 0, 4) . '...',
        ]);

        return $merchantId;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to retrieve merchant_id from database', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}

/**
 * Deletes a tracking record after successful credential retrieval.
 *
 * This should be called after credentials have been successfully retrieved
 * and saved to ensure sensitive data is not retained longer than necessary.
 *
 * @param string $trackingId
 * @return bool
 */
function nxp_paypal_delete_tracking_record(string $trackingId): bool
{
    if ($trackingId === '') {
        return false;
    }

    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        return false;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        return false;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;

    try {
        $sql = "DELETE FROM " . $tableName . " WHERE tracking_id = :trackingId";
        $sql = $db->bindVars($sql, ':trackingId', $trackingId, 'string');
        $db->Execute($sql);

        nxp_paypal_log_debug('Deleted tracking record after completion', [
            'tracking_id' => $trackingId,
        ]);

        return true;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to delete tracking record', [
            'tracking_id' => $trackingId,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Cleans up expired tracking records from the database.
 *
 * This is called automatically during persist operations to prevent
 * accumulation of stale data. Records older than 1 hour are removed.
 *
 * @return int Number of records deleted
 */
function nxp_paypal_cleanup_expired_tracking(): int
{
    global $db;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
        return 0;
    }

    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        return 0;
    }

    $tableName = TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING;

    try {
        $sql = "DELETE FROM " . $tableName . " WHERE expires_at < NOW()";
        $db->Execute($sql);

        // Get affected rows if available
        if (method_exists($db, 'affectedRows')) {
            $deleted = (int)$db->affectedRows();
            if ($deleted > 0) {
                nxp_paypal_log_debug('Cleaned up expired tracking records', [
                    'deleted_count' => $deleted,
                ]);
            }
            return $deleted;
        }

        return 0;
    } catch (Throwable $e) {
        nxp_paypal_log_debug('Failed to cleanup expired tracking records', [
            'error' => $e->getMessage(),
        ]);
        return 0;
    }
}

/**
 * Redacts sensitive values from context before logging.
 *
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function nxp_paypal_redact_log_context(array $context): array
{
    $sensitiveKeys = [
        'client_secret', 'secret', 'access_token', 'refresh_token',
        'authorization', 'password', 'securitytoken', 'nonce', 'credentials'
    ];
    
    $redacted = [];
    foreach ($context as $key => $value) {
        $lowerKey = is_string($key) ? strtolower($key) : '';
        if (in_array($lowerKey, $sensitiveKeys, true)) {
            $redacted[$key] = '[REDACTED]';
            continue;
        }
        if (is_array($value)) {
            $redacted[$key] = nxp_paypal_redact_log_context($value);
        } elseif (is_scalar($value) || $value === null) {
            $redacted[$key] = $value;
        } else {
            $redacted[$key] = (string) $value;
        }
    }
    return $redacted;
}

/**
 * Sends a JSON error response and terminates execution.
 *
 * @param string $message
 * @param int    $statusCode
 * @return void
 */
function nxp_paypal_json_error(string $message, int $statusCode = 400): void
{
    nxp_paypal_log_debug('Returning JSON error response', [
        'message' => $message,
        'status_code' => $statusCode,
    ]);
    nxp_paypal_json_response(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Sends a JSON success response and terminates execution.
 *
 * @param array<string, mixed> $payload
 * @return void
 */
function nxp_paypal_json_success(array $payload): void
{
    $payload['success'] = true;
    nxp_paypal_json_response($payload, 200);
}

/**
 * Outputs JSON response with appropriate headers.
 *
 * @param array<string, mixed> $payload
 * @param int                  $statusCode
 * @return void
 */
function nxp_paypal_json_response(array $payload, int $statusCode): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }

    echo json_encode($payload);
    exit;
}

/**
 * Returns the shared onboarding service instance.
 *
 * @return NuminixPaypalOnboardingService
 */
function nxp_paypal_onboarding_service(): NuminixPaypalOnboardingService
{
    static $service;

    if ($service instanceof NuminixPaypalOnboardingService) {
        return $service;
    }

    if (!class_exists('NuminixPaypalOnboardingService')) {
        throw new RuntimeException('PayPal onboarding service is unavailable.');
    }

    $service = new NuminixPaypalOnboardingService();

    return $service;
}

/**
 * Returns the default environment from configuration.
 *
 * @return string
 */
function nxp_paypal_default_environment(): string
{
    if (defined('NUMINIX_PPCP_ENVIRONMENT')) {
        $env = strtolower((string)NUMINIX_PPCP_ENVIRONMENT);
        if (in_array($env, ['sandbox', 'live'], true)) {
            return $env;
        }
    }

    return 'sandbox';
}

/**
 * Generates a CSRF nonce.
 *
 * @return string
 */
function nxp_paypal_generate_nonce(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Generates a tracking identifier.
 *
 * @return string
 */
function nxp_paypal_generate_tracking_id(): string
{
    return 'nxp-' . bin2hex(random_bytes(10));
}

/**
 * Returns the origin for the current request.
 *
 * @return string
 */
function nxp_paypal_get_origin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Builds the current request URL preserving whitelisted onboarding parameters.
 *
 * When building the return URL for PayPal onboarding, this function includes
 * the tracking_id and environment from the session if they're not already in
 * the URL query parameters. This is critical for cross-session merchant_id
 * persistence: when PayPal redirects back to this URL, the completion page
 * needs the tracking_id to associate the returned merchant_id with the
 * correct onboarding flow.
 *
 * @return string
 */
function nxp_paypal_current_url(): string
{
    $origin = nxp_paypal_get_origin();
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parsed = parse_url($uri);

    if ($parsed === false) {
        $parsed = [];
    }

    $path = isset($parsed['path']) && $parsed['path'] !== '' ? $parsed['path'] : '/';

    $queryParameters = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParameters);
    }

    $whitelisted = ['env' => ['sandbox', 'live']];
    $optionalKeys = ['step', 'tracking_id'];

    $filtered = ['main_page' => 'paypal_signup'];

    if (isset($queryParameters['main_page'])) {
        $mainPage = $queryParameters['main_page'];
        if (is_array($mainPage)) {
            $mainPage = reset($mainPage);
        }
        if (is_string($mainPage) && strtolower(trim($mainPage)) === 'paypal_signup') {
            $filtered['main_page'] = 'paypal_signup';
        }
    }

    foreach ($whitelisted as $key => $allowedValues) {
        if (!array_key_exists($key, $queryParameters)) {
            continue;
        }

        $value = $queryParameters[$key];
        if (is_array($value)) {
            $value = reset($value);
        }

        if (!is_string($value)) {
            continue;
        }

        $value = strtolower(trim($value));
        if (in_array($value, $allowedValues, true)) {
            $filtered[$key] = $value;
        }
    }

    foreach ($optionalKeys as $key) {
        if (!array_key_exists($key, $queryParameters)) {
            continue;
        }

        $value = $queryParameters[$key];
        if (is_array($value)) {
            $value = reset($value);
        }

        $sanitized = nxp_paypal_filter_string($value);
        if ($sanitized !== null) {
            $filtered[$key] = $sanitized;
        }
    }

    // Include tracking_id from session if not already in query parameters.
    // This is critical for the PayPal return redirect: when PayPal redirects back
    // to the completion page, we need the tracking_id in the URL so that the
    // merchant_id returned by PayPal can be associated with the correct flow
    // and persisted to the database for cross-session retrieval.
    if (!isset($filtered['tracking_id'])) {
        $sessionTrackingId = $_SESSION['nxp_paypal']['tracking_id'] ?? null;
        if (is_string($sessionTrackingId) && $sessionTrackingId !== '') {
            $sanitized = nxp_paypal_filter_string($sessionTrackingId);
            if ($sanitized !== null) {
                $filtered['tracking_id'] = $sanitized;
            }
        }
    }

    // Include environment from session if not already in query parameters.
    // This ensures the completion page knows which environment's credentials to persist.
    if (!isset($filtered['env'])) {
        $sessionEnv = $_SESSION['nxp_paypal']['env'] ?? null;
        if (is_string($sessionEnv) && in_array($sessionEnv, ['sandbox', 'live'], true)) {
            $filtered['env'] = $sessionEnv;
        }
    }

    $queryString = http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);

    return $origin . $path . ($queryString !== '' ? '?' . $queryString : '');
}

/**
 * Enhances a client-provided return URL with tracking parameters.
 *
 * @param string $clientReturnUrl The return URL from the client
 * @param string $trackingId The tracking ID for this onboarding session
 * @param string $environment The environment (sandbox or live)
 * @return string Enhanced return URL with tracking parameters
 */
function nxp_paypal_enhance_return_url(string $clientReturnUrl, string $trackingId, string $environment): string
{
    // Parse the URL
    $parsed = parse_url($clientReturnUrl);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        // Invalid URL, return as-is
        return $clientReturnUrl;
    }
    
    // Extract existing query parameters
    $queryParams = [];
    if (!empty($parsed['query'])) {
        // Decode HTML entities that may have been introduced during transport
        // This handles cases where & was encoded as &amp; in the query string
        $decodedQuery = html_entity_decode($parsed['query'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        parse_str($decodedQuery, $queryParams);
    }
    
    // Add tracking_id and env if not already present
    if (empty($queryParams['tracking_id']) && $trackingId !== '') {
        $queryParams['tracking_id'] = $trackingId;
    }
    if (empty($queryParams['env']) && in_array($environment, ['sandbox', 'live'], true)) {
        $queryParams['env'] = $environment;
    }
    
    // Rebuild the URL
    $url = $parsed['scheme'] . '://' . $parsed['host'];
    if (!empty($parsed['port'])) {
        $url .= ':' . $parsed['port'];
    }
    if (!empty($parsed['path'])) {
        $url .= $parsed['path'];
    }
    
    $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    if ($queryString !== '') {
        $url .= '?' . $queryString;
    }
    
    if (!empty($parsed['fragment'])) {
        $url .= '#' . $parsed['fragment'];
    }
    
    return $url;
}

/**
 * Validates a client-provided return URL to prevent open redirect vulnerabilities.
 *
 * @param string $returnUrl The return URL to validate
 * @return bool True if the URL is valid and safe, false otherwise
 */
function nxp_paypal_validate_return_url(string $returnUrl): bool
{
    // Parse the URL
    $parsed = parse_url($returnUrl);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return false;
    }
    
    // Only allow HTTPS (HTTP allowed for localhost development)
    $allowedSchemes = ['https'];
    $host = strtolower($parsed['host']);
    if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '.local') !== false) {
        $allowedSchemes[] = 'http';
    }
    
    if (!in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
        return false;
    }
    
    // Check against allowed domains/patterns
    // This can be configured via constant or database in production
    if (defined('NUMINIX_PPCP_ALLOWED_RETURN_DOMAINS')) {
        $allowedDomains = explode(',', NUMINIX_PPCP_ALLOWED_RETURN_DOMAINS);
        $allowed = false;
        foreach ($allowedDomains as $domain) {
            $domain = trim(strtolower($domain));
            if ($domain === '' || $domain === '*') {
                $allowed = true;
                break;
            }
            // Support wildcard subdomains (e.g., *.example.com)
            if (strpos($domain, '*.') === 0) {
                $baseDomain = substr($domain, 2);
                if ($host === $baseDomain || substr($host, -(strlen($baseDomain) + 1)) === '.' . $baseDomain) {
                    $allowed = true;
                    break;
                }
            } elseif ($host === $domain) {
                $allowed = true;
                break;
            }
        }
        
        return $allowed;
    }
    
    // If no specific whitelist is configured, allow any HTTPS URL
    // This maintains backward compatibility but should be configured in production
    return true;
}

/**
 * Sanitizes optional string input.
 *
 * @param mixed $value
 * @return string|null
 */
function nxp_paypal_filter_string($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    // FILTER_SANITIZE_STRING was deprecated in PHP 8.1; emulate its behaviour by
    // stripping HTML tags while preserving quotes and other printable
    // characters. Control characters are also removed to prevent log/header
    // injection issues.
    $value = strip_tags($value);

    // Remove ASCII control characters (except the common whitespace range).
    $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if ($sanitized === null) {
        // Fallback to the unfiltered value if PCRE encounters an error.
        $sanitized = $value;
    }

    $sanitized = trim($sanitized);

    return $sanitized === '' ? null : $sanitized;
}

/**
 * Detects if the current request is a PayPal return redirect after modal completion.
 *
 * When PayPal's onboarding modal completes, PayPal redirects the popup window to the
 * configured return_url with specific query parameters. This function identifies such
 * redirects by checking for PayPal-specific parameters.
 *
 * @return bool
 */
function nxp_paypal_is_paypal_return_redirect(): bool
{
    // PayPal typically performs a GET redirect, but some browsers/extensions may
    // convert the redirect into a POST. Accept both methods so we don't miss
    // the completion payload and lose authCode/sharedId.
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    if (!in_array($method, ['GET', 'POST'], true)) {
        return false;
    }

    $params = $_REQUEST;

    // PayPal includes merchantIdInPayPal or merchantId in the return redirect
    $hasMerchantId = !empty($params['merchantIdInPayPal']) || !empty($params['merchantId']);

    // PayPal may also include permissionsGranted or consentStatus
    $hasConsentInfo = !empty($params['permissionsGranted']) || !empty($params['consentStatus']);

    // PayPal may include isEmailConfirmed or accountStatus
    $hasAccountInfo = !empty($params['isEmailConfirmed']) || !empty($params['accountStatus']);

    // Per PayPal docs, authCode and sharedId are returned for credential exchange
    // See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
    $hasAuthCode = !empty($params['authCode']) && !empty($params['sharedId']);

    return $hasMerchantId || $hasConsentInfo || $hasAccountInfo || $hasAuthCode;
}

/**
 * Displays a user-friendly completion page when PayPal redirects back to the API endpoint.
 *
 * This page is shown in the popup window after PayPal onboarding completes. It instructs
 * the user that the popup will close automatically, or provides a manual close button.
 *
 * When the merchant_id is received from PayPal, it is persisted to the database so that
 * subsequent status polling requests (from a different session) can retrieve it.
 *
 * @return void
 */
function nxp_paypal_show_completion_page(): void
{
    // Sanitize and validate PayPal return parameters
    // These are only used to determine success status, not displayed to user
    $permissionsGranted = nxp_paypal_filter_string($_REQUEST['permissionsGranted'] ?? null);
    $consentStatus = nxp_paypal_filter_string($_REQUEST['consentStatus'] ?? null);

    // Extract merchant_id from PayPal return parameters - this is critical for credential retrieval
    $merchantId = nxp_paypal_filter_string(
        $_REQUEST['merchantIdInPayPal']
        ?? $_REQUEST['merchantId']
        ?? $_REQUEST['merchant_id']
        ?? null
    );

    // Extract authCode and sharedId from PayPal return parameters
    // Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
    // and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
    // access token. Then, use this access token to get the seller's REST API credentials."
    // See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
    $authCode = nxp_paypal_filter_string($_REQUEST['authCode'] ?? null);
    $sharedId = nxp_paypal_filter_string($_REQUEST['sharedId'] ?? null);

    // Extract tracking_id from session or URL parameters
    $trackingId = nxp_paypal_filter_string(
        $_REQUEST['tracking_id']
        ?? ($_SESSION['nxp_paypal']['tracking_id'] ?? null)
    );

    // Extract environment from session or URL parameters
    $environment = nxp_paypal_filter_string(
        $_REQUEST['env']
        ?? ($_SESSION['nxp_paypal']['env'] ?? null)
    );
    if ($environment === null || !in_array($environment, ['sandbox', 'live'], true)) {
        $environment = 'sandbox';
    }

    nxp_paypal_log_debug('PayPal return payload received', [
        'tracking_id' => $trackingId,
        'environment' => $environment,
        'has_merchant_id' => $merchantId !== null ? 'yes' : 'no',
        'has_auth_code' => $authCode !== null ? 'yes' : 'no',
        'has_shared_id' => $sharedId !== null ? 'yes' : 'no',
        'permissions_granted' => $permissionsGranted,
        'consent_status' => $consentStatus,
    ]);

    // Persist merchant_id to database for cross-session retrieval
    // This is critical because the status polling requests come from a different session
    if ($merchantId !== null && $trackingId !== null) {
        $persisted = nxp_paypal_persist_merchant_id($trackingId, $merchantId, $environment);
        nxp_paypal_log_debug('Completion page persisting merchant_id', [
            'tracking_id' => $trackingId,
            'merchant_id_prefix' => substr($merchantId, 0, 4) . '...',
            'environment' => $environment,
            'persisted' => $persisted ? 'yes' : 'no',
        ]);
    }

    // If we have authCode and sharedId, persist them for credential exchange
    // The status polling endpoint will use these to obtain the seller's REST API credentials
    if ($authCode !== null && $sharedId !== null && $trackingId !== null) {
        $persistedAuth = nxp_paypal_persist_auth_code($trackingId, $authCode, $sharedId, $environment);
        nxp_paypal_log_debug('Completion page persisting authCode and sharedId', [
            'tracking_id' => $trackingId,
            'has_auth_code' => 'yes',
            'shared_id_prefix' => substr($sharedId, 0, 4) . '...',
            'environment' => $environment,
            'persisted' => $persistedAuth ? 'yes' : 'no',
        ]);
    }

    $success = ($permissionsGranted !== null && strtolower($permissionsGranted) === 'true')
            || ($consentStatus !== null && strtolower($consentStatus) === 'true');

    // All output variables are hardcoded strings - not user-controlled
    if ($success) {
        $title = 'PayPal Setup Complete';
        $heading = '✓ PayPal Onboarding Complete';
        $message = 'Your PayPal account has been connected successfully. This window will close automatically.';
        $messageClass = 'success';
    } else {
        $title = 'PayPal Setup';
        $heading = 'PayPal Onboarding';
        $message = 'Please return to your admin panel to check the onboarding status. This window will close automatically.';
        $messageClass = 'info';
    }

    // Prepare data for postMessage to parent window
    // Only include sanitized, validated values
    $postMessageData = [
        'event' => 'paypal_onboarding_complete',
        'success' => $success,
        'permissionsGranted' => $permissionsGranted === 'true',
    ];
    if ($merchantId !== null) {
        $postMessageData['merchantId'] = $merchantId;
    }
    // Include authCode and sharedId for the parent window to use in credential exchange
    if ($authCode !== null) {
        $postMessageData['authCode'] = $authCode;
    }
    if ($sharedId !== null) {
        $postMessageData['sharedId'] = $sharedId;
    }
    $postMessageJson = json_encode($postMessageData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .icon.success { color: #22c55e; }
        .icon.info { color: #3b82f6; }
        h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 16px;
        }
        p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn:hover { background: #2563eb; }
        .countdown {
            color: #9ca3af;
            font-size: 14px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon <?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo $success ? '✓' : 'ℹ'; ?>
        </div>
        <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <button class="btn" onclick="window.close();">Close Window</button>
        <p class="countdown">Closing in <span id="timer">5</span> seconds...</p>
    </div>
    <script>
        (function() {
            // Send completion message to parent window (admin panel) with merchant ID
            // This allows the parent to include the merchant_id in subsequent status polls
            var messageData = <?php echo $postMessageJson; ?>;
            if (window.opener && !window.opener.closed) {
                try {
                    // Send to all origins since admin panel may be on different domain
                    window.opener.postMessage(messageData, '*');
                } catch(e) {
                    // Ignore cross-origin errors - parent will detect popup close
                }
            }

            var seconds = 5;
            var timer = document.getElementById('timer');
            var interval = setInterval(function() {
                seconds--;
                if (timer) timer.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    try { window.close(); } catch(e) {}
                }
            }, 1000);
        })();
    </script>
</body>
</html>
    <?php
}
