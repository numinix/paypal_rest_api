<?php
declare(strict_types=1);
/**
 * Service for requesting PayPal signup links via the Partner Referrals API.
 */

class NuminixPaypalIsuSignupLinkService
{
    private const ATTRIBUTION_ID = 'NuminixPPCP_SP';
    private const LEGACY_REFERRAL_LINK_CONFIGURATION_KEY = 'NUMINIX_PPCP_PARTNER_REFERRAL_LINK';
    private const REFERRAL_LINK_CONFIGURATION_KEYS = [
        'sandbox' => 'NUMINIX_PPCP_SANDBOX_PARTNER_REFERRAL_LINK',
        'live' => 'NUMINIX_PPCP_LIVE_PARTNER_REFERRAL_LINK',
    ];

    /**
     * @var array<string, mixed>|null
     */
    protected $lastDebugSnapshot = null;

    /**
     * Generates a PayPal signup link using the Referrals API.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(array $options): array
    {
        $this->lastDebugSnapshot = null;
        $environment = $this->normalizeEnvironment($options['environment'] ?? null);
        [$clientId, $clientSecret] = $this->resolveCredentials($environment, $options);

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal partner API credentials are required.');
        }

        $apiBase = $this->resolveApiBase($environment);
        $accessToken = $this->obtainAccessToken($apiBase, $clientId, $clientSecret);

        $payload = $this->buildPayload($environment, $options);
        $response = $this->createPartnerReferral($apiBase, $accessToken, $payload);

        $links = isset($response['links']) && is_array($response['links']) ? $response['links'] : [];
        $actionUrl = $this->extractActionUrl($links);
        if ($actionUrl === '') {
            $this->logDebug('PayPal partner referral response did not include an action_url link.', [
                'environment' => $environment,
                'payload' => $payload,
                'response' => $response,
            ]);
            throw new RuntimeException('PayPal did not return an onboarding link.');
        }

        $result = [
            'environment' => $environment,
            'tracking_id' => (string) $payload['tracking_id'],
            'partner_referral_id' => (string) ($response['partner_referral_id'] ?? ''),
            'action_url' => $actionUrl,
            'links' => $links,
            'payload' => $payload,
            'raw_response' => $response,
        ];

        $this->persistReferralLink($environment, $actionUrl);
        $this->logActivity('PayPal signup link generated for environment ' . $environment . '.');

        return $result;
    }

    /**
     * Returns the most recent debug snapshot captured during a request cycle.
     *
     * @return array<string, mixed>|null
     */
    public function getLastDebugSnapshot(): ?array
    {
        return $this->lastDebugSnapshot;
    }

    /**
     * Saves the generated referral link to configuration for future retrieval.
     *
     * @param string $environment
     * @param string $url
     * @return void
     */
    protected function persistReferralLink(string $environment, string $url): void
    {
        $url = trim($url);
        if ($url === '' || !defined('TABLE_CONFIGURATION') || !function_exists('zen_db_input')) {
            return;
        }

        global $db;
        if (!isset($db) || !is_object($db)) {
            return;
        }

        $key = $this->getReferralLinkConfigurationKey($environment);
        $existingValue = $this->sanitizeUrl($this->getConfigurationValue($key));

        // If a value is already stored for this environment, keep it unless the merchant clears it manually.
        if ($existingValue !== '') {
            $this->defineConfigurationConstant($key, $existingValue);
            return;
        }

        // Migrate a legacy value if present before falling back to the newly generated URL.
        $valueToPersist = $existingValue;
        if ($valueToPersist === '') {
            $legacy = $this->sanitizeUrl($this->getConfigurationValue(self::LEGACY_REFERRAL_LINK_CONFIGURATION_KEY));
            if ($legacy !== '') {
                $valueToPersist = $legacy;
            }
        }

        if ($valueToPersist === '') {
            $valueToPersist = $url;
        }

        $escapedKey = zen_db_input($key);
        $query = $db->Execute(
            "SELECT configuration_id"
            . " FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = '" . $escapedKey . "'"
            . " LIMIT 1"
        );

        if ($query && !$query->EOF) {
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION
                . " SET configuration_value = '" . zen_db_input($valueToPersist) . "', last_modified = NOW()"
                . " WHERE configuration_id = " . (int) $query->fields['configuration_id']
                . " LIMIT 1"
            );
        } else {
            $groupId = $this->detectConfigurationGroupId();
            $db->Execute(
                "INSERT INTO " . TABLE_CONFIGURATION
                . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)"
                . " VALUES ('Stored PayPal partner referral link', '" . zen_db_input($key) . "', '" . zen_db_input($valueToPersist) . "', 'Caches the reusable PayPal onboarding link for this environment.', " . (int) $groupId . ", 100, NOW())"
            );
        }

        $this->defineConfigurationConstant($key, $valueToPersist);
    }

    /**
     * Normalizes environment input.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeEnvironment($value): string
    {
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['sandbox', 'live'], true)) {
                return $value;
            }
        }

        if (defined('NUMINIX_PPCP_ENVIRONMENT')) {
            $config = strtolower((string) NUMINIX_PPCP_ENVIRONMENT);
            if (in_array($config, ['sandbox', 'live'], true)) {
                return $config;
            }
        }

        return 'sandbox';
    }

    /**
     * Resolves partner credentials.
     *
     * @param string                $environment
     * @param array<string, mixed> $options
     * @return array{0: string, 1: string}
     */
    protected function resolveCredentials(string $environment, array $options): array
    {
        $clientId = '';
        $clientSecret = '';

        if (!empty($options['client_id']) && is_string($options['client_id'])) {
            $clientId = trim($options['client_id']);
        }
        if (!empty($options['client_secret']) && is_string($options['client_secret'])) {
            $clientSecret = trim($options['client_secret']);
        }

        if ($clientId !== '' && $clientSecret !== '') {
            return [$clientId, $clientSecret];
        }

        $suffix = strtoupper($environment);

        $envClientId = getenv('PAYPAL_PARTNER_CLIENT_ID_' . $suffix);
        $envClientSecret = getenv('PAYPAL_PARTNER_CLIENT_SECRET_' . $suffix);

        if ($clientId === '' && is_string($envClientId)) {
            $clientId = trim($envClientId);
        }
        if ($clientSecret === '' && is_string($envClientSecret)) {
            $clientSecret = trim($envClientSecret);
        }

        if ($clientId !== '' && $clientSecret !== '') {
            return [$clientId, $clientSecret];
        }

        $configPrefix = 'NUMINIX_PPCP_' . $suffix . '_PARTNER_';
        $configClientId = $this->getConfigurationValue($configPrefix . 'CLIENT_ID');
        $configClientSecret = $this->getConfigurationValue($configPrefix . 'CLIENT_SECRET');

        if ($clientId === '' && $configClientId !== null) {
            $clientId = trim($configClientId);
        }
        if ($clientSecret === '' && $configClientSecret !== null) {
            $clientSecret = trim($configClientSecret);
        }

        return [$clientId, $clientSecret];
    }

    /**
     * Resolves the PayPal API base URL.
     *
     * @param string $environment
     * @return string
     */
    protected function resolveApiBase(string $environment): string
    {
        if ($environment === 'live') {
            return 'https://api-m.paypal.com';
        }

        return 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Obtains an OAuth access token from PayPal.
     *
     * @param string $apiBase
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     */
    protected function obtainAccessToken(string $apiBase, string $clientId, string $clientSecret): string
    {
        $url = rtrim($apiBase, '/') . '/v1/oauth2/token';
        $headers = [
            'Accept: application/json',
            'Accept-Language: en_US',
        ];
        $body = http_build_query([
            'grant_type' => 'client_credentials',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->performHttpRequest($url, $headers, $body, [
            'basic_auth' => $clientId . ':' . $clientSecret,
            'content_type' => 'application/x-www-form-urlencoded',
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $decoded = $this->decodeJson($response['body']);
            $this->logDebug('PayPal access token request failed.', [
                'url' => $url,
                'status' => $response['status'],
                'response' => empty($decoded) ? $response['body'] : $decoded,
            ]);
            $message = 'Unable to obtain PayPal access token (HTTP ' . $response['status'] . ').';
            if (!empty($decoded['error_description'])) {
                $message = (string) $decoded['error_description'];
            } elseif (!empty($decoded['error'])) {
                $message = (string) $decoded['error'];
            }
            throw new RuntimeException($message);
        }

        $decoded = $this->decodeJson($response['body']);
        $token = (string) ($decoded['access_token'] ?? '');

        if ($token === '') {
            $this->logDebug('PayPal access token response was missing an access_token value.', [
                'url' => $url,
                'status' => $response['status'],
                'response' => empty($decoded) ? $response['body'] : $decoded,
            ]);
            $message = 'PayPal did not return an access token.';
            if (!empty($decoded['error_description'])) {
                $message = (string) $decoded['error_description'];
            }
            throw new RuntimeException($message);
        }

        return $token;
    }

    /**
     * Sends the partner referral request to PayPal.
     *
     * @param string               $apiBase
     * @param string               $accessToken
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function createPartnerReferral(string $apiBase, string $accessToken, array $payload): array
    {
        $url = rtrim($apiBase, '/') . '/v2/customer/partner-referrals';
        $trackingId = (string) ($payload['tracking_id'] ?? '');

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'PayPal-Partner-Attribution-Id: ' . self::ATTRIBUTION_ID,
        ];

        if ($trackingId !== '') {
            $headers[] = 'PayPal-Request-Id: ' . $trackingId;
        }

        $response = $this->performHttpRequest($url, $headers, json_encode($payload) ?: '{}');
        $decoded = $this->decodeJson($response['body']);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->logDebug('PayPal partner referral request failed.', [
                'url' => $url,
                'status' => $response['status'],
                'payload' => $payload,
                'response' => empty($decoded) ? $response['body'] : $decoded,
            ]);
            $message = 'PayPal partner referral request failed (HTTP ' . $response['status'] . ').';
            if (!empty($decoded['message'])) {
                $message = (string) $decoded['message'];
            } elseif (!empty($decoded['error_description'])) {
                $message = (string) $decoded['error_description'];
            }

            $detailMessage = $this->resolveErrorDetails($decoded['details'] ?? []);
            if ($detailMessage !== '') {
                $message = rtrim($message, '.') . '. ' . $detailMessage;
            }

            throw new RuntimeException($message);
        }

        return $decoded;
    }

    /**
     * Creates a human readable error message from PayPal error details.
     *
     * @param mixed $details
     * @return string
     */
    protected function resolveErrorDetails($details): string
    {
        if (!is_array($details)) {
            return '';
        }

        $messages = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $issue = strtoupper((string) ($detail['issue'] ?? ''));
            $description = $this->sanitizeString($detail['description'] ?? '', 512, '');

            if ($issue === 'PRODUCT_PPCP_UNAUTHORIZED') {
                $messages[] = 'Your PayPal partner account is not enabled for PayPal Complete Payments (PPCP). Contact your PayPal partner manager or support team to have PPCP activated before generating a signup link.';
                continue;
            }

            if ($description !== '') {
                $messages[] = $description;
                continue;
            }

            if ($issue !== '') {
                $messages[] = $issue;
            }
        }

        $messages = array_filter(array_map('trim', $messages));

        return implode(' ', array_unique($messages));
    }

    /**
     * Builds the partner referral payload.
     *
     * @param string               $environment
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function buildPayload(string $environment, array $options): array
    {
        $storeName = $this->sanitizeString($options['display_name'] ?? $this->getStoreName(), 128, 'Storefront');
        $businessName = $this->sanitizeString($options['business_name'] ?? $storeName, 128, $storeName);
        $ownerEmail = $this->sanitizeEmail($options['email'] ?? $this->getStoreOwnerEmail());
        [$givenName, $surname] = $this->parseOwnerName($options['given_name'] ?? null, $options['surname'] ?? null);
        $trackingId = $this->resolveTrackingId($options['tracking_id'] ?? null);

        $returnUrl = $this->sanitizeUrl($options['return_url'] ?? $this->getOnboardingUrl('return'));
        $websiteUrls = $this->resolveWebsiteUrls($options['website_urls'] ?? null);

        $restIntegration = [
            'integration_method' => 'PAYPAL',
            'integration_type' => 'THIRD_PARTY',
            'third_party_details' => [
                'features' => ['PAYMENT', 'REFUND'],
            ],
        ];

        $payload = [
            'tracking_id' => $trackingId,
            'products' => ['PPCP'],
            'operations' => [
                [
                    'operation' => 'API_INTEGRATION',
                    'api_integration_preference' => [
                        'rest_api_integration' => $restIntegration,
                    ],
                ],
            ],
            'legal_consents' => [
                [
                    'type' => 'SHARE_DATA_CONSENT',
                    'granted' => true,
                ],
            ],
            'contact_information' => [
                'email_address' => $ownerEmail,
                'name' => [
                    'given_name' => $givenName,
                    'surname' => $surname,
                ],
            ],
            'business_entity' => [
                'business_type' => [
                    'type' => 'INDIVIDUAL',
                ],
                'business_industry' => [
                    'industry_category' => 'ECOMMERCE',
                    'industry_type' => 'GENERAL_RETAIL',
                ],
            ],
        ];

        $partnerOverride = array_filter([
            'return_url' => $returnUrl,
        ]);
        if (!empty($partnerOverride)) {
            $payload['partner_config_override'] = $partnerOverride;
        }

        $businessInformation = array_filter([
            'business_name' => $businessName,
            'website_urls' => $websiteUrls,
            'customer_service_email' => $ownerEmail,
        ]);
        if (!empty($businessInformation)) {
            $payload['business_information'] = $businessInformation;
        }

        if (!empty($options['partner_config_override']) && is_array($options['partner_config_override'])) {
            $payload['partner_config_override'] = array_merge(
                $payload['partner_config_override'] ?? [],
                $this->sanitizeOverride($options['partner_config_override'])
            );
        }

        if (!empty($options['referral_payload']) && is_array($options['referral_payload'])) {
            $payload = array_merge($payload, $options['referral_payload']);
        }

        return $payload;
    }

    /**
     * Extracts the action URL from PayPal link relations.
     *
     * @param array<int, mixed> $links
     * @return string
     */
    protected function extractActionUrl(array $links): string
    {
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            if (($link['rel'] ?? '') === 'action_url' && !empty($link['href'])) {
                return $this->sanitizeUrl($link['href']);
            }
        }

        return '';
    }

    /**
     * Performs an HTTP request using cURL or the stream wrapper.
     *
     * @param string               $url
     * @param array<int, string>   $headers
     * @param string               $body
     * @param array<string, mixed> $options
     * @return array{status: int, body: string}
     */
    protected function performHttpRequest(string $url, array $headers, string $body, array $options = []): array
    {
        $headers = array_values(array_filter($headers, 'is_string'));
        $headers[] = 'Connection: close';

        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;
        $contentType = (string) ($options['content_type'] ?? 'application/json');

        $hasContentType = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType && $contentType !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HEADER, true);

            if (!empty($options['basic_auth']) && is_string($options['basic_auth'])) {
                curl_setopt($ch, CURLOPT_USERPWD, $options['basic_auth']);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            }

            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch) ?: 'cURL error';
                curl_close($ch);
                throw new RuntimeException('Unable to contact PayPal: ' . $error);
            }

            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $bodyContent = substr($response, $headerSize);

            return [
                'status' => $status,
                'body' => $bodyContent === false ? '' : $bodyContent,
            ];
        }

        $contextHeaders = $headers;
        if (!empty($options['basic_auth']) && is_string($options['basic_auth'])) {
            $contextHeaders[] = 'Authorization: Basic ' . base64_encode($options['basic_auth']);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $contextHeaders) . "\r\n",
                'content' => $body,
                'timeout' => $timeout,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            $error = error_get_last();
            throw new RuntimeException('Unable to contact PayPal: ' . ($error['message'] ?? 'HTTP request failed'));
        }

        $status = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }

    /**
     * Decodes JSON into an associative array.
     *
     * @param string $json
     * @return array<string, mixed>
     */
    protected function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Retrieves a configuration value from the database if available.
     *
     * @param string $key
     * @return string|null
     */
    protected function getConfigurationValue(string $key): ?string
    {
        if (!function_exists('zen_get_configuration_key_value')) {
            return null;
        }

        $value = zen_get_configuration_key_value($key);
        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Resolves the configuration key used to store a referral link for the requested environment.
     *
     * @param string $environment
     * @return string
     */
    protected function getReferralLinkConfigurationKey(string $environment): string
    {
        if (isset(self::REFERRAL_LINK_CONFIGURATION_KEYS[$environment])) {
            return self::REFERRAL_LINK_CONFIGURATION_KEYS[$environment];
        }

        return self::LEGACY_REFERRAL_LINK_CONFIGURATION_KEY;
    }

    /**
     * Defines a configuration constant when possible while avoiding duplicate definitions.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    protected function defineConfigurationConstant(string $key, string $value): void
    {
        if (function_exists('zen_define_default')) {
            zen_define_default($key, $value);
        } elseif (!defined($key)) {
            define($key, $value);
        }
    }

    /**
     * Sanitizes a URL value for safe storage.
     *
     * @param string|null $value
     * @return string
     */
    protected function sanitizeUrl(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $value;
    }

    /**
     * Attempts to detect the configuration group id for inserting new keys.
     *
     * @return int
     */
    protected function detectConfigurationGroupId(): int
    {
        if (function_exists('zen_get_configuration_group_id')) {
            $groupId = zen_get_configuration_group_id('NUMINIX_PPCP_ENVIRONMENT');
            if (is_numeric($groupId) && (int) $groupId > 0) {
                return (int) $groupId;
            }
        }

        return 0;
    }

    /**
     * Retrieves the configured storefront name.
     *
     * @return string
     */
    protected function getStoreName(): string
    {
        if (defined('STORE_NAME')) {
            $name = trim((string) STORE_NAME);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Zen Cart Store';
    }

    /**
     * Retrieves the configured store owner email.
     *
     * @return string
     */
    protected function getStoreOwnerEmail(): string
    {
        if (defined('STORE_OWNER_EMAIL_ADDRESS')) {
            $email = trim((string) STORE_OWNER_EMAIL_ADDRESS);
            if ($email !== '') {
                return $email;
            }
        }

        return 'merchant@example.com';
    }

    /**
     * Parses the store owner's name.
     *
     * @param mixed $given
     * @param mixed $surname
     * @return array{0: string, 1: string}
     */
    protected function parseOwnerName($given = null, $surname = null): array
    {
        if (is_string($given) && trim($given) !== '') {
            $givenName = $this->sanitizeString($given, 60, 'Store');
        } else {
            $givenName = 'Store';
        }

        if (is_string($surname) && trim($surname) !== '') {
            $familyName = $this->sanitizeString($surname, 60, 'Owner');
        } else {
            $familyName = null;
        }

        if ($familyName !== null) {
            return [$givenName, $familyName];
        }

        if (defined('STORE_OWNER')) {
            $owner = trim((string) STORE_OWNER);
            if ($owner !== '') {
                $parts = preg_split('/\s+/', $owner, -1, PREG_SPLIT_NO_EMPTY);
                if (!empty($parts)) {
                    $givenName = $this->sanitizeString(array_shift($parts) ?: 'Store', 60, 'Store');
                    $family = $this->sanitizeString(implode(' ', $parts), 60, 'Owner');
                    return [$givenName, $family];
                }
            }
        }

        return [$givenName, 'Owner'];
    }

    /**
     * Generates or validates the tracking identifier.
     *
     * @param mixed $value
     * @return string
     */
    protected function resolveTrackingId($value): string
    {
        if (is_string($value)) {
            $candidate = strtolower(trim($value));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        try {
            return 'nxp-' . bin2hex(random_bytes(10));
        } catch (Throwable $exception) {
            return 'nxp-' . str_replace('.', '-', uniqid('', true));
        }
    }

    /**
     * Resolves website URLs for the payload.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    protected function resolveWebsiteUrls($value): array
    {
        if (is_array($value)) {
            $urls = [];
            foreach ($value as $url) {
                $sanitized = $this->sanitizeUrl($url);
                if ($sanitized !== '') {
                    $urls[] = $sanitized;
                }
            }
            if (!empty($urls)) {
                return array_values(array_unique($urls));
            }
        } elseif (is_string($value)) {
            $sanitized = $this->sanitizeUrl($value);
            if ($sanitized !== '') {
                return [$sanitized];
            }
        }

        $default = $this->getStorefrontUrl();
        return ($default === '') ? [] : [$default];
    }

    /**
     * Returns the storefront URL.
     *
     * @return string
     */
    protected function getStorefrontUrl(): string
    {
        $server = '';
        if (defined('HTTPS_SERVER') && HTTPS_SERVER !== '') {
            $server = (string) HTTPS_SERVER;
        } elseif (defined('HTTP_SERVER') && HTTP_SERVER !== '') {
            $server = (string) HTTP_SERVER;
        }

        if ($server === '') {
            return '';
        }

        $catalog = defined('DIR_WS_CATALOG') ? (string) DIR_WS_CATALOG : '/';
        $catalog = '/' . ltrim($catalog, '/');

        return rtrim($server, '/') . rtrim($catalog, '/');
    }

    /**
     * Returns the onboarding return/cancel URL.
     *
     * @param string $action
     * @return string
     */
    protected function getOnboardingUrl(string $action): string
    {
        $page = defined('FILENAME_PAYPAL_SIGNUP') ? FILENAME_PAYPAL_SIGNUP : 'paypal_signup';

        if (function_exists('zen_href_link')) {
            return $this->sanitizeUrl(zen_href_link($page, 'action=' . urlencode($action), 'SSL'));
        }

        $base = $this->getStorefrontUrl();
        if ($base === '') {
            return '';
        }

        return $base . '/index.php?main_page=' . rawurlencode($page) . '&action=' . urlencode($action);
    }

    /**
     * Sanitizes textual overrides.
     *
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    protected function sanitizeOverride(array $override): array
    {
        $sanitized = [];
        foreach ($override as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (is_string($value)) {
                if (stripos($key, 'url') !== false) {
                    $clean = $this->sanitizeUrl($value);
                } elseif (stripos($key, 'email') !== false) {
                    $clean = $this->sanitizeEmail($value);
                } else {
                    $clean = $this->sanitizeString($value, 256, '');
                }
                if ($clean !== '') {
                    $sanitized[$key] = $clean;
                }
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeOverride($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitizes strings with fallback.
     *
     * @param mixed  $value
     * @param int    $maxLength
     * @param string $fallback
     * @return string
     */
    protected function sanitizeString($value, int $maxLength, string $fallback): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                if (function_exists('mb_substr')) {
                    $sanitized = mb_substr($trimmed, 0, $maxLength);
                } else {
                    $sanitized = substr($trimmed, 0, $maxLength);
                }
                return $sanitized === '' ? $fallback : $sanitized;
            }
        }

        return $fallback;
    }

    /**
     * Sanitizes an email address.
     *
     * @param mixed $value
     * @return string
     */
    protected function sanitizeEmail($value): string
    {
        if (is_string($value)) {
            $email = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
            if (is_string($email)) {
                return $email;
            }
        }

        return 'merchant@example.com';
    }

    /**
     * Sanitizes a URL value.
     *
     * @param mixed $value
     * @return string
     */
    protected function sanitizeUrl($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $url = trim($value);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        return $url;
    }

    /**
     * Logs additional debugging information to the admin activity log when available.
     *
     * @param string               $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $sanitized = [];
        if (!empty($context)) {
            $sanitized = $this->redactForLog($context);
        }

        $timestamp = date('c');
        $logMessage = $message;

        if (!empty($sanitized)) {
            $encoded = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                $encoded = var_export($sanitized, true);
            }

            if (strlen($encoded) > 4000) {
                $encoded = substr($encoded, 0, 4000) . '...';
            }

            $logMessage .= ' Context: ' . $encoded;
        }

        $this->lastDebugSnapshot = [
            'timestamp' => $timestamp,
            'message' => $message,
        ];

        if (!empty($sanitized)) {
            $this->lastDebugSnapshot['context'] = $sanitized;
        }

        $this->lastDebugSnapshot['log_entry'] = '[' . $timestamp . '] ' . $logMessage;

        if (function_exists('zen_record_admin_activity')) {
            zen_record_admin_activity($logMessage, 'info');
        } else {
            trigger_error($logMessage, E_USER_NOTICE);
        }

        $this->writeDebugLog($this->lastDebugSnapshot['log_entry']);
    }

    /**
     * Appends a debug log line to the Zen Cart logs directory when available.
     *
     * @param string $line
     * @return void
     */
    protected function writeDebugLog(string $line): void
    {
        $logFile = $this->resolveDebugLogFile();
        if ($logFile === null || $line === '') {
            return;
        }

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                return;
            }
        }

        $result = @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false && function_exists('error_log')) {
            error_log('Numinix PayPal ISU unable to write debug log: ' . $logFile);
        }
    }

    /**
     * Determines the debug log file path within the Zen Cart logs directory.
     *
     * @return string|null
     */
    protected function resolveDebugLogFile(): ?string
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

        return $baseDir . '/numinix_paypal_signup_debug.log';
    }

    /**
     * Recursively redacts sensitive values before logging.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function redactForLog($value)
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $lowerKey = is_string($key) ? strtolower($key) : '';
                if (in_array($lowerKey, ['client_secret', 'access_token', 'refresh_token', 'authorization', 'password'], true)) {
                    $redacted[$key] = '[redacted]';
                    continue;
                }

                $redacted[$key] = $this->redactForLog($item);
            }

            return $redacted;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return $this->redactForLog($value->jsonSerialize());
        }

        return (string) $value;
    }

    /**
     * Records an admin activity message when available.
     *
     * @param string $message
     * @return void
     */
    protected function logActivity(string $message): void
    {
        if (function_exists('zen_record_admin_activity')) {
            zen_record_admin_activity($message, 'info');
        } else {
            trigger_error($message, E_USER_NOTICE);
        }
    }
}
