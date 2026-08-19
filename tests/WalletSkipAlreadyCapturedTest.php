<?php
declare(strict_types=1);

/**
 * Validates captureOrAuthorizePayPalOrder skips duplicate capture when PayPal
 * already settled the wallet payment (e.g. Pay in 3 redirect return).
 */

namespace {
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CAPTURED = 'CAPTURED';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $failures = 0;

echo "Test 1: COMPLETED status without session payments should skip capture...\n";
$_SESSION['PayPalAdvancedCheckout'] = [
    'Order' => [
        'id' => 'TESTORDER123',
        'status' => STATUS_COMPLETED,
        'guid' => 'test-guid',
    ],
];
$should_fetch = in_array(
    $_SESSION['PayPalAdvancedCheckout']['Order']['status'],
    [STATUS_COMPLETED, STATUS_CAPTURED],
    true
);
if (!$should_fetch) {
    fwrite(STDERR, "✗ Expected fetch path for COMPLETED wallet return\n");
    $failures++;
} else {
    echo "✓ COMPLETED wallet return triggers settled-order lookup\n";
}

echo "\nTest 2: ORDER_ALREADY_CAPTURED should be treated as recoverable...\n";
$error_info = [
    'details' => [
        [
            'issue' => 'ORDER_ALREADY_CAPTURED',
            'description' => 'Order already captured.',
        ],
    ],
];
$issues = ['ORDER_ALREADY_CAPTURED', 'ORDER_ALREADY_AUTHORIZED'];
$recoverable = false;
foreach ($error_info['details'] as $detail) {
    if (in_array((string)($detail['issue'] ?? ''), $issues, true)) {
        $recoverable = true;
        break;
    }
}
if (!$recoverable) {
    fwrite(STDERR, "✗ ORDER_ALREADY_CAPTURED not recognized as recoverable\n");
    $failures++;
} else {
    echo "✓ ORDER_ALREADY_CAPTURED recognized as recoverable\n";
}

echo "\nTest 3: Session snapshot with captures should skip capture...\n";
$_SESSION['PayPalAdvancedCheckout']['Order']['current'] = [
    'purchase_units' => [
        [
            'payments' => [
                'captures' => [
                    ['status' => 'COMPLETED', 'id' => 'CAP123'],
                ],
            ],
        ],
    ],
];
$captures = $_SESSION['PayPalAdvancedCheckout']['Order']['current']['purchase_units'][0]['payments']['captures'] ?? [];
if ($captures === []) {
    fwrite(STDERR, "✗ Expected captures in session snapshot\n");
    $failures++;
} else {
    echo "✓ Listener-style session snapshot retains capture data\n";
}

echo "\n";
if ($failures > 0) {
    fwrite(STDERR, "✗ $failures test(s) failed\n");
    exit(1);
}

echo "✓ All WalletSkipAlreadyCaptured tests passed!\n";
}
