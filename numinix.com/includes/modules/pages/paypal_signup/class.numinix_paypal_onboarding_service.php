<?php
/**
 * Direct PayPal onboarding integration for the storefront flow.
 */

declare(strict_types=1);

$signupServicePath = dirname(__DIR__, 4) . '/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
if (file_exists($signupServicePath) && !class_exists('NuminixPaypalIsuSignupLinkService')) {
    require_once $signupServicePath;
}

if (!class_exists('NuminixPaypalIsuSignupLinkService')) {
    throw new RuntimeException('PayPal onboarding dependency missing.');
}

class NuminixPaypalOnboardingService extends NuminixPaypalIsuSignupLinkService
{
    private const DEFAULT_POLLING_INTERVAL_MS = 5000;

    /**
     * Initiates onboarding by creating a PayPal partner referral.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function start(array $payload): array
    {
        try {
            $options = [
                'environment' => $payload['environment'] ?? null,
                'tracking_id' => $payload['tracking_id'] ?? null,
                'return_url' => $payload['return_url'] ?? null,
            ];

            if (!empty($payload['origin'])) {
                $options['website_urls'] = [$payload['origin']];
            }

            $result = $this->request($options);
            $trackingId = (string)($result['tracking_id'] ?? ($payload['tracking_id'] ?? ''));

            $actionUrl = (string)($result['action_url'] ?? '');
            if ($actionUrl === '' && !empty($result['links']) && is_array($result['links'])) {
                foreach ($result['links'] as $link) {
                    if (is_array($link) && ($link['rel'] ?? '') === 'action_url' && !empty($link['href'])) {
                        $actionUrl = (string)$link['href'];
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'PayPal onboarding started.',
                'data' => [
                    'environment' => (string)($result['environment'] ?? $payload['environment'] ?? 'sandbox'),
                    'tracking_id' => $trackingId,
                    'partner_referral_id' => (string)($result['partner_referral_id'] ?? ''),
                    'seller_nonce' => (string)($result['seller_nonce'] ?? ''),
                    'redirect_url' => $actionUrl,
                    'action_url' => $actionUrl,
                    'links' => is_array($result['links'] ?? null) ? $result['links'] : [],
                    'step' => 'waiting',
                    'polling_interval' => self::DEFAULT_POLLING_INTERVAL_MS,
                ],
            ];
        } catch (Throwable $exception) {
            return $this->formatExceptionResponse($exception, 'Unable to initiate PayPal onboarding.');
        }
    }

    /**
     * Finalizes onboarding after the merchant returns from PayPal.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function finalize(array $payload): array
    {
        return $this->resolveStatus($payload, true);
    }

    /**
     * Polls the PayPal APIs for onboarding progress.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function status(array $payload): array
    {
        return $this->resolveStatus($payload, false);
    }

    /**
     * Normalizes status handling for finalize/status actions.
     *
     * @param array<string, mixed> $payload
     * @param bool                 $fromFinalize
     * @return array<string, mixed>
     */
    private function resolveStatus(array $payload, bool $fromFinalize): array
    {
        try {
            $environment = $this->normalizeEnvironment($payload['environment'] ?? null);
            $trackingId = $this->sanitizeTrackingId($payload['tracking_id'] ?? null);
            $partnerReferralId = $this->sanitizePartnerReferralId($payload['partner_referral_id'] ?? null);
            $merchantId = $this->sanitizeMerchantId($payload['merchant_id'] ?? null);
            $sellerNonce = $this->sanitizeSellerNonce($payload['seller_nonce'] ?? null);
            
            // Extract authCode and sharedId for credential exchange per PayPal docs
            $authCode = $this->sanitizeAuthCode($payload['auth_code'] ?? null);
            $sharedId = $this->sanitizeSharedId($payload['shared_id'] ?? null);
            $partnerMerchantId = $this->resolvePartnerMerchantId($environment, $payload);

            [$clientId, $clientSecret] = $this->resolveCredentials($environment, $payload);
            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('PayPal partner API credentials are required.');
            }

            $apiBase = $this->resolveApiBase($environment);
            $accessToken = $this->obtainAccessToken($apiBase, $clientId, $clientSecret);

            // Extract merchant credentials - try multiple methods:
            // 1. First, try to use authCode and sharedId to exchange for seller credentials (PayPal recommended flow)
            // 2. Fall back to merchant integration lookup and extract from oauth_integrations
            $credentials = null;
            $sellerToken = [];
            $integration = null;

            // Method 1: Exchange authCode + sharedId for seller credentials (PayPal recommended flow)
            // See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
            // When authCode and sharedId are available, skip merchant integration lookup and go straight to exchange
            if ($authCode !== '' && $sharedId !== '') {
                $this->logDebug('Attempting authCode/sharedId credential exchange', [
                    'tracking_id' => $trackingId,
                    'has_auth_code' => 'yes',
                    'has_shared_id' => 'yes',
                    'has_seller_nonce' => $sellerNonce !== '' ? 'yes' : 'no',
                ]);
                $credentials = $this->exchangeAuthCodeForCredentials(
                    $apiBase,
                    $clientId,
                    $clientSecret,
                    $authCode,
                    $sharedId,
                    $partnerMerchantId,
                    $sellerNonce
                );

                if (is_array($credentials) && isset($credentials['access_token'])) {
                    $sellerToken['access_token'] = (string)$credentials['access_token'];
                    unset($credentials['access_token']);
                }
                if (is_array($credentials) && isset($credentials['access_token_expires_at'])) {
                    $sellerToken['access_token_expires_at'] = (int)$credentials['access_token_expires_at'];
                    unset($credentials['access_token_expires_at']);
                }
            }

            // Method 2: Fall back to merchant integration lookup when authCode/sharedId not available
            if ($credentials === null) {
                $this->logDebug('AuthCode/sharedId not available; falling back to merchant integration lookup', [
                    'tracking_id' => $trackingId,
                    'has_auth_code' => $authCode !== '' ? 'yes' : 'no',
                    'has_shared_id' => $sharedId !== '' ? 'yes' : 'no',
                ]);

                $integration = $this->fetchMerchantIntegration(
                    $apiBase,
                    $accessToken,
                    $clientId,
                    $trackingId,
                    $partnerReferralId,
                    $merchantId,
                    $environment
                );

                if ($integration === null) {
                    return $this->waitingResponse($environment, $trackingId, $partnerReferralId, $merchantId);
                }

                $step = $this->mapIntegrationToStep($integration);
                if ($step === 'completed') {
                    $credentials = $this->extractMerchantCredentials($integration);
                }
            }

            // Build response data
            $data = [
                'environment' => $environment,
                'tracking_id' => $trackingId,
                'partner_referral_id' => $partnerReferralId,
                'merchant_id' => $integration !== null ? (string)($integration['merchant_id'] ?? '') : $merchantId,
                'merchant_id_in_paypal' => $integration !== null ? (string)($integration['merchant_id_in_paypal'] ?? ($integration['merchant_id'] ?? '')) : '',
                'payments_receivable' => $integration !== null ? (bool)($integration['payments_receivable'] ?? false) : false,
                'primary_email_confirmed' => $integration !== null ? (bool)($integration['primary_email_confirmed'] ?? false) : false,
                'capabilities' => $integration !== null ? $this->extractCapabilities($integration) : [],
                'step' => $credentials !== null ? 'completed' : ($integration !== null ? $this->mapIntegrationToStep($integration) : 'waiting'),
                'polling_interval' => self::DEFAULT_POLLING_INTERVAL_MS,
            ];

            if ($data['step'] === 'waiting') {
                $data['status_hint'] = 'provisioning';
            }

            if ($integration !== null && !empty($integration['links']) && is_array($integration['links'])) {
                $data['links'] = $integration['links'];
            }

            if ($credentials !== null) {
                $data['credentials'] = $credentials;
                if (!empty($sellerToken)) {
                    $data['seller_token'] = $sellerToken;
                }
            }

            return [
                'success' => true,
                'message' => $fromFinalize
                    ? 'PayPal onboarding progress updated.'
                    : 'PayPal onboarding status retrieved.',
                'data' => $data,
            ];
        } catch (Throwable $exception) {
            return $this->formatExceptionResponse(
                $exception,
                $fromFinalize
                    ? 'Unable to finalize PayPal onboarding.'
                    : 'Unable to retrieve PayPal onboarding status.'
            );
        }
    }

    /**
     * Exchanges authCode and sharedId for seller REST API credentials.
     *
     * Per PayPal docs: "When your seller completes the sign-up flow, PayPal returns an authCode
     * and sharedId to your seller's browser. Use the authCode and sharedId to get the seller's
     * access token. Then, use this access token to get the seller's REST API credentials."
     * See: https://developer.paypal.com/docs/multiparty/seller-onboarding/build-onboarding/
     *
     * @param string $apiBase
     * @param string $partnerClientId
     * @param string $partnerClientSecret
     * @param string $authCode
     * @param string $sharedId
     * @param string $partnerMerchantId
     * @param string $sellerNonce
     * @return array{
     *   client_id: string,
     *   client_secret: string,
     *   access_token?: string,
     *   access_token_expires_at?: int
     * }|null
     */
    private function exchangeAuthCodeForCredentials(
        string $apiBase,
        string $partnerClientId,
        string $partnerClientSecret,
        string $authCode,
        string $sharedId,
        string $partnerMerchantId,
        string $sellerNonce
    ): ?array {
        if ($authCode === '' || $sharedId === '') {
            return null;
        }

        // Step 1: Exchange authCode + sharedId for seller access token (ISU flow)
        $tokenUrl = rtrim($apiBase, '/') . '/v1/oauth2/token';
        $tokenHeaders = [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $tokenBody = http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $authCode,
            // IMPORTANT: for ISU, PayPal expects the seller_nonce (your single-use token)
            // as code_verifier in the token exchange.
            'code_verifier' => $sellerNonce,
        ], '', '&', PHP_QUERY_RFC3986);

        // IMPORTANT: for ISU, PayPal expects sharedId as the Basic auth username (password empty).
        $tokenResponse = $this->performHttpCall(
            'POST',
            $tokenUrl,
            $tokenHeaders,
            ['basic_auth' => $sharedId . ':'],
            $tokenBody
        );

        $tokenDecoded = $this->decodeJson($tokenResponse['body']);
        if ($tokenResponse['status'] < 200 || $tokenResponse['status'] >= 300) {
            $this->logDebug('Auth code exchange failed', [
                'status' => $tokenResponse['status'],
                'error' => $tokenDecoded['error'] ?? 'unknown',
                'error_description' => $tokenDecoded['error_description'] ?? '',
                'attempt' => 'isu_shared_id',
            ]);
            return null;
        }

        $sellerAccessToken = (string)($tokenDecoded['access_token'] ?? '');
        $sellerAccessTokenTtl = (int)($tokenDecoded['expires_in'] ?? 0);
        if ($sellerAccessToken === '') {
            $this->logDebug('Auth code exchange response missing access_token', [
                'response_keys' => array_keys($tokenDecoded),
            ]);
            return null;
        }

        // Step 2: Use seller access token to get REST API credentials
        // The seller's credentials are returned in the token response for third-party integrations
        $clientId = (string)($tokenDecoded['client_id'] ?? '');
        $clientSecret = (string)($tokenDecoded['client_secret'] ?? '');
        
        // If credentials weren't in the token response, try to get them via the credentials endpoint
        if (($clientId === '' || $clientSecret === '') && $partnerMerchantId !== '') {
            $credentialsUrl = rtrim($apiBase, '/') . '/v1/customer/partners/' . rawurlencode($partnerMerchantId) . '/merchant-integrations/credentials';
            $credentialsHeaders = [
                'Accept: application/json',
                'Authorization: Bearer ' . $sellerAccessToken,
            ];

            $credentialsResponse = $this->performHttpCall('GET', $credentialsUrl, $credentialsHeaders);
            $credentialsDecoded = $this->decodeJson($credentialsResponse['body']);

            if ($credentialsResponse['status'] >= 200 && $credentialsResponse['status'] < 300) {
                $clientId = (string)($credentialsDecoded['client_id'] ?? '');
                $clientSecret = (string)($credentialsDecoded['client_secret'] ?? '');
            } else {
                $this->logDebug('Credentials endpoint request failed', [
                    'status' => $credentialsResponse['status'],
                ]);
            }
        } elseif ($clientId === '' || $clientSecret === '') {
            $this->logDebug('Credentials endpoint skipped due to missing partner merchant ID', [
                'partnerMerchantId_present' => $partnerMerchantId !== '' ? 'yes' : 'no',
            ]);
        }

        if ($clientId !== '' && $clientSecret !== '') {
            $payload = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];

            if ($sellerAccessToken !== '') {
                $payload['access_token'] = $sellerAccessToken;
                if ($sellerAccessTokenTtl > 0) {
                    $payload['access_token_expires_at'] = time() + $sellerAccessTokenTtl;
                }
            }

            return $payload;
        }

        $this->logDebug('Auth code exchange completed but no credentials returned', [
            'attempt' => 'isu_shared_id',
        ]);

        return null;
    }

    /**
     * Sanitizes the authorization code.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeAuthCode($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * Sanitizes the shared ID.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeSharedId($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * Sanitizes the seller nonce used for PKCE code verification.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeSellerNonce($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * Resolves the configured PayPal partner merchant identifier.
     *
     * @param string                $environment
     * @param array<string, mixed>  $options
     * @return string
     */
    private function resolvePartnerMerchantId(string $environment, array $options): string
    {
        $partnerMerchantId = '';

        if (!empty($options['partner_merchant_id']) && is_string($options['partner_merchant_id'])) {
            $partnerMerchantId = trim($options['partner_merchant_id']);
        }

        if ($partnerMerchantId === '') {
            $suffix = strtoupper($environment);
            $envValue = getenv('PAYPAL_PARTNER_MERCHANT_ID_' . $suffix);
            if (is_string($envValue)) {
                $partnerMerchantId = trim($envValue);
            }
        }

        if ($partnerMerchantId === '') {
            $suffix = strtoupper($environment);
            $configValue = $this->getConfigurationValue('NUMINIX_PPCP_' . $suffix . '_PARTNER_MERCHANT_ID');
            if ($configValue !== null) {
                $partnerMerchantId = trim($configValue);
            }
        }

        if ($partnerMerchantId !== '') {
            $length = strlen($partnerMerchantId);
            if (!preg_match('/^[A-Za-z0-9]+$/', $partnerMerchantId) || $length < 10 || $length > 20) {
                $this->logDebug('Partner merchant ID format invalid', [
                    'length' => $length,
                ]);
                return '';
            }
        }

        return $partnerMerchantId;
    }

    /**
     * Returns a waiting response when PayPal has not yet provisioned the account.
     *
     * @param string $environment
     * @param string $trackingId
     * @param string $partnerReferralId
     * @return array<string, mixed>
     */
    private function waitingResponse(string $environment, string $trackingId, string $partnerReferralId, string $merchantId): array
    {
        return [
            'success' => true,
            'message' => 'Waiting for PayPal to finish provisioning the merchant account.',
            'data' => [
                'environment' => $environment,
                'tracking_id' => $trackingId,
                'partner_referral_id' => $partnerReferralId,
                'merchant_id' => $merchantId,
                'step' => 'waiting',
                'status_hint' => 'provisioning',
                'polling_interval' => self::DEFAULT_POLLING_INTERVAL_MS,
            ],
        ];
    }

    /**
     * Formats an exception into a response payload.
     *
     * @param Throwable $exception
     * @param string    $fallback
     * @return array<string, mixed>
     */
    private function formatExceptionResponse(Throwable $exception, string $fallback): array
    {
        $message = trim($exception->getMessage()) ?: $fallback;

        return [
            'success' => false,
            'message' => $message,
            'data' => [],
        ];
    }

    /**
     * Validates the tracking identifier.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeTrackingId($value): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        throw new RuntimeException('Tracking reference is required.');
    }

    /**
     * Normalizes the partner referral identifier.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizePartnerReferralId($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return $trimmed;
    }

    /**
     * Normalizes the merchant identifier supplied by PayPal.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeMerchantId($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return $trimmed;
    }

    /**
     * Resolves merchant integration details from PayPal.
     *
     * @param string $apiBase
     * @param string $accessToken
     * @param string $partnerClientId
     * @param string $trackingId
     * @param string $partnerReferralId
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegration(
        string $apiBase,
        string $accessToken,
        string $partnerClientId,
        string $trackingId,
        string $partnerReferralId,
        string $merchantId,
        string $environment
    ): ?array
    {
        $integration = null;
        $referral = null;

        $integration = $this->fetchMerchantIntegrationByTrackingId(
            $apiBase,
            $accessToken,
            $partnerClientId,
            $trackingId,
            $merchantId,
            $environment
        );

        if ($integration === null && $merchantId !== '') {
            $integration = $this->fetchMerchantIntegrationByMerchantId($apiBase, $accessToken, $partnerClientId, $merchantId, $environment);
        }

        if ($integration === null && $partnerReferralId !== '') {
            $referral = $this->fetchPartnerReferral($apiBase, $accessToken, $partnerReferralId);
            $integration = $this->extractMerchantIntegrationFromReferral($apiBase, $accessToken, $partnerClientId, $referral, $environment);
        }

        if ($integration !== null && empty($integration['environment'])) {
            $integration['environment'] = $environment;
        }

        // Sandbox flows sometimes omit oauth_integrations on the initial merchant integration lookup,
        // even though the account is already active. If we have a merchant identifier but no OAuth
        // details, perform a direct lookup to enrich the payload so credentials can be surfaced.
        if ($integration !== null
            && empty($integration['oauth_integrations'])
            && (!empty($integration['merchant_id']) || $merchantId !== '')
        ) {
            $enriched = $this->fetchMerchantIntegrationByMerchantId(
                $apiBase,
                $accessToken,
                $partnerClientId,
                (string) (!empty($integration['merchant_id']) ? $integration['merchant_id'] : $merchantId),
                $environment
            );

            if ($enriched !== null) {
                $integration = $enriched;
            }
        }

        return $integration;
    }

    /**
     * Retrieves partner referral details from PayPal.
     *
     * @param string $apiBase
     * @param string $accessToken
     * @param string $partnerReferralId
     * @return array<string, mixed>|null
     */
    private function fetchPartnerReferral(string $apiBase, string $accessToken, string $partnerReferralId): ?array
    {
        $url = rtrim($apiBase, '/') . '/v2/customer/partner-referrals/' . rawurlencode($partnerReferralId);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $response = $this->performHttpCall('GET', $url, $headers);
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] === 404) {
            return null;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $decoded;
        }

        $message = $this->resolveErrorMessage($decoded, 'Unable to retrieve PayPal referral status.');
        throw new RuntimeException($message);
    }

    /**
     * Derives merchant integration details using referral metadata when possible.
     *
     * @param string                     $apiBase
     * @param string                     $accessToken
     * @param string                     $partnerClientId
     * @param array<string, mixed>|null  $referral
     * @param string                     $environment
     * @return array<string, mixed>|null
     */
    private function extractMerchantIntegrationFromReferral(
        string $apiBase,
        string $accessToken,
        string $partnerClientId,
        ?array $referral,
        string $environment
    ): ?array
    {
        if (empty($referral)) {
            return null;
        }

        $merchantId = '';
        if (!empty($referral['merchant_id'])) {
            $merchantId = (string)$referral['merchant_id'];
        }

        if ($merchantId === '' && !empty($referral['links']) && is_array($referral['links'])) {
            foreach ($referral['links'] as $link) {
                if (!is_array($link)) {
                    continue;
                }

                if (($link['rel'] ?? '') === 'merchant_integration_details' && !empty($link['href'])) {
                    return $this->fetchIntegrationByUrl((string)$link['href'], $accessToken);
                }

                if ($merchantId === '' && ($link['rel'] ?? '') === 'self' && !empty($link['href'])) {
                    $query = parse_url((string)$link['href'], PHP_URL_QUERY);
                    if (is_string($query)) {
                        parse_str($query, $parts);
                        if (!empty($parts['merchant_id'])) {
                            $merchantId = (string)$parts['merchant_id'];
                        }
                    }
                }
            }
        }

        if ($merchantId !== '') {
            return $this->fetchMerchantIntegrationByMerchantId($apiBase, $accessToken, $partnerClientId, $merchantId, $environment);
        }

        return null;
    }

    /**
     * Performs an integration lookup using an absolute URL provided by PayPal.
     *
     * @param string $url
     * @param string $accessToken
     * @return array<string, mixed>|null
     */
    private function fetchIntegrationByUrl(string $url, string $accessToken): ?array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $response = $this->performHttpCall('GET', $url, $headers);
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] === 404) {
            return null;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return $decoded;
        }

        $message = $this->resolveErrorMessage($decoded, 'Unable to retrieve PayPal merchant integration details.');
        throw new RuntimeException($message);
    }

    /**
     * Fetches merchant integration details for a specific merchant identifier.
     *
     * Uses the standard partner merchant-integrations endpoint which requires
     * the partner's client ID in the URL path:
     * GET /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}
     *
     * @param string $apiBase
     * @param string $accessToken
     * @param string $partnerClientId
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegrationByMerchantId(
        string $apiBase,
        string $accessToken,
        string $partnerClientId,
        string $merchantId,
        string $environment
    ): ?array
    {
        // Validate partnerClientId - should only contain alphanumeric, underscore, dash (PayPal client IDs)
        if ($partnerClientId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $partnerClientId)) {
            $this->logDebug('Invalid partnerClientId format for merchant integration lookup', [
                'partnerClientId_length' => strlen($partnerClientId),
            ]);
            return null;
        }

        // Validate merchantId - should only contain alphanumeric characters (PayPal merchant IDs are typically 13 uppercase chars)
        if ($merchantId === '' || !preg_match('/^[A-Za-z0-9]+$/', $merchantId)) {
            $this->logDebug('Invalid merchantId format for merchant integration lookup', [
                'merchantId_length' => strlen($merchantId),
            ]);
            return null;
        }

        // Use the standard partner endpoint with the partner's client ID in the path
        // This is the correct endpoint for Partner Referrals API integrations
        // Endpoint: GET /v1/customer/partners/{partner_id}/merchant-integrations/{merchant_id}
        $url = rtrim($apiBase, '/') . '/v1/customer/partners/' . rawurlencode($partnerClientId) . '/merchant-integrations/' . rawurlencode($merchantId);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $response = $this->performHttpCall('GET', $url, $headers);
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] === 404) {
            // Merchant not found - return null so caller can try alternative methods
            return null;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            // Direct lookup returns a single merchant integration object, not a list
            return $this->normalizeMarketplaceIntegration($decoded, $environment);
        }

        // For errors, log and throw an exception with the PayPal error message
        $message = $this->resolveErrorMessage($decoded, 'Unable to retrieve PayPal merchant integration details.');
        $this->logDebug('Merchant integration lookup by merchant_id failed', [
            'merchant_id_prefix' => substr($merchantId, 0, 4) . '...',
            'status' => $response['status'],
            'error' => $message,
        ]);

        throw new RuntimeException($message);
    }

    /**
     * Fetches merchant integration details using the tracking identifier.
     *
     * Uses the standard partner merchant-integrations endpoint with tracking_id query parameter:
     * GET /v1/customer/partners/{partner_id}/merchant-integrations?tracking_id={tracking_id}
     *
     * @param string $apiBase
     * @param string $accessToken
     * @param string $partnerClientId
     * @param string $trackingId
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegrationByTrackingId(
        string $apiBase,
        string $accessToken,
        string $partnerClientId,
        string $trackingId,
        string $merchantId,
        string $environment
    ): ?array
    {
        // Validate partnerClientId - should only contain alphanumeric, underscore, dash (PayPal client IDs)
        if ($partnerClientId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $partnerClientId)) {
            $this->logDebug('Invalid partnerClientId format for tracking_id lookup', [
                'partnerClientId_length' => strlen($partnerClientId),
            ]);
            return null;
        }

        // Use the standard partner endpoint with tracking_id as a query parameter
        // Endpoint: GET /v1/customer/partners/{partner_id}/merchant-integrations?tracking_id={tracking_id}
        $query = http_build_query(array_filter([
            'tracking_id' => $trackingId,
        ], static function ($value) {
            return $value !== '' && $value !== null;
        }));

        $url = rtrim($apiBase, '/') . '/v1/customer/partners/' . rawurlencode($partnerClientId) . '/merchant-integrations';
        if ($query !== '') {
            $url .= '?' . $query;
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $response = $this->performHttpCall('GET', $url, $headers);
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] === 404) {
            // Merchant not found - return null so caller can try alternative methods
            return null;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            // The response may be a list of integrations or a single integration
            // Handle both cases for compatibility
            if (isset($decoded['merchant_id']) || isset($decoded['merchant_id_in_paypal'])) {
                // Single integration object
                return $this->normalizeMarketplaceIntegration($decoded, $environment);
            }

            // List response - look for matching tracking_id
            // Try known response formats first, then fall back to treating decoded as the items array
            $items = null;
            if (isset($decoded['merchant_integrations']) && is_array($decoded['merchant_integrations'])) {
                $items = $decoded['merchant_integrations'];
            } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
                $items = $decoded['items'];
            } else {
                // Fallback: the response may be a direct array or have an unexpected structure
                // Log this case to help identify API response format changes
                $this->logDebug('Merchant integrations response used fallback parsing', [
                    'tracking_id' => $trackingId,
                    'response_keys' => array_keys($decoded),
                ]);
                $items = [$decoded];
            }

            if (!is_array($items)) {
                return null;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemTracking = (string)($item['tracking_id'] ?? '');
                if ($itemTracking !== '' && strcasecmp($itemTracking, $trackingId) !== 0) {
                    continue;
                }

                return $this->normalizeMarketplaceIntegration($item, $environment);
            }

            // If no exact match, return the first item if available
            if (!empty($items[0]) && is_array($items[0])) {
                return $this->normalizeMarketplaceIntegration($items[0], $environment);
            }
        }

        // For non-404 errors, log but don't throw - let caller try other methods
        $message = $this->resolveErrorMessage($decoded, 'Unable to retrieve PayPal merchant integration details.');
        $this->logDebug('Merchant integration lookup by tracking_id returned error', [
            'tracking_id' => $trackingId,
            'status' => $response['status'],
            'error' => $message,
        ]);

        return null;
    }

    /**
     * Normalizes Marketplace-managed merchant integration responses into a credential payload.
     *
     * @param array<string, mixed> $integration
     * @param string               $environment
     * @return array<string, mixed>
     */
    private function normalizeMarketplaceIntegration(array $integration, string $environment): array
    {
        $credentialPayload = is_array($integration['credential_payload'] ?? null)
            ? $integration['credential_payload']
            : [];

        $status = (string)($integration['status'] ?? '');
        $paymentsReceivable = (bool)($integration['payments_receivable'] ?? false);
        $primaryEmailConfirmed = (bool)($integration['primary_email_confirmed'] ?? false);

        if (!empty($integration['integration_status']) && is_array($integration['integration_status'])) {
            $status = $status !== '' ? $status : (string)($integration['integration_status']['status'] ?? '');
            $paymentsReceivable = $paymentsReceivable || !empty($integration['integration_status']['payments_receivable']);
            $primaryEmailConfirmed = $primaryEmailConfirmed || !empty($integration['integration_status']['primary_email_confirmed']);
        }

        $thirdPartyDetails = $this->extractThirdPartyDetailsFromIntegration($integration);

        $normalized = array_merge(
            [
                'merchant_id' => (string)($integration['merchant_id'] ?? ''),
                'merchant_id_in_paypal' => (string)($integration['merchant_id_in_paypal'] ?? ($integration['merchant_id'] ?? '')),
                'status' => $status,
                'payments_receivable' => $paymentsReceivable,
                'primary_email_confirmed' => $primaryEmailConfirmed,
                'capabilities' => is_array($integration['capabilities'] ?? null) ? $integration['capabilities'] : [],
                'oauth_integrations' => is_array($integration['oauth_integrations'] ?? null) ? $integration['oauth_integrations'] : [],
                'third_party_details' => $thirdPartyDetails,
                'links' => is_array($integration['links'] ?? null) ? $integration['links'] : [],
                'products' => is_array($integration['products'] ?? null) ? $integration['products'] : [],
                'environment' => (string)($credentialPayload['environment'] ?? $environment),
            ],
            $credentialPayload
        );

        $thirdPartyCredentials = $this->extractCredentialsFromThirdPartyDetails($thirdPartyDetails);
        if (!empty($thirdPartyCredentials)) {
            $normalized = array_merge($normalized, $thirdPartyCredentials);
        }

        if (empty($normalized['status']) && !empty($credentialPayload['status'])) {
            $normalized['status'] = (string)$credentialPayload['status'];
        }

        if (empty($normalized['client_id']) || empty($normalized['client_secret'])) {
            $oauthCredentials = $this->extractMerchantCredentials($normalized);
            if ($oauthCredentials !== null) {
                $normalized['client_id'] = $normalized['client_id'] ?? $oauthCredentials['client_id'];
                $normalized['client_secret'] = $normalized['client_secret'] ?? $oauthCredentials['client_secret'];
            }
        }

        return $normalized;
    }

    /**
     * Extracts credential hints from third party details.
     *
     * @param array<string, mixed> $thirdPartyDetails
     * @return array<string, string>
     */
    private function extractCredentialsFromThirdPartyDetails(array $thirdPartyDetails): array
    {
        if (empty($thirdPartyDetails)) {
            return [];
        }

        $clientId = trim((string)($thirdPartyDetails['partner_client_id'] ?? ''));
        $clientSecret = trim((string)($thirdPartyDetails['partner_client_secret'] ?? ''));

        $credentials = [];

        if ($clientId !== '') {
            $credentials['client_id'] = $clientId;
        }

        if ($clientSecret !== '') {
            $credentials['client_secret'] = $clientSecret;
        }

        return $credentials;
    }

    /**
     * Attempts to discover third party details from the marketplace response.
     *
     * @param array<string, mixed> $integration
     * @return array<string, mixed>
     */
    private function extractThirdPartyDetailsFromIntegration(array $integration): array
    {
        if (!empty($integration['third_party_details']) && is_array($integration['third_party_details'])) {
            return $integration['third_party_details'];
        }

        if (!empty($integration['products']) && is_array($integration['products'])) {
            foreach ($integration['products'] as $product) {
                if (!is_array($product)) {
                    continue;
                }

                if (!empty($product['third_party_details']) && is_array($product['third_party_details'])) {
                    return $product['third_party_details'];
                }

                if (!empty($product['rest_api_integration']) && is_array($product['rest_api_integration'])) {
                    $restIntegration = $product['rest_api_integration'];
                    if (!empty($restIntegration['third_party_details']) && is_array($restIntegration['third_party_details'])) {
                        return $restIntegration['third_party_details'];
                    }
                }
            }
        }

        return [];
    }

    /**
     * Derives a human-readable error message from a PayPal response.
     *
     * @param array<string, mixed> $response
     * @param string               $fallback
     * @return string
     */
    private function resolveErrorMessage(array $response, string $fallback): string
    {
        if (!empty($response['message'])) {
            return (string)$response['message'];
        }
        if (!empty($response['error_description'])) {
            return (string)$response['error_description'];
        }
        if (!empty($response['name'])) {
            return (string)$response['name'];
        }

        if (!empty($response['details']) && is_array($response['details'])) {
            $messages = [];
            foreach ($response['details'] as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                if (!empty($detail['issue'])) {
                    $messages[] = (string)$detail['issue'];
                } elseif (!empty($detail['description'])) {
                    $messages[] = (string)$detail['description'];
                }
            }
            if (!empty($messages)) {
                return implode(' ', $messages);
            }
        }

        return $fallback;
    }

    /**
     * Normalizes capability values returned by PayPal.
     *
     * @param array<string, mixed> $integration
     * @return array<int, string>
     */
    private function extractCapabilities(array $integration): array
    {
        if (empty($integration['capabilities']) || !is_array($integration['capabilities'])) {
            return [];
        }

        $result = [];
        foreach ($integration['capabilities'] as $capability) {
            if (is_string($capability) && $capability !== '') {
                $result[] = $capability;
            } elseif (is_array($capability) && !empty($capability['name'])) {
                $result[] = (string)$capability['name'];
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Extracts merchant REST API credentials from the integration response.
     *
     * @param array<string, mixed> $integration
     * @return array{client_id: string, client_secret: string}|null
     */
    private function extractMerchantCredentials(array $integration): ?array
    {
        $directClientId = trim((string)($integration['client_id'] ?? ''));
        $directClientSecret = trim((string)($integration['client_secret'] ?? ''));

        if ($directClientId !== '' && $directClientSecret !== '') {
            return [
                'client_id' => $directClientId,
                'client_secret' => $directClientSecret,
            ];
        }

        if (empty($integration['oauth_integrations']) || !is_array($integration['oauth_integrations'])) {
            return null;
        }

        foreach ($integration['oauth_integrations'] as $oauth) {
            if (!is_array($oauth)) {
                continue;
            }

            $integrationMethod = strtoupper((string)($oauth['integration_method'] ?? ''));

            // Look for PAYPAL integration method with OAUTH credentials
            if ($integrationMethod !== 'PAYPAL') {
                continue;
            }

            // Extract credentials from oauth_third_party_details (primary) or oauth_third_party (legacy)
            $thirdPartyDetails = $oauth['oauth_third_party_details'] ?? $oauth['oauth_third_party'] ?? [];
            if (!is_array($thirdPartyDetails)) {
                continue;
            }

            $clientId = trim((string)($thirdPartyDetails['partner_client_id'] ?? ($thirdPartyDetails['client_id'] ?? '')));
            $clientSecret = trim((string)($thirdPartyDetails['partner_client_secret'] ?? ($thirdPartyDetails['client_secret'] ?? '')));

            if ($clientId !== '' && $clientSecret !== '') {
                return [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ];
            }
        }

        return null;
    }

    /**
     * Maps PayPal integration status fields into storefront onboarding steps.
     *
     * @param array<string, mixed> $integration
     * @return string
     */
    private function mapIntegrationToStep(array $integration): string
    {
        $status = strtolower((string)($integration['status'] ?? ''));
        $paymentsReceivable = !empty($integration['payments_receivable']);
        $permissionsGranted = !empty($integration['oauth_integrations'][0]['permissions_granted'] ?? false);

        if ($paymentsReceivable || in_array($status, ['active', 'approved', 'completed', 'ready'], true)) {
            return 'completed';
        }

        if (in_array($status, ['declined', 'denied', 'terminated', 'suspended'], true)) {
            return 'cancelled';
        }

        if ($permissionsGranted || in_array($status, ['in_review', 'under_review', 'pending_merchant_action'], true)) {
            return 'finalized';
        }

        return 'waiting';
    }

    /**
     * Performs an HTTP call supporting GET/POST semantics.
     *
     * @param string               $method
     * @param string               $url
     * @param array<int, string>   $headers
     * @param array<string, mixed> $options
     * @param string               $body
     * @return array{status: int, body: string}
     */
    private function performHttpCall(string $method, string $url, array $headers, array $options = [], string $body = ''): array
    {
        $headers = array_values(array_filter($headers, 'is_string'));
        $headers[] = 'Connection: close';

        $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 30;
        $method = strtoupper($method);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HEADER, true);

            if (!empty($options['basic_auth']) && is_string($options['basic_auth'])) {
                curl_setopt($ch, CURLOPT_USERPWD, $options['basic_auth']);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            if ($method !== 'GET' && $method !== 'HEAD') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch) ?: 'cURL error';
                curl_close($ch);
                throw new RuntimeException('Unable to contact PayPal: ' . $error);
            }

            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $bodyContent = substr($response, $headerSize);

            return [
                'status' => $status,
                'body' => $bodyContent === false ? '' : $bodyContent,
            ];
        }

        $context = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => $timeout,
            ],
        ];

        if (!empty($options['basic_auth']) && is_string($options['basic_auth'])) {
            $context['http']['header'] .= 'Authorization: Basic ' . base64_encode($options['basic_auth']) . "\r\n";
        }

        if ($method !== 'GET' && $method !== 'HEAD') {
            $context['http']['content'] = $body;
        }

        $resource = stream_context_create($context);
        $responseBody = @file_get_contents($url, false, $resource);
        if ($responseBody === false) {
            $error = error_get_last();
            throw new RuntimeException('Unable to contact PayPal: ' . ($error['message'] ?? 'HTTP request failed'));
        }

        $status = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                    $status = (int)$matches[1];
                    break;
                }
            }
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }
}
