<?php
declare(strict_types=1);

/**
 * Verify saved-card recurring honors total_billing_cycles and admin expiry helpers.
 */

echo "Running Billing Cycles Limit Test...\n\n";

$basePath = dirname(__DIR__);
$failures = 0;

$cronFile = $basePath . '/cron/paypalac_saved_card_recurring.php';
$classFile = $basePath . '/includes/classes/paypalacSavedCardRecurring.php';
$adminFile = $basePath . '/admin/paypalac_subscriptions.php';
$moduleFile = $basePath . '/includes/modules/payment/paypalac.php';

echo "Test 1: Cron skips subscriptions with no remaining billing cycles...\n";
$cron = file_get_contents($cronFile);
if (strpos($cron, 'has_remaining_billing_cycles($payment_id, $payment_details)') === false) {
    echo "✗ Missing pre-charge remaining-cycles check\n";
    $failures++;
} elseif (strpos($cron, "skip_reason' => 'all billing cycles completed'") === false
    && strpos($cron, "skip_reason' => \"all billing cycles completed\"") === false
    && strpos($cron, 'all billing cycles completed') === false) {
    echo "✗ Missing skip path for completed billing cycles\n";
    $failures++;
} else {
    echo "✓ Cron checks remaining cycles before charging\n";
}

echo "\nTest 2: Cron stops scheduling after the final cycle...\n";
if (strpos($cron, 'has_remaining_billing_cycles($payment_id, $payment_details, 1)') === false) {
    echo "✗ Missing post-charge remaining-cycles check\n";
    $failures++;
} elseif (strpos($cron, 'Final billing cycle reached') === false) {
    echo "✗ Missing final-cycle completion log/path\n";
    $failures++;
} else {
    echo "✓ Cron clears next payment after final cycle\n";
}

echo "\nTest 3: Saved-card class exposes cycle helpers...\n";
$class = file_get_contents($classFile);
$helperOk = true;
foreach ([
    'function count_completed_billing_cycles',
    'function get_total_billing_cycles_limit',
    'function has_remaining_billing_cycles',
] as $needle) {
    if (strpos($class, $needle) === false) {
        echo "✗ Missing {$needle}\n";
        $failures++;
        $helperOk = false;
    }
}
if ($helperOk) {
    echo "✓ Cycle helper methods are present\n";
}

echo "\nTest 4: Admin page shows chargeable Next + calculated Expiry...\n";
$admin = file_get_contents($adminFile);
if (strpos($admin, 'function paypalac_subscription_next_payment_is_chargeable') === false
    || strpos($admin, 'function paypalac_calculate_subscription_expiry_date') === false
    || strpos($admin, 'paypalac_subscription_next_payment_is_chargeable($row)') === false
    || strpos($admin, 'Expiry:') === false) {
    echo "✗ Admin expiry/chargeable helpers are incomplete\n";
    $failures++;
} else {
    echo "✓ Admin helpers and Billing Details expiry display are present\n";
}

echo "\nTest 5: Module version bumped to 1.3.19...\n";
$module = file_get_contents($moduleFile);
if (strpos($module, "CURRENT_VERSION = '1.3.19'") === false) {
    echo "✗ CURRENT_VERSION was not bumped to 1.3.19\n";
    $failures++;
} else {
    echo "✓ CURRENT_VERSION is 1.3.19\n";
}

echo "\n=== Summary ===\n";
if ($failures > 0) {
    echo "✗ Failures: {$failures}\n";
    exit(1);
}

echo "✅ All billing-cycle limit tests passed!\n";
exit(0);
