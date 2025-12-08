<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/modules/payment/braintree_googlepay.php';

class BraintreeGooglePayTest extends TestCase {
    private $module;

    protected function setUp(): void {
        $_SESSION = [];
        $reflection = new ReflectionClass('braintree_googlepay');
        $this->module = $reflection->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void {
        $_POST = [];
        $_SESSION = [];
    }

    public function testProcessButtonReturnsEmptyWhenNoNonce() {
        $_POST = [];
        $this->assertSame('', $this->module->process_button());
    }

    public function testProcessButtonReturnsHiddenFieldWhenNonceProvided() {
        $_POST['payment_method_nonce'] = 'nonce123';
        $_POST['google_pay_card_funding_source'] = 'credit';
        $expected = "\n<input type=\"hidden\" name=\"payment_method_nonce\" value=\"nonce123\" />" .
            "\n<input type=\"hidden\" name=\"google_pay_card_funding_source\" value=\"credit\" />";
        $this->assertSame($expected, $this->module->process_button());
    }

    public function testProcessButtonAjaxWithoutNonce() {
        global $order; // defined in bootstrap
        $_POST = [];
        $expected = [
            'ccFields' => [],
            'extraFields' => [zen_session_name() => zen_session_id()]
        ];
        $this->assertEquals($expected, $this->module->process_button_ajax());
    }

    public function testProcessButtonAjaxWithNonce() {
        global $order;
        $_POST['payment_method_nonce'] = 'nonce123';
        $_POST['google_pay_card_funding_source'] = 'debit';
        $expected = [
            'ccFields' => [
                'bt_nonce' => 'nonce123',
                'bt_payment_type' => 'google_pay',
                'bt_currency_code' => $order->info['currency'],
                'bt_card_funding_source' => 'debit'
            ],
            'extraFields' => [zen_session_name() => zen_session_id()]
        ];
        $this->assertEquals($expected, $this->module->process_button_ajax());
    }
}
?>
