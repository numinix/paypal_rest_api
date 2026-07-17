<?php
/**
 * Smoke checks that the admin subscriptions list no longer counts billing cycles
 * for every saved-card row before pagination, and that schema/backfill helpers
 * short-circuit after the first warm check.
 */

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/admin/paypalac_subscriptions.php');
$manager = file_get_contents($root . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php');
$sccr = file_get_contents($root . '/includes/classes/paypalacSavedCardRecurring.php');

function assert_true($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: $msg\n");
}

assert_true(
    strpos($admin, 'Defer expensive orders_status_history LIKE counts until after pagination') !== false,
    'admin defers cycle counts until after pagination'
);
assert_true(
    preg_match('/\$subscriptionRows = array_slice\(\$allSubscriptions[\s\S]*count_completed_billing_cycles/', $admin) === 1,
    'count_completed_billing_cycles runs only on the visible page'
);
assert_true(
    strpos($admin, "payments_completed'] = ((int) (\$scrFields['orders_id']") !== false,
    'full list uses cheap orders_id placeholder for payments_completed'
);
assert_true(
    strpos($manager, 'private static bool $schemaReady = false') !== false,
    'SubscriptionManager caches ensureSchema per request'
);
assert_true(
    strpos($manager, 'expirationBackfillNeeded') !== false,
    'SubscriptionManager gates expiration backfill'
);
assert_true(
    strpos($sccr, 'function saved_card_expiration_backfill_needed') !== false,
    'saved-card backfill has cheap probe helper'
);
assert_true(
    strpos($admin, 'saved_card_expiration_backfill_needed') !== false,
    'admin page uses saved-card backfill probe'
);

fwrite(STDOUT, "\nAll AdminSubscriptionsListPerfTest checks passed.\n");
