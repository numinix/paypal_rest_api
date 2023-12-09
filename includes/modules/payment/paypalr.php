<?php
/**
 * paypalr.php payment module class for PayPal RESTful API payment method
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

use PayPalRestful\Admin\AdminMain;
use PayPalRestful\Admin\GetPayPalOrderTransactions;
use PayPalRestful\Api\PayPalRestfulApi;
use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;
use PayPalRestful\Token\TokenCache;
use PayPalRestful\Zc2Pp\Amount;
use PayPalRestful\Zc2Pp\ConfirmPayPalPaymentChoiceRequest;
use PayPalRestful\Zc2Pp\CreatePayPalOrderRequest;
use PayPalRestful\Zc2Pp\UpdatePayPalOrderRequest;

/**
 * The PayPal payment module using PayPal's RESTful API (v2)
 */
class paypalr extends base
{
    protected const CURRENT_VERSION = '1.0.0-beta2';

    protected const WEBHOOK_NAME = HTTP_SERVER . DIR_WS_CATALOG . 'ppr_webhook_main.php';

    /**
     * name of this module
     *
     * @var string
     */
    public string $code;

    /**
     * displayed module title
     *
     * @var string
     */
    public string $title;

    /**
     * displayed module description
     *
     * @var string
     */
    public string $description = '';

    /**
     * module status - set based on various config and zone criteria
     *
     * @var boolean
     */
    public bool $enabled;

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
    public int $zone;

    /**
     * debugging flags
     *
     * @var boolean
     */
    public bool $emailAlerts;

    /**
     * sort order of display
     *
     * @var int/null
     */
    public $sort_order = 0;

    /**
     * order status setting for completed orders
     *
    * @var int
     */
    public int $order_status;

    /**
     * URLs used during checkout if this is the selected payment method
     *
     * @var string
     */
    public $form_action_url;

    /**
     * The orders::orders_id for a just-created order, supplied during
     * the 'checkout_process' step.
     */
    protected $orders_id;

    /**
     * Debug interface, shared with the PayPalRestfulApi class.
     */
    protected Logger $log; //- An instance of the Logger class, logs debug tracing information.

    /**
     * An array to maintain error information returned by various PayPalRestfulApi methods.
     */
    protected ErrorInfo $errorInfo; //- An instance of the ErrorInfo class, logs debug tracing information.

    /**
     * An instance of the PayPalRestfulApi class.
     *
     * @var object PayPalRestfulApi
     */
    protected PayPalRestfulApi $ppr;
    
    /**
     * An array (set by before_process) containing the captured/authorized order's
     * PayPal response information, for use by after_order_create to populate the
     * paypal table's record once the associated order's ID is known.
     */
    protected array $orderInfo = [];

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

        $this->sort_order = defined('MODULE_PAYMENT_PAYPALR_SORT_ORDER') ? ((int)MODULE_PAYMENT_PAYPALR_SORT_ORDER) : null;
        if (null === $this->sort_order) {
            return false;
        }

        $this->errorInfo = new ErrorInfo();

        $this->log = new Logger();
        $debug = (strpos(MODULE_PAYMENT_PAYPALR_DEBUGGING, 'Log') !== false);
        if ($debug === true) {
            $this->log->enableDebug();
        }
        $this->emailAlerts = ($debug === true || MODULE_PAYMENT_PAYPALR_DEBUGGING === 'Alerts Only');

        // -----
        // An order's *initial* order-status depending on the mode in which the PayPal transaction
        // is to be performed.
        //
        $order_status = (int)(MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE === 'Final Sale') ? MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID : MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
        $this->order_status = ($order_status > 1) ? $order_status : (int)DEFAULT_ORDERS_STATUS_ID;

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

    public static function getEnvironmentInfo(): array
    {
        // -----
        // Determine and return which (live vs. sandbox) credentials are in use.
        //
        if (MODULE_PAYMENT_PAYPALR_SERVER === 'live') {
            $client_id = MODULE_PAYMENT_PAYPALR_CLIENTID_L;
            $secret = MODULE_PAYMENT_PAYPALR_SECRET_L;
        } else {
            $client_id = MODULE_PAYMENT_PAYPALR_CLIENTID_S;
            $secret = MODULE_PAYMENT_PAYPALR_SECRET_S;
        }
        
        return [
            $client_id,
            $secret,
        ];
    }

    // -----
    // Validate the configuration settings to ensure that the payment module
    // can be enabled for use.
    //
    // Side effects:
    //
    // - Additional fields are added to the 'paypal' table, if not already present.
    // - The payment module is auto-disabled if any configuration issues are found.
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
            [$client_id, $secret] = self::getEnvironmentInfo();

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
            $this->sendAlertEmail(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_CONFIGURATION, $error_message);
        }
    }

    /**
     *  Sets payment module status based on zone restrictions etc
     */
    public function update_status()
    {
        global $order, $current_page_base;

        if ($this->enabled === false || !isset($order->billing['country']['id'])) {
            return;
        }

        $order_total = $order->info['total'];
        if ($order_total == 0) {
            $this->enabled = false;
            $this->log->write("update_status: Module disabled because purchase amount is set to 0.00." . Logger::logJSON($order->info));
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
     * required to "Create" the order at PayPal.  If the order can't be created, no selection
     * is rendered.
     */
    public function selection(): array
    {
        // -----
        // Determine which color button to use.
        //
        $chosen_button_color = 'MODULE_PAYMENT_PAYPALR_BUTTON_IMG_' . MODULE_PAYMENT_PAYPALR_BUTTON_COLOR;
        $paypal_button = (defined($chosen_button_color)) ? constant($chosen_button_color) : MODULE_PAYMENT_PAYPALR_BUTTON_IMG_YELLOW;

        // -----
        // Return the PayPal selection as a button
        //
        return [
            'id' => $this->code,
            'module' => '<img src="' . $paypal_button . '" alt="' . MODULE_PAYMENT_PAYPALR_BUTTON_ALTTEXT . '" title="' . MODULE_PAYMENT_PAYPALR_BUTTON_ALTTEXT . '">',
        ];
    }

    protected function resetOrder()
    {
        unset($_SESSION['PayPalRestful']['Order']);
    }

    protected function createPayPalOrder(): bool
    {
        global $order;

        // -----
        // Build the request for the PayPal order's "Create" or "Update".
        //
        $create_order_request = new CreatePayPalOrderRequest($order);

        // -----
        // Send the request off to register the order at PayPal.
        //
        $order_response = $this->ppr->createOrder($create_order_request->get());
        if ($order_response === false) {
            $this->errorInfo->copyErrorInfo($this->ppr->getErrorInfo());
            return false;
        }

        // -----
        // Save the created PayPal order in the session and indicate that the
        // operation was successful.
        //
        $paypal_id = $order_response['id'];
        $status = $order_response['status'];
        $create_time = $order_response['create_time'];
        unset(
            $order_response['id'],
            $order_response['status'],
            $order_response['create_time'],
            $order_response['links'],
            $order_response['purchase_units'][0]['reference_id'],
            $order_response['purchase_units'][0]['payee']
        );
        $_SESSION['PayPalRestful']['Order'] = [
            'current' => $order_response,
            'id' => $paypal_id,
            'status' => $status,
            'create_time' => $create_time,
        ];
        return true;
    }

    // -----
    // Issued (in 3-page checkout) during the 'checkout_confirmation' page's
    // header.
    //
    public function pre_confirmation_check()
    {
        global $order;

        $this->log->write("pre_confirmation_check starts ...\n", true, 'before');

        // -----
        // Build the request for the PayPal order's "Create" and send to off to PayPal.
        //
        $paypal_order_created = $this->createPayPalOrder();
        if ($paypal_order_created === false) {
            $this->setMessageAndRedirect("createPayPalOrder failed\n" . Logger::logJSON($this->ppr->getErrorInfo()), FILENAME_CHECKOUT_PAYMENT);  //- FIXME
        }

        // -----
        // Add an invoice number to the PayPal order, it'll be ZC-{paypal-txn-id}.
        //
        $paypal_id = $_SESSION['PayPalRestful']['Order']['id'];
        $update_order_invoice = [
            [
                'op' => 'add',
                'path' => "/purchase_units/@reference_id=='default'/invoice_id",
                'value' => "ZC-$paypal_id",
            ],
        ];
        $order_update_response = $this->ppr->updateOrder($paypal_id, $update_order_invoice);
        if ($order_update_response === false) {
            $this->setMessageAndRedirect("updateOrder invoice failed:\n" . Logger::logJSON($this->ppr->getErrorInfo()), FILENAME_CHECKOUT_PAYMENT);
        }

        $confirm_payment_choice_request = new ConfirmPayPalPaymentChoiceRequest(self::WEBHOOK_NAME, $order);
        $payment_choice_response = $this->ppr->confirmPaymentSource($_SESSION['PayPalRestful']['Order']['id'], $confirm_payment_choice_request->get());
        if ($payment_choice_response === false) {
            $this->setMessageAndRedirect("confirmPaymentSource failed\n" . Logger::logJSON($this->ppr->getErrorInfo()), FILENAME_CHECKOUT_PAYMENT);  //- FIXME
        }

        $current_status = $payment_choice_response['status'];
        if ($current_status !== PayPalRestfulApi::STATUS_PAYER_ACTION_REQUIRED) {
            $this->setMessageAndRedirect("confirmPaymentSource invalid return status '$current_status', see log.", FILENAME_CHECKOUT_PAYMENT);  //- FIXME
        }

        // -----
        // If present (it *should* be), save the payment source (e.g. 'paypal' or 'card') in the order's
        // session array, for use when the payment is confirmed so that the source
        // can be included in the 'title' of the PayPal Checkout.
        //
/*---- Not sure if this violates PayPal's instructions since it'll show 'paypal' (without caps).
        if (isset($payment_choice_response['payment_source'])) {
            $_SESSION['PayPalRestful']['Order']['payment_source'] = array_key_first($payment_choice_response['payment_source']);
            $this->title .= ' (' . $_SESSION['PayPalRestful']['Order']['payment_source'] . ')';
        }
*/

        // -----
        // Locate (and save) the URL to which the customer is redirected at PayPal
        // to confirm their payment choice.
        //
        $action_link = '';
        foreach ($payment_choice_response['links'] as $next_link) {
            if ($next_link['rel'] === 'payer-action') {
                $action_link = $next_link['href'];
                $approve_method = $next_link['method'];
                break;
            }
        }
        if ($action_link === '') {
            trigger_error("No payer-action link returned by PayPal, payment cannot be completed.\n", Logger::logJSON($payment_choice_response), E_USER_WARNING);
            $this->setMessageAndRedirect("confirmPaymentSource, no payer-action link found.", FILENAME_CHECKOUT_PAYMENT);  //- FIXME
        }
        $this->form_action_url = $action_link;

        $this->log->write('pre_confirmation_check, completed.', true, 'after');
    }

    /**
     * Display Payment Additional Information for review on the Checkout Confirmation Page
     */
    public function confirmation()
    {
        return false;
    }

    /**
     * Issued by the checkout_process page's header_php.php when a change
     * in the cart's contents are detected, given a payment module the
     * opportunity to reset any related variables.
     */
    public function clear_payments()
    {
        $this->resetOrder();
    }

    /**
     * Prepare the hidden fields comprising the parameters for the Submit button on the checkout confirmation page
     */
    public function process_button()
    {
        return false;
    }

    /**
     * Prepare and submit the final authorization to PayPal via the appropriate means as configured.
     * Issued close to the start of the 'checkout_process' phase.
     */
    public function before_process()
    {
        global $messageStack;

        if (!isset($_SESSION['PayPalRestful']['Order']['status']) || $_SESSION['PayPalRestful']['Order']['status'] !== PayPalRestfulApi::STATUS_APPROVED) {
            $this->setMessageAndRedirect("paypalr::before_process, can't capture/authorize order; wrong status ({$_SESSION['PayPalRestful']['Order']['status']}).", FILENAME_CHECKOUT_SHIPPING);  //- FIXME
        }

        $paypal_id = $_SESSION['PayPalRestful']['Order']['id'];
        if (MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE === 'Final Sale') {
            $response = $this->ppr->captureOrder($paypal_id);
        } else {
            $response = $this->ppr->authorizeOrder($paypal_id);
        }

        if ($response === false) {
            $this->setMessageAndRedirect('paypalr::before_process, can\'t capture/authorize order; error in attempt, see log.', FILENAME_CHECKOUT_PAYMENT);  //- FIXME
        }

        $_SESSION['PayPalRestful']['Order']['status'] = $response['status'];
        unset($response['purchase_units'][0]['links']);
        $this->orderInfo = $response;

        // -----
        // Determine the payment's status to be recorded in the paypal table and to accompany the
        // additional order-status-history record to be created by the after_process method.
        //
        $txn_type = $this->orderInfo['intent'];
        $payment = $this->orderInfo['purchase_units'][0]['payments']['captures'][0] ?? $this->orderInfo['purchase_units'][0]['payments']['authorizations'][0];
        $payment_status = ($payment['status'] !== PayPalRestfulApi::STATUS_COMPLETED) ? $payment['status'] : (($txn_type === 'CAPTURE') ? PayPalRestfulApi::STATUS_CAPTURED : PayPalRestfulApi::STATUS_APPROVED);

        $this->orderInfo['payment_status'] = $payment_status;
        $this->orderInfo['paypal_payment_status'] = $payment['status'];
        $this->orderInfo['txn_type'] = $txn_type;

        // -----
        // If an expiration is present (e.g. this is a payment authorization), record the expiration
        // time for follow-on recording in the database.
        //
        $this->orderInfo['expiration_time'] = $payment['expiration_time'] ?? null;

        // -----
        // If the order's PayPal status doesn't indicate successful completion, ensure that
        // the overall order's status is set to this payment-module's PENDING status and set
        // a processing flag so that the after_process method will alert the store admin if
        // configured.
        //
        // Setting the order's overall status here, since zc158a and earlier don't acknowlege
        // a payment-module's change in status during the payment processing!
        //
        $this->orderInfo['admin_alert_needed'] = false;
        if ($payment_status !== PayPalRestfulApi::STATUS_CAPTURED && $payment_status !== PayPalRestfulApi::STATUS_CREATED) {
            global $order;

            $this->order_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID;
            $order->info['order_status'] = $this->order_status;
            $this->orderInfo['admin_alert_needed'] = true;

            $this->log->write("==> paypalr::before_process: Payment status {$payment['status']} received from PayPal; order's status forced to pending.");
        }

        $this->notify('NOTIFY_PAYPALR_BEFORE_PROCESS_FINISHED', $this->orderInfo);
    }

    protected function setMessageAndRedirect(string $error_message, string $redirect_page)
    {
        global $messageStack;

        $messageStack->add_session('checkout', $error_message, 'error');  //- FIXME
        $this->resetOrder();
        zen_redirect(zen_href_link($redirect_page));
    }

    /**
     * Issued by /modules/checkout_process.php after the main order-record has
     * been provided in the database, supplying the just-created order's 'id'.
     *
     * The before_process method has stored the successful PayPal response from the
     * payment's capture (or authorization), based on the site's configuration, in
     * the class' orderInfo property.
     *
     * Unlike other payment modules, paypalr stores its database information during
     * the after_order_create method's processing, just in case some email issue arises
     * so that the information's not written.
     */
    public function after_order_create($orders_id)
    {
        $this->orderInfo['orders_id'] = $orders_id;

        $purchase_unit = $this->orderInfo['purchase_units'][0];
        $address_info = [];
        if (isset($purchase_unit['shipping']['address'])) {
            $shipping_address = $purchase_unit['shipping']['address'];
            $address_street = $shipping_address['address_line_1'];
            if (isset($shipping_address['address_line_2'])) {
                $address_street .= ', ' . $shipping_address['address_line_2'];
            }
            $address_street = substr($address_street, 0, 254);
            $address_info = [
                'address_name' => substr($purchase_unit['shipping']['name']['full_name'], 0, 64),
                'address_street' => $address_street,
                'address_city' => substr($shipping_address['admin_area_2'] ?? '', 0, 120),
                'address_state' => substr($shipping_address['admin_area_1'] ?? '', 0, 120),
                'address_zip' => substr($shipping_address['postal_code'] ?? '', 0, 10),
                'address_country' => substr($shipping_address['country_code'] ?? '', 0, 64),
            ];
        }

        $payment = $purchase_unit['payments']['captures'][0] ?? $purchase_unit['payments']['authorizations'][0];

        $payment_info = [];
        if (isset($payment['seller_receivable_breakdown'])) {
            $seller_receivable = $payment['seller_receivable_breakdown'];
            $payment_info = [
                'payment_date' => Helpers::convertPayPalDatePay2Db($payment['create_time']),
                'payment_gross' => $seller_receivable['gross_amount']['value'],
                'payment_fee' => $seller_receivable['paypal_fee']['value'],
                'settle_amount' => $seller_receivable['receivable_amount']['value'] ?? $seller_receivable['net_amount']['value'],
                'settle_currency' => $seller_receivable['receivable_amount']['currency_code'] ?? $seller_receivable['net_amount']['currency_code'],
                'exchange_rate' => $seller_receivable['exchange_rate']['value'] ?? 'null',
            ];
        }

        // -----
        // Set information used by the after_process method's status-history record creation.
        //
        $payment_type = array_key_first($this->orderInfo['payment_source']);
        $this->orderInfo['payment_info'] = [
            'payment_type' => $payment_type,
            'amount' => $payment['amount']['value'] . ' ' . $payment['amount']['currency_code'],
            'created_date' => $payment['created_date'] ?? '',
        ];

        $expiration_time = (isset($this->orderInfo['expiration_time'])) ? Helpers::convertPayPalDatePay2Db($this->orderInfo['expiration_time']) : 'null';
        $num_cart_items = $_SESSION['cart']->count_contents();
        $sql_data_array = [
            'order_id' => $orders_id,
            'txn_type' => 'CREATE',
            'module_name' => $this->code,
            'module_mode' => $this->orderInfo['txn_type'],
            'reason_code' => $payment['status_details']['reason'] ?? '',
            'payment_type' => $payment_type,
            'payment_status' => $this->orderInfo['payment_status'],
            'invoice' => $purchase_unit['invoice_id'] ?? $purchase_unit['custom_id'] ?? '',
            'mc_currency' => $payment['amount']['currency_code'],
            'first_name' => substr($this->orderInfo['payer']['name']['given_name'], 0, 32),
            'last_name' => substr($this->orderInfo['payer']['name']['surname'], 0, 32),
            'payer_email' => $this->orderInfo['payer']['email_address'],
            'payer_id' => $this->orderInfo['payer']['payer_id'],
            'payer_status' => $this->orderInfo['payment_source'][$payment_type]['account_status'] ?? 'UNKNOWN',
            'receiver_email' => $purchase_unit['payee']['email_address'],
            'receiver_id' => $purchase_unit['payee']['merchant_id'],
            'txn_id' => $this->orderInfo['id'],
            'num_cart_items' => $num_cart_items,
            'mc_gross' => $payment['amount']['value'],
            'date_added' => Helpers::convertPayPalDatePay2Db($this->orderInfo['create_time']),
            'last_modified' => Helpers::convertPayPalDatePay2Db($this->orderInfo['update_time']),
            'notify_version' => self::CURRENT_VERSION,
            'expiration_time' => $expiration_time,
        ];
        $sql_data_array = array_merge($sql_data_array, $address_info, $payment_info);
        zen_db_perform(TABLE_PAYPAL, $sql_data_array);

        $sql_data_array = [
            'order_id' => $orders_id,
            'txn_type' => $this->orderInfo['txn_type'],
            'final_capture' => (int)($this->orderInfo['txn_type'] === 'CAPTURE'),
            'module_name' => $this->code,
            'module_mode' => '',
            'reason_code' => $payment['status_details']['reason'] ?? '',
            'payment_type' => $payment_type,
            'payment_status' => $payment['status'],
            'mc_currency' => $payment['amount']['currency_code'],
            'txn_id' => $payment['id'],
            'parent_txn_id' => $this->orderInfo['id'],
            'num_cart_items' => $num_cart_items,
            'mc_gross' => $payment['amount']['value'],
            'notify_version' => self::CURRENT_VERSION,
            'date_added' => Helpers::convertPayPalDatePay2Db($payment['create_time']),
            'last_modified' => Helpers::convertPayPalDatePay2Db($payment['update_time']),
            'expiration_time' => $expiration_time,
        ];
        $sql_data_array = array_merge($sql_data_array, $payment_info);
        zen_db_perform(TABLE_PAYPAL, $sql_data_array);
    }

    /**
     * Issued at the tail-end of the checkout_process' header_php.php, indicating that the
     * order's been recorded in the database and any required emails sent.
     *
     * Add a customer-visible order-status-history record identifying the
     * associated transaction ID, payment method, timestamp, status and amount.
     */
    public function after_process()
    {
        $payment_info = $this->orderInfo['payment_info'];
        $timestamp = '';
        if ($payment_info['created_date'] !== '') {
            $timestamp = 'Timestamp: ' . $payment_info['created_date'] . "\n";
        }

        $message =
            'Transaction ID: ' . $this->orderInfo['id'] . "\n" .
            'Payment Type: PayPal Checkout (' . $payment_info['payment_type'] . ")\n" .
            $timestamp .
            'Payment Status: ' . $this->orderInfo['payment_status'] . "\n" .
            'Amount: ' . $payment_info['amount'];
        zen_update_orders_history($this->orderInfo['orders_id'], $message, null, -1, 0);

        // -----
        // If the order's processing requires an admin-alert, send one if so configured.
        //
        if ($this->orderInfo['admin_alert_needed'] === true) {
            $this->sendAlertEmail(
                MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT_ORDER_ATTN,
                sprintf(MODULE_PAYMENT_PAYPALR_ALERT_ORDER_CREATION, $this->orderInfo['orders_id'], $this->orderInfo['paypal_payment_status'])
            );
        }

        $this->resetOrder();
    }

    /**
      * Build admin-page components
      *
      * @param int $zf_order_id
      * @return string
      */
    public function admin_notification($zf_order_id)
    {
        $admin_main = new AdminMain($this->code, self::CURRENT_VERSION, (int)$zf_order_id, $this->ppr);

        return $admin_main->get();
    }

    public function help()
    {
        return [
            'link' => 'https://github.com/lat9/paypalr/wiki'
        ];
    }

    /**
     * Determine whether the shipping-edit button should be displayed or not
     */
    public function alterShippingEditButton()
    {
        return false;
    }

    /**
     * Used to submit a refund for a given payment-capture for an order.
     */
    public function _doRefund($oID)
    {
        global $messageStack;

        $oID = (int)$oID;

        if (!isset($_POST['ppr-amount'], $_POST['doRefundOid'], $_POST['capture_txn_id'], $_POST['ppr-refund-note']) || $oID !== (int)$_POST['doRefundOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($this->code, self::CURRENT_VERSION, $oID, $this->ppr);
        $ppr_capture_db_txns = $ppr_txns->getDatabaseTxns('CAPTURE');
        if (count($ppr_capture_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'CAPTURE', $oID), 'error');
            return;
        }

        $capture_id_txn = false;
        foreach ($ppr_capture_db_txns as $next_txn) {
            if ($next_txn['txn_id'] === $_POST['capture_txn_id']) {
                $capture_id_txn = $next_txn;
                break;
            }
        }
        if ($capture_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_PARAM_ERROR, 2), 'error');
            return;
        }

        $capture_currency = $capture_id_txn['mc_currency'];

        $payer_note = $_POST['ppr-refund-note'];
        $invoice_id = $ppr_txns->getInvoiceId();

        $full_refund = isset($_POST['ppr-refund-full']);
        if ($full_refund === true) {
            $refund_response = $this->ppr->refundCaptureFull($_POST['capture_txn_id'], $invoice_id, $payer_note);
        } else {
            $amount = new Amount($capture_currency);
            $refund_amount = $amount->getValueFromString($_POST['ppr-amount']);
            $refund_response = $this->ppr->refundCapturePartial($_POST['capture_txn_id'], $capture_currency, $refund_amount, $invoice_id, $payer_note);
        }

        if ($refund_response === false) {
            $error_info = $this->ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                default:
                    $error_message = MODULE_PAYMENT_PAYPALR_REFUND_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $amount_refunded = $refund_response['amount']['value'] . ' ' . $refund_response['amount']['currency_code'];
        $payer_note = "\n$payer_note";

        $refund_memo = sprintf(MODULE_PAYMENT_PAYPALR_REFUND_MEMO, zen_updated_by_admin(), $amount_refunded) . "\n" . $payer_note;
        $ppr_txns->addDbTransaction('REFUND', $refund_response, $refund_memo);

        $parent_capture_status = $this->ppr->getCaptureStatus($_POST['capture_txn_id']);
        if ($parent_capture_status === false) {
            $messageStack->add_session("Error retrieving capture status:\n" . json_encode($this->ppr->getErrorInfo()), 'warning');
        } else {
            $ppr_txns->updateParentTxnDateAndStatus($parent_capture_status);
        }

        $ppr_txns->updateMainTransaction($refund_response);

        $comments =
            'REFUNDED. Trans ID: ' . $refund_response['id'] . "\n" .
            'Amount: ' . $amount_refunded . "\n" .
            $payer_note;

        if (($capture_id_txn['mc_gross'] . ' ' . $capture_currency) !== $refund_amount) {
            $capture_status = -1;
        } else {
            $refund_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;   //-FIXME:  There might be multiple captures to be refunded
            $refund_status = ($order_status > 0) ? $order_status : 2;
        }
        zen_update_orders_history($oID, $comments, null, $refund_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REFUND_COMPLETE, $oID), 'success');
    }

    /**
     * Used to re-authorize a previously-created transaction, possibly changing the
     * authorized value.
     */
    public function _doAuth($oID, $order_amt, $currency = 'USD')
    {
        global $db, $messageStack;

        $oID = (int)$oID;

        if (!isset($_POST['ppr-amount'], $_POST['doAuthOid'], $_POST['auth_txn_id']) || $oID !== (int)$_POST['doAuthOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($this->code, self::CURRENT_VERSION, $oID, $this->ppr);
        $ppr_db_txns = $ppr_txns->getDatabaseTxns('AUTHORIZE');
        if (count($ppr_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'AUTHORIZE', $oID), 'error');
            return;
        }

        $auth_id_txn = false;
        foreach ($ppr_txns as $next_txn) {
            if ($next_txn_id === $_POST['auth_txn_id']) {
                $auth_id_txn = $next_txn;
                break;
            }
        }
        if ($auth_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_PARAM_ERROR, 2), 'error');
            return;
        }

        $auth_currency = $auth_id_txn['mc_currency'];
        $amount = new Amount($auth_currency);
        $auth_amount = $amount->getAmountFromString($_POST['ppr-amount']);

        $auth_response = $this->ppr->reAuthorizePayment($_POST['auth_txn_id'], $auth_currency, $auth_amount);
        if ($auth_response === false) {
            $error_info = $this->ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                case 'REAUTHORIZATION_TOO_SOON':
                    $error_message = MODULE_PAYMENT_PAYPALR_REAUTH_TOO_SOON;
                    break;
                default:
                    $error_message = MODULE_PAYMENT_PAYPALR_REAUTH_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $amount = $auth_response['amount']['value'] . ' ' . $auth_response['amount']['currency_code'];
        $reauth_memo = sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_MEMO, zen_updated_by_admin(), $amount);
        $ppr_txns->addDbTransaction('AUTHORIZE', $auth_response, $reauth_memo);
        $ppr_txns->updateMainTransaction($auth_response);

        // -----
        // A re-authorization transaction, for whatever reason, doesn't return its 'parent'
        // transaction id (the authorization just updated) in its response.  To keep the
        // parent/child chain valid in the database, update the just-created re-authorization
        // to reflect its parent authorization.
        //
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL . "
                SET parent_txn_id = '" . $_POST['auth_txn_id'] . "'
              WHERE txn_id = '" . $auth_response['id'] . "'
              LIMIT 1"
        );

        // -----
        // A re-authorization doesn't change an order's status.  Write an orders-history
        // record containing information for the admin's hidden view.
        //
        $comments =
            'AUTHORIZATION ADDED. Trans ID: ' . $auth_response['id'] . "\n" .
            'Amount: ' . $amount;
        zen_update_orders_history($oID, $comments);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_COMPLETE, $amount), 'success');
    }

    /**
     * Used to capture part or all of a given previously-authorized transaction.  A capture is
     * performed against the most recent authorization (or re-authorization).
     */
    public function _doCapt($oID, $captureType = 'Complete', $order_amt = 0, $order_currency = 'USD')
    {
        global $db, $messageStack;

        $oID = (int)$oID;

        if (!isset($_POST['ppr-amount'], $_POST['doCaptOid'], $_POST['auth_txn_id'], $_POST['ppr-capt-note']) || $oID !== (int)$_POST['doCaptOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_CAPTURE_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($this->code, self::CURRENT_VERSION, $oID, $this->ppr);
        $ppr_auth_db_txns = $ppr_txns->getDatabaseTxns('AUTHORIZE');
        if (count($ppr_auth_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'AUTHORIZE', $oID), 'error');
            return;
        }

        $auth_id_txn = false;
        foreach ($ppr_auth_db_txns as $next_txn) {
            if ($next_txn['txn_id'] === $_POST['auth_txn_id']) {
                $auth_id_txn = $next_txn;
                break;
            }
        }
        if ($auth_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_REAUTH_PARAM_ERROR, 2), 'error');
            return;
        }

        $capt_currency = $auth_id_txn['mc_currency'];
        $amount = new Amount($capt_currency);
        $capt_amount = $amount->getValueFromString($_POST['ppr-amount']);
        $payer_note = $_POST['ppr-capt-note'];
        $final_capture = isset($_POST['final_capture']);

        $capture_response = $this->ppr->capturePayment($_POST['auth_txn_id'], $capt_currency, $capt_amount, $ppr_txns->getInvoiceId(), $payer_note, $final_capture);
        if ($capture_response === false) {
            $error_info = $this->ppr->getErrorInfo();
            $issue = $error_info['details'][0]['issue'] ?? '';
            switch ($issue) {
                default:
                    $error_message = MODULE_PAYMENT_PAYPALR_CAPTURE_ERROR . "\n" . json_encode($error_info);
                    break;
            }
            $messageStack->add_session($error_message, 'error');
            return;
        }

        $amount = $capture_response['amount']['value'] . ' ' . $capture_response['amount']['currency_code'];
        $payer_note = "\n$payer_note";

        $capture_memo_message = ($final_capture === true) ? MODULE_PAYMENT_PAYPALR_FINAL_CAPTURE_MEMO : MODULE_PAYMENT_PAYPALR_PARTIAL_CAPTURE_MEMO;
        $capture_memo = sprintf($capture_memo_message, zen_updated_by_admin(), $amount) . "\n" . $payer_note;
        $ppr_txns->addDbTransaction('CAPTURE', $capture_response, $capture_memo);

        $parent_auth_status = $this->ppr->getAuthorizationStatus($_POST['auth_txn_id']);
        if ($parent_auth_status === false) {
            $messageStack->add_session("Error retrieving authorization status:\n" . json_encode($this->ppr->getErrorInfo()), 'warning');
        } else {
            $ppr_txns->updateParentTxnDateAndStatus($parent_auth_status);
        }

        $ppr_txns->updateMainTransaction($capture_response);

        $comments =
            'FUNDS CAPTURED. Trans ID: ' . $capture_response['id'] . "\n" .
            "Amount: $amount\n" .
            $payer_note;

        if ($final_capture === false) {
            $capture_status = -1;
        } else {
            $capture_status = (int)MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID;
            $capture_status = ($order_status > 0) ? $order_status : 2;
        }
        zen_update_orders_history($oID, $comments, null, $capture_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_CAPTURE_COMPLETE, $oID), 'success');
    }

    /**
     * Used to void a given previously-authorized transaction.
     *
     * NOTE: Once a PayPal transaction is voided, it is REMOVED from PayPal's
     * history and any request to retrieve the order's PayPal status will result
     * a RESOURCE_NOT_FOUND (404) error!
     */
    public function _doVoid($oID)
    {
        global $db, $messageStack;

        $oID = (int)$oID;
        $module_name = '<em>' . $this->code . '</em>';

        if (!isset($_POST['ppr-void-id'], $_POST['doVoidOid'], $_POST['ppr-void-note']) || $oID !== (int)$_POST['doVoidOid']) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_PARAM_ERROR, 1), 'error');
            return;
        }

        $ppr_txns = new GetPayPalOrderTransactions($this->code, self::CURRENT_VERSION, $oID, $this->ppr);
        $ppr_db_txns = $ppr_txns->getDatabaseTxns('AUTHORIZE');
        if (count($ppr_db_txns) === 0) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_NO_RECORDS, 'AUTHORIZE', $oID), 'error');
            return;
        }

        $auth_id_txn = false;
        foreach ($ppr_txns as $next_txn) {
            if ($next_txn_id === $_POST['ppr-void-id']) {
                $auth_id_txn = $next_txn;
                break;
            }
        }
        if ($auth_id_txn === false) {
            $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_PARAM_ERROR, 2), 'error');
            return;
        }

        $void_response = $this->ppr->voidPayment($_POST['ppr-void-id']);
        if ($void_response === false) {
             $messageStack->add_session(MODULE_PAYMENT_PAYPALR_VOID_ERROR . "\n" . json_encode($this->ppr->getErrorInfo()), 'error');
             return;
        }

        // -----
        // Note: An authorization void returns *no additional information*, with a 204 http-code.
        // Simply update this authorization's status to indicate that it's been voided.
        //
        $void_memo = sprintf(MODULE_PAYMENT_PAYPALR_VOID_MEMO, zen_updated_by_admin()) . "\n\n";
        $void_note = strip_tags($_POST['ppr-void-note']);
        $modification_date = Helpers::convertPayPalDatePay2Db($void_response['update_time']);
        $memo = zen_db_input("\n$modification_date: $void_memo$void_note");
        $db->Execute(
            "UPDATE " . TABLE_PAYPAL . "
                SET last_modified = '$modification_date',
                    payment_status = 'VOIDED',
                    notify_version = '" . $this->moduleVersion . "',
                    memo = CONCAT(IFNULL(memo, ''), '$memo'),
                    last_updated = now()
              WHERE paypal_ipn_id = " . $auth_id_txn['paypal_ipn_id'] . "
              LIMIT 1"
        );

        $comments =
            'VOIDED. Trans ID: ' . $last_auth_txn['txn_id'] . "\n" .
            $void_note;

        $voided_status = (int)MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID;
        $voided_status = ($voided_status > 0) ? $voided_status : 1;
        zen_update_orders_history($oID, $comments, null, $voided_status, 0);

        $messageStack->add_session(sprintf(MODULE_PAYMENT_PAYPALR_VOID_COMPLETE, $oID), 'warning');
    }

    /**
     * Evaluate installation status of this module. Returns true if the status key is found.
     */
    public function check(): bool
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT configuration_value
                   FROM " . TABLE_CONFIGURATION . "
                  WHERE configuration_key = 'MODULE_PAYMENT_PAYPALR_STATUS'
                  LIMIT 1"
            );
            $this->_check = !$check_query->EOF;
        }
        return $this->_check;
    }

    /**
     * Installs all the configuration keys for this module
     */
    public function install()
    {
        global $db, $sniffer;

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

                ('Completed Order Status', 'MODULE_PAYMENT_PAYPALR_ORDER_STATUS_ID', '2', 'Set the status of orders whose payment has been successfully <em>captured</em> to this value.<br><b>Recommended: Processing[2]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('Set Unpaid Order Status', 'MODULE_PAYMENT_PAYPALR_ORDER_PENDING_STATUS_ID', '1', 'Set the status of orders whose payment has been successfully <em>authorized</em> to this value.<br><b>Recommended: Pending[1]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('Set Refund Order Status', 'MODULE_PAYMENT_PAYPALR_REFUNDED_STATUS_ID', '1', 'Set the status of <em><b>fully</b>-refunded<em> or <em>voided</em> orders to this value.<br><b>Recommended: Pending[1]</b>', 6, 0, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now()),

                ('PayPal Page Style', 'MODULE_PAYMENT_PAYPALR_PAGE_STYLE', 'Primary', 'The page-layout style you want customers to see when they visit the PayPal site. You can configure your <b>Custom Page Styles</b> in your PayPal Profile settings. This value is case-sensitive.', 6, 0, NULL, NULL, now()),

                ('Store (Brand) Name at PayPal', 'MODULE_PAYMENT_PAYPALR_BRANDNAME', '', 'The name of your store as it should appear on the PayPal login page. If blank, your store name will be used.', 6, 0, NULL, NULL, now()),

                ('Payment Action', 'MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE', 'Final Sale', 'How do you want to obtain payment?<br><b>Default: Final Sale</b>', 6, 0, 'zen_cfg_select_option([\'Auth Only\', \'Final Sale\'], ', NULL,  now()),

                ('Transaction Currency', 'MODULE_PAYMENT_PAYPALR_CURRENCY', 'Selected Currency', 'In which currency should the order be sent to PayPal?<br>NOTE: If an unsupported currency is sent to PayPal, it will be auto-converted to the <em>Fall-back Currency</em>.<br><b>Default: Selected Currency</b>', 6, 0, 'zen_cfg_select_option([\'Selected Currency\', $currencies_list], ', NULL, now()),

                ('Fall-back Currency', 'MODULE_PAYMENT_PAYPALR_CURRENCY_FALLBACK', 'USD', 'If the <b>Transaction Currency</b> is set to <em>Selected Currency</em>, what currency should be used as a fall-back when the customer\'s selected currency is not supported by PayPal?<br><b>Default: USD</b>', 6, 0, 'zen_cfg_select_option([\'USD\', \'GBP\'], ', NULL, now()),

                ('Debug Mode', 'MODULE_PAYMENT_PAYPALR_DEBUGGING', 'Off', 'Would you like to enable debug mode?  A complete detailed log of failed transactions will be emailed to the store owner.', 6, 0, 'zen_cfg_select_option([\'Off\', \'Alerts Only\', \'Log File\', \'Log and Email\'], ', NULL, now())"
        );

        // -----
        // Make any modifications to the 'paypal' table, if not already done.
        //
        // 1. Adding an order's re-authorize time limit, so it's always available.
        //
        if ($sniffer->field_exists(TABLE_PAYPAL, 'expiration_time') === false) {
            $db->Execute(
                "ALTER TABLE " . TABLE_PAYPAL . "
                   ADD expiration_time datetime default NULL AFTER date_added"
            );
        }

        // -----
        // 2. Increasing the number of characters in 'notify_version' (was varchar(6) NOT NULL default '')
        // since the payment-module's version will be stored there.
        //
        if ($sniffer->field_type(TABLE_PAYPAL, 'notify_version', 'varchar(20)') === false) {
            $db->Execute(
                "ALTER TABLE " . TABLE_PAYPAL . "
                   MODIFY notify_version varchar(20) NOT NULL default ''"
            );
        }

        // -----
        // 3. Adding a final_capture flag (0/1) for use in the payment module's admin
        // notifications handling.
        //
        if ($sniffer->field_exists(TABLE_PAYPAL, 'final_capture') === false) {
            $db->Execute(
                "ALTER TABLE " . TABLE_PAYPAL . "
                   ADD final_capture tinyint(1) NOT NULL default 0 AFTER txn_type"
            );
        }

        // -----
        // 4. Increasing the number of characters in 'pending_reason' (was varchar(32) default NULL)
        // since some of the status_details::reason codes, e.g. RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION,
        // won't fit and result in a MySQL error otherwise.
        //
        if ($sniffer->field_type(TABLE_PAYPAL, 'pending_reason', 'varchar(64)') === false) {
            $db->Execute(
                "ALTER TABLE " . TABLE_PAYPAL . "
                   MODIFY pending_reason varchar(64) default NULL"
            );
        }

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
            'MODULE_PAYMENT_PAYPALR_PAGE_STYLE',
            'MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE',
            'MODULE_PAYMENT_PAYPALR_DEBUGGING',
        ];

        $this->notify('NOTIFY_PAYMENT_PAYPALR_UNINSTALLED');
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
    public function sendAlertEmail(string $subject_detail, string $message)
    {
        if ($this->emailAlerts === true) {
            zen_mail(
                STORE_NAME,
                STORE_OWNER_EMAIL_ADDRESS,
                sprintf(MODULE_PAYMENT_PAYPALR_ALERT_SUBJECT, $subject_detail),
                $message,
                STORE_OWNER,
                STORE_OWNER_EMAIL_ADDRESS,
                ['EMAIL_MESSAGE_HTML' => nl2br($message, false)],   //- Replace new-lines with HTML5 <br>
                'paymentalert'
            );
        }
    }
}
