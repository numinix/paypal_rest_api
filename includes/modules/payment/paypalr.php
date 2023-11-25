<?php
/**
 * paypalt.php payment module class for PayPal RESTful API payment method
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 Nov 21 Modified in v1.5.8a $
 */
/**
 * Load the support class' auto-loader.
 */
require DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\Logger;
use PayPalRestful\Token\TokenCache;
use PayPalRestful\Zc2Pp\Amount;
use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;

/**
 * The PayPal payment module using PayPal's RESTful API (v2)
 */
class paypalr extends base
{
    protected const CURRENT_VERSION = '1.0.0-beta1';

    /**
     * name of this module
     *
     * @var string
     */
    public $code;

    /**
     * displayed module title
     *
     * @var string
     */
    public $title;

    /**
     * displayed module description
     *
     * @var string
     */
    public $description = '';

    /**
     * module status - set based on various config and zone criteria
     *
     * @var boolean
     */
    public $enabled;

    /**
     * Installation 'check' flag
     *
     * @var boolean
     */
    protected $_check;

    /**
     * the zone to which this module is restricted for use
     *
     * @var int
     */
    public $zone;

    /**
     * debugging flags
     *
     * @var boolean
     */
    public $emailAlerts;

    /**
     * sort order of display
     *
     * @var int
     */
    public $sort_order = 0;

    /**
     * order status setting for pending orders
     *
     * @var int
     */
    protected $order_pending_status = 1;

    /**
     * order status setting for completed orders
     *
    * @var int
     */
    public $order_status;

    /**
     * URLs used during checkout if this is the selected payment method
     *
     * @var string
     */
    public $form_action_url;
    public $ec_redirect_url;

    /**
     * Debug interface, shared with the PayPalRestfulApi class.
     */
    protected $log; //- An instance of the Logger class, logs debug tracing information.

    /**
     * An instance of the PayPalRestfulApi class.
     *
     * @var object PayPalRestfulApi
     */
    protected $ppr;

    /**
     * class constructor
     */
    public function __construct()
    {
        global $order, $messageStack;

        $this->code = 'paypalr';

        $curl_installed = (function_exists('curl_init'));

        if (IS_ADMIN_FLAG === false) {
            $this->title = MODULE_PAYMENT_PAYPALR_TEXT_TITLE;
        } else {
            $this->title = MODULE_PAYMENT_PAYPALR_TEXT_TITLE_ADMIN . (($curl_installed === true) ? '' : $this->alertMsg(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL));
            $this->description = sprintf(MODULE_PAYMENT_PAYPALR_TEXT_ADMIN_DESCRIPTION, self::CURRENT_VERSION);
        }

        $this->sort_order = defined('MODULE_PAYMENT_PAYPALR_SORT_ORDER') ? MODULE_PAYMENT_PAYPALR_SORT_ORDER : null;
        if (null === $this->sort_order) {
            return false;
        }

        $this->log = new Logger();
        $debug = (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false);
        if ($debug === true) {
            $this->log->enableDebug();
        }
        $this->emailAlerts = ($debug === true || MODULE_PAYMENT_PAYPALR_DEBUGGING === 'Alerts Only');

        $this->order_pending_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
        $this->order_status = (int)((int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID > 0) ? MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID : DEFAULT_ORDERS_STATUS_ID;

        $this->zone = (int)MODULE_PAYMENT_PAYPALR_ZONE;

        $this->enabled = (MODULE_PAYMENT_PAYPALR_STATUS === 'True');
        if ($this->enabled === true) {
            $this->validateConfiguration($curl_installed);

            if (IS_ADMIN_FLAG === true) {
                if (MODULE_PAYMENT_PAYPALR_SERVER === 'sandbox') {
                    $this->title .= $this->alertMsg(' (sandbox active)');
                }
                if ($debug === true) {
                    $this->title .= '<strong> (Debug)</strong>';
                }
//                $this->tableCheckup();
            }

            if ($this->enabled === true && is_object($order)) {
                $this->update_status();
            }
        }
    }
    protected function alertMsg(string $msg)
    {
        return '<b class="text-danger">' . $msg . '</b>';
    }

    // -----
    // Validate the configuration settings to ensure that the payment module
    // can be enabled for use.
    //
    // Side effect: The payment module is auto-disabled if any configuration
    // issues are found.
    //
    protected function validateConfiguration(bool $curl_installed)
    {
        // -----
        // No CURL, no payment module!  The PayPalRestApi class requires
        // CURL to 'do its business'.
        //
        if ($curl_installed === false) {
            $this->setConfigurationDisabled(MODULE_PAYMENT_PAYPALR_ERROR_NO_CURL);
        // -----
        // CURL installed, make sure that the configured credentials are valid ...
        //
        } else {
            // -----
            // Determine which (live vs. sandbox) credentials are in use.
            //
            if (MODULE_PAYMENT_PAYPALR_SERVER === 'live') {
                $client_id = MODULE_PAYMENT_PAYPALR_CLIENTID_L;
                $secret = MODULE_PAYMENT_PAYPALR_SECRET_L;
            } else {
                $client_id = MODULE_PAYMENT_PAYPALR_CLIENTID_S;
                $secret = MODULE_PAYMENT_PAYPALR_SECRET_S;
            }

            // -----
            // Ensure that the current environment's credentials are set and, if so,
            // that they're valid PayPal credentials.
            //
            $error_message = '';
            if ($client_id === '' || $secret === '') {
                $error_message = sprintf(MODULE_PAYMENT_PAYPALR_ERROR_CREDS_NEEDED, MODULE_PAYMENT_PAYPALR_SERVER);
            } else {
                $this->ppr = new PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER, $client_id, $secret);

                global $current_page;
                $use_saved_credentials = (IS_ADMIN_FLAG === false || $current_page === FILENAME_MODULES);
                $this->log->write("validateCredentials: Checking ($use_saved_credentials).", true, 'before');
                if ($this->ppr->validatePayPalCredentials($use_saved_credentials) === false) {
                    $error_message = sprintf(MODULE_PAYMENT_PAYPALR_ERROR_INVALID_CREDS, MODULE_PAYMENT_PAYPALR_SERVER);
                }
                $this->log->write('', false, 'after');
            }

            // -----
            // Any credential errors detected, the payment module's auto-disabled.
            //
            if ($error_message !== '') {
                $this->setConfigurationDisabled($error_message);
            }
        }
    }
    protected function setConfigurationDisabled(string $error_message)
    {
        global $db, $messageStack;

        $this->enabled = false;

        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = 'False'
              WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_STATUS'
              LIMIT 1"
        );

        $error_message .= MODULE_PAYMENT_PAYPALR_AUTO_DISABLED;
        if (IS_ADMIN_FLAG === true) {
            $messageStack->add_session($error_message, 'error');
            $this->description = $this->alertMsg($error_message) . '<br><br>' . $this->description;
        } else {
            $this->sendAlertEmail(MODULE_PAYMENT_PAYPALR_ALERT_MSG_CONFIGURATION, $error_message);
        }
    }

    /**
     *  Sets payment module status based on zone restrictions etc
     */
    public function update_status()
    {
        global $order;

        if ($this->enabled === false || !isset($order->billing['country']['id'])) {
            return;
        }

        $order_total = $order->info['total'];
        if ($order_total == 0) {
            $this->enabled = false;
            $this->log->write("update_status: Module disabled because purchase amount is set to 0.00." . $this->log->logJSON($order->info));
            return;
        }

        // module cannot be used for purchase > 1000000 JPY
        if ($order->info['currency'] === 'JPY' && (($order_total * $order->info['currency_value']) > 1000000)) {
            $this->enabled = false;
            $this->log->write("update_status: Module disabled because purchase price ($order_total) exceeds PayPal-imposed maximum limit of 1000000 JPY.");
            return;
        }

        if ($this->zone > 0) {
            global $db;

            $sql =
                "SELECT zone_id
                   FROM " . TABLE_ZONES_TO_GEO_ZONES . "
                  WHERE geo_zone_id = :zoneId
                    AND zone_country_id = :countryId
                  ORDER BY zone_id";
            $sql = $db->bindVars($sql, ':zoneId', $this->zone, 'integer');
            $sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');
            $check = $db->Execute($sql);
            $check_flag = false;
            foreach ($check as $next_zone) {
                if ($next_zone['zone_id'] < 1 || $next_zone['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag === false) {
                $this->enabled = false;
                $this->log->write('update_status: Module disabled due to zone restriction. Billing address is not within the Payment Zone selected in the module settings.');
                return;
            }
        }

        // -----
        // Determine the currency to be used to send the order to PayPal and whether it's usable.
        //
        $order_currency = $order->info['currency'];
        $paypal_default_currency = (MODULE_PAYMENT_PAYPALR_CURRENCY === 'Selected Currency') ? $order_currency : str_replace('Only ', '', MODULE_PAYMENT_PAYPALR_CURRENCY);
        $amount = new Amount($paypal_default_currency);

        $paypal_currency = $amount->getDefaultCurrencyCode();
        if ($paypal_currency !== $order_currency) {
            $this->log->write("==> order_status: Paypal currency ($paypal_currency) different from order's ($order_currency); checking validity.");

            global $currencies;
            if (!isset($currencies)) {
                $currencies = new currencies();
            }
            if ($currencies->is_set($paypal_currency) === false) {
                $this->log->write('  --> Payment method disabled; Paypal currency is not configured.');
                $this->enabled = false;
                return;
            }
        }

/* Not seeing this limitation during initial testing
        // module cannot be used for purchase > $10,000 USD equiv
        if (!function_exists('paypalUSDCheck')) {
            require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_currency_check.php';
        }
        if (paypalUSDCheck($order->info['total']) === false) {
            $this->enabled = false;
            $this->log->write('update_status: Module disabled because purchase price (' . $order->info['total']. ') exceeds PayPal-imposed maximum limit of 10,000 USD or equivalent.');
        }
*/
    }

    /**
     *  Validate the credit card information via javascript (Number, Owner, and CVV Lengths)
     */
    public function javascript_validation()
    {
        return false;
    }

    /**
     * At this point (checkout_payment in the 3-page version), we've got all the information
     * required to "Create" the order at PayPal.  If the order can't be created, no selection.
     */
    public function selection()
    {
        $this->log->write("paypalr:: selection starts:\n", $this->log->logJSON($_SESSION['PayPalRestful']['Order'] ?? []), true, 'before');

        global $order;

        // -----
        // Set up the currency to be used to create the PayPal order.
        //
        $paypal_currency = (MODULE_PAYMENT_PAYPALR_CURRENCY === 'Selected Currency') ? $order->info['currency'] : str_replace('Only ', '', MODULE_PAYMENT_PAYPALR_CURRENCY);
        $amount = new Amount($paypal_currency);
        
        // -----
        // Build the request for the PayPal order's "Create" and send to off to PayPal. Any issues result
        // in the associated selection not being displayed.
        //
        $create_order_request = new CreatePayPalOrderRequest($order);
        $create_order = $this->ppr->createOrder($create_order_request->get());
        if ($create_order === false) {
            return false;
        }

        // -----
        // Return the PayPal selection as a button, modelled after https://developer.paypal.com/demo/checkout/#/pattern/responsive
        //
        $this->log->write("paypalr:: selection complete, showing button:\n", $this->log->logJSON($_SESSION['PayPalRestful']['Order'] ?? []), true, 'after');
        return [
            'id' => $this->code,
            'module' =>
                '<button id="paypalr-paypal" style="background: #ffc439; border-radius: 4px; border: none;">' .
                '  <img style="margin: 0.25vw 2vw;" src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAxcHgiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAxMDEgMzIiIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaW5ZTWluIG1lZXQiIHhtbG5zPSJodHRwOiYjeDJGOyYjeDJGO3d3dy53My5vcmcmI3gyRjsyMDAwJiN4MkY7c3ZnIj48cGF0aCBmaWxsPSIjMDAzMDg3IiBkPSJNIDEyLjIzNyAyLjggTCA0LjQzNyAyLjggQyAzLjkzNyAyLjggMy40MzcgMy4yIDMuMzM3IDMuNyBMIDAuMjM3IDIzLjcgQyAwLjEzNyAyNC4xIDAuNDM3IDI0LjQgMC44MzcgMjQuNCBMIDQuNTM3IDI0LjQgQyA1LjAzNyAyNC40IDUuNTM3IDI0IDUuNjM3IDIzLjUgTCA2LjQzNyAxOC4xIEMgNi41MzcgMTcuNiA2LjkzNyAxNy4yIDcuNTM3IDE3LjIgTCAxMC4wMzcgMTcuMiBDIDE1LjEzNyAxNy4yIDE4LjEzNyAxNC43IDE4LjkzNyA5LjggQyAxOS4yMzcgNy43IDE4LjkzNyA2IDE3LjkzNyA0LjggQyAxNi44MzcgMy41IDE0LjgzNyAyLjggMTIuMjM3IDIuOCBaIE0gMTMuMTM3IDEwLjEgQyAxMi43MzcgMTIuOSAxMC41MzcgMTIuOSA4LjUzNyAxMi45IEwgNy4zMzcgMTIuOSBMIDguMTM3IDcuNyBDIDguMTM3IDcuNCA4LjQzNyA3LjIgOC43MzcgNy4yIEwgOS4yMzcgNy4yIEMgMTAuNjM3IDcuMiAxMS45MzcgNy4yIDEyLjYzNyA4IEMgMTMuMTM3IDguNCAxMy4zMzcgOS4xIDEzLjEzNyAxMC4xIFoiPjwvcGF0aD48cGF0aCBmaWxsPSIjMDAzMDg3IiBkPSJNIDM1LjQzNyAxMCBMIDMxLjczNyAxMCBDIDMxLjQzNyAxMCAzMS4xMzcgMTAuMiAzMS4xMzcgMTAuNSBMIDMwLjkzNyAxMS41IEwgMzAuNjM3IDExLjEgQyAyOS44MzcgOS45IDI4LjAzNyA5LjUgMjYuMjM3IDkuNSBDIDIyLjEzNyA5LjUgMTguNjM3IDEyLjYgMTcuOTM3IDE3IEMgMTcuNTM3IDE5LjIgMTguMDM3IDIxLjMgMTkuMzM3IDIyLjcgQyAyMC40MzcgMjQgMjIuMTM3IDI0LjYgMjQuMDM3IDI0LjYgQyAyNy4zMzcgMjQuNiAyOS4yMzcgMjIuNSAyOS4yMzcgMjIuNSBMIDI5LjAzNyAyMy41IEMgMjguOTM3IDIzLjkgMjkuMjM3IDI0LjMgMjkuNjM3IDI0LjMgTCAzMy4wMzcgMjQuMyBDIDMzLjUzNyAyNC4zIDM0LjAzNyAyMy45IDM0LjEzNyAyMy40IEwgMzYuMTM3IDEwLjYgQyAzNi4yMzcgMTAuNCAzNS44MzcgMTAgMzUuNDM3IDEwIFogTSAzMC4zMzcgMTcuMiBDIDI5LjkzNyAxOS4zIDI4LjMzNyAyMC44IDI2LjEzNyAyMC44IEMgMjUuMDM3IDIwLjggMjQuMjM3IDIwLjUgMjMuNjM3IDE5LjggQyAyMy4wMzcgMTkuMSAyMi44MzcgMTguMiAyMy4wMzcgMTcuMiBDIDIzLjMzNyAxNS4xIDI1LjEzNyAxMy42IDI3LjIzNyAxMy42IEMgMjguMzM3IDEzLjYgMjkuMTM3IDE0IDI5LjczNyAxNC42IEMgMzAuMjM3IDE1LjMgMzAuNDM3IDE2LjIgMzAuMzM3IDE3LjIgWiI+PC9wYXRoPjxwYXRoIGZpbGw9IiMwMDMwODciIGQ9Ik0gNTUuMzM3IDEwIEwgNTEuNjM3IDEwIEMgNTEuMjM3IDEwIDUwLjkzNyAxMC4yIDUwLjczNyAxMC41IEwgNDUuNTM3IDE4LjEgTCA0My4zMzcgMTAuOCBDIDQzLjIzNyAxMC4zIDQyLjczNyAxMCA0Mi4zMzcgMTAgTCAzOC42MzcgMTAgQyAzOC4yMzcgMTAgMzcuODM3IDEwLjQgMzguMDM3IDEwLjkgTCA0Mi4xMzcgMjMgTCAzOC4yMzcgMjguNCBDIDM3LjkzNyAyOC44IDM4LjIzNyAyOS40IDM4LjczNyAyOS40IEwgNDIuNDM3IDI5LjQgQyA0Mi44MzcgMjkuNCA0My4xMzcgMjkuMiA0My4zMzcgMjguOSBMIDU1LjgzNyAxMC45IEMgNTYuMTM3IDEwLjYgNTUuODM3IDEwIDU1LjMzNyAxMCBaIj48L3BhdGg+PHBhdGggZmlsbD0iIzAwOWNkZSIgZD0iTSA2Ny43MzcgMi44IEwgNTkuOTM3IDIuOCBDIDU5LjQzNyAyLjggNTguOTM3IDMuMiA1OC44MzcgMy43IEwgNTUuNzM3IDIzLjYgQyA1NS42MzcgMjQgNTUuOTM3IDI0LjMgNTYuMzM3IDI0LjMgTCA2MC4zMzcgMjQuMyBDIDYwLjczNyAyNC4zIDYxLjAzNyAyNCA2MS4wMzcgMjMuNyBMIDYxLjkzNyAxOCBDIDYyLjAzNyAxNy41IDYyLjQzNyAxNy4xIDYzLjAzNyAxNy4xIEwgNjUuNTM3IDE3LjEgQyA3MC42MzcgMTcuMSA3My42MzcgMTQuNiA3NC40MzcgOS43IEMgNzQuNzM3IDcuNiA3NC40MzcgNS45IDczLjQzNyA0LjcgQyA3Mi4yMzcgMy41IDcwLjMzNyAyLjggNjcuNzM3IDIuOCBaIE0gNjguNjM3IDEwLjEgQyA2OC4yMzcgMTIuOSA2Ni4wMzcgMTIuOSA2NC4wMzcgMTIuOSBMIDYyLjgzNyAxMi45IEwgNjMuNjM3IDcuNyBDIDYzLjYzNyA3LjQgNjMuOTM3IDcuMiA2NC4yMzcgNy4yIEwgNjQuNzM3IDcuMiBDIDY2LjEzNyA3LjIgNjcuNDM3IDcuMiA2OC4xMzcgOCBDIDY4LjYzNyA4LjQgNjguNzM3IDkuMSA2OC42MzcgMTAuMSBaIj48L3BhdGg+PHBhdGggZmlsbD0iIzAwOWNkZSIgZD0iTSA5MC45MzcgMTAgTCA4Ny4yMzcgMTAgQyA4Ni45MzcgMTAgODYuNjM3IDEwLjIgODYuNjM3IDEwLjUgTCA4Ni40MzcgMTEuNSBMIDg2LjEzNyAxMS4xIEMgODUuMzM3IDkuOSA4My41MzcgOS41IDgxLjczNyA5LjUgQyA3Ny42MzcgOS41IDc0LjEzNyAxMi42IDczLjQzNyAxNyBDIDczLjAzNyAxOS4yIDczLjUzNyAyMS4zIDc0LjgzNyAyMi43IEMgNzUuOTM3IDI0IDc3LjYzNyAyNC42IDc5LjUzNyAyNC42IEMgODIuODM3IDI0LjYgODQuNzM3IDIyLjUgODQuNzM3IDIyLjUgTCA4NC41MzcgMjMuNSBDIDg0LjQzNyAyMy45IDg0LjczNyAyNC4zIDg1LjEzNyAyNC4zIEwgODguNTM3IDI0LjMgQyA4OS4wMzcgMjQuMyA4OS41MzcgMjMuOSA4OS42MzcgMjMuNCBMIDkxLjYzNyAxMC42IEMgOTEuNjM3IDEwLjQgOTEuMzM3IDEwIDkwLjkzNyAxMCBaIE0gODUuNzM3IDE3LjIgQyA4NS4zMzcgMTkuMyA4My43MzcgMjAuOCA4MS41MzcgMjAuOCBDIDgwLjQzNyAyMC44IDc5LjYzNyAyMC41IDc5LjAzNyAxOS44IEMgNzguNDM3IDE5LjEgNzguMjM3IDE4LjIgNzguNDM3IDE3LjIgQyA3OC43MzcgMTUuMSA4MC41MzcgMTMuNiA4Mi42MzcgMTMuNiBDIDgzLjczNyAxMy42IDg0LjUzNyAxNCA4NS4xMzcgMTQuNiBDIDg1LjczNyAxNS4zIDg1LjkzNyAxNi4yIDg1LjczNyAxNy4yIFoiPjwvcGF0aD48cGF0aCBmaWxsPSIjMDA5Y2RlIiBkPSJNIDk1LjMzNyAzLjMgTCA5Mi4xMzcgMjMuNiBDIDkyLjAzNyAyNCA5Mi4zMzcgMjQuMyA5Mi43MzcgMjQuMyBMIDk1LjkzNyAyNC4zIEMgOTYuNDM3IDI0LjMgOTYuOTM3IDIzLjkgOTcuMDM3IDIzLjQgTCAxMDAuMjM3IDMuNSBDIDEwMC4zMzcgMy4xIDEwMC4wMzcgMi44IDk5LjYzNyAyLjggTCA5Ni4wMzcgMi44IEMgOTUuNjM3IDIuOCA5NS40MzcgMyA5NS4zMzcgMy4zIFoiPjwvcGF0aD48L3N2Zz4" data-v-b01da731="" alt="" role="presentation">' .
                '</button>',
        ];
    }

    // -----
    // Issued (in 3-page checkout) during the 'checkout_confirmation' page's
    // header.
    //
    public function pre_confirmation_check()
    {
        global $order;

        // -----
        // Set up the currency to be used to create the PayPal order.
        //
        $paypal_currency = (MODULE_PAYMENT_PAYPALR_CURRENCY === 'Selected Currency') ? $order->info['currency'] : str_replace('Only ', '', MODULE_PAYMENT_PAYPALR_CURRENCY);
        $amount = new Amount($paypal_currency);

    }

    /**
     * Display Payment Information for review on the Checkout Confirmation Page
     */
    public function confirmation()
    {
        // -----
        // Nothing additional to display.
        //
        return false;
    }

    /**
     * Prepare the hidden fields comprising the parameters for the Submit button on the checkout confirmation page
     */
    public function process_button()
    {
        // When hitting the checkout-confirm button, we are going into markflow mode
        $_SESSION['paypal_ec_markflow'] = 1;

        // if we have a token, we want to avoid incontext checkout, so we return no special markup
        if (isset($_SESSION['paypal_ec_token']) && !empty($_SESSION['paypal_ec_token'])) {
            return '';
        }

    // send the PayPal-provided javascript to trigger the incontext checkout experience
    return "      <script>
        window.paypalCheckoutReady = function () {
        paypal.checkout.setup('" . MODULE_PAYMENT_PAYPALR_MERCHANTID . "', {
          //locale: '" . $this->getLanguageCode('incontext') . "',"
          . (MODULE_PAYMENT_PAYPALR_SERVER == 'live' ? '' : "\n          environment: 'sandbox',") . "
          container: 'checkout_confirmation',
          button: 'btn_submit'
        });
      };
      </script>
      <script src=\"https://www.paypalobjects.com/api/checkout.js\" async></script>";
    }

    /**
     * Prepare and submit the final authorization to PayPal via the appropriate means as configured
     */
    public function before_process()
    {
        global $order, $doPayPal, $messageStack;


        $this->notify('NOTIFY_PAYPALR_BEFORE_PROCESS_FINISHED', $response);
    }

    /**
     * When the order returns from the processor, this stores the results in order-status-history and logs data for subsequent use
     */
    public function after_process()
    {
        global $insert_id, $order;

        $this->notify('NOTIFY_PAYPALR_AFTER_PROCESS_FINISHED', $paypal_order);
    }

    /**
      * Build admin-page components
      *
      * @param int $zf_order_id
      * @return string
      */
    public function admin_notification($zf_order_id)
    {
        return '';
    }

    public function help()
    {
        return [
            'link' => 'https://docs.zen-cart.com/user/payment/paypal_express_checkout/'
        ];
    }

  /**
   * Determine whether the shipping-edit button should be displayed or not
   */
  function alterShippingEditButton() {
    return false;
    if ($this->in_special_checkout() && empty($_SESSION['paypal_ec_markflow'])) {
      return zen_href_link('ipn_main_handler.php', 'type=ec&clearSess=1', 'SSL', true,true, true);
    }
  }

  /**
   * Used to submit a refund for a given transaction.  FOR FUTURE USE.
   * @TODO: Add option to specify shipping/tax amounts for refund instead of just total. Ref: https://developer.paypal.com/docs/classic/release-notes/merchant/PayPal_Merchant_API_Release_Notes_119/
   */
    public function _doRefund($oID, $amount = 'Full', $note = '')
    {
        global $db, $messageStack;

        $new_order_status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;
        $orig_order_amount = 0;

        $proceedToRefund = false;
        $refundNote = strip_tags(zen_db_input($_POST['refnote']));
        if (isset($_POST['fullrefund']) && $_POST['fullrefund'] === MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_FULL) {
            $refundAmt = 'Full';
            if (isset($_POST['reffullconfirm']) && $_POST['reffullconfirm'] == 'on') {
                $proceedToRefund = true;
            } else {
                $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_REFUND_FULL_CONFIRM_ERROR, 'error');
            }
        }
        if (isset($_POST['partialrefund']) && $_POST['partialrefund'] === MODULE_PAYMENT_PAYPAL_ENTRY_REFUND_BUTTON_TEXT_PARTIAL) {
            $refundAmt = (float)$_POST['refamt'];
            $proceedToRefund = true;
            if ($refundAmt == 0) {
                $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_INVALID_REFUND_AMOUNT, 'error');
                $proceedToRefund = false;
            }
        }

        // look up history on this order from PayPal table
        $sql = "SELECT * FROM " . TABLE_PAYPAL . " WHERE order_id = :orderID  AND parent_txn_id = '' ";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $zc_ppHist = $db->Execute($sql);
        if ($zc_ppHist->EOF === true) {
            return false;
        }

        $txnID = $zc_ppHist->fields['txn_id'];
        $curCode = $zc_ppHist->fields['mc_currency'];
        $PFamt = $zc_ppHist->fields['mc_gross'];
        if ($refundAmt == 'Full') {
            $refundAmt = $PFamt;
        }

        /**
         * Submit refund request to PayPal
         */
        if ($proceedToRefund === false) {
            return false;
        }

        $response = $doPayPal->RefundTransaction($oID, $txnID, $refundAmt, $refundNote, $curCode);

        $error = $this->_errorHandler($response, 'DoRefund');
        $new_order_status = ($new_order_status > 0 ? $new_order_status : 1);
        if ($error === false) {
            if (!isset($response['GROSSREFUNDAMT'])) {
                $response['GROSSREFUNDAMT'] = $refundAmt;
            }

            // Success, so save the results
            $comments =
                'REFUND INITIATED. Trans ID:' .
                $response['REFUNDTRANSACTIONID'] .
                ($response['PNREF'] ?? '') . "\n" .
                ' Gross Refund Amt: ' . urldecode($response['GROSSREFUNDAMT']) .
                (isset($response['PPREF']) ? "\nPPRef: " . $response['PPREF'] : '') . "\n" .
                $refundNote;
            zen_update_orders_history($oID, $comments, null, $new_order_status, 0);

            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_TEXT_REFUND_INITIATED, urldecode($response['GROSSREFUNDAMT']), urldecode($response['REFUNDTRANSACTIONID']) . $response['PNREF'] ?? ''), 'success');
            return true;
        }
        return false;
    }

    /**
     * Used to authorize part of a given previously-initiated transaction.  FOR FUTURE USE.
     */
    public function _doAuth($oID, $amt, $currency = 'USD')
    {
        global $db, $messageStack;
        $doPayPal = $this->paypal_init();
        $authAmt = $amt;
        $new_order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;

        if (isset($_POST['orderauth']) && $_POST['orderauth'] == MODULE_PAYMENT_PAYPAL_ENTRY_AUTH_BUTTON_TEXT_PARTIAL) {
          $authAmt = (float)$_POST['authamt'];
          $new_order_status = MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
          if (isset($_POST['authconfirm']) && $_POST['authconfirm'] == 'on') {
            $proceedToAuth = true;
          } else {
            $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_AUTH_CONFIRM_ERROR, 'error');
            $proceedToAuth = false;
          }
          if ($authAmt == 0) {
            $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_INVALID_AUTH_AMOUNT, 'error');
            $proceedToAuth = false;
          }
        }
        // look up history on this order from PayPal table
        $sql = "SELECT * FROM " . TABLE_PAYPAL . " WHERE order_id = :orderID  AND parent_txn_id = '' ";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $zc_ppHist = $db->Execute($sql);
        if ($zc_ppHist->RecordCount() == 0) return false;
        $txnID = $zc_ppHist->fields['txn_id'];
        /**
         * Submit auth request to PayPal
         */
        if ($proceedToAuth) {
          $response = $doPayPal->DoAuthorization($txnID, $authAmt, $currency);

          //$this->zcLog("_doAuth($oID, $amt, $currency):", print_r($response, true));

          $error = $this->_errorHandler($response, 'DoAuthorization');
          $new_order_status = ($new_order_status > 0 ? $new_order_status : 1);
          if (!$error) {
            // Success, so save the results
            $comments = 'AUTHORIZATION ADDED. Trans ID: ' . urldecode($response['TRANSACTIONID']) . "\n" . ' Amount:' . urldecode($response['AMT']) . ' ' . $currency;
            zen_update_orders_history($oID, $comments, null, $new_order_status, -1);

            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_TEXT_AUTH_INITIATED, urldecode($response['AMT'])), 'success');
            return true;
          }
        }
    }

    /**
     * Used to capture part or all of a given previously-authorized transaction.  FOR FUTURE USE.
     * (alt value for $captureType = 'NotComplete')
     */
    public function _doCapt($oID, $captureType = 'Complete', $amt = 0, $currency = 'USD', $note = '')
    {
        global $db, $messageStack;

        //@TODO: Read current order status and determine best status to set this to
        $new_order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;

        $orig_order_amount = 0;
        $proceedToCapture = false;
        $captureNote = strip_tags(zen_db_input($_POST['captnote']));
        if (isset($_POST['captfullconfirm']) && $_POST['captfullconfirm'] == 'on') {
            $proceedToCapture = true;
        } else {
            $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_CAPTURE_FULL_CONFIRM_ERROR, 'error');
        }
        if (isset($_POST['captfinal']) && $_POST['captfinal'] === 'on') {
            $captureType = 'Complete';
        } else {
            $captureType = 'NotComplete';
        }
        if (isset($_POST['btndocapture']) && $_POST['btndocapture'] === MODULE_PAYMENT_PAYPALR_ENTRY_CAPTURE_BUTTON_TEXT_FULL) {
            $captureAmt = (float)$_POST['captamt'];
            if ($captureAmt == 0) {
                $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_INVALID_CAPTURE_AMOUNT, 'error');
                $proceedToCapture = false;
            }
        }

        // look up history on this order from PayPal table
        $sql = "SELECT * FROM " . TABLE_PAYPAL . " WHERE order_id = :orderID  AND parent_txn_id = '' ";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $zc_ppHist = $db->Execute($sql);
        if ($zc_ppHist->EOF === true) {
            return false;
        }
        $txnID = $zc_ppHist->fields['txn_id'];

        /**
         * Submit capture request to PayPal
         */
        if ($proceedToCapture === true) {
            $response = $doPayPal->DoCapture($txnID, $captureAmt, $currency, $captureType, '', $captureNote);

            $error = $this->_errorHandler($response, 'DoCapture');
            $new_order_status = ($new_order_status > 0 ? $new_order_status : 1);
            if ($error === false) {
                if (isset($response['PNREF'])) {
                    if (!isset($response['AMT'])) {
                        $response['AMT'] = $captureAmt;
                    }
                    if (!isset($response['ORDERTIME'])) {
                        $response['ORDERTIME'] = date("M-d-Y h:i:s");
                    }
                }
                // Success, so save the results
                $comments =
                    'FUNDS CAPTURED. Trans ID: ' . urldecode($response['TRANSACTIONID']) .
                    ($response['PNREF'] ?? '') . "\n" .
                    ' Amount: ' . urldecode($response['AMT']) . ' ' .
                    $currency . "\n" .
                    'Time: ' . urldecode($response['ORDERTIME']) . "\n" .
                    'Auth Code: ' . (!empty($response['AUTHCODE']) ? $response['AUTHCODE'] : $response['CORRELATIONID']) .
                    (isset($response['PPREF']) ? "\nPPRef: " . $response['PPREF'] : '') . "\n" .
                    $captureNote;
                zen_update_orders_history($oID, $comments, null, $new_order_status, 0);

                $messageStack->add_session(
                    sprintf(MODULE_PAYMENT_PAYPALR_TEXT_CAPT_INITIATED, urldecode($response['AMT']), urldecode(!empty($response['AUTHCODE']) ? $response['AUTHCODE'] : $response['CORRELATIONID']). $response['PNREF'] ?? ''),
                    'success'
                );
                return true;
            }
        }
        return false;
    }

    /**
     * Used to void a given previously-authorized transaction.  FOR FUTURE USE.
     */
    public function _doVoid($oID, $note = '')
    {
        global $db, $messageStack;

        $new_order_status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;

        $voidNote = strip_tags(zen_db_input($_POST['voidnote']));
        $voidAuthID = trim(strip_tags(zen_db_input($_POST['voidauthid'])));
        $proceedToVoid = false;
        if (isset($_POST['ordervoid']) && $_POST['ordervoid'] === MODULE_PAYMENT_PAYPALR_ENTRY_VOID_BUTTON_TEXT_FULL) {
            if (isset($_POST['voidconfirm']) && $_POST['voidconfirm'] === 'on') {
                $proceedToVoid = true;
            } else {
                $messageStack->add_session(MODULE_PAYMENT_PAYPALR_TEXT_VOID_CONFIRM_ERROR, 'error');
            }
        }

        // look up history on this order from PayPal table
        $sql = "SELECT * FROM " . TABLE_PAYPAL . " WHERE order_id = :orderID  AND parent_txn_id = '' ";
        $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
        $sql = $db->bindVars($sql, ':transID', $voidAuthID, 'string');
        $zc_ppHist = $db->Execute($sql);
        if ($zc_ppHist->EOF === true) {
            return false;
        }

        $txnID = $zc_ppHist->fields['txn_id'];
        /**
         * Submit void request to PayPal
         */
        if ($proceedToVoid === true) {
            $response = $doPayPal->DoVoid($voidAuthID, $voidNote);

            $error = $this->_errorHandler($response, 'DoVoid');
            $new_order_status = ($new_order_status > 0 ? $new_order_status : 1);
            if ($error === false) {
                // Success, so save the results
                $comments =
                    'VOIDED. Trans ID: ' . urldecode($response['AUTHORIZATIONID']) .
                    ($response['PNREF'] ?? '') .
                    (isset($response['PPREF']) ? "\nPPRef: " . $response['PPREF'] : '') . "\n" .
                    $voidNote;
                zen_update_orders_history($oID, $comments, null, $new_order_status, 0);

                $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_TEXT_VOID_INITIATED, urldecode($response['AUTHORIZATIONID']) . ($response['PNREF'] ?? '')), 'success');
                return true;
            }
        }
        return false;
    }

    /**
     * Determine the language to use when redirecting to the PayPal site
     * Order of selection: locale for current language, current-language-code, delivery-country, billing-country, store-country
     */
    public function getLanguageCode($mode = 'ec')
    {
        global $order, $locales, $lng;

        if (!isset($lng) || !is_object($lng)) {
            $lng = new language;
        }
        $allowed_country_codes = ['US', 'AU', 'DE', 'FR', 'IT', 'GB', 'ES', 'AT', 'BE', 'CA', 'CH', 'CN', 'NL', 'PL', 'PT', 'BR', 'RU'];
        $allowed_language_codes = ['da_DK', 'he_IL', 'id_ID', 'ja_JP', 'no_NO', 'pt_BR', 'ru_RU', 'sv_SE', 'th_TH', 'tr_TR', 'zh_CN', 'zh_HK', 'zh_TW'];

        if ($mode === 'incontext') {
            $additional_language_codes = ['de_DE', 'en_AU', 'en_GB', 'en_US', 'es_ES', 'fr_CA', 'fr_FR', 'it_IT', 'nl_NL', 'pl_PL', 'pt_PT'];
            $allowed_language_codes = array_merge($allowed_language_codes, $additional_language_codes);
            $allowed_country_codes = [];
        }

        $lang_code = '';
        $user_locale_info = [];
        if (isset($locales) && is_array($locales)) {
            $user_locale_info = $locales;
        }

        $lng->get_browser_language();
        array_unshift($user_locale_info, $lng->language['code']);

        $user_locale_info[] = strtoupper($_SESSION['languages_code']);

        if (isset($order->delivery['country']['id'])) {
            $shippingISO = zen_get_countries_with_iso_codes($order->delivery['country']['id']);
            $user_locale_info[] = strtoupper($shippingISO['countries_iso_code_2']);
        }

        if (isset($order->billing['country']['id'])) {
            $billingISO = zen_get_countries_with_iso_codes($order->billing['country']['id']);
            $user_locale_info[] = strtoupper($billingISO['countries_iso_code_2']);
        }

        if (isset($order->customer['country']['id'])) {
            $custISO = zen_get_countries_with_iso_codes($order->customer['country']['id']);
            $user_locale_info[] = strtoupper($custISO['countries_iso_code_2']);
        }

        $storeISO = zen_get_countries_with_iso_codes(STORE_COUNTRY);
        $user_locale_info[] = strtoupper($storeISO['countries_iso_code_2']);

        $to_match = array_map('strtoupper', array_merge($allowed_country_codes, $allowed_language_codes));
        foreach ($user_locale_info as $val) {
            if (in_array(strtoupper($val), $to_match)) {
                if (strtoupper($val) === 'EN') {
                    $val = (isset($locales) && $locales[0] === 'en_GB') ? 'GB' : 'US';
                }
                return $val;
            }
        }
        return '';
    }

  /**
   * Calculate the amount based on acceptable currencies
   */
  function calc_order_amount($amount, $paypalCurrency, $applyFormatting = false) {
    global $currencies;
    $amount = ($amount * $currencies->get_value($paypalCurrency));
    if (in_array($paypalCurrency, array('JPY', 'HUF', 'TWD')) || (int)$currencies->get_decimal_places($paypalCurrency) == 0) {
      $amount = (int)$amount;
      $applyFormatting = FALSE;
    }
    return ($applyFormatting ? round($amount, $currencies->get_decimal_places($paypalCurrency)) : $amount);
  }
  /**
   * Set the state field depending on what PayPal requires for that country.
   * The shipping address state or province is required if the address is in one of the following countries: Argentina, Brazil, Canada, China, Indonesia, India, Japan, Mexico, Thailand, USA
   * https://developer.paypal.com/docs/classic/api/state_codes/
   */
  function setStateAndCountry(&$info) {
    global $db, $messageStack;
    switch ($info['country']['iso_code_2']) {
      case 'AU':
      case 'US':
      case 'CA':
      // Paypal only accepts two character state/province codes for some countries.
      if (strlen($info['state']) > 2) {
        $sql = "SELECT zone_code FROM " . TABLE_ZONES . " WHERE zone_name = :zoneName";
        $sql = $db->bindVars($sql, ':zoneName', $info['state'], 'string');
        $state = $db->Execute($sql);
        if (!$state->EOF) {
          $info['state'] = $state->fields['zone_code'];
        } else {
          $messageStack->add_session('header', MODULE_PAYMENT_PAYPALR_TEXT_STATE_ERROR, 'error');
          $this->terminateEC(MODULE_PAYMENT_PAYPALR_TEXT_STATE_ERROR);
        }
      }
      break;
      case 'AT':
      case 'BE':
      case 'FR':
      case 'DE':
      case 'CH':
      $info['state'] = '';
      break;
      case 'GB':
      break;
      default:
      $info['state'] = '';
    }
  }

    /**
     * Evaluate installation status of this module. Returns true if the status key is found.
     */
    public function check()
    {
        global $db;

        if (!isset($this->_check)) {
          $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_STATUS' LIMIT 1");
          $this->_check = !$check_query->EOF;
        }

        return $this->_check;
    }

    /**
     * Installs all the configuration keys for this module
     */
    public function install()
    {
        global $db;

        $amount = new Amount();
        $supported_currencies = $amount->getSupportedCurrencyCodes();
        $currencies_list = '';
        foreach ($supported_currencies as $next_currency) {
            $currencies_list .= "\'Only $next_currency\',";
        }
        $currencies_list = rtrim($currencies_list, ',');

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
             VALUES
                ('Enable this Payment Module', 'MODULE_PAYMENT_PAYPALR_STATUS', 'False', 'Do you want to enable this payment module?', 6, 0, 'zen_cfg_select_option([\'True\', \'False\'], ', NULL, now()),

                ('Environment', 'MODULE_PAYMENT_PAYPALR_SERVER', 'live', '<b>Live: </b> Used to process Live transactions<br><b>Sandbox: </b>For developers and testing', 6, 0, 'zen_cfg_select_option([\'live\', \'sandbox\'], ', NULL, now()),

                ('Client ID (live)', 'MODULE_PAYMENT_PAYPALR_CLIENTID_L', '', 'The <em>Client ID</em> from your PayPal API Signature settings under *API Access* for your <b>live</b> site. Required if using the <b>live</b> environment.', 6, 0, NULL, 'zen_cfg_password_display', now()),

                ('Client Secret (live)', 'MODULE_PAYMENT_PAYPALR_SECRET_L', '', 'The <em>Client Secret</em> from your PayPal API Signature settings under *API Access* for your <b>live</b> site. Required if using the <b>live</b> environment.', 6, 0, NULL, 'zen_cfg_password_display', now()),

                ('Client ID (sandbox)', 'MODULE_PAYMENT_PAYPALR_CLIENTID_S', '', 'The <em>Client ID</em> from your PayPal API Signature settings under *API Access* for your <b>sandbox</b> site. Required if using the <b>sandbox</b> environment..', 6, 0, NULL, 'zen_cfg_password_display', now()),

                ('Client Secret (sandbox)', 'MODULE_PAYMENT_PAYPALR_SECRET_S', '', 'The <em>Client Secret</em> from your PayPal API Signature settings under *API Access* for your <b>sandbox</b> site. Required if using the <b>sandbox</b> environment.', 6, 0, NULL, 'zen_cfg_password_display', now()),

                ('Sort order of display.', 'MODULE_PAYMENT_PAYPALR_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', 6, 0, NULL, NULL, now()),

                ('Payment Zone', 'MODULE_PAYMENT_PAYPALR_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', 6, 0, 'zen_cfg_pull_down_zone_classes(', 'zen_get_zone_class_title', now()),

                ('Completed Order Status', 'MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID', '2', 'Set the status of orders successfully paid with this payment module to this value.<br><b>Recommended: Processing[2]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('Set Unpaid Order Status', 'MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID', '1', 'Set the status of unpaid orders made with this payment module to this value.<br><b>Recommended: Pending[1]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('Set Refund Order Status', 'MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID', '1', 'Set the status of refunded orders to this value.<br><b>Recommended: Pending[1]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('PayPal Page Style', 'MODULE_PAYMENT_PAYPALR_PAGE_STYLE', 'Primary', 'The page-layout style you want customers to see when they visit the PayPal site. You can configure your <b>Custom Page Styles</b> in your PayPal Profile settings. This value is case-sensitive.', 6, 0, NULL, NULL, now()),

                ('Store (Brand) Name at PayPal', 'MODULE_PAYMENT_PAYPALR_BRANDNAME', '', 'The name of your store as it should appear on the PayPal login page. If blank, your store name will be used.', 6, 0, NULL, NULL, now()),

                ('Payment Action', 'MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE', 'Final Sale', 'How do you want to obtain payment?<br><b>Default: Final Sale</b>', 6, 0, 'zen_cfg_select_option([\'Auth Only\', \'Final Sale\'], ', NULL,  now()),

                ('Transaction Currency', 'MODULE_PAYMENT_PAYPALR_CURRENCY', 'Selected Currency', 'In which currency should the order be sent to PayPal?<br>NOTE: If an unsupported currency is sent to PayPal, it will be auto-converted to the <em>Fall-back Currency</em>.<br><b>Default: Selected Currency</b>', 6, 0, 'zen_cfg_select_option([\'Selected Currency\', $currencies_list], ', NULL, now()),

                ('Fall-back Currency', 'MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK', 'USD', 'If the <b>Transaction Currency</b> is set to <em>Selected Currency</em>, what currency should be used as a fall-back when the customer\'s selected currency is not supported by PayPal?<br><b>Default: USD</b>', 6, 0, 'zen_cfg_select_option([\'USD\', \'GBP\'], ', NULL, now()),

                ('Allowed Payment Types', 'MODULE_PAYMENT_PAYPALR_ALLOWEDPAYMENT', 'Any', 'Do you want to allow non-instant payments like eCheck/EFT/ELV?', 6, 0, 'zen_cfg_select_option([\'Any\', \'Instant Only\'], ', NULL, now()),

                ('Debug Mode', 'MODULE_PAYMENT_PAYPALR_DEBUGGING', 'Off', 'Would you like to enable debug mode?  A complete detailed log of failed transactions will be emailed to the store owner.', 6, 0, 'zen_cfg_select_option([\'Off\', \'Alerts Only\', \'Log File\', \'Log and Email\'], ', NULL, now())"
        );

        $this->notify('NOTIFY_PAYMENT_PAYPALR_INSTALLED');
    }

    public function keys()
    {
        return [
            'MODULE_PAYMENT_PAYPALR_STATUS',
            'MODULE_PAYMENT_PAYPALR_SORT_ORDER',
            'MODULE_PAYMENT_PAYPALR_ZONE',
            'MODULE_PAYMENT_PAYPALR_SERVER',
            'MODULE_PAYMENT_PAYPALR_CLIENTID_L',
            'MODULE_PAYMENT_PAYPALR_SECRET_L',
            'MODULE_PAYMENT_PAYPALR_CLIENTID_S',
            'MODULE_PAYMENT_PAYPALR_SECRET_S',
            'MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID',
            'MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID',
            'MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_PAYPALR_CURRENCY',
            'MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK',
            'MODULE_PAYMENT_PAYPALR_BRANDNAME',
            'MODULE_PAYMENT_PAYPALEC_ALLOWEDPAYMENT',
            'MODULE_PAYMENT_PAYPALR_PAGE_STYLE',
            'MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE',
            'MODULE_PAYMENT_PAYPALR_DEBUGGING',
        ];
    }

    /**
     * Uninstall this module
     */
    public function remove()
    {
        global $db;

        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\_PAYMENT\_PAYPALR\_%'");

        $this->notify('NOTIFY_PAYMENT_PAYPALR_UNINSTALLED');
    }
    
    /**
     * Send email to store-owner, if configured.
     *
     */
    public function sendAlertEmail(string $errorNum, string $message)
    {
        if ($this->emailAlerts === true) {
            zen_mail(
                STORE_NAME,
                STORE_OWNER_EMAIL_ADDRESS,
                MODULE_PAYMENT_PAYPALR_TEXT_EMAIL_ERROR_SUBJECT . " ($errorNum)",
                $message,
                STORE_OWNER,
                STORE_OWNER_EMAIL_ADDRESS,
                ['EMAIL_MESSAGE_HTML' => nl2br($message)],
                'paymentalert'
            );
        }
    }
}
