<?php
/**
 * Smoke checks for persisted subscription expiration_date support.
 */

$root = dirname(__DIR__);
$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "✓ $message\n";
        return;
    }
    echo "✗ $message\n";
    $failures++;
}

echo "Test 1: Helper compute functions exist...\n";
$helpers = file_get_contents($root . '/includes/functions/extra_functions/paypalac_subscription_functions.php');
assert_true(strpos($helpers, 'function paypalac_compute_subscription_expiration_date') !== false, 'paypalac_compute_subscription_expiration_date defined');
assert_true(strpos($helpers, 'function paypalac_add_billing_periods_to_date') !== false, 'paypalac_add_billing_periods_to_date defined');

echo "\nTest 2: SCCR schedule_payment persists expiration_date + date_added...\n";
$sccr = file_get_contents($root . '/includes/classes/paypalacSavedCardRecurring.php');
assert_true(strpos($sccr, 'ensure_saved_cards_recurring_expiration_date_column') !== false, 'ensure column helper present');
assert_true(strpos($sccr, 'backfill_saved_card_expiration_dates') !== false, 'backfill helper present');
assert_true(strpos($sccr, "fieldName' => 'expiration_date'") !== false || strpos($sccr, "fieldName' => 'expiration_date\"") !== false || strpos($sccr, "'expiration_date'") !== false, 'expiration_date written on schedule');
assert_true(strpos($sccr, "fieldName' => 'date_added'") !== false, 'date_added written on schedule');

echo "\nTest 3: SubscriptionManager sets/backfills expiration_date...\n";
$manager = file_get_contents($root . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php');
assert_true(strpos($manager, 'backfillExpirationDates') !== false, 'backfillExpirationDates present');
assert_true(strpos($manager, "record['expiration_date']") !== false, 'logSubscription writes expiration_date');

echo "\nTest 4: Admin prefers stored expiration_date...\n";
$admin = file_get_contents($root . '/admin/paypalac_subscriptions.php');
assert_true(strpos($admin, 'Prefer the persisted creation-time expiration_date') !== false, 'admin calculate prefers stored column');
assert_true(strpos($admin, 'backfill_saved_card_expiration_dates') !== false, 'admin triggers SCCR backfill');

echo "\nTest 5: Module version bumped to 1.3.19...\n";
$module = file_get_contents($root . '/includes/modules/payment/paypalac.php');
assert_true(strpos($module, "CURRENT_VERSION = '1.3.19'") !== false, 'CURRENT_VERSION is 1.3.19');

echo "\nTest 6: Runtime compute math...\n";
require_once $root . '/includes/functions/extra_functions/paypalac_subscription_functions.php';
$expiry = paypalac_compute_subscription_expiration_date('2026-06-09', 'YEAR', 1, 1);
assert_true($expiry === '2027-06-09', '1 year cycle from 2026-06-09 => 2027-06-09');
$indefinite = paypalac_compute_subscription_expiration_date('2026-06-09', 'YEAR', 1, 0);
assert_true($indefinite === null, '0 cycles => null (indefinite)');
$five = paypalac_compute_subscription_expiration_date('2026-07-13', 'YEAR', 1, 5);
assert_true($five === '2031-07-13', '5 year cycles from 2026-07-13 => 2031-07-13');

if ($failures > 0) {
    fwrite(STDERR, "\n$failures test(s) failed\n");
    exit(1);
}

echo "\nAll expiration date persistence checks passed.\n";
exit(0);
