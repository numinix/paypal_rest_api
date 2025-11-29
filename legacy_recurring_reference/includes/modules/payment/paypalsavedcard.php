<?php
//
// paypalsavedcard.php v1.1 2017  Numinix Technology $
// this module can work with payflow or paypalwpp
//
class paypalsavedcard {
  var $code, $title, $description, $enabled, $PayPalwpp, $Payflow, $PayPalRestful, $paypalSavedCardRecurring, $action;
  var $_logDir = 'includes/modules/payment/paypal/logs/';
  var $_logLevel = 0;
// class constructor
  function __construct($paypalSavedCardRecurring = null) {
    global $order, $PayPal;
    $this->code = 'paypalsavedcard';
    $this->title = defined('MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_TITLE') ? MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_TITLE : '';
    $this->description = defined('MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_DESCRIPTION') ? MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_DESCRIPTION : '';
    $this->api_type = MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE;
//      $this->email_footer = MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_EMAIL_FOOTER;
    $this->sort_order = MODULE_PAYMENT_PAYPALSAVEDCARD_SORT_ORDER;
    $this->enabled = ((MODULE_PAYMENT_PAYPALSAVEDCARD_STATUS == 'True') ? true : false);
    if ((int) MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID > 0) {
      $this->order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID;
    }
    if (IS_ADMIN_FLAG === false) {
      if (is_object($order))
        $this->update_status();
      if ($paypalSavedCardRecurring != null) {
        $this->paypalSavedCardRecurring = $paypalSavedCardRecurring;
      }
      else {
        if (!class_exists('paypalSavedCardRecurring')) {
          require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'paypalSavedCardRecurring.php');
        }
        $this->paypalSavedCardRecurring = new paypalSavedCardRecurring($this);
      }
      switch (MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE) {
        case 'payflow' :
          $this->initiate_payflow();
          break;
        case 'paypalr' :
          $this->initiate_paypalr();
          break;
        default :
          $this->initiate_paypalwpp();
          break;
      }
    }
  }
  function initiate_payflow() {
    if (!class_exists('payflow')) {
      require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/payflow.php');
    }
    $Payflow = new payflow;
    $this->Payflow = $Payflow->paypal_init();
  }
  function initiate_paypalwpp() {
    require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_curl.php');
    if (!class_exists('PayPal')) {
      require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
    }
    $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
    $this->PayPalwpp = new PayPal($PayPalConfig);
  }
  function initiate_paypalr() {
    $autoload = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
    if (!class_exists('PayPalRestful\\Api\\PayPalRestfulApi')) {
      if (file_exists($autoload)) {
        require_once ($autoload);
      }
    }
    if (!class_exists('PayPalRestful\\Api\\PayPalRestfulApi')) {
      $this->notify_error('PayPal REST library missing', 'The PayPal REST autoloader could not be found at ' . $autoload . '. Please ensure the PayPal REST payment module is installed.', 'warning');
      return false;
    }
    $clientId = defined('MODULE_PAYMENT_PAYPALR_CLIENT_ID') ? MODULE_PAYMENT_PAYPALR_CLIENT_ID : '';
    $clientSecret = defined('MODULE_PAYMENT_PAYPALR_CLIENT_SECRET') ? MODULE_PAYMENT_PAYPALR_CLIENT_SECRET : '';
    $environment = '';
    if (defined('MODULE_PAYMENT_PAYPALR_ENVIRONMENT')) {
      $environment = MODULE_PAYMENT_PAYPALR_ENVIRONMENT;
    }
    elseif (defined('MODULE_PAYMENT_PAYPALR_MODE')) {
      $environment = MODULE_PAYMENT_PAYPALR_MODE;
    }
    if ($environment === '') {
      $environment = 'sandbox';
    }
    try {
      $this->PayPalRestful = new PayPalRestful\Api\PayPalRestfulApi($environment, $clientId, $clientSecret);
    }
    catch (Exception $e) {
      $this->notify_error('Unable to initialize PayPal REST API', 'The PayPal REST API client failed to initialize. Message: ' . $e->getMessage(), 'warning');
      return false;
    }
    return $this->PayPalRestful;
  }
// class methods
  function update_status() {
    global $order, $db;
    if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAYPALSAVEDCARD_ZONE > 0)) {
      $check_flag = false;
      $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPALSAVEDCARD_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
      while (!$check->EOF) {
        if ($check->fields['zone_id'] < 1) {
          $check_flag = true;
          break;
        }
        elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
          $check_flag = true;
          break;
        }
        $check->MoveNext();
      }
      if ($check_flag == false) {
        $this->enabled = false;
      }
    }
  }
  function javascript_validation() {
    return false;
  }
  function selection() {
    return array('id' => $this->code, 'module' => $this->title);
  }
  function pre_confirmation_check() {
    return false;
  }
  function confirmation() {
    global $db;
    $saved_card = $this->get_card_details($_SESSION['saved_card_id']);
    $confirmation = array('title' => '', 'fields' => array(array('title' => $saved_card['type'] . ' ending in ' . $saved_card['last_digits'], 'field' => '')));
    return $confirmation;
  }
  function process_button() {
    return false;
  }
  function before_process() {
    global $db, $order, $messageStack;
    if (MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE == 'paypalr') {
      $this->initiate_paypalr();
    }
    else {
      require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_curl.php');
      require_once (DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
    }
    $error = false;
    $saved_card_id = $_SESSION['saved_card_id'];
    $this->saved_card_id = $saved_card_id;
//      $currency = $paypalwpp->selectCurrency($order->info['currency']);
//      $order_amount = $paypalwpp->calc_order_amount($order->info['total'], $currency, FALSE);
    $saved_card = $this->get_card_details($saved_card_id);
    if (strlen($saved_card['paypal_transaction_id']) > 0) {
//if any items in the cart have a future start date, do an authorization only
      if ($this->paypalSavedCardRecurring->order_contains_future_start_date()) {
        $this->action = 'Authorization';
      }
      else {
        $this->action = 'Sale';
      }
      $error = $this->process($this->action, $saved_card['paypal_transaction_id'], $order->info['total']);
    }
    else {
      $error = true;
//no transaction_id
    }
    if ($error) {
      $messageStack->add_session('checkout_payment', MODULE_PAYMENT_PAYPALSAVEDCARD_TEXT_ERROR_MESSAGE, 'error');
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
    }
  }
/*
*  Function to process the payment with PayPal.  Called from this module during checkout and also from the saved
* cards recrring cron.
* $action can be 'Sale' or 'Authorization'
*/
  function process($action, $transaction_id, $amount) {
    global $order;
    $details = $this->get_card_details_by_transaction_id($transaction_id);
    if ($details['api_type'] != MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE) {
      $this->notify_error('Saved Card API discrepency', 'The API for saved cards is currently set to ' . MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE . ' but the saved cards module is attempting to process a card which was saved with ' . $details['api_type'] . ' We will attempt to process the saved card using ' . $details['api_type'] . '.  You will receive an error message if it fails. Details of order: ' . json_encode($order), 'warning');
    }
    if (MODULE_PAYMENT_PAYPALSAVEDCARD_MODE == 'Dev') {
      $response['ACK'] = 'Success';
      $this->notify_error('Saved Card processed in dev mode', 'This order was run through in dev mode and payment was not actually taken.  Details of order: ' . json_encode($order), 'warning');
    }
    elseif ($details['api_type'] == 'payflow') {
      return $this->process_with_payflow($action, $transaction_id, $amount);
    }
    elseif ($details['api_type'] == 'paypalr') {
      return $this->process_with_paypalr($action, $transaction_id, $amount);
    }
    else {
      return $this->process_with_paypalwpp($action, $transaction_id, $amount);
    }
  }
  function process_with_paypalwpp($action, $transaction_id, $amount) {
    global $order;
    if (!$this->PayPalwpp) {
      $this->initiate_paypalwpp();
    }
    if($action == 'Sale'){
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID;
    } else {
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID;
    }
    $this->responsedata = $response = $this->PayPalwpp->DoReferenceTransaction(array('DRTFields' => array('PAYMENTACTION' => $action, 'REFERENCEID' => $transaction_id, 'VERSION' => '78', 'AMT' => number_format($amount, 2, '.', ''))));
    if ($response['ACK'] == 'Success') {
      $this->payment_status = 'Completed';
      $this->transaction_id = $response['TRANSACTIONID'];
      $this->order_status = $new_order_status;
      return false; //no error
    }
    else {
      $this->notify_error('Error processing saved card with paypalwpp', 'There was an error returned from PayPal while processing a saved card.  Details of order: ' . json_encode($order) . ' Error message: ' . $response['L_ERRORCODE0'] . '  ' . $response['L_LONGMESSAGE0']);
      return $response['L_ERRORCODE0'] . '  ' . $response['L_LONGMESSAGE0'];
    }
  }
  function process_with_payflow($action, $transaction_id, $amount) {
    global $order;
    if (!$this->Payflow) {
      $this->initiate_payflow();
    }
    $optionsAll['TRXTYPE'] = ($this->action == 'Authorization' ? 'A' : 'S'); //upload transactiontype for saving credit card
    $optionsAll['PAYMENTACTION'] = $action; //amount must be > 0, so do an authorization so the customer is not charged
    $optionsAll['ORIGID'] = $transaction_id;
    $optionsAll['INVNUM'] = (int)$_SESSION['customer_id'] . '-' . time() . '-[' . substr(STORE_NAME, 0, 30) . ']-saved-credit-card';
    $optionsAll['AMT'] = number_format($amount, 2, '.', '');
    $this->responsedata = $response = $this->Payflow->DoDirectPayment(null, null, null, null, null, null, $optionsAll, null);
    if ($response['RESULT'] == 0) {
      if ($response['PNREF']) {
        if(isset($response['DUPLICATE']) && $response['DUPLICATE'] > 0) {
          $this->notify_error('Processing saved card with Payflow', 'While attempting to process a saved card with Payflow, Paypal is able to process the payment but it treats the payment as a duplicate: ' . $response['RESULT'] . ' ' . $response['DUPLICATE'] . "\n\n  Details of order:  " . json_encode($order));
          return $response['RESULT'] . ' ' . $response['DUPLICATE'];
        } else {
          // PNREF only comes from payflow mode
          $this->payment_type = MODULE_PAYMENT_PAYFLOW_PF_TEXT_TYPE;
          $this->transaction_id = $response['PNREF'];
          $this->payment_status = (MODULE_PAYMENT_PAYFLOW_TRANSACTION_MODE == 'Auth Only') ? 'Authorization' : 'Completed';
          $this->avs = 'AVSADDR: ' . $response['AVSADDR'] . ', AVSZIP: ' . $response['AVSZIP'] . ', IAVS: ' . $response['IAVS'];
          $this->cvv2 = $response['CVV2MATCH'];
          $this->amt = number_format($amount, 2, '.', '');
          $this->payment_time = date('Y-m-d h:i:s');
          $this->responsedata['CURRENCYCODE'] = $order->info['currency'];
          $this->responsedata['EXCHANGERATE'] = $order->info['currency_value'];
          $this->auth_code = $this->response['AUTHCODE'];
        }
      } else {
        // here we're in NVP mode
        $this->transaction_id = $response['TRANSACTIONID'];
        $this->payment_type = MODULE_PAYMENT_PAYFLOW_DP_TEXT_TYPE;
        $this->payment_status = (MODULE_PAYMENT_PAYFLOW_TRANSACTION_MODE == 'Auth Only') ? 'Authorization' : 'Completed';
        $this->pendingreason = (MODULE_PAYMENT_PAYFLOW_TRANSACTION_MODE == 'Auth Only') ? 'authorization' : '';
        $this->avs = $response['AVSCODE'];
        $this->cvv2 = $response['CVV2MATCH'];
        $this->correlationid = $response['CORRELATIONID'];
        $this->payment_time = urldecode($response['TIMESTAMP']);
        $this->amt = urldecode($response['AMT'] . ' ' . $response['CURRENCYCODE']);
        $this->auth_code = (isset($this->response['AUTHCODE'])) ? $this->response['AUTHCODE'] : $this->response['TOKEN'];
        $this->transactiontype = 'cart';
      }

      if($action == 'Sale'){
        $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID;
      } else {
        $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID;
      }

      $this->order_status = $new_order_status;
      return false;
//no error
    }
    else {
      $this->notify_error('Processing saved card with Payflow', 'While attempting to process a saved card with Payflow, Paypal returned this error: ' . $response['RESULT'] . ' ' . $response['RESPMSG'] . "\n\n  Details of order:  " . json_encode($order));
      return $response['RESULT'] . ' ' . $response['RESPMSG'];
    }
  }
  function process_with_paypalr($action, $transaction_id, $amount) {
    global $order;
    if (!$this->PayPalRestful) {
      if (!$this->initiate_paypalr()) {
        return 'PayPal REST client unavailable';
      }
    }
    $intent = ($action == 'Authorization') ? 'AUTHORIZE' : 'CAPTURE';
    $currency = isset($order->info['currency']) ? $order->info['currency'] : (defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD');
    $orderAmount = number_format($amount, 2, '.', '');
    $request = array('intent' => $intent, 'purchase_units' => array(array('amount' => array('currency_code' => $currency, 'value' => $orderAmount))));
    $cardDetails = $this->get_card_details_by_transaction_id($transaction_id);
    $cardPayload = $this->buildVaultPaymentSource($cardDetails);
    if (!empty($cardPayload) && isset($cardPayload['vault_id'])) {
      $request['payment_source'] = array('card' => $cardPayload);
      $transaction_id = $cardPayload['vault_id'];
    } elseif (strlen($transaction_id) > 0) {
      $request['payment_source'] = array('token' => array('id' => $transaction_id, 'type' => 'BILLING_AGREEMENT'));
    }
    if (!isset($request['payment_source'])) {
      $this->notify_error('Missing PayPal REST payment source', 'No payment source was available while processing a saved card transaction. Details: ' . json_encode($cardDetails), 'error');
      return 'Missing PayPal REST payment source';
    }
    try {
      $createResponse = $this->PayPalRestful->createOrder($request);
    }
    catch (Exception $e) {
      $this->notify_error('Error creating PayPal REST order', 'PayPal REST threw an exception while creating an order. Details: ' . $e->getMessage() . ' Order: ' . json_encode($order), 'error');
      return $e->getMessage();
    }
    $normalizedCreate = $this->normalize_rest_response($createResponse);
    $orderId = isset($normalizedCreate['id']) ? $normalizedCreate['id'] : '';
    if (strlen($orderId) == 0) {
      $this->notify_error('Invalid PayPal REST order response', 'PayPal REST order creation did not return an order id. Response: ' . json_encode($normalizedCreate), 'error');
      return 'Unable to create PayPal order';
    }
    $this->responsedata = array('ORDER_ID' => $orderId, 'INTENT' => $intent, 'CURRENCYCODE' => $currency, 'AMT' => $orderAmount);
    $this->responsedata['CREATE_ORDER_RESPONSE'] = $normalizedCreate;
    try {
      if ($intent == 'AUTHORIZE') {
        if (method_exists($this->PayPalRestful, 'authorizeOrder')) {
          $captureResponse = $this->PayPalRestful->authorizeOrder($orderId);
        }
        elseif (method_exists($this->PayPalRestful, 'authorize')) {
          $captureResponse = $this->PayPalRestful->authorize($orderId);
        }
        else {
          $captureResponse = array();
        }
      }
      else {
        if (method_exists($this->PayPalRestful, 'captureOrder')) {
          $captureResponse = $this->PayPalRestful->captureOrder($orderId);
        }
        elseif (method_exists($this->PayPalRestful, 'capture')) {
          $captureResponse = $this->PayPalRestful->capture($orderId);
        }
        else {
          $captureResponse = array();
        }
      }
    }
    catch (Exception $e) {
      $this->notify_error('Error finalizing PayPal REST order', 'PayPal REST threw an exception while finalizing order ' . $orderId . '. Details: ' . $e->getMessage(), 'error');
      return $e->getMessage();
    }
    $normalizedCapture = $this->normalize_rest_response($captureResponse);
    $this->responsedata['FINALIZE_ORDER_RESPONSE'] = $normalizedCapture;
    $resultingId = $this->extract_rest_token($normalizedCapture, $normalizedCreate);
    if (!$resultingId) {
      $resultingId = $this->extract_rest_payment_id($normalizedCapture, ($intent == 'AUTHORIZE') ? 'authorizations' : 'captures');
    }
    if (!$resultingId) {
      $resultingId = $transaction_id;
    }
    if (!$resultingId) {
      $this->notify_error('Missing PayPal REST transaction id', 'Unable to determine the PayPal identifier for REST transaction. Response: ' . json_encode($normalizedCapture), 'error');
      return 'Unable to determine PayPal transaction id';
    }
    $this->transaction_id = $resultingId;
    $this->payment_type = 'PayPal REST';
    $this->payment_status = ($intent == 'AUTHORIZE') ? 'Authorization' : 'Completed';
    $this->pendingreason = ($intent == 'AUTHORIZE') ? 'authorization' : '';
    $this->amt = $orderAmount;
    $this->payment_time = date('Y-m-d h:i:s');
    $this->transactiontype = 'rest';
    if($action == 'Sale'){
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID;
    } else {
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID;
    }
    $this->order_status = $new_order_status;
    return false;
  }
  function normalize_rest_response($response) {
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
  function extract_rest_token($response, $fallback = null) {
    $data = $this->normalize_rest_response($response);
    if (isset($data['payment_source']['token']['id'])) {
      return $data['payment_source']['token']['id'];
    }
    if (isset($data['payment_source']['card']['id'])) {
      return $data['payment_source']['card']['id'];
    }
    if (isset($data['payment_source']['card']['vault']['id'])) {
      return $data['payment_source']['card']['vault']['id'];
    }
    if (isset($data['payment_source']['card']['vault_id'])) {
      return $data['payment_source']['card']['vault_id'];
    }
    if (isset($data['supplementary_data']['related_ids']['billing_agreement_id'])) {
      return $data['supplementary_data']['related_ids']['billing_agreement_id'];
    }
    if ($fallback != null) {
      return $this->extract_rest_token($fallback);
    }
    return false;
  }
  function extract_rest_payment_id($response, $type) {
    $data = $this->normalize_rest_response($response);
    if (isset($data['purchase_units']) && is_array($data['purchase_units'])) {
      foreach ($data['purchase_units'] as $purchaseUnit) {
        if (isset($purchaseUnit['payments'][$type]) && is_array($purchaseUnit['payments'][$type])) {
          foreach ($purchaseUnit['payments'][$type] as $payment) {
            if (isset($payment['id'])) {
              return $payment['id'];
            }
          }
        }
      }
    }
    if (isset($data['id'])) {
      return $data['id'];
    }
    return false;
  }
  function get_card_details_by_transaction_id($transaction_id) {
    global $db;
    $sql = 'SELECT * FROM ' . TABLE_SAVED_CREDIT_CARDS . ' WHERE paypal_transaction_id LIKE \'' . $transaction_id . '\'';
    $result = $db->Execute($sql);
    return $result->fields;
  }
  protected function ensurePayPalVaultManagerLoaded() {
    if (!class_exists('PayPalRestful\\Common\\VaultManager')) {
      $autoload = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
      if (file_exists($autoload)) {
        require_once ($autoload);
      }
    }
    return class_exists('PayPalRestful\\Common\\VaultManager');
  }
  protected function determineCardCustomerId(array $cardDetails) {
    if (isset($cardDetails['customers_id'])) {
      return (int) $cardDetails['customers_id'];
    }
    if (isset($cardDetails['customer_id'])) {
      return (int) $cardDetails['customer_id'];
    }
    if (isset($cardDetails['customersID'])) {
      return (int) $cardDetails['customersID'];
    }
    if (isset($_SESSION['customer_id'])) {
      return (int) $_SESSION['customer_id'];
    }
    return 0;
  }
  protected function extractVaultIdFromCard(array $cardDetails) {
    $candidates = array();
    if (isset($cardDetails['paypal_vault_card']['vault_id'])) {
      $candidates[] = $cardDetails['paypal_vault_card']['vault_id'];
    }
    if (isset($cardDetails['vault_id'])) {
      $candidates[] = $cardDetails['vault_id'];
    }
    if (isset($cardDetails['paypal_transaction_id'])) {
      $candidates[] = $cardDetails['paypal_transaction_id'];
    }
    if (isset($cardDetails['paypal_stored_credential_id'])) {
      $candidates[] = $cardDetails['paypal_stored_credential_id'];
    }
    foreach ($candidates as $candidate) {
      $candidate = trim((string) $candidate);
      if (strlen($candidate) > 0) {
        return substr($candidate, 0, 64);
      }
    }
    return '';
  }
  public function getVaultCardForSavedCard(array $cardDetails, $activeOnly = false) {
    $customers_id = $this->determineCardCustomerId($cardDetails);
    $vaultId = $this->extractVaultIdFromCard($cardDetails);
    if ($customers_id <= 0 || $vaultId === '') {
      return array();
    }
    if (!$this->ensurePayPalVaultManagerLoaded()) {
      return array();
    }
    $cards = PayPalRestful\Common\VaultManager::getCustomerVaultedCards($customers_id, (bool) $activeOnly);
    foreach ($cards as $card) {
      if (isset($card['vault_id']) && $card['vault_id'] === $vaultId) {
        return $card;
      }
    }
    return array();
  }
  protected function normalizeVaultExpiryValue($expiry) {
    $expiry = trim((string) $expiry);
    if ($expiry === '') {
      return '';
    }
    if (preg_match('/^\d{4}-\d{2}$/', $expiry)) {
      return $expiry;
    }
    if (preg_match('/^\d{6}$/', $expiry)) {
      $year = substr($expiry, 0, 4);
      $month = substr($expiry, - 2);
      return $year . '-' . $month;
    }
    $digits = preg_replace('/[^0-9]/', '', $expiry);
    if (strlen($digits) === 4) {
      $month = substr($digits, 0, 2);
      $year = substr($digits, - 2);
      return '20' . $year . '-' . $month;
    }
    if (strlen($digits) === 6) {
      $month = substr($digits, 0, 2);
      $year = substr($digits, 2, 4);
      return $year . '-' . $month;
    }
    return '';
  }
  protected function buildBillingAddressFromCard(array $cardDetails, array $vaultCard = array()) {
    if (isset($vaultCard['billing_address']) && is_array($vaultCard['billing_address']) && count($vaultCard['billing_address']) > 0) {
      return $vaultCard['billing_address'];
    }
    global $db;
    $customers_id = $this->determineCardCustomerId($cardDetails);
    $addressId = isset($cardDetails['address_id']) ? (int) $cardDetails['address_id'] : 0;
    if ($addressId <= 0 && $customers_id > 0) {
      $customerLookup = $db->Execute("SELECT customers_default_address_id FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int) $customers_id . " LIMIT 1");
      if (!$customerLookup->EOF) {
        $addressId = (int) $customerLookup->fields['customers_default_address_id'];
      }
    }
    if ($addressId <= 0) {
      return array();
    }
    $address = $db->Execute("SELECT * FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int) $addressId . " LIMIT 1");
    if ($address->EOF) {
      return array();
    }
    $countryCode = '';
    if (isset($address->fields['entry_country_id']) && (int) $address->fields['entry_country_id'] > 0) {
      $country = zen_get_countries($address->fields['entry_country_id']);
      if (isset($country['countries_iso_code_2'])) {
        $countryCode = $country['countries_iso_code_2'];
      }
    }
    $billing = array(
      'address_line_1' => trim($address->fields['entry_street_address']),
      'postal_code' => trim($address->fields['entry_postcode']),
      'country_code' => $countryCode
    );
    if (isset($address->fields['entry_suburb']) && strlen($address->fields['entry_suburb']) > 0) {
      $billing['address_line_2'] = trim($address->fields['entry_suburb']);
    }
    if (isset($address->fields['entry_city']) && strlen($address->fields['entry_city']) > 0) {
      $billing['admin_area_2'] = trim($address->fields['entry_city']);
    }
    $state = '';
    if (isset($address->fields['entry_state']) && strlen($address->fields['entry_state']) > 0) {
      $state = trim($address->fields['entry_state']);
    }
    if ($state === '' && isset($address->fields['entry_zone_id']) && (int) $address->fields['entry_zone_id'] > 0) {
      $state = zen_get_zone_code($address->fields['entry_country_id'], $address->fields['entry_zone_id'], '');
    }
    if ($state !== '') {
      $billing['admin_area_1'] = $state;
    }
    return array_filter($billing, function ($value) {
      return $value !== '' && $value !== null;
    });
  }
  public function buildVaultPaymentSource(array $cardDetails, array $options = array()) {
    $vaultId = $this->extractVaultIdFromCard($cardDetails);
    if ($vaultId === '') {
      return array();
    }
    $vaultCard = $this->getVaultCardForSavedCard($cardDetails, false);
    $cardPayload = array('vault_id' => $vaultId);
    $expiry = '';
    if (isset($vaultCard['expiry'])) {
      $expiry = $vaultCard['expiry'];
    }
    if ($expiry === '' && isset($cardDetails['expiry'])) {
      $expiry = $cardDetails['expiry'];
    }
    $expiry = $this->normalizeVaultExpiryValue($expiry);
    if ($expiry !== '') {
      $cardPayload['expiry'] = $expiry;
    }
    $lastDigits = '';
    if (isset($vaultCard['last_digits'])) {
      $lastDigits = $vaultCard['last_digits'];
    }
    if ($lastDigits === '' && isset($cardDetails['last_digits'])) {
      $lastDigits = substr($cardDetails['last_digits'], - 4);
    }
    $lastDigits = preg_replace('/[^0-9]/', '', $lastDigits);
    if ($lastDigits !== '') {
      $cardPayload['last_digits'] = substr($lastDigits, - 4);
    }
    $brand = '';
    if (isset($vaultCard['brand'])) {
      $brand = $vaultCard['brand'];
    }
    if ($brand === '' && isset($cardDetails['type'])) {
      $brand = strtoupper($cardDetails['type']);
    }
    if ($brand !== '') {
      $cardPayload['brand'] = strtoupper(trim($brand));
    }
    $name = '';
    if (isset($vaultCard['cardholder_name'])) {
      $name = $vaultCard['cardholder_name'];
    }
    if ($name === '' && isset($cardDetails['name_on_card'])) {
      $name = $cardDetails['name_on_card'];
    }
    if ($name !== '') {
      $cardPayload['name'] = trim($name);
    }
    $billing = $this->buildBillingAddressFromCard($cardDetails, $vaultCard);
    if (!empty($billing)) {
      $cardPayload['billing_address'] = $billing;
    }
    $storedDefaults = array('payment_initiator' => 'MERCHANT', 'payment_type' => 'UNSCHEDULED', 'usage' => 'SUBSEQUENT');
    if (isset($options['stored_credential']) && is_array($options['stored_credential'])) {
      $storedDefaults = array_merge($storedDefaults, $options['stored_credential']);
    }
    $cardPayload['attributes']['stored_credential'] = $storedDefaults;
    return $cardPayload;
  }
/*
*  customer_id is optional, pass it in for an assitional security check that the customer owns the card.
*/
  function get_card_details($saved_card_id, $customer_id = null) {
    global $db;
//validate that card belongs to the logged in user
    $sql = "SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE saved_credit_card_id = " . $saved_card_id;
    if ($customer_id) {
      $sql .= ' AND customers_id = ' . (int) $customer_id;
    }
    $result = $db->Execute($sql);
    return $result->fields;
  }
  function after_process() {
    global $db, $insert_id;
    if ($this->action == 'Sale') {
      $comments = "Payment with saved credit card successful. Transaction id " . $this->transaction_id;
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID;
    }
    elseif ($this->action == 'Authorization') {
      $comments = "Card has been authorized for the order total.  No payment has been taken yet. Transaction id " . $this->transaction_id;
      $new_order_status = MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID;
    }
    if(empty($insert_id)) $insert_id = $_SESSION['order_number_created'];
    $sql_data_array = array(array('fieldName' => 'orders_id', 'value' => $insert_id, 'type' => 'integer'), array('fieldName' => 'orders_status_id', 'value' => $new_order_status, 'type' => 'integer'), array('fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring'), array('fieldName' => 'customer_notified', 'value' => 0, 'type' => 'integer'), array('fieldName' => 'comments', 'value' => $comments, 'type' => 'string'));
    $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
// store the PayPal order meta data -- used for later matching and back-end processing activities
    $paypal_token = isset($_SESSION['paypal_ec_token']) ? $_SESSION['paypal_ec_token'] : '';
    $paypal_payer_info = (isset($_SESSION['paypal_ec_payer_info']) && is_array($_SESSION['paypal_ec_payer_info'])) ? $_SESSION['paypal_ec_payer_info'] : array();
    $paypal_payer_id = isset($_SESSION['paypal_ec_payer_id']) ? $_SESSION['paypal_ec_payer_id'] : '';
    $ppref = isset($this->responsedata['PPREF']) ? $this->responsedata['PPREF'] : '';
    $invoice = '';
    if (strlen($paypal_token) > 0 && strlen($ppref) > 0) {
      $invoice = urldecode($paypal_token . $ppref);
    }
    elseif (isset($this->responsedata['ORDER_ID'])) {
      $invoice = $this->responsedata['ORDER_ID'];
    }
    $module_mode = '';
    if ($this->transactiontype == 'rest' || MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE == 'paypalr') {
      $module_mode = 'PayPal REST';
    }
    elseif (defined('MODULE_PAYMENT_PAYFLOW_MODULE_MODE')) {
      $module_mode = MODULE_PAYMENT_PAYFLOW_MODULE_MODE;
    }
    $receiver_email = '';
    if ($module_mode == 'PayPal REST') {
      if (defined('MODULE_PAYMENT_PAYPALR_MERCHANT_EMAIL')) {
        $receiver_email = MODULE_PAYMENT_PAYPALR_MERCHANT_EMAIL;
      }
    }
    elseif (defined('MODULE_PAYMENT_PAYFLOW_MODULE_MODE') && substr(MODULE_PAYMENT_PAYFLOW_MODULE_MODE, 0, 7) == 'Payflow') {
      $receiver_email = defined('MODULE_PAYMENT_PAYFLOW_PFVENDOR') ? MODULE_PAYMENT_PAYFLOW_PFVENDOR : '';
    }
    elseif (defined('MODULE_PAYMENT_PAYFLOW_APIUSERNAME')) {
      $receiver_email = str_replace('_api1', '', MODULE_PAYMENT_PAYFLOW_APIUSERNAME);
    }
    $mc_currency = isset($this->responsedata['CURRENCYCODE']) ? $this->responsedata['CURRENCYCODE'] : (isset($this->responsedata['CURRENCY_CODE']) ? $this->responsedata['CURRENCY_CODE'] : '');
    $settle_amount = isset($this->responsedata['SETTLEAMT']) ? (float) urldecode($this->responsedata['SETTLEAMT']) : (float) $this->amt;
    $settle_currency = $mc_currency;
    $exchange_rate = (isset($this->responsedata['EXCHANGERATE']) && urldecode($this->responsedata['EXCHANGERATE']) > 0) ? urldecode($this->responsedata['EXCHANGERATE']) : 1.0;
    $mc_fee = isset($this->feeamt) ? (float) urldecode($this->feeamt) : 0.0;
    $paypal_order = array(
        'order_id' => $insert_id,
        'txn_type' => $this->transactiontype,
        'module_name' => $this->code,
        'module_mode' => $module_mode,
        'reason_code' => $this->reasoncode,
        'payment_type' => $this->payment_type,
        'payment_status' => $this->payment_status,
        'pending_reason' => $this->pendingreason,
        'invoice' => $invoice,
        'first_name' => isset($paypal_payer_info['payer_firstname']) ? $paypal_payer_info['payer_firstname'] : '',
        'last_name' => isset($paypal_payer_info['payer_lastname']) ? $paypal_payer_info['payer_lastname'] : '',
        'payer_business_name' => isset($paypal_payer_info['payer_business']) ? $paypal_payer_info['payer_business'] : '',
        'address_name' => isset($paypal_payer_info['ship_name']) ? $paypal_payer_info['ship_name'] : '',
        'address_street' => isset($paypal_payer_info['ship_street_1']) ? $paypal_payer_info['ship_street_1'] : '',
        'address_city' => isset($paypal_payer_info['ship_city']) ? $paypal_payer_info['ship_city'] : '',
        'address_state' => isset($paypal_payer_info['ship_state']) ? $paypal_payer_info['ship_state'] : '',
        'address_zip' => isset($paypal_payer_info['ship_postal_code']) ? $paypal_payer_info['ship_postal_code'] : '',
        'address_country' => isset($paypal_payer_info['ship_country']) ? $paypal_payer_info['ship_country'] : '',
        'address_status' => isset($paypal_payer_info['ship_address_status']) ? $paypal_payer_info['ship_address_status'] : '',
        'payer_email' => isset($paypal_payer_info['payer_email']) ? $paypal_payer_info['payer_email'] : '',
        'payer_id' => $paypal_payer_id,
        'payer_status' => isset($paypal_payer_info['payer_status']) ? $paypal_payer_info['payer_status'] : '',
        'payment_date' => trim(preg_replace('/[^0-9-:]/', ' ', $this->payment_time)),
        'business' => '',
        'receiver_email' => $receiver_email,
        'receiver_id' => '',
        'txn_id' => $this->transaction_id,
        'parent_txn_id' => '',
        'num_cart_items' => (float) $this->numitems,
        'mc_gross' => (float) $this->amt,
        'mc_fee' => $mc_fee,
        'mc_currency' => $mc_currency,
        'settle_amount' => $settle_amount,
        'settle_currency' => $settle_currency,
        'exchange_rate' => $exchange_rate,
        'notify_version' => '0',
        'verify_sign' => '',
        'date_added' => 'now()',
        'memo' => '{Record generated by payment module}');
    zen_db_perform(TABLE_PAYPAL, $paypal_order);

    if(!empty($this->saved_card_id) && !empty($this->transaction_id)) {
      $data_sql_array = array(
        'paypal_transaction_id' => $this->transaction_id
      );

      zen_db_perform(TABLE_SAVED_CREDIT_CARDS, $data_sql_array, 'update', 'saved_credit_card_id = ' . $this->saved_card_id);
    }

// Unregister the paypal session variables, making it necessary to start again for another purchase
    unset($_SESSION['paypal_ec_temp']);
    unset($_SESSION['paypal_ec_token']);
    unset($_SESSION['paypal_ec_payer_id']);
    unset($_SESSION['paypal_ec_payer_info']);
    unset($_SESSION['paypal_ec_final']);
    unset($_SESSION['paypal_ec_markflow']);
    unset($_SESSION['order_number_created']);
  }
  function admin_notification($zf_order_id) {
    global $db;

//$response = $this->_TransactionSearch('2006-12-01T00:00:00Z', $zf_order_id);
    $sql = "SELECT * from " . TABLE_PAYPAL . " WHERE order_id = :orderID
            AND parent_txn_id = '' AND order_id > 0
            ORDER BY paypal_ipn_id DESC LIMIT 1";
    $sql = $db->bindVars($sql, ':orderID', $zf_order_id, 'integer');
    $ipn = $db->Execute($sql);

    $details = $this->get_card_details_by_transaction_id($ipn->fields['txn_id']);

    $module = $this->code;
    $output = '';
    if ($details['api_type'] == 'payflow') {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/payflow.php');
        $payflow = new Payflow();
        $response = $payflow->_GetTransactionDetails($zf_order_id);
    } elseif ($details['api_type'] == 'paypalr') {
        $response = array();
        $helper = $this->get_paypalr_admin_helper('TransactionDetails');
        if ($helper) {
            if (method_exists($helper, 'execute')) {
                $response = $helper->execute($ipn->fields['txn_id']);
            } elseif (method_exists($helper, 'run')) {
                $response = $helper->run($ipn->fields['txn_id']);
            }
        }
        if (!$response) {
            $helper = $this->get_paypalr_admin_helper('GetTransactionDetails');
            if ($helper) {
                if (method_exists($helper, 'execute')) {
                    $response = $helper->execute($ipn->fields['txn_id']);
                } elseif (method_exists($helper, 'run')) {
                    $response = $helper->run($ipn->fields['txn_id']);
                }
            }
        }
    } else {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypaldp.php');
        $paypaldp = new paypaldp();
        $response = $paypaldp->_GetTransactionDetails($zf_order_id);
    }
    if ($ipn->EOF) {
      $ipn = new stdClass;
      $ipn->fields = array();
    }
    if (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypalwpp_admin_notification.php')) require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypalwpp_admin_notification.php');
    return $output;
  }
  function _doRefund($oID, $amount = 'Full', $note = '') {
    global $db;
    $sql = "SELECT * from " . TABLE_PAYPAL . " WHERE order_id = :orderID
            AND parent_txn_id = '' AND order_id > 0
            ORDER BY paypal_ipn_id DESC LIMIT 1";
    $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
    $ipn = $db->Execute($sql);

    $details = $this->get_card_details_by_transaction_id($ipn->fields['txn_id']);
    if ($details['api_type'] == 'payflow') {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/payflow.php');
        $payflow = new Payflow();
        return $payflow->_doRefund($oID, $amount, $note);
    } elseif ($details['api_type'] == 'paypalr') {
        $helper = $this->get_paypalr_admin_helper('DoRefund');
        if ($helper) {
            if (method_exists($helper, 'execute')) {
                return $helper->execute($ipn->fields['txn_id'], $amount, $note);
            } elseif (method_exists($helper, 'run')) {
                return $helper->run($ipn->fields['txn_id'], $amount, $note);
            }
        }
        $this->notify_error('PayPal REST refund unavailable', 'Unable to locate the PayPal REST refund helper. Please ensure the PayPal REST admin classes are installed.', 'warning');
        return false;
    } else {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypaldp.php');
        $paypaldp = new paypaldp();
        return $paypaldp->_doRefund($oID, $amount, $note);
    }
  }
  function _doVoid($oID, $note = '') {
    global $db;
    $sql = "SELECT * from " . TABLE_PAYPAL . " WHERE order_id = :orderID
            AND parent_txn_id = '' AND order_id > 0
            ORDER BY paypal_ipn_id DESC LIMIT 1";
    $sql = $db->bindVars($sql, ':orderID', $oID, 'integer');
    $ipn = $db->Execute($sql);

    $details = $this->get_card_details_by_transaction_id($ipn->fields['txn_id']);
    if ($details['api_type'] == 'payflow') {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/payflow.php');
        $payflow = new Payflow();
        return $payflow->_doVoid($oID, $note);
    } elseif ($details['api_type'] == 'paypalr') {
        $helper = $this->get_paypalr_admin_helper('DoVoid');
        if ($helper) {
            if (method_exists($helper, 'execute')) {
                return $helper->execute($ipn->fields['txn_id'], $note);
            } elseif (method_exists($helper, 'run')) {
                return $helper->run($ipn->fields['txn_id'], $note);
            }
        }
        $this->notify_error('PayPal REST void unavailable', 'Unable to locate the PayPal REST void helper. Please ensure the PayPal REST admin classes are installed.', 'warning');
        return false;
    } else {
        require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypaldp.php');
        $paypaldp = new paypaldp();
        return $paypaldp->_doVoid($oID, $note);
    }
  }
  function get_error() {
    return false;
  }
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYPALSAVEDCARD_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  function make_address($street, $street2, $city, $zip, $state, $zone_id, $country) {
    $DataArray = array();
    $DataArray['street'] = $street;
    $DataArray['street2'] = $street2;
    $DataArray['city'] = $city;
    $DataArray['zip'] = $zip;
    $DataArray['state'] = $state;
    $DataArray['zone_id'] = $zone_id;
    $DataArray['countrycode'] = $country;
    return $DataArray;
  }
/*
* Function to find the credit card in the POST and save it to the database to be re-used
* This function should be called after the user has confirmed their order
*/
  function save_card_from_post() {
    global $cc_validation, $payment_modules;
    if (!class_exists('cc_validation')) {
      include_once (DIR_WS_CLASSES . 'cc_validation.php');
    }
    if (!is_object($cc_validation)) {
      $cc_validation = new cc_validation();
    }
    if (isset($_POST['wpp_cc_number']) && strlen($_POST['wpp_cc_number']) > 5) {
      $validation_response = $cc_validation->validate($_POST['wpp_cc_number'], $_POST['wpp_cc_expdate_month'], $_POST['wpp_cc_expdate_year'], $_POST['wpp_cc_issuedate_month'], $_POST['wpp_cc_issuedate_year']);
      if ($validation_response) {
        $_SESSION['saved_card_id'] = $this->add_saved_card($cc_validation->cc_number, $_POST['wpp_cc_checkcode'], $cc_validation->cc_expiry_month . substr($cc_validation->cc_expiry_year, - 2), $_POST['wpp_payer_firstname'] . ' ' . $_POST['wpp_payer_lastname'], $cc_validation->cc_type, $_POST['wpp_cc_save']);
      }
    }
    if (isset($_POST['paypalwpp_cc_number']) && strlen($_POST['paypalwpp_cc_number']) > 5) {
//in some cases, the name prefix of inputs us "paypalwpp" instead of "wpp"
// . $cc_validation->cc_expiry_year, $_POST['paypalwpp_payer_firstname    '] . ' ' . $_POST['paypalwpp_payer_lastname'], $cc_validation->cc_type
      $validation_response = $cc_validation->validate($_POST['paypalwpp_cc_number'], $_POST['paypalwpp_cc_expires_month'], $_POST['paypalwpp_cc_expires_year'], $_POST['paypalwpp_cc_issuedate_month'], $_POST['paypalwpp_cc_issuedate_year']);
//print $cc_validation->cc_number . ' , ' . $_POST['paypalwpp_cc_checkcode'] . ' , '  .  $cc_validation->cc_expiry_month . ' . ' .$cc_validation->cc_expiry_year . ' , ' . $_POST['paypalwpp_cc_firstname'] . ' ' . $_POST['paypalwpp_cc_lastname'] . ' , ' . $cc_validation->cc_type;
      if ($validation_response) {
        $_SESSION['saved_card_id'] = $this->add_saved_card($cc_validation->cc_number, $_POST['paypalwpp_cc_checkcode'], $cc_validation->cc_expiry_month . substr($cc_validation->cc_expiry_year, - 2), $_POST['paypalwpp_cc_firstname'] . ' ' . $_POST['paypalwpp_cc_lastname'], $cc_validation->cc_type, $_POST['paypalwpp_cc_save']);
      }
    }
  }
/*
Function to save a credit card on Paypal's server and keep an transaction ID so that it can be used later for a reference transaction.
If a saved_credit_card_id is passed in, this card will be saved on top of another card (edit)
*/
  function add_saved_card($cardnumber, $cvv, $expirydate, $fullname, $paymenttype, $visible = 0, $primary = 0, $saved_credit_card_id = 0, $address_info = null) {
    global $PayPal, $db, $payment_modules;
    list($firstname, $lastname) = explode(' ', $fullname);

    // Check if the first time to save a credit card or if current primary card is already expired
    $check = "SELECT saved_credit_card_id FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE customers_id = " . (int)$_SESSION['customer_id'];
    $check .= " AND is_deleted = '0' AND is_primary = 1 AND LAST_DAY(STR_TO_DATE(expiry, '%m%y')) > CURDATE()";
    $existed_cards = $db->Execute($check);
    
    // Set primary if this is first saved card
    if (!$existed_cards->RecordCount() > 0) {
      $remove_old_primary_sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . " SET is_primary = 0 WHERE customers_id = " . (int)$_SESSION['customer_id'];
      $db->execute($remove_old_primary_sql);

      $primary = 1;
    }

// build the address info using the selected billing address if address_info is null
    if ($address_info == null) {
      $sql = 'SELECT * FROM ' . TABLE_ADDRESS_BOOK . ' WHERE address_book_id = ' . (int) $_SESSION['customer_default_address_id'] . ' AND customers_id = ' . (int) $_SESSION['customer_id'];
      $result = $db->Execute($sql);
      $saved_address = $result->fields;
      $country_info = zen_get_countries($saved_address['entry_country_id']);
      $country_code = $country_info['countries_iso_code_2'];
      $address_info = $this->make_address($saved_address['entry_street_address'], $saved_address['entry_suburb'], $saved_address['entry_city'], $saved_address['entry_postcode'], $saved_address['entry_state'], $saved_address['entry_zone_id'], $country_code);
    }
//check if card already exists in user's account
    $check = $db->execute('select * FROM ' . TABLE_SAVED_CREDIT_CARDS . ' WHERE customers_id = ' . $_SESSION['customer_id'] . ' AND is_deleted = \'0\' AND last_digits = ' . (int) substr($cardnumber, - 4) . ';');
    if ($check->RecordCount() > 0 && $address_info == null) { //if address info is  null, we cannot update the existing card
      return 0;
//todo: if the card exists, but is marked as deleted, then enable it and set the saved_credit_card_id so that this can proceed like an update.
    }
    if ($check->RecordCount() > 0 && $address_info != null) {
      $saved_credit_card_id = $check->fields['saved_credit_card_id'];
//this card already exists, do an update of the address
    }

    if(is_object($payment_modules)) {
      $transaction_id = $GLOBALS[$payment_modules->selected_module]->transaction_id;
    } else {
      if (MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE == 'payflow') {
        $transaction_id = $this->save_card_with_payflow($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info);
      } elseif (MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE == 'paypalr') {
        $transaction_id = $this->save_card_with_paypalr($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info);
      } else {
        $transaction_id = $this->save_card_with_paypalwpp($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info);
      }
    }

    if (!$transaction_id) {
      return 0; //failure
    }

    $insert_data = array(array('fieldName' => 'customers_id', 'value' => (int) $_SESSION['customer_id'], 'type' => 'integer'), array('fieldName' => 'type', 'value' => $paymenttype, 'type' => 'string'), array('fieldName' => 'last_digits', 'value' => substr($cardnumber, - 4), 'type' => 'string'), array('fieldName' => 'name_on_card', 'value' => $fullname, 'type' => 'string'), array('fieldName' => 'api_type', 'value' => MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE, 'type' => 'string'), array('fieldName' => 'is_primary', 'value' => $primary, 'type' => 'integer'), array('fieldName' => 'expiry', 'value' => $expirydate, 'type' => 'string'), array('fieldName' => 'is_visible', 'value' => $visible, 'type' => 'integer'), array('fieldName' => 'paypal_transaction_id', 'value' => $transaction_id, 'type' => 'string'),);
    $action = $saved_credit_card_id > 0 ? 'update' : 'insert';
    if ($action == 'update') {
      $parameters = 'saved_credit_card_id = ' . $saved_credit_card_id . ' AND customers_id = ' . $_SESSION['customer_id'];
    }
    else {
      $parameters = '';
    }
    $db->perform(TABLE_SAVED_CREDIT_CARDS, $insert_data, $action, $parameters);
    $insert_ID = $db->insert_ID();

    if(isset($_SESSION['billto']) && is_numeric($_SESSION['billto'])) {
      $update_address_sql = $sql = 'UPDATE ' . TABLE_SAVED_CREDIT_CARDS . ' SET address_id = ' . (int)$_SESSION['billto'] . ' WHERE saved_credit_card_id = ';

      if($action == 'update') {
        $update_address_sql .= $saved_credit_card_id;
      } else {
        $update_address_sql .= $insert_ID;
      }
      $db->execute($update_address_sql);
    }

    if ($action == 'update') {
      return $saved_credit_card_id;
    }
    else {
      return $insert_ID;
    }
  }
  function save_card_with_paypalwpp($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info) {
    $data = array('DPFields' => array('PAYMENTACTION' => 'Authorization', 'ACCT' => $cardnumber, 'CVV2' => $cvv, 'EXPDATE' => $expirydate, 'FIRSTNAME' => $firstname, 'LASTNAME' => $lastname, 'CREDITCARDTYPE' => $paymenttype, 'AMT' => 0, 'NOTIFYURL' => HTTPS_SERVER . '/saved_credit_card_ipn.php' //we don't want this to post to the main IPN and make a $0 order
    ));
    if ($address_info != null) {
      $data['BillingAddress'] = $address_info;
    }
    $response = $this->PayPalwpp->DoDirectPayment($data);
    if ($response['ACK'] == 'Failure') {
      $this->notify_error('Adding a saved card with paypalwpp', 'While attempting to add a saved card with paypalwpp, Paypal returned this error: ' . $response['L_ERRORCODE0'] . ' ' . $response['L_LONGMESSAGE0'] . "\n\n customer " . $firstname . ' ' . $lastname);
      return 0;
    }
    else {
      return $response['TRANSACTIONID'];
    }
  }
  function save_card_with_paypalr($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info) {
    if (!$this->PayPalRestful) {
      if (!$this->initiate_paypalr()) {
        return 0;
      }
    }
    $sanitizedNumber = preg_replace('/[^0-9]/', '', $cardnumber);
    $expMonth = substr($expirydate, 0, 2);
    $expYear = substr($expirydate, - 2);
    $formattedExpiry = '20' . $expYear . '-' . $expMonth;
    $cardData = array('number' => $sanitizedNumber, 'expiry' => $formattedExpiry, 'security_code' => $cvv, 'name' => trim($firstname . ' ' . $lastname));
    if (strlen($paymenttype) > 0) {
      $cardData['brand'] = strtoupper($paymenttype);
    }
    if (is_array($address_info)) {
      $billing = array('address_line_1' => $address_info['street'], 'postal_code' => $address_info['zip'], 'country_code' => $address_info['countrycode']);
      if (isset($address_info['street2']) && strlen($address_info['street2']) > 0) {
        $billing['address_line_2'] = $address_info['street2'];
      }
      if (isset($address_info['city'])) {
        $billing['admin_area_2'] = $address_info['city'];
      }
      if (isset($address_info['state'])) {
        $billing['admin_area_1'] = $address_info['state'];
      }
      $cardData['billing_address'] = $billing;
    }
    $cardData['stored_credential'] = array('type' => 'MERCHANT_INITIATED_FIRST', 'usage' => 'FIRST');
    $tokenRequest = array('payment_source' => array('card' => $cardData));
    try {
      if (method_exists($this->PayPalRestful, 'createPaymentToken')) {
        $tokenResponse = $this->PayPalRestful->createPaymentToken($tokenRequest);
      }
      elseif (method_exists($this->PayPalRestful, 'createToken')) {
        $tokenResponse = $this->PayPalRestful->createToken($tokenRequest);
      }
      else {
        $tokenResponse = null;
      }
    }
    catch (Exception $e) {
      $this->notify_error('Adding a saved card with PayPal REST', 'While attempting to create a payment token with PayPal REST, an exception occurred: ' . $e->getMessage(), 'error');
      return 0;
    }
    if ($tokenResponse) {
      $tokenId = $this->extract_rest_token($tokenResponse);
      if ($tokenId) {
        return $tokenId;
      }
    }
    $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';
    $orderRequest = array('intent' => 'AUTHORIZE', 'purchase_units' => array(array('amount' => array('currency_code' => $currency, 'value' => '1.00'))), 'payment_source' => array('card' => $cardData));
    try {
      $createResponse = $this->PayPalRestful->createOrder($orderRequest);
      $createNormalized = $this->normalize_rest_response($createResponse);
      $tokenId = $this->extract_rest_token($createNormalized);
      if ($tokenId) {
        return $tokenId;
      }
      $orderId = isset($createNormalized['id']) ? $createNormalized['id'] : '';
      if (strlen($orderId) == 0) {
        $this->notify_error('Adding a saved card with PayPal REST', 'PayPal REST did not return an order id when saving a card. Response: ' . json_encode($createNormalized), 'error');
        return 0;
      }
      if (method_exists($this->PayPalRestful, 'authorizeOrder')) {
        $finalizeResponse = $this->PayPalRestful->authorizeOrder($orderId);
      }
      elseif (method_exists($this->PayPalRestful, 'authorize')) {
        $finalizeResponse = $this->PayPalRestful->authorize($orderId);
      }
      elseif (method_exists($this->PayPalRestful, 'captureOrder')) {
        $finalizeResponse = $this->PayPalRestful->captureOrder($orderId);
      }
      elseif (method_exists($this->PayPalRestful, 'capture')) {
        $finalizeResponse = $this->PayPalRestful->capture($orderId);
      }
      else {
        $finalizeResponse = array();
      }
      $tokenId = $this->extract_rest_token($finalizeResponse, $createNormalized);
      if ($tokenId) {
        return $tokenId;
      }
      $paymentId = $this->extract_rest_payment_id($finalizeResponse, 'authorizations');
      if (!$paymentId) {
        $paymentId = $this->extract_rest_payment_id($finalizeResponse, 'captures');
      }
      if ($paymentId) {
        return $paymentId;
      }
      $this->notify_error('Adding a saved card with PayPal REST', 'PayPal REST did not return a token when saving a card. Response: ' . json_encode($finalizeResponse), 'error');
    }
    catch (Exception $e) {
      $this->notify_error('Adding a saved card with PayPal REST', 'While attempting to add a saved card with PayPal REST, an exception occurred: ' . $e->getMessage(), 'error');
    }
    return 0;
  }
/*
* Performs a zero-dollar authorization to get a token that can be used for future transactions
*/
  function save_card_with_payflow($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info) {
    global $messageStack;

    $responses = array();

    // Payflow supports zero-dollar authorizations. Start with a $0.00 validation attempt
    $response = $this->perform_payflow_authorization_attempt($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info, 0.00);
    if ($this->payflow_attempt_succeeded($response)) {
      $this->handle_successful_payflow_authorization($response, 0.00);
      return $response['PNREF'];
    }

    $responses[] = array('amount' => 0.00, 'response' => $response);

    if (!$this->should_retry_payflow_authorization($response)) {
      $this->notify_error('Adding a saved card with Payflow', 'While attempting to add a saved card with Payflow, Paypal returned this error: ' . $response['RESULT'] . ' ' . $response['RESPMSG'] . "\n\n customer " . $firstname . ' ' . $lastname);
      return 0;
    }

    // Retry with small authorization amounts if PayPal does not accept the zero-dollar validation
    $fallbackAmounts = array(0.50, 1.00);
    foreach ($fallbackAmounts as $amount) {
      $response = $this->perform_payflow_authorization_attempt($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info, $amount);
      if ($this->payflow_attempt_succeeded($response)) {
        $this->handle_successful_payflow_authorization($response, $amount);
        return $response['PNREF'];
      }

      $responses[] = array('amount' => $amount, 'response' => $response);
    }

    $attemptDetails = array();
    foreach ($responses as $attempt) {
      $resultCode = isset($attempt['response']['RESULT']) ? $attempt['response']['RESULT'] : 'N/A';
      $resultMessage = isset($attempt['response']['RESPMSG']) ? $attempt['response']['RESPMSG'] : 'No response message';
      $attemptDetails[] = '$' . number_format($attempt['amount'], 2) . ' (' . $resultCode . ' ' . $resultMessage . ')';
    }

    if (is_object($messageStack)) {
      $customerMessage = defined('MODULE_PAYMENT_PAYPALSAVEDCARD_CONTACT_BANK_MESSAGE') ? MODULE_PAYMENT_PAYPALSAVEDCARD_CONTACT_BANK_MESSAGE : 'We could not verify your card with PayPal. Please contact your bank to approve the verification request and try again. The store administrator has been notified.';
      $messageStack->add_session('saved_credit_cards', $customerMessage, 'error');
    }

    $this->notify_error(
      'Adding a saved card with Payflow',
      'While attempting to add a saved card with Payflow, Paypal returned an error after the following authorization attempts: ' . implode(', ', $attemptDetails) .
      "\n\nThe customer has been advised to contact their bank so the verification can be approved.\n\n customer " . $firstname . ' ' . $lastname
    );

    return 0;
  }

  function perform_payflow_authorization_attempt($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $address_info, $amount) {
    $isZeroAmount = ((float) $amount <= 0);

    // Use the legacy Payflow "L" upload transaction for zero-dollar validations. When we have to
    // authorize a small amount, switch to an authorization request so the customer is not charged.
    $optionsAll = array(
      'TRXTYPE' => $isZeroAmount ? 'L' : 'A',
      'AMT' => number_format($amount, 2, '.', '')
    );

    $optionsNVP = is_array($address_info) ? $address_info : array();
    $optionsNVP['PAYMENTACTION'] = 'Authorization';

    return $this->Payflow->DoDirectPayment($cardnumber, $cvv, $expirydate, $firstname, $lastname, $paymenttype, $optionsAll, $optionsNVP);
  }

  function handle_successful_payflow_authorization($response, $amount) {
    if (!$this->payflow_response_indicates_sale($response)) {
      return;
    }

    $pnref = isset($response['PNREF']) ? $response['PNREF'] : '';
    if (strlen($pnref) === 0) {
      return;
    }

    $voidResponse = $this->void_payflow_reference_transaction($pnref, $amount);
    if ($voidResponse === true) {
      if ((float) $amount > 0) {
        $this->notify_error(
          'Voided Payflow sale during saved card validation',
          'Payflow converted a saved-card validation for $' . number_format($amount, 2) . " into a sale. The sale (PNREF " . $pnref . ") was voided automatically.",
          'warning'
        );
      }
      return;
    }

    if (is_array($voidResponse)) {
      $resultCode = isset($voidResponse['RESULT']) ? $voidResponse['RESULT'] : 'N/A';
      $resultMessage = isset($voidResponse['RESPMSG']) ? $voidResponse['RESPMSG'] : 'No response message';
    }
    else {
      $resultCode = 'N/A';
      $resultMessage = 'Unknown response while attempting to void the Payflow sale.';
    }

    $this->notify_error(
      'Unable to void Payflow sale during saved card validation',
      'Payflow converted a saved-card validation for $' . number_format($amount, 2) . " into a sale (PNREF " . $pnref . "), but the system could not void the transaction automatically. Payflow responded with: " . $resultCode . ' ' . $resultMessage . '\n\nPlease review and void the transaction manually if necessary.',
      'error'
    );
  }

  function payflow_response_indicates_sale($response) {
    if (!is_array($response)) {
      return false;
    }

    $typeFields = array('TYPE', 'TRXTYPE', 'TRANSACTIONTYPE');
    foreach ($typeFields as $field) {
      if (isset($response[$field])) {
        $value = strtoupper(trim($response[$field]));
        if ($value === 'S' || $value === 'SALE') {
          return true;
        }
        if ($value === 'A' || $value === 'AUTH' || $value === 'AUTHORIZATION') {
          return false;
        }
      }
    }

    if (isset($response['AMT']) && (float) $response['AMT'] > 0) {
      return true;
    }

    if (isset($response['CAPTURECOMPLETE'])) {
      $captureComplete = strtoupper(trim($response['CAPTURECOMPLETE']));
      if ($captureComplete === 'Y') {
        return true;
      }
    }

    return false;
  }

  function void_payflow_reference_transaction($pnref, $amount = null) {
    if (!$this->Payflow) {
      $this->initiate_payflow();
    }

    $voidRequest = array(
      'TRXTYPE' => 'V',
      'ORIGID' => $pnref
    );

    if ($amount !== null) {
      $voidRequest['AMT'] = number_format($amount, 2, '.', '');
    }

    $response = $this->Payflow->DoDirectPayment(null, null, null, null, null, null, $voidRequest, null);
    if (is_array($response) && isset($response['RESULT']) && (int) $response['RESULT'] === 0) {
      return true;
    }

    return $response;
  }

  function should_retry_payflow_authorization($response) {
    if (!is_array($response)) {
      return false;
    }

    if (!isset($response['RESULT']) || !isset($response['RESPMSG'])) {
      return false;
    }

    $resultCode = (int) $response['RESULT'];
    $message = strtolower(trim($response['RESPMSG']));

    return ($resultCode === 4 && strpos($message, 'invalid amount') !== false);
  }

  function payflow_attempt_succeeded($response) {
    if (!is_array($response)) {
      return false;
    }

    if (!isset($response['RESULT']) || !isset($response['PNREF'])) {
      return false;
    }

    return (int) $response['RESULT'] <= 0;
  }
/*
* Function to delete a card.  This isn't a true delete, we simply mark it as deleted but allow the admin to still use it.
*
*/
  function delete_card($card_id, $customer_id) {
    global $db;
    $sql = "UPDATE " . TABLE_SAVED_CREDIT_CARDS . "
        SET is_deleted = '1'
        WHERE  saved_credit_card_id = :delete
        AND    customers_id = :customersID
        LIMIT 1";
    $sql = $db->bindVars($sql, ':customersID', $customer_id, 'integer');
    $sql = $db->bindVars($sql, ':delete', $card_id, 'integer');
    $db->Execute($sql);
//      if(is_object($this->paypalSavedCardRecurring)) {
//        return $this->paypalSavedCardRecurring->card_was_deleted($card_id); //to re-assign subscriptions using that card.
//      }
    return '';
  }
  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Paypal Saved Cards', 'MODULE_PAYMENT_PAYPALSAVEDCARD_STATUS', 'False', 'Do you want to allow customers to pay with credit cards saved in their account? paypalwpp payment module is required', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
values ('Production or dev?', 'MODULE_PAYMENT_PAYPALSAVEDCARD_MODE', 'Production', 'Should the saved credit cards be run in production or dev mode?  In production mode the payment will be processed accourding to the settings in paypalwpp.  In dev mode the cards will not actually be processed and the module will return success.', '6', '1', 'zen_cfg_select_option(array(\'Production\', \'Dev\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
values ('Paypal API', 'MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE', 'paypalwpp', 'Should the transactions be processed with PayPal WPP, Payflow or PayPal REST?  The corresponding payment module will need to be installed and configured.  For PayPal REST, be sure to configure the MODULE_PAYMENT_PAYPALR_* settings.', '6', '1', 'zen_cfg_select_option(array(\'paypalwpp\', \'payflow\', \'paypalr\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYPALSAVEDCARD_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Authorization Status', 'MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID', '0', 'Status to use for orders where the payment was authorized, but no funds have been taken.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Notification Email', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL', '', 'Email address to send errors and warnings to', '6', '0', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Recurring Failure Recipients', 'MODULE_PAYMENT_PAYPALSAVEDCARD_FAILURE_EMAILS', '', 'Comma separated list of additional recipients for recurring failure reports', '6', '0', now())");
  }
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }
  function keys() {
    return array('MODULE_PAYMENT_PAYPALSAVEDCARD_STATUS', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ZONE', 'MODULE_PAYMENT_PAYPALSAVEDCARD_SORT_ORDER', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID', 'MODULE_PAYMENT_PAYPALSAVEDCARD_MODE', 'MODULE_PAYMENT_PAYPALSAVEDCARD_API_TYPE', 'MODULE_PAYMENT_PAYPALSAVEDCARD_AUTHORIZATION_STATUS_ID', 'MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL', 'MODULE_PAYMENT_PAYPALSAVEDCARD_FAILURE_EMAILS');
  }
  function get_paypalr_config() {
    $environment = '';
    if (defined('MODULE_PAYMENT_PAYPALR_ENVIRONMENT')) {
      $environment = MODULE_PAYMENT_PAYPALR_ENVIRONMENT;
    }
    elseif (defined('MODULE_PAYMENT_PAYPALR_MODE')) {
      $environment = MODULE_PAYMENT_PAYPALR_MODE;
    }
    return array('client_id' => defined('MODULE_PAYMENT_PAYPALR_CLIENT_ID') ? MODULE_PAYMENT_PAYPALR_CLIENT_ID : '', 'client_secret' => defined('MODULE_PAYMENT_PAYPALR_CLIENT_SECRET') ? MODULE_PAYMENT_PAYPALR_CLIENT_SECRET : '', 'environment' => $environment);
  }
  function get_paypalr_admin_helper($classSuffix) {
    if (!$this->PayPalRestful) {
      if (!$this->initiate_paypalr()) {
        return false;
      }
    }
    $class = 'PayPalRestful\\Admin\\' . $classSuffix;
    if (!class_exists($class)) {
      return false;
    }
    try {
      $reflection = new ReflectionClass($class);
      $constructor = $reflection->getConstructor();
      $args = array();
      if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
        $args[] = $this->PayPalRestful;
        if ($constructor->getNumberOfRequiredParameters() > 1) {
          $args[] = $this->get_paypalr_config();
        }
      }
      $helper = $reflection->newInstanceArgs($args);
      if (method_exists($helper, 'setApi')) {
        $helper->setApi($this->PayPalRestful);
      }
      if (method_exists($helper, 'setConfig')) {
        $helper->setConfig($this->get_paypalr_config());
      }
      return $helper;
    }
    catch (Exception $e) {
      $this->notify_error('PayPal REST admin helper error', 'Unable to initialize helper ' . $class . '. ' . $e->getMessage(), 'warning');
    }
    return false;
  }
  function notify_error($subject, $message, $type = 'error') {
    $to = MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL; //todo: use store admin here
    if ($type == 'error') {
      $message = "The Saved Credit Cards module encountered an error.  Please contact Numinix Support if you unsure of how to resolve this issue \n\n" . $message;
    }
    zen_mail($to, $to, 'Saved Credit Cards (' . $type . ') - ' . $subject, nl2br($message), STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'savedcard_' . $type);
  }
}