<?php
class OPRC_Address_Lookup_GetAddress_Provider extends OPRC_Address_Lookup_AbstractProvider
{
    /**
     * Performs the lookup for the supplied postal code using the GetAddress API.
     *
     * @param string $postalCode
     * @param array $context
     *
     * @return array
     * @throws Exception
     */
    public function lookup($postalCode, array $context = [])
    {
        $postalCode = strtoupper(trim($postalCode));
        if ($postalCode === '') {
            return [];
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            throw new Exception('GetAddress API key is not configured.');
        }

        list($perPage, $maxPages) = $this->resolvePaginationConfig();

        $results = [];
        $page = 1;
        $totalAvailable = null;
        $fetchedCount = 0;

        while (true) {
            if ($maxPages > 0 && $page > $maxPages) {
                break;
            }

            $url = $this->buildRequestUrl($postalCode, $apiKey, $page, $perPage);
            $responseBody = $this->performRequest($url);
            $payload = $this->decodeResponse($responseBody);

            if (!isset($payload['addresses']) || !is_array($payload['addresses'])) {
                $message = null;
                if (isset($payload['Message'])) {
                    $message = (string)$payload['Message'];
                } elseif (isset($payload['message'])) {
                    $message = (string)$payload['message'];
                }

                if ($page === 1 && $message !== null) {
                    throw new Exception($message);
                }

                break;
            }

            if (empty($payload['addresses'])) {
                break;
            }

            $pageAddresses = $payload['addresses'];
            $fetchedCount += count($pageAddresses);
            foreach ($pageAddresses as $address) {
                if (!is_array($address)) {
                    continue;
                }

                $formatted = $this->formatAddress($address, $postalCode, $context);
                if ($formatted !== null) {
                    $results[] = $formatted;
                }
            }

            $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
            $pageCount = isset($summary['count']) ? (int)$summary['count'] : count($pageAddresses);
            if (isset($summary['total'])) {
                $totalAvailable = (int)$summary['total'];
            }

            if ($totalAvailable !== null && $fetchedCount >= $totalAvailable) {
                break;
            }

            if ($pageCount < $perPage) {
                break;
            }
            $page++;
        }

        return $results;
    }

    public function isConfigured()
    {
        return $this->resolveApiKey() !== '';
    }

    /**
     * @return string
     */
    protected function resolveApiKey()
    {
        if (defined('OPRC_ADDRESS_LOOKUP_API_KEY')) {
            $apiKey = trim(OPRC_ADDRESS_LOOKUP_API_KEY);
            if ($apiKey !== '') {
                return $apiKey;
            }
        }

        if (defined('OPRC_GETADDRESS_API_KEY')) {
            return trim(OPRC_GETADDRESS_API_KEY);
        }

        return '';
    }

    /**
     * @param string $postalCode
     * @param string $apiKey
     * @return string
     */
    protected function buildRequestUrl($postalCode, $apiKey, $page = 0, $perPage = null)
    {
        $baseUrl = $this->getConfig('endpoint', defined('OPRC_GETADDRESS_ENDPOINT') ? OPRC_GETADDRESS_ENDPOINT : 'https://api.getaddress.io');
        $baseUrl = rtrim($baseUrl, '/');

        $queryParameters = [
            'api-key' => $apiKey,
            'expand' => 'true',
        ];

        if ($perPage !== null) {
            $queryParameters['per-page'] = $perPage;
        }

        $page = (int)$page;
        if ($page > 0) {
            $queryParameters['page'] = $page;
        }

        $query = http_build_query($queryParameters);

        return sprintf('%s/find/%s?%s', $baseUrl, rawurlencode($postalCode), $query);
    }

    /**
     * @return array
     */
    protected function resolvePaginationConfig()
    {
        $perPage = (int)$this->getConfig('per_page', 100);
        if ($perPage < 1) {
            $perPage = 1;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }

        $maxPages = $this->getConfig('max_pages', 0);
        if (is_string($maxPages) && $maxPages !== '' && !is_numeric($maxPages)) {
            $maxPages = 0;
        }
        $maxPages = (int)$maxPages;
        if ($maxPages < 0) {
            $maxPages = 0;
        }

        return [$perPage, $maxPages];
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    protected function performRequest($url)
    {
        $error = null;
        $body = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'OPRC GetAddress Provider');

            $body = curl_exec($ch);
            if ($body === false) {
                $error = curl_error($ch);
            }

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $status >= 400) {
                if (!$error && isset($body[0])) {
                    $error = sprintf('HTTP %s returned by GetAddress.', $status);
                }
            }
        }

        if ($body === '' || $body === false) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => "User-Agent: OPRC GetAddress Provider\r\n",
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false && $error === null) {
                $error = 'Unable to reach GetAddress API.';
            }
        }

        if ($body === false || $body === '') {
            if ($error === null) {
                $error = 'Empty response from GetAddress API.';
            }

            throw new Exception($error);
        }

        return $body;
    }

    /**
     * @param string $responseBody
     * @return array
     * @throws Exception
     */
    protected function decodeResponse($responseBody)
    {
        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Unable to decode GetAddress response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @param array $address
     * @param string $fallbackPostcode
     * @return array|null
     */
    protected function formatAddress(array $address, $fallbackPostcode, array $context = [])
    {
        $town = $this->extractValue($address, 'town_or_city');
        $county = $this->extractValue($address, 'county');
        $postcode = $this->extractValue($address, 'postcode');
        if ($postcode === '') {
            $postcode = $fallbackPostcode;
        }

        $formattedAddress = [];
        if (isset($address['formatted_address']) && is_array($address['formatted_address'])) {
            $formattedAddress = array_filter(array_map('trim', $address['formatted_address']));
        }

        $line1 = $this->extractValue($address, 'line_1');
        $line2 = $this->extractValue($address, 'line_2');
        $line3 = $this->extractValue($address, 'line_3');
        $dependentLocality = $this->extractValue($address, 'dependent_locality');
        $doubleDependentLocality = $this->extractValue($address, 'double_dependent_locality');

        $labelParts = $formattedAddress;
        if (empty($labelParts)) {
            $labelParts = array_filter([$line1, $line2, $line3, $town, $county, $postcode]);
        }

        if ($postcode !== '' && !in_array($postcode, $labelParts, true)) {
            $labelParts[] = $postcode;
        }

        $labelParts = array_values(array_unique($labelParts));
        if (empty($labelParts)) {
            return null;
        }

        $label = implode(', ', $labelParts);

        $street = $line1;
        if ($street === '') {
            $street = $this->extractValue($address, 'thoroughfare');
        }
        if ($street === '' && isset($formattedAddress[0])) {
            $street = $formattedAddress[0];
        }
        if ($street === '' && isset($labelParts[0])) {
            $street = $labelParts[0];
        }

        $secondaryParts = array_filter([
            $line2,
            $line3,
            $dependentLocality,
            $doubleDependentLocality,
        ]);

        if (empty($secondaryParts)) {
            $fallbackLines = array_slice($formattedAddress, 1);
            if (empty($fallbackLines)) {
                $fallbackLines = array_slice($labelParts, 1);
            }

            foreach ($fallbackLines as $part) {
                if ($part === '') {
                    continue;
                }
                if ($town !== '' && strcasecmp($part, $town) === 0) {
                    continue;
                }
                if ($postcode !== '' && stripos($part, $postcode) !== false) {
                    continue;
                }
                if ($street !== '' && strcasecmp($part, $street) === 0) {
                    continue;
                }

                $secondaryParts[] = $part;
            }
        }

        $secondaryParts = array_values(array_unique($secondaryParts));
        $secondaryLine = implode(', ', $secondaryParts);

        if ($street === '' && $secondaryLine === '') {
            $street = $label;
        }

        $fields = [
            'street_address' => $street,
            'suburb' => $secondaryLine,
            'city' => $town,
            'state' => $county,
            'postcode' => $postcode,
        ];

        $companyCandidates = [
            $this->extractValue($address, 'organisation_name'),
            $this->extractValue($address, 'sub_building_name'),
            $this->extractValue($address, 'building_name'),
        ];
        foreach ($companyCandidates as $company) {
            if ($company === '') {
                continue;
            }
            if ($street !== '' && strcasecmp($company, $street) === 0) {
                continue;
            }

            $fields['company'] = $company;
            break;
        }

        if (isset($context['zone_country_id'])) {
            $fields['zone_country_id'] = (string)$context['zone_country_id'];
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = (string)$value;
        }

        return [
            'label' => $label,
            'fields' => $fields,
            'meta' => [
                'provider' => 'getaddress',
            ],
        ];
    }

    /**
     * @param array $address
     * @param string $key
     * @return string
     */
    protected function extractValue(array $address, $key)
    {
        if (!isset($address[$key])) {
            return '';
        }

        $value = $address[$key];
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('trim', $value)));
        }

        return trim((string)$value);
    }
}

return [
    'key' => 'getaddress',
    'title' => 'GetAddress.io',
    'class' => 'OPRC_Address_Lookup_GetAddress_Provider',
    'file' => __FILE__,
];
// eof
