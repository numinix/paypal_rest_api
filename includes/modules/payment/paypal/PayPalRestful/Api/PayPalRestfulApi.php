<?php
/**
 * PayPalRestfulApi.php communications class for PayPal Rest payment module
 *
 * Applicable PayPal documentation:
 *
 * - https://developer.paypal.com/docs/checkout/advanced/processing/
 * - https://stackoverflow.com/questions/14451401/how-do-i-make-a-patch-request-in-php-using-curl
 * - https://developer.paypal.com/docs/checkout/standard/customize/
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */

namespace PayPalRestful\Api;

use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Logger;
use PayPalRestful\Token\TokenCache;

/**
 * PayPal REST API (see https://developer.paypal.com/api/rest/)
 */
class PayPalRestfulApi extends ErrorInfo
{
    // -----
    // Constants used to set the class variable errorInfo['errNum'].
    //
    public const ERR_NO_ERROR      = 0;    //-No error occurred, initial value

    public const ERR_NO_CHANNEL    = -1;   //-Set if the curl_init fails; no other requests are honored
    public const ERR_CURL_ERROR    = -2;   //-Set if the curl_exec fails.  The curlErrno variable contains the curl_errno and errMsg contains curl_error

    // -----
    // Constants that define the test and production endpoints for the API requests.
    //
    protected const ENDPOINT_SANDBOX = 'https://api-m.sandbox.paypal.com/';
    protected const ENDPOINT_PRODUCTION = 'https://api-m.paypal.com/';

    // -----
    // PayPal constants associated with an order's current 'status'.
    //
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_CAPTURED = 'CAPTURED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_DENIED = 'DENIED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    //- The order requires an action from the payer (e.g. 3DS authentication). Redirect the payer to the "rel":"payer-action" HATEOAS
    //    link returned as part of the response prior to authorizing or capturing the order.
    public const STATUS_PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_REFUNDED = 'REFUNDED';
    public const STATUS_SAVED = 'SAVED';
    public const STATUS_VOIDED = 'VOIDED';

    /**
     * Variables associated with interface logging;
     *
     * @log Logger object, logs debug tracing information.
     */
    protected $log;

    /**
     * Variables associated with interface logging;
     *
     * @token TokenCache object, caches any access-token retrieved from PayPal.
     */
    protected $tokenCache;

    /**
     * Sandbox or production? Set during class construction.
     */
    protected $endpoint;

    /**
     * OAuth client id and secret, set during class construction.
     */
    private $clientId;
    private $clientSecret;
    
    /**
     * The CURL channel, initialized during construction.
     */
    protected $ch = false;

    /**
     * Options for cURL. Defaults to preferred (constant) options.  Used by
     * the curlGet and curlPost methods.
     */
    protected $curlOptions = [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ];

    // -----
    // Class constructor, saves endpoint (live vs. sandbox), clientId and clientSecret
    //
    public function __construct(string $endpoint_type, string $client_id, string $client_secret)
    {
        $this->endpoint = ($endpoint_type === 'live') ? self::ENDPOINT_PRODUCTION : self::ENDPOINT_SANDBOX;
        $this->clientId = $client_id;
        $this->clientSecret = $client_secret;

        $this->ch = curl_init();
        if ($this->ch === false) {
            $this->setErrorInfo(self::ERR_NO_CHANNEL, 'Unable to initialize the CURL channel.');
            trigger_error($this->errMsg, E_USER_WARNING);
        }

        $this->log = new Logger();
        $this->tokenCache = new TokenCache($client_secret);
    }

    // ----
    // Class destructor, close the CURL channel if the channel's open (i.e. not false).  Also an 'alias' for the
    // public 'close' method.
    //
    public function __destruct()
    {
        $this->close();
    }
    public function close()
    {
        if ($this->ch !== false) {
            curl_close($this->ch);
            $this->ch = false;
        }
    }

    // ===== Start Non-token Methods =====

    public function createOrder(array $order_request)
    {
        $this->log->write('==> Start createOrder', true);
        $response = $this->curlPost('v2/checkout/orders', $order_request);
        $this->log->write("==> End createOrder\n", true);
        return $response;
    }

    public function getOrderStatus(string $paypal_order_id)
    {
        $this->log->write('==> Start getOrderStatus', true);
        $response = $this->curlGet("v2/checkout/orders/$paypal_order_id");
        $this->log->write("==> End getOrderStatus\n", true);
        return $response;
    }

    public function confirmPaymentSource(string $paypal_order_id, array $payment_source)
    {
        $this->log->write('==> Start confirmPaymentSource', true);
        $paypal_options = [
            'payment_source' => $payment_source,
        ];
        $response = $this->curlPost("v2/checkout/orders/$paypal_order_id/confirm-payment-source", $paypal_options);
        $this->log->write("==> End confirmPaymentSource\n", true);
        return $response;
    }

    public function captureOrder(string $paypal_order_id)
    {
        $this->log->write('==> Start captureOrder', true);
        $response = $this->curlPost("v2/checkout/orders/$paypal_order_id/capture");
        $this->log->write("==> End captureOrder\n", true);
        return $response;
    }

    public function authorizeOrder(string $paypal_order_id)
    {
        $this->log->write('==> Start authorizeOrder', true);
        $response = $this->curlPost("v2/checkout/orders/$paypal_order_id/authorize");
        $this->log->write("==> End authorizeOrder\n", true);
        return $response;
    }

    public function getAuthorizationDetails(string $paypal_auth_id)
    { 
        $this->log->write('==> Start getAuthorizationDetails', true);
        $response = $this->curlPost("v2/payments/authorizations/$paypal_auth_id");
        $this->log->write("==> End getAuthorizationDetails\n", true);
        return $response;
    }

    public function capturePayment(string $paypal_auth_id, string $invoice_id, string $payer_note, )
    { 
        $this->log->write('==> Start capturePayment', true);
        $response = $this->curlPost("v2/payments/authorizations/$paypal_auth_id/capture");
        $this->log->write("==> End capturePayment\n", true);
        return $response;
    }

    public function reAuthorizePayment(string $paypal_auth_id, string $currency_code, string $value)
    { 
        $this->log->write('==> Start reAuthorizePayment', true);
        $amount = [
            'amount' => [
                'currency_code' => $currency_code,
                'value' => $value,
            ],
        ];
        $response = $this->curlPost("v2/payments/authorizations/$paypal_auth_id/reauthorize", $amount);
        $this->log->write("==> End reAuthorizePayment\n", true);
        return $response;
    }

    public function voidPayment(string $paypal_auth_id)
    { 
        $this->log->write('==> Start voidPayment', true);
        $response = $this->curlPost("v2/payments/authorizations/$paypal_auth_id/void");
        $this->log->write("==> End voidPayment\n", true);
        return $response;
    }

    public function getCapturedDetails(string $paypal_capture_id)
    { 
        $this->log->write('==> Start getCapturedDetails', true);
        $response = $this->curlPost("v2/payments/captures/$paypal_capture_id");
        $this->log->write("==> End getCapturedDetails\n", true);
        return $response;
    }
    
    public function refundCaptureFull(string $paypal_capture_id)
    {
    }
    public function refundCapturePartial(string $paypal_capture_id, string $currency_code, string $value)
    {
    }
    protected function refundCapture($paypal_capture_id, array $amount)
    {
    }

    // -----
    // Update a PayPal order with CREATED or APPROVED status **only**.
    //
    // Parameters:
    // - paypal_order_id
    //      The 'id' value returned by PayPal when the order was created or approved.
    // - update_order_request
    //      The to-be-updated contents for the order, presumed to have been created by \Zc2Pp\UpdatePayPalOrderRequest.php
    //
    // Return Values:
    // - response
    //      Returns false if an error is detected; the caller retrieves the error details via the
    //          getErrorInfo method.
    //      On success, returns an associative array containing the PayPal response.
    //
    public function updateOrder(string $paypal_order_id, array $update_order_request)
    {
        $this->log->write("==> Start updateOrder ($paypal_order_id).  Current:\n" . $this->log->logJSON($order_request_current) . "\nUpdate:\n" . $this->log->logJSON($update_order_request), true);
        $response = $this->curlPatch("v2/checkout/orders/$paypal_order_id", $update_order_request);
        $this->log->write('==> End updateOrder', true);
        return $response;
    }

    // ===== End Non-token Methods =====

    // ===== Start Token Handling Methods =====

    // -----
    // Validates the supplied client-id/secret; used during admin/store initialization to
    // auto-disable the associated payment method if the credentials aren't valid.
    //
    // Normally, this method is called requesting that any saved token be used, to cut
    // down on API requests.  The one exception is during the payment-module's configuration
    // in the admin, where the currently-configured credentials need to be specifically validated!
    //
    public function validatePayPalCredentials(bool $use_saved_token = true): bool
    {
        return ($this->getOAuth2Token($this->clientId, $this->clientSecret, $use_saved_token) !== '');
    }

    // -----
    // Retrieves an OAuth token from PayPal to use in follow-on requests, returning the token
    // to the caller.
    //
    // Normally, the method's called without the 3rd parameter, so it will check to see if a
    // previously-saved token is available to cut down on API calls.  The validatePayPalCredentials
    // method is an exclusion, as it's used during the admin configuration of the payment module to
    // ensure that the client id/secret are validated.
    //
    protected function getOAuth2Token(string $client_id, string $client_secret, bool $use_saved_token = true): string
    {
        if ($this->ch === false) {
            $this->ch = curl_init();
            if ($this->ch === false) {
                $this->setErrorInfo(self::ERR_NO_CHANNEL, 'Unable to initialize the CURL channel.');
                return '';
            }
        }

        if ($use_saved_token === true) {
            $token = $this->tokenCache->get();
            if ($token !== '') {
                return $token;
            }
        }

        $additional_curl_options = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
            ]
        ];
        $response = $this->curlPost('v1/oauth2/token', ['grant_type' => 'client_credentials'], $additional_curl_options, false);

        $token = '';
        if ($response !== false) {
            $token = $response['access_token'];
            if ($use_saved_token === true) {
                $this->tokenCache->save($token, $response['expires_in']);
            }
         }

        return $token;
    }

    // -----
    // Sets the common authorization header into the CURL options for a PayPal Restful request.
    //
    // If the request to retrieve the token fails, an empty array is returned; otherwise,
    // the authorization-header containing the successfully-retrieved token is merged into
    // supplied array of CURL options and returned.
    //
    protected function setAuthorizationHeader(array $curl_options): array
    {
        $oauth2_token = $this->getOAuth2Token($this->clientId, $this->clientSecret);
        if ($oauth2_token === '') {
            return [];
        }

        $curl_options[CURLOPT_HTTPHEADER] = [
            'Content-Type: application/json',
            "Authorization: Bearer $oauth2_token",
            'Prefer: return=representation',
        ];
        return $curl_options;
    }

    // ===== End Token Handling Methods =====

    // ===== Start CURL Interface Methods =====

    // -----
    // A common method for all POST requests to PayPal.
    //
    // Parameters:
    // - option
    //     The option to be performed, e.g. v2/checkout/orders
    // - options_array
    //     An (optional) array of options to be supplied, dependent on the 'option' to be sent.
    // - additional_curl_options
    //     An array of additional CURL options to be applied.
    // - token_required
    //     An indication as to whether/not an authorization header is to be include.
    //
    // Return Values:
    // - On success, an associative array containing the PayPal response.
    // - On failure, returns false.  The details of the failure can be interrogated via the getErrorInfo method.
    //
    protected function curlPost(string $option, array $options_array = [], array $additional_curl_options = [], bool $token_required = true)
    {
        if ($this->ch === false) {
            $this->ch = curl_init();
            if ($this->ch === false) {
                $this->setErrorInfo(self::ERR_NO_CHANNEL, 'Unable to initialize the CURL channel.');
                return false;
            }
        }

        $url = $this->endpoint . $option;
        $curl_options = array_replace($this->curlOptions, [CURLOPT_POST => true, CURLOPT_URL => $url], $additional_curl_options);

        // -----
        // If a token is required, i.e. it's not a request to gather an access-token, use
        // the existing token to set the request's authorization.  Note that the method
        // being called will check to see if the current token has expired and will request
        // an update, if needed.
        //
        // Set the CURL options to use for this current request and then, if the token is NOT
        // required (i.e. the request is to retrieve an access-token), remove the site's
        // PayPal credentials from the posted options so that they're not exposed in subsequent
        // API logs.
        //
        if ($token_required === false) {
            if (count($options_array) !== 0) {
                $curl_options[CURLOPT_POSTFIELDS] = http_build_query($options_array);
            }
        } else {
            $curl_options = $this->setAuthorizationHeader($curl_options);
            if (count($curl_options) === 0) {
                return false;
            }
            if (count($options_array) !== 0) {
                $curl_options[CURLOPT_POSTFIELDS] = json_encode($options_array);
            }
        }

        curl_reset($this->ch);
        curl_setopt_array($this->ch, $curl_options);
        if ($token_required === false) {
            unset($curl_options[CURLOPT_POSTFIELDS]);
        }
        return $this->issueRequest('curlPost', $option, $curl_options);
    }

    // -----
    // A common method for all GET requests to PayPal.
    //
    // Parameters:
    // - option
    //      The option to be performed, e.g. v2/checkout/orders/{id}
    // - options_array
    //      An (optional) array of options to be supplied, dependent on the 'option' to be sent.
    //
    // Return Values:
    // - On success, an associative array containing the PayPal response.
    // - On failure, returns false.  The details of the failure can be interrogated via the getErrorInfo method.
    //
    protected function curlGet($option, $options_array = [])
    {
        if ($this->ch === false) {
            $this->ch = curl_init();
            if ($this->ch === false) {
                $this->setErrorInfo(self::ERR_NO_CHANNEL, 'Unable to initialize the CURL channel.');
                return false;
            }
        }

        $url = $this->endpoint . $option;
        if (count($options_array) !== 0) {
            $url .= '?' . http_build_query($options_array);
        }
        curl_reset($this->ch);
        $curl_options = array_replace($this->curlOptions, [CURLOPT_HTTPGET => true, CURLOPT_URL => $url]);  //-HTTPGET Needed since we might be toggling between GET and POST requests
        $curl_options = $this->setAuthorizationHeader($curl_options);
        if (count($curl_options) === 0) {
            return null;
        }

        curl_setopt_array($this->ch, $curl_options);
        return $this->issueRequest('curlGet', $option, $curl_options);
    }

    // -----
    // A common method for all PATCH requests to PayPal.
    //
    // Parameters:
    // - option
    //     The option to be performed, e.g. v2/checkout/orders/{id}
    // - options_array
    //     An (optional) array of options to be supplied, dependent on the 'option' to be sent.
    //
    // Return Values:
    // - On success, an associative array containing the PayPal response.
    // - On failure, returns false.  The details of the failure can be interrogated via the getErrorInfo method.
    //
    //
    protected function curlPatch($option, $options_array = [])
    {
        if ($this->ch === false) {
            $this->ch = curl_init();
            if ($this->ch === false) {
                $this->setErrorInfo(self::ERR_NO_CHANNEL, 'Unable to initialize the CURL channel.');
                return false;
            }
        }

        $url = $this->endpoint . $option;
        $curl_options = array_replace($this->curlOptions, [CURLOPT_POST => true, CURLOPT_CUSTOMREQUEST => 'PATCH', CURLOPT_URL => $url]);
        $curl_options = $this->setAuthorizationHeader($curl_options);
        if (count($curl_options) === 0) {
            return false;
        }

        if (count($options_array) !== 0) {
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($options_array);
        }
        curl_reset($this->ch);
        curl_setopt_array($this->ch, $curl_options);
        return $this->issueRequest('curlPatch', $option, $curl_options);
    }

    protected function issueRequest(string $request_type, string $option, array $curl_options)
    {
        // -----
        // Issue the CURL request.
        //
        $curl_response = curl_exec($this->ch);

        // -----
        // If a CURL error is indicated, call the common error-handling method to record that error.
        //
        if ($curl_response === false) {
            $response = false;
            $this->handleCurlError($request_type, $option, $curl_options);
        // -----
        // Otherwise, a response was returned.  Call the common response-handler to determine
        // whether or not an error occurred.
        //
        } else {
            $response = $this->handleResponse($request_type, $option, $curl_options, $curl_response);
        }
        return $response; 
    }

    // -----
    // Protected method, called by curlGet and curlPost when the curl_exec itself
    // returns an error.  Set the internal variables to capture the error information
    // and log (if enabled) to the PayPal logfile.
    //
    protected function handleCurlError(string $method, string $option, array $curl_options)
    {
        $this->setErrorInfo(self::ERR_CURL_ERROR, curl_error($this->ch), curl_errno($this->ch));
        curl_reset($this->ch);
        $this->log->write("handleCurlError for $method ($option) : CURL error (" . $this->log->logJSON($this->errorInfo) . "\nCURL Options:\n" . $this->log->logJSON($curl_options));
    }

    // -----
    // Protected method, called by curlGet and curlPost when no CURL error is reported.
    //
    // We'll check the HTTP response code returned by PayPal and take possibly option-specific
    // actions.
    //
    // Returns false if an error is detected, otherwise an associative array containing
    // the PayPal response.
    //
    protected function handleResponse(string $method, string $option, array $curl_options, $response)
    {
        // -----
        // Decode the PayPal response into an associative array, retrieve the httpCode associated
        // with the response and 'reset' the errorInfo property.
        //
        $response = json_decode($response, true);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->setErrorInfo($httpCode, '', 0, []);

        // -----
        // If no error, simply return the associated response.
        //
        // 200: Request succeeded
        // 201: A POST method successfully created a resource.
        // 204: No content returned; implies successful completion of an updateOrder request.
        //
        if ($httpCode === 200 || $httpCode === 201 || $httpCode === 204) {
            $this->log->write("The $method ($option) request was successful ($httpCode).\n" . $this->log->logJSON($response));
            return $response;
        }

        $errMsg = '';
        switch ($httpCode) {
            // -----
            // 401: The access token has expired, noting that this "shouldn't" happen.
            //
            case 401:
                $this->tokenCache->clear();
                $errMsg = 'An expired-token error was received.';
                trigger_error($errMsg, E_USER_WARNING);
                break;

            // -----
            // 400: A general, usually interface-related, error occurred.
            // 403: Permissions error, the client doesn't have access to the requested endpoint.
            // 404: Something was not found.
            // 422: Unprocessable entity, kind of like 400.
            // 429: Rate Limited (you're making too many requests too quickly; you should reduce your rate of requests to stay within our Acceptable Useage Policy)
            // 500: Server Error
            // 503: Service Unavailable (our machine is currently down for maintenance; try your request again later)
            //
            case 400:
            case 403:
            case 404:
            case 422:
            case 429:
            case 500:
            case 503:
                break;

            // -----
            // Anything else wasn't expected.  Create a warning-level log indicating the
            // issue and that the response wasn't 'valid' and indicate that the
            // slamming timeout has started for some.
            //
            default:
                $errMsg = "An unexpected response ($httpCode) was returned from PayPal.";
                trigger_error($errMsg, E_USER_WARNING);
                break;
        }
        
        // -----
        // Note the error information in the errorInfo array, log a message to the PayPal log and
        // let the caller know that the request was unsuccessful.
        //
        $this->setErrorInfo($httpCode, $errMsg, 0, $response);
        $this->log->write("The $method ($option) request was unsuccessful.\n" . $this->log->logJSON($this->errorInfo) . "\nCURL Options: " . $this->log->logJSON($curl_options));

        return false;
    }
    // ===== End CURL Interface Methods =====
}
