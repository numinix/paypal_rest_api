<?php
/**
 * Test that Apple Pay tokens are normalized to PayPal's expected format
 * before calling confirmPaymentSource to satisfy PayPal's schema.
 * 
 * Per PayPal's API schema, Apple Pay confirmPaymentSource should ONLY contain
 * the token field. Contact information (name, email, billing_address) should
 * NOT be included as they cause MALFORMED_REQUEST_JSON errors.
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
        // In production, this would redirect and exit. Simulate by throwing exception.
        throw new Exception("Redirect to $page with message: $message");
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
$applePaymentData = [
    'data' => 'encrypted-data',
    'signature' => 'signature',
    'header' => ['ephemeralPublicKey' => 'abc'],
    'version' => 'EC_v1',
];

$applePayload = [
    'orderID' => 'TEST-ORDER-ID',
    'token' => [
        'paymentData' => $applePaymentData,
        'paymentMethod' => ['type' => 'credit'],
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

if ($normalizedPayload['token'] !== json_encode($applePaymentData)) {
    fwrite(STDERR, "FAIL: Apple Pay token JSON should encode paymentData only\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Apple Pay token encodes paymentData payload\n");
}

// Test 2: Only token field should be present (no contact fields)
$applePayloadWithContacts = [
    'orderID' => 'TEST-ORDER-ID',
    'token' => [
        'paymentData' => $applePaymentData,
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

// Verify only token is present - contact fields should be excluded
if (isset($normalizedWithContacts['name']) || 
    isset($normalizedWithContacts['email_address']) ||
    isset($normalizedWithContacts['billing_address'])) {
    fwrite(STDERR, "FAIL: Contact fields (name, email_address, billing_address) should NOT be in normalized payload\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Contact fields correctly excluded from payment source\n");
}

// Verify token is still present and properly encoded
if (!isset($normalizedWithContacts['token']) || !is_string($normalizedWithContacts['token'])) {
    fwrite(STDERR, "FAIL: Token should be present and JSON-encoded\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Token field present and properly encoded\n");
}

// Verify ONLY token field exists (no extra fields)
$allowedKeys = ['token'];
$actualKeys = array_keys($normalizedWithContacts);
$extraKeys = array_diff($actualKeys, $allowedKeys);
if (!empty($extraKeys)) {
    fwrite(STDERR, "FAIL: Only 'token' field should be present, found extra keys: " . implode(', ', $extraKeys) . "\n");
    $failures++;
} else {
    fwrite(STDOUT, "✓ Normalized payload contains only token field\n");
}

// Test 3: Missing token should trigger redirect
$payloadWithoutToken = [
    'orderID' => 'TEST-ORDER-ID',
    'wallet' => 'apple_pay',
];

try {
    $normalizedWithoutToken = $common->normalizeWalletPayloadPublic('apple_pay', $payloadWithoutToken, $errorMessages);
    fwrite(STDERR, "FAIL: Missing token should trigger redirect (no exception thrown)\n");
    $failures++;
} catch (Exception $e) {
    // Check that redirect was triggered
    if (count($paymentModule->redirects) === 0) {
        fwrite(STDERR, "FAIL: Missing token should trigger redirect\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Missing token correctly triggers redirect\n");
        // Reset redirects for next test
        $paymentModule->redirects = [];
    }
}

// Test 4: Non-Apple Pay payload unchanged
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
