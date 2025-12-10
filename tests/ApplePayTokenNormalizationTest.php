<?php
/**
 * Test that Apple Pay tokens are normalized to JSON strings before calling
 * confirmPaymentSource to satisfy PayPal's schema.
 */

require_once __DIR__ . '/../includes/modules/payment/paypal/paypal_common.php';

define('FILENAME_CHECKOUT_PAYMENT', 'checkout_payment');

class StubLogger
{
    public $messages = [];

    public function write($message, $force = false, $context = '')
    {
        $this->messages[] = $message;
    }
}

class StubPaymentModule
{
    public $log;
    public $redirects = [];

    public function __construct()
    {
        $this->log = new StubLogger();
    }

    public function setMessageAndRedirect($message, $page)
    {
        $this->redirects[] = [$message, $page];
    }
}

class PayPalCommonWrapper extends PayPalCommon
{
    public function __construct($paymentModule)
    {
        parent::__construct($paymentModule);
    }

    public function normalizeWalletPayloadPublic($walletType, array $payload, array $errorMessages)
    {
        return $this->normalizeWalletPayload($walletType, $payload, $errorMessages);
    }
}

$failures = 0;
$paymentModule = new StubPaymentModule();
$common = new PayPalCommonWrapper($paymentModule);

$applePayload = [
    'orderID' => 'TEST-ORDER-ID',
    'token' => [
        'paymentData' => [
            'data' => 'encrypted-data',
            'signature' => 'signature',
        ],
    ],
    'wallet' => 'apple_pay',
];

$errorMessages = ['payload_invalid' => 'Invalid payload'];
$normalizedPayload = $common->normalizeWalletPayloadPublic('apple_pay', $applePayload, $errorMessages);

if (!is_string($normalizedPayload['token'])) {
    fwrite(STDERR, "FAIL: Apple Pay token should be JSON-encoded string\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Apple Pay token normalized to JSON string\n");
}

if ($normalizedPayload['token'] !== json_encode($applePayload['token'])) {
    fwrite(STDERR, "FAIL: Apple Pay token JSON does not match original payload\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Apple Pay token matches JSON encoding of payload\n");
}

$untouchedPayload = $common->normalizeWalletPayloadPublic('google_pay', ['token' => 'already-string'], $errorMessages);
if ($untouchedPayload['token'] !== 'already-string') {
    fwrite(STDERR, "FAIL: Non-Apple Pay payload should not be modified\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Non-Apple Pay payload left unchanged\n");
}

if ($failures === 0) {
    fwrite(STDOUT, "\nAll Apple Pay token normalization tests passed!\n");
    exit(0);
}

fwrite(STDERR, "\n$failures test(s) failed.\n");
exit(1);
