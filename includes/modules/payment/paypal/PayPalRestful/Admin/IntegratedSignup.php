<?php
/**
 * Helper for managing the PayPal partner integrated sign-up flow.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.0
 */

namespace PayPalRestful\Admin;

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;

class IntegratedSignup
{
    private string $environment;

    private ?PayPalRestfulApi $api = null;

    private Logger $log;

    private string $trackingId = '';

    private string $referralId = '';

    private array $links = [];

    private string $actionUrl = '';

    private array $error = [];

    private array $lastPayload = [];

    public function __construct(string $environment)
    {
        $this->environment = (strtolower($environment) === 'live') ? 'live' : 'sandbox';
        $this->log = new Logger('isu');
        if (defined('MODULE_PAYMENT_PAYPALR_DEBUGGING') && strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false) {
            $this->log->enableDebug();
        }
        $this->initializeApi();
    }

    public function createReferral(): bool
    {
        if ($this->api === null) {
            if (empty($this->error)) {
                $this->error = [
                    'errMsg' => 'Unable to initialize the PayPal API client for integrated sign-up.',
                    'errNum' => 0,
                    'curlErrno' => 0,
                    'name' => 'CONFIGURATION',
                    'message' => 'PayPal partner credentials are not available.',
                    'details' => [],
                    'debug_id' => '',
                ];
            }
            return false;
        }

        $payload = $this->buildPayload();
        $this->lastPayload = $payload;

        $response = $this->api->createPartnerReferral($payload);
        if ($response === false) {
            $this->error = $this->api->getErrorInfo();
            $this->log->write('Integrated sign-up partner referral failed.' . "\n" . Logger::logJSON([
                'payload' => $payload,
                'error' => $this->error,
            ]));
            return false;
        }

        $this->links = $response['links'] ?? [];
        $this->actionUrl = $this->extractActionUrl();
        if ($this->actionUrl === '') {
            $this->error = [
                'errMsg' => 'PayPal did not provide an onboarding link.',
                'errNum' => 0,
                'curlErrno' => 0,
                'name' => 'MISSING_LINK',
                'message' => 'The partner-referral response did not contain an action_url link.',
                'details' => [],
                'debug_id' => $response['debug_id'] ?? '',
            ];
            $this->log->write('Integrated sign-up response missing action_url link.' . "\n" . Logger::logJSON([
                'links' => $this->links,
                'response' => $response,
            ]));
            return false;
        }

        $this->referralId = (string)($response['partner_referral_id'] ?? '');
        $this->error = [];

        return true;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function getActionUrl(): string
    {
        return $this->actionUrl;
    }

    public function getTrackingId(): string
    {
        return $this->trackingId;
    }

    public function getReferralId(): string
    {
        return $this->referralId;
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getLastPayload(): array
    {
        return $this->lastPayload;
    }

    private function initializeApi(): void
    {
        [$clientId, $clientSecret] = \paypalr::getPartnerCredentials($this->environment);
        if ($clientId === '' || $clientSecret === '') {
            $this->error = [
                'errMsg' => sprintf('Missing partner credentials for the %s environment.', $this->environment),
                'errNum' => 0,
                'curlErrno' => 0,
                'name' => 'CONFIGURATION',
                'message' => 'PayPal partner API credentials are required to start onboarding.',
                'details' => [],
                'debug_id' => '',
            ];
            $this->log->write($this->error['errMsg']);
            return;
        }

        $this->api = new PayPalRestfulApi($this->environment, $clientId, $clientSecret);
    }

    private function extractActionUrl(): string
    {
        foreach ($this->links as $link) {
            if (($link['rel'] ?? '') === 'action_url' && !empty($link['href'])) {
                return (string)$link['href'];
            }
        }

        return '';
    }

    private function buildPayload(): array
    {
        $storeName = defined('STORE_NAME') ? trim((string)STORE_NAME) : '';
        $ownerEmail = defined('STORE_OWNER_EMAIL_ADDRESS') ? trim((string)STORE_OWNER_EMAIL_ADDRESS) : '';
        [$givenName, $surname] = $this->parseOwnerName();
        $trackingId = $this->generateTrackingId();

        $operations = [
            [
                'operation' => 'API_INTEGRATION',
                'api_integration_preference' => [
                    'rest_api_integration' => [
                        'integration_method' => 'PAYPAL',
                        'integration_type' => 'THIRD_PARTY',
                        'third_party_details' => [
                            'features' => ['PAYMENT', 'REFUND', 'PARTNER_FEE'],
                        ],
                    ],
                ],
            ],
        ];

        $legalConsents = [
            [
                'type' => 'SHARE_DATA_CONSENT',
                'granted' => true,
            ],
        ];

        $partnerConfigOverride = array_filter([
            'display_name' => $storeName,
            'return_url' => $this->getReturnUrl(),
        ]);

        $businessInformation = array_filter([
            'business_name' => $storeName,
            'website_urls' => $this->getWebsiteUrls(),
            'customer_service_email' => $ownerEmail,
        ]);

        $payload = [
            'tracking_id' => $trackingId,
            'operations' => $operations,
            'products' => ['PPCP'],
            'legal_consents' => $legalConsents,
            'business_entity' => [
                'business_type' => 'INDIVIDUAL',
                'business_industry' => [
                    'industry_category' => 'ECOMMERCE',
                    'industry_type' => 'GENERAL_RETAIL',
                ],
            ],
            'contact_information' => [
                'email_address' => $ownerEmail,
                'name' => [
                    'given_name' => $givenName,
                    'surname' => $surname,
                ],
            ],
        ];

        if (!empty($partnerConfigOverride)) {
            $payload['partner_config_override'] = $partnerConfigOverride;
        }
        if (!empty($businessInformation)) {
            $payload['business_information'] = $businessInformation;
        }

        return $payload;
    }

    private function parseOwnerName(): array
    {
        $ownerName = defined('STORE_OWNER') ? trim((string)STORE_OWNER) : '';
        if ($ownerName === '') {
            return ['Store', 'Owner'];
        }

        $parts = preg_split('/\s+/', $ownerName, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return ['Store', 'Owner'];
        }

        $given = array_shift($parts) ?: 'Store';
        $surname = trim(implode(' ', $parts));
        if ($surname === '') {
            $surname = 'Owner';
        }

        return [$given, $surname];
    }

    private function generateTrackingId(): string
    {
        if ($this->trackingId !== '') {
            return $this->trackingId;
        }

        try {
            $this->trackingId = 'zen-' . bin2hex(random_bytes(8));
        } catch (\Exception $exception) {
            $this->trackingId = 'zen-' . uniqid('', true);
        }

        return $this->trackingId;
    }

    private function getWebsiteUrls(): array
    {
        $url = $this->getStorefrontUrl();
        return ($url === '') ? [] : [$url];
    }

    private function getStorefrontUrl(): string
    {
        $server = '';
        if (defined('HTTPS_SERVER') && HTTPS_SERVER !== '') {
            $server = (string)HTTPS_SERVER;
        } elseif (defined('HTTP_SERVER')) {
            $server = (string)HTTP_SERVER;
        }

        if ($server === '') {
            return '';
        }

        $catalogPath = defined('DIR_WS_CATALOG') ? (string)DIR_WS_CATALOG : '/';
        $catalogPath = '/' . ltrim($catalogPath, '/');

        return rtrim($server, '/') . rtrim($catalogPath, '/');
    }

    private function getReturnUrl(): string
    {
        if (function_exists('zen_href_link')) {
            return zen_href_link(FILENAME_MODULES, 'set=payment&module=paypalr', 'SSL');
        }

        return $this->getStorefrontUrl();
    }
}
