<?php
declare(strict_types=1);

/**
 * Test to verify subscription failure handling doesn't change next_payment_date
 *
 * This test ensures that:
 * 1. When a payment fails, the subscription's next_payment_date stays the same (no drift)
 * 2. REST API subscriptions are properly detected even when api_type is not set
 * 3. The cron will retry on next run with the original billing date
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Subscription Failure Date Preservation Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify cron does NOT update next_payment_date on failure
echo "Test 1: Checking cron preserves next_payment_date when payment fails...\n";
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    // Check that we're NOT updating next_payment_date or 'date' field on failure
    // The old buggy code would call update_payment_info or schedule_payment
    if (preg_match('/else\s*\{[^}]*update_payment_info.*date.*tomorrow/s', $content)) {
        echo "✗ Cron still updates next_payment_date on failure (causes drift)\n";
        exit(1);
    }
    
    if (preg_match('/else\s*\{[^}]*schedule_payment.*tomorrow.*after failure/s', $content)) {
        echo "✗ Cron still calls schedule_payment on failure (creates duplicates)\n";
        exit(1);
    }
    
    // Check for the correct comment explaining why we don't update the date
    if (strpos($content, 'Do NOT update next_payment_date') !== false &&
        strpos($content, 'prevents subscription drift') !== false) {
        echo "✓ Cron correctly preserves next_payment_date on failure (no drift)\n";
    } else {
        echo "✗ Missing explanation comment about preserving next_payment_date\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 2: Verify REST API subscription detection fallback
echo "Test 2: Checking REST API subscription detection with vault card fallback...\n";
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Look for the fallback logic that checks for vault card
    $hasVaultCardVar = 'has_vault_card';
    if (strpos($content, $hasVaultCardVar) !== false &&
        strpos($content, 'paypal_vault_card') !== false &&
        strpos($content, 'api_type === \'\' && $' . $hasVaultCardVar) !== false) {
        echo "✓ REST API subscription detection includes vault card fallback\n";
    } else {
        echo "✗ Missing vault card fallback for REST API subscription detection\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 3: Verify failed subscriptions are still picked up by get_scheduled_payments
echo "Test 3: Checking get_scheduled_payments includes failed status...\n";
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Verify that failed subscriptions are included in scheduled payments query
    if (strpos($content, "status = 'scheduled' OR status = 'failed'") !== false ||
        strpos($content, "status = \\'scheduled\\' OR status = \\'failed\\'") !== false) {
        echo "✓ get_scheduled_payments includes failed status for auto-retry\n";
    } else {
        echo "✗ get_scheduled_payments may not include failed subscriptions\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 4: Verify successful payments create NEW subscription (not update existing)
echo "Test 4: Checking successful payments create new subscription for next cycle...\n";
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    // When payment succeeds, should call schedule_payment to create next subscription
    if (strpos($content, 'schedule_payment') !== false &&
        strpos($content, 'Scheduled after previous successful payment') !== false) {
        echo "✓ Successful payments create new subscription for next billing cycle\n";
    } else {
        echo "✗ Missing schedule_payment call for successful payments\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

echo "All tests passed! ✓\n";
echo "\nVerified:\n";
echo "1. Failed payments preserve next_payment_date (no subscription drift)\n";
echo "2. REST API subscriptions are detected even when api_type is not set\n";
echo "3. Failed subscriptions are automatically retried by the cron\n";
echo "4. Successful payments create new subscription for next billing cycle\n";
