<?php
class BraintreeCommon {
    public function __construct($config = []) {}
    public function get_merchant_account_id($currency) { return 'stub_account'; }
    public function generate_client_token($merchantAccountID) { return 'test_token'; }
    public function before_process_common($merchantAccountId, $arr, $capture) { return true; }
    public function getTransactionId($orderId) { return 'txn-' . $orderId; }
    public function _GetTransactionDetails($oID) { return []; }
    public function _doRefund($oID, $amount, $note) { return true; }
    public function capturePayment($order_id, $status, $code) { return true; }
    public function create_braintree_table() {}
}
