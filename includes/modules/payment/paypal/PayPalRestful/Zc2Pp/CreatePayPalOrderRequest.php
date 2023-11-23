<?php
/**
 * Debug logging class for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Zc2Pp;

use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Logger;

class CreatePayPalOrderRequest extends ErrorInfo
{
    /**
     * Debug interface, shared with the PayPalRestfulApi class.
     */
    protected $log; //- An instance of the Logger class, logs debug tracing information.

    /**
     * The request to be submitted to a v2/orders/create PayPal endpoint.
     */
    protected $request;

    // -----
    // Constructor.  "Converts" a Zen Cart order into an PayPal /orders/create object.
    //
    public function __construct($order)
    {
        $this->log = new Logger();
    }
}
