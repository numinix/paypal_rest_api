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

/**
 * Returns a sanitized array representing the PayPal onboarding session.
 *
 * @return array<string, mixed>
 */
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
    if (!nxp_paypal_is_secure_request()) {
        nxp_paypal_json_error('SECURE transport is required for onboarding.');
    }

    if (!nxp_paypal_validate_origin()) {
        nxp_paypal_json_error('Request origin mismatch.');
    }

    $nonce = nxp_paypal_filter_string($_REQUEST['nonce'] ?? null);
    if (!nxp_paypal_validate_nonce($nonce)) {
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

    try {
        $response = nxp_paypal_onboarding_service()->start($payload);
    } catch (Throwable $exception) {
        $message = $exception->getMessage() ?: 'Unable to initiate onboarding.';
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

    $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
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

    if (isset($response['data']['credentials'])) {
        unset($response['data']['credentials']);
    }

    $context = is_array($response['data']) ? $response['data'] : [];
    $context['request'] = $payload;
    $context['response'] = $response;

    nxp_paypal_dispatch_event('finalize_success', $context);

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

    if (isset($response['data']['credentials'])) {
        unset($response['data']['credentials']);
    }

    $context = is_array($response['data']) ? $response['data'] : [];
    $context['request'] = $payload;
    $context['response'] = $response;

    nxp_paypal_dispatch_event('status_success', $context);

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

    if (function_exists('error_log')) {
        error_log('Numinix PayPal ISU event: ' . json_encode($payload));
    }
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
            'context_fields' => ['tracking_id', 'step', 'polling_interval', 'request', 'response'],
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
 * Sends a JSON error response and terminates execution.
 *
 * @param string $message
 * @param int    $statusCode
 * @return void
 */
function nxp_paypal_json_error(string $message, int $statusCode = 400): void
{
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
