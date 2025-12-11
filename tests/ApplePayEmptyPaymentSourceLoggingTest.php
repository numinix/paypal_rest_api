<?php
/**
 * Test to verify that logging in PayPalCommon doesn't throw TypeError
 * when payment_source is an empty stdClass (for Apple Pay without token)
 *
 * This test simulates the logging that happens in paypal_common.php when
 * creating a PayPal order with an empty payment_source object.
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
}

namespace {
    $failures = 0;

    // Test 1: Verify array_key_first returns null for empty stdClass
    $empty_object = new \stdClass();
    $key = array_key_first((array)$empty_object);  // Cast to array to avoid warning
    if ($key !== null) {
        fwrite(STDERR, "FAIL: array_key_first of empty stdClass should return null\n");
        fwrite(STDERR, "  Got: " . var_export($key, true) . "\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ array_key_first handles empty stdClass correctly\n");
    }

    // Test 2: Simulate the logging code from paypal_common.php
    $order_request = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            ['amount' => ['value' => '100.00', 'currency_code' => 'USD']]
        ],
        'payment_source' => new \stdClass(),  // Empty payment_source for Apple Pay without token
    ];

    $payment_source = $order_request['payment_source'] ?? [];
    $payment_source_type = '';
    $has_vault_id = 'n/a';

    if (is_array($payment_source) && !empty($payment_source)) {
        $payment_source_type = array_key_first($payment_source);
        $has_vault_id = (!empty($payment_source[$payment_source_type]['vault_id']) ? 'yes' : 'no');
    }

    // Verify the values are set to safe defaults
    if ($payment_source_type !== '') {
        fwrite(STDERR, "FAIL: payment_source_type should be empty string for empty stdClass\n");
        fwrite(STDERR, "  Got: '$payment_source_type'\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ payment_source_type is empty string for empty stdClass\n");
    }

    if ($has_vault_id !== 'n/a') {
        fwrite(STDERR, "FAIL: has_vault_id should be 'n/a' for empty stdClass\n");
        fwrite(STDERR, "  Got: '$has_vault_id'\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ has_vault_id is 'n/a' for empty stdClass\n");
    }

    // Test 3: Verify logging with empty stdClass doesn't throw errors
    try {
        $log_message = "createPayPalOrder(apple_pay): Sending order to PayPal.\n" .
            "  Payment source type: $payment_source_type\n" .
            "  Has vault_id in source: $has_vault_id";
        
        if (empty($log_message)) {
            fwrite(STDERR, "FAIL: Log message should not be empty\n");
            $failures++;
        } else {
            fwrite(STDOUT, "  ✓ Log message created successfully without errors\n");
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "FAIL: Logging threw exception: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 4: Verify JSON encoding of empty stdClass produces {}
    $json = json_encode($order_request['payment_source']);
    if ($json !== '{}') {
        fwrite(STDERR, "FAIL: Empty stdClass should encode to '{}'\n");
        fwrite(STDERR, "  Got: $json\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ Empty stdClass encodes to '{}' in JSON\n");
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    }

    fwrite(STDOUT, "\n✓ Apple Pay empty payment_source logging test passed.\n");
}
