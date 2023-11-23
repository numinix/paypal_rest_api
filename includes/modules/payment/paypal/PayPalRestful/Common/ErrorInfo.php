<?php
/**
 * Common error-information class for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */

namespace PayPalRestful\Common;

class ErrorInfo
{
    /**
     * Error information, when a CURL or RESTful error occurs.
     */
    protected $errorInfo;

    public function __construct()
    {
        $this->resetErrorInfo();
    }

    public function getErrorInfo(): array
    {
        return $this->errorInfo;
    }

    protected function setErrorInfo(int $errNum, string $errMsg, int $curlErrno = 0, $response = [])
    {
        $name = $response['name'] ?? 'n/a';
        $message = $response['message'] ?? 'n/a';
        $details = $response['details'] ?? 'n/a';
        $this->errorInfo = compact('errNum', 'errMsg', 'curlErrno', 'name', 'message', 'details');
    }

    protected function resetErrorInfo()
    {
        $this->errorInfo = [
            'errMsg' => '',
            'errNum' => 0,
            'curlErrno' => 0,
            'name' => '',
            'message' => '',
            'details' => [],
        ];
    }
}
