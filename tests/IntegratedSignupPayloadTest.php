<?php
declare(strict_types=1);

namespace PayPalRestful\Common {
    class Logger
    {
        public function __construct(string $name = '')
        {
        }

        public function enableDebug(): void
        {
        }

        public function write(string $message, bool $includeTimestamp = false, string $includeSeparator = ''): void
        {
        }

        public static function logJSON($data, bool $keep_links = false, bool $use_var_export = false): string
        {
            return json_encode($data);
        }
    }
}

namespace PayPalRestful\Api {
    class PayPalRestfulApi
    {
        public const PARTNER_ATTRIBUTION_ID = 'NuminixPPCP_SP';

        public static array $lastConstruct = [];
        public static array $lastPayload = [];
        public static $nextResponse = null;
        public static array $nextError = [];

        public function __construct(string $environment, string $clientId, string $clientSecret)
        {
            self::$lastConstruct = [
                'environment' => $environment,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];
        }

        public function createPartnerReferral(array $payload)
        {
            self::$lastPayload = $payload;
            return self::$nextResponse;
        }

        public function getErrorInfo(): array
        {
            return self::$nextError;
        }
    }
}

namespace {
    class paypalr
    {
        public static array $credentials = [
            'sandbox' => ['sandbox-partner-id', 'sandbox-partner-secret'],
            'live' => ['live-partner-id', 'live-partner-secret'],
        ];

        public static function getPartnerCredentials(string $environment): array
        {
            $environment = (strtolower($environment) === 'live') ? 'live' : 'sandbox';
            return self::$credentials[$environment] ?? ['', ''];
        }
    }

    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', __DIR__ . '/../');
    }
    if (!defined('DIR_WS_CATALOG')) {
        define('DIR_WS_CATALOG', 'store/');
    }
    if (!defined('STORE_NAME')) {
        define('STORE_NAME', 'Unit Test Store');
    }
    if (!defined('STORE_OWNER')) {
        define('STORE_OWNER', 'Pat Merchant');
    }
    if (!defined('STORE_OWNER_EMAIL_ADDRESS')) {
        define('STORE_OWNER_EMAIL_ADDRESS', 'owner@example.com');
    }
    if (!defined('HTTPS_SERVER')) {
        define('HTTPS_SERVER', 'https://example.com');
    }
    if (!defined('HTTP_SERVER')) {
        define('HTTP_SERVER', 'http://example.com');
    }
    if (!defined('FILENAME_MODULES')) {
        define('FILENAME_MODULES', 'modules.php');
    }
    if (!defined('MODULE_PAYMENT_PAYPALR_DEBUGGING')) {
        define('MODULE_PAYMENT_PAYPALR_DEBUGGING', 'Off');
    }

    if (!function_exists('zen_href_link')) {
        function zen_href_link(string $page = '', string $parameters = '', string $connection = 'NONSSL'): string
        {
            $scheme = ($connection === 'SSL') ? 'https://example.com/admin/' : 'http://example.com/admin/';
            $url = $scheme . ltrim($page, '/');
            if ($parameters !== '') {
                $url .= '?' . $parameters;
            }
            return $url;
        }
    }

    require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/Admin/IntegratedSignup.php';
}

namespace PayPalRestful\Tests {
    use PayPalRestful\Admin\IntegratedSignup;
    use PayPalRestful\Api\PayPalRestfulApi;
    use PHPUnit\Framework\TestCase;

    final class IntegratedSignupPayloadTest extends TestCase
    {
        protected function setUp(): void
        {
            PayPalRestfulApi::$nextResponse = null;
            PayPalRestfulApi::$nextError = [];
            PayPalRestfulApi::$lastPayload = [];
            PayPalRestfulApi::$lastConstruct = [];
        }

        public function testPayloadIncludesStoreMetadataAndRedirects(): void
        {
            PayPalRestfulApi::$nextResponse = [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://example.com/api/referrals/1'],
                    ['rel' => 'action_url', 'href' => 'https://onboarding.example.com/start'],
                ],
                'partner_referral_id' => 'TEST-REFERRAL-ID',
            ];

            $signup = new IntegratedSignup('LIVE');

            $this->assertTrue($signup->createReferral());
            $payload = $signup->getLastPayload();

            $this->assertSame('live', $signup->getEnvironment());
            $this->assertSame('https://onboarding.example.com/start', $signup->getActionUrl());
            $this->assertSame('TEST-REFERRAL-ID', $signup->getReferralId());

            $this->assertArrayHasKey('tracking_id', $payload);
            $this->assertNotSame('', $payload['tracking_id']);
            $this->assertStringStartsWith('zen-', $payload['tracking_id']);

            $this->assertSame('Unit Test Store', $payload['business_information']['business_name']);
            $this->assertSame(['https://example.com/store'], $payload['business_information']['website_urls']);
            $this->assertSame('owner@example.com', $payload['business_information']['customer_service_email']);

            $contact = $payload['contact_information'];
            $this->assertSame('owner@example.com', $contact['email_address']);
            $this->assertSame('Pat', $contact['name']['given_name']);
            $this->assertSame('Merchant', $contact['name']['surname']);

            $override = $payload['partner_config_override'];
            $this->assertSame('Unit Test Store', $override['display_name']);
            $this->assertSame('https://example.com/admin/paypalr_integrated_signup.php?action=return', $override['return_url']);

            $restIntegration = $payload['operations'][0]['api_integration_preference']['rest_api_integration'];
            $this->assertSame(PayPalRestfulApi::PARTNER_ATTRIBUTION_ID, $restIntegration['third_party_details']['partner_attribution_id']);
            $this->assertContains('PAYMENT', $restIntegration['third_party_details']['features']);

            $redirects = $restIntegration['redirect_urls'];
            $this->assertSame('https://example.com/admin/paypalr_integrated_signup.php?action=return', $redirects['return_url']);
            $this->assertSame('https://example.com/admin/paypalr_integrated_signup.php?action=cancel', $redirects['cancel_url']);

            $this->assertNotEmpty($signup->getLinks());
        }

        public function testCreateReferralCapturesApiError(): void
        {
            PayPalRestfulApi::$nextResponse = false;
            PayPalRestfulApi::$nextError = [
                'errMsg' => 'Simulated failure.',
                'debug_id' => 'DEBUG123',
                'details' => [],
            ];

            $signup = new IntegratedSignup('sandbox');

            $this->assertFalse($signup->createReferral());
            $this->assertSame(PayPalRestfulApi::$nextError, $signup->getError());
        }
    }
}
