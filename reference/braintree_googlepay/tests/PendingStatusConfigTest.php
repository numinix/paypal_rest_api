<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/modules/payment/braintree_googlepay.php';

class PendingStatusConfigTest extends TestCase {
    private $module;

    protected function setUp(): void {
        $_SESSION = [];
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void {
        $_SESSION = [];
    }

    public function testPendingStatusIdKeyIsRegistered() {
        $keys = $this->module->keys();
        $this->assertContains('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID', $keys, 
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID should be registered in keys()');
    }

    public function testPendingStatusIdKeyPositionAfterRefundedStatusId() {
        $keys = $this->module->keys();
        $refundedIndex = array_search('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_REFUNDED_STATUS_ID', $keys);
        $pendingIndex = array_search('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID', $keys);
        
        $this->assertNotFalse($refundedIndex, 'REFUNDED_STATUS_ID should exist in keys');
        $this->assertNotFalse($pendingIndex, 'PENDING_STATUS_ID should exist in keys');
        $this->assertGreaterThan($refundedIndex, $pendingIndex, 
            'PENDING_STATUS_ID should come after REFUNDED_STATUS_ID in the keys array');
    }

    public function testAllExpectedKeysArePresent() {
        $keys = $this->module->keys();
        $expectedKeys = [
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_VERSION',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SERVER',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PUBLIC_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRIVATE_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOKENIZATION_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SETTLEMENT',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRODUCT_PAGE',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SHOPPING_CART',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOTAL_SELECTOR',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ZONE',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SORT_ORDER'
        ];
        
        foreach ($expectedKeys as $expectedKey) {
            $this->assertContains($expectedKey, $keys, "$expectedKey should be in keys()");
        }
        
        $this->assertCount(count($expectedKeys), $keys, 
            'keys() should return exactly ' . count($expectedKeys) . ' configuration keys');
    }
}
