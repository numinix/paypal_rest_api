<?php
interface PayPalProfileGatewayInterface
{
    public function cancelProfile(array $subscription, $note = '', array $context = array());
    public function suspendProfile(array $subscription, $note = '', array $context = array());
    public function reactivateProfile(array $subscription, $note = '', array $context = array());
    public function getProfileStatus(array $subscription, array $context = array());
    public function updateBillingCycles(array $subscription, array $billingCycles, array $context = array());
    public function updatePaymentSource(array $subscription, array $paymentSource, array $context = array());
}

class PayPalProfileManager implements PayPalProfileGatewayInterface
{
    /** @var PayPalProfileGatewayInterface[] */
    protected $gateways = array();

    public function __construct(array $gateways = array())
    {
        foreach ($gateways as $gateway) {
            if ($gateway instanceof PayPalProfileGatewayInterface) {
                $this->gateways[] = $gateway;
            }
        }
    }

    public static function create($restClient = null, $legacyClient = null)
    {
        $gateways = array();
        if ($legacyClient) {
            $gateways[] = new PayPalLegacyProfileGateway($legacyClient);
        }
        if ($restClient) {
            $gateways[] = new PayPalRestProfileGateway($restClient);
        }
        return new self($gateways);
    }

    public function cancelProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array($note), $context);
    }

    public function suspendProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array($note), $context);
    }

    public function reactivateProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array($note), $context);
    }

    public function getProfileStatus(array $subscription, array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array(), $context);
    }

    public function updateBillingCycles(array $subscription, array $billingCycles = array(), array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array($billingCycles), $context);
    }

    public function updatePaymentSource(array $subscription, array $paymentSource = array(), array $context = array())
    {
        return $this->execute(__FUNCTION__, $subscription, array($paymentSource), $context);
    }

    protected function execute($method, array $subscription, array $arguments = array(), array $context = array())
    {
        $result = array('success' => false, 'message' => '', 'status' => null, 'retry' => false);
        $preferredGateway = $this->normalizeGatewayName($this->extractPreferredGateway($subscription, $context));
        if ($preferredGateway !== '') {
            $context['preferred_gateway'] = $preferredGateway;
        } else {
            unset($context['preferred_gateway']);
        }
        $confidence = $this->normalizeConfidence(isset($context['confidence']) ? $context['confidence'] : '');
        if ($confidence !== '') {
            $context['confidence'] = $confidence;
        } else {
            unset($context['confidence']);
        }
        $gateways = $this->prioritizeGateways($this->gateways, $preferredGateway);
        $attemptedGateways = array();
        $preferredAttempted = false;
        $preferredFailed = false;
        $fallbackOccurred = false;

        foreach ($gateways as $gateway) {
            if (!method_exists($gateway, $method)) {
                continue;
            }
            $gatewayName = $this->identifyGateway($gateway);
            $attemptedGateways[] = $gatewayName;
            if ($preferredGateway !== '' && $gatewayName === $preferredGateway) {
                $preferredAttempted = true;
            } elseif ($preferredAttempted) {
                $fallbackOccurred = true;
            }
            try {
                $callArguments = array_merge(array($subscription), $arguments, array($context));
                $response = call_user_func_array(array($gateway, $method), $callArguments);
            } catch (Exception $e) {
                $response = array(
                    'success' => false,
                    'message' => $e->getMessage(),
                    'retry' => $this->shouldRetry($e)
                );
            }
            $response = $this->normalizeResult($response);
            $response['gateway'] = $gatewayName;
            if ($response['success']) {
                if ($preferredFailed && $fallbackOccurred) {
                    $this->logGatewayFallback($method, $preferredGateway, $attemptedGateways, $response, $context, true);
                }
                return $response;
            }
            $result = $response;
            if ($preferredGateway !== '' && $gatewayName === $preferredGateway) {
                $preferredFailed = true;
            }
            if (empty($response['retry'])) {
                break;
            }
        }
        if (!array_key_exists('profile', $result)) {
            $result['profile'] = array();
        }
        if (!array_key_exists('profile_source', $result)) {
            $result['profile_source'] = '';
        }
        if (!array_key_exists('gateway', $result)) {
            $result['gateway'] = $preferredGateway !== '' ? $preferredGateway : (isset($attemptedGateways[0]) ? $attemptedGateways[0] : '');
        }
        if ($preferredFailed && $fallbackOccurred) {
            $this->logGatewayFallback($method, $preferredGateway, $attemptedGateways, $result, $context, false);
        }
        return $result;
    }

    protected function normalizeResult($result)
    {
        if (!is_array($result)) {
            $result = array('success' => (bool)$result);
        }
        if (!array_key_exists('success', $result)) {
            $result['success'] = false;
        }
        if (!array_key_exists('message', $result)) {
            $result['message'] = '';
        }
        if (!array_key_exists('status', $result)) {
            $result['status'] = null;
        }
        if (!array_key_exists('retry', $result)) {
            $result['retry'] = false;
        }
        if (!array_key_exists('gateway', $result)) {
            $result['gateway'] = '';
        }
        return $result;
    }

    protected function extractPreferredGateway(array $subscription, array $context)
    {
        if (isset($context['preferred_gateway'])) {
            return $context['preferred_gateway'];
        }
        if (isset($subscription['preferred_gateway'])) {
            return $subscription['preferred_gateway'];
        }
        if (isset($subscription['gateway_hint'])) {
            return $subscription['gateway_hint'];
        }
        if (isset($subscription['profile_source'])) {
            return $subscription['profile_source'];
        }
        return '';
    }

    protected function normalizeGatewayName($gateway)
    {
        if (!is_string($gateway)) {
            return '';
        }
        $normalized = strtolower(trim($gateway));
        if ($normalized === 'paypalr') {
            $normalized = 'rest';
        }
        if ($normalized === 'nvp') {
            $normalized = 'legacy';
        }
        if ($normalized === '') {
            return '';
        }
        return preg_replace('/[^a-z0-9_-]/', '', $normalized);
    }

    protected function normalizeConfidence($confidence)
    {
        $value = strtolower(trim((string) $confidence));
        if (in_array($value, array('high', 'medium', 'low'), true)) {
            return $value;
        }
        return '';
    }

    protected function prioritizeGateways(array $gateways, $preferredGateway)
    {
        if ($preferredGateway === '') {
            return $gateways;
        }
        $preferred = array();
        $others = array();
        foreach ($gateways as $gateway) {
            $name = $this->identifyGateway($gateway);
            if ($name === $preferredGateway) {
                $preferred[] = $gateway;
            } else {
                $others[] = $gateway;
            }
        }
        if (empty($preferred)) {
            return $gateways;
        }
        return array_merge($preferred, $others);
    }

    protected function identifyGateway($gateway)
    {
        if ($gateway instanceof PayPalRestProfileGateway) {
            return 'rest';
        }
        if ($gateway instanceof PayPalLegacyProfileGateway) {
            return 'legacy';
        }
        $class = is_object($gateway) ? get_class($gateway) : (string) $gateway;
        $parts = explode('\\', $class);
        $shortName = end($parts);
        $shortName = strtolower(preg_replace('/[^a-z0-9_-]/', '', $shortName));
        if ($shortName === '') {
            return 'unknown';
        }
        return $shortName;
    }

    protected function logGatewayFallback($method, $preferredGateway, array $attemptedGateways, array $result, array $context, $succeeded)
    {
        $payload = array(
            'method' => $method,
            'preferred_gateway' => $preferredGateway,
            'attempted_gateways' => $attemptedGateways,
            'final_gateway' => isset($result['gateway']) ? $result['gateway'] : null,
            'success' => isset($result['success']) ? (bool) $result['success'] : null,
            'fallback_succeeded' => (bool) $succeeded,
        );
        if (isset($context['confidence']) && $context['confidence'] !== '') {
            $payload['confidence'] = $context['confidence'];
        }
        if (isset($context['hint_source']) && $context['hint_source'] !== '') {
            $payload['hint_source'] = $context['hint_source'];
        }
        if (!empty($result['message'])) {
            $payload['message'] = $result['message'];
        }
        $line = '[PayPalProfileManager] gateway-fallback ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logLine($line);
    }

    protected function getLogFilePath()
    {
        if (defined('PAYPAL_PROFILE_MANAGER_LOG')) {
            return PAYPAL_PROFILE_MANAGER_LOG;
        }
        if (defined('DIR_FS_LOGS')) {
            return rtrim(DIR_FS_LOGS, '/\\') . '/paypal_profile_manager.log';
        }
        return '';
    }

    protected function logLine($line)
    {
        $logFile = $this->getLogFilePath();
        $line = rtrim($line) . PHP_EOL;
        if ($logFile !== '') {
            $directory = dirname($logFile);
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            if (@error_log($line, 3, $logFile)) {
                return;
            }
        }
        error_log($line);
    }

    protected function shouldRetry(Exception $e)
    {
        $message = $e->getMessage();
        if (stripos($message, 'not found') !== false) {
            return true;
        }
        if (stripos($message, '404') !== false) {
            return true;
        }
        if (stripos($message, 'legacy') !== false) {
            return true;
        }
        return false;
    }
}

class PayPalLegacyProfileGateway implements PayPalProfileGatewayInterface
{
    /** @var PayPal */
    protected $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function cancelProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateStatus($subscription, 'Cancel', $note);
    }

    public function suspendProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateStatus($subscription, 'Suspend', $note);
    }

    public function reactivateProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateStatus($subscription, 'Reactivate', $note);
    }

    public function getProfileStatus(array $subscription, array $context = array())
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier');
        }
        $data = array('GRPPDFields' => array('PROFILEID' => $profileId));
        $response = $this->client->GetRecurringPaymentsProfileDetails($data);
        if (!is_array($response)) {
            return array('success' => false, 'message' => 'Unable to retrieve profile details');
        }
        if ($this->hasErrors($response)) {
            return array(
                'success' => false,
                'message' => $this->formatErrors($response)
            );
        }
        $status = isset($response['STATUS']) ? $response['STATUS'] : null;
        return array('success' => true, 'status' => $status, 'profile' => $response, 'profile_source' => 'legacy');
    }

    public function updateBillingCycles(array $subscription, array $billingCycles, array $context = array())
    {
        return array('success' => false, 'message' => 'PayPal legacy profiles cannot be updated with REST operations.');
    }

    public function updatePaymentSource(array $subscription, array $paymentSource, array $context = array())
    {
        return array('success' => false, 'message' => 'PayPal legacy profiles cannot be updated with REST operations.');
    }

    protected function updateStatus(array $subscription, $action, $note)
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier');
        }
        $fields = array('PROFILEID' => $profileId, 'ACTION' => $action);
        if (strlen($note) > 0) {
            $fields['NOTE'] = $note;
        }
        $data = array('MRPPSFields' => $fields);
        $response = $this->client->ManageRecurringPaymentsProfileStatus($data);
        if ($this->hasErrors($response)) {
            return array(
                'success' => false,
                'message' => $this->formatErrors($response)
            );
        }
        return array('success' => true);
    }

    protected function extractProfileId(array $subscription)
    {
        if (isset($subscription['profile_id']) && strlen($subscription['profile_id']) > 0) {
            return $subscription['profile_id'];
        }
        if (isset($subscription['PROFILEID']) && strlen($subscription['PROFILEID']) > 0) {
            return $subscription['PROFILEID'];
        }
        return '';
    }

    protected function hasErrors($response)
    {
        return isset($response['ERRORS']) && is_array($response['ERRORS']) && count($response['ERRORS']) > 0;
    }

    protected function formatErrors($response)
    {
        if (!$this->hasErrors($response)) {
            return '';
        }
        $messages = array();
        foreach ($response['ERRORS'] as $error) {
            if (is_array($error)) {
                if (isset($error['L_LONGMESSAGE0'])) {
                    $messages[] = $error['L_LONGMESSAGE0'];
                } elseif (isset($error['L_SHORTMESSAGE0'])) {
                    $messages[] = $error['L_SHORTMESSAGE0'];
                } elseif (isset($error['L_ERRORCODE0'])) {
                    $messages[] = 'Error ' . $error['L_ERRORCODE0'];
                }
            }
        }
        if (count($messages) === 0) {
            $messages[] = 'An unknown PayPal error occurred.';
        }
        return implode(' ', $messages);
    }
}

class PayPalRestProfileGateway implements PayPalProfileGatewayInterface
{
    /** @var mixed */
    protected $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function cancelProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateRestStatus($subscription, $note, array(
            array('cancelSubscription', 'reason'),
            array('cancel', 'reason'),
            array('deactivateSubscription', 'reason'),
            array('suspendSubscription', 'note')
        ));
    }

    public function suspendProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateRestStatus($subscription, $note, array(
            array('suspendSubscription', 'reason'),
            array('suspend', 'reason'),
            array('pauseSubscription', 'reason')
        ));
    }

    public function reactivateProfile(array $subscription, $note = '', array $context = array())
    {
        return $this->updateRestStatus($subscription, $note, array(
            array('activateSubscription', 'reason'),
            array('reactivateSubscription', 'reason'),
            array('activate', 'reason'),
            array('reactivate', 'reason')
        ));
    }

    public function getProfileStatus(array $subscription, array $context = array())
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier', 'retry' => true);
        }
        $methods = array('getSubscription', 'showSubscriptionDetails', 'showSubscription', 'getBillingAgreement', 'retrieveSubscription', 'retrieveBillingAgreement');
        foreach ($methods as $method) {
            try {
                $response = $this->callRestMethod($method, array($profileId));
                if ($response === null) {
                    continue;
                }
                $normalized = $this->normalizeRestResponse($response);
                $status = $this->extractStatus($normalized);
                return array('success' => true, 'status' => $status, 'profile' => $normalized, 'profile_source' => 'rest');
            } catch (BadMethodCallException $e) {
                continue;
            } catch (Exception $e) {
                return array('success' => false, 'message' => $e->getMessage(), 'retry' => $this->shouldRetry($e));
            }
        }
        return array('success' => false, 'message' => 'PayPal REST status lookup unavailable', 'retry' => true);
    }

    public function updateBillingCycles(array $subscription, array $billingCycles, array $context = array())
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier', 'retry' => true);
        }
        $operations = array();
        if (isset($billingCycles['operations']) && is_array($billingCycles['operations'])) {
            $operations = $billingCycles['operations'];
        } else {
            if (isset($billingCycles['billing_cycles']) && is_array($billingCycles['billing_cycles'])) {
                $operations[] = array('op' => 'replace', 'path' => '/plan/billing_cycles', 'value' => $billingCycles['billing_cycles']);
            }
            if (isset($billingCycles['plan']) && is_array($billingCycles['plan'])) {
                $operations[] = array('op' => 'replace', 'path' => '/plan', 'value' => $billingCycles['plan']);
            }
            if (isset($billingCycles['pricing_scheme']) && is_array($billingCycles['pricing_scheme'])) {
                $operations[] = array('op' => 'replace', 'path' => '/plan/billing_cycles/0/pricing_scheme/fixed_price', 'value' => $billingCycles['pricing_scheme']);
            }
            if (isset($billingCycles['next_billing_time']) && $billingCycles['next_billing_time'] !== '') {
                $operations[] = array('op' => 'replace', 'path' => '/billing_info/next_billing_time', 'value' => $billingCycles['next_billing_time']);
            }
        }
        if (count($operations) === 0) {
            return array('success' => true);
        }
        return $this->patchSubscription($profileId, $operations);
    }

    public function updatePaymentSource(array $subscription, array $paymentSource, array $context = array())
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier', 'retry' => true);
        }
        $operations = array();
        if (isset($paymentSource['operations']) && is_array($paymentSource['operations'])) {
            $operations = $paymentSource['operations'];
        } else {
            if (isset($paymentSource['payment_source']) && is_array($paymentSource['payment_source'])) {
                $operations[] = array('op' => 'replace', 'path' => '/payment_source', 'value' => $paymentSource['payment_source']);
            } else {
                $value = array();
                if (isset($paymentSource['token']) && is_array($paymentSource['token'])) {
                    $value['token'] = $paymentSource['token'];
                }
                if (isset($paymentSource['card']) && is_array($paymentSource['card'])) {
                    $value['card'] = $paymentSource['card'];
                }
                if (isset($paymentSource['paypal']) && is_array($paymentSource['paypal'])) {
                    $value['paypal'] = $paymentSource['paypal'];
                }
                if (!empty($value)) {
                    $operations[] = array('op' => 'replace', 'path' => '/payment_source', 'value' => $value);
                }
            }
        }
        if (count($operations) === 0) {
            return array('success' => false, 'message' => 'No payment source data supplied.');
        }
        return $this->patchSubscription($profileId, $operations);
    }

    protected function patchSubscription($profileId, array $operations)
    {
        $normalizedOperations = $this->normalizePatchOperations($operations);
        if (count($normalizedOperations) === 0) {
            return array('success' => false, 'message' => 'No valid subscription updates provided.');
        }
        $methods = array('patchSubscription', 'updateSubscription', 'reviseSubscription', 'updateBillingAgreement', 'patchBillingAgreement');
        $payloadOptions = array(
            array($normalizedOperations),
            array(array('patch_request' => $normalizedOperations)),
            array(array('operations' => $normalizedOperations)),
        );
        $lastException = null;
        foreach ($methods as $method) {
            $methodAvailable = true;
            foreach ($payloadOptions as $arguments) {
                $callArguments = array_merge(array($profileId), $arguments);
                try {
                    $result = $this->callRestMethod($method, $callArguments);
                    if ($result !== null) {
                        return array('success' => true, 'profile' => $this->normalizeRestResponse($result), 'profile_source' => 'rest');
                    }
                    return array('success' => true, 'profile_source' => 'rest');
                } catch (BadMethodCallException $e) {
                    $lastException = $e;
                    $methodAvailable = false;
                    break;
                } catch (Exception $e) {
                    $details = $this->extractErrorDetailsFromException($e);
                    $result = array('success' => false, 'message' => $e->getMessage());
                    if (!empty($details)) {
                        $result['details'] = $details;
                    }
                    if ($this->shouldRetry($e)) {
                        $result['retry'] = true;
                    }
                    return $result;
                }
            }
            if (!$methodAvailable) {
                continue;
            }
        }
        if ($lastException instanceof BadMethodCallException) {
            return array('success' => false, 'message' => $lastException->getMessage(), 'retry' => true);
        }
        return array('success' => false, 'message' => 'PayPal REST update unavailable', 'retry' => true);
    }

    protected function normalizePatchOperations(array $operations)
    {
        $normalized = array();
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            if (!isset($operation['op']) || !isset($operation['path'])) {
                continue;
            }
            $entry = array(
                'op' => strtolower($operation['op']),
                'path' => $this->normalizePatchPath($operation['path'])
            );
            if (array_key_exists('value', $operation)) {
                $entry['value'] = $operation['value'];
            }
            if (array_key_exists('from', $operation)) {
                $entry['from'] = $operation['from'];
            }
            $normalized[] = $entry;
        }
        return $normalized;
    }

    protected function normalizePatchPath($path)
    {
        if (!is_string($path) || $path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    protected function extractErrorDetailsFromException(Exception $e)
    {
        if (method_exists($e, 'getData')) {
            $data = $e->getData();
            if (is_array($data)) {
                return $data;
            }
        }
        if (property_exists($e, 'result') && is_array($e->result)) {
            return $e->result;
        }
        $message = $e->getMessage();
        if (is_string($message)) {
            $decoded = json_decode($message, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return array();
    }

    protected function updateRestStatus(array $subscription, $note, array $methods)
    {
        $profileId = $this->extractProfileId($subscription);
        if (!$profileId) {
            return array('success' => false, 'message' => 'Missing profile identifier', 'retry' => true);
        }
        $payloadNote = strlen($note) > 0 ? $note : 'Customer request';
        $lastException = null;
        foreach ($methods as $entry) {
            list($method, $key) = $entry;
            $arguments = array($profileId, array($key => $payloadNote));
            try {
                $result = $this->callRestMethod($method, $arguments);
                if ($result !== null) {
                    return array('success' => true);
                }
            } catch (BadMethodCallException $e) {
                $lastException = $e;
                continue;
            } catch (Exception $e) {
                if ($this->shouldRetry($e)) {
                    return array('success' => false, 'message' => $e->getMessage(), 'retry' => true);
                }
                return array('success' => false, 'message' => $e->getMessage());
            }
        }
        if ($lastException instanceof BadMethodCallException) {
            return array('success' => false, 'message' => $lastException->getMessage(), 'retry' => true);
        }
        return array('success' => false, 'message' => 'PayPal REST update unavailable', 'retry' => true);
    }

    protected function callRestMethod($method, $arguments = array())
    {
        $candidates = array($method);
        $candidates[] = lcfirst($method);
        $candidates[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
        foreach (array_unique($candidates) as $candidate) {
            if (method_exists($this->client, $candidate)) {
                return call_user_func_array(array($this->client, $candidate), $arguments);
            }
        }
        throw new BadMethodCallException('Method ' . $method . ' is not available on the PayPal REST client');
    }

    protected function normalizeRestResponse($response)
    {
        if (is_array($response)) {
            if (isset($response['result']) && is_array($response['result'])) {
                return $response['result'];
            }
            return $response;
        }
        if (is_object($response)) {
            if (method_exists($response, 'toArray')) {
                return $response->toArray();
            }
            return json_decode(json_encode($response), true);
        }
        return array();
    }

    protected function extractStatus(array $response)
    {
        if (isset($response['status'])) {
            return $response['status'];
        }
        if (isset($response['STATUS'])) {
            return $response['STATUS'];
        }
        if (isset($response['status_details']['status'])) {
            return $response['status_details']['status'];
        }
        if (isset($response['status']['value'])) {
            return $response['status']['value'];
        }
        return null;
    }

    protected function extractProfileId(array $subscription)
    {
        if (isset($subscription['profile_id']) && strlen($subscription['profile_id']) > 0) {
            return $subscription['profile_id'];
        }
        if (isset($subscription['PROFILEID']) && strlen($subscription['PROFILEID']) > 0) {
            return $subscription['PROFILEID'];
        }
        if (isset($subscription['id']) && strlen($subscription['id']) > 0) {
            return $subscription['id'];
        }
        return '';
    }

    protected function shouldRetry(Exception $e)
    {
        $message = $e->getMessage();
        if (stripos($message, 'not found') !== false) {
            return true;
        }
        if (stripos($message, '404') !== false) {
            return true;
        }
        if (stripos($message, 'legacy') !== false) {
            return true;
        }
        return false;
    }
}
