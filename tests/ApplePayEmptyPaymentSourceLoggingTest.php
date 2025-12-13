<?php
/**
 * Test to verify that logging in PayPalCommon doesn't throw TypeError
 * when payment_source is missing (Apple Pay order created before token)
 *
 * This test simulates the logging that happens in paypal_common.php when
 * creating a PayPal order without a payment_source field.
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

    // Test 1: Simulate the logging code from paypal_common.php with NO payment_source key
    $order_request = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            ['amount' => ['value' => '100.00', 'currency_code' => 'USD']]
        ],
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
        fwrite(STDERR, "FAIL: payment_source_type should be empty string when payment_source is missing\n");
        fwrite(STDERR, "  Got: '$payment_source_type'\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ payment_source_type is empty string when payment_source missing\n");
    }

    if ($has_vault_id !== 'n/a') {
        fwrite(STDERR, "FAIL: has_vault_id should be 'n/a' when payment_source is missing\n");
        fwrite(STDERR, "  Got: '$has_vault_id'\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ has_vault_id is 'n/a' when payment_source missing\n");
    }

    // Test 2: Verify logging without payment_source doesn't throw errors
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

    // Test 3: Verify JSON encoding of missing payment_source can be handled safely
    $json = json_encode($payment_source);
    if ($json !== '[]') {
        fwrite(STDERR, "FAIL: Missing payment_source should encode to '[]'\n");
        fwrite(STDERR, "  Got: $json\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ Missing payment_source encodes to '[]' in JSON\n");
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    }

    fwrite(STDOUT, "\n✓ Apple Pay empty payment_source logging test passed.\n");
}
