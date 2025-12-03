<?php
// Mock BraintreeCommon class for testing
class BraintreeCommon {
    private $config;
    private $failCount = 0; // For testing retry behavior
    
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    public function generate_client_token($merchantAccountID = null) {
        // Mock implementation - returns a token with the merchant account
        if (isset($GLOBALS['mock_token_fail_count']) && $this->failCount < $GLOBALS['mock_token_fail_count']) {
            $this->failCount++;
            return false;
        }
        return 'token-acct-' . ($merchantAccountID ?: 'USD');
    }
    
    public function get_merchant_account_id($currency) {
        return $currency;
    }
    
    public function before_process_common($merchantAccountID, $params, $settlement) {
        return true;
    }
    
    public function getTransactionId($orderId) {
        return 'txn-' . $orderId;
    }
    
    public function _GetTransactionDetails($orderId) {
        return [];
    }
    
    public function _doRefund($orderId, $amount, $note) {
        return true;
    }
    
    public function capturePayment($orderId, $orderStatus, $code) {
        return true;
    }
    
    public function create_braintree_table() {
        $GLOBALS['table_created'] = true;
    }
}
