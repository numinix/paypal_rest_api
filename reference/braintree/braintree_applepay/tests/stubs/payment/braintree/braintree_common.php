<?php
class BraintreeCommon {
    public $config;
    public $captureParams = [];
    public function __construct($config) {
        $this->config = $config;
    }
    public function generate_client_token($merchantAccountId) {
        return 'TOKEN_' . $merchantAccountId;
    }
    public function get_merchant_account_id($currency) {
        return 'MAID_' . $currency;
    }
    public function capturePayment($order_id, $status, $code) {
        $this->captureParams = [$order_id, $status, $code];
        return true;
    }
    public function _doRefund($oID, $amount, $note) { return true; }
    public function create_braintree_table() {}
    public function before_process_common($maID, $arr = [], $settlement = false) { return []; }
    public function getTransactionId($orderId) { return 'txn_' . $orderId; }
    public function _GetTransactionDetails($oID) { return ['id' => $oID]; }
}
