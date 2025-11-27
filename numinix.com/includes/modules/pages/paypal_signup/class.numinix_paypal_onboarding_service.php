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

            [$clientId, $clientSecret] = $this->resolveCredentials($environment, $payload);
            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('PayPal partner API credentials are required.');
            }

            $apiBase = $this->resolveApiBase($environment);
            $accessToken = $this->obtainAccessToken($apiBase, $clientId, $clientSecret);

            $integration = $this->fetchMerchantIntegration(
                $apiBase,
                $accessToken,
                $trackingId,
                $partnerReferralId,
                $merchantId,
                $environment
            );
            if ($integration === null) {
                return $this->waitingResponse($environment, $trackingId, $partnerReferralId, $merchantId);
            }

            $step = $this->mapIntegrationToStep($integration);
            $data = [
                'environment' => $environment,
                'tracking_id' => $trackingId,
                'partner_referral_id' => $partnerReferralId,
                'merchant_id' => (string)($integration['merchant_id'] ?? ''),
                'merchant_id_in_paypal' => (string)($integration['merchant_id_in_paypal'] ?? ($integration['merchant_id'] ?? '')),
                'payments_receivable' => (bool)($integration['payments_receivable'] ?? false),
                'primary_email_confirmed' => (bool)($integration['primary_email_confirmed'] ?? false),
                'capabilities' => $this->extractCapabilities($integration),
                'step' => $step,
                'polling_interval' => self::DEFAULT_POLLING_INTERVAL_MS,
            ];

            if ($step === 'waiting') {
                $data['status_hint'] = 'provisioning';
            }

            if (!empty($integration['links']) && is_array($integration['links'])) {
                $data['links'] = $integration['links'];
            }

            // Extract merchant credentials from oauth_integrations when onboarding is complete
            if ($step === 'completed') {
                $credentials = $this->extractMerchantCredentials($integration);
                if ($credentials !== null) {
                    $data['credentials'] = $credentials;
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
     * @param string $trackingId
     * @param string $partnerReferralId
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegration(
        string $apiBase,
        string $accessToken,
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
            $trackingId,
            $merchantId,
            $environment
        );

        if ($integration === null && $merchantId !== '') {
            $integration = $this->fetchMerchantIntegrationByMerchantId($apiBase, $accessToken, $merchantId, $environment);
        }

        if ($integration === null && $partnerReferralId !== '') {
            $referral = $this->fetchPartnerReferral($apiBase, $accessToken, $partnerReferralId);
            $integration = $this->extractMerchantIntegrationFromReferral($apiBase, $accessToken, $referral, $environment);
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
     * @param array<string, mixed>|null  $referral
     * @param string                     $environment
     * @return array<string, mixed>|null
     */
    private function extractMerchantIntegrationFromReferral(
        string $apiBase,
        string $accessToken,
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
            return $this->fetchMerchantIntegrationByMerchantId($apiBase, $accessToken, $merchantId, $environment);
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
     * @param string $apiBase
     * @param string $accessToken
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegrationByMerchantId(
        string $apiBase,
        string $accessToken,
        string $merchantId,
        string $environment
    ): ?array
    {
        $query = http_build_query([
            'include_products' => 'true',
            'partner_merchant_id' => $merchantId,
        ]);

        $url = rtrim($apiBase, '/') . '/v1/customer/partners/marketplace/merchant-integrations?' . $query;

        $items = $this->fetchMarketplaceManagedIntegrations($url, $accessToken);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $partnerMerchantId = (string)($item['partner_merchant_id'] ?? '');
            $merchantMatch = ($partnerMerchantId !== '') && strcasecmp($partnerMerchantId, $merchantId) === 0;
            $merchantIdMatch = !$merchantMatch
                && !empty($item['merchant_id_in_paypal'])
                && strcasecmp((string)$item['merchant_id_in_paypal'], $merchantId) === 0;

            if ($merchantMatch || $merchantIdMatch) {
                return $this->normalizeMarketplaceIntegration($item, $environment);
            }
        }

        if (!empty($items[0]) && is_array($items[0])) {
            return $this->normalizeMarketplaceIntegration($items[0], $environment);
        }

        return null;
    }

    /**
     * Fetches merchant integration details using the tracking identifier.
     *
     * @param string $apiBase
     * @param string $accessToken
     * @param string $trackingId
     * @param string $merchantId
     * @param string $environment
     * @return array<string, mixed>|null
     */
    private function fetchMerchantIntegrationByTrackingId(
        string $apiBase,
        string $accessToken,
        string $trackingId,
        string $merchantId,
        string $environment
    ): ?array
    {
        $query = http_build_query(array_filter([
            'include_products' => 'true',
            'tracking_id' => $trackingId,
            'partner_merchant_id' => $merchantId,
        ], static function ($value) {
            return $value !== '' && $value !== null;
        }));

        $url = rtrim($apiBase, '/') . '/v1/customer/partners/marketplace/merchant-integrations?' . $query;

        $items = $this->fetchMarketplaceManagedIntegrations($url, $accessToken);

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

        if (!empty($items[0]) && is_array($items[0])) {
            return $this->normalizeMarketplaceIntegration($items[0], $environment);
        }

        return null;
    }

    /**
     * Retrieves Marketplace-managed merchant integrations.
     *
     * @param string $url
     * @param string $accessToken
     * @return array<int, mixed>
     */
    private function fetchMarketplaceManagedIntegrations(string $url, string $accessToken): array
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $response = $this->performHttpCall('GET', $url, $headers);
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] === 404) {
            return [];
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            if (isset($decoded['items']) && is_array($decoded['items'])) {
                return $decoded['items'];
            }

            return [];
        }

        $message = $this->resolveErrorMessage($decoded, 'Unable to retrieve PayPal merchant integration details.');
        throw new RuntimeException($message);
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
