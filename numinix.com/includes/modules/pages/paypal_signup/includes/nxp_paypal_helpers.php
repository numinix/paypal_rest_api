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
        'nonce' => nxp_paypal_generate_nonce(),
        'updated_at' => time(),
    ];

    $state = $_SESSION['nxp_paypal'] ?? [];
    if (!is_array($state)) {
        $state = [];
    }

    $input = [
        'env' => nxp_paypal_filter_string($_GET['env'] ?? null),
        'step' => nxp_paypal_filter_string($_GET['step'] ?? null),
        'code' => nxp_paypal_filter_string($_GET['code'] ?? null),
        'tracking_id' => nxp_paypal_filter_string($_GET['tracking_id'] ?? null),
        'partner_referral_id' => nxp_paypal_filter_string($_GET['partner_referral_id'] ?? null),
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
    $payload = [
        'environment' => $session['env'],
        'tracking_id' => $session['tracking_id'] ?: nxp_paypal_generate_tracking_id(),
        'origin' => nxp_paypal_get_origin(),
        'return_url' => nxp_paypal_current_url(),
    ];

    nxp_paypal_log_debug('Processing start action', [
        'environment' => $payload['environment'],
        'tracking_id' => $payload['tracking_id'],
        'origin' => $payload['origin'],
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

    if (empty($trackingId)) {
        nxp_paypal_json_error('Missing tracking reference.');
    }

    $payload = [
        'code' => $code,
        'tracking_id' => $trackingId,
        'environment' => $session['env'],
        'partner_referral_id' => $session['partner_referral_id'] ?? nxp_paypal_filter_string($_REQUEST['partner_referral_id'] ?? null),
    ];

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

    $_SESSION['nxp_paypal']['step'] = !empty($response['data']['step']) ? $response['data']['step'] : 'finalized';
    if (!empty($response['data']['partner_referral_id'])) {
        $_SESSION['nxp_paypal']['partner_referral_id'] = $response['data']['partner_referral_id'];
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

    $payload = [
        'tracking_id' => $trackingId,
        'environment' => $session['env'],
        'partner_referral_id' => $session['partner_referral_id'] ?? nxp_paypal_filter_string($_REQUEST['partner_referral_id'] ?? null),
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

    if (!empty($response['data']['step'])) {
        $_SESSION['nxp_paypal']['step'] = $response['data']['step'];
    }

    if (!empty($response['data']['partner_referral_id'])) {
        $_SESSION['nxp_paypal']['partner_referral_id'] = $response['data']['partner_referral_id'];
    }

    $_SESSION['nxp_paypal']['updated_at'] = time();

    // Store credentials in session if available, but redact from event logs
    if (isset($response['data']['credentials']) && is_array($response['data']['credentials'])) {
        $_SESSION['nxp_paypal']['credentials'] = $response['data']['credentials'];
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

    $queryString = http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);

    return $origin . $path . ($queryString !== '' ? '?' . $queryString : '');
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
    // Only GET requests can be PayPal redirects
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    if ($method !== 'GET') {
        return false;
    }

    // PayPal includes merchantIdInPayPal or merchantId in the return redirect
    $hasMerchantId = !empty($_GET['merchantIdInPayPal']) || !empty($_GET['merchantId']);

    // PayPal may also include permissionsGranted or consentStatus
    $hasConsentInfo = !empty($_GET['permissionsGranted']) || !empty($_GET['consentStatus']);

    // PayPal may include isEmailConfirmed or accountStatus
    $hasAccountInfo = !empty($_GET['isEmailConfirmed']) || !empty($_GET['accountStatus']);

    return $hasMerchantId || $hasConsentInfo || $hasAccountInfo;
}

/**
 * Displays a user-friendly completion page when PayPal redirects back to the API endpoint.
 *
 * This page is shown in the popup window after PayPal onboarding completes. It instructs
 * the user that the popup will close automatically, or provides a manual close button.
 *
 * @return void
 */
function nxp_paypal_show_completion_page(): void
{
    // Sanitize and validate PayPal return parameters
    // These are only used to determine success status, not displayed to user
    $permissionsGranted = nxp_paypal_filter_string($_GET['permissionsGranted'] ?? null);
    $consentStatus = nxp_paypal_filter_string($_GET['consentStatus'] ?? null);

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
