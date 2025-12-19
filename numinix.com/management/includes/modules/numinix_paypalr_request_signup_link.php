<?php
declare(strict_types=1);
/**
 * Admin command endpoint for requesting a PayPal signup link.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$servicePath = __DIR__ . '/../classes/Numinix/PaypalIsu/SignupLinkService.php';
if (file_exists($servicePath)) {
    require_once $servicePath;
}

header('Content-Type: application/json');

$response = (new class {
    /** @var NuminixPaypalIsuSignupLinkService|null */
    protected $service;

    /**
     * Handles the request lifecycle.
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        try {
            $this->assertPostRequest();

            $payload = $this->decodePayload();
            $this->assertValidToken($payload);

            $service = $this->signupService();
            $result = $service->request($this->extractOptions($payload));

            return [
                'success' => true,
                'message' => 'Signup link generated successfully.',
                'data' => $result,
            ];
        } catch (Throwable $exception) {
            $this->logError($exception);

            if (http_response_code() < 400) {
                http_response_code(400);
            }

            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Ensures the request method is POST.
     *
     * @return void
     */
    protected function assertPostRequest(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            throw new RuntimeException('Invalid request method.');
        }
    }

    /**
     * Decodes the JSON payload.
     *
     * @return array<string, mixed>
     */
    protected function decodePayload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Missing request payload.');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON payload.');
        }

        return $payload;
    }

    /**
     * Validates the CSRF token supplied by the caller.
     *
     * @param array<string, mixed> $payload
     * @return void
     */
    protected function assertValidToken(array $payload): void
    {
        $token = (string) ($payload['securityToken'] ?? ($_POST['securityToken'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('Missing security token.');
        }

        $expected = $_SESSION['securityToken'] ?? null;
        if (is_string($expected) && $expected !== '') {
            if (function_exists('hash_equals')) {
                if (!hash_equals($expected, $token)) {
                    throw new RuntimeException('Security token validation failed.');
                }
                return;
            }

            if ($expected === $token) {
                return;
            }

            throw new RuntimeException('Security token validation failed.');
        }

        if (function_exists('zen_validate_token')) {
            try {
                $validator = new ReflectionFunction('zen_validate_token');
                $args = [$token];
                if ($validator->getNumberOfParameters() > 1) {
                    $args[] = false;
                }

                if ($validator->invokeArgs($args)) {
                    return;
                }
            } catch (Throwable $ignored) {
                // Ignore and fall through to failure.
            }
        }

        throw new RuntimeException('Security token validation failed.');
    }

    /**
     * Extracts the options for the signup request.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function extractOptions(array $payload): array
    {
        $options = [];
        if (!empty($payload['options']) && is_array($payload['options'])) {
            $options = $payload['options'];
        }

        foreach ($payload as $key => $value) {
            if ($key === 'options' || $key === 'securityToken') {
                continue;
            }

            if (!array_key_exists($key, $options)) {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Resolves the signup link service.
     *
     * @return NuminixPaypalIsuSignupLinkService
     */
    protected function signupService(): NuminixPaypalIsuSignupLinkService
    {
        if ($this->service instanceof NuminixPaypalIsuSignupLinkService) {
            return $this->service;
        }

        if (!class_exists('NuminixPaypalIsuSignupLinkService')) {
            throw new RuntimeException('Signup link service unavailable.');
        }

        $this->service = new NuminixPaypalIsuSignupLinkService();

        return $this->service;
    }

    /**
     * Logs the exception via the service if possible.
     *
     * @param \Throwable $exception
     * @return void
     */
    protected function logError(Throwable $exception): void
    {
        if ($this->service instanceof NuminixPaypalIsuSignupLinkService) {
            $message = 'Numinix PayPal signup link request failed: ' . $exception->getMessage();
            if (function_exists('zen_record_admin_activity')) {
                zen_record_admin_activity($message, 'error');
            } else {
                trigger_error($message, E_USER_WARNING);
            }
            return;
        }

        $message = 'Numinix PayPal signup link request failed: ' . $exception->getMessage();
        trigger_error($message, E_USER_WARNING);
    }
})();

echo json_encode($response);
