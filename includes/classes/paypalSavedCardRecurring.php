<?php
require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'order.php');
require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'order_total.php');
require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'shipping.php');
require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'payment.php');
require_once (DIR_FS_CATALOG . DIR_WS_CLASSES . 'shopping_cart.php');
class paypalSavedCardRecurring {
var $PayPal, $PayPalRestful, $paypalsavedcard, $paymentModuleCode;
function __construct($paypalsavedcard = null) {
$this->PayPal = null;
$this->PayPalRestful = null;
$this->paymentModuleCode = 'paypalsavedcard';
if ($paypalsavedcard == NULL) {
$legacyModulePath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalsavedcard.php';
if (file_exists($legacyModulePath)) {
require_once ($legacyModulePath);
$this->paypalsavedcard = new paypalsavedcard($this);
}
else {
$restCardModule = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_creditcard.php';
$this->paymentModuleCode = file_exists($restCardModule) ? 'paypalr_creditcard' : 'paypalr';
$this->paypalsavedcard = null;
}
}
else {
$this->paypalsavedcard = $paypalsavedcard;
if (!is_object($this->paypalsavedcard)) {
$this->paymentModuleCode = 'paypalr_creditcard';
}
}
}
function get_paypal_legacy_client() {
if ($this->PayPal instanceof PayPal) {
return $this->PayPal;
                }
               $PayPalConfig = array('Sandbox' => (MODULE_PAYMENT_PAYPALWPP_SERVER == 'sandbox' ? true : false), 'APIUsername' => MODULE_PAYMENT_PAYPALWPP_APIUSERNAME, 'APIPassword' => MODULE_PAYMENT_PAYPALWPP_APIPASSWORD, 'APISignature' => MODULE_PAYMENT_PAYPALWPP_APISIGNATURE);
               if (!class_exists('PayPal')) {
                       require_once (DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/class.paypal_wpp_recurring.php');
               }
               $this->PayPal = new PayPal($PayPalConfig);
               return $this->PayPal;
       }
       function get_paypal_rest_client() {
               if ($this->PayPalRestful) {
                       return $this->PayPalRestful;
               }
if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'initiate_paypalr')) {
$client = $this->paypalsavedcard->initiate_paypalr();
if ($client) {
$this->PayPalRestful = isset($this->paypalsavedcard->PayPalRestful) ? $this->paypalsavedcard->PayPalRestful : $client;
return $this->PayPalRestful;
}
}
               $autoload = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
               if (!class_exists('PayPalRestful\\Api\\PayPalRestfulApi') && file_exists($autoload)) {
                       require_once ($autoload);
               }
               if (class_exists('PayPalRestful\\Api\\PayPalRestfulApi')) {
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
                               return $this->PayPalRestful;
                       }
                       catch (Exception $e) {
                               $this->notify_error('Unable to initialize PayPal REST API', 'The PayPal REST API client failed to initialize. Message: ' . $e->getMessage(), 'warning');
                       }
               }
               return false;
       }
       function try_paypal_rest_method($client, $method, $arguments = array()) {
               $methods = array($method);
               $methods[] = lcfirst($method);
               $methods[] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
               foreach (array_unique($methods) as $candidate) {
                       if (method_exists($client, $candidate)) {
                               return call_user_func_array(array($client, $candidate), is_array($arguments) ? $arguments : array($arguments));
                       }
               }
               return null;
       }
       function call_paypal_rest_method($client, $method, $arguments = array()) {
               $result = $this->try_paypal_rest_method($client, $method, $arguments);
               if ($result === null) {
                       throw new \BadMethodCallException('Method ' . $method . ' is not available on the PayPal REST client');
               }
               return $result;
       }
       function finalize_paypal_rest_order($client, $intent, $order_id) {
               $methods = ($intent == 'AUTHORIZE') ? array('authorizeOrder', 'authorize') : array('captureOrder', 'capture');
               $exception = null;
               foreach ($methods as $method) {
                       try {
                               return $this->call_paypal_rest_method($client, $method, array($order_id));
                       }
                       catch (\BadMethodCallException $e) {
                               $exception = $e;
                       }
               }
               if ($exception) {
                       throw $exception;
               }
               throw new Exception('Unable to finalize PayPal REST order.');
       }
       function normalize_rest_response($response) {
               if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'normalize_rest_response')) {
                       return $this->paypalsavedcard->normalize_rest_response($response);
               }
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
               if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'extract_rest_token')) {
                       return $this->paypalsavedcard->extract_rest_token($response, $fallback);
               }
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
               if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'extract_rest_payment_id')) {
                       return $this->paypalsavedcard->extract_rest_payment_id($response, $type);
               }
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
      protected function paypal_recurring_has_column($column)
      {
              static $columns = array();
              $column = trim((string) $column);
              if ($column === '' || !defined('TABLE_PAYPAL_RECURRING')) {
                      return false;
              }
              if (array_key_exists($column, $columns)) {
                      return $columns[$column];
              }
              global $db;
              $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_PAYPAL_RECURRING . " LIKE '" . zen_db_input($column) . "'");
              $columns[$column] = ($result && $result->RecordCount() > 0);
              return $columns[$column];
      }
      protected function saved_cards_recurring_has_column($column)
      {
              static $columns = array();
              $column = trim((string) $column);
              if ($column === '' || !defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
                      return false;
              }
              if (array_key_exists($column, $columns)) {
                      return $columns[$column];
              }
              global $db;
              $result = $db->Execute("SHOW COLUMNS FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " LIKE '" . zen_db_input($column) . "'");
              $columns[$column] = ($result && $result->RecordCount() > 0);
              return $columns[$column];
      }
protected function ensure_vault_manager_loaded() {
if (!class_exists('PayPalRestful\\Common\\VaultManager')) {
$autoload = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';
if (file_exists($autoload)) {
require_once ($autoload);
}
}
return class_exists('PayPalRestful\\Common\\VaultManager');
}
public function get_saved_card_details($saved_card_id, $customer_id = null) {
if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'get_card_details')) {
return $this->paypalsavedcard->get_card_details($saved_card_id, $customer_id);
}
global $db;
$sql = "SELECT * FROM " . TABLE_SAVED_CREDIT_CARDS . " WHERE saved_credit_card_id = " . (int) $saved_card_id;
if ($customer_id) {
$sql .= ' AND customers_id = ' . (int) $customer_id;
}
$result = $db->Execute($sql);
return $result ? $result->fields : array();
}
protected function extract_vault_id_from_card(array $cardDetails) {
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
protected function normalize_vault_expiry_value($expiry) {
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
protected function build_billing_address_from_card(array $cardDetails, array $vaultCard = array()) {
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
protected function build_vault_payment_source(array $cardDetails, array $options = array()) {
if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'buildVaultPaymentSource')) {
return $this->paypalsavedcard->buildVaultPaymentSource($cardDetails, $options);
}
$vaultId = $this->extract_vault_id_from_card($cardDetails);
if ($vaultId === '') {
return array();
}
$vaultCard = $this->find_vault_card_for_payment($cardDetails);
$cardPayload = array('vault_id' => $vaultId);
$expiry = '';
if (isset($vaultCard['expiry'])) {
$expiry = $vaultCard['expiry'];
}
if ($expiry === '' && isset($cardDetails['expiry'])) {
$expiry = $cardDetails['expiry'];
}
$expiry = $this->normalize_vault_expiry_value($expiry);
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
$billing = $this->build_billing_address_from_card($cardDetails, $vaultCard);
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
       protected function find_vault_card_for_payment(array $payment_details) {
               if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'getVaultCardForSavedCard')) {
                       $vaultCard = $this->paypalsavedcard->getVaultCardForSavedCard($payment_details, false);
                       if (is_array($vaultCard) && count($vaultCard) > 0) {
                               return $vaultCard;
                       }
               }
$vaultId = $this->extract_vault_id_from_card($payment_details);
               if ($vaultId === '') {
                       return array();
               }
               $customers_id = 0;
               if (isset($payment_details['customers_id'])) {
                       $customers_id = (int) $payment_details['customers_id'];
               }
               elseif (isset($payment_details['customer_id'])) {
                       $customers_id = (int) $payment_details['customer_id'];
               }
               if ($customers_id <= 0) {
                       return array();
               }
               if (!$this->ensure_vault_manager_loaded()) {
                       return array();
               }
               $cards = PayPalRestful\Common\VaultManager::getCustomerVaultedCards($customers_id, false);
               foreach ($cards as $card) {
                       if (isset($card['vault_id']) && $card['vault_id'] === $vaultId) {
                               return $card;
                       }
               }
               return array();
       }
       function get_stored_credential_id($payment_details) {
               if (isset($payment_details['paypal_vault_card']['vault_id']) && strlen($payment_details['paypal_vault_card']['vault_id']) > 0) {
                       return $payment_details['paypal_vault_card']['vault_id'];
               }
               if (isset($payment_details['paypal_stored_credential_id']) && strlen($payment_details['paypal_stored_credential_id']) > 0) {
                       return $payment_details['paypal_stored_credential_id'];
               }
               if (isset($payment_details['paypal_transaction_id']) && strlen($payment_details['paypal_transaction_id']) > 0) {
                       return $payment_details['paypal_transaction_id'];
               }
               return '';
       }
       function get_payment_currency($payment_details) {
               global $order;
               if (is_object($order) && isset($order->info['currency']) && strlen($order->info['currency']) > 0) {
                       return $order->info['currency'];
               }
               if (isset($payment_details['currencycode']) && strlen($payment_details['currencycode']) > 0) {
                       return $payment_details['currencycode'];
               }
               return defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'USD';
       }
       function process_rest_payment($payment_details, $total_to_bill) {
               $client = $this->get_paypal_rest_client();
               if (!$client) {
                       return array('success' => false, 'error' => 'PayPal REST client unavailable');
               }
               $credential_id = $this->get_stored_credential_id($payment_details);
               if (!(strlen($credential_id) > 0)) {
                       return array('success' => false, 'error' => 'Missing stored credential identifier');
               }
               $intent = 'CAPTURE';
               $currency = $this->get_payment_currency($payment_details);
               $amount = number_format((float) $total_to_bill, 2, '.', '');
               $request = array('intent' => $intent, 'purchase_units' => array(array('amount' => array('currency_code' => $currency, 'value' => $amount))));
$cardPayload = $this->build_vault_payment_source($payment_details, array('stored_credential' => array('payment_type' => 'RECURRING')));
               if (!empty($cardPayload) && isset($cardPayload['vault_id'])) {
                       $request['payment_source'] = array('card' => $cardPayload);
                       $credential_id = $cardPayload['vault_id'];
               }
               elseif (strlen($credential_id) > 0) {
                       $request['payment_source'] = array('token' => array('id' => $credential_id, 'type' => 'BILLING_AGREEMENT'));
               }
               if (!isset($request['payment_source'])) {
                       $this->notify_error('Missing PayPal REST payment source', 'No payment source was available for saved card recurring payment #' . $paypal_saved_card_recurring_id . '. Details: ' . json_encode($payment_details), 'error');
                       return array('success' => false, 'error' => 'Missing PayPal REST payment source');
               }
               try {
                       $create_response = $this->call_paypal_rest_method($client, 'createOrder', array($request));
               }
               catch (Exception $e) {
                       $this->notify_error('PayPal REST order creation failed', 'PayPal REST threw an exception while creating an order. Details: ' . $e->getMessage(), 'error');
                       return array('success' => false, 'error' => $e->getMessage());
               }
               $normalized_create = $this->normalize_rest_response($create_response);
               $order_id = isset($normalized_create['id']) ? $normalized_create['id'] : '';
               if (strlen($order_id) == 0) {
                       $this->notify_error('PayPal REST order creation failed', 'PayPal REST order creation did not return an order id. Response: ' . json_encode($normalized_create), 'error');
                       return array('success' => false, 'error' => 'Unable to create PayPal order');
               }
               try {
                       $capture_response = $this->finalize_paypal_rest_order($client, $intent, $order_id);
               }
               catch (Exception $e) {
                       $this->notify_error('PayPal REST capture failed', 'PayPal REST threw an exception while finalizing order ' . $order_id . '. Details: ' . $e->getMessage(), 'error');
                       return array('success' => false, 'error' => $e->getMessage());
               }
               $normalized_capture = $this->normalize_rest_response($capture_response);
               $transaction_id = $this->extract_rest_payment_id($normalized_capture, 'captures');
               if (!$transaction_id) {
                       $transaction_id = $this->extract_rest_payment_id($normalized_capture, 'authorizations');
               }
               if (!$transaction_id) {
                       $transaction_id = $this->extract_rest_token($normalized_capture, $normalized_create);
               }
               if (!$transaction_id) {
                       $transaction_id = $credential_id;
               }
               if (is_object($this->paypalsavedcard)) {
                       $this->paypalsavedcard->transaction_id = $transaction_id;
                       $this->paypalsavedcard->payment_status = 'Completed';
                       $this->paypalsavedcard->payment_type = 'PayPal REST';
                       $this->paypalsavedcard->payment_time = date('Y-m-d H:i:s');
                       $this->paypalsavedcard->transactiontype = 'rest';
                       $this->paypalsavedcard->pendingreason = '';
                       $this->paypalsavedcard->amt = $amount;
                       $this->paypalsavedcard->responsedata = array('ORDER_ID' => $order_id, 'INTENT' => $intent, 'CURRENCYCODE' => $currency, 'AMT' => $amount, 'CREATE_ORDER_RESPONSE' => $normalized_create, 'FINALIZE_ORDER_RESPONSE' => $normalized_capture);
               }
               return array('success' => true, 'transaction_id' => $transaction_id);
       }
       function is_paypalr_primary() {
               if (defined('MODULE_PAYMENT_PAYPALR_STATUS') && MODULE_PAYMENT_PAYPALR_STATUS == 'True') {
                       return true;
               }
               return false;
       }
       function cancel_paypalr_subscription($subscription) {
               $client = $this->get_paypal_rest_client();
               if (!$client) {
                       return false;
               }
               $profile_id = isset($subscription['profile_id']) ? $subscription['profile_id'] : '';
               if (!(strlen($profile_id) > 0)) {
                       return false;
               }
               $reason = 'Cancelled: duplicate subscription';
               $methods = array(
                       array('method' => 'cancelSubscription', 'arguments' => array($profile_id, array('reason' => $reason))),
                       array('method' => 'cancel', 'arguments' => array($profile_id, array('reason' => $reason))),
                       array('method' => 'deactivateSubscription', 'arguments' => array($profile_id, array('reason' => $reason)))
               );
               foreach ($methods as $candidate) {
                       try {
                               $response = $this->try_paypal_rest_method($client, $candidate['method'], $candidate['arguments']);
                               if ($response !== null) {
                                       return true;
                               }
                       }
                       catch (Exception $e) {
                               $this->notify_error('PayPal REST duplicate cancellation failed', 'Attempted to cancel REST subscription ' . $profile_id . ' but encountered: ' . $e->getMessage(), 'warning');
                               return false;
                       }
               }
               return false;
       }
      public function cancel_subscription_paypalwpp_direct($customer_id, $products_id, $domain = '')
      {
              global $db;
              $customer_id = (int) $customer_id;
              $products_id = (int) $products_id;
              $domain = trim((string) $domain);
              if ($customer_id <= 0 || $products_id <= 0) {
                      return 0;
              }
              if (!defined('TABLE_PAYPAL_RECURRING')) {
                      $definitions = DIR_FS_CATALOG . 'management/includes/extra_datafiles/paypal_subscriptions_database_tables.php';
                      if (file_exists($definitions)) {
                              require_once ($definitions);
                      }
              }
              if (!defined('TABLE_PAYPAL_RECURRING')) {
                      return 0;
              }
              $conditions = array(
                      'customers_id = ' . (int) $customer_id,
                      'products_id = ' . (int) $products_id,
                      "(status IS NULL OR status NOT IN ('Cancelled', 'Suspended', 'Expired'))",
              );
              if ($domain !== '' && $this->paypal_recurring_has_column('domain')) {
                      $conditions[] = "domain = '" . zen_db_input($domain) . "'";
              }
              $subscription = $db->Execute(
                      'SELECT *'
                      . '  FROM ' . TABLE_PAYPAL_RECURRING
                      . ' WHERE ' . implode(' AND ', $conditions)
                      . ' ORDER BY subscription_id DESC'
                      . ' LIMIT 1;'
              );
              if (!$subscription || $subscription->EOF) {
                      return 0;
              }
              $profile_id = isset($subscription->fields['profile_id']) ? trim((string) $subscription->fields['profile_id']) : '';
              if ($profile_id === '') {
                      return 0;
              }
              $functions = DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/functions.php';
              if (file_exists($functions)) {
                      require_once ($functions);
              }
              if (!function_exists('zen_paypal_subscription_cancel_immediately')) {
                      return 0;
              }
              $options = array(
                      'note' => 'Automatically cancelled during saved card subscription migration.',
                      'source' => 'saved_cards_recurring',
                      'subscription' => $subscription->fields,
                      'saved_card_recurring' => $this,
              );
              $cancel_result = zen_paypal_subscription_cancel_immediately($customer_id, $profile_id, $options);
              if (empty($cancel_result['success'])) {
                      $message = isset($cancel_result['message']) ? $cancel_result['message'] : 'Unknown error';
                      $this->notify_error('Unable to cancel PayPal WPP subscription', 'Cancellation attempt for PayPal profile ' . $profile_id . ' (customer #' . $customer_id . ') failed. Reason: ' . $message, 'warning');
                      return 0;
              }
              $prepaid_days = 0;
              $next_payment_fields = array('next_payment_date', 'next_payment_due', 'next_payment_due_date', 'next_billing_date');
              $next_payment_value = '';
              foreach ($next_payment_fields as $candidate) {
                      if (isset($subscription->fields[$candidate]) && $subscription->fields[$candidate] !== '') {
                              $next_payment_value = $subscription->fields[$candidate];
                              break;
                      }
              }
              if ($next_payment_value !== '') {
                      $timestamp = strtotime($next_payment_value);
                      if ($timestamp !== false) {
                              $midnight_today = strtotime(date('Y-m-d'));
                              if ($timestamp > $midnight_today) {
                                      $prepaid_days = (int) ceil(($timestamp - $midnight_today) / 86400);
                              }
                      }
              }
              return $prepaid_days;
      }
/*
* Schedules a payment to be taken.  Schedules are created when an order for a recurring product is placed or after a successful payment has been taken.
* original_orders_products_id is optional, but without it the subscription will not be automatically renewed when the payment is taken.
*/
        function schedule_payment($amount, $date, $saved_credit_card_id, $original_orders_products_id = '', $comments = '', array $metadata = array()) {
                global $db;
                if (!$this->validate_saved_card($saved_credit_card_id, $original_orders_products_id)) {
                        $this->notify_error('Invalid saved card id', 'saved card id: ' . $saved_credit_card_id . ' orders_products_id ' . $original_orders_products_id);
                        return 0;
                }
                $amount = preg_replace("/[^0-9\.]/", "", $amount); //remove any illegal chars from the amount so it stores properly.
                $metadata = $this->prepare_schedule_payment_metadata($metadata, $original_orders_products_id, $amount);
                $sql_data_array = array(
                        array('fieldName' => 'date', 'value' => $date, 'type' => 'string'),
                        array('fieldName' => 'amount', 'value' => $amount, 'type' => 'string'),
                        array('fieldName' => 'status', 'value' => 'scheduled', 'type' => 'string'),
                        array('fieldName' => 'original_orders_products_id', 'value' => $original_orders_products_id, 'type' => 'integer'),
                        array('fieldName' => 'saved_credit_card_id', 'value' => $saved_credit_card_id, 'type' => 'integer'),
                        array('fieldName' => 'comments', 'value' => $comments, 'type' => 'string'),
                        array('fieldName' => 'products_id', 'value' => $metadata['products_id'], 'type' => 'integer'),
                        array('fieldName' => 'products_name', 'value' => $metadata['products_name'], 'type' => 'string'),
                        array('fieldName' => 'products_model', 'value' => $metadata['products_model'], 'type' => 'string'),
                        array('fieldName' => 'currency_code', 'value' => $metadata['currency_code'], 'type' => 'string'),
                        array('fieldName' => 'billing_period', 'value' => $metadata['billing_period'], 'type' => 'string'),
                        array('fieldName' => 'billing_frequency', 'value' => $metadata['billing_frequency'], 'type' => 'integer'),
                        array('fieldName' => 'total_billing_cycles', 'value' => $metadata['total_billing_cycles'], 'type' => 'integer'),
                        array('fieldName' => 'domain', 'value' => $metadata['domain'], 'type' => 'string'),
                        array('fieldName' => 'subscription_attributes_json', 'value' => $metadata['subscription_attributes_json'], 'type' => 'string'),
                );
                $db->perform(TABLE_SAVED_CREDIT_CARDS_RECURRING, $sql_data_array);
                $paypal_saved_card_recurring_id = $db->insert_ID();
                return $paypal_saved_card_recurring_id;
        }

        protected function prepare_schedule_payment_metadata(array $metadata, $original_orders_products_id, $amount)
        {
                $normalized = $this->normalize_schedule_payment_metadata($metadata);
                $original_orders_products_id = (int) $original_orders_products_id;

                if ($this->schedule_metadata_missing($normalized)) {
                        list($fallbackValues, $fallbackAttributes) = $this->extract_order_item_metadata($original_orders_products_id);
                        foreach ($fallbackValues as $key => $value) {
                                if (!isset($normalized[$key]) || $normalized[$key] === '' || $normalized[$key] === null) {
                                        $normalized[$key] = $value;
                                }
                        }
                        if (!is_array($normalized['subscription_attributes']) || count($normalized['subscription_attributes']) === 0) {
                                $normalized['subscription_attributes'] = $fallbackAttributes;
                        } elseif (is_array($fallbackAttributes) && count($fallbackAttributes) > 0) {
                                $normalized['subscription_attributes'] = array_merge($fallbackAttributes, $normalized['subscription_attributes']);
                        }
                }

                if ((!isset($normalized['domain']) || $normalized['domain'] === '') && $original_orders_products_id > 0 && function_exists('nmx_check_domain')) {
                        $domain = nmx_check_domain($original_orders_products_id);
                        if ($domain) {
                                $normalized['domain'] = $domain;
                        }
                }

                if (!isset($normalized['currency_code']) || $normalized['currency_code'] === '') {
                        if (defined('DEFAULT_CURRENCY')) {
                                $normalized['currency_code'] = DEFAULT_CURRENCY;
                        } elseif (isset($_SESSION['currency']) && $_SESSION['currency'] !== '') {
                                $normalized['currency_code'] = $_SESSION['currency'];
                        } else {
                                $normalized['currency_code'] = 'USD';
                        }
                }

                if (!is_array($normalized['subscription_attributes'])) {
                        $normalized['subscription_attributes'] = array();
                }

                $normalized['subscription_attributes'] = $this->merge_subscription_attribute_context($normalized, $normalized['subscription_attributes']);
                if (!isset($normalized['subscription_attributes']['amount']) || !is_numeric($normalized['subscription_attributes']['amount'])) {
                        $normalized['subscription_attributes']['amount'] = $amount;
                }

                if (isset($normalized['billing_frequency']) && $normalized['billing_frequency'] !== '' && $normalized['billing_frequency'] !== null) {
                        if (is_numeric($normalized['billing_frequency'])) {
                                $normalized['billing_frequency'] = (int) $normalized['billing_frequency'];
                        } else {
                                $normalized['billing_frequency'] = null;
                        }
                }
                if (isset($normalized['total_billing_cycles']) && $normalized['total_billing_cycles'] !== '' && $normalized['total_billing_cycles'] !== null) {
                        if (is_numeric($normalized['total_billing_cycles'])) {
                                $normalized['total_billing_cycles'] = (int) $normalized['total_billing_cycles'];
                        } else {
                                $normalized['total_billing_cycles'] = null;
                        }
                }

                $normalized['subscription_attributes_json'] = $this->encode_schedule_attributes($normalized['subscription_attributes']);

                $normalized['products_id'] = isset($normalized['products_id']) && $normalized['products_id'] !== '' ? (int) $normalized['products_id'] : null;

                return $normalized;
        }

        protected function normalize_schedule_payment_metadata(array $metadata)
        {
                $normalized = array(
                        'products_id' => null,
                        'products_name' => null,
                        'products_model' => null,
                        'currency_code' => null,
                        'billing_period' => null,
                        'billing_frequency' => null,
                        'total_billing_cycles' => null,
                        'domain' => null,
                        'subscription_attributes' => array(),
                        'subscription_attributes_json' => '',
                );

                $map = array(
                        'products_id' => array('products_id', 'product_id', 'productsId'),
                        'products_name' => array('products_name', 'product_name'),
                        'products_model' => array('products_model', 'product_model'),
                        'currency_code' => array('currency_code', 'currency', 'currencycode'),
                        'billing_period' => array('billing_period', 'billingperiod'),
                        'billing_frequency' => array('billing_frequency', 'billingfrequency'),
                        'total_billing_cycles' => array('total_billing_cycles', 'totalbillingcycles'),
                        'domain' => array('domain'),
                );

                foreach ($map as $target => $sources) {
                        foreach ($sources as $source) {
                                if (isset($metadata[$source])) {
                                        $normalized[$target] = $metadata[$source];
                                        break;
                                }
                        }
                }

                if (isset($metadata['subscription_attributes']) && is_array($metadata['subscription_attributes'])) {
                        $normalized['subscription_attributes'] = $metadata['subscription_attributes'];
                } elseif (isset($metadata['attributes']) && is_array($metadata['attributes'])) {
                        $normalized['subscription_attributes'] = $metadata['attributes'];
                }

                if (isset($metadata['subscription_attributes_json']) && is_string($metadata['subscription_attributes_json']) && $metadata['subscription_attributes_json'] !== '') {
                        $decoded = json_decode($metadata['subscription_attributes_json'], true);
                        if (is_array($decoded)) {
                                $normalized['subscription_attributes'] = $decoded;
                                $normalized['subscription_attributes_json'] = $metadata['subscription_attributes_json'];
                        }
                }

                if (isset($metadata['subscription_attributes']) && is_string($metadata['subscription_attributes'])) {
                        $decodedAttributes = json_decode($metadata['subscription_attributes'], true);
                        if (is_array($decodedAttributes)) {
                                $normalized['subscription_attributes'] = $decodedAttributes;
                        }
                }

                return $normalized;
        }

        protected function schedule_metadata_missing(array $metadata)
        {
                $requiredKeys = array('products_id', 'products_name', 'products_model', 'billing_period', 'billing_frequency');
                foreach ($requiredKeys as $key) {
                        if (!isset($metadata[$key]) || $metadata[$key] === '' || $metadata[$key] === null) {
                                return true;
                        }
                }
                return false;
        }

        protected function extract_order_item_metadata($original_orders_products_id) {
                global $db;

                $values = array();
                $attributes = array();

                $original_orders_products_id = (int) $original_orders_products_id;
                if ($original_orders_products_id <= 0) {
                        return array($values, $attributes);
                }

                $query = $db->Execute('SELECT op.*, o.currency, o.date_purchased FROM ' . TABLE_ORDERS_PRODUCTS . ' op LEFT JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = op.orders_id WHERE op.orders_products_id = ' . $original_orders_products_id . ' LIMIT 1;');
                if ($query->RecordCount() > 0) {
                        if (isset($query->fields['products_id'])) {
                                $values['products_id'] = (int) $query->fields['products_id'];
                        }
                        if (isset($query->fields['products_name'])) {
                                $values['products_name'] = $query->fields['products_name'];
                        }
                        if (isset($query->fields['products_model'])) {
                                $values['products_model'] = $query->fields['products_model'];
                        }
                        if (isset($query->fields['orders_id'])) {
                                $values['orders_id'] = (int) $query->fields['orders_id'];
                                $values['original_orders_id'] = (int) $query->fields['orders_id'];
                        }
                        if (isset($query->fields['date_purchased']) && $query->fields['date_purchased'] !== '') {
                                $values['order_date_purchased'] = $query->fields['date_purchased'];
                                $values['date_purchased'] = $query->fields['date_purchased'];
                        }
                        if (!empty($query->fields['currency'])) {
                                $values['currency_code'] = $query->fields['currency'];
                        }
                }

                $attributes = $this->get_attributes($original_orders_products_id);
                if (is_array($attributes)) {
                        if (!isset($values['billing_period']) && isset($attributes['billingperiod'])) {
                                $values['billing_period'] = $attributes['billingperiod'];
                        }
                        if (!isset($values['billing_frequency']) && isset($attributes['billingfrequency'])) {
                                $values['billing_frequency'] = $attributes['billingfrequency'];
                        }
                        if (!isset($values['total_billing_cycles']) && isset($attributes['totalbillingcycles'])) {
                                $values['total_billing_cycles'] = $attributes['totalbillingcycles'];
                        }
                        if (!isset($values['domain']) && isset($attributes['domain'])) {
                                $values['domain'] = $attributes['domain'];
                        }
                }
                else {
                        $attributes = array();
                }

                return array($values, $attributes);
        }

        protected function hydrate_payment_details_from_legacy_order(array $fields) {
                $original_orders_products_id = isset($fields['original_orders_products_id']) ? (int) $fields['original_orders_products_id'] : 0;
                if ($original_orders_products_id <= 0) {
                        return $fields;
                }

                $keysToCheck = array('products_id', 'products_name', 'products_model', 'currency_code', 'billing_period', 'billing_frequency', 'total_billing_cycles', 'domain');
                $needsFallback = false;
                foreach ($keysToCheck as $key) {
                        if (!isset($fields[$key]) || $fields[$key] === '' || $fields[$key] === null) {
                                $needsFallback = true;
                                break;
                        }
                }
                if (!$needsFallback) {
                        if (!isset($fields['subscription_attributes']) || !is_array($fields['subscription_attributes']) || count($fields['subscription_attributes']) === 0) {
                                $needsFallback = true;
                        }
                }
                if (!$needsFallback) {
                        return $fields;
                }

                list($legacyValues, $legacyAttributes) = $this->extract_order_item_metadata($original_orders_products_id);
                foreach ($legacyValues as $key => $value) {
                        if (!isset($fields[$key]) || $fields[$key] === '' || $fields[$key] === null) {
                                $fields[$key] = $value;
                        }
                }

                if (!isset($fields['subscription_attributes']) || !is_array($fields['subscription_attributes']) || count($fields['subscription_attributes']) === 0) {
                        $fields['subscription_attributes'] = $legacyAttributes;
                }
                elseif (is_array($legacyAttributes)) {
                        foreach ($legacyAttributes as $key => $value) {
                                if (!isset($fields['subscription_attributes'][$key]) || $fields['subscription_attributes'][$key] === '' || $fields['subscription_attributes'][$key] === null) {
                                        $fields['subscription_attributes'][$key] = $value;
                                }
                        }
                }

                if ((!isset($fields['domain']) || $fields['domain'] === '') && isset($legacyValues['domain'])) {
                        $fields['domain'] = $legacyValues['domain'];
                }
                if ((!isset($fields['currency_code']) || $fields['currency_code'] === '') && isset($legacyValues['currency_code'])) {
                        $fields['currency_code'] = $legacyValues['currency_code'];
                }
                if ((!isset($fields['order_date_purchased']) || $fields['order_date_purchased'] === '') && isset($legacyValues['order_date_purchased'])) {
                        $fields['order_date_purchased'] = $legacyValues['order_date_purchased'];
                }
                if ((!isset($fields['date_purchased']) || $fields['date_purchased'] === '') && isset($legacyValues['date_purchased'])) {
                        $fields['date_purchased'] = $legacyValues['date_purchased'];
                }

                return $fields;
        }

	function migrate_legacy_subscription_context($recurring_id, array $payment_details) {
		$recurring_id = (int) $recurring_id;
		if ($recurring_id <= 0) {
			return $payment_details;
		}

		$originalOrdersProductsId = isset($payment_details['original_orders_products_id']) ? (int) $payment_details['original_orders_products_id'] : 0;
		if ($originalOrdersProductsId > 0) {
			return $payment_details;
		}

		$legacyOrdersProductsId = 0;
		if (isset($payment_details['orders_products_id']) && (int) $payment_details['orders_products_id'] > 0) {
			$legacyOrdersProductsId = (int) $payment_details['orders_products_id'];
		}
		if ($legacyOrdersProductsId <= 0) {
			$payment_details['original_orders_products_id'] = 0;
			return $payment_details;
		}

		$amount = isset($payment_details['amount']) ? $payment_details['amount'] : '0';

		$seedMetadata = array(
			'products_id' => isset($payment_details['products_id']) ? $payment_details['products_id'] : null,
			'products_name' => isset($payment_details['products_name']) ? $payment_details['products_name'] : null,
			'products_model' => isset($payment_details['products_model']) ? $payment_details['products_model'] : null,
			'currency_code' => isset($payment_details['currency_code']) ? $payment_details['currency_code'] : null,
			'billing_period' => isset($payment_details['billing_period']) ? $payment_details['billing_period'] : null,
			'billing_frequency' => isset($payment_details['billing_frequency']) ? $payment_details['billing_frequency'] : null,
			'total_billing_cycles' => isset($payment_details['total_billing_cycles']) ? $payment_details['total_billing_cycles'] : null,
			'domain' => isset($payment_details['domain']) ? $payment_details['domain'] : null,
			'subscription_attributes' => isset($payment_details['subscription_attributes']) && is_array($payment_details['subscription_attributes']) ? $payment_details['subscription_attributes'] : array(),
		);

		if (isset($payment_details['subscription_attributes_json']) && $payment_details['subscription_attributes_json'] !== '') {
			$seedMetadata['subscription_attributes_json'] = $payment_details['subscription_attributes_json'];
		}

		$normalizedMetadata = $this->prepare_schedule_payment_metadata($seedMetadata, $legacyOrdersProductsId, $amount);

		$this->update_payment_info($recurring_id, array(
			'original_orders_products_id' => $legacyOrdersProductsId,
			'metadata' => $normalizedMetadata,
			'comments' => '  Legacy subscription metadata migrated. ',
		));

		$payment_details['original_orders_products_id'] = $legacyOrdersProductsId;
		$payment_details['subscription_attributes'] = $normalizedMetadata['subscription_attributes'];
		$payment_details['subscription_attributes_json'] = $normalizedMetadata['subscription_attributes_json'];

		foreach (array('products_id', 'products_name', 'products_model', 'currency_code', 'billing_period', 'billing_frequency', 'total_billing_cycles', 'domain') as $key) {
			if (!isset($payment_details[$key]) || $payment_details[$key] === '' || $payment_details[$key] === null) {
				$payment_details[$key] = $normalizedMetadata[$key];
			}
		}

		return $payment_details;
	}

        protected function merge_subscription_attribute_context(array $normalized, array $subscriptionAttributes) {
                $subscriptionAttributes = is_array($subscriptionAttributes) ? $subscriptionAttributes : array();

                $context = array(
                        'billingperiod' => isset($normalized['billing_period']) ? $normalized['billing_period'] : null,
                        'billingfrequency' => isset($normalized['billing_frequency']) ? $normalized['billing_frequency'] : null,
                        'totalbillingcycles' => isset($normalized['total_billing_cycles']) ? $normalized['total_billing_cycles'] : null,
                        'domain' => isset($normalized['domain']) ? $normalized['domain'] : null,
                        'currencycode' => isset($normalized['currency_code']) ? $normalized['currency_code'] : null,
                );

                foreach ($context as $key => $value) {
                        if (($value !== null && $value !== '') && (!isset($subscriptionAttributes[$key]) || $subscriptionAttributes[$key] === '')) {
                                $subscriptionAttributes[$key] = $value;
                        }
                }

                return $subscriptionAttributes;
        }

        protected function escape_db_value($value) {
                if (function_exists('zen_db_input')) {
                        return zen_db_input($value);
                }

                return addslashes($value);
        }

        protected function build_subscription_scope_sql($context) {
                if (!is_array($context)) {
                        return '';
                }

                $orders_products_id = 0;
                if (isset($context['original_orders_products_id'])) {
                        $orders_products_id = (int) $context['original_orders_products_id'];
                }
                elseif (isset($context['orders_products_id'])) {
                        $orders_products_id = (int) $context['orders_products_id'];
                }
                if ($orders_products_id > 0) {
                        return 'orders_products_id = ' . $orders_products_id;
                }

                $conditions = array();
                if (isset($context['saved_credit_card_id']) && (int) $context['saved_credit_card_id'] > 0) {
                        $conditions[] = 'saved_credit_card_id = ' . (int) $context['saved_credit_card_id'];
                }
                if (isset($context['products_id']) && (int) $context['products_id'] > 0) {
                        $conditions[] = 'products_id = ' . (int) $context['products_id'];
                }
                if (isset($context['domain']) && $context['domain'] !== '') {
                        $conditions[] = "domain = '" . $this->escape_db_value($context['domain']) . "'";
                }

                if (count($conditions) === 0) {
                        return '';
                }

                return implode(' AND ', $conditions);
        }

        protected function get_snapshot_attributes($snapshot) {
                if (!is_array($snapshot)) {
                        return array();
                }

                $attributes = array();

                if (isset($snapshot['subscription_attributes']) && is_array($snapshot['subscription_attributes'])) {
                        $attributes = $snapshot['subscription_attributes'];
                }
                elseif (isset($snapshot['attributes']) && is_array($snapshot['attributes'])) {
                        $attributes = $snapshot['attributes'];
                }
                elseif (isset($snapshot['subscription_attributes']) && is_string($snapshot['subscription_attributes']) && $snapshot['subscription_attributes'] !== '') {
                        $decoded = json_decode($snapshot['subscription_attributes'], true);
                        if (is_array($decoded)) {
                                $attributes = $decoded;
                        }
                }

                if ((!is_array($attributes) || count($attributes) === 0) && isset($snapshot['subscription_attributes_json']) && is_string($snapshot['subscription_attributes_json']) && $snapshot['subscription_attributes_json'] !== '') {
                        $decoded = json_decode($snapshot['subscription_attributes_json'], true);
                        if (is_array($decoded)) {
                                $attributes = $decoded;
                        }
                }

                if (!is_array($attributes)) {
                        $attributes = array();
                }

                $map = array(
                        'billing_period' => 'billingperiod',
                        'billingperiod' => 'billingperiod',
                        'billing_frequency' => 'billingfrequency',
                        'billingfrequency' => 'billingfrequency',
                        'total_billing_cycles' => 'totalbillingcycles',
                        'totalbillingcycles' => 'totalbillingcycles',
                        'domain' => 'domain',
                        'currency_code' => 'currencycode',
                        'currencycode' => 'currencycode',
                        'amount' => 'amount',
                        'amt' => 'amount',
                );

                foreach ($map as $source => $target) {
                        if (isset($snapshot[$source]) && $snapshot[$source] !== '' && $snapshot[$source] !== null) {
                                if (!isset($attributes[$target]) || $attributes[$target] === '' || $attributes[$target] === null) {
                                        $attributes[$target] = $snapshot[$source];
                                }
                        }
                }

                return $attributes;
        }

        protected function encode_schedule_attributes(array $attributes) {
                if (!is_array($attributes) || count($attributes) === 0) {
                        return '';
                }

                $encoded = json_encode($attributes, JSON_UNESCAPED_SLASHES);
                if ($encoded === false) {
                        return '';
                }

                return $encoded;
        }
/*
* Function to validate that the card belongs to the customer that placed the order. This should be checked at a higher level as well,
* but this additional check will catch any oversights (SESSION issue, etc.)
*/
	function validate_saved_card($saved_credit_card_id, $original_orders_products_id) {
		global $db;
		$sql = 'SELECT \'true\' as valid FROM ' . TABLE_ORDERS_PRODUCTS . ' op
            JOIN ' . TABLE_ORDERS . ' o ON o.orders_id = op.orders_id
            JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.customers_id = o.customers_id;';
		$result = $db->Execute($sql);
		return ($result->fields['valid'] == 'true') ? true : false;
	}
/*
* Connect to Paypal to complete a transaction
*/
        function process_payment($paypal_saved_card_recurring_id, $total_to_bill) {
                $payment_details = $this->get_payment_details($paypal_saved_card_recurring_id);
                if (!$this->validate_saved_card($payment_details['saved_credit_card_id'], $payment_details['original_orders_products_id'])) {
                        $this->update_payment_status($paypal_saved_card_recurring_id, 'failed', 'Validation failed');
                        return array('success' => false, 'error' => 'Validation failed');
                }
                $api_type = isset($payment_details['api_type']) ? $payment_details['api_type'] : '';
                if (in_array($api_type, array('paypalr', 'rest'))) {
                        $result = $this->process_rest_payment($payment_details, $total_to_bill);
                        if ($result['success']) {
                                $transaction_id = $result['transaction_id'];
                                $this->update_payment_status($paypal_saved_card_recurring_id, 'complete', 'Transaction id: ' . $transaction_id);
                                return $result;
                        }
$this->update_payment_status($paypal_saved_card_recurring_id, 'failed', 'Paypal error: ' . $result['error']);
return $result;
}
if (!is_object($this->paypalsavedcard) || !method_exists($this->paypalsavedcard, 'process')) {
$this->update_payment_status($paypal_saved_card_recurring_id, 'failed', 'Paypal error: Legacy module unavailable');
return array('success' => false, 'error' => 'Legacy saved card module unavailable');
}
$error = $this->paypalsavedcard->process('Sale', $payment_details['paypal_transaction_id'], $total_to_bill);
                if (!$error) {
                        $payment_status = 'Completed';
                        $transaction_id = $this->paypalsavedcard->transaction_id;
                        $this->update_payment_status($paypal_saved_card_recurring_id, 'complete', 'Transaction id: ' . $transaction_id);
                        return array('success' => true, 'transaction_id' => $transaction_id);
                }
                $this->update_payment_status($paypal_saved_card_recurring_id, 'failed', 'Paypal error: ' . $error);
                return array('success' => false, 'error' => $error);
        }
/*
*  Checks order to see if it contains recurring products
*  Copied from paypal_wpp_recurring.php with some modifications
*/
	function find_subscription_products_in_order($products = null) {
		global $db, $zco_notifier;
		if (!is_array($products)) {
			$products = $_SESSION['cart']->get_products();
		}
		$subscriptions = array();
		$defaultStartDate = date('Y-m-d');
                $todayTimestamp = strtotime('today');
                foreach ($products as $product) {
                        $subscriptions[$product['id']]['product_info'] = $this->get_product_info($product['id']);
                        $subscriptions[$product['id']] = $this->get_subscription_attributes($product);
                        $startdateAttribute = isset($subscriptions[$product['id']]['startdate']) ? $subscriptions[$product['id']]['startdate'] : null;
                        $startdateTimestamp = (!empty($startdateAttribute)) ? strtotime($startdateAttribute) : false;
                        $subscriptions[$product['id']]['startdate'] = ($startdateTimestamp !== false && $startdateTimestamp > $todayTimestamp) ? $startdateAttribute : $defaultStartDate;
                        $subscriptions[$product['id']]['quantity'] = $product['quantity'];
			if ($subscriptions[$product['id']]['billingperiod'] && !($subscriptions[$product['id']]['billingfrequency'] > 0) && strpos($subscriptions[$product['id']]['billingfrequency'], 'Lifetime') === false) {
				$subscriptions[$product['id']]['billingfrequency'] = '1'; //default in case it was missed
			}
// check if subscription exists and then populate all other fields
			if ($subscriptions[$product['id']]['billingperiod'] && strpos($subscriptions[$product['id']]['billingfrequency'], 'Lifetime') === false) {
// this needs to be set to the base price plus price of attributes but not including one-time charges then multiple by quantity
				$subscriptions[$product['id']]['amt'] = ($product['final_price'] - $product['one_time_charges']) * $product['quantity'];
				$subscriptions[$product['id']]['currencycode'] = $_SESSION['currency'];
				$subscriptions[$product['id']]['desc'] = $product['name'];
				if (!is_null($product['tax_class_id'])) {
					$products_tax = zen_get_tax_rate($product['tax_class_id']);
					$products_total_tax = zen_calculate_tax($product['final_price'], $products_tax) * $product['quantity'];
					$subscriptions[$product['id']]['taxamt'] = $products_total_tax;
				}
			}
			else {
				if ($subscriptions[$product['id']]['product_info']['products_license']) {
					$this->notify_error('possible configuration error', 'While looking for subscription products in this order, we found a product that is licensed but does not have a billing period so it is not recognized as a subscription product.  No subscription or licence will be created.  Subscription info: ' . json_encode($subscriptions[$product['id']]), 'warning');
				}
				unset($subscriptions[$product['id']]); //this is not a subscription product
			}
		}
		return $subscriptions;
	}
/*
*  Function to get product info not specific to any order
*/
	function get_product_info($product_id) {
		global $db;
		$sql = 'SELECT * FROM ' . TABLE_PRODUCTS . ' WHERE products_id = ' . (int) $product_id;
		$result = $db->Execute($sql);
		return $result->fields;
	}

/*
*  Function to get product info with description not specific to any order
*/
        function get_product_info_with_desc($product_id) {
                global $db;
                $sql = 'SELECT * FROM ' . TABLE_PRODUCTS . ' p
        LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id = pd.products_id
        WHERE p.products_id = ' . (int) $product_id;
                $result = $db->Execute($sql);
                return $result->fields;
        }
/*
* Function to determine if any of the subscriptions in the order contain a future start date.
* Future start dates are a trigger for doing authorization only during transaction
*/
	function order_contains_future_start_date() {
		$subscriptions = $this->find_subscription_products_in_order();
		foreach ($subscriptions as $subscription) {
			if (strtotime($subscription['startdate']) > strtotime('today')) {
				return true;
			}
		}
		return false;
	}
/*
* Returns the next date that this subscription should be billed on, or false if the number of cycles in the agreement has already been reached
* If the transaction has failed, the number of days that it failed on can be subtracted, so that the correct billing frequency is maintained.
*
* The assummption is made that the cron is run daily, so the number of missed payments is the same as the number of days behind on payment the customer is.
*
*  If the period info is missing from the attributes, it should be retrieved from the product.
*/
        function next_billing_date($attributes, $subscription_context = array(), $days_to_minus = 0) {
                global $db;

                $original_orders_products_id = 0;
                $completed_cycles = null;
                $scopeSql = '';

                if (!is_array($subscription_context)) {
                        $original_orders_products_id = (int) $subscription_context;
                        $subscription_context = array();
                }
                else {
                        if (isset($subscription_context['original_orders_products_id'])) {
                                $original_orders_products_id = (int) $subscription_context['original_orders_products_id'];
                        }
                        elseif (isset($subscription_context['orders_products_id'])) {
                                $original_orders_products_id = (int) $subscription_context['orders_products_id'];
                        }
                        if (isset($subscription_context['completed_payments'])) {
                                $completed_cycles = (int) $subscription_context['completed_payments'];
                        }
                        if (isset($subscription_context['subscription_scope'])) {
                                $scopeSql = $subscription_context['subscription_scope'];
                        }
                }

                if ($scopeSql === '') {
                        $scopeSql = $this->build_subscription_scope_sql($subscription_context);
                }
                if ($scopeSql === '' && $original_orders_products_id > 0) {
                        $scopeSql = 'orders_products_id = ' . $original_orders_products_id;
                }

                if ($completed_cycles === null && $scopeSql !== '') {
                        $result = $db->Execute('SELECT COUNT(*) AS num_cycles FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE ' . $scopeSql);
                        $completed_cycles = (int) $result->fields['num_cycles'];
                }
                if ($completed_cycles === null) {
                        $completed_cycles = 0;
                }

                if (is_numeric($attributes['totalbillingcycles']) && $attributes['totalbillingcycles'] > 0 && $completed_cycles >= $attributes['totalbillingcycles']) {
                        return false;
                }

                if (!((int) $attributes['billingfrequency'] > 0) || !(strlen($attributes['billingperiod']) > 0)) {
                        $this->notify_error('Invalid period info', 'Unable to calculate next billing date.  Possibly this is an invalid manually added order. orders_products_id: ' . $original_orders_products_id . ' attributes: ' . json_encode($attributes));
                        return false;
                }

                $next_date = strtotime('+' . (int) $attributes['billingfrequency'] . " " . $attributes['billingperiod']);
                return date("Y-m-d", strtotime('-' . $days_to_minus . " Day", $next_date));
        }

        function get_domain($orders_products_id = 0, array $snapshot = array()) {
                global $db;

                if (is_array($orders_products_id) && count($snapshot) === 0) {
                        $snapshot = $orders_products_id;
                        $orders_products_id = isset($snapshot['original_orders_products_id']) ? (int) $snapshot['original_orders_products_id'] : 0;
                }

                if (isset($snapshot['domain']) && $snapshot['domain'] !== '') {
                        return $snapshot['domain'];
                }

                $attributes = $this->get_snapshot_attributes($snapshot);
                if (isset($attributes['domain']) && $attributes['domain'] !== '') {
                        return $attributes['domain'];
                }

                $orders_products_id = (int) $orders_products_id;
                if ($orders_products_id <= 0) {
                        return '';
                }

                $hasDomainColumn = $this->saved_cards_recurring_has_column('domain');
                $domainSelect = $hasDomainColumn ? 'domain, ' : '';
                $snapshotRow = $db->Execute('SELECT ' . $domainSelect . 'subscription_attributes_json FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE orders_products_id = ' . $orders_products_id . ' ORDER BY saved_credit_card_recurring_id DESC LIMIT 1;');
                if ($snapshotRow->RecordCount() > 0) {
                        if ($hasDomainColumn && isset($snapshotRow->fields['domain']) && $snapshotRow->fields['domain'] !== '') {
                                return $snapshotRow->fields['domain'];
                        }
                        $decoded = $this->get_snapshot_attributes($snapshotRow->fields);
                        if (isset($decoded['domain']) && $decoded['domain'] !== '') {
                                return $decoded['domain'];
                        }
                }

                $sql = 'SELECT products_options_values FROM ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " WHERE products_options = 'Domain' AND orders_products_id = " . $orders_products_id;
                $result = $db->Execute($sql);
                if ($result->RecordCount() > 0 && isset($result->fields['products_options_values'])) {
                        return $result->fields['products_options_values'];
                }

                return '';
        }

        function add_licence($orders_id, $products_id, $next_payment_date, $domain = '', $products_name = '', $products_model = '') {
                if (function_exists('nmx_log_license')) {
                        return nmx_log_license($orders_id);
                }

                if (is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'add_licence')) {
                        return $this->paypalsavedcard->add_licence($orders_id, $products_id, $next_payment_date, $domain, $products_name, $products_model);
                }

                return false;
        }

        function get_attributes($orders_products_id, array $snapshot = array()) {
                global $db;

                if (is_array($orders_products_id) && count($snapshot) === 0) {
                        $snapshot = $orders_products_id;
                        $orders_products_id = isset($snapshot['original_orders_products_id']) ? (int) $snapshot['original_orders_products_id'] : 0;
                }

                $attributes = $this->get_snapshot_attributes($snapshot);
                if (is_array($attributes) && count($attributes) > 0) {
                        return $attributes;
                }

                $orders_products_id = (int) $orders_products_id;
                if ($orders_products_id <= 0) {
                        return false;
                }

                $hasDomainColumn = $this->saved_cards_recurring_has_column('domain');
                $domainSelect = $hasDomainColumn ? 'domain, ' : '';
                $snapshotRow = $db->Execute('SELECT subscription_attributes_json, billing_period, billing_frequency, total_billing_cycles, ' . $domainSelect . 'currency_code FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE orders_products_id = ' . $orders_products_id . ' ORDER BY saved_credit_card_recurring_id DESC LIMIT 1;');
                if ($snapshotRow->RecordCount() > 0) {
                        $attributes = $this->get_snapshot_attributes($snapshotRow->fields);
                        if (is_array($attributes) && count($attributes) > 0) {
                                return $attributes;
                        }
                }

                $orderProduct = $db->Execute('SELECT orders_products_id FROM ' . TABLE_ORDERS_PRODUCTS . ' WHERE orders_products_id = ' . $orders_products_id . ' LIMIT 1;');
                if ($orderProduct->RecordCount() === 0) {
                        $this->log_subscription_issue('orders_products entry missing while loading attributes for orders_products_id ' . $orders_products_id);
                        return false;
                }

                $sql = 'SELECT opa.products_options_id, opa.products_options_values_id, opa.products_options, opa.products_options_values, po.products_options_type FROM ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' opa LEFT JOIN ' . TABLE_PRODUCTS_OPTIONS . ' po ON po.products_options_id = opa.products_options_id WHERE opa.orders_products_id = ' . $orders_products_id;
                $result = $db->Execute($sql);
                $product = array('attributes' => array(), 'attributes_values' => array(), 'attributes_map' => array());
                while (!$result->EOF) {
                        $optionId = (int) $result->fields['products_options_id'];
                        if ($optionId > 0) {
                                $valueId = isset($result->fields['products_options_values_id']) ? (int) $result->fields['products_options_values_id'] : 0;
                                $product['attributes'][$optionId] = $valueId;
                                $product['attributes_values'][$optionId] = isset($result->fields['products_options_values']) ? $result->fields['products_options_values'] : '';
                                $optionType = isset($result->fields['products_options_type']) ? (int) $result->fields['products_options_type'] : 0;
                                $mapKey = $optionId;
                                $mapValue = $valueId;
                                if ($optionType === 1) {
                                        $textPrefix = defined('TEXT_PREFIX') ? TEXT_PREFIX : 'TXT_';
                                        $mapKey = $textPrefix . $optionId;
                                        $mapValue = isset($result->fields['products_options_values']) ? $result->fields['products_options_values'] : '';
                                }
                                $product['attributes_map'][$mapKey] = $mapValue;
                        }
                        $result->MoveNext();
                }

                if (count($product['attributes']) === 0) {
                        return false;
                }

                $attributes = $this->get_subscription_attributes($product);
                if (!is_array($attributes) || count($attributes) === 0) {
                        return false;
                }

                return $attributes;
        }
        protected function resolve_snapshot_log_path()
        {
                $fallback = DIR_FS_CATALOG . 'includes/modules/pages/my_subscriptions/saved_credit_cards_recurring_migration.log';
                if (defined('DIR_FS_LOGS')) {
                        return rtrim(DIR_FS_LOGS, '/\\') . '/saved_credit_cards_recurring_migration.log';
                }
                return $fallback;
        }

        protected function log_subscription_issue($message)
        {
                $path = $this->resolve_snapshot_log_path();
                if (!is_string($path) || $path === '') {
                        return false;
                }
                $directory = dirname($path);
                if (!is_dir($directory)) {
                        @mkdir($directory, 0777, true);
                }
                $timestamp = date('Y-m-d H:i:s');
                return error_log('[' . $timestamp . '] ' . $message . PHP_EOL, 3, $path);
        }

        protected function get_missing_source_order_comment($orders_products_id) {
		$orders_products_id = (int) $orders_products_id;
		if ($orders_products_id > 0) {
			return '  Cancelled automatically because original order item #' . $orders_products_id . ' was removed.  ';
		}
		return '  Cancelled automatically because the original order item was removed.  ';
	}
	public function cancel_subscription_for_missing_source_order($payment_details) {
		global $db;
		if (!is_array($payment_details)) {
			return false;
		}
		$recurring_id = isset($payment_details['saved_credit_card_recurring_id']) ? (int) $payment_details['saved_credit_card_recurring_id'] : 0;
		if ($recurring_id <= 0) {
			return false;
		}
		$subscription = $db->Execute('SELECT status, comments FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE saved_credit_card_recurring_id = ' . $recurring_id . ' LIMIT 1;');
                if ($subscription->RecordCount() === 0) {
                        return false;
                }
                $commentOrdersProductsId = isset($payment_details['original_orders_products_id']) ? (int) $payment_details['original_orders_products_id'] : 0;
                $comment = $this->get_missing_source_order_comment($commentOrdersProductsId);
                $this->log_subscription_issue('Missing source order context for subscription #' . $recurring_id . ' (orders_products_id ' . $commentOrdersProductsId . ')');
                if ($commentOrdersProductsId > 0) {
                        $commentToken = 'original order item #' . $commentOrdersProductsId . ' was removed';
                }
		else {
			$commentToken = 'the original order item was removed';
		}
		if (strtolower($subscription->fields['status']) === 'cancelled') {
			if (strpos($subscription->fields['comments'], $commentToken) === false) {
				$db->Execute('UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . " SET comments = CONCAT(comments, '" . $comment . "') WHERE saved_credit_card_recurring_id = " . $recurring_id . ' LIMIT 1;');
			}
			return false;
		}
		$customerId = isset($payment_details['customers_id']) ? (int) $payment_details['customers_id'] : false;
		$this->update_payment_status($recurring_id, 'cancelled', $comment, $customerId);
		return true;
	}
	function get_subscription_attributes($product) {
		global $db;
		$attributes = array();
                if (is_array($product['attributes'])) {
                        foreach ($product['attributes'] as $options_id => $options_values_id) {
                                $options = $db->Execute("SELECT products_options_name FROM " . TABLE_PRODUCTS_OPTIONS . " WHERE products_options_id = " . (int) $options_id . " LIMIT 1;");
                                if ($options->RecordCount() > 0) {
                                        $normalized_option_name = strtolower($options->fields['products_options_name']);
                                        $normalized_option_name = preg_replace('/\s+/', '', $normalized_option_name);
                                        switch ($normalized_option_name) {
						case 'billingperiod' :
							$billingperiod = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int) $options_values_id . " LIMIT 1;");
							if ($billingperiod->RecordCount() > 0) {
								switch ($billingperiod->fields['products_options_values_name']) {
									case 'Day' :
									case 'Daily' :
										$billing_period_value = 'Day';
										break;
									case 'Week' :
									case 'Weekly' :
										$billing_period_value = 'Week';
										break;
									case 'Month' :
									case 'Monthly' :
										$billing_period_value = 'Month';
										break;
									case 'SemiMonth' :
									case 'Semi Monthly' :
									case 'Semi-Monthly' :
									case 'Bi Weekly' :
									case 'Bi-Weekly' :
										$billing_period_value = 'SemiMonth';
										break;
									case 'Semi Monthly' :
									case 'Semi-Monthly' :
									case 'Bi Weekly' :
									case 'Bi-Weekly' :
										$billing_period_value = 'SemiMonth';
										break;
									case 'Year' :
									case 'Yearly' :
										$billing_period_value = 'Year';
										break;
								}
								$attributes['billingperiod'] = $billing_period_value;
							}
							break;
						case 'billingfrequency' :
							$billingfrequency = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int) $options_values_id . " LIMIT 1;");
							if ($billingfrequency->RecordCount() > 0) {
								if( strpos($billingfrequency->fields['products_options_values_name'], 'Lifetime') !== false ){
									$attributes['billingfrequency'] = "Lifetime";
								} else {
									$frequency = (int) $billingfrequency->fields['products_options_values_name'];
									if ($frequency > 0) {
										$attributes['billingfrequency'] = $frequency;
									}
									else {
	// Patching change names on attribute id 32 on Nmx
										switch (strtolower(trim($billingfrequency->fields['products_options_values_name']))) {
											case 'monthly' :
											case 'month' :
											case '1 month' :
											case '30 days' :
												$attributes['billingfrequency'] = 1;
												break;
											case 'quarterly' :
												$attributes['billingfrequency'] = 3;
												break;
											case '365 days' :
												$attributes['billingfrequency'] = 12;
												break;
											case 'lifetime' :
												$attributes['billingfrequency'] = 0;
												break;
										}
									}
								}
							}
							break;
						case 'totalbillingcycles' :
							$totalbillingcycles = $db->Execute("SELECT products_options_values_name FROM " . TABLE_PRODUCTS_OPTIONS_VALUES . " WHERE products_options_values_id = " . (int) $options_values_id . " LIMIT 1;");
							if ($totalbillingcycles->RecordCount() > 0) {
								if ($totalbillingcycles->fields['products_options_values_name'] == 'Good until cancelled' || $totalbillingcycles->fields['products_options_values_name'] == 'Good until cancelled') {
									$attributes['totalbillingcycles'] = 0;
								}
								else {
									$attributes['totalbillingcycles'] = $totalbillingcycles->fields['products_options_values_name'];
								}
							}
							break;
						case 'startdate' :
							$attributes['startdate'] = $product['attributes_values'][$options_id];
							break;
                                                default :
                                                        if (strpos($normalized_option_name, 'domain') !== false) {
                                                                $attributes['domain'] = $product['attributes_values'][$options_id];
                                                        }
                                                        break;
                                        }
                                }
                        }
                }
                if (isset($product['attributes_map']) && is_array($product['attributes_map'])) {
                        $attributes['attributes_map'] = $product['attributes_map'];
                }
                if (isset($product['attributes_values']) && is_array($product['attributes_values'])) {
                        $attributes['attributes_values'] = $product['attributes_values'];
                }
                return $attributes;
        }
/*
* Creates a Zen Cart order for this recurring payment
*/
	function prepare_order($customer_info, $products_id, $original_orders_products_id) {
		global $db, $zco_notifier, $order_total_modules, $order_totals, $order;
// initiate the shopping cart
		$_SESSION['cart'] = new shoppingCart();
// build the attributes
                $attributes_array = array();
                $attributes = $db->Execute("SELECT * FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " opa
                        LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " op ON (opa.orders_products_id = op.orders_products_id)
                        LEFT JOIN " . TABLE_PRODUCTS_OPTIONS . " po ON (po.products_options_id = opa.products_options_id)
                        WHERE op.orders_products_id = " . (int) $original_orders_products_id . "
                                AND (opa.attributes_price_onetime = 0 OR opa.attributes_price_onetime IS NULL);");
                if ($attributes->RecordCount() > 0) {
                        while (!$attributes->EOF) {
                                $value = $attributes->fields['products_options_values_id'];
// check if option is text input
                                if ($attributes->fields['products_options_type'] == 1) {
                                        $attributes->fields['products_options_id'] = TEXT_PREFIX . $attributes->fields['products_options_id'];
                                        $value = $attributes->fields['products_options_values'];
                                }
                                $attributes_array[$attributes->fields['products_options_id']] = $value;
                                $attributes->MoveNext();
                        }
                }
                elseif (isset($customer_info['subscription_attributes']['attributes_map']) && is_array($customer_info['subscription_attributes']['attributes_map'])) {
                        $attributes_array = $customer_info['subscription_attributes']['attributes_map'];
                }
// add product to the cart
                $_SESSION['cart']->add_cart($products_id, 1, $attributes_array); //only qty=1 of a subscription can be ordered.
//$products = $_SESSION['cart']->get_products();
//print_r($products);
//die();
// process payment
$_SESSION['payment'] = $this->paymentModuleCode; //use available payment module for recurring payments
$payment_modules = new payment($_SESSION['payment']);
//set shipping
//    $_SESSION['shipping'] = 'free'; //always this method for recurring subscription orders
		$_SESSION['shipping'] = array('id' => 'free');
// initiate the order
		$order = new order();
// get the users address book ID
		$_SESSION['billto'] = $_SESSION['sendto'] = $customer_info['customers_default_address_id'];
		$_SESSION['customer_id'] = $customer_info['customers_id'];
// set the cart variables
		$order->cart();
// initiate order totals
		$order_total_modules = new order_total();
		$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_BEFORE_ORDER_TOTALS_PROCESS');
//NX mod: don't allow store credit for plans
		if (!zen_product_in_category($products_id, CATEGORY_ID_PLANS) && !zen_product_in_category($products_id, CATEGORY_ID_CUSTOM_PLANS)) {
//plans cannot be paid for with store credit (because a plan is essentially purchasing store credit.)
//Initialize Store Credit
			$store_credit = new storeCredit();
//    define('MODULE_ORDER_TOTAL_SC_STATUS', 'true');
//    define('STORE_CREDIT_AUTOMATICALLY_ADD', 'true');
			$_SESSION['storecredit'] = $store_credit->retrieve_customer_credit($_SESSION['customer_id']);
//BOF NX mod: don't allow store credit for plan payments
		}
		else {
			$_SESSION['storecredit'] = 0; //in case it was set elsewhere.
		}
//EOF NX mod: don't allow store credit for plan payments
// process order totals
		$order_totals = $order_total_modules->process();
		$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_TOTALS_PROCESS');
		return array($order, $order_totals);
	}
	function create_order($order, $saved_credit_card_id = '') {
		global $db, $zco_notifier, $order_total_modules, $order_totals;
// create the order
		$zf_insert_id = $insert_id = $order->create($order_totals, 2);
		$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE');
// add products from shopping cart to the order
		$order->create_add_products($zf_insert_id, 2);
		$_SESSION['order_number_created'] = $zf_insert_id;
		$_SESSION['automatic_subscription_order'] = true;
		$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS');
		$zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE', array('zf_insert_id' => $zf_insert_id));
/*     // update the order history
$db->Execute("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified, comments)
VALUES (" . (int) $zf_insert_id . ", " . (int) MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID . ", NOW(), 0, 'PayPal Saved Card recurring payment successful " . $paypal_confirmation . "');");

// update order status in the orders table
$db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . (int) MODULE_PAYMENT_PAYPALSAVEDCARD_ORDER_STATUS_ID . " WHERE orders_id = " . (int) $zf_insert_id . " LIMIT 1;");
*/
// send the order email
//     $disable_store_credit_default = true;
		$order->send_order_email($zf_insert_id, 2);
//process the store credit
// $order_total_modules->apply_credit();
// reset the shopping cart session
		$_SESSION['cart']->reset(true);
if ($_SESSION['payment'] == 'paypalsavedcard' && is_object($this->paypalsavedcard) && method_exists($this->paypalsavedcard, 'after_process')) {
$this->paypalsavedcard->action = 'Sale';
$this->paypalsavedcard->saved_card_id = $saved_credit_card_id;
$this->paypalsavedcard->after_process();
}
// unset the customer ID
		unset($_SESSION['customer_id']);
		unset($_SESSION['payment']);
		unset($_SESSION['shipping']);
		return $zf_insert_id;
	}
                protected function normalize_saved_card_payment_details(array $fields) {
                $subscription_customer_id = 0;
                if (isset($fields['saved_card_customer_id']) && (int) $fields['saved_card_customer_id'] > 0) {
                        $subscription_customer_id = (int) $fields['saved_card_customer_id'];
                }
                elseif (isset($fields['subscription_customer_id']) && (int) $fields['subscription_customer_id'] > 0) {
                        $subscription_customer_id = (int) $fields['subscription_customer_id'];
                }
                elseif (isset($fields['customers_id']) && (int) $fields['customers_id'] > 0) {
                        $subscription_customer_id = (int) $fields['customers_id'];
                }

                if ($subscription_customer_id > 0) {
                        $fields['subscription_customer_id'] = $subscription_customer_id;
                        $fields['customers_id'] = $subscription_customer_id;
                }

                if (isset($fields['sccr_products_id']) && (int) $fields['sccr_products_id'] > 0) {
                        $fields['products_id'] = (int) $fields['sccr_products_id'];
                }
                elseif (isset($fields['products_id'])) {
                        $fields['products_id'] = (int) $fields['products_id'];
                }

                if (isset($fields['original_orders_id']) && (int) $fields['original_orders_id'] > 0) {
                        $fields['orders_id'] = (int) $fields['original_orders_id'];
                }

                if (isset($fields['sccr_products_name']) && $fields['sccr_products_name'] !== '') {
                        $fields['products_name'] = $fields['sccr_products_name'];
                }
                if (isset($fields['sccr_products_model']) && $fields['sccr_products_model'] !== '') {
                        $fields['products_model'] = $fields['sccr_products_model'];
                }

                if (isset($fields['sccr_currency_code']) && $fields['sccr_currency_code'] !== '') {
                        $fields['currency_code'] = $fields['sccr_currency_code'];
                }
                elseif (isset($fields['order_currency_code']) && $fields['order_currency_code'] !== '') {
                        $fields['currency_code'] = $fields['order_currency_code'];
                }

                if (isset($fields['sccr_billing_period']) && $fields['sccr_billing_period'] !== '') {
                        $fields['billing_period'] = $fields['sccr_billing_period'];
                }
                if (isset($fields['sccr_billing_frequency']) && $fields['sccr_billing_frequency'] !== null) {
                        if (is_numeric($fields['sccr_billing_frequency'])) {
                                $fields['billing_frequency'] = (int) $fields['sccr_billing_frequency'];
                        }
                }
                if (isset($fields['sccr_total_billing_cycles']) && $fields['sccr_total_billing_cycles'] !== null) {
                        if (is_numeric($fields['sccr_total_billing_cycles'])) {
                                $fields['total_billing_cycles'] = (int) $fields['sccr_total_billing_cycles'];
                        }
                }

                if (isset($fields['sccr_domain']) && $fields['sccr_domain'] !== '') {
                        $fields['domain'] = $fields['sccr_domain'];
                }

                $subscriptionAttributes = $this->get_snapshot_attributes($fields);
                if (count($subscriptionAttributes) > 0) {
                        $fields['subscription_attributes'] = $subscriptionAttributes;
                        if ((!isset($fields['billing_period']) || $fields['billing_period'] === '') && isset($subscriptionAttributes['billingperiod'])) {
                                $fields['billing_period'] = $subscriptionAttributes['billingperiod'];
                        }
                        if ((!isset($fields['billing_frequency']) || $fields['billing_frequency'] === null) && isset($subscriptionAttributes['billingfrequency']) && is_numeric($subscriptionAttributes['billingfrequency'])) {
                                $fields['billing_frequency'] = (int) $subscriptionAttributes['billingfrequency'];
                        }
                        if ((!isset($fields['total_billing_cycles']) || $fields['total_billing_cycles'] === null) && isset($subscriptionAttributes['totalbillingcycles']) && is_numeric($subscriptionAttributes['totalbillingcycles'])) {
                                $fields['total_billing_cycles'] = (int) $subscriptionAttributes['totalbillingcycles'];
                        }
                        if ((!isset($fields['domain']) || $fields['domain'] === '') && isset($subscriptionAttributes['domain'])) {
                                $fields['domain'] = $subscriptionAttributes['domain'];
                        }
                        if ((!isset($fields['currency_code']) || $fields['currency_code'] === '') && isset($subscriptionAttributes['currencycode'])) {
                                $fields['currency_code'] = $subscriptionAttributes['currencycode'];
                        }
                }

                if ((!isset($fields['domain']) || $fields['domain'] === '') && isset($fields['original_orders_products_id']) && (int) $fields['original_orders_products_id'] > 0 && function_exists('nmx_check_domain')) {
                        $domain = nmx_check_domain($fields['original_orders_products_id']);
                        if ($domain) {
                                $fields['domain'] = $domain;
                        }
                }

                if (isset($fields['amount'])) {
                        $fields['amount'] = preg_replace("/[^0-9\\.]/", "", $fields['amount']);
                }
                $fields['paypal_vault_card'] = $this->find_vault_card_for_payment($fields);
                return $fields;
        }

        function get_payment_details($paypal_saved_card_recurring_id, $preloaded_fields = null) {
		global $db;
                if (!is_array($preloaded_fields)) {
                        $hasDomainColumn = $this->saved_cards_recurring_has_column('domain');
                        $domainSelect = $hasDomainColumn ? "\n          sccr.domain AS sccr_domain," : '';
                        $sql = "SELECT
          sccr.*,
          sccr.products_id AS sccr_products_id,
          sccr.products_name AS sccr_products_name,
          sccr.products_model AS sccr_products_model,
          sccr.currency_code AS sccr_currency_code,
          sccr.billing_period AS sccr_billing_period,
          sccr.billing_frequency AS sccr_billing_frequency,
          sccr.total_billing_cycles AS sccr_total_billing_cycles," . $domainSelect . "
          sccr.subscription_attributes_json AS sccr_subscription_attributes_json,
          COALESCE(scc.customers_id, c.customers_id) AS subscription_customer_id,
          scc.*,
          c.*,
          scc.customers_id AS saved_card_customer_id
        FROM " . TABLE_SAVED_CREDIT_CARDS_RECURRING . " sccr
        LEFT JOIN " . TABLE_SAVED_CREDIT_CARDS . " scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
        INNER JOIN " . TABLE_CUSTOMERS . " c ON c.customers_id = scc.customers_id
        WHERE sccr.saved_credit_card_recurring_id = " . (int) $paypal_saved_card_recurring_id;
			$result = $db->Execute($sql);
			$fields = $result->fields;
		} else {
			$fields = $preloaded_fields;
		}

		if (!is_array($fields)) {
			return $fields;
		}

		$fields = $this->normalize_saved_card_payment_details($fields);
                $fields = $this->hydrate_payment_details_from_legacy_order($fields);
                return $fields;
        }

        function find_group_pricing($products_id) {
		global $db;
		$products_name = zen_get_products_name((int) $products_id);
		$group = $db->Execute("SELECT group_id, group_percentage FROM " . TABLE_GROUP_PRICING . " WHERE group_name = '" . $products_name . "' LIMIT 1;");
		if ($group->RecordCount() > 0 && (int) $group->fields['group_id'] > 0) {
			return $group->fields['group_id'];
		}
		return false;
	}
        function create_group_pricing($products_id, $customers_id, $next_billing_date = 0) {
                global $db;
                $group_id = $this->find_group_pricing($products_id);
                // Add user to pricing group
                if ($group_id > 0) {
                        // remove any pending cancellations from the database for this customer
                        $db->Execute("DELETE FROM " . TABLE_SUBSCRIPTION_CANCELLATIONS . " WHERE customers_id = " . (int) $customers_id . ";");
                        // set the customer's group ID
                        $db->Execute("UPDATE " . TABLE_CUSTOMERS . " SET customers_group_pricing = " . (int) $group_id . " WHERE customers_id = " . (int) $customers_id . " LIMIT 1;");
                        if ($next_billing_date > 0) {
                                $subsciption_plan_name = zen_get_products_name($products_id);
                                // schedule a new cancellation 1 day after the next plan payment, it will get deleted when the next plan payment comes in
                                $db->Execute("INSERT INTO " . TABLE_SUBSCRIPTION_CANCELLATIONS . " (customers_id, group_name, expiration_date) VALUES (" . (int) $customers_id . ", '" . $subsciption_plan_name . "', '" . date('Y-m-d', strtotime('+5 days', strtotime($next_billing_date))) . "');");
                        }
                }
        }
/*
* Cancels the users group pricing
* products_id is optional, if passed in the function will check that the product has a group discount before cancelling it.
*/
	function remove_group_pricing($customers_id, $products_id = false) {
		global $db;
		if ($products_id) {
			$group_id = $this->find_group_pricing($products_id);
			if (!$group_id) {
				return false;
			}
		}
		$sql = "UPDATE " . TABLE_CUSTOMERS . " SET customers_group_pricing = '0' WHERE customers_id = " . (int) $customers_id;
		if ($group_id) {
			$sql .= ' AND customers_group_pricing = ' . $group_id;
		}
		$sql .= ' LIMIT 1;';
		$db->Execute($sql);
	}
/*
* Some categories only allow the customer to have one subscription.  e.g. a customer can only be on one "plan"
* This function will cancel the other subscriptions in the category
*/
	function cancel_other_subscriptions_in_category($customer_id, $subscription_to_keep, $categories) {
		global $db;
// disable this for now
		return false;
		$customer_subscriptions = array();
		foreach ($categories as $category_id) {
			$customer_subscriptions = array_merge($customer_subscriptions, $this->get_customer_subscriptions($customer_id, $category_id));
		}
		foreach ($customer_subscriptions as $subscription) {
			if ($subscription['saved_credit_card_recurring_id'] != $subscription_to_keep && $subscription['status'] == 'scheduled') {
				$this->update_payment_status($subscription['saved_credit_card_recurring_id'], 'cancelled', 'Subscription was cancelled because the customer can only have one subscription in this category.  ', $customers_id);
			}
		}
	}
/*
* Returns the saved credit card id for the customer, if one exists
* Gives priority to cards marked as 'primary', then to newer cards
* This is the card to be automatically selected when the customer has not chosen another.
*/
	function get_customers_saved_card($customers_id) {
		global $db;
		$sql = 'SELECT saved_credit_card_id FROM ' . TABLE_SAVED_CREDIT_CARDS . ' WHERE customers_id = ' . $customers_id . ' AND is_deleted = \'0\' AND LAST_DAY(STR_TO_DATE(expiry, \'%m%y\')) > CURDATE() ORDER BY is_primary, saved_credit_card_id DESC;';
		$result = $db->execute($sql);
		if ($result->fields['saved_credit_card_id'] > 0) {
			return $result->fields['saved_credit_card_id'];
		}
		else {
			return false;
		}
	}
/*
* When a card is deleted, we need to check if it had any subscriptions.  If it did, we need to find another card to use for the subscription
* Returns a message to show to the customer
*/
	function card_was_deleted($card_id, $customers_id) {
		global $db;
//find another card
		$new_card = $this->get_customers_saved_card($customers_id);
if ($new_card) {
$new_card_details = $this->get_saved_card_details($new_card);
}
		$message = '';
		$sql = 'SELECT * FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr LEFT JOIN ' . TABLE_ORDERS_PRODUCTS . ' op ON op.orders_products_id = sccr.orders_products_id WHERE status = \'scheduled\' and saved_credit_card_id = ' . $card_id;
		$subscriptions = $db->Execute($sql);
		while (!$subscriptions->EOF) {
			if (!$new_card) {
				$this->update_payment_status($subscriptions->fields['saved_credit_card_recurring_id'], 'cancelled', 'Subscription was cancelled because there is no saved card available', $customers_id);
				$message .= 'There is no card available for your subscription to ' . $subscriptions->fields['products_name'] . ' so the subscription has been cancelled.  ';
			}
			else {
				$db->execute("UPDATE " . TABLE_SAVED_CREDIT_CARDS . " SET is_primary = 1 WHERE saved_credit_card_id = " . $new_card_details['saved_credit_card_id']);
				$this->update_payment_info($subscriptions->fields['saved_credit_card_recurring_id'], array('saved_credit_card_id' => $new_card));
				$message .= 'Your subscription to ' . $subscriptions->fields['products_name'] . ' will now use saved ' . $new_card_details['type'] . ' ending in ' . $new_card_details['last_digits'] . '.  ';
			}
			$subscriptions->MoveNext();
		}
		return $message;
	}
/*
*  Gets a list of paypal_saved_card_recurring_id's that are status scheduled and have a date of today or earlier. Used by cron.
*/
	function get_scheduled_payments() {
		global $db;
		$sql = 'SELECT saved_credit_card_recurring_id FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE status = \'scheduled\' AND date <= \'' . date('Y-m-d') . '\'';
		$result = $db->Execute($sql);
		$payments = array();
		while (!$result->EOF) {
			$payments[] = $result->fields['saved_credit_card_recurring_id'];
			$result->MoveNext();
		}
		return $payments;
	}
/*
* Update status of payment to 'complete', 'failed', 'scheduled', or 'cancelled'
* Customer id is optional, pass it in for a security check that the customer owns the subscription he is trying to update.
*/
        function update_payment_status($paypal_saved_card_recurring_id, $status, $comments = '', $customer_id = false) {
                global $db;
                $details = $this->get_payment_details($paypal_saved_card_recurring_id);
                $subscription_owner_id = 0;
                if (is_array($details)) {
                        if (isset($details['saved_card_customer_id']) && (int) $details['saved_card_customer_id'] > 0) {
                                $subscription_owner_id = (int) $details['saved_card_customer_id'];
                        }
                        elseif (isset($details['subscription_customer_id']) && (int) $details['subscription_customer_id'] > 0) {
                                $subscription_owner_id = (int) $details['subscription_customer_id'];
                        }
                        elseif (isset($details['customers_id'])) {
                                $subscription_owner_id = (int) $details['customers_id'];
                        }
                }
                if ($customer_id === false || (int)$customer_id <= 0) {
                        $customer_id = $subscription_owner_id;
                }
                if ($customer_id != false) {
//security check
                        if ($subscription_owner_id !== (int) $customer_id) {
                                return false;
                        }
                }
//If the subscription is being re-activated (new status scheduled), verify that the card associated with the subscription is still valid.  If not, find another one
		if ($status == 'scheduled') {
$saved_card = $this->get_saved_card_details($details['saved_credit_card_id']);
			if ($saved_card['is_deleted'] == '1') {
				$new_card = $this->get_customers_saved_card($details['customers_id']);
				if ($new_card > 0) {
					$this->update_payment_info($paypal_saved_card_recurring_id, array('saved_credit_card_id' => $new_card));
					$comments .= '  Saved card was changed because #' . $details['saved_credit_card_id'] . '  has been deleted.  ';
				}
				else {
					$status = 'cancelled';
					$comments .= '  Could not be activated because there are no saved cards available.  ';
				}
			}
		}
		$sql = 'UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' SET status = \'' . $status . '\', comments = CONCAT(comments, \'' . $comments . '\') WHERE saved_credit_card_recurring_id = ' . $paypal_saved_card_recurring_id;
                $db->Execute($sql);
                //notify admin of cancellation
                if ($status == 'cancelled' || $status == 'failed') {
                        $details = $this->get_payment_details($paypal_saved_card_recurring_id);
                        $message = 'Customer #:' . $customer_id . "\n";
			$message .= 'Customer name: ' . $details['customers_firstname'] . ' ' . $details['customers_lastname'] . "\n";
			$message .= 'Subscription #' . $paypal_saved_card_recurring_id . "\n";
			$message .= 'Product: ' . $details['products_name'] . "\n";
			$message .= 'New Status: ' . $status . "\n";
			$message .= 'Comments: ' . $comments . "\n";

                        // Add instructions for updating the payment card in plain text so it renders correctly for text emails
                        $message .= "\n------------------------------------------------------------";
                        $message .= "\nTo update your card go to Payment Info:";
                        $message .= "\nhttps://www.numinix.com/account_saved_credit_cards.html";
                        $message .= "\n------------------------------------------------------------\n";

			$this->notify_error('Subscription ' . $status . '  for ' . $details['customers_firstname'] . ' ' . $details['customers_lastname'], $message, 'warning', $details['customers_email_address'], $details['customers_firstname']);
			zen_mail('Numinix Support', 'support@numinix.com', 'Saved Credit Cards Recurring (warning) - ' . 'Subscription ' . $status . '  for ' . $details['customers_firstname'] . ' ' . $details['customers_lastname'], nl2br($message), STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'default');
		}
		//BOF NX mod by Jeff
		// elseif ($status == 'failed') {
		// 	$details = $this->get_payment_details($paypal_saved_card_recurring_id);
		// 	$message = 'Customer #:' . $customer_id . "\n";
		// 	$message .= 'Customer name: ' . $details['customers_firstname'] . ' ' . $details['customers_lastname'] . "\n";
		// 	$message .= 'Subscription #' . $paypal_saved_card_recurring_id . "\n";
		// 	$message .= 'Product: ' . $details['products_name'] . "\n";
		// 	$message .= 'New Status: ' . $status . "\n";
		// 	$message .= 'Comments: ' . $comments . "\n";

		// 	// Add new line for customer email with formatting
		// 	$message .= "\n<div style='border-bottom: 1px solid #ccc; width: 100%;'></div>";
		// 	$message .= "\n<div style='text-align: center; width:100%;font-size:20px;line-height:32px;'><p>To update your card go to</p> <a href='https://www.numinix.com/account_saved_credit_cards.html' style='text-decoration: none;background: #0686D4;color: #fff;padding:9px 20px;display: inline-block;margin-top:15px;border-radius: 4px;'>Payment Info</a></div>";
		// 	$message .= "\n<div style='border-bottom: 1px solid #ccc; width: 100%;'></div>\n";

		// 	zen_mail('Numinix Support', 'support@numinix.com', 'Subscription Payment Failed for ' . $details['customers_firstname'] . ' ' . $details['customers_lastname'], nl2br($message), STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => nl2br($message)), 'default');
		// }

		//EOF NX mod by Jeff
	}
	
	/**
	 * Skip the next payment for a subscription by calculating and updating the next billing date.
	 * 
	 * @param int $paypal_saved_card_recurring_id Subscription ID
	 * @param int|false $customer_id Optional customer ID for security check
	 * @return bool Success status
	 */
	function skip_next_payment($paypal_saved_card_recurring_id, $customer_id = false) {
		global $db;
		
		$paypal_saved_card_recurring_id = (int)$paypal_saved_card_recurring_id;
		if ($paypal_saved_card_recurring_id <= 0) {
			return false;
		}
		
		$details = $this->get_payment_details($paypal_saved_card_recurring_id);
		if (!is_array($details)) {
			return false;
		}
		
		// Determine subscription owner
		$subscription_owner_id = 0;
		if (isset($details['saved_card_customer_id']) && (int) $details['saved_card_customer_id'] > 0) {
			$subscription_owner_id = (int) $details['saved_card_customer_id'];
		}
		elseif (isset($details['subscription_customer_id']) && (int) $details['subscription_customer_id'] > 0) {
			$subscription_owner_id = (int) $details['subscription_customer_id'];
		}
		elseif (isset($details['customers_id'])) {
			$subscription_owner_id = (int) $details['customers_id'];
		}
		
		if ($customer_id === false || (int)$customer_id <= 0) {
			$customer_id = $subscription_owner_id;
		}
		
		// Security check
		if ($customer_id != false) {
			if ($subscription_owner_id !== (int) $customer_id) {
				return false;
			}
		}
		
		// Only allow skipping scheduled subscriptions
		if (!isset($details['status']) || $details['status'] !== 'scheduled') {
			return false;
		}
		
		// Extract subscription attributes
		$attributes = array();
		if (isset($details['subscription_attributes']) && is_array($details['subscription_attributes'])) {
			$attributes = $details['subscription_attributes'];
		} elseif (isset($details['subscription_attributes_json']) && $details['subscription_attributes_json'] !== '') {
			$decoded = json_decode($details['subscription_attributes_json'], true);
			if (is_array($decoded)) {
				$attributes = $decoded;
			}
		}
		
		// Get billing period and frequency
		if (!isset($attributes['billingperiod']) && isset($details['billing_period']) && $details['billing_period'] !== null) {
			$attributes['billingperiod'] = $details['billing_period'];
		}
		if (!isset($attributes['billingfrequency']) && isset($details['billing_frequency']) && $details['billing_frequency'] !== null) {
			$attributes['billingfrequency'] = $details['billing_frequency'];
		}
		
		// Validate we have billing info
		if (!isset($attributes['billingperiod']) || !isset($attributes['billingfrequency']) || 
		    $attributes['billingperiod'] === '' || (int)$attributes['billingfrequency'] <= 0) {
			return false;
		}
		
		// Get current scheduled date
		$currentDate = isset($details['date']) ? $details['date'] : date('Y-m-d');
		$baseDate = DateTime::createFromFormat('Y-m-d', $currentDate);
		if (!$baseDate) {
			$baseDate = new DateTime('today');
		}
		$baseDate->setTime(0, 0, 0);
		
		// Calculate next billing date
		$period = strtolower(trim((string)$attributes['billingperiod']));
		$frequency = (int)$attributes['billingfrequency'];
		
		$nextDate = clone $baseDate;
		try {
			switch ($period) {
				case 'day':
				case 'daily':
					$nextDate->add(new DateInterval('P' . $frequency . 'D'));
					break;
				case 'week':
				case 'weekly':
					$nextDate->add(new DateInterval('P' . $frequency . 'W'));
					break;
				case 'semimonth':
				case 'semi-month':
				case 'semi monthly':
				case 'semi-monthly':
				case 'bi-weekly':
				case 'bi weekly':
					$days = max(1, $frequency * 15);
					$nextDate->add(new DateInterval('P' . $days . 'D'));
					break;
				case 'month':
				case 'monthly':
					$nextDate->add(new DateInterval('P' . $frequency . 'M'));
					break;
				case 'year':
				case 'yearly':
					$nextDate->add(new DateInterval('P' . $frequency . 'Y'));
					break;
				default:
					$nextDate->modify('+' . $frequency . ' ' . $period);
					break;
			}
		} catch (Exception $e) {
			return false;
		}
		
		// Update the next payment date
		$newDate = $nextDate->format('Y-m-d');
		$this->update_payment_info($paypal_saved_card_recurring_id, array(
			'date' => $newDate,
			'comments' => '  Payment skipped by admin. Next payment date updated to ' . $newDate . '.  '
		));
		
		return true;
	}
	
/*
*  Function to update payment info.  Should only be applied to scheduled or cancelled payments, so that we keep historical data in tact.
*  Can be modified in the future to update more fields.
*/
	        function update_payment_info($paypal_saved_card_recurring_id, $data, array $metadata = array()) {
                global $db;

                $paypal_saved_card_recurring_id = (int) $paypal_saved_card_recurring_id;
                if ($paypal_saved_card_recurring_id <= 0) {
                        return;
                }

                if (isset($data['metadata']) && is_array($data['metadata'])) {
                        $metadata = array_merge($metadata, $data['metadata']);
                        unset($data['metadata']);
                }

                $sql = 'UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' SET saved_credit_card_recurring_id=saved_credit_card_recurring_id';

                if (isset($data['order_id']) && isset($data['date'])) {
                        $sql .= ', recurring_orders_id = ' . (int) $data['order_id'];
                        $sql .= ", date = '" . $this->escape_db_value($data['date']) . "'";
                }
                elseif (isset($data['date'])) {
                        $sql .= ", date = '" . $this->escape_db_value($data['date']) . "'";
                }
                elseif (isset($data['saved_credit_card_id'])) {
                        $sql .= ', saved_credit_card_id = ' . (int) $data['saved_credit_card_id'];
                }

                if (array_key_exists('original_orders_products_id', $data)) {
                        $originalOrdersProductsId = (int) $data['original_orders_products_id'];
                        if ($originalOrdersProductsId > 0) {
                                $sql .= ', orders_products_id = ' . $originalOrdersProductsId;
                        } else {
                                $sql .= ', orders_products_id = NULL';
                        }
                }

                if (isset($data['amount'])) {
                        $data['amount'] = preg_replace("/[^0-9\\.]/", '', $data['amount']);
                        $sql .= ", amount = '" . $this->escape_db_value($data['amount']) . "'";
                }

                if (isset($data['comments'])) {
                        $sql .= ", comments = CONCAT(comments, '" . $this->escape_db_value($data['comments']) . "')";
                }

                if (isset($data['product'])) {
                        $product = $this->get_product_info_with_desc($data['product']);
                        if (is_array($product) && isset($data['original_orders_products_id'])) {
                                $updateProductSQL = 'UPDATE ' . TABLE_ORDERS_PRODUCTS . ' SET products_id = ' . (int) $product['products_id'];
                                $updateProductSQL .= ", products_name = '" . $this->escape_db_value($product['products_name']) . "'";
                                $updateProductSQL .= ", products_model = '" . $this->escape_db_value($product['products_model']) . "'";
                                $updateProductSQL .= ' WHERE orders_products_id = ' . (int) $data['original_orders_products_id'];
                                $db->Execute($updateProductSQL);
                        }

                        $metadata['products_id'] = isset($product['products_id']) ? (int) $product['products_id'] : null;
                        $metadata['products_name'] = isset($product['products_name']) ? $product['products_name'] : null;
                        $metadata['products_model'] = isset($product['products_model']) ? $product['products_model'] : null;
                }

                $snapshotUpdates = array();
                $subscriptionAttributes = array();
                if (isset($metadata['subscription_attributes']) && is_array($metadata['subscription_attributes'])) {
                        $subscriptionAttributes = $metadata['subscription_attributes'];
                        unset($metadata['subscription_attributes']);
                }
                elseif (isset($data['subscription_attributes']) && is_array($data['subscription_attributes'])) {
                        $subscriptionAttributes = $data['subscription_attributes'];
                }

                foreach (array('products_id', 'billing_frequency', 'total_billing_cycles') as $intKey) {
                        if (isset($metadata[$intKey]) && $metadata[$intKey] !== null && $metadata[$intKey] !== '') {
                                $snapshotUpdates[] = $intKey . ' = ' . (int) $metadata[$intKey];
                                unset($metadata[$intKey]);
                        }
                }

                foreach (array('products_name', 'products_model', 'billing_period', 'domain', 'currency_code') as $stringKey) {
                        if (isset($metadata[$stringKey]) && $metadata[$stringKey] !== null && $metadata[$stringKey] !== '') {
                                $snapshotUpdates[] = $stringKey . " = '" . $this->escape_db_value($metadata[$stringKey]) . "'";
                                unset($metadata[$stringKey]);
                        }
                }

                if (isset($metadata['subscription_attributes_json']) && $metadata['subscription_attributes_json'] !== '') {
                        $snapshotUpdates[] = "subscription_attributes_json = '" . $this->escape_db_value($metadata['subscription_attributes_json']) . "'";
                        unset($metadata['subscription_attributes_json']);
                }

                if (count($subscriptionAttributes) > 0) {
                        $encoded = $this->encode_schedule_attributes($subscriptionAttributes);
                        if ($encoded !== '') {
                                $snapshotUpdates[] = "subscription_attributes_json = '" . $this->escape_db_value($encoded) . "'";
                        }
                }

                if (count($snapshotUpdates) > 0) {
                        $updateSql = 'UPDATE ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' SET ' . implode(', ', $snapshotUpdates);
                        $updateSql .= ' WHERE saved_credit_card_recurring_id = ' . $paypal_saved_card_recurring_id;
                        $db->Execute($updateSql);
                }

                $sql .= ' WHERE saved_credit_card_recurring_id = ' . $paypal_saved_card_recurring_id;
                $db->Execute($sql);
        }

        function count_failed_payments($paypal_saved_card_recurring_id, array $payment_details = null) {
		global $db;
		if (!is_array($payment_details)) {
			$payment_details = $this->get_payment_details($paypal_saved_card_recurring_id);
		}

		if (!is_array($payment_details)) {
			return 0;
		}

		$scopeSql = $this->build_subscription_scope_sql($payment_details);
		if ($scopeSql === '') {
			return 0;
		}

		$result = $db->Execute('SELECT MAX(date) as last_success FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . " WHERE " . $scopeSql . " AND status = 'complete'");
		$last_successful_payment = isset($result->fields['last_success']) ? $result->fields['last_success'] : '' ;

		$sql = 'SELECT count(*) AS count FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . " WHERE status = 'failed' AND " . $scopeSql;
		if (!empty($last_successful_payment)) {
			$sql .= " AND date > '" . $last_successful_payment . "'";
		}
		$result = $db->Execute($sql);
		return (int) $result->fields['count'];
	}

/*
*  This function will return an array of all of the product_ids that the user has an open subscription for (scheduled payment) or that the user has cancelled.
*  Used on the My Account -> Service Plan page
*/
        function get_customer_subscriptions($customer_id, $category_id = null, $domain = '') {
                global $db;
                $hasDomainColumn = $this->saved_cards_recurring_has_column('domain');
                $domainSelect = $hasDomainColumn ? "\n            sccr.domain AS sccr_domain," : '';
                $sql = 'SELECT
            sccr.*,
            sccr.products_id AS sccr_products_id,
            sccr.products_name AS sccr_products_name,
            sccr.products_model AS sccr_products_model,
            sccr.currency_code AS sccr_currency_code,
            sccr.billing_period AS sccr_billing_period,
            sccr.billing_frequency AS sccr_billing_frequency,
            sccr.total_billing_cycles AS sccr_total_billing_cycles,' . $domainSelect . '
            sccr.subscription_attributes_json AS sccr_subscription_attributes_json,
            scc.customers_id AS saved_card_customer_id,
            scc.type,
            scc.last_digits,
            scc.is_deleted,
            scc.is_primary,
            c.customers_firstname,
            c.customers_lastname,
            c.customers_email_address,
            c.customers_default_address_id
        FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' sccr
        LEFT JOIN ' . TABLE_SAVED_CREDIT_CARDS . ' scc ON scc.saved_credit_card_id = sccr.saved_credit_card_id
        LEFT JOIN ' . TABLE_CUSTOMERS . ' c ON c.customers_id = scc.customers_id
        WHERE scc.customers_id = ' . (int) $customer_id . " AND sccr.status IN ('cancelled', 'scheduled')";
                if ($category_id != null) {
                        $sql .= ' AND sccr.products_id IN (SELECT products_id FROM ' . TABLE_PRODUCTS . ' WHERE master_categories_id = ' . (int) $category_id . ')';
                }
                $result = $db->Execute($sql);
                $payments = array();
                while (!$result->EOF) {
                        $normalized = $this->normalize_saved_card_payment_details($result->fields);
                        if ($domain !== '') {
                                $subscription_domain = '';
                                if (isset($normalized['domain']) && $normalized['domain'] !== '') {
                                        $subscription_domain = $normalized['domain'];
                                }
                                if ($subscription_domain !== $domain) {
                                        $result->MoveNext();
                                        continue;
                                }
                        }

                        $normalized['type'] = isset($result->fields['type']) ? $result->fields['type'] : (isset($normalized['type']) ? $normalized['type'] : '');
                        $normalized['card_type'] = $normalized['type'];
                        if (isset($result->fields['last_digits'])) {
                                $normalized['last_digits'] = $result->fields['last_digits'];
                        }

                        $key = 0;
                        if (isset($normalized['original_orders_products_id']) && (int) $normalized['original_orders_products_id'] > 0) {
                                $key = (int) $normalized['original_orders_products_id'];
                        }
                        elseif (isset($normalized['saved_credit_card_recurring_id'])) {
                                $key = (int) $normalized['saved_credit_card_recurring_id'];
                        }
                        $payments[$key] = $normalized;
                        $result->MoveNext();
                }
                return $payments;
        }
/*
* Searches to find if the customer already has a subscription to the given product
* If so, return the subscription id.  If not, returns false.
*/
	function customer_has_subscription($customer_id, $product_id, $domain = '') {
		global $db;
		$subscriptions = $this->get_customer_subscriptions($customer_id, $domain);
		foreach ($subscriptions as $subscription) {
			if ((int) $subscription['products_id'] == (int) $product_id && $subscription['status'] == 'scheduled' && strtotime($subscription['date']) > strtotime('today')) {
				return $subscription['saved_credit_card_recurring_id'];
			}
		}
		return false;
	}
        function subscription_stats($paypal_saved_card_recurring_id, $payment_details = null, array $precomputed_stats = array()) {
                global $db;

                $payment_details = $this->get_payment_details($paypal_saved_card_recurring_id, $payment_details);
                $stats = array(
                        'start_date' => '',
                        'payments_completed' => 0,
                        'next_date' => '',
                        'payments_remaining' => 0,
                        'overdue_balance' => 0,
                        'missing_source_order' => false,
                );

                if (!is_array($payment_details)) {
                        return $stats;
                }

                $original_orders_products_id = isset($payment_details['original_orders_products_id']) ? (int) $payment_details['original_orders_products_id'] : 0;
                $scopeSql = $this->build_subscription_scope_sql($payment_details);

                $attributes = $this->get_snapshot_attributes($payment_details);
                if (!is_array($attributes) || count($attributes) === 0) {
                        if ($original_orders_products_id > 0) {
                                $attributes = $this->get_attributes($original_orders_products_id);
                        }
                }

                if (!is_array($attributes) || count($attributes) === 0) {
                        if ($original_orders_products_id > 0) {
                                $this->cancel_subscription_for_missing_source_order($payment_details);
                                $stats['missing_source_order'] = true;
                        }
                        return $stats;
                }

                $precomputed = array();
                if ($original_orders_products_id > 0 && isset($precomputed_stats[$original_orders_products_id]) && is_array($precomputed_stats[$original_orders_products_id])) {
                        $precomputed = $precomputed_stats[$original_orders_products_id];
                }

                $completed_payments = null;
                if (isset($precomputed['completed_payments'])) {
                        $completed_payments = (int) $precomputed['completed_payments'];
                }
                elseif ($scopeSql !== '') {
                        $result = $db->Execute('SELECT COUNT(*) AS num_payments FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . " WHERE " . $scopeSql . " AND status = 'complete'");
                        $completed_payments = (int) $result->fields['num_payments'];
                }
                else {
                        $completed_payments = 0;
                }

                $stats['payments_completed'] = $completed_payments + 1;

                $context = is_array($payment_details) ? $payment_details : array();
                $context['original_orders_products_id'] = $original_orders_products_id;
                $context['completed_payments'] = $completed_payments;
                if ($scopeSql !== '') {
                        $context['subscription_scope'] = $scopeSql;
                }
                $stats['next_date'] = $this->next_billing_date($attributes, $context);

                $totalCycles = isset($attributes['totalbillingcycles']) ? $attributes['totalbillingcycles'] : null;
                if (!is_numeric($totalCycles)) {
                        $stats['payments_remaining'] = $totalCycles;
                }
                else {
                        $stats['payments_remaining'] = (int) $totalCycles - $stats['payments_completed'];
                }

                $start_date = '';
                if (isset($precomputed['start_date']) && $precomputed['start_date'] !== '') {
                        $start_date = $precomputed['start_date'];
                }
                elseif (isset($payment_details['order_date_purchased']) && $payment_details['order_date_purchased'] !== '') {
                        $start_date = date('Y-m-d', strtotime($payment_details['order_date_purchased']));
                }
                elseif (isset($payment_details['date_purchased']) && $payment_details['date_purchased'] !== '') {
                        $start_date = date('Y-m-d', strtotime($payment_details['date_purchased']));
                }
                elseif ($scopeSql !== '') {
                        $dateResult = $db->Execute('SELECT MIN(date) AS first_date FROM ' . TABLE_SAVED_CREDIT_CARDS_RECURRING . ' WHERE ' . $scopeSql);
                        if (isset($dateResult->fields['first_date']) && $dateResult->fields['first_date'] !== null) {
                                $start_date = date('Y-m-d', strtotime($dateResult->fields['first_date']));
                        }
                }
                elseif ($original_orders_products_id > 0) {
                        $sql = 'SELECT date_purchased FROM ' . TABLE_ORDERS . ' o JOIN ' . TABLE_ORDERS_PRODUCTS . ' op ON op.orders_id = o.orders_id WHERE op.orders_products_id = ' . $original_orders_products_id;
                        $order_result = $db->Execute($sql);
                        if (isset($order_result->fields['date_purchased'])) {
                                $start_date = date('Y-m-d', strtotime($order_result->fields['date_purchased']));
                        }
                }

                if ($start_date !== '') {
                        $stats['start_date'] = $start_date;
                }

                $amount = isset($payment_details['amount']) ? (float) $payment_details['amount'] : 0.0;
                if ($stats['start_date'] !== '' && $amount !== 0.0) {
                        $startDateObj = date_create($stats['start_date']);
                        $now = date_create();
                        if ($startDateObj && $now) {
                                $interval = date_diff($startDateObj, $now);
                                $billingPeriod = isset($attributes['billingperiod']) ? $attributes['billingperiod'] : '';
                                $billing_period_abbrev = $this->get_billing_period_abbrev($billingPeriod);
                                switch ($billing_period_abbrev) {
                                        case 'y':
                                                $expected_payments_completed = $interval->y;
                                                break;
                                        case 'm':
                                                $expected_payments_completed = ($interval->y * 12) + $interval->m;
                                                break;
                                        case 'sm':
                                                $expected_payments_completed = ($interval->days === false) ? 0 : floor($interval->days / 15);
                                                break;
                                        case 'w':
                                                $expected_payments_completed = ($interval->days === false) ? 0 : floor($interval->days / 7);
                                                break;
                                        default:
                                                $expected_payments_completed = ($interval->days === false) ? 0 : $interval->days;
                                                break;
                                }

                                if ($stats['payments_completed'] < $expected_payments_completed) {
                                        $stats['overdue_balance'] = ($expected_payments_completed - $stats['payments_completed']) * $amount;
                                }
                        }
                }

                return $stats;
        }

        protected function get_billing_period_abbrev($billingPeriod) {
                if (!is_string($billingPeriod)) {
                        return '';
                }

                switch (strtolower(trim($billingPeriod))) {
                        case 'year':
                        case 'years':
                        case 'annual':
                        case 'annually':
                        case 'y':
                                return 'y';
                        case 'month':
                        case 'months':
                        case 'monthly':
                        case 'mo':
                        case 'm':
                                return 'm';
                        case 'semimonth':
                        case 'semi-month':
                        case 'semi monthly':
                        case 'semi-monthly':
                        case 'biweekly':
                        case 'bi-weekly':
                        case 'bi weekly':
                        case 'sm':
                                return 'sm';
                        case 'week':
                        case 'weeks':
                        case 'weekly':
                        case 'w':
                                return 'w';
                        case 'day':
                        case 'days':
                        case 'daily':
                        case 'd':
                        default:
                                return 'd';
                }
        }

        function checkout_confirmation_form($order) {
		$subscriptions = $this->find_subscription_products_in_order($order->products);
		if (sizeof($subscriptions) > 0) {
			foreach ($_POST as $key => $value) {
				if (strstr($key, 'paypalwpp')) {
					echo zen_draw_hidden_field($key, $value, 'class="hiddenField"');
				}
			}
		}
	}
        function notify_error($subject, $message, $type = 'error', $customers_email = '', $customers_name = '') {
                $to = MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL;
                if ($type == 'error') {
                        $message = "The Saved Credit Cards Recurring module encountered an error.  Please contact Numinix Support if you unsure of how to resolve this issue \n\n" . $message;
                }

                $htmlMessage = nl2br($message);

                if ($type == 'warning') {
                        if (strlen(trim($customers_email)) > 0) {
                                zen_mail($customers_name, $customers_email, 'Saved Credit Cards Recurring (' . $type . ') - ' . $subject, $htmlMessage, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => $htmlMessage), 'recurring_' . $type);
                        }

                        if (strlen(trim($to)) > 0) {
                                zen_mail($to, $to, 'Saved Credit Cards Recurring (' . $type . ') - ' . $subject, $htmlMessage, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => $htmlMessage), 'recurring_admin_' . $type);
                        }
                } else {
                        if (strlen(trim($to)) > 0) {
                                zen_mail($to, $to, 'Saved Credit Cards Recurring (' . $type . ') - ' . $subject, $htmlMessage, STORE_NAME, EMAIL_FROM, array('EMAIL_MESSAGE_HTML' => $htmlMessage), 'recurring_' . $type);
                        }
                }
        }

	//BOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation
        function schedule_subscription_cancellation($customers_id, $expiration_date, $products_id = false) {
		global $db;

		if ($products_id) {
			$group_info = $this->find_group_info($products_id);

			if (!$group_info) {
				return false;
			}

			$group_name = $group_info['group_name'];

			$sql_data_array= array(
				array('fieldName'=>'customers_id', 'value'=> $customers_id, 'type'=>'integer'),
				array('fieldName'=>'group_name', 'value'=> $group_name, 'type'=>'string'),
				array('fieldName'=>'expiration_date', 'value'=>$expiration_date, 'type'=>'date'),

		   );

		   $db->perform(TABLE_SUBSCRIPTION_CANCELLATIONS, $sql_data_array);
		}


	}

        function remove_subscription_cancellation($customers_id, $expiration_date, $products_id = false){
		global $db;
		if ($products_id) {
			$group_info = $this->find_group_info($products_id);

			if (!$group_info) {
				return false;
			}

		   $group_name = $group_info['group_name'];

		   $sql = "DELETE FROM " .TABLE_SUBSCRIPTION_CANCELLATIONS. " WHERE customers_id = :customers_id AND group_name = :group_name AND expiration_date = :expiration_date ";
		   $sql = $db->bindVars($sql, ':customers_id', $customers_id, 'integer');
		   $sql = $db->bindVars($sql, ':group_name', $group_name, 'string');
		   $sql = $db->bindVars($sql, ':expiration_date', $expiration_date, 'date');
		   $db->Execute($sql);
		}
	}

	function find_group_info($products_id) {
		global $db;
		$products_name = zen_get_products_name((int) $products_id);
		$group = $db->Execute("SELECT group_id, group_percentage,group_name FROM " . TABLE_GROUP_PRICING . " WHERE group_name = '" . $products_name . "' LIMIT 1;");

		if ($group->RecordCount() > 0 && (int) $group->fields['group_id'] > 0) {
			return $group->fields;
		}
		return false;
	}
	//EOF Modified for NX-3191::Remove Subscription Discount at Renewal Date after Cancellation
}