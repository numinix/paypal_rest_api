<?php
/**
 * PayPal REST API Webhook Object
 * This class is just a model to hold all the content of the incoming webhook data
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte June 2025 $
 *
 * Last updated: v1.2.0
 */

namespace PayPalAdvancedCheckout\Webhooks;

class WebhookObject
{
    /**
     * @var array
     */
    protected $jsonBody = [];

    /**
     * @var string
     */
    protected $method;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $rawBody = '';

    /**
     * @var string
     */
    protected $userAgent = '';

    /**
     * @var array
     */
    protected $metadata = [];

    public function __construct(
        string $method, // request method
        array $headers, // request headers
        string $rawBody = '', // request body, unaltered
        string $userAgent = '', // request User Agent
        array $metadata = [] // optional misc meta info
    ) {
        $this->method = $method;
        $this->headers = $headers;
        $this->rawBody = $rawBody;
        $this->userAgent = $userAgent;
        $this->metadata = $metadata;

        if (empty($this->rawBody)) {
            return;
        }
        $this->jsonBody = \json_decode($this->rawBody, true);
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getJsonBody(): array
    {
        return $this->jsonBody;
    }

    /**
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * Add more to the metadata array; note that matching array keys will overwrite
     *
     * @param array $metadata
     */
    public function addMetadata(array $metadata): void
    {
        $this->metadata += $metadata;
    }
}
