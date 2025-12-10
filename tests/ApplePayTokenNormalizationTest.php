<?php
/**
 * Test that Apple Pay tokens and contact information are normalized to PayPal's expected format
 * before calling confirmPaymentSource to satisfy PayPal's schema.
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

// Test 1: Token normalization
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

// Test 2: Contact normalization
$applePayloadWithContacts = [
    'orderID' => 'TEST-ORDER-ID',
    'token' => [
        'paymentData' => [
            'data' => 'encrypted-data',
        ],
    ],
    'wallet' => 'apple_pay',
    'billing_contact' => [
        'givenName' => 'John',
        'familyName' => 'Doe',
        'emailAddress' => 'john.doe@example.com',
        'addressLines' => ['123 Main St', 'Apt 4'],
        'locality' => 'San Francisco',
        'administrativeArea' => 'CA',
        'postalCode' => '94105',
        'countryCode' => 'US',
    ],
];

$normalizedWithContacts = $common->normalizeWalletPayloadPublic('apple_pay', $applePayloadWithContacts, $errorMessages);

// Check name transformation
if (!isset($normalizedWithContacts['name']) || 
    $normalizedWithContacts['name']['given_name'] !== 'John' ||
    $normalizedWithContacts['name']['surname'] !== 'Doe') {
    fwrite(STDERR, "FAIL: Name should be transformed to PayPal format\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Name transformed to PayPal format\n");
}

// Check email transformation
if (!isset($normalizedWithContacts['email_address']) || 
    $normalizedWithContacts['email_address'] !== 'john.doe@example.com') {
    fwrite(STDERR, "FAIL: Email should be extracted\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Email extracted correctly\n");
}

// Check billing address transformation
if (!isset($normalizedWithContacts['billing_address']) ||
    $normalizedWithContacts['billing_address']['address_line_1'] !== '123 Main St' ||
    $normalizedWithContacts['billing_address']['address_line_2'] !== 'Apt 4' ||
    $normalizedWithContacts['billing_address']['admin_area_2'] !== 'San Francisco' ||
    $normalizedWithContacts['billing_address']['admin_area_1'] !== 'CA' ||
    $normalizedWithContacts['billing_address']['postal_code'] !== '94105' ||
    $normalizedWithContacts['billing_address']['country_code'] !== 'US') {
    fwrite(STDERR, "FAIL: Billing address should be transformed to PayPal format\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Billing address transformed to PayPal format\n");
}

// Check that raw contacts are removed
if (isset($normalizedWithContacts['billing_contact']) || 
    isset($normalizedWithContacts['shipping_contact']) ||
    isset($normalizedWithContacts['wallet']) ||
    isset($normalizedWithContacts['orderID'])) {
    fwrite(STDERR, "FAIL: Raw contact fields should be removed\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Raw contact fields removed\n");
}

// Test 3: Non-Apple Pay payload unchanged
$untouchedPayload = $common->normalizeWalletPayloadPublic('google_pay', ['token' => 'already-string'], $errorMessages);
if ($untouchedPayload['token'] !== 'already-string') {
    fwrite(STDERR, "FAIL: Non-Apple Pay payload should not be modified\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Non-Apple Pay payload left unchanged\n");
}

if ($failures === 0) {
    fwrite(STDOUT, "\nAll Apple Pay token and contact normalization tests passed!\n");
    exit(0);
}

fwrite(STDERR, "\n$failures test(s) failed.\n");
exit(1);
